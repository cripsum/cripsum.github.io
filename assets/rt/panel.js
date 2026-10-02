/**
 * Cripsum™ — contenuto del menu delle notifiche della navbar.
 *
 * Il menu è un popover della navbar come quello dell'account (`#cnav-bell`,
 * emesso da includes/nav_render.php): aprirlo, chiuderlo, posizionarlo e
 * farlo diventare uno sheet su telefono è compito di js/navbar.js, lo stile
 * sta in css/navbar.css. Qui c'è solo ciò che ci va dentro.
 *
 * Due sezioni. «Notifiche» è quello che aspetta una risposta o una lettura
 * (richieste di amicizia e inviti, con i pulsanti per rispondere sul posto;
 * chat non lette; menzioni; ticket). «Posta» sono gli ultimi messaggi del
 * sito. Il contenuto si chiede al server solo quando il menu viene aperto
 * (api/notify/panel.php) e si aggiorna da solo finché resta aperto, con gli
 * eventi che assets/rt/rt.js riceve già.
 */
(() => {
    'use strict';

    const RT = window.CripsumRT;
    const pop = document.getElementById('cnav-bell');
    const body = document.getElementById('cnavBellBody');
    if (!RT || !pop || !body || window.CripsumNotifyPanel) return;

    const lang = RT.lang;
    const T = {
        it: {
            news: 'Notifiche',
            mail: 'Posta',
            readAll: 'Segna tutto come letto',
            accept: 'Accetta',
            decline: 'Rifiuta',
            emptyNews: 'Sei in pari: richieste, inviti e messaggi non letti compaiono qui.',
            emptyMail: 'Nessun messaggio dal sito.',
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
            news: 'Notifications',
            mail: 'Inbox',
            readAll: 'Mark all as read',
            accept: 'Accept',
            decline: 'Decline',
            emptyNews: 'You are all caught up: requests, invites and unread messages show up here.',
            emptyMail: 'No messages from the site.',
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

    const state = { data: null, loadedAt: 0, loading: false, failed: false, seenBefore: 0, firstTime: false };
    let reloadTimer = null;

    // ── Piccoli aiuti ──────────────────────────────────────────────────────

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function iconEl(name, extra) {
        const node = el('i', 'fa-solid ' + name + (extra ? ' ' + extra : ''));
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    /** Solo percorsi del sito: i link del menu non portano mai fuori. */
    function sitePath(url) {
        const value = String(url || '');
        return value.startsWith('/') && !value.startsWith('//') ? value : '/' + lang + '/inbox';
    }

    function ago(ts) {
        if (!ts) return '';
        const seconds = Math.max(0, Math.floor(Date.now() / 1000) - Number(ts));
        if (seconds < 60) return T.now;
        if (seconds < 3600) return T.min.replace('{n}', Math.floor(seconds / 60));
        if (seconds < 86400) return T.hour.replace('{n}', Math.floor(seconds / 3600));
        if (seconds < 14 * 86400) return T.day.replace('{n}', Math.floor(seconds / 86400));
        return new Date(ts * 1000).toLocaleDateString(lang === 'en' ? 'en-GB' : 'it-IT', { day: 'numeric', month: 'short' });
    }

    function isOpen() {
        try {
            if (pop.matches(':popover-open')) return true;
        } catch (_) {
            /* browser senza popover: vale la classe che mette navbar.js */
        }
        return pop.classList.contains('is-open');
    }

    function close() {
        try {
            if (pop.hasAttribute('popover') && typeof pop.hidePopover === 'function') {
                pop.hidePopover();
                return;
            }
        } catch (_) {
            /* non era aperto */
        }
        pop.classList.remove('is-open');
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

    async function request(url, payload) {
        const headers = { 'Accept': 'application/json', 'X-Cripsum-Lang': lang, 'X-Requested-With': 'XMLHttpRequest' };
        const options = { credentials: 'same-origin', cache: 'no-store', headers };
        if (payload) {
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-Token'] = document.querySelector('meta[name="csrf-token"]')?.content || document.body.dataset.csrf || '';
            options.method = 'POST';
            options.body = JSON.stringify(payload);
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

    function fail(error) {
        RT.toast({ key: 'panel-error', title: error.message, text: '', icon: 'fa-triangle-exclamation', duration: 4500 });
    }

    // ── Dati ───────────────────────────────────────────────────────────────

    async function load(force) {
        if (state.loading) return;
        if (!force && state.data && Date.now() - state.loadedAt < 15000) return;
        state.loading = true;
        try {
            state.data = await request('/api/notify/panel.php');
            state.loadedAt = Date.now();
            state.failed = false;
            RT.applyCounters(state.data.counters);
        } catch (_) {
            state.failed = true;
        } finally {
            state.loading = false;
            if (isOpen()) paint();
        }
    }

    function reloadSoon() {
        clearTimeout(reloadTimer);
        reloadTimer = setTimeout(() => load(true), 700);
    }

    // ── Disegno ────────────────────────────────────────────────────────────

    let rowIndex = 0;

    /** Riga del menu: una .cnav-row come quelle del pannello account, con avatar e due righe di testo. */
    function row(options) {
        const item = el('div', 'cnav-bell__item' + (options.unread ? ' is-unread' : ''));
        const link = el('a', 'cnav-row cnav-bell__row');
        link.href = options.href;
        link.setAttribute('role', 'menuitem');
        link.style.setProperty('--i', String(rowIndex++));
        if (options.onClick) link.addEventListener('click', options.onClick);

        const media = el('span', 'cnav-bell__media' + (options.tone ? ' cnav-bell__media--' + options.tone : ''));
        if (options.avatar) {
            const img = el('img');
            img.src = sitePath(options.avatar);
            img.alt = '';
            img.loading = 'lazy';
            media.appendChild(img);
        } else {
            media.classList.add('is-icon');
            media.appendChild(iconEl(options.icon || 'fa-bell'));
        }

        const text = el('span', 'cnav-bell__text');
        text.append(el('span', 'cnav-bell__title', options.title || ''), el('span', 'cnav-bell__sub', options.sub || ''));

        const meta = el('span', 'cnav-bell__meta');
        meta.appendChild(el('time', null, ago(options.ts)));
        if (options.count > 0) meta.appendChild(el('span', 'cnav-row__badge', options.count > 99 ? '99+' : String(options.count)));
        else if (options.gift) {
            const gift = iconEl('fa-gift', 'cnav-bell__gift');
            gift.title = T.rewards;
            meta.appendChild(gift);
        } else if (options.dot) meta.appendChild(el('span', 'cnav-bell__dot'));

        link.append(media, text, meta);
        item.appendChild(link);
        return item;
    }

    /** Accetta o rifiuta dal menu: la riga esce subito, e torna se il server dice di no. */
    async function answer(node, item, accept) {
        node.classList.add('is-busy');
        try {
            if (item.do === 'friend') {
                await request('/api/social/' + (accept ? 'accept_friend_request.php' : 'decline_friend_request.php'), { sender_id: item.user_id });
                document.dispatchEvent(new CustomEvent('cripsum:social-changed', { detail: { userId: item.user_id } }));
            } else {
                await request('/api/chat/' + (accept ? 'accept_invite.php' : 'decline_invite.php'), { chat_id: item.chat_id });
            }
            node.classList.add('is-gone');
            setTimeout(() => load(true), 200);
            RT.refreshCounters();
        } catch (error) {
            node.classList.remove('is-busy');
            fail(error);
        }
    }

    function newsRow(item) {
        const href = sitePath(item.url);
        const node = row({
            href,
            avatar: item.avatar,
            icon: item.icon || TYPE_ICONS[item.type],
            title: item.title,
            sub: item.text,
            ts: item.ts,
            count: item.count,
            unread: true,
            dot: !!item.passing && item.ts > state.seenBefore,
            onClick: (event) => {
                if (event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) return;
                // Sulle pagine di chat la conversazione si apre sul posto;
                // a chiudere il menu pensa navbar.js, come per ogni link.
                if (item.chat && RT.open({ chat: item.chat, url: href }, true)) event.preventDefault();
            }
        });

        if (item.do) {
            const actions = el('div', 'cnav-bell__actions');
            const yes = el('button', 'cnav-bell__btn cnav-bell__btn--go', T.accept);
            const no = el('button', 'cnav-bell__btn', T.decline);
            yes.type = no.type = 'button';
            yes.addEventListener('click', () => answer(node, item, true));
            no.addEventListener('click', () => answer(node, item, false));
            actions.append(yes, no);
            node.appendChild(actions);
        }
        return node;
    }

    function mailRow(item) {
        const category = MAIL_ICONS[item.category] ? item.category : 'system';
        return row({
            href: '/' + lang + '/inbox?m=' + encodeURIComponent(item.id),
            icon: MAIL_ICONS[category],
            tone: category === 'rewards' ? 'rewards' : '',
            title: item.title,
            sub: T.cat[category],
            ts: item.ts,
            unread: item.unread,
            gift: item.rewards,
            dot: item.unread
        });
    }

    function section(title, count, action) {
        const wrap = el('div', 'cnav-sect');
        const head = el('div', 'cnav-bell__head');
        head.appendChild(el('p', 'cnav-sect__label', title));
        if (count > 0) head.appendChild(el('span', 'cnav-bell__count', count > 99 ? '99+' : String(count)));
        if (action) head.appendChild(action);
        wrap.appendChild(head);
        return wrap;
    }

    function skeleton(wrap) {
        for (let i = 0; i < 3; i += 1) {
            const line = el('div', 'cnav-bell__skel');
            line.append(el('span'), el('span'));
            wrap.appendChild(line);
        }
    }

    function paint() {
        const data = state.data;
        rowIndex = 0;
        body.textContent = '';

        if (state.firstTime) body.appendChild(el('p', 'cnav-bell__intro', T.intro));

        const news = data ? data.notifications || [] : [];
        const mail = data ? data.mail || [] : [];
        const mailUnread = data ? Number(data.mail_unread) || 0 : 0;

        const newsSection = section(T.news, news.length);
        if (!data && state.failed) {
            newsSection.appendChild(el('p', 'cnav-bell__note', T.error));
            const actions = el('div', 'cnav-bell__actions');
            const retry = el('button', 'cnav-bell__btn', T.retry);
            retry.type = 'button';
            retry.addEventListener('click', () => {
                state.failed = false;
                paint();
                load(true);
            });
            actions.appendChild(retry);
            newsSection.appendChild(actions);
        } else if (!data) {
            skeleton(newsSection);
        } else if (!news.length) {
            newsSection.appendChild(el('p', 'cnav-bell__note', T.emptyNews));
        } else {
            news.forEach((item) => newsSection.appendChild(newsRow(item)));
        }
        body.appendChild(newsSection);

        let readAll = null;
        if (mailUnread > 0) {
            readAll = el('button', 'cnav-bell__link', T.readAll);
            readAll.type = 'button';
            readAll.addEventListener('click', async () => {
                readAll.disabled = true;
                try {
                    await request('/api/inbox.php', { action: 'read_all' });
                    RT.refreshCounters();
                    await load(true);
                } catch (error) {
                    readAll.disabled = false;
                    fail(error);
                }
            });
        }
        const mailSection = section(T.mail, mailUnread, readAll);
        if (!data && !state.failed) skeleton(mailSection);
        else if (!mail.length) mailSection.appendChild(el('p', 'cnav-bell__note', T.emptyMail));
        else mail.forEach((item) => mailSection.appendChild(mailRow(item)));
        body.appendChild(mailSection);
    }

    // ── Collegamenti ───────────────────────────────────────────────────────

    function onOpened() {
        const seen = readSeen();
        state.seenBefore = Number(seen.at) || 0;
        state.firstTime = !seen.at;
        writeSeen({ at: Math.floor(Date.now() / 1000) });
        paint();
        load(false);
    }

    // Con l'API popover l'apertura la annuncia il browser; senza, navbar.js
    // mette una classe al clic sul pulsante.
    pop.addEventListener('toggle', (event) => {
        if (event.newState === 'open') onOpened();
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.cnav-inbox') || pop.hasAttribute('popover')) return;
        setTimeout(() => {
            if (isOpen()) onOpened();
        }, 0);
    });

    const alerts = document.getElementById('cnavBellAlerts');
    if (alerts) {
        alerts.addEventListener('click', () => {
            close();
            RT.openSettings();
        });
    }

    RT.onUser((event) => {
        if (isOpen() && ['pm', 'gm', 'fr', 'fa', 'gi', 'ib', 'tk', 'mn', 'ls', 'sl', 'rd', 'resync'].includes(event.t)) reloadSoon();
        else state.loadedAt = 0;
    });

    window.CripsumNotifyPanel = { close, reload: () => load(true) };
})();
