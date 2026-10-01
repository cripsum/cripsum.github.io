/**
 * Cripsum™ — pagina Amici.
 *
 * Cinque sezioni (online, tutti, richieste, suggeriti, bloccati) e una
 * ricerca sempre a portata di mano. Gli elenchi arrivano da api/social/;
 * mentre la pagina è aperta, richieste e amicizie nuove arrivano dal
 * controllo leggero del sito (assets/rt/rt.js) senza ricaricare.
 */
(() => {
    'use strict';

    const K = window.ChatKit;
    if (!K || !document.getElementById('spApp')) return;

    const { h, icon, t } = K;
    const lang = K.lang;
    const RT = window.CripsumRT || null;
    const Card = window.CripsumUserCard || null;

    K.extend({
        sp_online: { it: 'Online', en: 'Online' },
        sp_all: { it: 'Tutti', en: 'All' },
        sp_requests: { it: 'Richieste', en: 'Requests' },
        sp_suggestions: { it: 'Suggeriti', en: 'Suggested' },
        sp_blocked: { it: 'Bloccati', en: 'Blocked' },
        sp_stat_friends: { it: 'Amici', en: 'Friends' },
        sp_stat_online: { it: 'Online ora', en: 'Online now' },
        sp_stat_requests: { it: 'Richieste in arrivo', en: 'Incoming requests' },
        sp_received: { it: 'Ricevute', en: 'Received' },
        sp_sent: { it: 'Inviate', en: 'Sent' },
        sp_among_friends: { it: 'Tra i tuoi amici', en: 'Among your friends' },
        sp_other_users: { it: 'Altri utenti', en: 'Other users' },
        sp_message: { it: 'Messaggio', en: 'Message' },
        sp_add: { it: 'Aggiungi', en: 'Add friend' },
        sp_accept: { it: 'Accetta', en: 'Accept' },
        sp_decline: { it: 'Rifiuta', en: 'Decline' },
        sp_cancel_request: { it: 'Annulla richiesta', en: 'Cancel request' },
        sp_pending: { it: 'Richiesta inviata', en: 'Request sent' },
        sp_unblock: { it: 'Sblocca', en: 'Unblock' },
        sp_more: { it: 'Altro', en: 'More' },
        sp_profile: { it: 'Apri il profilo', en: 'Open profile' },
        sp_card: { it: 'Vedi la scheda', en: 'View card' },
        sp_remove: { it: 'Rimuovi dagli amici', en: 'Remove friend' },
        sp_remove_title: { it: 'Rimuovere {name} dagli amici?', en: 'Remove {name} from your friends?' },
        sp_remove_text: { it: 'Non verrà avvisato. Potrete sempre mandarvi una nuova richiesta.', en: 'They will not be notified. You can always send each other a new request.' },
        sp_block: { it: 'Blocca', en: 'Block' },
        sp_block_title: { it: 'Bloccare {name}?', en: 'Block {name}?' },
        sp_block_text: { it: 'Non potrà più scriverti né mandarti richieste, e l\'amicizia verrà rimossa. Non verrà avvisato.', en: 'They will no longer be able to message you or send requests, and the friendship will be removed. They will not be notified.' },
        sp_mutual_one: { it: '1 amico in comune', en: '1 mutual friend' },
        sp_mutual: { it: '{n} amici in comune', en: '{n} mutual friends' },
        sp_wants: { it: 'Ricevuta {when}', en: 'Received {when}' },
        sp_waiting: { it: 'In attesa · {when}', en: 'Waiting · {when}' },
        sp_friend_tag: { it: 'Amico', en: 'Friend' },
        sp_empty_online: { it: 'Nessun amico online', en: 'No friends online' },
        sp_empty_online_text: { it: 'Quando un amico apre il sito lo trovi qui.', en: 'When a friend opens the site you will find them here.' },
        sp_empty_all: { it: 'Non hai ancora amici', en: 'You have no friends yet' },
        sp_empty_all_text: { it: 'Cerca qualcuno per nome o dai un\'occhiata ai suggeriti.', en: 'Search for someone by name or take a look at the suggestions.' },
        sp_empty_requests: { it: 'Nessuna richiesta in sospeso', en: 'No pending requests' },
        sp_empty_requests_text: { it: 'Le richieste che ricevi o mandi compaiono qui.', en: 'Requests you receive or send show up here.' },
        sp_empty_suggestions: { it: 'Nessun suggerimento per ora', en: 'No suggestions for now' },
        sp_empty_suggestions_text: { it: 'Più amici hai, più persone possiamo proporti.', en: 'The more friends you have, the more people we can suggest.' },
        sp_empty_blocked: { it: 'Non hai bloccato nessuno', en: 'You have not blocked anyone' },
        sp_empty_blocked_text: { it: 'Le persone che blocchi non possono scriverti né mandarti richieste.', en: 'People you block cannot message you or send you requests.' },
        sp_empty_search: { it: 'Nessun utente trovato', en: 'No users found' },
        sp_empty_search_text: { it: 'Controlla di aver scritto bene il nome utente.', en: 'Check that you typed the username correctly.' },
        sp_search_hint: { it: 'Scrivi almeno due lettere.', en: 'Type at least two letters.' },
        sp_see_suggestions: { it: 'Vedi i suggeriti', en: 'See suggestions' },
        sp_load_error: { it: 'Non è stato possibile caricare l\'elenco.', en: 'The list could not be loaded.' },
        sp_retry: { it: 'Riprova', en: 'Retry' }
    });

    const TABS = ['online', 'all', 'requests', 'suggestions', 'blocked'];
    const els = {
        stats: document.getElementById('spStats'),
        tabs: document.getElementById('spTabs'),
        content: document.getElementById('spContent'),
        search: document.getElementById('spSearch'),
        clear: document.getElementById('spSearchClear')
    };

    const state = {
        tab: 'all',
        query: '',
        friends: null,
        requests: null,
        suggestions: null,
        blocked: null,
        results: null,
        counts: { friends: 0, online: 0, requests_received: 0, requests_sent: 0, blocked: 0 },
        failed: false,
        token: 0
    };

    // ── Dati ───────────────────────────────────────────────────────────────

    async function loadFriends() {
        const data = (await K.api('/api/social/friends.php')).data;
        state.friends = data.all || [];
        if (data.counts) state.counts = data.counts;
    }

    async function loadRequests() {
        const data = (await K.api('/api/social/friend_requests.php')).data;
        state.requests = { received: data.received || [], sent: data.sent || [] };
    }

    async function loadSuggestions() {
        state.suggestions = (await K.api('/api/social/suggested_users.php')).data.suggestions || [];
    }

    async function loadBlocked() {
        state.blocked = (await K.api('/api/social/blocked_users.php')).data.blocked || [];
    }

    async function loadSearch() {
        const query = state.query;
        if (query.length < 2) {
            state.results = null;
            return;
        }
        const users = (await K.api('/api/social/search_users.php?q=' + encodeURIComponent(query) + '&limit=24')).data.users || [];
        if (query === state.query) state.results = users;
    }

    /** Ricarica ciò che serve alla vista attuale; `everything` dopo un'azione che tocca più elenchi. */
    async function refresh(everything) {
        const token = ++state.token;
        const jobs = [loadFriends()];
        if (everything || state.tab === 'requests' || state.requests === null) jobs.push(loadRequests());
        if (state.tab === 'suggestions' || (everything && state.suggestions !== null)) jobs.push(loadSuggestions());
        if (state.tab === 'blocked' || (everything && state.blocked !== null)) jobs.push(loadBlocked());
        if (state.query.length >= 2) jobs.push(loadSearch());

        try {
            await Promise.all(jobs);
            state.failed = false;
        } catch (error) {
            state.failed = true;
        }
        if (token !== state.token) return;
        paint();
    }

    const refreshSoon = K.debounce(() => refresh(true), 400);

    // ── Azioni ─────────────────────────────────────────────────────────────

    async function act(button, endpoint, body) {
        const card = button ? button.closest('.sp-card') : null;
        if (card) card.classList.add('is-busy');
        try {
            const data = await K.api('/api/social/' + endpoint, { body });
            if (data.message) K.toast(data.message, 'success');
            if (RT) RT.refreshCounters();
            await refresh(true);
        } catch (error) {
            K.toast(error.message, 'error');
            if (card) card.classList.remove('is-busy');
        }
    }

    async function removeFriend(user) {
        const ok = await K.confirm({
            title: t('sp_remove_title', { name: user.display_name }), text: t('sp_remove_text'),
            confirmLabel: t('sp_remove'), danger: true, icon: 'fa-solid fa-user-minus'
        });
        if (ok) act(null, 'remove_friend.php', { friend_id: user.id });
    }

    async function blockUser(user) {
        const ok = await K.confirm({
            title: t('sp_block_title', { name: user.display_name }), text: t('sp_block_text'),
            confirmLabel: t('sp_block'), danger: true, icon: 'fa-solid fa-ban'
        });
        if (ok) act(null, 'block_user.php', { blocked_id: user.id });
    }

    function moreMenu(user, anchor, isFriend) {
        K.menu(anchor, [
            Card ? { label: t('sp_card'), icon: 'fa-regular fa-id-card', onSelect: () => Card.open({ id: user.id }) } : null,
            { label: t('sp_profile'), icon: 'fa-regular fa-user', onSelect: () => { window.location.href = '/u/' + encodeURIComponent(user.username); } },
            { divider: true },
            isFriend ? { label: t('sp_remove'), icon: 'fa-solid fa-user-minus', danger: true, onSelect: () => removeFriend(user) } : null,
            { label: t('sp_block'), icon: 'fa-solid fa-ban', danger: true, onSelect: () => blockUser(user) }
        ], { alignRight: true, header: '@' + user.username });
    }

    // ── Disegno ────────────────────────────────────────────────────────────

    const button = (kind, iconName, label, onClick, title) => h('button', {
        type: 'button', class: 'ck-btn ck-btn--' + kind + ' ck-btn--sm', title: title || null, onClick
    }, iconName ? icon(iconName) : null, label);

    const messageLink = (user) => h('a', { class: 'ck-btn ck-btn--ghost ck-btn--sm', href: '/' + lang + '/chat?user_id=' + user.id },
        icon('fa-solid fa-comment'), t('sp_message'));

    const moreButton = (user, isFriend) => h('button', {
        type: 'button', class: 'ck-icon-btn sp-card__more', 'aria-label': t('sp_more'), title: t('sp_more'),
        onClick: (event) => moreMenu(user, event.currentTarget, isFriend)
    }, icon('fa-solid fa-ellipsis'));

    /**
     * Una persona. `kind` decide la riga sotto il nome e i pulsanti:
     * friend, received, sent, blocked, oppure person (suggeriti e ricerca,
     * dove i pulsanti dipendono dal rapporto che c'è già).
     */
    function card(user, kind) {
        let sub;
        const actions = h('div', { class: 'sp-card__actions' });

        if (kind === 'friend') {
            sub = h('span', { class: user.is_online ? 'is-online' : '' }, K.presenceLabel(user.is_online, user.last_seen_ts));
            actions.append(messageLink(user), moreButton(user, true));
        } else if (kind === 'received') {
            sub = t('sp_wants', { when: K.relativeTime(user.sent_ts) });
            actions.append(
                button('primary', 'fa-solid fa-check', t('sp_accept'), (e) => act(e.currentTarget, 'accept_friend_request.php', { sender_id: user.id })),
                button('ghost', null, t('sp_decline'), (e) => act(e.currentTarget, 'decline_friend_request.php', { sender_id: user.id })));
        } else if (kind === 'sent') {
            sub = t('sp_waiting', { when: K.relativeTime(user.sent_ts) });
            actions.append(button('ghost', 'fa-solid fa-xmark', t('sp_cancel_request'), (e) => act(e.currentTarget, 'cancel_friend_request.php', { receiver_id: user.id })));
        } else if (kind === 'blocked') {
            sub = '@' + user.username;
            actions.append(button('ghost', 'fa-solid fa-unlock', t('sp_unblock'), (e) => act(e.currentTarget, 'unblock_user.php', { blocked_id: user.id })));
        } else {
            const mutual = Number(user.mutual_connections) || 0;
            sub = mutual > 0 ? (mutual === 1 ? t('sp_mutual_one') : t('sp_mutual', { n: mutual })) : '@' + user.username;

            if (user.is_blocked_by_viewer) {
                actions.append(button('ghost', 'fa-solid fa-unlock', t('sp_unblock'), (e) => act(e.currentTarget, 'unblock_user.php', { blocked_id: user.id })));
            } else if (user.is_friend) {
                actions.append(messageLink(user), moreButton(user, true));
            } else {
                if (user.friend_request_received) {
                    actions.append(button('primary', 'fa-solid fa-check', t('sp_accept'), (e) => act(e.currentTarget, 'accept_friend_request.php', { sender_id: user.id })));
                } else if (user.friend_request_sent) {
                    actions.append(button('ghost', 'fa-solid fa-user-clock', t('sp_pending'), (e) => act(e.currentTarget, 'cancel_friend_request.php', { receiver_id: user.id }), t('sp_cancel_request')));
                } else if (user.can_send_friend_request) {
                    actions.append(button('primary', 'fa-solid fa-user-plus', t('sp_add'), (e) => act(e.currentTarget, 'send_friend_request.php', { receiver_id: user.id })));
                }
                actions.append(moreButton(user, false));
            }
        }

        const profileUrl = '/u/' + encodeURIComponent(user.username);
        return h('article', { class: 'sp-card', dataset: { userId: user.id } },
            h('a', { class: 'sp-card__who user-card-trigger', href: profileUrl, dataset: { userId: user.id } },
                h('span', { class: 'sp-avatar' },
                    h('img', { src: K.avatarUrl(user.id), alt: '', loading: 'lazy' }),
                    user.is_online ? h('span', { class: 'ck-dot is-online' }) : null),
                h('span', { class: 'sp-card__text' },
                    h('strong', null, user.display_name || user.username, user.is_premium ? K.premiumGem() : null,
                        kind === 'person' && user.is_friend ? h('span', { class: 'sp-tag' }, t('sp_friend_tag')) : null),
                    h('small', null, sub))),
            actions);
    }

    function grid(users, kind) {
        const wrap = h('div', { class: 'sp-grid' });
        users.forEach((user, index) => {
            const node = card(user, kind);
            node.style.setProperty('--sp-i', String(Math.min(index, 12)));
            wrap.appendChild(node);
        });
        return wrap;
    }

    function section(title, count, node) {
        return h('section', { class: 'sp-section' },
            h('h2', null, title, count !== null ? h('span', null, String(count)) : null),
            node);
    }

    function empty(iconName, title, text, action) {
        return h('div', { class: 'sp-empty' }, K.emptyState(iconName, title, text, action || null));
    }

    function skeleton() {
        const wrap = h('div', { class: 'sp-grid' });
        for (let i = 0; i < 6; i += 1) {
            wrap.appendChild(h('div', { class: 'sp-card sp-card--skeleton' },
                h('span', { class: 'sp-avatar ck-skeleton' }),
                h('span', { class: 'sp-card__text' }, h('span', { class: 'ck-skeleton' }), h('span', { class: 'ck-skeleton' }))));
        }
        return wrap;
    }

    function paintStats() {
        const c = state.counts;
        const tile = (iconName, value, label, tab, highlight) => h('button', {
            type: 'button', class: 'sp-stat' + (highlight ? ' is-hot' : ''), onClick: () => setTab(tab)
        }, h('span', { class: 'sp-stat__icon' }, icon(iconName)), h('span', { class: 'sp-stat__text' }, h('strong', null, String(value)), h('small', null, label)));

        K.clear(els.stats);
        els.stats.append(
            tile('fa-solid fa-user-group', c.friends, t('sp_stat_friends'), 'all'),
            tile('fa-solid fa-circle', c.online, t('sp_stat_online'), 'online'),
            tile('fa-solid fa-user-clock', c.requests_received, t('sp_stat_requests'), 'requests', c.requests_received > 0));
    }

    function paintTabs() {
        const c = state.counts;
        const defs = [
            ['online', t('sp_online'), c.online, false],
            ['all', t('sp_all'), c.friends, false],
            ['requests', t('sp_requests'), c.requests_received, true],
            ['suggestions', t('sp_suggestions'), 0, false],
            c.blocked > 0 || state.tab === 'blocked' ? ['blocked', t('sp_blocked'), c.blocked, false] : null
        ].filter(Boolean);

        K.clear(els.tabs);
        defs.forEach(([key, label, count, hot]) => {
            const active = state.tab === key && state.query.length < 2;
            els.tabs.appendChild(h('button', {
                type: 'button', role: 'tab', class: 'sp-tab' + (active ? ' is-active' : ''), 'aria-selected': active ? 'true' : 'false',
                onClick: () => setTab(key)
            }, label, count > 0 ? h('span', { class: hot ? 'ck-count' : 'sp-tab__count' }, String(count)) : null));
        });
    }

    function paintSearch() {
        K.clear(els.content);
        const query = state.query.toLowerCase();
        if (state.query.length < 2) {
            els.content.appendChild(h('p', { class: 'sp-note' }, t('sp_search_hint')));
            return;
        }
        if (state.results === null) {
            els.content.appendChild(skeleton());
            return;
        }

        const mine = (state.friends || []).filter((u) => (u.username + ' ' + (u.display_name || '')).toLowerCase().includes(query));
        const mineIds = new Set(mine.map((u) => Number(u.id)));
        const others = state.results.filter((u) => !mineIds.has(Number(u.id)));

        if (!mine.length && !others.length) {
            els.content.appendChild(empty('fa-solid fa-magnifying-glass', t('sp_empty_search'), t('sp_empty_search_text')));
            return;
        }
        if (mine.length) els.content.appendChild(section(t('sp_among_friends'), mine.length, grid(mine, 'friend')));
        if (others.length) els.content.appendChild(section(t('sp_other_users'), others.length, grid(others, 'person')));
    }

    function paintTab() {
        K.clear(els.content);

        if (state.failed && state.friends === null) {
            els.content.appendChild(empty('fa-solid fa-triangle-exclamation', t('sp_load_error'), '',
                h('button', { type: 'button', class: 'ck-btn ck-btn--primary', onClick: () => refresh(true) }, t('sp_retry'))));
            return;
        }

        const suggestButton = () => h('button', { type: 'button', class: 'ck-btn ck-btn--primary', onClick: () => setTab('suggestions') },
            icon('fa-solid fa-wand-magic-sparkles'), t('sp_see_suggestions'));

        switch (state.tab) {
            case 'online': {
                if (state.friends === null) return els.content.appendChild(skeleton());
                const online = state.friends.filter((u) => u.is_online);
                if (!online.length) return els.content.appendChild(empty('fa-regular fa-moon', t('sp_empty_online'), t('sp_empty_online_text')));
                return els.content.appendChild(grid(online, 'friend'));
            }
            case 'all': {
                if (state.friends === null) return els.content.appendChild(skeleton());
                if (!state.friends.length) return els.content.appendChild(empty('fa-solid fa-user-group', t('sp_empty_all'), t('sp_empty_all_text'), suggestButton()));
                const sorted = [...state.friends].sort((a, b) => (Number(b.is_online) - Number(a.is_online))
                    || (a.display_name || a.username).localeCompare(b.display_name || b.username, K.locale, { sensitivity: 'base' }));
                return els.content.appendChild(grid(sorted, 'friend'));
            }
            case 'requests': {
                if (state.requests === null) return els.content.appendChild(skeleton());
                const { received, sent } = state.requests;
                if (!received.length && !sent.length) return els.content.appendChild(empty('fa-regular fa-envelope-open', t('sp_empty_requests'), t('sp_empty_requests_text'), suggestButton()));
                if (received.length) els.content.appendChild(section(t('sp_received'), received.length, grid(received, 'received')));
                if (sent.length) els.content.appendChild(section(t('sp_sent'), sent.length, grid(sent, 'sent')));
                return undefined;
            }
            case 'suggestions': {
                if (state.suggestions === null) return els.content.appendChild(skeleton());
                if (!state.suggestions.length) return els.content.appendChild(empty('fa-solid fa-wand-magic-sparkles', t('sp_empty_suggestions'), t('sp_empty_suggestions_text')));
                return els.content.appendChild(grid(state.suggestions, 'person'));
            }
            case 'blocked': {
                if (state.blocked === null) return els.content.appendChild(skeleton());
                if (!state.blocked.length) return els.content.appendChild(empty('fa-solid fa-ban', t('sp_empty_blocked'), t('sp_empty_blocked_text')));
                return els.content.appendChild(grid(state.blocked, 'blocked'));
            }
            default:
                return undefined;
        }
    }

    function paint() {
        paintStats();
        paintTabs();
        if (state.query.length >= 1) paintSearch();
        else paintTab();
    }

    // ── Navigazione ────────────────────────────────────────────────────────

    function setTab(tab) {
        if (!TABS.includes(tab)) return;
        state.tab = tab;
        if (state.query) {
            state.query = '';
            state.results = null;
            els.search.value = '';
            els.clear.hidden = true;
        }
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        history.replaceState(null, '', url.pathname + url.search);
        paint();

        const missing = (tab === 'requests' && state.requests === null)
            || (tab === 'suggestions' && state.suggestions === null)
            || (tab === 'blocked' && state.blocked === null);
        if (missing) refresh(false);
    }

    const searchSoon = K.debounce(async () => {
        const query = state.query;
        try {
            await loadSearch();
        } catch (error) {
            state.results = [];
            K.toast(error.message, 'error');
        }
        if (query === state.query) paint();
    }, 320);

    els.search.addEventListener('input', () => {
        state.query = els.search.value.trim();
        state.results = null;
        els.clear.hidden = state.query === '';
        paint();
        if (state.query.length >= 2) searchSoon();
    });
    els.search.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && els.search.value) {
            event.preventDefault();
            els.clear.click();
        }
    });
    els.clear.addEventListener('click', () => {
        els.search.value = '';
        state.query = '';
        state.results = null;
        els.clear.hidden = true;
        paint();
        els.search.focus();
    });

    document.getElementById('spPrivacy').addEventListener('click', () => Card && Card.privacy());

    // La scheda utente e le altre schede del sito possono cambiare i rapporti.
    document.addEventListener('cripsum:social-changed', refreshSoon);
    if (RT) {
        RT.onUser((event) => {
            if (['sl', 'fr', 'fa', 'resync'].includes(event.t)) refreshSoon();
        });
    }

    // Chi è online cambia da solo: una rinfrescata al minuto, a scheda visibile.
    setInterval(() => {
        if (document.visibilityState === 'visible') refresh(false);
    }, 60000);

    (async () => {
        const wanted = new URLSearchParams(window.location.search).get('tab');
        paint();
        try {
            await Promise.all([loadFriends(), loadRequests()]);
        } catch (error) {
            state.failed = true;
        }

        if (TABS.includes(wanted)) state.tab = wanted;
        else if (state.counts.requests_received > 0) state.tab = 'requests';
        else if (state.counts.online > 0) state.tab = 'online';
        else state.tab = 'all';

        paint();
        if (state.tab === 'suggestions' || state.tab === 'blocked') refresh(false);
    })();
})();
