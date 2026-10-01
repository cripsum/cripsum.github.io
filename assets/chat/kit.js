/**
 * Cripsum™ — kit condiviso dalle pagine di chat e dalla pagina Amici.
 *
 * Qui stanno i pezzi che prima ogni pagina si riscriveva a modo suo (e con
 * difetti diversi): costruzione sicura dell'HTML, chiamate al server,
 * date, testo dei messaggi, avvisi, dialoghi, menu, visualizzatore.
 *
 * Regola sull'HTML: niente stringhe incollate dentro innerHTML con dati che
 * arrivano dal server. Gli elementi si costruiscono con h() e il testo entra
 * sempre come nodo di testo, quindi quello che scrive un utente non può mai
 * diventare markup.
 *
 * I componenti specifici delle chat (lista messaggi, riga di scrittura,
 * emoji, GIF) stanno in kit-chat.js.
 */
window.ChatKit = (() => {
    'use strict';

    const lang = (window.CNAV_STATE && window.CNAV_STATE.lang === 'en') || document.documentElement.lang === 'en' ? 'en' : 'it';
    const locale = lang === 'en' ? 'en-GB' : 'it-IT';

    const dict = {
        err_generic: { it: 'Qualcosa è andato storto. Riprova.', en: 'Something went wrong. Try again.' },
        err_network: { it: 'Connessione assente. Riprova.', en: 'No connection. Try again.' },
        err_session: { it: 'Sessione scaduta. Ricarica la pagina.', en: 'Session expired. Reload the page.' },
        ok: { it: 'OK', en: 'OK' },
        cancel: { it: 'Annulla', en: 'Cancel' },
        confirm: { it: 'Conferma', en: 'Confirm' },
        close: { it: 'Chiudi', en: 'Close' },
        save: { it: 'Salva', en: 'Save' },
        today: { it: 'Oggi', en: 'Today' },
        yesterday: { it: 'Ieri', en: 'Yesterday' },
        now: { it: 'adesso', en: 'just now' },
        min_ago: { it: '{n} min fa', en: '{n} min ago' },
        hours_ago: { it: '{n} h fa', en: '{n} h ago' },
        days_ago: { it: '{n} g fa', en: '{n} d ago' },
        online: { it: 'Online', en: 'Online' },
        offline: { it: 'Offline', en: 'Offline' },
        last_seen: { it: 'Attivo {when}', en: 'Active {when}' },
        download: { it: 'Scarica', en: 'Download' },
        previous: { it: 'Precedente', en: 'Previous' },
        next: { it: 'Successivo', en: 'Next' },
        copied: { it: 'Copiato.', en: 'Copied.' },
        copy_failed: { it: 'Copia non riuscita.', en: 'Could not copy.' }
    };

    function extend(more) {
        Object.assign(dict, more);
    }

    function t(key, vars) {
        const entry = dict[key];
        let text = entry ? (entry[lang] ?? entry.it ?? key) : key;
        if (vars) {
            Object.keys(vars).forEach((name) => {
                text = text.split('{' + name + '}').join(String(vars[name]));
            });
        }
        return text;
    }

    // ── DOM ────────────────────────────────────────────────────────────────

    /**
     * Crea un elemento. `props`: class, dataset, style (oggetto), attributi
     * e gestori (onClick...). I figli stringa diventano nodi di testo.
     */
    function h(tag, props, ...children) {
        const el = document.createElement(tag);
        if (props) {
            Object.keys(props).forEach((key) => {
                const value = props[key];
                if (value === null || value === undefined || value === false) return;
                if (key === 'class') el.className = value;
                else if (key === 'dataset') Object.assign(el.dataset, value);
                else if (key === 'style' && typeof value === 'object') Object.assign(el.style, value);
                else if (key === 'text') el.textContent = value;
                else if (key.startsWith('on') && typeof value === 'function') el.addEventListener(key.slice(2).toLowerCase(), value);
                else if (value === true) el.setAttribute(key, '');
                else el.setAttribute(key, String(value));
            });
        }
        append(el, children);
        return el;
    }

    function append(parent, children) {
        children.forEach((child) => {
            if (child === null || child === undefined || child === false) return;
            if (Array.isArray(child)) append(parent, child);
            else if (child instanceof Node) parent.appendChild(child);
            else parent.appendChild(document.createTextNode(String(child)));
        });
    }

    /** Icona Font Awesome, sempre decorativa. */
    function icon(name, extra) {
        return h('i', { class: name + (extra ? ' ' + extra : ''), 'aria-hidden': 'true' });
    }

    function clear(el) {
        while (el.firstChild) el.removeChild(el.firstChild);
        return el;
    }

    /**
     * Aggancia alla pagina uno strato che le sta sopra (finestra, menu,
     * avviso). La posizione va scritta anche nello stile in linea: il foglio
     * del profilo rimette nel flusso ogni figlio diretto di body che non
     * dichiara lì «position: fixed», e lo strato finirebbe in fondo alla pagina.
     */
    function mount(el) {
        el.style.position = 'fixed';
        document.body.appendChild(el);
        return el;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    /** Solo percorsi del sito o indirizzi http(s): niente javascript: e simili. */
    function safeUrl(url, fallback = '#') {
        const value = String(url || '');
        if (/^https?:\/\//i.test(value)) return value;
        if (value.startsWith('/') && !value.startsWith('//')) return value;
        return fallback;
    }

    function avatarUrl(userId) {
        return '/includes/get_pfp.php?id=' + encodeURIComponent(userId);
    }

    function debounce(fn, wait) {
        let timer = null;
        const wrapped = (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), wait);
        };
        wrapped.cancel = () => clearTimeout(timer);
        return wrapped;
    }

    const isMobile = () => window.matchMedia('(max-width: 820px)').matches;
    const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ── Server ─────────────────────────────────────────────────────────────

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content || document.body.dataset.csrf || '';
    }

    function failure(data, status) {
        const message = (data && (data.error?.message || (typeof data.error === 'string' ? data.error : ''))) || t(status === 419 ? 'err_session' : 'err_generic');
        const error = new Error(message);
        error.status = status;
        error.data = data;
        error.code = data?.error?.code || null;
        return error;
    }

    /**
     * Chiamata JSON al server. `body` (oggetto) la rende una POST con il
     * token; gli errori arrivano come eccezioni con il messaggio già pronto
     * da mostrare.
     */
    async function api(url, options = {}) {
        const headers = { 'Accept': 'application/json', 'X-Cripsum-Lang': lang, 'X-Requested-With': 'XMLHttpRequest' };
        let body;
        if (options.form) {
            body = options.form;
            headers['X-CSRF-Token'] = csrf();
        } else if (options.body !== undefined) {
            body = JSON.stringify(options.body);
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-Token'] = csrf();
        }

        let response;
        try {
            response = await fetch(url, {
                method: options.method || (body ? 'POST' : 'GET'),
                credentials: 'same-origin',
                cache: 'no-store',
                headers,
                body,
                signal: options.signal
            });
        } catch (error) {
            if (error.name === 'AbortError') throw error;
            const offline = new Error(t('err_network'));
            offline.status = 0;
            throw offline;
        }

        let data = null;
        try {
            data = await response.json();
        } catch (_) {
            data = null;
        }

        if (!response.ok || !data || data.ok === false || data.success === false) {
            throw failure(data, response.status);
        }
        return data;
    }

    /** Caricamento di file con avanzamento (fetch non lo sa dire). */
    function upload(url, form, onProgress) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url);
            xhr.withCredentials = true;
            xhr.setRequestHeader('X-CSRF-Token', csrf());
            xhr.setRequestHeader('X-Cripsum-Lang', lang);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable && onProgress) onProgress(event.loaded / event.total);
            };
            xhr.onerror = () => {
                const error = new Error(t('err_network'));
                error.status = 0;
                reject(error);
            };
            xhr.onload = () => {
                let data = null;
                try {
                    data = JSON.parse(xhr.responseText);
                } catch (_) {
                    data = null;
                }
                if (xhr.status >= 200 && xhr.status < 300 && data && data.ok !== false) resolve(data);
                else reject(failure(data, xhr.status));
            };
            xhr.send(form);
        });
    }

    // ── Date ───────────────────────────────────────────────────────────────

    const toDate = (ts) => new Date(Number(ts) * 1000);

    function formatTime(ts) {
        if (!ts) return '';
        return toDate(ts).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
    }

    function sameDay(a, b) {
        return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    }

    function dayLabel(ts) {
        if (!ts) return '';
        const date = toDate(ts);
        const today = new Date();
        const yesterday = new Date();
        yesterday.setDate(today.getDate() - 1);
        if (sameDay(date, today)) return t('today');
        if (sameDay(date, yesterday)) return t('yesterday');
        const options = { day: 'numeric', month: 'long' };
        if (date.getFullYear() !== today.getFullYear()) options.year = 'numeric';
        return date.toLocaleDateString(locale, options);
    }

    function dayKey(ts) {
        const date = toDate(ts);
        return date.getFullYear() + '-' + date.getMonth() + '-' + date.getDate();
    }

    /** Ora per le liste: l'ora se è oggi, «Ieri», poi la data corta. */
    function listTime(ts) {
        if (!ts) return '';
        const date = toDate(ts);
        const today = new Date();
        const yesterday = new Date();
        yesterday.setDate(today.getDate() - 1);
        if (sameDay(date, today)) return formatTime(ts);
        if (sameDay(date, yesterday)) return t('yesterday');
        return date.toLocaleDateString(locale, { day: '2-digit', month: '2-digit' });
    }

    function relativeTime(ts) {
        if (!ts) return '';
        const seconds = Math.max(0, Math.floor(Date.now() / 1000) - Number(ts));
        if (seconds < 60) return t('now');
        if (seconds < 3600) return t('min_ago', { n: Math.floor(seconds / 60) });
        if (seconds < 86400) return t('hours_ago', { n: Math.floor(seconds / 3600) });
        if (seconds < 7 * 86400) return t('days_ago', { n: Math.floor(seconds / 86400) });
        return toDate(ts).toLocaleDateString(locale, { day: 'numeric', month: 'short' });
    }

    function presenceLabel(online, lastSeenTs) {
        if (online) return t('online');
        if (lastSeenTs) return t('last_seen', { when: relativeTime(lastSeenTs) });
        return t('offline');
    }

    function fileSize(bytes) {
        const value = Number(bytes) || 0;
        if (value < 1024) return value + ' B';
        if (value < 1048576) return (value / 1024).toFixed(value < 10240 ? 1 : 0) + ' KB';
        return (value / 1048576).toFixed(1) + ' MB';
    }

    // ── Testo dei messaggi ─────────────────────────────────────────────────

    let customEmojis = new Map();

    function setCustomEmojis(list) {
        customEmojis = new Map();
        (Array.isArray(list) ? list : []).forEach((emoji) => {
            if (emoji && /^[A-Za-z0-9_\-]{1,40}$/.test(String(emoji.code))) {
                customEmojis.set(String(emoji.code), { code: String(emoji.code), url: safeUrl(emoji.url, '') });
            }
        });
    }

    function emojiNode(code, className) {
        const emoji = customEmojis.get(code);
        if (!emoji || !emoji.url) return null;
        return h('img', { class: className || 'ck-emoji', src: emoji.url, alt: ':' + code + ':', title: ':' + code + ':', loading: 'lazy', draggable: 'false' });
    }

    // Niente lookbehind: i Safari meno recenti non lo leggono e l'intero file
    // smetterebbe di funzionare. Il carattere prima della @ si cattura a parte.
    const TOKEN = /(https?:\/\/[^\s<]+)|(:[A-Za-z0-9_\-]{1,40}:)|(^|[^A-Za-z0-9_@])(@[A-Za-z0-9_]{3,20})(?![A-Za-z0-9_])/g;

    let emojiOnlyPattern = null;
    try {
        emojiOnlyPattern = new RegExp('^(?:\\p{Extended_Pictographic}|\\p{Emoji_Component}|\\u200d|\\ufe0f|\\s)+$', 'u');
    } catch (_) {
        emojiOnlyPattern = null;
    }

    /**
     * Testo di un messaggio → nodi: link cliccabili, emoji del sito,
     * menzioni evidenziate. Tutto il resto resta testo puro.
     */
    function renderText(text, options = {}) {
        const fragment = document.createDocumentFragment();
        const source = String(text ?? '');
        let last = 0;
        let match;
        TOKEN.lastIndex = 0;

        while ((match = TOKEN.exec(source)) !== null) {
            if (match.index > last) fragment.appendChild(document.createTextNode(source.slice(last, match.index)));
            const [whole, url, emoji, before, mention] = match;

            if (mention && before) fragment.appendChild(document.createTextNode(before));

            if (url) {
                const clean = url.replace(/[.,!?;:)\]]+$/g, '');
                const tail = url.slice(clean.length);
                fragment.appendChild(h('a', { href: clean, target: '_blank', rel: 'noopener noreferrer nofollow', class: 'ck-link' }, clean));
                if (tail) fragment.appendChild(document.createTextNode(tail));
            } else if (emoji) {
                const node = emojiNode(emoji.slice(1, -1));
                fragment.appendChild(node || document.createTextNode(whole));
            } else if (mention && options.mentions) {
                const name = mention.slice(1);
                const mine = options.me && name.toLowerCase() === String(options.me).toLowerCase();
                fragment.appendChild(h('a', {
                    class: 'ck-mention' + (mine ? ' is-me' : ''),
                    href: '/u/' + encodeURIComponent(name),
                    dataset: { username: name }
                }, mention));
            } else if (mention) {
                fragment.appendChild(document.createTextNode(mention));
            } else {
                fragment.appendChild(document.createTextNode(whole));
            }
            last = match.index + whole.length;
        }
        if (last < source.length) fragment.appendChild(document.createTextNode(source.slice(last)));
        return fragment;
    }

    /** Vero se il testo è fatto solo di emoji (al massimo tre): si mostrano grandi. */
    function isEmojiOnly(text) {
        const value = String(text || '').trim();
        if (!value || value.length > 40) return false;
        if (/^(?::[A-Za-z0-9_\-]{1,40}:\s*){1,3}$/.test(value)) {
            return value.match(/:[A-Za-z0-9_\-]{1,40}:/g).every((code) => customEmojis.has(code.slice(1, -1)));
        }
        try {
            if (!emojiOnlyPattern || !emojiOnlyPattern.test(value) || /^[\d#*\s]+$/.test(value)) return false;
            if (window.Intl && Intl.Segmenter) {
                const count = [...new Intl.Segmenter(undefined, { granularity: 'grapheme' }).segment(value.replace(/\s+/g, ''))].length;
                return count >= 1 && count <= 3;
            }
            return Array.from(value.replace(/\s+/g, '')).length <= 6;
        } catch (_) {
            return false;
        }
    }

    // ── Avviso breve ───────────────────────────────────────────────────────

    let toastEl = null;
    let toastTimer = null;

    function toast(message, kind = 'info') {
        if (!toastEl || !document.body.contains(toastEl)) {
            toastEl = h('div', { class: 'ck-toast', role: 'status', 'aria-live': 'polite' });
            mount(toastEl);
        }
        toastEl.textContent = message;
        toastEl.dataset.kind = kind;
        toastEl.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toastEl.classList.remove('is-visible'), kind === 'error' ? 4200 : 2600);
    }

    /** Avviso con un'azione («Annulla») valida per pochi secondi. */
    function toastAction(message, actionLabel, seconds = 5) {
        return new Promise((resolve) => {
            const bar = h('div', { class: 'ck-toast ck-toast--action is-visible', role: 'status' },
                h('span', null, message),
                h('button', { type: 'button', class: 'ck-toast__btn' }, actionLabel));
            mount(bar);
            let done = false;
            const finish = (value) => {
                if (done) return;
                done = true;
                bar.classList.remove('is-visible');
                setTimeout(() => bar.remove(), 220);
                resolve(value);
            };
            bar.querySelector('button').addEventListener('click', () => finish(true));
            setTimeout(() => finish(false), seconds * 1000);
        });
    }

    async function copy(text) {
        try {
            await navigator.clipboard.writeText(String(text ?? ''));
            toast(t('copied'));
        } catch (_) {
            toast(t('copy_failed'), 'error');
        }
    }

    // ── Dialoghi ───────────────────────────────────────────────────────────

    /**
     * Finestra modale nello stile del sito. Restituisce una promessa con il
     * valore dell'azione scelta (null se chiusa).
     */
    function dialog(options) {
        return new Promise((resolve) => {
            const previous = document.activeElement;
            const card = h('div', { class: 'ck-dialog__card' + (options.wide ? ' ck-dialog__card--wide' : ''), role: 'dialog', 'aria-modal': 'true' });
            const overlay = h('div', { class: 'ck-dialog' }, card);

            let settled = false;
            const close = (value) => {
                if (settled) return;
                settled = true;
                overlay.classList.add('is-closing');
                document.removeEventListener('keydown', onKey, true);
                setTimeout(() => overlay.remove(), 160);
                if (previous && previous.focus) previous.focus();
                resolve(value === undefined ? null : value);
            };

            if (options.title) {
                card.appendChild(h('header', { class: 'ck-dialog__head' },
                    h('h3', null, options.icon ? icon(options.icon) : null, options.title),
                    h('button', { type: 'button', class: 'ck-icon-btn', 'aria-label': t('close'), onClick: () => close(null) }, icon('fa-solid fa-xmark'))));
            }

            const body = h('div', { class: 'ck-dialog__body' });
            if (typeof options.body === 'string') body.appendChild(h('p', { class: 'ck-dialog__text' }, options.body));
            else if (options.body instanceof Node) body.appendChild(options.body);
            card.appendChild(body);

            const actions = options.actions || [{ label: t('ok'), value: true, kind: 'primary' }];
            if (actions.length) {
                const foot = h('footer', { class: 'ck-dialog__foot' });
                actions.forEach((action) => {
                    foot.appendChild(h('button', {
                        type: 'button',
                        class: 'ck-btn ck-btn--' + (action.kind || 'ghost'),
                        onClick: async () => {
                            if (action.onClick) {
                                const result = await action.onClick(close);
                                if (result === false) return;
                                if (result !== undefined) return close(result);
                            }
                            close(action.value === undefined ? true : action.value);
                        }
                    }, action.icon ? icon(action.icon) : null, action.label));
                });
                card.appendChild(foot);
            }

            function onKey(event) {
                if (event.key === 'Escape') {
                    event.stopPropagation();
                    close(null);
                }
                if (event.key === 'Tab') {
                    const focusable = card.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                    if (!focusable.length) return;
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                }
            }

            overlay.addEventListener('mousedown', (event) => {
                if (event.target === overlay && options.dismissible !== false) close(null);
            });
            document.addEventListener('keydown', onKey, true);

            mount(overlay);
            if (options.onOpen) options.onOpen({ card, body, close });
            const target = card.querySelector('[autofocus]') || card.querySelector('.ck-btn--primary, .ck-btn--danger') || card.querySelector('button');
            if (target) target.focus();
        });
    }

    function confirm(options) {
        return dialog({
            title: options.title,
            icon: options.icon,
            body: options.text,
            actions: [
                { label: options.cancelLabel || t('cancel'), value: false, kind: 'ghost' },
                { label: options.confirmLabel || t('confirm'), value: true, kind: options.danger ? 'danger' : 'primary' }
            ]
        }).then((value) => value === true);
    }

    function prompt(options) {
        const input = options.multiline
            ? h('textarea', { class: 'ck-input', rows: '3', maxlength: options.maxLength || 300, placeholder: options.placeholder || '', autofocus: true })
            : h('input', { class: 'ck-input', type: 'text', maxlength: options.maxLength || 100, placeholder: options.placeholder || '', autofocus: true });
        input.value = options.value || '';
        const body = h('div', { class: 'ck-field' },
            options.label ? h('label', { class: 'ck-label' }, options.label) : null,
            input,
            options.hint ? h('small', { class: 'ck-hint' }, options.hint) : null);

        return dialog({
            title: options.title,
            icon: options.icon,
            body,
            actions: [
                { label: t('cancel'), value: null, kind: 'ghost' },
                { label: options.confirmLabel || t('save'), kind: 'primary', onClick: () => input.value.trim() }
            ],
            onOpen: ({ close }) => {
                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' && !options.multiline) {
                        event.preventDefault();
                        close(input.value.trim());
                    }
                });
                setTimeout(() => {
                    input.focus();
                    input.select?.();
                }, 30);
            }
        });
    }

    // ── Menu ───────────────────────────────────────────────────────────────

    let openMenu = null;

    function closeMenu() {
        if (openMenu) {
            openMenu.destroy();
            openMenu = null;
        }
    }

    /**
     * Menu contestuale. Su schermi stretti diventa un pannello dal basso.
     * `items`: { label, icon, danger, disabled, onSelect } | { divider } | { node }.
     * `anchor`: elemento oppure { x, y }.
     */
    function menu(anchor, items, options = {}) {
        closeMenu();
        const sheet = isMobile();
        const list = h('div', { class: 'ck-menu' + (sheet ? ' ck-menu--sheet' : ''), role: 'menu' });
        const backdrop = sheet ? h('div', { class: 'ck-menu-backdrop' }) : null;

        if (options.header) list.appendChild(h('div', { class: 'ck-menu__header' }, options.header));

        items.filter(Boolean).forEach((item) => {
            if (item.divider) {
                list.appendChild(h('div', { class: 'ck-menu__divider' }));
                return;
            }
            if (item.node) {
                list.appendChild(h('div', { class: 'ck-menu__custom' }, item.node));
                return;
            }
            list.appendChild(h('button', {
                type: 'button',
                role: 'menuitem',
                class: 'ck-menu__item' + (item.danger ? ' is-danger' : ''),
                disabled: !!item.disabled,
                onClick: (event) => {
                    event.stopPropagation();
                    closeMenu();
                    if (item.onSelect) item.onSelect();
                }
            }, item.icon ? icon(item.icon) : h('span', { class: 'ck-menu__spacer' }), h('span', null, item.label)));
        });

        const onOutside = (event) => {
            if (!list.contains(event.target)) closeMenu();
        };
        const onKey = (event) => {
            if (event.key === 'Escape') closeMenu();
        };

        if (backdrop) mount(backdrop);
        mount(list);

        // Colloca il menu vicino al punto di partenza senza farlo uscire
        // dallo schermo. Si richiama anche quando il contenuto cresce
        // (il pannello completo delle emoji).
        const place = () => {
            if (sheet) return;
            let x;
            let y;
            if (anchor instanceof Element) {
                const rect = anchor.getBoundingClientRect();
                x = options.alignRight ? rect.right - list.offsetWidth : rect.left;
                y = rect.bottom + 6;
                if (y + list.offsetHeight > window.innerHeight - 8) y = rect.top - list.offsetHeight - 6;
            } else {
                x = anchor.x;
                y = anchor.y;
                if (y + list.offsetHeight > window.innerHeight - 8) y = y - list.offsetHeight;
            }
            x = Math.max(8, Math.min(x, window.innerWidth - list.offsetWidth - 8));
            y = Math.max(8, Math.min(y, window.innerHeight - list.offsetHeight - 8));
            list.style.left = x + 'px';
            list.style.top = y + 'px';
        };
        place();

        setTimeout(() => {
            document.addEventListener('mousedown', onOutside, true);
            document.addEventListener('touchstart', onOutside, true);
            document.addEventListener('keydown', onKey, true);
            window.addEventListener('resize', closeMenu);
        }, 0);

        openMenu = {
            reposition: place,
            destroy() {
                document.removeEventListener('mousedown', onOutside, true);
                document.removeEventListener('touchstart', onOutside, true);
                document.removeEventListener('keydown', onKey, true);
                window.removeEventListener('resize', closeMenu);
                list.remove();
                if (backdrop) backdrop.remove();
            }
        };
        return openMenu;
    }

    // ── Visualizzatore di immagini e video ─────────────────────────────────

    function lightbox(items, startIndex = 0) {
        const media = items.filter((item) => item && item.src);
        if (!media.length) return;
        let index = Math.max(0, Math.min(startIndex, media.length - 1));

        const stage = h('div', { class: 'ck-lightbox__stage' });
        const caption = h('div', { class: 'ck-lightbox__caption' });
        const downloadLink = h('a', { class: 'ck-icon-btn', 'aria-label': t('download'), title: t('download'), download: '' }, icon('fa-solid fa-download'));
        const prev = h('button', { type: 'button', class: 'ck-lightbox__nav ck-lightbox__nav--prev', 'aria-label': t('previous') }, icon('fa-solid fa-chevron-left'));
        const next = h('button', { type: 'button', class: 'ck-lightbox__nav ck-lightbox__nav--next', 'aria-label': t('next') }, icon('fa-solid fa-chevron-right'));
        const closeBtn = h('button', { type: 'button', class: 'ck-icon-btn', 'aria-label': t('close') }, icon('fa-solid fa-xmark'));
        const overlay = h('div', { class: 'ck-lightbox', role: 'dialog', 'aria-modal': 'true' },
            h('div', { class: 'ck-lightbox__bar' }, caption, h('div', { class: 'ck-lightbox__tools' }, downloadLink, closeBtn)),
            prev, stage, next);

        const show = () => {
            const item = media[index];
            clear(stage);
            const src = safeUrl(item.src, '');
            if (item.type === 'video') {
                stage.appendChild(h('video', { src, controls: true, autoplay: true, playsinline: true }));
            } else {
                stage.appendChild(h('img', { src, alt: item.name || '' }));
            }
            caption.textContent = (item.name || '') + (media.length > 1 ? '  ·  ' + (index + 1) + '/' + media.length : '');
            downloadLink.href = src;
            downloadLink.setAttribute('download', item.name || '');
            prev.hidden = next.hidden = media.length < 2;
        };
        const move = (step) => {
            index = (index + step + media.length) % media.length;
            show();
        };
        const close = () => {
            document.removeEventListener('keydown', onKey, true);
            overlay.remove();
        };
        function onKey(event) {
            if (event.key === 'Escape') {
                event.stopPropagation();
                close();
            } else if (event.key === 'ArrowLeft') move(-1);
            else if (event.key === 'ArrowRight') move(1);
        }

        prev.addEventListener('click', (event) => {
            event.stopPropagation();
            move(-1);
        });
        next.addEventListener('click', (event) => {
            event.stopPropagation();
            move(1);
        });
        closeBtn.addEventListener('click', close);
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay || event.target === stage) close();
        });
        document.addEventListener('keydown', onKey, true);

        mount(overlay);
        show();
        closeBtn.focus();
    }

    // ── Pezzi ricorrenti ───────────────────────────────────────────────────

    function premiumGem() {
        return h('img', { class: 'cr-premium-gem ck-gem', src: '/img/premium.svg', alt: 'Premium', title: 'Premium' });
    }

    function roleBadge(role) {
        if (role !== 'owner' && role !== 'admin') return null;
        return h('span', { class: 'ck-role ck-role--' + role }, role === 'owner' ? 'Owner' : 'Admin');
    }

    function spinner() {
        return h('div', { class: 'ck-spinner', 'aria-hidden': 'true' }, h('span'), h('span'), h('span'));
    }

    function emptyState(iconName, title, text, action) {
        return h('div', { class: 'ck-empty' },
            h('div', { class: 'ck-empty__icon' }, icon(iconName)),
            h('strong', null, title),
            text ? h('p', null, text) : null,
            action || null);
    }

    return {
        lang, locale, t, extend,
        h, icon, clear, mount, escapeHtml, safeUrl, avatarUrl, debounce, isMobile, reducedMotion,
        api, upload, csrf,
        formatTime, dayLabel, dayKey, listTime, relativeTime, presenceLabel, fileSize,
        setCustomEmojis, emojiNode, renderText, isEmojiOnly, customEmojis: () => customEmojis,
        toast, toastAction, copy,
        dialog, confirm, prompt, menu, closeMenu, lightbox,
        premiumGem, roleBadge, spinner, emptyState
    };
})();
