/**
 * Cripsum™ — tempo reale e avvisi, su ogni pagina con la navbar.
 *
 * Fa una domanda sola e leggera al server («c'è qualcosa di nuovo?»,
 * api/rt/poll.php: non apre il database) e smista le risposte:
 *
 *   - alle pagine che si sono registrate (chat private, chat globale, amici),
 *     che così non hanno un loro giro di controllo separato;
 *   - alla navbar, che aggiorna i contatori di posta, chat, menzioni e
 *     amici senza ricaricare;
 *   - agli avvisi: un riquadro in pagina, il suono, il numero nel titolo
 *     della scheda e, se l'utente lo ha attivato, la notifica del browser.
 *
 * Le notifiche del browser non dipendono da questo giro: una scheda in
 * secondo piano viene rallentata (su telefono sospesa) e arriverebbero tardi
 * o mai. Chi le attiva iscrive il browser alle notifiche push: il server
 * manda un segnale, lo riceve /sw.js anche a sito chiuso, ed è lui a
 * mostrarle. Qui resta l'iscrizione, e un ripiego per i browser senza push.
 *
 * Il ritmo si adatta: veloce mentre si chatta, lento sulle altre pagine,
 * più lento ancora a scheda nascosta, e sempre più rado se il server non
 * risponde.
 */
