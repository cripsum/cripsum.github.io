/*
 * Pannello admin: sezione Utenti.
 *
 * La lista con i filtri e la scheda di un utente, divisa in schede: account,
 * economia, inventario, achievement, badge, moderazione e attivita'.
 *
 * La scheda legge sempre l'utente dal database (get_user_details.php) e
 * l'account salva solo i campi cambiati. Prima il form partiva dalla riga
 * della lista, che non ha le valute ne' NSFW, rich presence e 2FA: salvare un
 * utente gli azzerava i soldi e gli cambiava quelle impostazioni. Le valute
 * hanno un endpoint loro (adjust_user_balance.php) che somma e toglie sul
 * saldo del momento.
 *
 * Usa gli strumenti di admin.js (window.CripsumAdmin) come shop e gacha.
 */
(() => {
    'use strict';

    const A = window.CripsumAdmin;
    if (!A) return;

    const { api, openModal, confirmBox, showToast, setLoading, emptyState, pagination } = A;
    const e = A.escapeHtml;
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const ONLINE_REFRESH_MS = 30000;
    const PREMIUM_BONUS = 25000;
    const PREMIUM_BADGE_ID = 5;

    const RARITY_FALLBACK = {
        comune: { label: 'Comune', color: '#9ca3af' }, raro: { label: 'Raro', color: '#38bdf8' },
        epico: { label: 'Epico', color: '#c084fc' }, leggendario: { label: 'Leggendario', color: '#fbbf24' },
        speciale: { label: 'Speciale', color: '#ffffff' }, segreto: { label: 'Segreto', color: '#a855f7' },
        theone: { label: 'The One', color: '#60a5fa' },
    };
    const PITY_LABELS = { standard: 'Standard', evento: 'Evento', principiante: 'Principiante' };
    const ROLE_LABELS = { utente: 'Utente', admin: 'Admin', owner: 'Owner' };
    const FIELD_LABELS = {
        username: 'username', display_name: 'nome visualizzato', email: 'email', ruolo: 'ruolo',
        data_creazione: 'data di registrazione', email_verificata: 'email verificata', is_premium: 'Premium',
        nsfw: 'NSFW', richpresence: 'Rich Presence',
    };

    /* ── Formattazione ────────────────────────────────────────────────── */

    // In italiano il punto delle migliaia manca sotto 10.000 ("5000" accanto
    // a "125.200"): per i saldi si mette sempre.
    const numberFormat = new Intl.NumberFormat('it-IT', { useGrouping: 'always' });
    const num = (value) => numberFormat.format(Number(value || 0));
    const compact = (value) => {
        const n = Number(value || 0);
        if (Math.abs(n) >= 1e6) return `${(n / 1e6).toLocaleString('it-IT', { maximumFractionDigits: 1 })}M`;
        if (Math.abs(n) >= 1e4) return `${(n / 1e3).toLocaleString('it-IT', { maximumFractionDigits: 1 })}K`;
        return num(n);
    };
    const flag = (value) => (Number(value) === 1 ? 1 : 0);

    // Le date arrivano gia' nell'ora del sito: si riformatta la stringa senza
    // passare da Date, che la sposterebbe nel fuso del browser.
    const dateParts = (value) => String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
    const fmtDay = (value) => {
        const m = dateParts(value);
        return m ? `${m[3]}/${m[2]}/${m[1]}` : '—';
    };
    const fmtStamp = (value) => {
        const m = dateParts(value);
        return m ? `${m[3]}/${m[2]}/${m[1]}${m[4] ? ` ${m[4]}:${m[5]}` : ''}` : '—';
    };
    // Valore per <input type="datetime-local" step="1">, sempre con i secondi:
    // il browser li toglie quando sono :00, e senza normalizzare il confronto
    // vedrebbe una modifica che non c'e'.
    const inputDate = (value) => {
        const m = dateParts(value);
        return m ? `${m[1]}-${m[2]}-${m[3]}T${m[4] || '00'}:${m[5] || '00'}:${m[6] || '00'}` : '';
    };
    const timeAgo = (seconds) => {
        const s = Math.max(0, Number(seconds) || 0);
        const plural = (n, one, many) => `${n} ${n === 1 ? one : many} fa`;
        if (s < 60) return 'adesso';
        if (s < 3600) return `${Math.floor(s / 60)} min fa`;
        if (s < 86400) return `${Math.floor(s / 3600)} h fa`;
        if (s < 86400 * 30) return plural(Math.floor(s / 86400), 'giorno', 'giorni');
        if (s < 86400 * 365) return plural(Math.floor(s / (86400 * 30)), 'mese', 'mesi');
        return plural(Math.floor(s / (86400 * 365)), 'anno', 'anni');
    };

    const roleBadge = (role) => {
        const cls = role === 'owner' ? 'admin-badge--warning' : role === 'admin' ? 'admin-badge--info' : '';
        const icon = role === 'owner' ? 'fa-crown' : role === 'admin' ? 'fa-user-shield' : 'fa-user';
        return `<span class="admin-badge ${cls}"><i class="fa-solid ${icon}"></i>${e(ROLE_LABELS[role] || role || 'Utente')}</span>`;
    };
    const banBadge = (user) => (Number(user.isBannato) === 1
        ? `<span class="admin-badge admin-badge--danger"><i class="fa-solid fa-ban"></i>${user.banned_until ? 'Ban a tempo' : 'Bannato'}</span>`
        : '<span class="admin-badge admin-badge--success"><i class="fa-solid fa-check"></i>Attivo</span>');
    const premiumBadge = (user) => (flag(user.is_premium)
        ? '<span class="admin-badge admin-badge--premium"><img class="cr-premium-gem" src="/img/premium.svg" alt="">Premium</span>'
        : '');
    const onlineBadge = (user) => (user.is_online
        ? '<span class="admin-badge admin-badge--success"><span class="admin-online-dot" aria-hidden="true"></span>Online</span>'
        : '');
    const lastSeen = (user) => (user.seconds_since_active === null || user.seconds_since_active === undefined
        ? '<span class="admin-muted">Mai</span>'
        : `<b class="${user.is_online ? 'admin-text-online' : ''}">${timeAgo(user.seconds_since_active)}</b><div class="admin-row-sub">${fmtStamp(user.ultimo_accesso)}</div>`);
    const twofaActive = (user) => flag(user.twofa_enabled) && flag(user.twofa_has_secret);
    // Godos, Godo Shards e Frammenti hanno la loro immagine; l'icona resta
    // solo se una valuta non ne ha una.
    const currencyIcon = (c) => (c.image
        ? `<img class="admin-currency" src="${e(c.image)}" alt="">`
        : `<i class="${e(c.icon)}"></i>`);

    const rarityKey = (value) => {
        const v = String(value || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ\s_-]/g, '');
        const aliases = { common: 'comune', rare: 'raro', epic: 'epico', legendary: 'leggendario', special: 'speciale', secret: 'segreto', one: 'theone' };
        return aliases[v] || v;
    };
    const rarityPill = (value) => {
        const key = rarityKey(value);
        const def = (sheet?.rarities || RARITY_FALLBACK)[key];
        return `<span class="admin-rarity" style="--c:${e(def?.color || '#9ca3af')}">${e(def?.label || value || '—')}</span>`;
    };
    const image = (url, icon, cls = 'admin-thumb') => (url
        ? `<span class="${cls}"><img src="${e(A.assetUrl(url))}" alt="" loading="lazy" onerror="this.parentNode.classList.add('is-broken'); this.outerHTML='<i class=&quot;${icon}&quot;></i>';"></span>`
        : `<span class="${cls} admin-thumb--fallback"><i class="${icon}"></i></span>`);

    /* ── Pulsanti ─────────────────────────────────────────────────────── */

    // Conferma in due tempi sul pulsante stesso: il primo click lo arma, il
    // secondo (entro qualche secondo) esegue. Evita una modale sopra la
    // scheda, che con Bootstrap si sovrappone male.
    const twoStep = (button, label = 'Confermi?') => {
        if (button.dataset.armed === '1') {
            clearTimeout(Number(button.dataset.armTimer));
            button.dataset.armed = '';
            button.classList.remove('is-armed');
            button.innerHTML = button.dataset.armHtml;
            return true;
        }
        button.dataset.armed = '1';
        button.dataset.armHtml = button.innerHTML;
        button.classList.add('is-armed');
        button.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> ${e(label)}`;
        button.dataset.armTimer = String(setTimeout(() => {
            if (button.dataset.armed !== '1') return;
            button.dataset.armed = '';
            button.classList.remove('is-armed');
            button.innerHTML = button.dataset.armHtml;
        }, 4000));
        return false;
    };

    const busy = async (button, task) => {
        const html = button ? button.innerHTML : '';
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        }
        try {
            return await task();
        } catch (error) {
            showToast(error.message, true);
            return undefined;
        } finally {
            if (button && button.isConnected) {
                button.disabled = false;
                button.innerHTML = html;
            }
        }
    };

    const copyText = async (value) => {
        try {
            await navigator.clipboard.writeText(String(value));
            showToast('Copiato negli appunti.');
        } catch (error) {
            showToast('Copia non riuscita: il browser non lo permette.', true);
        }
    };

    /* ── Lista ────────────────────────────────────────────────────────── */

    const state = {
        page: 1, status: 'all', role: 'all', premium: 'all', sort: 'data_creazione', dir: 'DESC',
        online: false, q: '', rows: [], currencies: [],
    };
    // La scheda cambia l'utente: la lista si ricarica quando si chiude, non a
    // ogni modifica, cosi' non lampeggia sotto la finestra.
    let listStale = false;

    const walletCell = (user) => (state.currencies.length
        ? `<div class="admin-user-wallet-mini">${state.currencies.map((c) => `<span title="${e(c.label)}: ${num(user[c.key])}">${currencyIcon(c)}${compact(user[c.key])}</span>`).join('')}</div>`
        : '<span class="admin-muted">—</span>');

    const userRow = (user) => {
        const id = Number(user.id);
        const banned = Number(user.isBannato) === 1;
        return `
        <tr class="admin-user-row${banned ? ' is-banned' : ''}" data-user-row="${id}" tabindex="0" aria-label="Apri la scheda di ${e(user.username)}">
            <td data-label="Utente">
                <div class="admin-cell-user">
                    <span class="admin-avatar-wrap${user.is_online ? ' is-online' : ''}"><img class="admin-avatar" src="${e(user.avatar_url)}" alt="" loading="lazy"></span>
                    <div class="admin-cell-text">
                        <div class="admin-row-title">${e(user.username)} ${premiumBadge(user)}</div>
                        <div class="admin-row-sub">#${id} · ${e(user.email)}</div>
                    </div>
                </div>
            </td>
            <td data-label="Stato"><div class="admin-status-stack">${roleBadge(user.ruolo)}${banBadge(user)}${onlineBadge(user)}</div></td>
            <td data-label="Saldo">${walletCell(user)}</td>
            <td data-label="Collezione"><div class="admin-user-counts"><span><b>${compact(user.character_count)}</b> personaggi</span><span><b>${compact(user.achievement_count)}</b> achievement</span></div></td>
            <td data-label="Attività" class="admin-nowrap">${lastSeen(user)}<div class="admin-row-sub">iscritto il ${fmtDay(user.data_creazione)}</div></td>
            <td data-label="Azioni"><div class="admin-row-actions">
                <button type="button" class="admin-btn admin-btn--small admin-btn--primary" data-user-open="${id}"><i class="fa-solid fa-id-card"></i> Scheda</button>
                ${banned
                    ? `<button type="button" class="admin-btn admin-btn--small" data-user-unban="${id}" title="Sbanna"><i class="fa-solid fa-unlock"></i> Sbanna</button>`
                    : `<button type="button" class="admin-btn admin-btn--small admin-btn--danger" data-user-open="${id}" data-tab="moderation" title="Banna"><i class="fa-solid fa-ban"></i></button>`}
            </div></td>
        </tr>`;
    };

    const renderOnlineToggle = (data) => {
        const button = $('#usersOnlineToggle');
        if (!button) return;
        button.hidden = !!data && data.online_available === false;
        button.classList.toggle('is-active', state.online);
        button.setAttribute('aria-pressed', state.online ? 'true' : 'false');
        const count = $('#usersOnlineCount');
        if (count && data) count.textContent = compact(data.online_count);
    };

    const renderSummary = (pages) => {
        const box = $('#usersSummary');
        if (!box || !pages) return;
        const total = Number(pages.total || 0);
        box.textContent = `${num(total)} ${total === 1 ? 'utente' : 'utenti'}${state.q ? ` per «${state.q}»` : ''}${pages.pages > 1 ? ` · pagina ${pages.page} di ${pages.pages}` : ''}`;
    };

    // `silent` e' l'aggiornamento automatico: niente scheletro di
    // caricamento, altrimenti la tabella lampeggia ogni 30 secondi.
    const loadUsers = async ({ silent = false } = {}) => {
        const box = $('#usersTable');
        if (!box) return;
        const q = A.getQuery();
        if (q !== state.q) {
            state.q = q;
            state.page = 1;
        }
        if (!silent) setLoading(box);
        try {
            const params = new URLSearchParams({
                q: state.q, status: state.status, role: state.role, premium: state.premium,
                page: state.page, sort: state.sort, dir: state.dir, online: state.online ? 1 : 0, limit: 20,
            });
            const data = await api(`get_users.php?${params}`);
            state.rows = data.users || [];
            state.currencies = data.currencies || [];
            renderOnlineToggle(data);
            renderSummary(data.pagination);
            box.innerHTML = state.rows.length ? `
                <table class="admin-table admin-users-table">
                    <thead><tr><th>Utente</th><th>Stato</th><th>Saldo</th><th>Collezione</th><th>Ultimo accesso</th><th><span class="visually-hidden">Azioni</span></th></tr></thead>
                    <tbody>${state.rows.map(userRow).join('')}</tbody>
                </table>`
                : state.online
                    ? emptyState('fa-solid fa-moon', 'Nessuno online', `Nessun utente attivo negli ultimi ${Number(data.online_window) || 30} secondi con questi filtri.`)
                    : emptyState('fa-solid fa-users', 'Nessun utente trovato', 'Prova a cambiare ricerca o filtri. Per cercare un ID scrivi #123.');
            pagination('#usersPagination', data.pagination, (page) => { state.page = page; loadUsers(); });
        } catch (error) {
            if (silent) return;
            box.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Errore utenti', error.message);
        }
    };

    const usersSectionActive = () => !!document.querySelector('[data-section-panel="users"].is-active');

    const unbanFromList = (id) => {
        const user = state.rows.find((row) => Number(row.id) === id);
        confirmBox('Sbannare utente?', `<p class="admin-muted">${e(user ? user.username : `#${id}`)} potrà accedere di nuovo.</p>`, async () => {
            await api('unban_user.php', { method: 'POST', body: { id } });
            showToast('Utente sbannato.');
            loadUsers({ silent: true });
        });
    };

    const bindList = () => {
        const box = $('#usersTable');
        if (!box || box.dataset.bound === '1') return;
        box.dataset.bound = '1';

        box.addEventListener('click', (event) => {
            const unban = event.target.closest('[data-user-unban]');
            if (unban) return unbanFromList(Number(unban.dataset.userUnban));
            const open = event.target.closest('[data-user-open]');
            if (open) return openUser(Number(open.dataset.userOpen), open.dataset.tab || 'account');
            if (event.target.closest('a, button, input, select, label')) return;
            const row = event.target.closest('[data-user-row]');
            if (row) openUser(Number(row.dataset.userRow));
        });
        box.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || !event.target.matches('[data-user-row]')) return;
            openUser(Number(event.target.dataset.userRow));
        });

        const reload = () => { state.page = 1; loadUsers(); };
        $('#usersStatusFilter')?.addEventListener('change', (ev) => { state.status = ev.target.value; reload(); });
        $('#usersRoleFilter')?.addEventListener('change', (ev) => { state.role = ev.target.value; reload(); });
        $('#usersPremiumFilter')?.addEventListener('change', (ev) => { state.premium = ev.target.value; reload(); });
        $('#usersSortFilter')?.addEventListener('change', (ev) => {
            const [sort, dir] = ev.target.value.split(':');
            state.sort = sort;
            state.dir = dir === 'ASC' ? 'ASC' : 'DESC';
            reload();
        });
        $('#usersOnlineToggle')?.addEventListener('click', () => {
            state.online = !state.online;
            renderOnlineToggle(null);
            reload();
        });
    };

    // Con la vista online accesa la lista si aggiorna da sola, ma solo se la
    // sezione e' aperta, la scheda e' visibile e non c'e' una modale aperta.
    setInterval(() => {
        if (!state.online || !usersSectionActive()) return;
        if (document.visibilityState !== 'visible') return;
        if (document.querySelector('.admin-modal.show, .admin-modal.is-open')) return;
        loadUsers({ silent: true });
    }, ONLINE_REFRESH_MS);

    /* ── Scheda utente ────────────────────────────────────────────────── */

    const TABS = [
        { key: 'account', icon: 'fa-solid fa-user-pen', label: 'Account' },
        { key: 'economy', icon: 'fa-solid fa-coins', image: '/img/godos.png', label: 'Economia' },
        { key: 'inventory', icon: 'fa-solid fa-box-open', label: 'Inventario' },
        { key: 'achievements', icon: 'fa-solid fa-trophy', label: 'Achievement' },
        { key: 'badges', icon: 'fa-solid fa-certificate', label: 'Badge' },
        { key: 'moderation', icon: 'fa-solid fa-gavel', label: 'Moderazione' },
        { key: 'activity', icon: 'fa-solid fa-clock-rotate-left', label: 'Attività' },
    ];

    let sheet = null;
    let openToken = 0;

    const sheetRoot = () => $('#adminModalBody [data-user-sheet]');
    const panel = (key) => $(`[data-panel="${key}"]`, sheetRoot() || document);
    const canManage = () => !!sheet?.data.permissions?.can_manage;
    const lockAttr = () => (canManage() ? '' : 'disabled');

    const openUser = async (id, tab = 'account') => {
        if (!id) return;
        const token = ++openToken;
        openModal('Scheda utente', 'Caricamento…', `
            <div class="admin-user-loading" data-user-loading>
                <span class="admin-avatar"></span>
                <div><div class="admin-row-title">Carico la scheda…</div><div class="admin-row-sub">Leggo account, saldo e inventario dal database</div></div>
            </div>`, '<button type="button" class="admin-btn" data-admin-close="1">Chiudi</button>');
        try {
            const data = await api(`get_user_details.php?id=${Number(id)}`);
            if (token !== openToken || !$('#adminModalBody [data-user-loading]')) return;
            renderSheet(data, tab);
        } catch (error) {
            if (token !== openToken) return;
            const body = $('#adminModalBody');
            if (body) body.innerHTML = emptyState('fa-solid fa-triangle-exclamation', 'Scheda non caricata', error.message);
        }
    };

    // Rilegge l'utente e ridisegna la scheda restando sulla stessa sezione.
    const refreshSheet = async (tab = sheet?.tab || 'account') => {
        if (!sheet) return;
        const data = await api(`get_user_details.php?id=${Number(sheet.data.user.id)}`);
        if (!sheetRoot()) return;
        renderSheet(data, tab);
    };

    const tabCount = (key) => {
        if (!sheet) return '';
        const d = sheet.data;
        if (key === 'inventory') return d.inventory_stats?.characters ?? d.inventory.length;
        if (key === 'achievements') return d.achievements.length;
        if (key === 'badges') return d.badges ? d.badges.filter((b) => b.owned).length : '';
        return '';
    };

    const updateTabCounts = () => {
        $$('[data-tab-count]', sheetRoot() || document).forEach((el) => {
            const value = tabCount(el.dataset.tabCount);
            el.textContent = value === '' ? '' : compact(value);
            el.hidden = value === '';
        });
    };

    const renderSheet = (data, tab) => {
        const body = $('#adminModalBody');
        if (!body) return;
        const user = data.user;
        sheet = { data, tab, rarities: data.rarities || RARITY_FALLBACK, original: null, closeWarnedAt: 0, pickers: {} };

        const title = $('#adminModalTitle');
        const subtitle = $('#adminModalSubtitle');
        if (title) title.textContent = `@${user.username}`;
        if (subtitle) subtitle.textContent = `ID #${Number(user.id)} · registrato il ${fmtDay(user.data_creazione)}`;

        body.innerHTML = `
            <div class="admin-user-sheet" data-user-sheet>
                <aside class="admin-user-side" data-sheet-side>${sideHtml()}</aside>
                <div class="admin-user-main">
                    ${canManage() ? '' : `<p class="admin-user-note"><i class="fa-solid fa-lock"></i>${data.permissions.is_self ? 'Questo è il tuo account.' : 'Puoi solo consultare questo account: un admin gestisce gli utenti, un owner tutti.'}</p>`}
                    <div class="admin-user-tabs" role="tablist" aria-label="Sezioni della scheda">
                        ${TABS.map((t) => `<button type="button" role="tab" data-tab="${t.key}" aria-selected="false">${t.image ? `<img class="admin-currency" src="${t.image}" alt="">` : `<i class="${t.icon}"></i>`}<span>${t.label}</span><b data-tab-count="${t.key}" hidden></b></button>`).join('')}
                    </div>
                    ${TABS.map((t) => `<section class="admin-user-panel" data-panel="${t.key}" role="tabpanel" hidden></section>`).join('')}
                </div>
            </div>`;

        renderAccount();
        renderEconomy();
        renderInventory();
        renderAchievements();
        renderBadges();
        renderModeration();
        renderActivity();
        updateTabCounts();
        bindSheet(sheetRoot());
        showTab(TABS.some((t) => t.key === tab) ? tab : 'account');
    };

    const showTab = (key) => {
        const root = sheetRoot();
        if (!root || !sheet) return;
        sheet.tab = key;
        $$('[role="tab"]', root).forEach((btn) => {
            const active = btn.dataset.tab === key;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        $$('[data-panel]', root).forEach((p) => { p.hidden = p.dataset.panel !== key; });
    };

    const loginMethods = (user) => {
        const out = [];
        if (flag(user.has_password)) out.push('password');
        if (flag(user.google_linked)) out.push('Google');
        if (user.discord_username) out.push(`Discord (${e(user.discord_username)})`);
        return out.length ? out.join(' · ') : '<span class="admin-muted">—</span>';
    };

    const sideHtml = () => {
        const { user: u, currencies } = sheet.data;
        return `
            <div class="admin-user-id">
                <span class="admin-avatar-wrap${u.is_online ? ' is-online' : ''}"><img class="admin-user-avatar" src="${e(u.avatar_url)}" alt=""></span>
                <div class="admin-cell-text">
                    <h3>${e(u.username)}</h3>
                    ${u.display_name ? `<p>${e(u.display_name)}</p>` : ''}
                </div>
            </div>
            <div class="admin-user-tags">
                ${roleBadge(u.ruolo)}${banBadge(u)}${premiumBadge(u)}${onlineBadge(u)}
                ${'email_verificata' in u ? (flag(u.email_verificata)
                    ? '<span class="admin-badge admin-badge--info"><i class="fa-solid fa-envelope-circle-check"></i>Verificato</span>'
                    : '<span class="admin-badge"><i class="fa-solid fa-envelope"></i>Non verificato</span>') : ''}
                ${twofaActive(u) ? '<span class="admin-badge admin-badge--success"><i class="fa-solid fa-shield-halved"></i>2FA</span>' : ''}
            </div>
            ${currencies.length ? `<div class="admin-user-wallet">${currencies.map((c) => `
                <div>${currencyIcon(c)}<span>${e(c.label)}</span><b data-wallet-side="${e(c.key)}">${num(c.value)}</b></div>`).join('')}</div>` : ''}
            <dl class="admin-user-facts">
                <div><dt>Email</dt><dd>${e(u.email)}</dd></div>
                <div><dt>Ultimo accesso</dt><dd>${u.seconds_since_active === null || u.seconds_since_active === undefined ? 'Mai' : `${timeAgo(u.seconds_since_active)} · ${fmtStamp(u.ultimo_accesso)}`}</dd></div>
                <div><dt>Registrato</dt><dd>${fmtStamp(u.data_creazione)}</dd></div>
                ${u.profile_views !== undefined && u.profile_views !== null ? `<div><dt>Visite al profilo</dt><dd>${num(u.profile_views)}</dd></div>` : ''}
                <div><dt>Accede con</dt><dd>${loginMethods(u)}</dd></div>
            </dl>
            <div class="admin-user-links">
                <a class="admin-btn admin-btn--small" href="${e(u.profile_url)}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Profilo</a>
                <button type="button" class="admin-btn admin-btn--small" data-copy="${Number(u.id)}" title="Copia l'ID"><i class="fa-regular fa-copy"></i> ID</button>
                <button type="button" class="admin-btn admin-btn--small" data-copy="${e(u.email)}" title="Copia l'email"><i class="fa-regular fa-copy"></i> Email</button>
                ${A.openMessageForm ? '<button type="button" class="admin-btn admin-btn--small" data-sheet-message title="Scrivigli dal Centro Messaggi"><i class="fa-solid fa-paper-plane"></i> Messaggio</button>' : ''}
            </div>`;
    };

    const refreshSide = () => {
        const side = $('[data-sheet-side]', sheetRoot() || document);
        if (side) side.innerHTML = sideHtml();
    };

    /* Account ----------------------------------------------------------- */

    const accountOriginal = (u) => {
        const out = { username: u.username || '', email: u.email || '', ruolo: u.ruolo || 'utente', data_creazione: inputDate(u.data_creazione) };
        if ('display_name' in u) out.display_name = u.display_name || '';
        ['email_verificata', 'is_premium', 'nsfw', 'richpresence'].forEach((key) => {
            if (key in u) out[key] = flag(u[key]);
        });
        return out;
    };

    const switchRow = (name, label, help, checked) => `
        <label class="admin-user-switch" data-field="${name}">
            <span><strong>${label}</strong><small>${help}</small></span>
            <span class="shop-admin-switch"><input type="checkbox" name="${name}" ${checked ? 'checked' : ''} ${lockAttr()}><span></span></span>
        </label>`;

    const renderAccount = () => {
        const box = panel('account');
        if (!box) return;
        const { user: u, permissions } = sheet.data;
        const o = accountOriginal(u);
        sheet.original = o;
        const roles = permissions.roles || {};
        const roleLocked = !canManage() || Object.values(roles).filter(Boolean).length <= 1;
        const twofaOn = flag(u.twofa_enabled) || flag(u.twofa_has_secret);

        box.innerHTML = `
            <form class="admin-user-form" data-account-form autocomplete="off" novalidate>
                <div class="admin-form-grid">
                    <div class="admin-field" data-field="username"><label for="uf-username">Username</label>
                        <input id="uf-username" name="username" value="${e(o.username)}" maxlength="20" spellcheck="false" ${lockAttr()}>
                        <small class="admin-user-help">3-20 caratteri: lettere, numeri e _. Cambia anche l'indirizzo del profilo.</small></div>
                    ${'display_name' in o ? `<div class="admin-field" data-field="display_name"><label for="uf-display">Nome visualizzato</label>
                        <input id="uf-display" name="display_name" value="${e(o.display_name)}" maxlength="40" placeholder="Nessuno (si vede lo username)" ${lockAttr()}></div>` : ''}
                    <div class="admin-field" data-field="email"><label for="uf-email">Email</label>
                        <input id="uf-email" type="text" inputmode="email" name="email" value="${e(o.email)}" spellcheck="false" ${lockAttr()}></div>
                    <div class="admin-field" data-field="ruolo"><label for="uf-role">Ruolo</label>
                        <select id="uf-role" name="ruolo" ${roleLocked ? 'disabled' : ''}>
                            ${['utente', 'admin', 'owner'].map((r) => `<option value="${r}" ${o.ruolo === r ? 'selected' : ''} ${roles[r] ? '' : 'disabled'}>${ROLE_LABELS[r]}</option>`).join('')}
                        </select>
                        ${roleLocked && canManage() ? `<small class="admin-user-help">${permissions.is_self ? 'Non puoi cambiare il tuo ruolo.' : 'Solo un owner assegna i ruoli.'}</small>` : ''}</div>
                    <div class="admin-field" data-field="data_creazione"><label for="uf-created">Registrato il</label>
                        <input id="uf-created" type="datetime-local" step="1" name="data_creazione" value="${e(o.data_creazione)}" ${lockAttr()}></div>
                </div>

                <div class="admin-user-switches">
                    ${'email_verificata' in o ? switchRow('email_verificata', 'Email verificata', "L'utente ha confermato il suo indirizzo.", o.email_verificata) : ''}
                    ${'is_premium' in o ? switchRow('is_premium', 'Premium', 'Badge Premium e funzioni in più del profilo.', o.is_premium) : ''}
                    ${'is_premium' in o ? `
                        <div class="admin-user-premium" data-premium-on hidden>
                            <label class="admin-user-check"><input type="checkbox" name="premium_bonus" checked> Bonus di benvenuto: +${num(PREMIUM_BONUS)} Godos, come un acquisto vero</label>
                            <label class="admin-user-check"><input type="checkbox" name="premium_email" checked> Avvisa l'utente via email</label>
                            <small class="admin-user-help">Il badge Premium viene assegnato comunque.</small>
                        </div>
                        <p class="admin-user-premium" data-premium-off hidden><i class="fa-solid fa-circle-info"></i> Toglie anche il badge Premium. I Godos del bonus restano all'utente.</p>` : ''}
                    ${'nsfw' in o ? switchRow('nsfw', 'Contenuti NSFW', 'Come «Mostra NSFW» nelle impostazioni dell\'utente.', o.nsfw) : ''}
                    ${'richpresence' in o ? switchRow('richpresence', 'Rich Presence', "Preferenza salvata nelle impostazioni dell'account.", o.richpresence) : ''}
                </div>

                <div class="admin-user-2fa${twofaActive(u) ? ' is-on' : ''}">
                    <i class="fa-solid fa-shield-halved"></i>
                    <div>
                        <strong>Autenticazione a due fattori</strong>
                        <small>${twofaActive(u)
                            ? `Attiva${u.twofa_enabled_at ? ` dal ${fmtStamp(u.twofa_enabled_at)}` : ''}. Disattivala se l'utente ha perso l'app di autenticazione.`
                            : twofaOn ? 'Segnata come attiva ma senza codice segreto: non protegge il login. Disattivala per sistemare il profilo.' : "Non attiva. Può accenderla solo l'utente, dalle impostazioni."}</small>
                    </div>
                    ${twofaOn && canManage() ? '<button type="button" class="admin-btn admin-btn--small admin-btn--danger" data-twofa-reset><i class="fa-solid fa-power-off"></i> Disattiva</button>' : ''}
                </div>

                ${canManage() ? `
                <div class="admin-user-savebar" data-savebar>
                    <span data-savebar-text>Nessuna modifica</span>
                    <div>
                        <button type="button" class="admin-btn admin-btn--small" data-account-reset disabled>Scarta</button>
                        <button type="submit" class="admin-btn admin-btn--small admin-btn--primary" data-account-save disabled><i class="fa-solid fa-floppy-disk"></i> Salva</button>
                    </div>
                </div>` : ''}
            </form>`;
        updateSavebar();
    };

    const accountForm = () => $('[data-account-form]', sheetRoot() || document);

    const normDate = (value) => (value && value.length === 16 ? `${value}:00` : value || '');

    const readAccount = () => {
        const form = accountForm();
        const out = {};
        if (!form || !sheet?.original) return out;
        Object.keys(sheet.original).forEach((key) => {
            const field = form.elements[key];
            if (!field) return;
            if (field.type === 'checkbox') out[key] = field.checked ? 1 : 0;
            else if (key === 'data_creazione') out[key] = normDate(field.value);
            else out[key] = field.value.trim();
        });
        return out;
    };

    const accountChanges = () => {
        const current = readAccount();
        return Object.keys(current).filter((key) => String(current[key]) !== String(sheet.original[key]));
    };

    const isDirty = () => !!sheet && !!accountForm() && accountChanges().length > 0;

    const updateSavebar = () => {
        const form = accountForm();
        if (!form) return;
        const changed = accountChanges();
        const bar = $('[data-savebar]', form);
        $$('[data-field]', form).forEach((el) => el.classList.toggle('is-changed', changed.includes(el.dataset.field)));

        const premium = form.elements.is_premium;
        const wasPremium = sheet.original.is_premium === 1;
        const on = $('[data-premium-on]', form);
        const off = $('[data-premium-off]', form);
        if (on) on.hidden = !(premium && premium.checked && !wasPremium);
        if (off) off.hidden = !(premium && !premium.checked && wasPremium);

        if (!bar) return;
        bar.classList.toggle('is-dirty', changed.length > 0);
        bar.classList.remove('is-warning');
        $('[data-savebar-text]', bar).textContent = changed.length
            ? `${changed.length} ${changed.length === 1 ? 'modifica' : 'modifiche'}: ${changed.map((key) => FIELD_LABELS[key] || key).join(', ')}`
            : 'Nessuna modifica';
        $('[data-account-save]', bar).disabled = !changed.length;
        $('[data-account-reset]', bar).disabled = !changed.length;
    };

    const saveAccount = async () => {
        const form = accountForm();
        if (!form || !canManage()) return;
        const changed = accountChanges();
        if (!changed.length) return;

        // Si controllano solo i campi cambiati: un account vecchio con uno
        // username o un'email fuori regola deve poter salvare il resto.
        const current = readAccount();
        const invalid = (name, message) => {
            showToast(message, true);
            form.elements[name]?.focus();
            return true;
        };
        if (changed.includes('username') && !/^[A-Za-z0-9_]{3,20}$/.test(current.username)
            && invalid('username', 'Username non valido: 3-20 caratteri, lettere, numeri o _.')) return;
        if (changed.includes('email') && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(current.email)
            && invalid('email', 'Email non valida.')) return;

        const button = $('[data-account-save]', form);
        if (changed.includes('ruolo') && !twoStep(button, `Ruolo: ${ROLE_LABELS[current.ruolo]}?`)) return;

        const payload = { id: Number(sheet.data.user.id) };
        changed.forEach((key) => { payload[key] = current[key]; });
        if (changed.includes('is_premium') && current.is_premium === 1) {
            payload.premium_bonus = form.elements.premium_bonus?.checked ? 1 : 0;
            payload.premium_email = form.elements.premium_email?.checked ? 1 : 0;
        }

        await busy(button, async () => {
            const res = await api('update_user.php', { method: 'POST', body: payload });
            listStale = true;
            showToast(res.message || 'Utente aggiornato.');
            await refreshSheet('account');
        });
    };

    const resetTwofa = async (button) => {
        if (!twoStep(button, 'Disattivo la 2FA?')) return;
        await busy(button, async () => {
            await api('update_user.php', { method: 'POST', body: { id: Number(sheet.data.user.id), twofa_reset: 1 } });
            listStale = true;
            showToast('2FA disattivata: al prossimo accesso basta la password.');
            // Le altre modifiche del form, se ce ne sono, restano da salvare.
            const pending = readAccount();
            await refreshSheet('account');
            const form = accountForm();
            if (!form) return;
            Object.entries(pending).forEach(([key, value]) => {
                const field = form.elements[key];
                if (!field) return;
                if (field.type === 'checkbox') field.checked = value === 1;
                else field.value = value;
            });
            updateSavebar();
        });
    };

    /* Economia ---------------------------------------------------------- */

    const renderEconomy = () => {
        const box = panel('economy');
        if (!box) return;
        const { currencies } = sheet.data;
        if (!currencies.length) {
            box.innerHTML = emptyState('fa-solid fa-coins', 'Nessuna valuta', 'Le colonne dei saldi non ci sono su questo database.');
            return;
        }
        box.innerHTML = `
            <p class="admin-user-note admin-user-note--info"><i class="fa-solid fa-circle-info"></i>Aggiungi e Togli partono dal saldo che c'è nel database in quel momento: se l'utente spende o vince mentre la scheda è aperta, non si perde niente. Ogni modifica finisce nel log.</p>
            <div class="admin-user-wallets">
                ${currencies.map((c) => `
                <article class="admin-user-wallet-card" data-wallet="${e(c.key)}" data-mode="add">
                    <header>${currencyIcon(c)}<strong>${e(c.label)}</strong><b data-wallet-value>${num(c.value)}</b></header>
                    <div class="admin-seg" role="group" aria-label="Operazione su ${e(c.label)}">
                        <button type="button" class="is-active" data-wallet-mode="add" ${lockAttr()}><i class="fa-solid fa-plus"></i> Aggiungi</button>
                        <button type="button" data-wallet-mode="subtract" ${lockAttr()}><i class="fa-solid fa-minus"></i> Togli</button>
                        <button type="button" data-wallet-mode="set" ${lockAttr()}><i class="fa-solid fa-equals"></i> Imposta</button>
                    </div>
                    <input class="admin-input admin-user-amount" type="number" min="0" max="2000000000" step="1" inputmode="numeric" placeholder="Importo" data-wallet-amount aria-label="Importo di ${e(c.label)}" ${lockAttr()}>
                    <div class="admin-user-chips" data-wallet-chips>
                        ${[100, 1000, 10000, 100000].map((n) => `<button type="button" class="admin-user-chip" data-wallet-chip="${n}" ${lockAttr()}>+${num(n)}</button>`).join('')}
                    </div>
                    <input class="admin-input" type="text" maxlength="200" placeholder="Nota per il log (facoltativa)" data-wallet-note ${lockAttr()}>
                    <footer>
                        <span data-wallet-preview class="admin-muted">Scrivi un importo</span>
                        <button type="button" class="admin-btn admin-btn--small admin-btn--primary" data-wallet-apply disabled>Applica</button>
                    </footer>
                </article>`).join('')}
            </div>`;
    };

    const walletCurrency = (card) => sheet.data.currencies.find((c) => c.key === card.dataset.wallet);

    const walletResult = (card) => {
        const currency = walletCurrency(card);
        const raw = $('[data-wallet-amount]', card).value.trim();
        const amount = raw === '' ? NaN : Number(raw);
        if (!currency || !Number.isInteger(amount) || amount < 0 || amount > 2000000000) return null;
        const before = Number(currency.value || 0);
        const mode = card.dataset.mode;
        if (mode !== 'set' && amount === 0) return null;
        const after = mode === 'add' ? Math.min(2000000000, before + amount) : mode === 'subtract' ? Math.max(0, before - amount) : amount;
        return { before, after, amount, mode };
    };

    const updateWalletPreview = (card) => {
        const result = walletResult(card);
        const preview = $('[data-wallet-preview]', card);
        const apply = $('[data-wallet-apply]', card);
        if (!result) {
            preview.className = 'admin-muted';
            preview.textContent = 'Scrivi un importo';
            apply.disabled = true;
            return;
        }
        const diff = result.after - result.before;
        preview.className = diff > 0 ? 'admin-user-up' : diff < 0 ? 'admin-user-down' : 'admin-muted';
        preview.innerHTML = `${num(result.before)} <i class="fa-solid fa-arrow-right"></i> <b>${num(result.after)}</b>${diff ? ` <small>(${diff > 0 ? '+' : '−'}${num(Math.abs(diff))})</small>` : ''}`;
        apply.disabled = !canManage() || diff === 0;
    };

    const setWalletValue = (key, value) => {
        const currency = sheet.data.currencies.find((c) => c.key === key);
        if (currency) currency.value = value;
        sheet.data.user[key] = value;
        const root = sheetRoot();
        if (!root) return;
        const card = $(`[data-wallet="${key}"]`, root);
        if (card) {
            $('[data-wallet-value]', card).textContent = num(value);
            updateWalletPreview(card);
        }
        const side = $(`[data-wallet-side="${key}"]`, root);
        if (side) {
            side.textContent = num(value);
            side.classList.remove('is-flash');
            void side.offsetWidth;
            side.classList.add('is-flash');
        }
    };

    const applyWallet = async (card, button) => {
        const result = walletResult(card);
        if (!result || !canManage()) return;
        if (result.mode !== 'add' && !twoStep(button, result.mode === 'set' ? `Imposto ${num(result.after)}?` : `Tolgo ${num(result.amount)}?`)) return;
        await busy(button, async () => {
            const res = await api('adjust_user_balance.php', {
                method: 'POST',
                body: {
                    id: Number(sheet.data.user.id), currency: card.dataset.wallet, mode: result.mode,
                    amount: result.amount, note: $('[data-wallet-note]', card).value.trim(),
                },
            });
            listStale = true;
            $('[data-wallet-amount]', card).value = '';
            $('[data-wallet-note]', card).value = '';
            setWalletValue(card.dataset.wallet, Number(res.balance));
            showToast(`${walletCurrency(card)?.label || 'Saldo'}: ${res.message}`);
        });
    };

    /* Ricerca per aggiungere (personaggi e achievement) ------------------ */

    const pickerHtml = (kind, placeholder, withQty) => `
        <div class="admin-user-picker" data-picker="${kind}">
            <label class="admin-global-search admin-user-picker-search"><i class="fa-solid fa-plus"></i>
                <input type="search" data-picker-input placeholder="${e(placeholder)}" autocomplete="off" aria-label="${e(placeholder)}">
            </label>
            ${withQty ? '<label class="admin-user-picker-qty" title="Copie da aggiungere">×<input type="number" class="admin-input" data-picker-qty min="1" max="9999" value="1" aria-label="Copie da aggiungere"></label>' : ''}
            <div class="admin-user-results" data-picker-results hidden></div>
        </div>`;

    const pickerTimers = {};
    const searchPicker = (picker) => {
        const kind = picker.dataset.picker;
        const input = $('[data-picker-input]', picker);
        const results = $('[data-picker-results]', picker);
        clearTimeout(pickerTimers[kind]);
        const q = input.value.trim();
        if (!q) {
            results.hidden = true;
            return;
        }
        pickerTimers[kind] = setTimeout(async () => {
            try {
                const endpoint = kind === 'character' ? 'get_characters.php' : 'get_achievements.php';
                const data = await api(`${endpoint}?${new URLSearchParams({ q, limit: 12 })}`);
                if (input.value.trim() !== q || !picker.isConnected) return;
                const items = (kind === 'character' ? data.characters : data.achievements) || [];
                sheet.pickers[kind] = new Map(items.map((item) => [Number(item.id), item]));
                renderPickerResults(picker, items);
            } catch (error) {
                results.hidden = false;
                results.innerHTML = `<p class="admin-user-results-empty">${e(error.message)}</p>`;
            }
        }, 250);
    };

    const renderPickerResults = (picker, items) => {
        const kind = picker.dataset.picker;
        const results = $('[data-picker-results]', picker);
        results.hidden = false;
        if (!items.length) {
            results.innerHTML = '<p class="admin-user-results-empty">Nessun risultato.</p>';
            return;
        }
        results.innerHTML = items.map((item) => {
            const id = Number(item.id);
            if (kind === 'character') {
                const owned = sheet.data.inventory.find((c) => Number(c.id) === id);
                return `<button type="button" class="admin-user-result" data-pick="${id}">
                    ${image(item.image_url || item.img_url, 'fa-solid fa-box-open')}
                    <span><b>${e(item.nome)}</b><small>${rarityPill(item.rarita)}${item.categoria ? ` · ${e(item.categoria)}` : ''}${owned ? ` · <em>ne ha già ${num(owned.quantita)}</em>` : ''}</small></span>
                    <i class="fa-solid fa-plus"></i>
                </button>`;
            }
            const owned = sheet.data.achievements.some((a) => Number(a.id) === id);
            return `<button type="button" class="admin-user-result" data-pick="${id}" ${owned ? 'disabled' : ''}>
                ${image(item.image_url || item.img_url, 'fa-solid fa-trophy')}
                <span><b>${e(item.nome)}</b><small>${num(item.punti)} punti${owned ? ' · <em>già sbloccato</em>' : ''}</small></span>
                <i class="fa-solid ${owned ? 'fa-check' : 'fa-plus'}"></i>
            </button>`;
        }).join('');
    };

    const pick = async (picker, button) => {
        const kind = picker.dataset.picker;
        const id = Number(button.dataset.pick);
        const item = sheet.pickers[kind]?.get(id);
        if (!item || !canManage()) return;
        const userId = Number(sheet.data.user.id);

        await busy(button, async () => {
            if (kind === 'character') {
                const qtyInput = $('[data-picker-qty]', picker);
                const quantity = Math.max(1, Math.min(9999, Number(qtyInput?.value) || 1));
                const res = await api('add_character_to_user.php', { method: 'POST', body: { user_id: userId, character_id: id, quantity } });
                setInventoryQuantity(item, Number(res.quantity) || quantity);
                showToast(`${item.nome}: ${res.message}`);
            } else {
                const res = await api('add_achievement_to_user.php', { method: 'POST', body: { user_id: userId, achievement_id: id } });
                if (!sheet.data.achievements.some((a) => Number(a.id) === id)) {
                    sheet.data.achievements.unshift({ ...item, image_url: item.image_url || item.img_url, unlocked_at: null });
                }
                renderAchievementList();
                showToast(`${item.nome}: ${res.message}`);
            }
            listStale = true;
            updateTabCounts();
        });
        if (picker.isConnected) renderPickerResults(picker, Array.from(sheet.pickers[kind]?.values() || []));
    };

    /* Inventario -------------------------------------------------------- */

    const renderInventory = () => {
        const box = panel('inventory');
        if (!box) return;
        const g = sheet.data.gacha;
        const rarities = sheet.rarities;
        const pity = g ? (g.pity || []).map((p) => `<span class="admin-user-chip is-static"><i class="fa-solid fa-gauge-high"></i>Pity ${e(PITY_LABELS[p.gruppo] || p.gruppo)} <b>${num(p.contatore)}</b>${flag(p.garantito) ? ' · garantito' : ''}</span>`).join('') : '';

        box.innerHTML = `
            <div class="admin-user-panel-head">
                <div><strong>Inventario</strong><small data-inv-stats></small></div>
                <div class="admin-user-chips">${g && g.pulls_total !== null ? `<span class="admin-user-chip is-static"><i class="fa-solid fa-dice"></i><b>${num(g.pulls_total)}</b> pull</span>` : ''}${pity}</div>
            </div>
            ${canManage() ? pickerHtml('character', 'Cerca un personaggio da dare…', true) : ''}
            <div class="admin-user-filters">
                <label class="admin-global-search"><i class="fa-solid fa-filter"></i><input type="search" data-inv-filter placeholder="Filtra l'inventario per nome o categoria" aria-label="Filtra l'inventario"></label>
                <select class="admin-input" data-inv-rarity aria-label="Filtra per rarità">
                    <option value="">Tutte le rarità</option>
                    ${Object.entries(rarities).map(([key, def]) => `<option value="${e(key)}">${e(def.label)}</option>`).join('')}
                </select>
            </div>
            <div class="admin-user-grid" data-inv-grid></div>`;
        renderInventoryGrid();
    };

    const renderInventoryGrid = () => {
        const root = sheetRoot() || document;
        const grid = $('[data-inv-grid]', root);
        if (!grid) return;
        const inv = sheet.data.inventory;
        const stats = $('[data-inv-stats]', root);
        if (stats) {
            const copies = inv.reduce((sum, c) => sum + Number(c.quantita || 1), 0);
            stats.textContent = `${num(inv.length)} ${inv.length === 1 ? 'personaggio' : 'personaggi'} · ${num(copies)} ${copies === 1 ? 'copia' : 'copie'}`;
        }
        const q = ($('[data-inv-filter]', root)?.value || '').trim().toLowerCase();
        const rarity = $('[data-inv-rarity]', root)?.value || '';
        const shown = inv.filter((c) => (!rarity || rarityKey(c.rarita) === rarity)
            && (!q || String(c.nome).toLowerCase().includes(q) || String(c.categoria || '').toLowerCase().includes(q)));

        if (!inv.length) {
            grid.innerHTML = emptyState('fa-solid fa-box-open', 'Inventario vuoto', canManage() ? 'Cerca un personaggio qui sopra per darglielo.' : '');
            return;
        }
        if (!shown.length) {
            grid.innerHTML = emptyState('fa-solid fa-filter', 'Nessun personaggio con questi filtri');
            return;
        }
        grid.innerHTML = shown.map((c) => `
            <article class="admin-user-item" data-char="${Number(c.id)}">
                ${image(c.image_url, 'fa-solid fa-box-open')}
                <div class="admin-cell-text">
                    <div class="admin-row-title">${e(c.nome)}</div>
                    <div class="admin-user-item-meta">${rarityPill(c.rarita)}${flag(c.limitato) ? '<span class="admin-pill admin-pill--limited">Limitato</span>' : ''}${c.categoria ? `<small>${e(c.categoria)}</small>` : ''}</div>
                </div>
                <div class="admin-user-qty">
                    ${canManage() ? `<button type="button" data-inv-dec title="Togli una copia" aria-label="Togli una copia di ${e(c.nome)}"><i class="fa-solid fa-minus"></i></button>` : ''}
                    <b>×${num(c.quantita)}</b>
                    ${canManage() ? `<button type="button" data-inv-inc title="Aggiungi una copia" aria-label="Aggiungi una copia di ${e(c.nome)}"><i class="fa-solid fa-plus"></i></button>` : ''}
                </div>
                ${canManage() ? `<button type="button" class="admin-icon-btn admin-user-remove" data-inv-remove title="Togli tutte le copie" aria-label="Togli ${e(c.nome)} dall'inventario"><i class="fa-solid fa-trash"></i></button>` : ''}
            </article>`).join('');
    };

    const sortInventory = () => {
        const order = Object.keys(sheet.rarities);
        sheet.data.inventory.sort((a, b) => (order.indexOf(rarityKey(b.rarita)) - order.indexOf(rarityKey(a.rarita))) || String(a.nome).localeCompare(String(b.nome), 'it'));
    };

    const setInventoryQuantity = (item, quantity) => {
        const inv = sheet.data.inventory;
        const index = inv.findIndex((c) => Number(c.id) === Number(item.id));
        if (quantity <= 0) {
            if (index >= 0) inv.splice(index, 1);
        } else if (index >= 0) {
            inv[index].quantita = quantity;
        } else {
            inv.push({
                id: Number(item.id), nome: item.nome, image_url: item.image_url || item.img_url, rarita: rarityKey(item.rarita),
                categoria: item.categoria, limitato: item.limitato, quantita: quantity,
            });
            sortInventory();
        }
        sheet.data.inventory_stats = { characters: inv.length, copies: inv.reduce((s, c) => s + Number(c.quantita || 1), 0) };
        renderInventoryGrid();
        updateTabCounts();
    };

    const inventoryAction = async (button, action) => {
        const card = button.closest('[data-char]');
        const item = sheet.data.inventory.find((c) => Number(c.id) === Number(card?.dataset.char));
        if (!item || !canManage()) return;
        const body = { user_id: Number(sheet.data.user.id), character_id: Number(item.id) };
        const last = Number(item.quantita) <= 1;

        if (action === 'inc') {
            await busy(button, async () => {
                const res = await api('add_character_to_user.php', { method: 'POST', body: { ...body, quantity: 1 } });
                listStale = true;
                setInventoryQuantity(item, Number(res.quantity) || Number(item.quantita) + 1);
            });
            return;
        }
        if ((action === 'remove' || last) && !twoStep(button, action === 'remove' ? 'Tolgo tutto?' : 'Tolgo?')) return;
        await busy(button, async () => {
            const res = await api('remove_character_from_user.php', { method: 'POST', body: action === 'dec' ? { ...body, quantity: 1 } : body });
            listStale = true;
            setInventoryQuantity(item, Number(res.quantity) || 0);
            if (!res.quantity) showToast(`${item.nome} tolto dall'inventario.`);
        });
    };

    /* Achievement ------------------------------------------------------- */

    const renderAchievements = () => {
        const box = panel('achievements');
        if (!box) return;
        box.innerHTML = `
            <div class="admin-user-panel-head"><div><strong>Achievement</strong><small data-ach-stats></small></div></div>
            ${canManage() ? pickerHtml('achievement', 'Cerca un achievement da assegnare…', false) : ''}
            <div class="admin-user-list" data-ach-list></div>`;
        renderAchievementList();
    };

    const renderAchievementList = () => {
        const root = sheetRoot() || document;
        const list = $('[data-ach-list]', root);
        if (!list) return;
        const ach = sheet.data.achievements;
        const stats = $('[data-ach-stats]', root);
        if (stats) {
            const points = ach.reduce((sum, a) => sum + Number(a.punti || 0), 0);
            stats.textContent = `${num(ach.length)} sbloccati · ${num(points)} punti`;
        }
        list.innerHTML = ach.length ? ach.map((a) => `
            <article class="admin-user-item" data-ach="${Number(a.id)}">
                ${image(a.image_url, 'fa-solid fa-trophy')}
                <div class="admin-cell-text">
                    <div class="admin-row-title">${e(a.nome)}</div>
                    <div class="admin-row-sub">${num(a.punti)} punti${a.unlocked_at ? ` · sbloccato il ${fmtDay(a.unlocked_at)}` : ''}</div>
                    ${a.descrizione ? `<span>${e(a.descrizione)}</span>` : ''}
                </div>
                ${canManage() ? `<button type="button" class="admin-icon-btn admin-user-remove" data-ach-remove title="Togli l'achievement" aria-label="Togli ${e(a.nome)}"><i class="fa-solid fa-trash"></i></button>` : ''}
            </article>`).join('')
            : emptyState('fa-solid fa-trophy', 'Nessun achievement', canManage() ? 'Cercane uno qui sopra per assegnarlo.' : '');
    };

    const removeAchievement = async (button) => {
        const card = button.closest('[data-ach]');
        const id = Number(card?.dataset.ach);
        const item = sheet.data.achievements.find((a) => Number(a.id) === id);
        if (!item || !canManage() || !twoStep(button, 'Tolgo?')) return;
        await busy(button, async () => {
            await api('remove_achievement_from_user.php', { method: 'POST', body: { user_id: Number(sheet.data.user.id), achievement_id: id } });
            listStale = true;
            sheet.data.achievements = sheet.data.achievements.filter((a) => Number(a.id) !== id);
            renderAchievementList();
            updateTabCounts();
            showToast(`${item.nome} tolto.`);
        });
    };

    /* Badge ------------------------------------------------------------- */

    const badgeIcon = (badge) => {
        if (badge.image_url) return `<img src="${e(A.assetUrl(badge.image_url))}" alt="" loading="lazy">`;
        const icon = String(badge.icon || '').trim();
        if (/^fa[a-z-]*\s/.test(icon)) return `<i class="${e(icon)}"></i>`;
        if (icon) return `<span>${e(icon)}</span>`;
        return '<i class="fa-solid fa-certificate"></i>';
    };

    const renderBadges = () => {
        const box = panel('badges');
        if (!box) return;
        const badges = sheet.data.badges;
        if (!badges) {
            box.innerHTML = emptyState('fa-solid fa-certificate', 'Badge non disponibili', 'Le tabelle dei badge personalizzati non ci sono su questo database.');
            return;
        }
        box.innerHTML = `
            <div class="admin-user-panel-head"><div><strong>Badge personalizzati</strong><small>Accendi un badge per assegnarlo, spegnilo per toglierlo.</small></div></div>
            ${badges.length ? `<div class="admin-user-badges">${badges.map((b) => `
                <label class="admin-user-badge${b.owned ? ' is-owned' : ''}" style="--c:${e(b.color || '#0f5bff')}">
                    <span class="admin-user-badge-icon">${badgeIcon(b)}</span>
                    <span class="admin-cell-text"><strong>${e(b.name)}</strong><small>${b.owned ? 'Assegnato' : 'Non assegnato'}${Number(b.id) === PREMIUM_BADGE_ID ? ' · segue il Premium' : ''}</small></span>
                    <span class="shop-admin-switch"><input type="checkbox" data-badge-toggle="${Number(b.id)}" ${b.owned ? 'checked' : ''} ${lockAttr()} aria-label="Badge ${e(b.name)}"><span></span></span>
                </label>`).join('')}</div>`
            : emptyState('fa-solid fa-certificate', 'Nessun badge creato')}`;
    };

    const toggleBadge = async (input) => {
        const id = Number(input.dataset.badgeToggle);
        const badge = sheet.data.badges?.find((b) => Number(b.id) === id);
        if (!badge || !canManage()) return;
        const wanted = input.checked;
        input.disabled = true;
        try {
            const res = await api('user_badges.php', { method: 'POST', body: { action: wanted ? 'add' : 'remove', user_id: Number(sheet.data.user.id), badge_id: id } });
            badge.owned = wanted;
            listStale = true;
            const card = input.closest('.admin-user-badge');
            card?.classList.toggle('is-owned', wanted);
            const small = card?.querySelector('small');
            if (small) small.textContent = `${wanted ? 'Assegnato' : 'Non assegnato'}${id === PREMIUM_BADGE_ID ? ' · segue il Premium' : ''}`;
            updateTabCounts();
            showToast(res.message);
        } catch (error) {
            input.checked = !wanted;
            showToast(error.message, true);
        } finally {
            input.disabled = !canManage();
        }
    };

    /* Moderazione ------------------------------------------------------- */

    const BAN_DURATIONS = [['1h', '1 ora'], ['1d', '1 giorno'], ['3d', '3 giorni'], ['7d', '7 giorni'], ['30d', '30 giorni'], ['permanent', 'Per sempre'], ['custom', 'Fino al…']];
    const BAN_REASONS = ['Spam', 'Linguaggio offensivo', 'Contenuti inappropriati', 'Multi-account', 'Abuso di bug', 'Molestie'];

    const renderModeration = () => {
        const box = panel('moderation');
        if (!box) return;
        const { user: u, permissions } = sheet.data;
        const banned = Number(u.isBannato) === 1;
        const canBan = !!permissions.can_ban;

        const banCard = banned ? `
            <div class="admin-user-ban">
                <i class="fa-solid fa-ban"></i>
                <div>
                    <strong>${u.banned_until ? `Bannato fino al ${fmtStamp(u.banned_until)}` : 'Bannato per sempre'}</strong>
                    <dl>
                        <div><dt>Motivo</dt><dd>${u.motivo_ban ? e(u.motivo_ban) : '<span class="admin-muted">Nessun motivo scritto</span>'}</dd></div>
                        ${u.banned_at ? `<div><dt>Dal</dt><dd>${fmtStamp(u.banned_at)}</dd></div>` : ''}
                        ${u.banned_by_username ? `<div><dt>Da</dt><dd>${e(u.banned_by_username)}</dd></div>` : ''}
                    </dl>
                </div>
                ${canBan ? '<button type="button" class="admin-btn admin-btn--small" data-unban><i class="fa-solid fa-unlock"></i> Sbanna</button>' : ''}
            </div>`
            : '<div class="admin-user-ok"><i class="fa-solid fa-circle-check"></i><div><strong>Nessun ban attivo</strong><small>L\'account può accedere normalmente.</small></div></div>';

        const form = canBan ? `
            <form class="admin-user-banform" data-ban-form novalidate>
                <div class="admin-user-panel-head"><div><strong>${banned ? 'Cambia il ban' : 'Banna l\'account'}</strong><small>${banned ? 'Sostituisce durata e motivo di quello attuale.' : 'L\'utente viene scollegato e vede la pagina del ban.'}</small></div></div>
                <div class="admin-field"><label>Durata</label>
                    <div class="admin-user-chips" role="radiogroup" aria-label="Durata del ban">
                        ${BAN_DURATIONS.map(([value, label], i) => `<label class="admin-user-chip admin-user-radio"><input type="radio" name="duration" value="${value}" ${i === (banned ? 5 : 1) ? 'checked' : ''}><span>${label}</span></label>`).join('')}
                    </div>
                </div>
                <div class="admin-field" data-ban-custom hidden><label for="ban-until">Fino al</label><input id="ban-until" type="datetime-local" name="customDate"></div>
                <div class="admin-field"><label for="ban-reason">Motivo <small class="admin-muted">(lo vede l'utente)</small></label>
                    <textarea id="ban-reason" name="reason" maxlength="255" rows="3" placeholder="Spiega in breve perché">${banned ? e(u.motivo_ban || '') : ''}</textarea>
                    <div class="admin-user-chips">${BAN_REASONS.map((r) => `<button type="button" class="admin-user-chip" data-ban-reason="${e(r)}">${e(r)}</button>`).join('')}</div>
                </div>
                <div class="admin-user-actions">
                    <button type="submit" class="admin-btn admin-btn--danger" data-ban-submit><i class="fa-solid fa-gavel"></i> ${banned ? 'Aggiorna il ban' : 'Banna'}</button>
                </div>
            </form>`
            : `<p class="admin-user-note"><i class="fa-solid fa-lock"></i>${permissions.is_self ? 'Non puoi bannare te stesso.' : 'Non hai i permessi per bannare questo account.'}</p>`;

        box.innerHTML = banCard + form;
    };

    const submitBan = async (form) => {
        const button = $('[data-ban-submit]', form);
        const duration = form.elements.duration.value;
        const customDate = form.elements.customDate.value;
        if (duration === 'custom' && !customDate) {
            showToast('Scegli fino a quando dura il ban.', true);
            form.elements.customDate.focus();
            return;
        }
        if (!twoStep(button, 'Confermi il ban?')) return;
        await busy(button, async () => {
            const res = await api('ban_user.php', {
                method: 'POST',
                body: { id: Number(sheet.data.user.id), duration, customDate, reason: form.elements.reason.value.trim() },
            });
            listStale = true;
            showToast(res.message || 'Utente bannato.');
            await refreshSheet('moderation');
        });
    };

    const unban = async (button) => {
        if (!twoStep(button, 'Sbanno?')) return;
        await busy(button, async () => {
            await api('unban_user.php', { method: 'POST', body: { id: Number(sheet.data.user.id) } });
            listStale = true;
            showToast('Utente sbannato.');
            await refreshSheet('moderation');
        });
    };

    /* Attivita' --------------------------------------------------------- */

    const renderActivity = () => {
        const box = panel('activity');
        if (!box) return;
        const { logs, gacha } = sheet.data;
        const describe = A.describeLog || ((log) => ({ icon: 'fa-solid fa-bolt', label: log.action, text: log.details || '' }));
        const recent = gacha?.recent || [];

        box.innerHTML = `
            <div class="admin-user-panel-head"><div><strong>Azioni dello staff</strong><small>${logs.length ? `Le ultime ${logs.length} modifiche fatte dal pannello su questo account.` : 'Le modifiche fatte dal pannello su questo account.'}</small></div></div>
            ${logs.length ? `<ol class="admin-user-timeline">${logs.map((log) => {
                const d = describe(log);
                return `<li>
                    <span class="admin-user-timeline-icon">${d.image ? `<img class="admin-currency" src="${e(d.image)}" alt="">` : `<i class="${e(d.icon)}"></i>`}</span>
                    <div class="admin-cell-text">
                        <div class="admin-row-title">${e(d.label)}</div>
                        ${d.text ? `<span>${e(d.text)}</span>` : ''}
                        <div class="admin-row-sub">${e(log.admin_username || `admin #${log.admin_id}`)} · ${fmtStamp(log.created_at)}</div>
                    </div>
                </li>`;
            }).join('')}</ol>` : emptyState('fa-solid fa-clock-rotate-left', 'Nessuna azione registrata')}
            ${recent.length ? `
                <div class="admin-user-panel-head"><div><strong>Ultime pull</strong><small>Dalla cronologia del gacha.</small></div></div>
                <ol class="admin-user-pulls">${recent.map((p) => `
                    <li>${rarityPill(p.rarita)}<b>${e(p.nome || `#${p.personaggio_id}`)}</b><small>${e(p.banner_id || '')} · ${fmtStamp(p.created_at)}</small></li>`).join('')}
                </ol>` : ''}`;
    };

    /* Eventi della scheda ----------------------------------------------- */

    const bindSheet = (root) => {
        if (!root) return;

        root.addEventListener('click', (event) => {
            const t = event.target;
            const tab = t.closest('[role="tab"][data-tab]');
            if (tab) return showTab(tab.dataset.tab);

            const copy = t.closest('[data-copy]');
            if (copy) return copyText(copy.dataset.copy);

            if (t.closest('[data-sheet-message]')) {
                if (isDirty()) {
                    showTab('account');
                    flashSavebar('Salva o scarta le modifiche prima di scrivere all\'utente.');
                    return;
                }
                return A.openMessageForm({ targetUser: sheet.data.user.username });
            }

            if (t.closest('[data-account-reset]')) {
                renderAccount();
                return;
            }
            const twofa = t.closest('[data-twofa-reset]');
            if (twofa) return resetTwofa(twofa);

            const mode = t.closest('[data-wallet-mode]');
            if (mode) {
                const card = mode.closest('[data-wallet]');
                card.dataset.mode = mode.dataset.walletMode;
                $$('[data-wallet-mode]', card).forEach((b) => b.classList.toggle('is-active', b === mode));
                $('[data-wallet-chips]', card).hidden = card.dataset.mode === 'set';
                updateWalletPreview(card);
                return $('[data-wallet-amount]', card).focus();
            }
            const chip = t.closest('[data-wallet-chip]');
            if (chip) {
                const card = chip.closest('[data-wallet]');
                const input = $('[data-wallet-amount]', card);
                input.value = String((Number(input.value) || 0) + Number(chip.dataset.walletChip));
                return updateWalletPreview(card);
            }
            const apply = t.closest('[data-wallet-apply]');
            if (apply) return applyWallet(apply.closest('[data-wallet]'), apply);

            const picked = t.closest('[data-pick]');
            if (picked) return pick(picked.closest('[data-picker]'), picked);

            const invBtn = t.closest('[data-inv-inc], [data-inv-dec], [data-inv-remove]');
            if (invBtn) return inventoryAction(invBtn, invBtn.hasAttribute('data-inv-inc') ? 'inc' : invBtn.hasAttribute('data-inv-dec') ? 'dec' : 'remove');

            const achRemove = t.closest('[data-ach-remove]');
            if (achRemove) return removeAchievement(achRemove);

            const reason = t.closest('[data-ban-reason]');
            if (reason) {
                const area = reason.closest('form').elements.reason;
                area.value = area.value.trim() ? `${area.value.trim()} · ${reason.dataset.banReason}` : reason.dataset.banReason;
                return area.focus();
            }
            const unbanBtn = t.closest('[data-unban]');
            if (unbanBtn) return unban(unbanBtn);

            // Click fuori da una ricerca: si chiudono i suoi risultati.
            $$('[data-picker-results]', root).forEach((results) => {
                if (!results.closest('[data-picker]').contains(t)) results.hidden = true;
            });
            return undefined;
        });

        root.addEventListener('input', (event) => {
            const t = event.target;
            if (t.closest('[data-account-form]')) return updateSavebar();
            if (t.matches('[data-wallet-amount]')) return updateWalletPreview(t.closest('[data-wallet]'));
            if (t.matches('[data-picker-input]')) return searchPicker(t.closest('[data-picker]'));
            if (t.matches('[data-inv-filter]')) return renderInventoryGrid();
            return undefined;
        });

        root.addEventListener('change', (event) => {
            const t = event.target;
            if (t.closest('[data-account-form]')) return updateSavebar();
            if (t.matches('[data-inv-rarity]')) return renderInventoryGrid();
            if (t.matches('[data-badge-toggle]')) return toggleBadge(t);
            if (t.name === 'duration' && t.closest('[data-ban-form]')) {
                const custom = $('[data-ban-custom]', root);
                custom.hidden = t.value !== 'custom';
                if (t.value === 'custom') {
                    const input = custom.querySelector('input');
                    const now = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
                    input.min = now.toISOString().slice(0, 16);
                    input.focus();
                }
            }
            return undefined;
        });

        root.addEventListener('focusin', (event) => {
            const input = event.target.closest('[data-picker-input]');
            if (input && input.value.trim()) $('[data-picker-results]', input.closest('[data-picker]')).hidden = false;
        });

        root.addEventListener('keydown', (event) => {
            // Esc dentro una ricerca chiude i risultati, non la finestra.
            if (event.key === 'Escape' && event.target.matches('[data-picker-input]')) {
                const results = $('[data-picker-results]', event.target.closest('[data-picker]'));
                if (!results.hidden) {
                    results.hidden = true;
                    event.stopPropagation();
                }
            }
            if (event.key === 'Enter' && event.target.matches('[data-wallet-amount], [data-wallet-note]')) {
                event.preventDefault();
                const card = event.target.closest('[data-wallet]');
                const apply = $('[data-wallet-apply]', card);
                if (!apply.disabled) applyWallet(card, apply);
            }
        });

        root.addEventListener('submit', (event) => {
            event.preventDefault();
            if (event.target.matches('[data-account-form]')) saveAccount();
            if (event.target.matches('[data-ban-form]')) submitBan(event.target);
        });
    };

    const flashSavebar = (text) => {
        const bar = $('[data-savebar]', sheetRoot() || document);
        if (!bar) return;
        $('[data-savebar-text]', bar).textContent = text;
        bar.classList.add('is-warning');
        bar.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    };

    /* Finestra ---------------------------------------------------------- */

    const modalEl = document.getElementById('adminModal');
    // Chiudere con modifiche non salvate: il primo tentativo avvisa, il
    // secondo (entro qualche secondo) chiude e le scarta.
    modalEl?.addEventListener('hide.bs.modal', (event) => {
        if (!sheetRoot() || !isDirty()) return;
        if (Date.now() - sheet.closeWarnedAt < 5000) return;
        event.preventDefault();
        sheet.closeWarnedAt = Date.now();
        showTab('account');
        flashSavebar('Modifiche non salvate: salva, oppure chiudi di nuovo per scartarle.');
    });
    modalEl?.addEventListener('hidden.bs.modal', () => {
        openToken++;
        sheet = null;
        if (listStale && usersSectionActive()) loadUsers({ silent: true });
        listStale = false;
    });

    // Qualsiasi elemento con data-open-user apre la scheda (la dashboard lo
    // usa sugli ultimi iscritti).
    document.addEventListener('click', (event) => {
        const el = event.target.closest('[data-open-user]');
        if (!el || el.closest('#usersTable')) return;
        event.preventDefault();
        openUser(Number(el.dataset.openUser), el.dataset.tab || 'account');
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        const el = event.target.closest('[data-open-user][role="button"]');
        if (!el) return;
        event.preventDefault();
        openUser(Number(el.dataset.openUser), el.dataset.tab || 'account');
    });

    bindList();
    A.openUser = openUser;
    A.registerSection('users', loadUsers);
})();
