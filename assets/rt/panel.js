/**
 * Cripsum™ — contenuto del menu delle notifiche della navbar.
 *
 * Il menu è un popover della navbar come quello dell'account (`#cnav-bell`,
 * emesso da includes/nav_render.php): posizionarlo, chiuderlo e farlo
 * diventare uno sheet su telefono è compito di js/navbar.js, lo stile sta in
 * css/navbar.css. Qui c'è ciò che ci va dentro, e il clic sulla campanella.
 *
 * Due linguette. «Notifiche» è quello che aspetta una risposta o una lettura
 * (richieste di amicizia e inviti, con i pulsanti per rispondere sul posto;
 * chat non lette e menzioni nella chat globale, col numero di messaggi;
 * ticket). «Posta» sono gli ultimi messaggi del sito. Il numero accanto a
 * «Notifiche» conta i messaggi, non le righe. Le due sezioni stanno una accanto all'altra e si scorre dall'una
 * all'altra; quella scelta è scritta in `data-tab` sul popover, ed è il CSS
 * a muovere cursore e binario. Il contenuto si chiede al server solo quando
 * il menu viene aperto (api/notify/panel.php) e si aggiorna da solo finché
 * resta aperto, con gli eventi che assets/rt/rt.js riceve già.
 *
 * La campanella è un link alla posta: il clic normale apre il menu, con
 * Ctrl, Shift o il tasto centrale resta un link.
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

    const TABS = ['news', 'mail'];
    const MAIL_ICONS = { system: 'fa-gear', social: 'fa-user-group', rewards: 'fa-gift', special: 'fa-star' };
    const TYPE_ICONS = { ticket: 'fa-headset', mention: 'fa-at', inbox: 'fa-envelope' };
    const SEEN_KEY = 'cripsum.rt.panel.' + RT.userId;

    const state = { tab: 'news', data: null, loadedAt: 0, loading: false, failed: false, seenBefore: 0, firstTime: false };
    const ui = { intro: null, panes: null, tab: {}, badge: {}, pane: {} };
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

    function capped(count) {
        return count > 99 ? '99+' : String(count);
    }

    /** Falso dove manca l'API popover: lì navbar.js toglie l'attributo e apre con una classe. */
    function usesPopover() {
        return pop.hasAttribute('popover') && typeof pop.showPopover === 'function';
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
            if (usesPopover()) {
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

    // ── Linguette ──────────────────────────────────────────────────────────

    /**
     * Il riquadro prende l'altezza della sezione in vista: senza, resterebbe
     * alto quanto la più lunga delle due. Con il menu chiuso non c'è niente
     * da misurare, e si lascia com'è.
     */
    function fitHeight() {
        const pane = ui.pane[state.tab];
        if (!pane || !pane.offsetHeight) return;
        ui.panes.style.height = pane.offsetHeight + 'px';
    }

    /**
     * La sezione nascosta resta nel documento, accanto all'altra: va tolta
     * dalla tastiera, e dalle voci che navbar.js percorre con le frecce.
     */
    function syncPanes() {
        TABS.forEach((name) => {
            const active = name === state.tab;
            const pane = ui.pane[name];
            ui.tab[name].setAttribute('aria-selected', active ? 'true' : 'false');
            ui.tab[name].tabIndex = active ? 0 : -1;
            pane.inert = !active;
            pane.setAttribute('aria-hidden', active ? 'false' : 'true');
            pane.querySelectorAll('.cnav-bell__row').forEach((link) => {
                if (active) link.setAttribute('role', 'menuitem');
                else link.removeAttribute('role');
            });
        });
    }

    function selectTab(name, focus) {
        if (!TABS.includes(name)) return;
        state.tab = name;
        pop.dataset.tab = name;
        syncPanes();
        fitHeight();
        if (focus) ui.tab[name].focus({ preventScroll: true });
    }

    /** Una voce passata dal timbro (un'amicizia accettata) è nuova solo finché il menu non è stato aperto. */
    function isFresh(item) {
        return !item.passing || Number(item.ts) > state.seenBefore;
    }

    /** Quanto c'è da leggere fra le notifiche: i messaggi di ogni chat, uno per ogni altra voce. */
    function pendingCount(items) {
        return items.reduce((sum, item) => sum + (isFresh(item) ? Math.max(1, Number(item.count) || 0) : 0), 0);
    }

    /** Si parte da dove c'è qualcosa: prima le notifiche, altrimenti la posta. */
    function pickTab() {
        const counters = RT.counters || {};
        const pendingNews = state.data && Date.now() - state.loadedAt < 15000
            ? pendingCount(state.data.notifications || [])
            : (Number(counters.friends) || 0) + (Number(counters.chat) || 0) + (Number(counters.mentions) || 0);
        return pendingNews > 0 || !(Number(counters.inbox) > 0) ? 'news' : 'mail';
    }

    function build() {
        body.textContent = '';

        ui.intro = el('p', 'cnav-bell__intro', T.intro);
        ui.intro.hidden = true;

        const tabs = el('div', 'cnav-bell__tabs');
        tabs.setAttribute('role', 'tablist');
        const thumb = el('span', 'cnav-bell__thumb');
        thumb.setAttribute('aria-hidden', 'true');
        tabs.appendChild(thumb);

        ui.panes = el('div', 'cnav-bell__panes');
        const track = el('div', 'cnav-bell__track');

        TABS.forEach((name) => {
            const tab = el('button', 'cnav-bell__tab');
            tab.type = 'button';
            tab.id = 'cnavBellTab-' + name;
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-controls', 'cnavBellPane-' + name);
            const badge = el('span', 'cnav-row__badge');
            badge.hidden = true;
            tab.append(el('span', null, T[name]), badge);
            tab.addEventListener('click', () => selectTab(name, false));
            tabs.appendChild(tab);

            const pane = el('div', 'cnav-bell__pane');
            pane.id = 'cnavBellPane-' + name;
            pane.dataset.pane = name;
            pane.setAttribute('role', 'tabpanel');
            pane.setAttribute('aria-labelledby', tab.id);
            track.appendChild(pane);

            ui.tab[name] = tab;
            ui.badge[name] = badge;
            ui.pane[name] = pane;
        });

        ui.panes.appendChild(track);
        body.append(ui.intro, tabs, ui.panes);

        // Frecce a destra e sinistra cambiano linguetta da qualsiasi punto
        // del menu: su e giù sono già di navbar.js, e Tab lo chiude.
        pop.addEventListener('keydown', (event) => {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
            if (event.target.closest('input, textarea')) return;
            event.preventDefault();
            selectTab(event.key === 'ArrowRight' ? 'mail' : 'news', true);
        });

        // Su telefono le due sezioni si sfogliano anche col dito.
        let startX = 0;
        let startY = 0;
        ui.panes.addEventListener('touchstart', (event) => {
            const touch = event.touches[0];
            startX = touch.clientX;
            startY = touch.clientY;
        }, { passive: true });
        ui.panes.addEventListener('touchend', (event) => {
            const touch = event.changedTouches[0];
            const dx = touch.clientX - startX;
            const dy = touch.clientY - startY;
            if (Math.abs(dx) < 56 || Math.abs(dx) < Math.abs(dy) * 1.6) return;
            selectTab(dx < 0 ? 'mail' : 'news', false);
        }, { passive: true });

        // Le righe arrivano dopo, e un carattere caricato tardi le allunga:
        // l'altezza del riquadro segue quella della sezione, non il contrario.
        if (typeof ResizeObserver === 'function') {
            const watcher = new ResizeObserver(fitHeight);
            TABS.forEach((name) => watcher.observe(ui.pane[name]));
        }

        selectTab(state.tab, false);
    }

    // ── Disegno ────────────────────────────────────────────────────────────

    let rowIndex = 0;

    /** Riga del menu: una .cnav-row come quelle del pannello account, con avatar e due righe di testo. */
    function row(options) {
        const item = el('div', 'cnav-bell__item' + (options.unread ? ' is-unread' : ''));
        const link = el('a', 'cnav-row cnav-bell__row');
        link.href = options.href;
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
        if (options.count > 0) meta.appendChild(el('span', 'cnav-row__badge', capped(options.count)));
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
            unread: isFresh(item),
            dot: !!item.passing && isFresh(item),
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

    function skeleton(wrap) {
        for (let i = 0; i < 3; i += 1) {
            const line = el('div', 'cnav-bell__skel');
            line.append(el('span'), el('span'));
            wrap.appendChild(line);
        }
    }

    function setBadge(name, count) {
        const badge = ui.badge[name];
        badge.hidden = !(count > 0);
        badge.textContent = count > 0 ? capped(count) : '';
    }

    function readAllBar() {
        const bar = el('div', 'cnav-bell__bar');
        const button = el('button', 'cnav-bell__link', T.readAll);
        button.type = 'button';
        button.addEventListener('click', async () => {
            button.disabled = true;
            try {
                await request('/api/inbox.php', { action: 'read_all' });
                // La pagina della posta, se è quella aperta sotto, rilegge l'elenco.
                document.dispatchEvent(new CustomEvent('cripsum:inbox-changed'));
                RT.refreshCounters();
                await load(true);
            } catch (error) {
                button.disabled = false;
                fail(error);
            }
        });
        bar.appendChild(button);
        return bar;
    }

    function paint() {
        const data = state.data;
        const news = data ? data.notifications || [] : [];
        const mail = data ? data.mail || [] : [];
        const mailUnread = data ? Number(data.mail_unread) || 0 : Number((RT.counters || {}).inbox) || 0;
        const newsPane = ui.pane.news;
        const mailPane = ui.pane.mail;

        ui.intro.hidden = !state.firstTime;
        setBadge('news', pendingCount(news));
        setBadge('mail', mailUnread);

        rowIndex = 0;
        newsPane.textContent = '';
        if (!data && state.failed) {
            newsPane.appendChild(el('p', 'cnav-bell__note', T.error));
            const actions = el('div', 'cnav-bell__actions');
            const retry = el('button', 'cnav-bell__btn', T.retry);
            retry.type = 'button';
            retry.addEventListener('click', () => {
                state.failed = false;
                paint();
                load(true);
            });
            actions.appendChild(retry);
            newsPane.appendChild(actions);
        } else if (!data) {
            skeleton(newsPane);
        } else if (!news.length) {
            newsPane.appendChild(el('p', 'cnav-bell__note', T.emptyNews));
        } else {
            news.forEach((item) => newsPane.appendChild(newsRow(item)));
        }

        rowIndex = 0;
        mailPane.textContent = '';
        if (!data && state.failed) {
            mailPane.appendChild(el('p', 'cnav-bell__note', T.error));
        } else if (!data) {
            skeleton(mailPane);
        } else if (!mail.length) {
            mailPane.appendChild(el('p', 'cnav-bell__note', T.emptyMail));
        } else {
            if (mailUnread > 0) mailPane.appendChild(readAllBar());
            mail.forEach((item) => mailPane.appendChild(mailRow(item)));
        }

        syncPanes();
        fitHeight();
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

    build();

    // La linguetta di partenza si sceglie prima che il menu compaia: fatto
    // dopo, cursore e binario scorrerebbero mentre il menu si sta aprendo.
    pop.addEventListener('beforetoggle', (event) => {
        if (event.newState !== 'open') return;
        ui.panes.style.height = '';
        selectTab(pickTab(), false);
    });
    pop.addEventListener('toggle', (event) => {
        if (event.newState === 'open') onOpened();
    });

    // La campanella è un link: il clic normale apre il menu, al posto di
    // seguirlo. Cliccare fuori da un popover lo chiude, e la campanella è
    // fuori: per non riaprirlo subito va ricordato com'era alla pressione.
    let pressedWhileOpen = false;
    document.addEventListener('pointerdown', (event) => {
        pressedWhileOpen = isOpen() && !!(event.target.closest && event.target.closest('.cnav-inbox'));
    }, true);

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('.cnav-inbox');
        if (!trigger) return;
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();

        // Da tastiera non c'è stata alcuna pressione: conta solo lo stato.
        const wasOpen = event.detail > 0 && pressedWhileOpen;
        pressedWhileOpen = false;

        if (!usesPopover()) {
            // Senza API popover ha già aperto o chiuso navbar.js.
            if (isOpen()) {
                selectTab(pickTab(), false);
                onOpened();
            }
            return;
        }

        if (wasOpen || isOpen()) {
            close();
            return;
        }
        try {
            pop.showPopover();
        } catch (_) {
            window.location.href = trigger.href;
        }
    });

    const alerts = document.getElementById('cnavBellAlerts');
    if (alerts) {
        alerts.addEventListener('click', () => {
            close();
            RT.openSettings();
        });
    }

    RT.onUser((event) => {
        if (isOpen() && ['pm', 'gm', 'fr', 'fa', 'gi', 'ib', 'tk', 'mn', 'mr', 'ls', 'sl', 'rd', 'resync'].includes(event.t)) reloadSoon();
        else state.loadedAt = 0;
    });

    window.CripsumNotifyPanel = { close, reload: () => load(true) };
})();
