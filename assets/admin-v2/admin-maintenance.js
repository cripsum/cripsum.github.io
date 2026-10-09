/*
 * Pannello admin: Manutenzione (gruppo "Sito").
 *
 * Chiude il sito a tutti tranne a chi sta nell'elenco, con un motivo che
 * legge chi arriva e, se si vuole, l'ora in cui si pensa di riaprire. Lo
 * stato sta nel database (tabella site_maintenance); qui c'e' solo il
 * modulo. Tutto passa da /api/admin/maintenance.php.
 *
 * Chi salva resta sempre nell'elenco: non ci si chiude fuori da soli. Gli
 * admin che non sono owner vedono lo stato ma non possono cambiarlo.
 */
(() => {
    'use strict';

    const A = window.CripsumAdmin;
    if (!A) return;

    const { api, confirmBox, showToast, emptyState, setLoading } = A;
    const e = A.escapeHtml;
    const $ = (sel, root = document) => root.querySelector(sel);

    const root = $('[data-maintenance-admin]');
    if (!root) return;

    let view = null;        // l'ultima risposta del server
    let allowed = [];       // l'elenco in lavorazione: [{ id, username, ruolo, me }]
    let searchTimer = null;
    let searchSeq = 0;

    const pad = (n) => String(n).padStart(2, '0');
    /** Secondi dall'epoca → valore di un <input type="datetime-local">, nell'ora di chi guarda. */
    const toLocalInput = (seconds) => {
        if (!seconds) return '';
        const d = new Date(seconds * 1000);
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    };
    const fromLocalInput = (value) => {
        const time = value ? new Date(value).getTime() : NaN;
        return Number.isFinite(time) ? Math.floor(time / 1000) : null;
    };
    const when = (seconds) => new Date(seconds * 1000).toLocaleString('it-IT', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' });

    const markNav = (on) => {
        const button = $('[data-admin-nav] [data-section="maintenance"]');
        if (button) button.classList.toggle('has-alert', on);
    };

    const chip = (user, editable) => `
        <span class="admin-maint__chip${user.me ? ' is-me' : ''}">
            <img src="/includes/get_pfp.php?id=${Number(user.id)}" alt="">
            <span>${e(user.username)}${user.me ? ' <small>tu</small>' : ''}</span>
            ${editable && !user.me ? `<button type="button" data-maint-remove="${Number(user.id)}" aria-label="Togli ${e(user.username)}"><i class="fa-solid fa-xmark"></i></button>` : ''}
        </span>`;

    const renderChips = () => {
        const box = $('[data-maint-chips]', root);
        if (!box) return;
        box.innerHTML = allowed.map((user) => chip(user, view.can_edit)).join('');
        const count = $('[data-maint-count]', root);
        if (count) count.textContent = `${allowed.length} su ${view.limits.allowed}`;
    };

    const render = () => {
        const s = view.state;
        const on = !!s.enabled;
        const locked = view.can_edit ? '' : ' disabled';
        markNav(on);

        root.innerHTML = `
            <div class="admin-maint">
                <div class="admin-maint__status ${on ? 'is-on' : 'is-off'}" role="status">
                    <span class="admin-maint__dot" aria-hidden="true"></span>
                    <div>
                        <strong>${on ? 'Il sito è in manutenzione' : 'Il sito è aperto'}</strong>
                        <small>${on
                            ? `Chiuso da ${e(when(s.since))}${s.updated_by ? ` · attivata da ${e(s.updated_by)}` : ''}. Lo vede solo chi sta nell'elenco qui sotto.`
                            : 'Tutti possono entrare. Da qui lo chiudi, scrivi perché e scegli chi può continuare a usarlo.'}</small>
                    </div>
                    ${on && view.can_edit ? '<button type="button" class="admin-btn admin-btn--primary" data-maint-off><i class="fa-solid fa-lock-open"></i> Riapri il sito</button>' : ''}
                </div>

                ${view.can_edit ? '' : '<p class="admin-maint__note"><i class="fa-solid fa-lock"></i> Solo l\'owner può cambiare la manutenzione: qui la vedi soltanto.</p>'}
                ${view.ready ? '' : '<p class="admin-maint__note is-warn"><i class="fa-solid fa-triangle-exclamation"></i> Manca la tabella <code>site_maintenance</code>: applica la migrazione <code>2026_10_09_site_maintenance.sql</code>, poi ricarica.</p>'}

                <form class="admin-maint__form" data-maint-form novalidate>
                    <div class="admin-field">
                        <label for="maintReasonIt">Motivo</label>
                        <textarea id="maintReasonIt" name="reason_it" maxlength="${Number(view.limits.reason)}" placeholder="Per esempio: stiamo aggiornando il database della Lootbox."${locked}>${e(s.reason_it)}</textarea>
                        <small class="admin-maint__hint"><span>Lo legge chi arriva sul sito mentre è chiuso.</span><span data-maint-left></span></small>
                    </div>
                    <div class="admin-field">
                        <label for="maintReasonEn">Motivo in inglese <em>facoltativo</em></label>
                        <textarea id="maintReasonEn" name="reason_en" maxlength="${Number(view.limits.reason)}" placeholder="Se lo lasci vuoto, chi legge in inglese vede quello in italiano."${locked}>${e(s.reason_en)}</textarea>
                    </div>
                    <div class="admin-field">
                        <label for="maintUntil">Riapertura prevista <em>facoltativa</em></label>
                        <input type="datetime-local" id="maintUntil" name="until" value="${e(toLocalInput(s.until))}"${locked}>
                        <small class="admin-maint__hint"><span>Compare nella pagina come «torniamo verso le…». Non riapre il sito da sola: quello lo fai tu.</span></small>
                    </div>
                    <div class="admin-field">
                        <label for="maintSearch">Chi può entrare <em data-maint-count></em></label>
                        <div class="admin-maint__chips" data-maint-chips></div>
                        ${view.can_edit ? `
                        <div class="admin-maint__picker">
                            <input type="search" id="maintSearch" class="admin-input" placeholder="Cerca un utente per nome, email o #ID" autocomplete="off" data-maint-search>
                            <div class="admin-maint__suggest" data-maint-suggest hidden></div>
                        </div>` : ''}
                        <small class="admin-maint__hint"><span>Solo loro vedono il sito mentre è chiuso, dopo aver fatto il login. Tu ci sei sempre.</span></small>
                    </div>
                    <div class="admin-maint__actions">
                        <button type="button" class="admin-btn" data-maint-preview><i class="fa-solid fa-eye"></i> Anteprima</button>
                        ${view.can_edit ? (on
                            ? '<button type="submit" class="admin-btn"><i class="fa-solid fa-floppy-disk"></i> Salva le modifiche</button>'
                            : '<button type="submit" class="admin-btn admin-btn--danger"><i class="fa-solid fa-screwdriver-wrench"></i> Metti il sito in manutenzione</button>') : ''}
                    </div>
                </form>

                <p class="admin-maint__note"><i class="fa-solid fa-life-ring"></i> Se resti chiuso fuori, da phpMyAdmin: <code>UPDATE site_maintenance SET enabled = 0;</code></p>
            </div>`;

        renderChips();
        updateLeft();
    };

    const updateLeft = () => {
        const area = $('#maintReasonIt', root);
        const left = $('[data-maint-left]', root);
        if (area && left) left.textContent = `${area.value.length} / ${view.limits.reason}`;
    };

    const payload = (enabled) => ({
        enabled,
        reason_it: $('#maintReasonIt', root).value,
        reason_en: $('#maintReasonEn', root).value,
        until: fromLocalInput($('#maintUntil', root).value),
        allowed: allowed.map((user) => user.id),
    });

    const save = async (enabled, message) => {
        try {
            view = await api('maintenance.php', { method: 'POST', body: payload(enabled) });
            allowed = view.state.allowed.slice();
            render();
            showToast(message);
        } catch (error) {
            showToast(error.message, true);
            const field = /motivo/i.test(error.message) ? $('#maintReasonIt', root) : (/riapertura/i.test(error.message) ? $('#maintUntil', root) : null);
            if (field) field.focus();
        }
    };

    const load = async () => {
        setLoading(root);
        try {
            view = await api('maintenance.php');
            allowed = view.state.allowed.slice();
            render();
        } catch (error) {
            root.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Manutenzione non caricata', error.message);
        }
    };

    const hideSuggest = () => {
        const box = $('[data-maint-suggest]', root);
        if (box) { box.hidden = true; box.innerHTML = ''; }
    };

    const search = async (query) => {
        const box = $('[data-maint-suggest]', root);
        if (!box) return;
        const seq = ++searchSeq;
        try {
            const data = await api(`get_users.php?${new URLSearchParams({ q: query, limit: 10 })}`);
            if (seq !== searchSeq) return;
            const taken = new Set(allowed.map((user) => user.id));
            const users = (data.users || []).filter((user) => !taken.has(Number(user.id))).slice(0, 6);
            box.innerHTML = users.length
                ? users.map((user) => `
                    <button type="button" data-maint-add="${Number(user.id)}" data-username="${e(user.username)}" data-ruolo="${e(user.ruolo || 'utente')}">
                        <img src="/includes/get_pfp.php?id=${Number(user.id)}" alt="">
                        <span>${e(user.username)}</span>
                        <small>#${Number(user.id)}${user.ruolo && user.ruolo !== 'utente' ? ` · ${e(user.ruolo)}` : ''}</small>
                    </button>`).join('')
                : '<p>Nessun utente trovato.</p>';
            box.hidden = false;
        } catch (error) {
            if (seq === searchSeq) hideSuggest();
        }
    };

    root.addEventListener('input', (event) => {
        if (event.target.id === 'maintReasonIt') updateLeft();
        if (event.target.matches('[data-maint-search]')) {
            clearTimeout(searchTimer);
            const query = event.target.value.trim();
            if (query.length < 2) { searchSeq += 1; hideSuggest(); return; }
            searchTimer = setTimeout(() => search(query), 240);
        }
    });

    root.addEventListener('keydown', (event) => {
        // Invio nella ricerca sceglie il primo risultato invece di mandare il modulo.
        if (event.target.matches('[data-maint-search]') && event.key === 'Enter') {
            event.preventDefault();
            const first = $('[data-maint-suggest] [data-maint-add]', root);
            if (first) first.click();
        }
        if (event.target.matches('[data-maint-search]') && event.key === 'Escape') hideSuggest();
    });

    root.addEventListener('click', (event) => {
        const add = event.target.closest('[data-maint-add]');
        if (add) {
            if (allowed.length >= view.limits.allowed) { showToast(`Al massimo ${view.limits.allowed} utenti.`, true); return; }
            allowed.push({ id: Number(add.dataset.maintAdd), username: add.dataset.username, ruolo: add.dataset.ruolo, me: false });
            renderChips();
            hideSuggest();
            const input = $('[data-maint-search]', root);
            if (input) { input.value = ''; input.focus(); }
            return;
        }

        const remove = event.target.closest('[data-maint-remove]');
        if (remove) {
            const id = Number(remove.dataset.maintRemove);
            allowed = allowed.filter((user) => user.id !== id);
            renderChips();
            return;
        }

        if (event.target.closest('[data-maint-preview]')) {
            const data = payload(true);
            const params = new URLSearchParams({ preview: 1, lang: 'it', reason_it: data.reason_it, reason_en: data.reason_en, until: data.until || '' });
            window.open(`/api/admin/maintenance.php?${params}`, '_blank', 'noopener');
            return;
        }

        if (event.target.closest('[data-maint-off]')) {
            save(false, 'Sito riaperto.');
        }
    });

    root.addEventListener('submit', (event) => {
        if (!event.target.matches('[data-maint-form]')) return;
        event.preventDefault();
        if (!view || !view.can_edit) return;

        if (view.state.enabled) {
            save(true, 'Manutenzione aggiornata.');
            return;
        }

        const others = allowed.filter((user) => !user.me).map((user) => e(user.username));
        confirmBox('Chiudere il sito?', `
            <p class="admin-muted">Da subito chi arriva vede la pagina di manutenzione, anche chi è già dentro.</p>
            <p class="admin-muted">Continuano a entrare: <b>tu</b>${others.length ? ', ' + others.map((name) => `<b>${name}</b>`).join(', ') : ''}.</p>`,
            () => save(true, 'Sito in manutenzione.'));
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.admin-maint__picker')) hideSuggest();
    });

    A.registerSection('maintenance', load);
})();
