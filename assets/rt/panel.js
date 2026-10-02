/**
 * Cripsum™ — menu delle notifiche della navbar.
 *
 * Il pulsante con la campanella non porta più dritto alla posta: apre un
 * menu con due parti. «Notifiche» è quello che aspetta una risposta o una
 * lettura (richieste di amicizia e inviti, con i pulsanti per rispondere sul
 * posto; chat non lette; menzioni; ticket). «Posta» sono gli ultimi messaggi
 * del sito. Da qui si arriva alla pagina completa.
 *
 * Il contenuto si chiede al server solo quando il menu viene aperto
 * (api/notify/panel.php) e si aggiorna da solo finché resta aperto, con gli
 * eventi che assets/rt/rt.js riceve già. Ctrl+clic e clic centrale sul
 * pulsante aprono la posta come un link normale.
 */
(() => {
    'use strict';

    const RT = window.CripsumRT;
    if (!RT || window.CripsumNotifyPanel) return;

    const lang = RT.lang;
    const T = {
        it: {
            title: 'Notifiche',
            tabNews: 'Notifiche',
            tabMail: 'Posta',
            settings: 'Avvisi e suoni',
            close: 'Chiudi',
            openInbox: 'Apri la posta',
            readAll: 'Segna tutto come letto',
            accept: 'Accetta',
            decline: 'Rifiuta',
            emptyNews: 'Sei in pari',
            emptyNewsText: 'Richieste, inviti e messaggi non letti compaiono qui.',
            emptyMail: 'Nessun messaggio',
            emptyMailText: 'Le comunicazioni del sito e i premi arrivano qui.',
            error: 'Non è stato possibile caricare le notifiche.',
            retry: 'Riprova',
            rewards: 'Premi da riscattare',
            now: 'adesso',
            min: '{n} min',
            hour: '{n} h',
            day: '{n} g',
            intro: 'Notifiche e posta ora stanno qui, in un posto solo.',
            cat: { system: 'Sistema', social: 'Social', rewards: 'Premi', special: 'Speciale' }
        },
        en: {
            title: 'Notifications',
            tabNews: 'Notifications',
            tabMail: 'Inbox',
            settings: 'Alerts and sounds',
            close: 'Close',
            openInbox: 'Open the inbox',
            readAll: 'Mark all as read',
            accept: 'Accept',
            decline: 'Decline',
            emptyNews: 'You are all caught up',
            emptyNewsText: 'Requests, invites and unread messages show up here.',
            emptyMail: 'No messages',
            emptyMailText: 'Site announcements and rewards arrive here.',
            error: 'The notifications could not be loaded.',
            retry: 'Retry',
            rewards: 'Rewards to claim',
            now: 'now',
            min: '{n} min',
            hour: '{n} h',
            day: '{n} d',
            intro: 'Notifications and inbox now live here, in one place.',
            cat: { system: 'System', social: 'Social', rewards: 'Rewards', special: 'Special' }
        }
    }[lang];

    const MAIL_ICONS = { system: 'fa-gear', social: 'fa-user-group', rewards: 'fa-gift', special: 'fa-star' };
    const TYPE_ICONS = { ticket: 'fa-headset', mention: 'fa-at', inbox: 'fa-envelope' };
    const SEEN_KEY = 'cripsum.rt.panel.' + RT.userId;

    const state = {
        open: false,
        tab: 'news',
        data: null,
        loadedAt: 0,
        loading: false,
        failed: false,
        anchor: null,
        seenBefore: 0
    };
    let panel = null;
    let backdrop = null;
    let reloadTimer = null;

    // ── Piccoli aiuti ──────────────────────────────────────────────────────

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function iconEl(name) {
        const node = el('i', 'fa-solid ' + name);
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    /** Solo percorsi del sito: i link del menu non portano mai fuori. */
    function sitePath(url) {
        const value = String(url || '');
        return value.startsWith('/') && !value.startsWith('//') ? value : '/' + lang + '/inbox';
    }

    function ago(ts) {
        const seconds = Math.max(0, Math.floor(Date.now() / 1000) - Number(ts || 0));
        if (!ts) return '';
        if (seconds < 60) return T.now;
        if (seconds < 3600) return T.min.replace('{n}', Math.floor(seconds / 60));
        if (seconds < 86400) return T.hour.replace('{n}', Math.floor(seconds / 3600));
        if (seconds < 14 * 86400) return T.day.replace('{n}', Math.floor(seconds / 86400));
        return new Date(ts * 1000).toLocaleDateString(lang === 'en' ? 'en-GB' : 'it-IT', { day: 'numeric', month: 'short' });
    }

    function readSeen() {
        try {
            return JSON.parse(localStorage.getItem(SEEN_KEY) || 'null') || {};
        } catch (_) {
            return {};
        }
    }

    function writeSeen(value) {
        try {
            localStorage.setItem(SEEN_KEY, JSON.stringify(value));
        } catch (_) {
            /* senza memoria il menu funziona lo stesso */
        }
    }

    async function request(url, body) {
        const headers = { 'Accept': 'application/json', 'X-Cripsum-Lang': lang, 'X-Requested-With': 'XMLHttpRequest' };
        const options = { credentials: 'same-origin', cache: 'no-store', headers };
        if (body) {
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-Token'] = document.querySelector('meta[name="csrf-token"]')?.content || document.body.dataset.csrf || '';
            options.method = 'POST';
            options.body = JSON.stringify(body);
        }
        const response = await fetch(url, options);
        let data = null;
        try {
            data = await response.json();
        } catch (_) {
            data = null;
        }
        if (!response.ok || !data || data.ok === false || data.success === false) {
            const message = (data && (data.error?.message || (typeof data.error === 'string' ? data.error : ''))) || T.error;
            throw new Error(message);
        }
        return data;
    }

    // ── Dati ───────────────────────────────────────────────────────────────

    async function load(force) {
        if (state.loading) return;
        if (!force && state.data && Date.now() - state.loadedAt < 15000) return;
        state.loading = true;
        if (!state.data) paint();
        try {
            state.data = await request('/api/notify/panel.php');
            state.loadedAt = Date.now();
            state.failed = false;
            RT.applyCounters(state.data.counters);
        } catch (_) {
            state.failed = true;
        } finally {
            state.loading = false;
            if (state.open) paint();
        }
    }

    function reloadSoon() {
        clearTimeout(reloadTimer);
        reloadTimer = setTimeout(() => load(true), 700);
    }

    // ── Disegno ────────────────────────────────────────────────────────────

    function mediaFor(item) {
        const media = el('span', 'crt-panel__media');
        if (item.avatar) {
            const img = el('img');
            img.src = sitePath(item.avatar);
            img.alt = '';
            img.loading = 'lazy';
            media.appendChild(img);
        } else {
            media.classList.add('is-icon');
            media.appendChild(iconEl(item.icon || TYPE_ICONS[item.type] || 'fa-bell'));
        }
        return media;
    }

    /** Accetta o rifiuta dal menu: la riga sparisce subito, e torna se il server dice di no. */
    async function answer(row, item, accept) {
        row.classList.add('is-busy');
        try {
            if (item.do === 'friend') {
                await request('/api/social/' + (accept ? 'accept_friend_request.php' : 'decline_friend_request.php'), { sender_id: item.user_id });
                document.dispatchEvent(new CustomEvent('cripsum:social-changed', { detail: { userId: item.user_id } }));
            } else {
                await request('/api/chat/' + (accept ? 'accept_invite.php' : 'decline_invite.php'), { chat_id: item.chat_id });
            }
            row.classList.add('is-gone');
            setTimeout(() => load(true), 220);
            RT.refreshCounters();
        } catch (error) {
            row.classList.remove('is-busy');
            RT.toast({ key: 'panel-error', title: error.message, text: '', icon: 'fa-triangle-exclamation', duration: 4500 });
        }
    }

    function newsRow(item) {
        const row = el('div', 'crt-panel__row');
        const link = el('a', 'crt-panel__link');
        link.href = sitePath(item.url);
        link.addEventListener('click', (event) => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) return;
            // Sulle pagine di chat la conversazione si apre sul posto.
            if (item.chat && RT.open({ chat: item.chat, url: link.href }, true)) {
                event.preventDefault();
                close();
            }
        });

        const body = el('span', 'crt-panel__body');
        const title = el('strong', null, item.title || '');
        const text = el('span', null, item.text || '');
        body.append(title, text);

        const meta = el('span', 'crt-panel__meta');
        meta.appendChild(el('time', null, ago(item.ts)));
        if (item.count > 0) meta.appendChild(el('b', 'crt-panel__count', item.count > 99 ? '99+' : String(item.count)));
        else if (item.passing && item.ts > state.seenBefore) meta.appendChild(el('i', 'crt-panel__dot'));

        link.append(mediaFor(item), body, meta);
        row.appendChild(link);

        if (item.do) {
            const actions = el('div', 'crt-panel__actions');
            const yes = el('button', 'crt-panel__btn crt-panel__btn--primary', T.accept);
            const no = el('button', 'crt-panel__btn', T.decline);
            yes.type = no.type = 'button';
            yes.addEventListener('click', () => answer(row, item, true));
            no.addEventListener('click', () => answer(row, item, false));
            actions.append(yes, no);
            row.appendChild(actions);
        }
        return row;
    }

    function mailRow(item) {
        const row = el('div', 'crt-panel__row' + (item.unread ? ' is-unread' : ''));
        const link = el('a', 'crt-panel__link');
        link.href = '/' + lang + '/inbox?m=' + encodeURIComponent(item.id);

        const media = el('span', 'crt-panel__media is-icon crt-panel__media--' + (MAIL_ICONS[item.category] ? item.category : 'system'));
        media.appendChild(iconEl(MAIL_ICONS[item.category] || 'fa-envelope'));

        const body = el('span', 'crt-panel__body');
        body.append(el('strong', null, item.title || ''), el('span', null, T.cat[item.category] || T.cat.system));

        const meta = el('span', 'crt-panel__meta');
        meta.appendChild(el('time', null, ago(item.ts)));
        if (item.rewards) {
            const gift = iconEl('fa-gift');
            gift.classList.add('crt-panel__gift');
            gift.title = T.rewards;
            meta.appendChild(gift);
        } else if (item.unread) {
            meta.appendChild(el('i', 'crt-panel__dot'));
        }

        link.append(media, body, meta);
        row.appendChild(link);
        return row;
    }

    function emptyState(iconName, title, text) {
        const box = el('div', 'crt-panel__empty');
        box.append(iconEl(iconName), el('strong', null, title), el('span', null, text));
        return box;
    }

    function paint() {
        if (!panel) return;
        const data = state.data;
        const news = data ? data.notifications || [] : [];
        const mail = data ? data.mail || [] : [];
        const mailUnread = data ? Number(data.mail_unread) || 0 : 0;

        const tabs = panel.querySelector('.crt-panel__tabs');
        tabs.textContent = '';
        [['news', T.tabNews, news.length], ['mail', T.tabMail, mailUnread]].forEach(([key, label, count]) => {
            const tab = el('button', 'crt-panel__tab' + (state.tab === key ? ' is-active' : ''));
            tab.type = 'button';
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-selected', state.tab === key ? 'true' : 'false');
            tab.appendChild(document.createTextNode(label));
            if (count > 0) tab.appendChild(el('b', null, count > 99 ? '99+' : String(count)));
            tab.addEventListener('click', () => {
                state.tab = key;
                paint();
            });
            tabs.appendChild(tab);
        });

        const list = panel.querySelector('.crt-panel__list');
        list.textContent = '';

        if (!data) {
            if (state.failed) {
                const box = emptyState('fa-triangle-exclamation', T.error, '');
                const retry = el('button', 'crt-panel__btn crt-panel__btn--primary', T.retry);
                retry.type = 'button';
                retry.addEventListener('click', () => load(true));
                box.appendChild(retry);
                list.appendChild(box);
            } else {
                for (let i = 0; i < 4; i += 1) {
                    const skeleton = el('div', 'crt-panel__row crt-panel__row--skeleton');
                    skeleton.append(el('span', 'crt-panel__media'), el('span', 'crt-panel__body'));
                    list.appendChild(skeleton);
                }
            }
        } else if (state.tab === 'news') {
            if (!news.length) list.appendChild(emptyState('fa-circle-check', T.emptyNews, T.emptyNewsText));
            else news.forEach((item) => list.appendChild(newsRow(item)));
        } else if (!mail.length) {
            list.appendChild(emptyState('fa-envelope-open', T.emptyMail, T.emptyMailText));
        } else {
            mail.forEach((item) => list.appendChild(mailRow(item)));
        }

        const readAll = panel.querySelector('.crt-panel__readall');
        readAll.hidden = !(state.tab === 'mail' && mailUnread > 0);
    }

    function build() {
        panel = el('div', 'crt-panel');
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', T.title);
        panel.tabIndex = -1;

        const head = el('header', 'crt-panel__head');
        head.appendChild(el('h3', null, T.title));
        const tools = el('div', 'crt-panel__tools');
        const settings = el('button', 'crt-panel__tool');
        settings.type = 'button';
        settings.title = T.settings;
        settings.setAttribute('aria-label', T.settings);
        settings.appendChild(iconEl('fa-sliders'));
        settings.addEventListener('click', () => {
            close();
            RT.openSettings();
        });
        const shut = el('button', 'crt-panel__tool');
        shut.type = 'button';
        shut.setAttribute('aria-label', T.close);
        shut.appendChild(iconEl('fa-xmark'));
        shut.addEventListener('click', () => close());
        tools.append(settings, shut);
        head.appendChild(tools);

        const tabs = el('div', 'crt-panel__tabs');
        tabs.setAttribute('role', 'tablist');

        const intro = el('p', 'crt-panel__intro', T.intro);
        intro.hidden = true;

        const list = el('div', 'crt-panel__list');

        const foot = el('footer', 'crt-panel__foot');
        const readAll = el('button', 'crt-panel__readall', T.readAll);
        readAll.type = 'button';
        readAll.hidden = true;
        readAll.addEventListener('click', async () => {
            readAll.disabled = true;
            try {
                await request('/api/inbox.php', { action: 'read_all' });
                await load(true);
                RT.refreshCounters();
            } catch (error) {
                RT.toast({ key: 'panel-error', title: error.message, text: '', icon: 'fa-triangle-exclamation', duration: 4500 });
            } finally {
                readAll.disabled = false;
            }
        });
        const open = el('a', 'crt-panel__open', T.openInbox);
        open.href = '/' + lang + '/inbox';
        open.appendChild(iconEl('fa-arrow-right'));
        foot.append(readAll, open);

        panel.append(head, tabs, intro, list, foot);

        backdrop = el('div', 'crt-panel-backdrop');
        backdrop.addEventListener('click', () => close());

        // Scritto anche in linea: il foglio del profilo rimette nel flusso i
        // figli diretti di body che non dichiarano qui «position: fixed».
        panel.style.position = 'fixed';
        backdrop.style.position = 'fixed';
    }

    function place() {
        if (!panel || !state.anchor) return;
        const sheet = window.matchMedia('(max-width: 640px)').matches;
        panel.classList.toggle('crt-panel--sheet', sheet);
        if (sheet) {
            panel.style.top = '';
            panel.style.right = '';
            return;
        }
        const rect = state.anchor.getBoundingClientRect();
        panel.style.top = Math.round(rect.bottom + 12) + 'px';
        panel.style.right = Math.max(12, Math.round(window.innerWidth - rect.right - 6)) + 'px';
    }

    function onKey(event) {
        if (event.key === 'Escape') {
            event.stopPropagation();
            close();
        }
    }

    function onOutside(event) {
        if (panel.contains(event.target) || event.target.closest('.cnav-inbox')) return;
        close();
    }

    function open(anchor) {
        if (!panel) build();
        state.anchor = anchor;
        state.open = true;

        const seen = readSeen();
        state.seenBefore = Number(seen.at) || 0;
        const firstTime = !seen.at;
        panel.querySelector('.crt-panel__intro').hidden = !firstTime;
        writeSeen({ at: Math.floor(Date.now() / 1000) });

        // Si parte da dove c'è qualcosa: prima le notifiche, altrimenti la posta.
        const counters = RT.counters;
        const pendingNews = counters.friends + counters.chat;
        state.tab = pendingNews > 0 || counters.inbox === 0 ? 'news' : 'mail';

        document.body.append(backdrop, panel);
        place();
        paint();
        load(false);

        document.querySelectorAll('.cnav-inbox').forEach((button) => button.setAttribute('aria-expanded', 'true'));
        document.addEventListener('keydown', onKey, true);
        document.addEventListener('pointerdown', onOutside, true);
        window.addEventListener('resize', place);
        panel.focus({ preventScroll: true });
    }

    function close() {
        if (!state.open) return;
        state.open = false;
        document.removeEventListener('keydown', onKey, true);
        document.removeEventListener('pointerdown', onOutside, true);
        window.removeEventListener('resize', place);
        document.querySelectorAll('.cnav-inbox').forEach((button) => button.setAttribute('aria-expanded', 'false'));
        panel.remove();
        backdrop.remove();
        if (state.anchor && state.anchor.focus) state.anchor.focus({ preventScroll: true });
    }

    // ── Collegamenti ───────────────────────────────────────────────────────

    document.addEventListener('click', (event) => {
        const button = event.target.closest('.cnav-inbox');
        if (!button) return;
        // Con Ctrl, Shift o il tasto centrale resta un link alla posta.
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) return;
        event.preventDefault();
        if (state.open) close();
        else open(button);
    });

    document.querySelectorAll('.cnav-inbox').forEach((button) => {
        button.setAttribute('aria-haspopup', 'dialog');
        button.setAttribute('aria-expanded', 'false');
    });

    RT.onUser((event) => {
        if (state.open && ['pm', 'gm', 'fr', 'fa', 'gi', 'ib', 'tk', 'mn', 'ls', 'sl', 'rd', 'resync'].includes(event.t)) reloadSoon();
        else state.loadedAt = 0;
    });

    window.CripsumNotifyPanel = { open, close, reload: () => load(true) };
})();
