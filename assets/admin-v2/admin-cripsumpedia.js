/*
 * Pannello admin: Cripsumpedia (gruppo "Community", fase 4).
 *
 * Tre schede: «Da approvare» (la coda: ogni proposta si apre nella pagina
 * Da approvare della Cripsumpedia, dove ci sono le differenze e i pulsanti),
 * «Voci» (tutte, anche bozze e nascoste: aprire, modificare, nascondere,
 * rimettere online, cancellare per sempre solo l'owner) e «Impostazioni»
 * (chi può proporre, chi è bloccato, import da Chi siamo).
 *
 * Il vecchio cripsumpedia/admin.php porta qui: /it/admin#cripsumpedia apre
 * questa sezione. Tutto passa da /api/admin/cripsumpedia.php.
 */
(() => {
    'use strict';

    const A = window.CripsumAdmin;
    if (!A) return;

    const { api, confirmBox, showToast, emptyState, setLoading } = A;
    const e = A.escapeHtml;
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const root = $('[data-cripsumpedia-admin]');
    if (!root) return;

    const state = { tab: 'pending', filter: 'all', q: '', page: 1, view: null, entries: null, loading: false };
    const get = (params) => api(`cripsumpedia.php?${new URLSearchParams(params)}`);
    const post = (action, body = {}) => api('cripsumpedia.php', { method: 'POST', body: { action, ...body } });

    const relative = new Intl.RelativeTimeFormat('it', { numeric: 'auto', style: 'short' });
    const ago = (value) => {
        const time = new Date(String(value).replace(' ', 'T')).getTime();
        if (!Number.isFinite(time)) return '';
        const seconds = Math.max(0, (Date.now() - time) / 1000);
        if (seconds < 60) return 'adesso';
        if (seconds < 3600) return relative.format(-Math.round(seconds / 60), 'minute');
        if (seconds < 86400) return relative.format(-Math.round(seconds / 3600), 'hour');
        if (seconds < 30 * 86400) return relative.format(-Math.round(seconds / 86400), 'day');
        return A.formatDate(value);
    };

    // Il numero rosso accanto a «Cripsumpedia» nel menu.
    const paintNav = (pending) => {
        const button = $('[data-admin-nav] [data-section="cripsumpedia"]');
        if (!button) return;
        let badge = $('.cmod-navcount', button);
        if (!pending) {
            badge?.remove();
            return;
        }
        if (!badge) {
            badge = document.createElement('em');
            badge.className = 'cmod-navcount';
            button.appendChild(badge);
        }
        badge.textContent = pending > 99 ? '99+' : String(pending);
        badge.title = `${pending} da approvare`;
    };

    const TABS = [['pending', 'Da approvare'], ['entries', 'Voci'], ['settings', 'Impostazioni']];
    const FILTERS = [['all', 'Tutte'], ['published', 'Pubblicate'], ['draft', 'Bozze'], ['hidden', 'Nascoste']];
    const STATUS = {
        published: '<span class="admin-badge admin-badge--success">Pubblicata</span>',
        draft: '<span class="admin-badge">Bozza</span>',
        hidden: '<span class="admin-badge admin-badge--danger">Nascosta</span>',
    };

    // ── Disegno ─────────────────────────────────────────────────────────────

    const tabsHtml = () => {
        const v = state.view || {};
        return `<div class="cmod-tabs cpa-tabs" role="tablist">${TABS.map(([tab, label]) => {
            const count = tab === 'pending' ? Number(v.pending_count || 0) : 0;
            return `<button type="button" role="tab" class="${tab === state.tab ? 'is-active' : ''}" aria-selected="${tab === state.tab}" data-cpa-tab="${tab}">${label}${count ? `<em class="is-red">${count}</em>` : ''}</button>`;
        }).join('')}</div>`;
    };

    const pendingHtml = () => {
        const v = state.view;
        if (!v.pending.length) {
            return emptyState('fa-solid fa-circle-check', 'Niente da approvare', v.settings.proposals_open
                ? 'Quando qualcuno propone una voce o una modifica, la trovi qui e ti arriva un avviso.'
                : 'Le proposte sono chiuse: per ora scrive solo lo staff. Le apri da «Impostazioni».');
        }
        return `<div class="admin-stack">${v.pending.map((item) => `
            <a class="admin-row-card admin-row-card--link" href="${e(item.url)}">
                <div class="admin-row-main">
                    <img class="admin-avatar" src="/includes/get_pfp.php?id=${Number(item.author_id)}" alt="">
                    <div>
                        <div class="admin-row-title">${e(item.title)}</div>
                        <div class="admin-row-sub">${e(item.author || 'account eliminato')} · ${item.kind === 'new' ? `${e(item.type_label.toLowerCase())} nuova` : 'modifica'} · ${e(ago(item.created_at))}${item.summary ? ` · «${e(item.summary)}»` : ''}</div>
                    </div>
                </div>
                <span class="admin-btn admin-btn--small">Apri <i class="fa-solid fa-arrow-right"></i></span>
            </a>`).join('')}</div>
            <p class="admin-muted cpa-note"><i class="fa-solid fa-circle-info"></i> Si apre la pagina «Da approvare»: lì vedi cosa cambia, campo per campo, e approvi, sistemi o rifiuti con un motivo che arriva nella Posta di chi l'ha scritta.</p>`;
    };

    const entriesHtml = () => {
        const list = state.entries;
        const filters = `<div class="cpa-bar">
            <div class="admin-seg cpa-seg" role="radiogroup" aria-label="Quali voci">${FILTERS.map(([value, label]) => `<button type="button" class="${state.filter === value ? 'is-active' : ''}" aria-pressed="${state.filter === value}" data-cpa-filter="${value}">${label}${value !== 'all' && state.view?.counts?.[value] ? ` <small>${Number(state.view.counts[value])}</small>` : ''}</button>`).join('')}</div>
            <input type="search" class="admin-input cpa-search" placeholder="Cerca per titolo" value="${e(state.q)}" data-cpa-search>
            <a class="admin-btn admin-btn--primary" href="${e(state.view.write_url)}" target="_blank" rel="noopener"><i class="fa-solid fa-pen"></i> Scrivi una voce</a>
        </div>`;
        if (!list) return `${filters}<p class="admin-muted cmod-note">Caricamento…</p>`;
        if (!list.entries.length) return `${filters}${emptyState('fa-solid fa-book-open', 'Nessuna voce', state.q ? 'Nessun risultato per questa ricerca.' : '')}`;

        const rows = list.entries.map((item) => {
            const actions = [
                item.status !== 'hidden' ? `<a class="admin-btn admin-btn--small" href="${e(item.url)}" target="_blank" rel="noopener" title="Apri"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>` : '',
                item.status !== 'hidden' ? `<a class="admin-btn admin-btn--small" href="${e(item.edit_url)}" target="_blank" rel="noopener"><i class="fa-solid fa-pen"></i> Modifica</a>` : '',
                `<a class="admin-btn admin-btn--small" href="${e(item.history_url)}" target="_blank" rel="noopener"><i class="fa-solid fa-clock-rotate-left"></i> Cronologia</a>`,
                item.status === 'hidden'
                    ? `<button type="button" class="admin-btn admin-btn--small" data-cpa-unhide="${item.id}"><i class="fa-solid fa-eye"></i> Rimetti online</button>`
                    : `<button type="button" class="admin-btn admin-btn--small" data-cpa-hide="${item.id}" data-title="${e(item.title)}"><i class="fa-solid fa-eye-slash"></i> Nascondi</button>`,
                item.status === 'hidden' && state.view.is_owner ? `<button type="button" class="admin-btn admin-btn--small admin-btn--danger" data-cpa-purge="${item.id}" data-title="${e(item.title)}"><i class="fa-solid fa-trash"></i></button>` : '',
            ].join('');
            return `
                <div class="admin-row-card">
                    <div class="admin-row-main">
                        ${A.thumb(item.image, 'fa-solid fa-book-open')}
                        <div>
                            <div class="admin-row-title">${e(item.title)}</div>
                            <div class="admin-row-sub">${e(item.type_label)} · ${STATUS[item.status] || ''} · ${e(ago(item.updated_at))}</div>
                        </div>
                    </div>
                    <div class="admin-row-actions">${actions}</div>
                </div>`;
        }).join('');

        const p = list.pagination;
        const pages = p.pages > 1 ? `<div class="cmod-pages">
            <button type="button" class="admin-btn admin-btn--small" data-cpa-page="${p.page - 1}" ${p.page <= 1 ? 'disabled' : ''} aria-label="Pagina precedente"><i class="fa-solid fa-chevron-left"></i></button>
            <span>${p.page} / ${p.pages}</span>
            <button type="button" class="admin-btn admin-btn--small" data-cpa-page="${p.page + 1}" ${p.page >= p.pages ? 'disabled' : ''} aria-label="Pagina successiva"><i class="fa-solid fa-chevron-right"></i></button>
        </div>` : '';
        return `${filters}<div class="admin-stack">${rows}</div>${pages}`;
    };

    const settingsHtml = () => {
        const v = state.view;
        const s = v.settings;
        const blocked = v.blocked.length
            ? `<div class="admin-stack">${v.blocked.map((row) => `
                <div class="admin-row-card">
                    <div class="admin-row-main">
                        <img class="admin-avatar" src="/includes/get_pfp.php?id=${Number(row.user_id)}" alt="">
                        <div>
                            <div class="admin-row-title">${e(row.username || 'account eliminato')}</div>
                            <div class="admin-row-sub">${row.reason ? `${e(row.reason)} · ` : ''}da ${e(row.by || '—')} · ${e(A.formatDate(row.created_at))}</div>
                        </div>
                    </div>
                    <button type="button" class="admin-btn admin-btn--small" data-cpa-unblock="${Number(row.user_id)}">Sblocca</button>
                </div>`).join('')}</div>`
            : '<p class="admin-muted">Nessuno: chi ha un account può proporre (se le proposte sono aperte).</p>';

        const people = v.chisiamo;
        const importBox = people.length
            ? `<p class="admin-muted">${people.length === 1 ? 'Una persona' : `${people.length} persone`} di Chi siamo non ${people.length === 1 ? 'ha' : 'hanno'} ancora una voce: ${people.slice(0, 12).map((p) => `<b>${e(p.name)}</b>`).join(', ')}${people.length > 12 ? '…' : ''}.</p>
               <p class="admin-muted">Diventano bozze con foto, frase e profilo collegato: le controlli, le completi e le pubblichi da «Voci» → Bozze. Si può rifare: chi ha già una voce si salta.</p>
               <button type="button" class="admin-btn" data-cpa-import><i class="fa-solid fa-file-import"></i> Importa come bozze</button>`
            : '<p class="admin-muted">Tutte le persone visibili di Chi siamo hanno già una voce.</p>';

        return `
            <div class="cpa-settings">
                <form class="cpa-card" data-cpa-settings novalidate>
                    <h3>Chi può scrivere</h3>
                    <label class="cpa-switch"><input type="checkbox" name="proposals_open" ${s.proposals_open ? 'checked' : ''}><span><b>Proposte aperte a tutti</b><small>Chi ha un account propone voci e modifiche; vanno online solo quando lo staff le approva. Chiuse: scrive solo lo staff.</small></span></label>
                    <div class="admin-form-grid">
                        <div class="admin-field"><label for="cpaDays">Giorni minimi di account</label><input type="number" id="cpaDays" class="admin-input" name="min_account_days" min="0" max="365" value="${Number(s.min_account_days)}"><small class="admin-muted">Contro gli account creati apposta. 0 = nessun limite.</small></div>
                        <div class="admin-field"><label for="cpaMax">Proposte in attesa per persona</label><input type="number" id="cpaMax" class="admin-input" name="max_pending" min="1" max="20" value="${Number(s.max_pending)}"><small class="admin-muted">Oltre, deve aspettare che lo staff le legga.</small></div>
                    </div>
                    ${v.uploads_ready ? '' : '<p class="cpa-warn"><i class="fa-solid fa-triangle-exclamation"></i> Per far caricare foto a chi non è staff esegui <code>migrations/2026_10_17_cripsumpedia_fase4.sql</code>. Senza, possono scrivere ma non caricare foto.</p>'}
                    <button type="submit" class="admin-btn admin-btn--primary"><i class="fa-solid fa-floppy-disk"></i> Salva</button>
                </form>

                <section class="cpa-card">
                    <h3>Chi non può proporre</h3>
                    ${blocked}
                    <form class="cpa-block" data-cpa-block novalidate>
                        <input class="admin-input" name="username" placeholder="Username" autocomplete="off" required>
                        <input class="admin-input" name="reason" placeholder="Motivo (lo vedete solo voi)" maxlength="300">
                        <button type="submit" class="admin-btn"><i class="fa-solid fa-ban"></i> Blocca</button>
                    </form>
                </section>

                <section class="cpa-card">
                    <h3>Persone da Chi siamo</h3>
                    ${importBox}
                    <div data-cpa-imported></div>
                </section>
            </div>`;
    };

    const render = () => {
        if (!state.view) return;
        paintNav(Number(state.view.pending_count || 0));
        const body = state.tab === 'pending' ? pendingHtml() : state.tab === 'entries' ? entriesHtml() : settingsHtml();
        root.innerHTML = `${tabsHtml()}<div class="cpa-body">${body}</div>`;
    };

    // ── Dati ────────────────────────────────────────────────────────────────

    const loadEntries = async () => {
        state.entries = null;
        render();
        try {
            state.entries = await get({ action: 'entries', filter: state.filter, q: state.q, page: state.page });
        } catch (error) {
            state.entries = { entries: [], pagination: { page: 1, pages: 1, total: 0 } };
            showToast(error.message, true);
        }
        render();
    };

    const load = async () => {
        if (state.loading) return;
        state.loading = true;
        if (!state.view) setLoading(root, 3);
        try {
            state.view = await get({ action: 'overview' });
            render();
            if (state.tab === 'entries') await loadEntries();
        } catch (error) {
            root.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Cripsumpedia non disponibile', error.message);
        } finally {
            state.loading = false;
        }
    };

    // ── Azioni ──────────────────────────────────────────────────────────────

    let searchTimer = null;
    root.addEventListener('input', (event) => {
        if (!event.target.matches('[data-cpa-search]')) return;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.q = event.target.value.trim();
            state.page = 1;
            loadEntries().then(() => {
                const input = $('[data-cpa-search]', root);
                if (input) {
                    input.focus();
                    input.setSelectionRange(input.value.length, input.value.length);
                }
            });
        }, 300);
    });

    root.addEventListener('click', async (event) => {
        const el = (selector) => event.target.closest(selector);
        const tab = el('[data-cpa-tab]');
        if (tab) {
            state.tab = tab.dataset.cpaTab;
            if (state.tab === 'entries' && !state.entries) loadEntries();
            else render();
            return;
        }
        const filter = el('[data-cpa-filter]');
        if (filter) {
            state.filter = filter.dataset.cpaFilter;
            state.page = 1;
            loadEntries();
            return;
        }
        const page = el('[data-cpa-page]');
        if (page && !page.disabled) {
            state.page = Number(page.dataset.cpaPage);
            loadEntries();
            return;
        }

        const hide = el('[data-cpa-hide]');
        if (hide) {
            confirmBox('Nascondere la voce?', `<p class="admin-muted">«${e(hide.dataset.title)}» esce dal sito ma resta qui, tra le nascoste, con tutta la cronologia: la rimetti online quando vuoi.</p>`, async () => {
                await post('hide', { entry_id: Number(hide.dataset.cpaHide) });
                showToast('Voce nascosta.');
                await load();
            });
            return;
        }
        const unhide = el('[data-cpa-unhide]');
        if (unhide) {
            unhide.disabled = true;
            try {
                await post('unhide', { entry_id: Number(unhide.dataset.cpaUnhide) });
                showToast('Voce di nuovo online.');
                await load();
            } catch (error) {
                unhide.disabled = false;
                showToast(error.message, true);
            }
            return;
        }
        const purge = el('[data-cpa-purge]');
        if (purge) {
            confirmBox('Cancellare per sempre?', `<p class="admin-muted">«${e(purge.dataset.title)}» sparisce dal database con tutte le sue versioni, i collegamenti e le foto che usa solo lei. Non si può annullare.</p>`, async () => {
                await post('purge', { entry_id: Number(purge.dataset.cpaPurge) });
                showToast('Voce cancellata.');
                await load();
            });
            return;
        }
        const unblock = el('[data-cpa-unblock]');
        if (unblock) {
            unblock.disabled = true;
            try {
                await post('unblock', { user_id: Number(unblock.dataset.cpaUnblock) });
                showToast('Può di nuovo proporre voci.');
                await load();
            } catch (error) {
                unblock.disabled = false;
                showToast(error.message, true);
            }
            return;
        }
        const importButton = el('[data-cpa-import]');
        if (importButton) {
            importButton.disabled = true;
            try {
                const data = await post('import_chisiamo');
                showToast(data.message);
                await load();
                const box = $('[data-cpa-imported]', root);
                if (box && data.created.length) {
                    box.innerHTML = `<div class="admin-stack">${data.created.map((row) => `
                        <div class="admin-row-card"><div class="admin-row-main"><div class="admin-row-title">${e(row.title)}</div></div>
                        <a class="admin-btn admin-btn--small" href="${e(row.edit_url)}" target="_blank" rel="noopener"><i class="fa-solid fa-pen"></i> Completa e pubblica</a></div>`).join('')}</div>`;
                }
            } catch (error) {
                importButton.disabled = false;
                showToast(error.message, true);
            }
        }
    });

    root.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.target;
        const button = $('[type="submit"]', form);
        if (button) button.disabled = true;
        try {
            if (form.matches('[data-cpa-settings]')) {
                const data = await post('save_settings', {
                    proposals_open: form.elements.proposals_open.checked ? 1 : 0,
                    min_account_days: Number(form.elements.min_account_days.value),
                    max_pending: Number(form.elements.max_pending.value),
                });
                state.view.settings = data.settings;
                showToast(data.settings.proposals_open ? 'Salvato: le proposte sono aperte.' : 'Salvato: scrive solo lo staff.');
                render();
            } else if (form.matches('[data-cpa-block]')) {
                const data = await post('block', { username: form.elements.username.value, reason: form.elements.reason.value });
                showToast(data.message);
                await load();
            }
        } catch (error) {
            showToast(error.message, true);
        } finally {
            if (button) button.disabled = false;
        }
    });

    A.registerSection('cripsumpedia', load);

    // /it/admin#cripsumpedia (il vecchio pannello della Cripsumpedia porta qui).
    const openFromHash = () => {
        if (location.hash !== '#cripsumpedia') return;
        $('[data-admin-nav] [data-section="cripsumpedia"]')?.click();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', openFromHash);
    else openFromHash();
})();