(() => {
    'use strict';

    if (window.CripsumRT) return;

    const nav = window.CNAV_STATE || {};
    const userId = Number(nav.userId) || 0;
    if (!userId) return;

    const lang = nav.lang === 'en' || document.documentElement.lang === 'en' ? 'en' : 'it';
    const T = {
        it: {
            settings: 'Avvisi',
            sound: 'Suono',
            soundHint: 'Un suono quando arriva un messaggio o una menzione.',
            popups: 'Riquadri in pagina',
            popupsHint: 'Un riquadro in basso quando succede qualcosa.',
            desktop: 'Notifiche sul dispositivo',
            desktopHint: 'Arrivano anche a sito chiuso, su questo dispositivo.',
            desktopBlocked: 'Le hai bloccate nelle impostazioni del browser per questo sito.',
            desktopUnsupported: 'Questo browser non le supporta.',
            desktopLimited: 'Questo browser le mostra solo mentre il sito è aperto.',
            install: 'Arrivano a nome del browser. Per vederle a nome di Cripsum™, installa il sito come app: dal menu del browser scegli «Installa» o «Aggiungi a schermata Home».',
            test: 'Manda una notifica di prova',
            testSent: 'Inviata: dovrebbe comparire fra un attimo.',
            testNone: 'Nessun dispositivo iscritto: spegni e riaccendi l\'interruttore qui sopra.',
            testFailed: 'Il servizio push non l\'ha accettata (codice {code}). Spegni e riaccendi l\'interruttore.',
            testError: 'Non è stato possibile mandarla. Riprova fra poco.',
            close: 'Chiudi',
            open: 'Apri'
        },
        en: {
            settings: 'Alerts',
            sound: 'Sound',
            soundHint: 'A sound when a message or a mention arrives.',
            popups: 'In-page pop-ups',
            popupsHint: 'A small card at the bottom when something happens.',
            desktop: 'Device notifications',
            desktopHint: 'They arrive even when the site is closed, on this device.',
            desktopBlocked: 'You blocked them in the browser settings for this site.',
            desktopUnsupported: 'This browser does not support them.',
            desktopLimited: 'This browser only shows them while the site is open.',
            install: 'They arrive under the browser\'s name. To see them as Cripsum™, install the site as an app: in the browser menu choose "Install" or "Add to Home screen".',
            test: 'Send a test notification',
            testSent: 'Sent: it should show up in a moment.',
            testNone: 'No device is subscribed: turn the switch above off and on again.',
            testFailed: 'The push service did not accept it (code {code}). Turn the switch off and on again.',
            testError: 'It could not be sent. Try again shortly.',
            close: 'Close',
            open: 'Open'
        }
    }[lang];

    // ── Preferenze (per dispositivo) ───────────────────────────────────────

    const PREF_KEY = 'cripsum.notify.' + userId;
    const prefs = Object.assign({ sound: true, popups: true, desktop: false }, readJson(PREF_KEY));

    function readJson(key) {
        try {
            return JSON.parse(localStorage.getItem(key) || 'null') || {};
        } catch (_) {
            return {};
        }
    }

    function writeJson(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (_) {
            /* navigazione privata: le preferenze valgono per questa pagina */
        }
    }

    // ── Stato ──────────────────────────────────────────────────────────────

    const listeners = { user: new Set(), global: new Set(), aux: new Set(), counters: new Set(), fallback: new Set() };
    const cursor = { u: -1, g: null, ga: -1 };
    const counters = {
        inbox: Number(nav.counts?.inbox) || 0,
        chat: Number(nav.counts?.chat) || 0,
        mentions: Number(nav.counts?.mentions) || 0,
        friends: Number(nav.counts?.friends) || 0,
        missions: Number(nav.counts?.missions) || 0,
        chats: 0,
        messages: 0,
        requests: 0,
        invites: 0
    };

    let mode = 'idle';            // 'idle' sulle pagine normali, 'chat' dove serve prontezza
    let timer = null;
    let inFlight = false;
    let failures = 0;
    let lastActivity = Date.now();
    let off = false;              // il server non ha i timbri: si ripiega su controlli lenti
    let activeChat = null;        // chat aperta e visibile: per quella niente avvisi
    let activeTicket = null;      // lo stesso per il ticket aperto nella posta
    let openChatHandler = null;
    let unseen = 0;
    const baseTitle = document.title;
    let statusTimer = null;

    const emit = (channel, payload) => {
        listeners[channel].forEach((fn) => {
            try {
                fn(payload);
            } catch (error) {
                console.error('[rt]', error);
            }
        });
    };

    // ── Giro di controllo ──────────────────────────────────────────────────

    function interval() {
        const hidden = document.visibilityState !== 'visible';
        let ms;
        if (mode === 'chat') {
            const quiet = Date.now() - lastActivity > 90000;
            ms = hidden ? 9000 : (quiet ? 4500 : 2000);
        } else {
            ms = hidden ? 60000 : 20000;
        }
        if (off) ms = Math.max(ms, mode === 'chat' ? 6000 : 60000);
        if (failures > 0) ms = Math.min(120000, ms * Math.pow(2, Math.min(failures, 5)));
        return ms * (0.9 + Math.random() * 0.2);
    }

    function schedule(delay) {
        clearTimeout(timer);
        timer = setTimeout(poll, delay === undefined ? interval() : delay);
    }

    async function poll() {
        if (inFlight) return schedule();
        inFlight = true;

        const params = new URLSearchParams({ u: String(cursor.u) });
        if (cursor.g !== null) {
            params.set('g', String(cursor.g));
            params.set('ga', String(cursor.ga));
        }

        try {
            const response = await fetch('/api/rt/poll.php?' + params.toString(), {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json', 'X-Cripsum-Lang': lang }
            });

            if (response.status === 401) {
                // Sessione finita: inutile continuare a chiedere.
                clearTimeout(timer);
                inFlight = false;
                return;
            }
            if (!response.ok) throw new Error('HTTP ' + response.status);

            const data = await response.json();
            failures = 0;

            if (data.off) {
                off = true;
                emit('fallback', {});
            } else {
                off = false;
                handle(data);
            }
        } catch (_) {
            failures += 1;
        } finally {
            inFlight = false;
            schedule();
        }
    }

    function handle(data) {
        // Achievement sbloccati poco prima che questa pagina si aprisse: li
        // mostra il popup di js/achievements-popup.js, che li riceve come
        // gli altri eventi.
        if (Array.isArray(data.ach)) {
            data.ach.forEach((id) => emit('user', { t: 'ach', a: id, late: true }));
        }

        if (data.u) {
            const first = cursor.u < 0;
            cursor.u = data.u.seq;
            if (!first) {
                if (data.u.resync) emit('user', { t: 'resync' });
                const events = data.u.events || [];
                if (events.length) lastActivity = Date.now();
                events.forEach((event) => emit('user', event));
                considerNotifications(events, data.u.resync);
            }
        }

        if (data.g && cursor.g !== null) {
            cursor.g = data.g.seq;
            if (data.g.resync) emit('global', { t: 'resync' });
            const events = data.g.events || [];
            if (events.length) lastActivity = Date.now();
            events.forEach((event) => emit('global', event));
        }

        if (data.ga && cursor.g !== null) {
            cursor.ga = data.ga.aux;
            emit('aux', data.ga);
        }

        if (data.stale && cursor.g !== null) refreshGlobalStatus();
    }

    /** Il conteggio degli online è vecchio: con un po' di scarto, per non partire tutti insieme. */
    function refreshGlobalStatus() {
        if (statusTimer) return;
        statusTimer = setTimeout(async () => {
            statusTimer = null;
            try {
                const response = await fetch('/api/chat/status.php', { credentials: 'same-origin', cache: 'no-store' });
                const data = await response.json();
                if (data && data.state) {
                    cursor.ga = data.state.aux;
                    emit('aux', data.state);
                }
            } catch (_) {
                /* riproverà al prossimo giro */
            }
        }, 300 + Math.random() * 2500);
    }

    // ── Contatori della navbar ─────────────────────────────────────────────

    function paintCounters() {
        // Il numero sulla campanella: tutto quello che il suo menu mostra
        // come da leggere, cioè posta, richieste di amicizia, messaggi nelle
        // chat e menzioni nella chat globale. Si scrive sulla copia desktop;
        // navbar.js la ricopia su quella mobile.
        const bell = counters.inbox + counters.friends + counters.chat + counters.mentions;
        const inbox = document.getElementById('inbox-unread-count');
        if (inbox) {
            inbox.textContent = bell > 99 ? '99+' : String(bell);
            inbox.classList.toggle('d-none', bell <= 0);
        }

        const setBadge = (key, value) => {
            document.querySelectorAll('[data-cnav-badge="' + key + '"]').forEach((el) => {
                el.textContent = value > 99 ? '99+' : String(value);
                el.hidden = value <= 0;
            });
        };
        setBadge('chat', counters.chat);
        setBadge('global', counters.mentions);
        setBadge('friends', counters.friends);

        const achvDot = document.querySelector('[data-cnav-new-key="achv"] .cnav-dot');
        nav.hasNews = counters.chat > 0 || counters.mentions > 0 || counters.friends > 0 || counters.missions > 0;
        const lit = nav.hasNews || !!(achvDot && !achvDot.hidden);
        document.querySelectorAll('.cnav-trigger--account, .cnav-avatar-btn').forEach((el) => {
            el.classList.toggle('has-news', lit);
        });

        emit('counters', Object.assign({}, counters));
    }

    function applyCounters(fresh) {
        if (!fresh) return;
        counters.inbox = Number(fresh.inbox) || 0;
        counters.friends = Number(fresh.friends) || 0;
        counters.chats = Number(fresh.chats) || 0;
        // Il badge delle chat conta i messaggi, non le conversazioni.
        counters.messages = Number(fresh.messages ?? fresh.chats) || 0;
        counters.requests = Number(fresh.requests) || 0;
        counters.invites = Number(fresh.invites) || 0;
        counters.chat = counters.messages + counters.requests + counters.invites;
        counters.mentions = Number(fresh.mentions) || 0;
        paintCounters();
    }

    let feedTimer = null;
    let feedSince = null;
    let feedBusy = false;
    let lastFeed = 0;

    /** Chiede contatori (e avvisi, se `since` è un numero) al server, mai più di una volta ogni due secondi. */
    function requestFeed(since) {
        if (typeof since === 'number') {
            feedSince = feedSince === null ? since : Math.min(feedSince, since);
        }
        if (feedTimer) return;
        const wait = Math.max(350, 2000 - (Date.now() - lastFeed));
        feedTimer = setTimeout(runFeed, wait);
    }

    async function runFeed() {
        feedTimer = null;
        if (feedBusy) return requestFeed();
        feedBusy = true;
        const since = feedSince;
        feedSince = null;
        lastFeed = Date.now();

        try {
            const url = '/api/notify/feed.php' + (since !== null ? '?since=' + encodeURIComponent(since) : '');
            const response = await fetch(url, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json', 'X-Cripsum-Lang': lang }
            });
            const data = await response.json();
            if (data && data.ok) {
                applyCounters(data.counters);
                (data.items || []).forEach(announce);
            }
        } catch (_) {
            /* i numeri si riallineano al prossimo giro */
        } finally {
            feedBusy = false;
        }
    }

    // ── Avvisi ─────────────────────────────────────────────────────────────

    const NOTIFY_TYPES = { pm: 1, gm: 1, mn: 1, fr: 1, fa: 1, gi: 1, ib: 1, tk: 1 };
    const COUNTER_TYPES = { rd: 1, ls: 1, sl: 1, pu: 1, gu: 1, mr: 1 };

    function considerNotifications(events, resync) {
        let since = null;
        let touchCounters = !!resync;

        events.forEach((event) => {
            if (NOTIFY_TYPES[event.t]) {
                if (Number(event.f) === userId) {
                    touchCounters = true;
                    return;
                }
                since = since === null ? event.s - 1 : Math.min(since, event.s - 1);
            } else if (COUNTER_TYPES[event.t]) {
                touchCounters = true;
            }
        });

        if (since !== null) requestFeed(since);
        else if (touchCounters) requestFeed();
    }

    const TOAST_SEEN = 'cripsum.rt.seen.' + userId;

    /** Vero la prima volta che un avviso passa da questo browser (le altre schede lo saltano). */
    function claim(seq) {
        let seen = 0;
        try {
            seen = Number(localStorage.getItem(TOAST_SEEN)) || 0;
        } catch (_) {
            return true;
        }
        if (seq <= seen) return false;
        try {
            localStorage.setItem(TOAST_SEEN, String(seq));
        } catch (_) {
            /* senza memoria condivisa ogni scheda avvisa per conto suo */
        }
        return true;
    }

    function isActiveChat(item) {
        if (document.visibilityState !== 'visible' || !document.hasFocus()) return false;
        if (item.ticket) return activeTicket === item.ticket;
        // Una menzione mentre si guarda la chat globale si vede già lì.
        if (item.global) return !!activeChat && activeChat.kind === 'global';
        return !!(item.chat && activeChat
            && activeChat.kind === item.chat.kind && Number(activeChat.id) === Number(item.chat.id));
    }

    function announce(item) {
        if (!item || item.silent || isActiveChat(item)) return;

        const visible = document.visibilityState === 'visible';
        // Una scheda nascosta lascia il riquadro e il suono a quella visibile,
        // se c'è; la notifica del browser ha un'etichetta e non si duplica.
        const mine = claim(item.seq);

        if (!visible) {
            unseen += 1;
            document.title = '(' + unseen + ') ' + baseTitle;
            // Con il push la notifica l'ha già mostrata /sw.js, e in tempo.
            if (!pushActive && prefs.desktop && 'Notification' in window && Notification.permission === 'granted') {
                showLocalNotification(item);
            }
        }

        if (!mine) return;
        if (visible && prefs.popups) toast(item);
        // Finestra non in primo piano e push attivo: il suono è quello della
        // notifica di sistema, non serve farlo due volte.
        if (prefs.sound && !(pushActive && !document.hasFocus())) playSound();
    }

    /** Ripiego senza push: la notifica la crea la pagina, quando il suo giro di controllo trova qualcosa. */
    function showLocalNotification(item) {
        const options = {
            body: item.text || '',
            icon: item.avatar || '/img/app-192.png',
            badge: '/img/app-badge.png',
            tag: 'cripsum-' + item.key,
            data: { url: item.url || '', item }
        };
        // Su Android una pagina non può crearla da sé: deve passare dal
        // service worker, che poi gestisce anche il clic.
        if (worker) {
            worker.showNotification(item.title, options).catch(() => {});
            return;
        }
        try {
            const note = new Notification(item.title, options);
            note.onclick = () => {
                window.focus();
                go(item);
                note.close();
            };
        } catch (_) {
            /* serve il service worker, e qui non c'è */
        }
    }

    // ── Notifiche push ─────────────────────────────────────────────────────

    const PUSH_MARK = 'cripsum.push';     // a chi è iscritto questo browser, per non ripeterlo a ogni pagina
    const pushSupported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    let worker = null;                    // registrazione di /sw.js
    let pushActive = false;               // questo browser è iscritto per l'utente collegato

    function keyBytes(base64url) {
        const padded = (base64url + '==='.slice((base64url.length + 3) % 4)).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(padded);
        const bytes = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i += 1) bytes[i] = raw.charCodeAt(i);
        return bytes;
    }

    function sameKey(buffer, base64url) {
        if (!buffer) return false;
        const a = new Uint8Array(buffer);
        const b = keyBytes(base64url);
        return a.length === b.length && a.every((value, i) => value === b[i]);
    }

    async function pushRequest(payload) {
        const response = await fetch('/api/notify/push.php', {
            method: payload ? 'POST' : 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: payload
                ? { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || document.body.dataset.csrf || '' }
                : { 'Accept': 'application/json' },
            body: payload ? JSON.stringify(payload) : undefined
        });
        const data = await response.json();
        if (!response.ok || !data || !data.ok) throw new Error('push');
        return data;
    }

    async function ensureWorker() {
        if (!worker) worker = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        return worker;
    }

    /**
     * Iscrive questo browser, se non lo è già. Al server lo dice solo quando
     * cambia qualcosa, o una volta al giorno: così, se il server ha perso
     * l'elenco, entro un giorno si rimette a posto da solo.
     */
    async function enablePush() {
        if (!pushSupported || Notification.permission !== 'granted') return false;
        const registration = await ensureWorker();
        await navigator.serviceWorker.ready;

        const mark = readJson(PUSH_MARK);
        let subscription = await registration.pushManager.getSubscription();
        const fresh = subscription && mark.u === userId && mark.e === subscription.endpoint
            && mark.k && sameKey(subscription.options.applicationServerKey, mark.k)
            && Date.now() - (Number(mark.at) || 0) < 86400000;
        if (fresh) {
            pushActive = true;
            return true;
        }

        const info = await pushRequest();
        if (!info.key) return false;

        // Iscrizione fatta con una chiave che non è più quella del sito: non
        // riceverebbe nulla, va rifatta.
        if (subscription && !sameKey(subscription.options.applicationServerKey, info.key)) {
            await subscription.unsubscribe();
            subscription = null;
        }
        if (!subscription) {
            subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(info.key) });
        }

        await pushRequest({
            action: 'subscribe',
            endpoint: subscription.endpoint,
            keys: subscription.toJSON().keys,
            replace_user: mark.u && Number(mark.u) !== userId ? Number(mark.u) : 0
        });
        writeJson(PUSH_MARK, { u: userId, e: subscription.endpoint, k: info.key, at: Date.now() });
        pushActive = true;
        return true;
    }

    /** Toglie l'iscrizione di questo browser: per chi spegne l'interruttore, o per un account che qui non c'è più. */
    async function disablePush(ownerId) {
        pushActive = false;
        try {
            const registration = await navigator.serviceWorker.getRegistration('/');
            const subscription = registration ? await registration.pushManager.getSubscription() : null;
            if (subscription) {
                if (ownerId === userId) await pushRequest({ action: 'unsubscribe', endpoint: subscription.endpoint });
                else {
                    await fetch('/api/notify/push.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'drop', endpoint: subscription.endpoint, user: ownerId })
                    });
                }
                await subscription.unsubscribe();
            }
        } catch (_) {
            /* il server la toglierà da sé al primo segnale respinto */
        }
        try {
            localStorage.removeItem(PUSH_MARK);
        } catch (_) {
            /* niente memoria, niente da togliere */
        }
    }

    /** Butta via l'iscrizione di questo browser senza toccare la preferenza: la prossima enablePush() ne fa una nuova. */
    async function resetPush() {
        pushActive = false;
        try {
            localStorage.removeItem(PUSH_MARK);
            const registration = await navigator.serviceWorker.getRegistration('/');
            const subscription = registration ? await registration.pushManager.getSubscription() : null;
            if (subscription) await subscription.unsubscribe();
        } catch (_) {
            /* si riparte comunque da un'iscrizione nuova */
        }
    }

    function onWorkerMessage(event) {
        const data = event.data || {};
        if (data.type === 'cripsum-push') {
            // È arrivato un segnale: inutile aspettare il prossimo giro.
            lastActivity = Date.now();
            schedule(0);
        } else if (data.type === 'cripsum-open') {
            const url = String(data.url || '');
            const item = data.item && typeof data.item === 'object' ? data.item : {};
            go(Object.assign({}, item, { url: url.startsWith('/') && !url.startsWith('//') ? url : '' }));
        }
    }

    function initPush() {
        if (!pushSupported) return;
        navigator.serviceWorker.addEventListener('message', onWorkerMessage);

        if (prefs.desktop && Notification.permission === 'granted') {
            enablePush().catch(() => {
                /* resta il ripiego: le notifiche create dalla pagina */
            });
            return;
        }
        // Un'iscrizione rimasta da prima (interruttore spento, permesso tolto,
        // o un altro account su questo browser) non deve continuare a ricevere.
        const mark = readJson(PUSH_MARK);
        if (mark.u) disablePush(Number(mark.u));
    }

    let audio = null;
    function playSound() {
        try {
            audio = audio || new Audio('/audio/notification.mp3');
            audio.volume = 0.5;
            audio.currentTime = 0;
            const result = audio.play();
            if (result && result.catch) result.catch(() => {});
        } catch (_) {
            /* il browser non lascia suonare prima di un'interazione */
        }
    }

    /**
     * Porta dove indica un avviso. Sulle pagine di chat la conversazione si
     * apre sul posto (e torna true); altrove si segue il link, a meno che
     * chi chiama non voglia pensarci da sé (`onlyInPlace`).
     */
    function go(item, onlyInPlace) {
        if (item.chat && openChatHandler) {
            openChatHandler(item.chat);
            return true;
        }
        if (!onlyInPlace && item.url) window.location.href = item.url;
        return false;
    }

    // ── Riquadri in pagina ─────────────────────────────────────────────────

    let stack = null;

    function ensureStack() {
        if (stack && document.body.contains(stack)) return stack;
        stack = document.createElement('div');
        stack.className = 'crt-stack';
        stack.setAttribute('role', 'status');
        stack.setAttribute('aria-live', 'polite');
        // Scritta anche in linea: il foglio del profilo rimette nel flusso i
        // figli diretti di body che non dichiarano qui «position: fixed».
        stack.style.position = 'fixed';
        document.body.appendChild(stack);
        return stack;
    }

    function toast(item) {
        const host = ensureStack();

        // Un solo riquadro per chat: quello nuovo sostituisce il vecchio.
        if (item.key) {
            host.querySelectorAll('[data-key="' + String(item.key).replace(/"/g, '') + '"]').forEach((old) => old.remove());
        }
        while (host.children.length >= 3) host.firstElementChild.remove();

        const card = document.createElement('div');
        card.className = 'crt-toast crt-toast--' + (item.type || 'info');
        card.dataset.key = item.key || '';
        card.tabIndex = 0;

        const media = document.createElement('div');
        media.className = 'crt-toast__media';
        if (item.avatar) {
            const img = document.createElement('img');
            img.src = item.avatar;
            img.alt = '';
            img.loading = 'lazy';
            media.appendChild(img);
        } else {
            const glyph = document.createElement('i');
            glyph.className = 'fa-solid ' + (/^fa-[a-z0-9-]+$/.test(item.icon || '') ? item.icon : 'fa-envelope');
            glyph.setAttribute('aria-hidden', 'true');
            media.appendChild(glyph);
        }

        const body = document.createElement('div');
        body.className = 'crt-toast__body';
        const title = document.createElement('strong');
        title.textContent = item.title || '';
        const text = document.createElement('span');
        text.textContent = item.text || '';
        body.append(title, text);

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'crt-toast__close';
        close.setAttribute('aria-label', T.close);
        close.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';

        card.append(media, body, close);
        host.appendChild(card);

        let hideTimer = null;
        const dismiss = () => {
            clearTimeout(hideTimer);
            card.classList.add('is-out');
            setTimeout(() => card.remove(), 220);
        };
        const arm = () => {
            clearTimeout(hideTimer);
            hideTimer = setTimeout(dismiss, item.duration || 6500);
        };

        close.addEventListener('click', (event) => {
            event.stopPropagation();
            dismiss();
        });
        card.addEventListener('click', () => {
            dismiss();
            go(item);
        });
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                dismiss();
                go(item);
            }
            if (event.key === 'Escape') dismiss();
        });
        card.addEventListener('mouseenter', () => clearTimeout(hideTimer));
        card.addEventListener('mouseleave', arm);
        arm();

        return { dismiss };
    }

    // ── Pannello delle preferenze ──────────────────────────────────────────

    function openSettings() {
        document.querySelector('.crt-settings')?.remove();

        const overlay = document.createElement('div');
        overlay.className = 'crt-settings';
        overlay.innerHTML =
            '<div class="crt-settings__card" role="dialog" aria-modal="true" aria-label="' + T.settings + '">' +
                '<header><h3><i class="fa-solid fa-bell" aria-hidden="true"></i> ' + T.settings + '</h3>' +
                '<button type="button" class="crt-settings__close" aria-label="' + T.close + '"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header>' +
                row('popups', T.popups, T.popupsHint) +
                row('sound', T.sound, T.soundHint) +
                row('desktop', T.desktop, T.desktopHint) +
                '<div class="crt-settings__extra" hidden>' +
                    '<p class="crt-settings__note"></p>' +
                    '<button type="button" class="crt-settings__test"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> ' + T.test + '</button>' +
                    '<p class="crt-settings__result" role="status" hidden></p>' +
                '</div>' +
            '</div>';

        function row(key, label, hint) {
            return '<label class="crt-settings__row"><span><strong>' + label + '</strong><small data-hint="' + key + '">' + hint + '</small></span>' +
                '<input type="checkbox" data-pref="' + key + '"' + (prefs[key] ? ' checked' : '') + '><i class="crt-switch" aria-hidden="true"></i></label>';
        }

        const close = () => overlay.remove();
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay || event.target.closest('.crt-settings__close')) close();
        });
        overlay.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') close();
        });

        const desktopBox = overlay.querySelector('[data-pref="desktop"]');
        const desktopHint = overlay.querySelector('[data-hint="desktop"]');
        if (!('Notification' in window)) {
            desktopBox.disabled = true;
            desktopBox.checked = false;
            desktopHint.textContent = T.desktopUnsupported;
        } else if (Notification.permission === 'denied') {
            desktopBox.disabled = true;
            desktopBox.checked = false;
            desktopHint.textContent = T.desktopBlocked;
        } else if (Notification.permission !== 'granted') {
            desktopBox.checked = false;
        }

        // Sotto l'interruttore, quando è acceso: come farle arrivare a nome
        // del sito, e una prova per vedere se questo dispositivo le riceve.
        const extra = overlay.querySelector('.crt-settings__extra');
        const note = overlay.querySelector('.crt-settings__note');
        const testButton = overlay.querySelector('.crt-settings__test');
        const testResult = overlay.querySelector('.crt-settings__result');
        const installed = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        const paintExtra = () => {
            const on = desktopBox.checked && !desktopBox.disabled;
            extra.hidden = !on;
            if (!on) return;
            note.textContent = pushSupported ? T.install : T.desktopLimited;
            note.hidden = pushSupported && installed;
            testButton.hidden = !pushSupported;
        };
        paintExtra();

        testButton.addEventListener('click', async () => {
            testButton.disabled = true;
            testResult.hidden = false;
            testResult.textContent = '…';
            try {
                const attempt = async () => {
                    await enablePush();
                    const answer = await pushRequest({ action: 'test' });
                    answer.failed = (answer.results || []).find((row) => row.code < 200 || row.code >= 300);
                    return answer;
                };
                let data = await attempt();
                // Iscrizione persa o scaduta: si rifà da capo e si riprova una volta.
                if (!data.devices || data.failed) {
                    await resetPush();
                    data = await attempt();
                }
                const failed = data.failed;
                if (!data.devices) testResult.textContent = T.testNone;
                else if (failed) testResult.textContent = T.testFailed.replace('{code}', String(failed.code || 0));
                else testResult.textContent = T.testSent;
            } catch (_) {
                testResult.textContent = T.testError;
            } finally {
                testButton.disabled = false;
            }
        });

        overlay.addEventListener('change', async (event) => {
            const box = event.target.closest('[data-pref]');
            if (!box) return;
            const key = box.dataset.pref;

            if (key === 'desktop' && box.checked && Notification.permission !== 'granted') {
                // Il permesso si chiede solo qui, dopo un clic esplicito.
                const answer = await Notification.requestPermission();
                if (answer !== 'granted') {
                    box.checked = false;
                    if (answer === 'denied') {
                        box.disabled = true;
                        desktopHint.textContent = T.desktopBlocked;
                    }
                }
            }

            prefs[key] = box.checked;
            writeJson(PREF_KEY, prefs);
            if (key === 'sound' && box.checked) playSound();
            if (key === 'desktop') {
                paintExtra();
                testResult.hidden = true;
                if (box.checked) enablePush().catch(() => {});
                else disablePush(userId);
            }
        });

        overlay.style.position = 'fixed';
        document.body.appendChild(overlay);
        overlay.querySelector('.crt-settings__close').focus();
    }

    // ── Vita della pagina ──────────────────────────────────────────────────

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            unseen = 0;
            document.title = baseTitle;
            lastActivity = Date.now();
            schedule(150);
        }
    });
    window.addEventListener('focus', () => {
        unseen = 0;
        document.title = baseTitle;
    });
    ['pointerdown', 'keydown'].forEach((name) => {
        window.addEventListener(name, () => {
            lastActivity = Date.now();
        }, { passive: true, capture: true });
    });
    window.addEventListener('online', () => {
        failures = 0;
        schedule(200);
    });

    // I numeri si riallineano da soli ogni cinque minuti, a scheda visibile.
    setInterval(() => {
        if (document.visibilityState === 'visible') requestFeed();
    }, 300000);

    window.CripsumRT = {
        userId,
        lang,
        prefs,
        counters,
        onUser: (fn) => (listeners.user.add(fn), () => listeners.user.delete(fn)),
        onGlobal: (fn) => (listeners.global.add(fn), () => listeners.global.delete(fn)),
        onAux: (fn) => (listeners.aux.add(fn), () => listeners.aux.delete(fn)),
        onCounters: (fn) => (listeners.counters.add(fn), () => listeners.counters.delete(fn)),
        onFallback: (fn) => (listeners.fallback.add(fn), () => listeners.fallback.delete(fn)),
        /** La pagina della chat globale dichiara da dove riprendere. */
        watchGlobal(state) {
            cursor.g = Number(state?.seq) || 0;
            cursor.ga = Number(state?.aux) || 0;
            schedule(100);
        },
        /** 'chat' dove serve prontezza, 'idle' altrove. */
        setMode(next) {
            mode = next === 'chat' ? 'chat' : 'idle';
            schedule(200);
        },
        /** Chat aperta in questo momento: per quella non servono avvisi. */
        setActiveChat(chat) {
            activeChat = chat || null;
        },
        /** Ticket aperto nella posta: le risposte si vedono già, niente avviso. */
        setActiveTicket(code) {
            activeTicket = code || null;
        },
        /** Le pagine di chat aprono la conversazione sul posto invece di ricaricare. */
        setOpenChatHandler(fn) {
            openChatHandler = typeof fn === 'function' ? fn : null;
        },
        pollNow: () => schedule(0),
        touch: () => {
            lastActivity = Date.now();
        },
        refreshCounters: () => requestFeed(),
        applyCounters,
        open: go,
        toast,
        playSound,
        openSettings,
        isOff: () => off
    };

    paintCounters();
    schedule(document.visibilityState === 'visible' ? 600 : 3000);
    initPush();
})();
