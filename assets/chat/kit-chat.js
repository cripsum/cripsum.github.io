/**
 * Cripsum™ — componenti delle chat, usati sia dalla chat globale sia dalle
 * chat private e di gruppo: lista dei messaggi, riga di scrittura, emoji,
 * GIF. Si appoggia a kit.js.
 *
 * Le pagine passano i messaggi in una forma comune (vedi MessageList) e
 * ricevono indietro le azioni dell'utente; a parlare col server ci pensano
 * loro.
 */
(() => {
    'use strict';

    const K = window.ChatKit;
    const { h, icon, t } = K;

    K.extend({
        emoji_recent: { it: 'Recenti', en: 'Recent' },
        emoji_site: { it: 'Cripsum', en: 'Cripsum' },
        emoji_smileys: { it: 'Faccine', en: 'Smileys' },
        emoji_people: { it: 'Gesti', en: 'Gestures' },
        emoji_nature: { it: 'Natura', en: 'Nature' },
        emoji_food: { it: 'Cibo', en: 'Food' },
        emoji_fun: { it: 'Svago', en: 'Fun' },
        emoji_objects: { it: 'Oggetti', en: 'Objects' },
        emoji_symbols: { it: 'Simboli', en: 'Symbols' },
        gif_search: { it: 'Cerca GIF su KLIPY...', en: 'Search GIFs on KLIPY...' },
        gif_none: { it: 'Nessuna GIF trovata.', en: 'No GIFs found.' },
        gif_more: { it: 'Altre GIF', en: 'More GIFs' },
        msg_deleted: { it: 'Messaggio eliminato', en: 'Message deleted' },
        msg_edited: { it: 'modificato', en: 'edited' },
        msg_forwarded: { it: 'Inoltrato', en: 'Forwarded' },
        msg_sending: { it: 'Invio...', en: 'Sending...' },
        msg_failed: { it: 'Non inviato', en: 'Not sent' },
        msg_retry: { it: 'Riprova', en: 'Retry' },
        msg_discard: { it: 'Scarta', en: 'Discard' },
        msg_read: { it: 'Letto', en: 'Read' },
        msg_sent: { it: 'Inviato', en: 'Sent' },
        msg_unread_divider: { it: 'Nuovi messaggi', en: 'New messages' },
        msg_new_pill: { it: '{n} nuovi', en: '{n} new' },
        msg_reply_gone: { it: 'Messaggio non disponibile', en: 'Message unavailable' },
        act_react: { it: 'Reagisci', en: 'React' },
        act_reply: { it: 'Rispondi', en: 'Reply' },
        act_more: { it: 'Altro', en: 'More' },
        act_bottom: { it: 'Torna in basso', en: 'Back to bottom' },
        att_photo: { it: 'Foto', en: 'Photo' },
        att_video: { it: 'Video', en: 'Video' },
        att_audio: { it: 'Audio', en: 'Audio' },
        att_file: { it: 'File', en: 'File' },
        cmp_placeholder: { it: 'Scrivi un messaggio...', en: 'Write a message...' },
        cmp_send: { it: 'Invia', en: 'Send' },
        cmp_attach: { it: 'Allega file', en: 'Attach files' },
        cmp_emoji: { it: 'Emoji', en: 'Emoji' },
        cmp_gif: { it: 'GIF', en: 'GIF' },
        cmp_replying: { it: 'Risposta a {name}', en: 'Replying to {name}' },
        cmp_editing: { it: 'Modifica del messaggio', en: 'Editing message' },
        cmp_too_many: { it: 'Puoi allegare al massimo {n} file per messaggio.', en: 'You can attach up to {n} files per message.' },
        cmp_too_big: { it: '«{name}» è troppo grande.', en: '"{name}" is too large.' },
        cmp_drop: { it: 'Rilascia per allegare', en: 'Drop to attach' },
        cmp_wait: { it: 'Aspetta {n}s', en: 'Wait {n}s' }
    });

    // ── Emoji ──────────────────────────────────────────────────────────────

    const EMOJI = {
        smileys: '😀 😃 😄 😁 😆 😅 🤣 😂 🙂 🙃 😉 😊 😇 🥰 😍 🤩 😘 😗 😚 😙 😋 😛 😜 🤪 😝 🤑 🤗 🤭 🤫 🤔 🤐 🤨 😐 😑 😶 😏 😒 🙄 😬 🤥 😌 😔 😪 🤤 😴 😷 🤒 🤕 🤢 🤮 🥵 🥶 🥴 😵 🤯 🤠 🥳 😎 🤓 🧐 😕 😟 🙁 😮 😯 😲 😳 🥺 😦 😧 😨 😰 😥 😢 😭 😱 😖 😣 😞 😓 😩 😫 🥱 😤 😡 😠 🤬 😈 👿 💀 💩 🤡 👻 👽 🤖',
        people: '👍 👎 👌 🤌 🤏 ✌️ 🤞 🤟 🤘 🤙 👈 👉 👆 👇 ☝️ ✋ 🤚 🖐️ 🖖 👋 🤝 🙏 ✍️ 💪 👏 🙌 👐 🤲 🫶 🫡 🫠 🤦 🤷 🙋 🙅 🙆 💁 🧠 👀 👁️ 👅 👄 🗣️ 👤 👥 🫂 💃 🕺 🏃 🚶',
        nature: '🐶 🐱 🐭 🐹 🐰 🦊 🐻 🐼 🐨 🐯 🦁 🐮 🐷 🐸 🐵 🙈 🙉 🙊 🐔 🐧 🐦 🦆 🦅 🦉 🐺 🐴 🦄 🐝 🦋 🐌 🐞 🐢 🐍 🐙 🦈 🐬 🐳 🌵 🌲 🌴 🍀 🌸 🌹 🌻 🌙 ⭐ 🌟 ✨ ⚡ 🔥 🌈 ☀️ ☁️ ❄️ 💧 🌊',
        food: '🍏 🍎 🍐 🍊 🍋 🍌 🍉 🍇 🍓 🍒 🍑 🥭 🍍 🥥 🥝 🍅 🥑 🌽 🥕 🥔 🍞 🥐 🧀 🍳 🥓 🍔 🍟 🍕 🌭 🌮 🌯 🍝 🍜 🍣 🍱 🍤 🍩 🍪 🎂 🍰 🍫 🍬 🍭 🍿 ☕ 🍵 🥤 🍺 🍻 🥂 🍷 🍸 🍾',
        fun: '⚽ 🏀 🏈 ⚾ 🎾 🏐 🎱 🏓 🥊 🏆 🥇 🥈 🥉 🎮 🕹️ 🎲 🧩 🎯 🎳 🎸 🎹 🥁 🎤 🎧 🎬 🎨 🎭 🎪 🎟️ 🚗 🏎️ 🚀 ✈️ 🚁 ⛵ 🏠 🏝️ 🗺️ 🎉 🎊 🎁 🎈 🪩',
        objects: '💡 🔦 📱 💻 ⌨️ 🖥️ 🖱️ 📷 🎥 📺 📻 ⏰ ⌛ 🔋 🔌 💰 💵 💎 🔧 🔨 🛠️ 🔑 🔒 🔓 📌 📎 ✂️ 📝 📚 📖 🔖 📦 📫 🗑️ 🧸 👑 💍 👓 🎓 🧢 👕 👟',
        symbols: '❤️ 🧡 💛 💚 💙 💜 🖤 🤍 🤎 💔 ❣️ 💕 💞 💓 💗 💖 💘 💝 💯 💢 💥 💫 💦 💨 🕳️ 💬 💭 💤 ✅ ❌ ❓ ❗ ⚠️ 🚫 ♻️ ➕ ➖ ➗ ✖️ 🆗 🆒 🆕 🔝 🔴 🟠 🟡 🟢 🔵 🟣 ⚫ ⚪ 🏁 🚩 🎌 🏳️'
    };
    const QUICK = ['👍', '❤️', '😂', '😮', '😭', '🔥'];
    const RECENT_KEY = 'cripsum.emoji.recent';

    function recentEmojis() {
        try {
            const list = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
            return Array.isArray(list) ? list.slice(0, 24) : [];
        } catch (_) {
            return [];
        }
    }

    function rememberEmoji(value) {
        try {
            const list = recentEmojis().filter((item) => item !== value);
            list.unshift(value);
            localStorage.setItem(RECENT_KEY, JSON.stringify(list.slice(0, 24)));
        } catch (_) {
            /* senza memoria i recenti restano vuoti */
        }
    }

    /**
     * Pannello delle emoji. `onPick` riceve { value, custom }: per le emoji
     * del sito `value` è il codice (senza i due punti).
     */
    class EmojiPicker {
        constructor(options) {
            this.onPick = options.onPick;
            this.tabs = h('div', { class: 'ck-emoji-tabs', role: 'tablist' });
            this.grid = h('div', { class: 'ck-emoji-grid' });
            this.el = h('div', { class: 'ck-emoji-picker' }, this.tabs, this.grid);
            this.category = '';

            this.grid.addEventListener('click', (event) => {
                const button = event.target.closest('[data-emoji]');
                if (!button) return;
                const custom = button.dataset.custom === '1';
                const value = button.dataset.emoji;
                rememberEmoji((custom ? ':' : '') + value);
                this.onPick({ value, custom });
            });
            this.build();
        }

        build() {
            const hasCustom = K.customEmojis().size > 0;
            const categories = [
                ['recent', 'fa-regular fa-clock', t('emoji_recent')],
                hasCustom ? ['site', 'fa-solid fa-star', t('emoji_site')] : null,
                ['smileys', 'fa-regular fa-face-smile', t('emoji_smileys')],
                ['people', 'fa-regular fa-hand', t('emoji_people')],
                ['nature', 'fa-solid fa-leaf', t('emoji_nature')],
                ['food', 'fa-solid fa-burger', t('emoji_food')],
                ['fun', 'fa-solid fa-gamepad', t('emoji_fun')],
                ['objects', 'fa-regular fa-lightbulb', t('emoji_objects')],
                ['symbols', 'fa-regular fa-heart', t('emoji_symbols')]
            ].filter(Boolean);

            K.clear(this.tabs);
            categories.forEach(([key, iconName, label]) => {
                this.tabs.appendChild(h('button', {
                    type: 'button', class: 'ck-emoji-tab', title: label, 'aria-label': label,
                    dataset: { category: key }, onClick: () => this.show(key)
                }, icon(iconName)));
            });
            this.show(recentEmojis().length ? 'recent' : (hasCustom ? 'site' : 'smileys'));
        }

        show(category) {
            this.category = category;
            this.tabs.querySelectorAll('.ck-emoji-tab').forEach((tab) => tab.classList.toggle('is-active', tab.dataset.category === category));
            K.clear(this.grid);

            const addUnicode = (value) => this.grid.appendChild(h('button', { type: 'button', class: 'ck-emoji-cell', dataset: { emoji: value } }, value));
            const addCustom = (code) => {
                const node = K.emojiNode(code, 'ck-emoji-cell__img');
                if (!node) return;
                this.grid.appendChild(h('button', { type: 'button', class: 'ck-emoji-cell', title: ':' + code + ':', dataset: { emoji: code, custom: '1' } }, node));
            };

            if (category === 'recent') {
                recentEmojis().forEach((value) => (value.startsWith(':') ? addCustom(value.slice(1)) : addUnicode(value)));
            } else if (category === 'site') {
                K.customEmojis().forEach((emoji) => addCustom(emoji.code));
            } else {
                (EMOJI[category] || '').split(' ').forEach(addUnicode);
            }
            this.grid.scrollTop = 0;
        }
    }

    /** Scelta rapida di una reazione: sei emoji e un «+» che apre il pannello completo. */
    function reactionPicker(anchor, onPick) {
        const row = h('div', { class: 'ck-react-quick' });
        const pick = (value) => {
            K.closeMenu();
            onPick(value);
        };
        const recents = recentEmojis().filter((value) => !value.startsWith(':')).slice(0, 6);
        const quick = [...new Set([...recents, ...QUICK])].slice(0, 6);
        quick.forEach((value) => row.appendChild(h('button', { type: 'button', class: 'ck-react-quick__btn', onClick: () => pick(value) }, value)));

        const wrap = h('div', { class: 'ck-react-pop' }, row);
        row.appendChild(h('button', {
            type: 'button', class: 'ck-react-quick__btn ck-react-quick__more', 'aria-label': t('act_more'),
            onClick: (event) => {
                event.stopPropagation();
                if (wrap.querySelector('.ck-emoji-picker')) return;
                wrap.appendChild(new EmojiPicker({ onPick: ({ value }) => pick(value) }).el);
                if (popover && popover.reposition) popover.reposition();
            }
        }, icon('fa-solid fa-plus')));

        const popover = K.menu(anchor, [{ node: wrap }]);
    }

    // ── GIF ────────────────────────────────────────────────────────────────

    class GifPanel {
        constructor(options) {
            this.onPick = options.onPick;
            this.query = '';
            this.next = '';
            this.loading = false;
            this.loaded = false;

            this.input = h('input', { type: 'search', class: 'ck-input', placeholder: t('gif_search'), maxlength: '60', autocomplete: 'off' });
            this.grid = h('div', { class: 'ck-gif-grid' });
            this.more = h('button', { type: 'button', class: 'ck-btn ck-btn--ghost ck-gif-more', hidden: true, onClick: () => this.load(true) }, t('gif_more'));
            this.el = h('div', { class: 'ck-gif-panel' },
                h('div', { class: 'ck-gif-head' }, icon('fa-solid fa-magnifying-glass'), this.input),
                this.grid, this.more,
                h('small', { class: 'ck-gif-credit' }, 'Powered by KLIPY'));

            this.input.addEventListener('input', K.debounce(() => {
                this.query = this.input.value.trim();
                this.next = '';
                this.load(false);
            }, 380));
            this.grid.addEventListener('click', (event) => {
                const button = event.target.closest('.ck-gif-item');
                if (button && button._gif) this.onPick(button._gif);
            });
        }

        open() {
            if (!this.loaded) this.load(false);
            setTimeout(() => this.input.focus(), 60);
        }

        async load(append) {
            if (this.loading) return;
            this.loading = true;
            this.more.hidden = true;
            if (!append) {
                K.clear(this.grid);
                this.grid.appendChild(K.spinner());
            }
            try {
                const params = new URLSearchParams();
                if (this.query) params.set('q', this.query);
                if (append && this.next) params.set('page', this.next);
                const data = await K.api('/api/chat/gifs.php?' + params.toString());
                if (!append) K.clear(this.grid);
                const gifs = data.gifs || [];
                if (!gifs.length && !append) {
                    this.grid.appendChild(h('div', { class: 'ck-gif-empty' }, t('gif_none')));
                }
                gifs.forEach((gif) => {
                    const button = h('button', { type: 'button', class: 'ck-gif-item', title: gif.title || 'GIF' },
                        h('img', { src: K.safeUrl(gif.preview_url || gif.url, ''), alt: gif.title || 'GIF', loading: 'lazy' }));
                    button._gif = gif;
                    this.grid.appendChild(button);
                });
                this.next = data.next || '';
                this.more.hidden = !this.next;
                this.loaded = true;
            } catch (error) {
                K.clear(this.grid);
                this.grid.appendChild(h('div', { class: 'ck-gif-empty' }, error.message));
            } finally {
                this.loading = false;
            }
        }
    }

    // ── Lista dei messaggi ─────────────────────────────────────────────────

    /**
     * Mostra una conversazione e la tiene aggiornata senza ridisegnarla.
     *
     * Forma di un messaggio:
     *   { id, ts, mine, author: { id, name, username, url, avatar, premium, role, badge },
     *     system, deleted, text, gif: { url, preview, title }, attachments: [],
     *     reply: { id, name, text }, forwarded, edited, pinned, favorite,
     *     reactions: [{ emoji, count, mine, title }], status, mention }
     *
     * Ogni elemento resta nel DOM finché il messaggio esiste: un messaggio
     * nuovo o una reazione toccano solo ciò che cambia, quindi le GIF non
     * ripartono e la posizione di lettura non salta.
     */
    /**
     * Indirizzo di un allegato da mostrare. Quelli veri passano dal controllo
     * di sempre; quelli di un messaggio non ancora partito (`local`) sono
     * indirizzi blob creati qui, nel browser di chi sta inviando.
     */
    function mediaUrl(file, key) {
        const value = String(file[key] || '');
        if (file.local) return value.startsWith('blob:') ? value : '';
        return K.safeUrl(value, '');
    }

    /**
     * Fotogramma di un video come JPEG, preso mezzo secondo dopo l'inizio (il
     * primo è spesso nero). Null se il browser non sa leggere il file: in
     * quel caso il video parte lo stesso, solo senza copertina.
     */
    function videoPoster(file) {
        return new Promise((resolve) => {
            const url = URL.createObjectURL(file);
            const video = document.createElement('video');
            let done = false;
            const finish = (blob) => {
                if (done) return;
                done = true;
                clearTimeout(timer);
                video.removeAttribute('src');
                video.load();
                URL.revokeObjectURL(url);
                resolve(blob || null);
            };
            const timer = setTimeout(() => finish(null), 6000);
            const draw = () => {
                try {
                    const width = video.videoWidth;
                    const height = video.videoHeight;
                    if (!width || !height) return finish(null);
                    const scale = Math.min(1, 640 / Math.max(width, height));
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.max(1, Math.round(width * scale));
                    canvas.height = Math.max(1, Math.round(height * scale));
                    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                    canvas.toBlob((blob) => finish(blob), 'image/jpeg', 0.82);
                } catch (_) {
                    finish(null);
                }
                return undefined;
            };

            video.muted = true;
            video.playsInline = true;
            video.preload = 'auto';
            video.addEventListener('error', () => finish(null));
            video.addEventListener('seeked', draw);
            video.addEventListener('loadeddata', () => {
                const target = Math.min(0.5, (video.duration || 0) / 2);
                if (target > 0 && Number.isFinite(target)) video.currentTime = target;
                else draw();
            });
            video.src = url;
        });
    }

    /** Allegati di un messaggio non ancora partito, visti da chi lo invia. */
    function localAttachments(files) {
        return files.map((file) => {
            const type = file.type.startsWith('image/') ? 'image'
                : file.type.startsWith('video/') ? 'video'
                    : file.type.startsWith('audio/') ? 'audio' : 'file';
            return {
                local: true,
                file_type: type,
                file_name: file.name,
                file_size: file.size,
                file_path: type === 'image' ? URL.createObjectURL(file) : '',
                poster: type === 'video' && file.ckPosterBlob ? URL.createObjectURL(file.ckPosterBlob) : null
            };
        });
    }

    function releaseLocalAttachments(attachments) {
        (attachments || []).forEach((file) => {
            if (!file.local) return;
            if (file.file_path) URL.revokeObjectURL(file.file_path);
            if (file.poster) URL.revokeObjectURL(file.poster);
        });
    }

    class MessageList {
        constructor(options) {
            this.el = options.container;
            this.options = Object.assign({ authors: 'always', mentions: false, me: '', groupSeconds: 300 }, options);
            this.items = new Map();
            this.nodes = new Map();
            this.days = new Map();
            this.unreadAfter = null;
            this.unreadNode = null;
            this.newCount = 0;

            this.el.classList.add('ck-list');
            // Senza azioni sui messaggi (i ticket) il clic destro e la selezione
            // del testo restano quelli del browser.
            this.plain = options.tools === false;
            this.el.classList.toggle('ck-list--plain', this.plain);
            this.top = h('div', { class: 'ck-list__top' });
            this.inner = h('div', { class: 'ck-list__inner' });
            // Il tasto «torna in basso» sta dentro l'area che scorre, incollato
            // al fondo: così resta sopra la riga di scrittura in ogni pagina.
            this.pill = h('button', { type: 'button', class: 'ck-list__pill', hidden: true, 'aria-label': t('act_bottom'), onClick: () => this.scrollToBottom(true) },
                icon('fa-solid fa-arrow-down'), h('span'));
            this.el.append(this.top, this.inner, this.pill);

            this.glideUntil = 0;
            this.el.addEventListener('scroll', () => this.onScroll(), { passive: true });
            // Chi scorre a mano mentre la lista sta scendendo riprende il comando.
            ['wheel', 'touchstart'].forEach((type) => this.el.addEventListener(type, () => {
                this.glideUntil = 0;
                this.anchor = null;
            }, { passive: true }));
            this.inner.addEventListener('click', (event) => this.onClick(event));
            this.inner.addEventListener('contextmenu', (event) => {
                const article = event.target.closest('.ck-msg');
                if (this.plain || !article || event.target.closest('a, video, audio, input, textarea')) return;
                const message = this.items.get(article.dataset.id);
                if (!message || message.system || message.status === 'sending') return;
                event.preventDefault();
                // Su Android la pressione prolungata lancia anche questo
                // evento: il menu è già aperto, non va riaperto.
                if (this.pressedUntil > Date.now()) return;
                this.emit('more', message, event, article);
            });
            this.watchLongPress();

            // Le immagini cambiano l'altezza quando finiscono di caricarsi:
            // se si stava leggendo l'ultimo messaggio, si resta lì.
            this.stick = true;
            if (window.ResizeObserver) {
                new ResizeObserver(() => {
                    this.knownHeight = this.el.scrollHeight;
                    if (this.stick) this.el.scrollTop = this.el.scrollHeight;
                    else if (this.anchor && this.anchorUntil > Date.now() && this.anchor.isConnected) this.anchor.scrollIntoView({ block: 'center' });
                }).observe(this.inner);
            }

            if (options.onReachTop && window.IntersectionObserver) {
                new IntersectionObserver((entries) => {
                    if (entries[0].isIntersecting && this.items.size) options.onReachTop();
                }, { root: this.el, rootMargin: '300px 0px 0px 0px' }).observe(this.top);
            }
        }

        emit(action, message, event, element) {
            if (this.options.onAction) this.options.onAction(action, message, event, element);
        }

        /**
         * Su telefono il menu del messaggio si apre tenendo premuto: i
         * pulsanti che compaiono al passaggio del mouse lì non esistono, e
         * iOS non lancia l'evento del clic destro.
         */
        watchLongPress() {
            let timer = null;
            let start = null;
            const cancel = () => {
                clearTimeout(timer);
                timer = null;
            };

            this.pressedUntil = 0;
            this.inner.addEventListener('touchstart', (event) => {
                cancel();
                const article = event.target.closest('.ck-msg');
                if (this.plain || !article || event.touches.length !== 1 || event.target.closest('a, audio, input, textarea, .ck-reaction')) return;
                start = { x: event.touches[0].clientX, y: event.touches[0].clientY };
                timer = setTimeout(() => {
                    timer = null;
                    const message = this.items.get(article.dataset.id);
                    if (!message || message.system || message.status === 'sending') return;
                    // Il dito che si alza non deve aprire l'immagine che c'era sotto.
                    this.pressedUntil = Date.now() + 700;
                    if (navigator.vibrate) navigator.vibrate(8);
                    this.emit('more', message, { type: 'contextmenu', clientX: start.x, clientY: start.y, preventDefault() {} }, article);
                }, 480);
            }, { passive: true });
            this.inner.addEventListener('touchmove', (event) => {
                if (!timer || !start) return;
                const touch = event.touches[0];
                if (Math.abs(touch.clientX - start.x) > 10 || Math.abs(touch.clientY - start.y) > 10) cancel();
            }, { passive: true });
            this.inner.addEventListener('touchend', cancel, { passive: true });
            this.inner.addEventListener('touchcancel', cancel, { passive: true });
        }

        key(id) {
            return String(id);
        }

        get size() {
            return this.items.size;
        }

        get(id) {
            return this.items.get(this.key(id));
        }

        all() {
            return [...this.items.values()].sort(MessageList.order);
        }

        static order(a, b) {
            const pendingA = String(a.id).startsWith('tmp-');
            const pendingB = String(b.id).startsWith('tmp-');
            if (pendingA !== pendingB) return pendingA ? 1 : -1;
            if (pendingA) return a.ts - b.ts;
            return Number(a.id) - Number(b.id);
        }

        firstId() {
            const real = this.all().filter((m) => !String(m.id).startsWith('tmp-'));
            return real.length ? Number(real[0].id) : 0;
        }

        lastId() {
            const real = this.all().filter((m) => !String(m.id).startsWith('tmp-'));
            return real.length ? Number(real[real.length - 1].id) : 0;
        }

        atBottom() {
            return this.el.scrollHeight - this.el.scrollTop - this.el.clientHeight < 90;
        }

        /** Vero mentre lo scorrimento animato verso il fondo è ancora in corsa. */
        gliding() {
            return this.glideUntil > Date.now();
        }

        onScroll() {
            // Un'immagine che finisce di caricarsi allunga la lista: l'evento
            // di scorrimento arriva lo stesso, ma non è l'utente ad aver
            // lasciato il fondo. Si riconosce dall'altezza cambiata.
            const grew = this.el.scrollHeight !== this.knownHeight;
            this.knownHeight = this.el.scrollHeight;
            if (this.stick && grew && !this.atBottom()) {
                this.el.scrollTop = this.el.scrollHeight;
                return;
            }
            this.stick = this.gliding() || this.atBottom();
            if (this.stick) {
                this.newCount = 0;
                if (this.options.onBottom) this.options.onBottom();
            }
            this.updatePill();
        }

        updatePill() {
            const show = !this.stick;
            this.pill.hidden = !show;
            const label = this.pill.querySelector('span');
            label.textContent = this.newCount > 0 ? t('msg_new_pill', { n: this.newCount }) : '';
            this.pill.classList.toggle('has-count', this.newCount > 0);
        }

        scrollToBottom(smooth) {
            const animated = !!smooth && !K.reducedMotion();
            this.stick = true;
            this.newCount = 0;
            // Un messaggio che arriva a metà corsa trova la lista «non in fondo»:
            // senza questo segno finirebbe tra i non letti con l'utente che guarda.
            this.glideUntil = animated ? Date.now() + 700 : 0;
            this.el.scrollTo({ top: this.el.scrollHeight, behavior: animated ? 'smooth' : 'auto' });
            this.updatePill();
        }

        /** Sostituisce tutto (apertura di una conversazione). */
        reset(messages, options = {}) {
            this.items.clear();
            this.nodes.clear();
            this.days.clear();
            K.clear(this.inner);
            this.unreadAfter = options.unreadAfter || null;
            this.unreadNode = null;
            this.newCount = 0;
            messages.forEach((message) => this.items.set(this.key(message.id), message));
            this.render();
            if (options.focusId && this.nodes.has(this.key(options.focusId))) {
                this.reveal(options.focusId);
            } else if (this.unreadNode) {
                this.stick = false;
                this.unreadNode.scrollIntoView({ block: 'center' });
                this.hold(this.unreadNode);
                this.onScroll();
            } else {
                this.stick = true;
                this.el.scrollTop = this.el.scrollHeight;
                this.updatePill();
            }
        }

        /**
         * Tiene fermo un punto della lista per qualche secondo: le immagini
         * che finiscono di caricarsi cambiano le altezze e sposterebbero ciò
         * che si stava guardando. Smette appena l'utente scorre.
         */
        hold(element) {
            this.anchor = element;
            this.anchorUntil = Date.now() + 4000;
        }

        showPlaceholder(node) {
            this.items.clear();
            this.nodes.clear();
            this.days.clear();
            K.clear(this.inner);
            if (node) this.inner.appendChild(node);
            this.pill.hidden = true;
        }

        /** Aggiunge o aggiorna messaggi. `older`: pagina precedente, la lettura non si sposta. */
        upsert(messages, options = {}) {
            if (!messages.length) return;
            const wasBottom = this.gliding() || this.atBottom();
            const before = this.el.scrollHeight;
            const previousTop = this.el.scrollTop;
            let arrived = 0;
            let mineArrived = false;

            messages.forEach((message) => {
                const key = this.key(message.id);
                if (!this.items.has(key)) {
                    if (!options.older) {
                        if (message.mine) mineArrived = true;
                        else if (!message.system) arrived += 1;
                    }
                }
                this.items.set(key, message);
            });
            this.render();

            if (options.older) {
                this.stick = false;
                this.el.scrollTop = previousTop + (this.el.scrollHeight - before);
            } else if (wasBottom || mineArrived) {
                this.scrollToBottom(arrived + (mineArrived ? 1 : 0) <= 3);
            } else if (arrived) {
                this.newCount += arrived;
                this.updatePill();
            }
        }

        remove(id) {
            const key = this.key(id);
            if (!this.items.has(key)) return;
            this.items.delete(key);
            this.render();
        }

        /** Sostituisce un messaggio in attesa con quello vero arrivato dal server. */
        swap(tempId, message) {
            const temp = this.key(tempId);
            const node = this.nodes.get(temp);
            this.items.delete(temp);
            if (node) {
                this.nodes.delete(temp);
                if (this.nodes.has(this.key(message.id))) {
                    node.el.remove();
                } else {
                    node.el.dataset.id = this.key(message.id);
                    this.nodes.set(this.key(message.id), node);
                }
            }
            this.items.set(this.key(message.id), message);
            this.render();
            if (this.stick) this.el.scrollTop = this.el.scrollHeight;
        }

        patch(id, changes) {
            const message = this.get(id);
            if (!message) return;
            Object.assign(message, changes);
            this.render();
        }

        reveal(id) {
            const node = this.nodes.get(this.key(id));
            if (!node) return false;
            this.stick = false;
            node.el.scrollIntoView({ block: 'center', behavior: K.reducedMotion() ? 'auto' : 'smooth' });
            this.hold(node.el);
            node.el.classList.remove('is-flash');
            void node.el.offsetWidth;
            node.el.classList.add('is-flash');
            setTimeout(() => node.el.classList.remove('is-flash'), 1800);
            return true;
        }

        clearUnreadDivider() {
            this.unreadAfter = null;
            if (this.unreadNode) {
                this.unreadNode.remove();
                this.unreadNode = null;
            }
        }

        /** Allinea il DOM all'elenco dei messaggi spostando o creando solo il necessario. */
        render() {
            const list = this.all();
            const wanted = [];
            let previous = null;
            let dividerPlaced = false;
            const usedDays = new Set();

            list.forEach((message, index) => {
                const day = K.dayKey(message.ts);
                if (!previous || K.dayKey(previous.ts) !== day) {
                    let dayNode = this.days.get(day);
                    if (!dayNode) {
                        dayNode = h('div', { class: 'ck-day' }, h('span', null, K.dayLabel(message.ts)));
                        this.days.set(day, dayNode);
                    }
                    usedDays.add(day);
                    wanted.push(dayNode);
                    previous = null;
                }

                if (this.unreadAfter !== null && !dividerPlaced && !message.mine && !message.system
                    && !String(message.id).startsWith('tmp-') && Number(message.id) > this.unreadAfter) {
                    if (!this.unreadNode) this.unreadNode = h('div', { class: 'ck-unread' }, h('span', null, t('msg_unread_divider')));
                    wanted.push(this.unreadNode);
                    dividerPlaced = true;
                    previous = null;
                }

                const next = list[index + 1];
                const continues = this.sameGroup(previous, message);
                const continued = this.sameGroup(message, next) && (!next || K.dayKey(next.ts) === day);
                const signature = JSON.stringify([message, continues, continued]);

                let node = this.nodes.get(this.key(message.id));
                if (!node) {
                    node = { el: this.build(message, continues, continued), signature };
                    this.nodes.set(this.key(message.id), node);
                } else if (node.signature !== signature) {
                    const fresh = this.build(message, continues, continued);
                    if (node.el.parentNode) node.el.parentNode.replaceChild(fresh, node.el);
                    node.el = fresh;
                    node.signature = signature;
                }
                wanted.push(node.el);
                previous = message;
            });

            if (!dividerPlaced && this.unreadNode) {
                this.unreadNode.remove();
                this.unreadNode = null;
            }

            let cursor = this.inner.firstChild;
            wanted.forEach((element) => {
                if (element === cursor) {
                    cursor = cursor.nextSibling;
                } else {
                    this.inner.insertBefore(element, cursor);
                }
            });
            while (cursor) {
                const stale = cursor;
                cursor = cursor.nextSibling;
                stale.remove();
            }

            [...this.nodes.keys()].forEach((key) => {
                if (!this.items.has(key)) this.nodes.delete(key);
            });
            [...this.days.keys()].forEach((key) => {
                if (!usedDays.has(key)) this.days.delete(key);
            });
        }

        sameGroup(a, b) {
            if (!a || !b || a.system || b.system) return false;
            if (String(a.author?.id) !== String(b.author?.id)) return false;
            return Math.abs(b.ts - a.ts) < this.options.groupSeconds;
        }

        build(message, continues, continued) {
            if (message.system) {
                return h('div', { class: 'ck-system', dataset: { id: this.key(message.id) } }, h('span', null, message.system));
            }

            const showAuthor = this.options.authors === 'always' || (this.options.authors === 'others' && !message.mine);
            const classes = ['ck-msg'];
            if (message.mine) classes.push('is-mine');
            if (continues) classes.push('is-cont');
            if (!continued) classes.push('is-tail');
            if (message.deleted) classes.push('is-deleted');
            if (message.status === 'sending') classes.push('is-sending');
            if (message.status === 'error') classes.push('is-error');
            if (message.mention) classes.push('is-mention');
            if (showAuthor) classes.push('has-author');

            const article = h('article', { class: classes.join(' '), dataset: { id: this.key(message.id) } });
            const author = message.author || {};

            if (showAuthor) {
                article.appendChild(continues
                    ? h('span', { class: 'ck-msg__avatar ck-msg__avatar--gap', 'aria-hidden': 'true' })
                    : h('a', { class: 'ck-msg__avatar', href: K.safeUrl(author.url), dataset: { action: 'user', userId: author.id }, tabindex: '-1' },
                        h('img', { src: K.safeUrl(author.avatar, '/img/abdul.jpg'), alt: '', loading: 'lazy' })));
            }

            const main = h('div', { class: 'ck-msg__main' });

            if (showAuthor && !continues) {
                main.appendChild(h('div', { class: 'ck-msg__meta' },
                    h('a', { class: 'ck-msg__name', href: K.safeUrl(author.url), dataset: { action: 'user', userId: author.id } }, author.name || author.username || ''),
                    author.premium ? K.premiumGem() : null,
                    K.roleBadge(author.role),
                    author.badge ? h('span', { class: 'ck-user-badge', title: author.badge.name },
                        author.badge.image ? h('img', { src: K.safeUrl(author.badge.image, ''), alt: '' }) : icon(author.badge.icon || 'fa-solid fa-medal'),
                        author.badge.name) : null));
            }

            const bubble = h('div', { class: 'ck-bubble' });

            if (message.deleted) {
                bubble.appendChild(h('div', { class: 'ck-text ck-text--deleted' }, icon('fa-solid fa-ban'), ' ', t('msg_deleted')));
            } else {
                if (message.forwarded) {
                    bubble.appendChild(h('div', { class: 'ck-fwd' }, icon('fa-solid fa-share'), ' ', t('msg_forwarded')));
                }
                if (message.reply) {
                    bubble.appendChild(h('button', { type: 'button', class: 'ck-reply', dataset: { action: 'jump', target: message.reply.id } },
                        h('strong', null, message.reply.name || ''),
                        h('span', null, message.reply.text || t('msg_reply_gone'))));
                }
                const media = this.buildMedia(message);
                if (media) {
                    bubble.appendChild(media);
                    bubble.classList.add('has-media');
                }
                if (message.text) {
                    const jumbo = !media && !message.reply && K.isEmojiOnly(message.text);
                    const text = h('div', { class: 'ck-text' + (jumbo ? ' is-jumbo' : '') });
                    text.appendChild(K.renderText(message.text, { mentions: this.options.mentions, me: this.options.me }));
                    bubble.appendChild(text);
                    if (jumbo) bubble.classList.add('is-jumbo');
                }
            }

            bubble.appendChild(this.buildFoot(message));

            const row = h('div', { class: 'ck-msg__row' }, bubble);
            if (this.options.tools !== false && !message.deleted && message.status !== 'sending' && message.status !== 'error') {
                row.appendChild(h('div', { class: 'ck-msg__tools' },
                    h('button', { type: 'button', class: 'ck-tool', title: t('act_react'), 'aria-label': t('act_react'), dataset: { action: 'react-pick' } }, icon('fa-regular fa-face-smile')),
                    h('button', { type: 'button', class: 'ck-tool', title: t('act_reply'), 'aria-label': t('act_reply'), dataset: { action: 'reply' } }, icon('fa-solid fa-reply')),
                    h('button', { type: 'button', class: 'ck-tool', title: t('act_more'), 'aria-label': t('act_more'), dataset: { action: 'more' } }, icon('fa-solid fa-ellipsis'))));
            }
            main.appendChild(row);

            if (message.status === 'error') {
                main.appendChild(h('div', { class: 'ck-msg__failed' },
                    icon('fa-solid fa-circle-exclamation'), ' ', message.errorText || t('msg_failed'), ' · ',
                    h('button', { type: 'button', dataset: { action: 'retry' } }, t('msg_retry')), ' · ',
                    h('button', { type: 'button', dataset: { action: 'discard' } }, t('msg_discard'))));
            }

            if (message.reactions && message.reactions.length && !message.deleted) {
                const chips = h('div', { class: 'ck-reactions' });
                message.reactions.forEach((reaction) => {
                    const custom = K.emojiNode(reaction.emoji, 'ck-reaction__img');
                    chips.appendChild(h('button', {
                        type: 'button', class: 'ck-reaction' + (reaction.mine ? ' is-mine' : ''), title: reaction.title || '',
                        dataset: { action: 'react', emoji: reaction.emoji }
                    }, custom || h('span', { class: 'ck-reaction__emoji' }, reaction.emoji), h('strong', null, String(reaction.count))));
                });
                main.appendChild(chips);
            }

            article.appendChild(main);
            return article;
        }

        buildFoot(message) {
            const foot = h('span', { class: 'ck-msg__foot' });
            if (message.pinned) foot.appendChild(icon('fa-solid fa-thumbtack', 'ck-msg__flag'));
            if (message.favorite) foot.appendChild(icon('fa-solid fa-star', 'ck-msg__flag'));
            if (message.edited && !message.deleted) foot.appendChild(h('span', { class: 'ck-msg__edited' }, t('msg_edited')));
            foot.appendChild(h('time', null, message.status === 'sending' ? t('msg_sending') : K.formatTime(message.ts)));
            if (message.mine && !message.deleted && (message.status === 'sent' || message.status === 'read')) {
                const read = message.status === 'read';
                foot.appendChild(h('span', { class: 'ck-msg__ticks' + (read ? ' is-read' : ''), title: t(read ? 'msg_read' : 'msg_sent') },
                    icon(read ? 'fa-solid fa-check-double' : 'fa-solid fa-check')));
            }
            return foot;
        }

        buildMedia(message) {
            if (message.gif && message.gif.url) {
                const url = K.safeUrl(message.gif.url, '');
                if (!url) return null;
                return h('div', { class: 'ck-media ck-media--gif' },
                    h('button', { type: 'button', class: 'ck-media__item', dataset: { action: 'open-gif' } },
                        h('img', { src: K.safeUrl(message.gif.preview || message.gif.url, url), alt: message.gif.title || 'GIF', loading: 'lazy' })));
            }

            const attachments = message.attachments || [];
            if (!attachments.length) return null;

            const visual = attachments.filter((a) => a.file_type === 'image' || a.file_type === 'video' || a.file_type === 'sticker');
            const others = attachments.filter((a) => !visual.includes(a));
            const wrap = h('div', { class: 'ck-media' });

            if (visual.length) {
                const grid = h('div', { class: 'ck-media__grid ck-media__grid--' + Math.min(visual.length, 4) });
                visual.forEach((file, index) => {
                    const src = mediaUrl(file, 'file_path');
                    if (!src && !file.local) return;
                    const cell = h('button', { type: 'button', class: 'ck-media__item', dataset: { action: 'open-media', index: String(index) } });
                    if (file.file_type === 'video') {
                        const poster = mediaUrl(file, 'poster');
                        if (poster) {
                            cell.appendChild(h('img', { src: poster, alt: file.file_name || '', loading: 'lazy' }));
                        } else if (file.local) {
                            cell.classList.add('is-blank');
                        } else {
                            // Senza copertina (video vecchi, formati che il browser
                            // di chi inviava non leggeva): il fotogramma arriva
                            // quando il browser ha scaricato abbastanza file, e
                            // fino ad allora si vede un segnaposto, non un buco nero.
                            const video = h('video', { src: src + '#t=0.1', preload: 'metadata', muted: true, playsinline: true });
                            cell.classList.add('is-blank');
                            video.addEventListener('loadeddata', () => cell.classList.remove('is-blank'), { once: true });
                            cell.appendChild(video);
                        }
                        cell.appendChild(h('span', { class: 'ck-media__play' }, icon('fa-solid fa-play')));
                    } else {
                        cell.appendChild(h('img', { src, alt: file.file_name || '', loading: 'lazy' }));
                    }
                    grid.appendChild(cell);
                });
                wrap.appendChild(grid);
            }

            others.forEach((file) => {
                const body = h('span', { class: 'ck-file__body' }, h('strong', null, file.file_name || t('att_file')), h('small', null, K.fileSize(file.file_size)));
                if (file.local) {
                    wrap.appendChild(h('div', { class: 'ck-file' },
                        h('span', { class: 'ck-file__icon' }, icon(file.file_type === 'audio' ? 'fa-solid fa-music' : 'fa-solid fa-file')), body));
                    return;
                }
                const src = K.safeUrl(file.file_path, '');
                if (!src) return;
                if (file.file_type === 'audio') {
                    wrap.appendChild(h('audio', { class: 'ck-media__audio', src, controls: true, preload: 'none' }));
                    return;
                }
                wrap.appendChild(h('a', { class: 'ck-file', href: src, download: file.file_name || '', target: '_blank', rel: 'noopener' },
                    h('span', { class: 'ck-file__icon' }, icon('fa-solid fa-file-arrow-down')), body));
            });

            return wrap.childNodes.length ? wrap : null;
        }

        onClick(event) {
            const target = event.target.closest('[data-action]');
            if (!target) return;
            if (this.pressedUntil > Date.now()) {
                event.preventDefault();
                return;
            }
            const article = target.closest('.ck-msg');
            const message = article ? this.items.get(article.dataset.id) : null;
            const action = target.dataset.action;

            if (action === 'jump') {
                event.preventDefault();
                const id = Number(target.dataset.target);
                if (!this.reveal(id)) this.emit('jump', { id }, event, target);
                return;
            }
            if (!message) return;

            if (action === 'open-media') {
                // Finché il messaggio non è partito i file stanno solo qui.
                if (String(message.id).startsWith('tmp-')) return;
                const visual = (message.attachments || []).filter((a) => ['image', 'video', 'sticker'].includes(a.file_type));
                K.lightbox(visual.map((a) => ({ type: a.file_type === 'video' ? 'video' : 'image', src: a.file_path, name: a.file_name })), Number(target.dataset.index) || 0);
                return;
            }
            if (action === 'open-gif') {
                K.lightbox([{ type: 'image', src: message.gif.url, name: message.gif.title || 'GIF' }], 0);
                return;
            }
            if (action === 'user') {
                // La pagina decide: di solito apre la card senza lasciare la chat.
                if (this.options.onAction) {
                    event.preventDefault();
                    this.emit('user', message, event, target);
                }
                return;
            }
            event.preventDefault();
            this.emit(action, message, event, target);
        }
    }

    // ── Riga di scrittura ──────────────────────────────────────────────────

    const MAX_IMAGE = 20 * 1024 * 1024;
    const MAX_FILE = 50 * 1024 * 1024;

    /**
     * Riga di scrittura. `onSend` riceve { text, replyTo, editId, files } e
     * restituisce una promessa; se va male il testo viene rimesso al suo
     * posto, così non si perde quello che si era scritto.
     */
    class Composer {
        constructor(options) {
            this.options = Object.assign({ maxLength: 2000, attachments: false, gif: true, maxFiles: 6 }, options);
            this.reply = null;
            this.edit = null;
            this.files = [];
            this.draftKey = null;
            this.disabled = false;
            this.cooldownUntil = 0;
            this.typingSent = 0;

            this.context = h('div', { class: 'ck-composer__context', hidden: true });
            this.filesBar = h('div', { class: 'ck-composer__files', hidden: true });
            this.panel = h('div', { class: 'ck-composer__panel', hidden: true });
            this.suggest = h('div', { class: 'ck-composer__suggest', hidden: true });
            this.input = h('textarea', {
                class: 'ck-composer__textarea', rows: '1', maxlength: String(this.options.maxLength),
                placeholder: this.options.placeholder || t('cmp_placeholder'), 'aria-label': this.options.placeholder || t('cmp_placeholder')
            });
            this.counter = h('span', { class: 'ck-composer__count', hidden: true });
            this.sendBtn = h('button', { type: 'button', class: 'ck-composer__send', 'aria-label': t('cmp_send'), title: t('cmp_send') }, icon('fa-solid fa-paper-plane'));
            this.fileInput = h('input', { type: 'file', multiple: true, hidden: true });
            this.note = h('div', { class: 'ck-composer__note', hidden: true });

            const tools = h('div', { class: 'ck-composer__tools' });
            if (this.options.attachments) {
                tools.appendChild(h('button', { type: 'button', class: 'ck-icon-btn', title: t('cmp_attach'), 'aria-label': t('cmp_attach'), onClick: () => this.fileInput.click() }, icon('fa-solid fa-paperclip')));
            }
            if (this.options.gif) {
                this.gifBtn = h('button', { type: 'button', class: 'ck-icon-btn ck-icon-btn--text', title: t('cmp_gif'), 'aria-label': t('cmp_gif'), onClick: () => this.togglePanel('gif') }, 'GIF');
                tools.appendChild(this.gifBtn);
            }
            this.emojiBtn = h('button', { type: 'button', class: 'ck-icon-btn', title: t('cmp_emoji'), 'aria-label': t('cmp_emoji'), onClick: () => this.togglePanel('emoji') }, icon('fa-regular fa-face-smile'));
            tools.appendChild(this.emojiBtn);

            this.row = h('div', { class: 'ck-composer__row' }, tools,
                h('div', { class: 'ck-composer__input' }, this.input, this.counter), this.sendBtn);
            this.el = h('div', { class: 'ck-composer' }, this.note, this.context, this.filesBar, this.suggest, this.panel, this.row, this.fileInput);
            options.root.appendChild(this.el);

            this.input.addEventListener('input', () => this.onInput());
            this.input.addEventListener('keydown', (event) => this.onKeydown(event));
            this.input.addEventListener('paste', (event) => this.onPaste(event));
            this.sendBtn.addEventListener('click', () => this.submit());
            this.fileInput.addEventListener('change', () => {
                this.addFiles(this.fileInput.files);
                this.fileInput.value = '';
            });
        }

        // — stato —

        focus() {
            if (!K.isMobile()) this.input.focus();
        }

        setDisabled(disabled, reason) {
            this.disabled = !!disabled;
            this.el.classList.toggle('is-disabled', this.disabled);
            this.input.disabled = this.disabled;
            this.note.hidden = !reason;
            this.note.textContent = reason || '';
            this.refreshSend();
        }

        /** Attesa prima del prossimo invio (modalità lenta della chat globale). */
        setCooldown(seconds) {
            this.cooldownUntil = Date.now() + seconds * 1000;
            clearInterval(this.cooldownTimer);
            const tick = () => {
                const left = Math.ceil((this.cooldownUntil - Date.now()) / 1000);
                if (left <= 0) {
                    clearInterval(this.cooldownTimer);
                    this.sendBtn.classList.remove('is-waiting');
                    this.sendBtn.removeAttribute('data-wait');
                    this.refreshSend();
                    return;
                }
                this.sendBtn.classList.add('is-waiting');
                this.sendBtn.dataset.wait = String(left);
                this.sendBtn.disabled = true;
            };
            tick();
            this.cooldownTimer = setInterval(tick, 500);
        }

        refreshSend() {
            const waiting = this.cooldownUntil > Date.now();
            const hasContent = this.input.value.trim() !== '' || this.files.length > 0;
            this.sendBtn.disabled = this.disabled || waiting || !hasContent;
            this.sendBtn.classList.toggle('is-ready', hasContent && !this.disabled && !waiting);
        }

        autosize() {
            this.input.style.height = 'auto';
            // A campo vuoto basta l'altezza di una riga: misurare qui, prima
            // che la pagina abbia finito di disporsi, darebbe numeri a caso.
            if (this.input.value === '') return;
            this.input.style.height = Math.min(this.input.scrollHeight, 168) + 'px';
        }

        // — bozze —

        /** Cambia conversazione: salva la bozza di quella vecchia e riprende quella nuova. */
        setDraftKey(key) {
            this.saveDraft();
            this.draftKey = key;
            this.clearContext();
            this.clearFiles();
            let draft = '';
            try {
                draft = key ? (localStorage.getItem('cripsum.draft.' + key) || '') : '';
            } catch (_) {
                draft = '';
            }
            this.input.value = draft;
            this.onInput(true);
        }

        saveDraft() {
            if (!this.draftKey || this.edit) return;
            try {
                const value = this.input.value;
                if (value.trim()) localStorage.setItem('cripsum.draft.' + this.draftKey, value.slice(0, 4000));
                else localStorage.removeItem('cripsum.draft.' + this.draftKey);
            } catch (_) {
                /* senza memoria la bozza vive finché la pagina resta aperta */
            }
        }

        // — risposta e modifica —

        setReply(message) {
            this.edit = null;
            this.reply = { id: message.id, name: message.author?.name || '', text: message.text || (message.gif ? 'GIF' : '') };
            this.paintContext('fa-solid fa-reply', t('cmp_replying', { name: this.reply.name }), this.reply.text);
            this.focus();
        }

        setEdit(message) {
            this.reply = null;
            this.edit = { id: message.id, previous: this.input.value };
            this.input.value = message.text || '';
            this.paintContext('fa-solid fa-pen', t('cmp_editing'), message.text || '');
            this.onInput(true);
            this.input.focus();
            this.input.setSelectionRange(this.input.value.length, this.input.value.length);
        }

        paintContext(iconName, title, text) {
            K.clear(this.context);
            this.context.append(
                icon(iconName),
                h('div', { class: 'ck-composer__context-body' }, h('strong', null, title), h('span', null, text)),
                h('button', { type: 'button', class: 'ck-icon-btn', 'aria-label': t('cancel'), onClick: () => this.clearContext(true) }, icon('fa-solid fa-xmark')));
            this.context.hidden = false;
        }

        /** Chiude risposta o modifica. Annullare una modifica rimette il testo che c'era prima. */
        clearContext(restore) {
            if (this.edit && restore) {
                this.input.value = this.edit.previous || '';
                this.onInput(true);
            }
            this.reply = null;
            this.edit = null;
            this.context.hidden = true;
            K.clear(this.context);
        }

        // — allegati —

        addFiles(fileList) {
            if (!this.options.attachments || this.disabled) return;
            const incoming = [...(fileList || [])];
            for (const file of incoming) {
                if (this.files.length >= this.options.maxFiles) {
                    K.toast(t('cmp_too_many', { n: this.options.maxFiles }), 'error');
                    break;
                }
                const limit = file.type.startsWith('image/') || file.type.startsWith('audio/') ? MAX_IMAGE : MAX_FILE;
                if (file.size > limit) {
                    K.toast(t('cmp_too_big', { name: file.name }), 'error');
                    continue;
                }
                // La copertina si prepara subito, mentre si scrive: all'invio
                // è già pronta e il video non parte in ritardo.
                if (file.type.startsWith('video/') && !file.ckPoster) {
                    file.ckPoster = videoPoster(file).then((blob) => {
                        file.ckPosterBlob = blob;
                        if (blob && this.files.includes(file)) this.paintFiles();
                        return blob;
                    });
                }
                this.files.push(file);
            }
            this.paintFiles();
            this.refreshSend();
            this.focus();
        }

        clearFiles() {
            this.files = [];
            this.paintFiles();
        }

        paintFiles() {
            this.filesBar.querySelectorAll('img[data-blob]').forEach((img) => URL.revokeObjectURL(img.src));
            K.clear(this.filesBar);
            this.filesBar.hidden = this.files.length === 0;
            this.files.forEach((file, index) => {
                const preview = file.type.startsWith('image/') ? file : (file.ckPosterBlob || null);
                const isImage = !!preview;
                const chip = h('div', { class: 'ck-filechip' + (isImage ? ' ck-filechip--image' : '') });
                if (isImage) {
                    chip.appendChild(h('img', { src: URL.createObjectURL(preview), alt: '', dataset: { blob: '1' } }));
                    if (preview !== file) chip.appendChild(h('span', { class: 'ck-filechip__play' }, icon('fa-solid fa-play')));
                } else {
                    chip.append(icon(file.type.startsWith('video/') ? 'fa-solid fa-film' : (file.type.startsWith('audio/') ? 'fa-solid fa-music' : 'fa-solid fa-file')),
                        h('span', { class: 'ck-filechip__name' }, file.name), h('small', null, K.fileSize(file.size)));
                }
                chip.appendChild(h('button', {
                    type: 'button', class: 'ck-filechip__remove', 'aria-label': t('cancel'),
                    onClick: () => {
                        this.files.splice(index, 1);
                        this.paintFiles();
                        this.refreshSend();
                    }
                }, icon('fa-solid fa-xmark')));
                this.filesBar.appendChild(chip);
            });
        }

        setProgress(fraction) {
            this.el.style.setProperty('--ck-progress', String(Math.max(0, Math.min(1, fraction))));
            this.el.classList.toggle('is-uploading', fraction > 0 && fraction < 1);
        }

        // — pannelli emoji e GIF —

        togglePanel(kind) {
            if (this.disabled) return;
            const same = !this.panel.hidden && this.panelKind === kind;
            this.closePanel();
            if (same) return;

            this.panelKind = kind;
            if (kind === 'emoji') {
                this.emojiPicker = this.emojiPicker || new EmojiPicker({ onPick: ({ value, custom }) => this.insert(custom ? ':' + value + ':' : value) });
                this.panel.appendChild(this.emojiPicker.el);
                this.emojiBtn.classList.add('is-active');
            } else {
                this.gifPanel = this.gifPanel || new GifPanel({
                    onPick: (gif) => {
                        this.closePanel();
                        this.submit({ gif });
                    }
                });
                this.panel.appendChild(this.gifPanel.el);
                this.gifPanel.open();
                if (this.gifBtn) this.gifBtn.classList.add('is-active');
            }
            this.panel.hidden = false;
        }

        closePanel() {
            this.panel.hidden = true;
            K.clear(this.panel);
            this.emojiBtn.classList.remove('is-active');
            if (this.gifBtn) this.gifBtn.classList.remove('is-active');
        }

        insert(text) {
            const start = this.input.selectionStart ?? this.input.value.length;
            const end = this.input.selectionEnd ?? this.input.value.length;
            const value = this.input.value;
            this.input.value = value.slice(0, start) + text + value.slice(end);
            const caret = start + text.length;
            this.input.setSelectionRange(caret, caret);
            this.onInput();
            this.focus();
        }

        // — scrittura —

        onInput(silent) {
            this.autosize();
            const length = this.input.value.length;
            const near = length > this.options.maxLength * 0.85;
            this.counter.hidden = !near;
            this.counter.textContent = length + '/' + this.options.maxLength;
            this.refreshSend();
            if (silent) return;

            this.saveDraftSoon = this.saveDraftSoon || K.debounce(() => this.saveDraft(), 500);
            this.saveDraftSoon();

            if (this.options.onTyping && !this.edit) {
                const now = Date.now();
                if (this.input.value.trim() && now - this.typingSent > 3000) {
                    this.typingSent = now;
                    this.options.onTyping(true);
                }
                clearTimeout(this.typingStop);
                this.typingStop = setTimeout(() => this.stopTyping(), 4000);
            }
            this.updateSuggestions();
        }

        stopTyping() {
            clearTimeout(this.typingStop);
            if (this.typingSent && this.options.onTyping) this.options.onTyping(false);
            this.typingSent = 0;
        }

        onKeydown(event) {
            if (!this.suggest.hidden && ['ArrowDown', 'ArrowUp', 'Enter', 'Tab', 'Escape'].includes(event.key)) {
                event.preventDefault();
                this.navigateSuggestions(event.key);
                return;
            }
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing && !K.isMobile()) {
                event.preventDefault();
                this.submit();
                return;
            }
            if (event.key === 'Escape') {
                if (!this.panel.hidden) this.closePanel();
                else if (this.reply || this.edit) this.clearContext(true);
                return;
            }
            if (event.key === 'ArrowUp' && this.input.value === '' && !this.edit && this.options.onEditLast) {
                event.preventDefault();
                this.options.onEditLast();
            }
        }

        onPaste(event) {
            if (!this.options.attachments) return;
            const files = [...(event.clipboardData?.files || [])];
            if (files.length) {
                event.preventDefault();
                this.addFiles(files);
            }
        }

        // — menzioni —

        updateSuggestions() {
            if (!this.options.mentionSource) return;
            const caret = this.input.selectionStart ?? 0;
            const before = this.input.value.slice(0, caret);
            const match = before.match(/(^|[^A-Za-z0-9_])@([A-Za-z0-9_]{0,20})$/);
            if (!match) {
                this.suggest.hidden = true;
                return;
            }
            const query = match[2].toLowerCase();
            const list = this.options.mentionSource(query).slice(0, 6);
            if (!list.length) {
                this.suggest.hidden = true;
                return;
            }
            K.clear(this.suggest);
            list.forEach((user, index) => {
                this.suggest.appendChild(h('button', {
                    type: 'button', class: 'ck-suggest__item' + (index === 0 ? ' is-active' : ''), dataset: { username: user.username },
                    onMousedown: (event) => {
                        event.preventDefault();
                        this.applyMention(user.username, match[2].length);
                    }
                }, h('img', { src: K.avatarUrl(user.id), alt: '' }), h('strong', null, '@' + user.username),
                    user.display_name && user.display_name !== user.username ? h('span', null, user.display_name) : null));
            });
            this.suggest.hidden = false;
            this.suggestLength = match[2].length;
        }

        navigateSuggestions(key) {
            const items = [...this.suggest.querySelectorAll('.ck-suggest__item')];
            let index = items.findIndex((item) => item.classList.contains('is-active'));
            if (key === 'Escape') {
                this.suggest.hidden = true;
                return;
            }
            if (key === 'Enter' || key === 'Tab') {
                const active = items[Math.max(0, index)];
                if (active) this.applyMention(active.dataset.username, this.suggestLength || 0);
                return;
            }
            index = (index + (key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            items.forEach((item, i) => item.classList.toggle('is-active', i === index));
        }

        applyMention(username, typedLength) {
            const caret = this.input.selectionStart ?? this.input.value.length;
            const start = caret - typedLength;
            this.input.value = this.input.value.slice(0, start) + username + ' ' + this.input.value.slice(caret);
            const next = start + username.length + 1;
            this.input.setSelectionRange(next, next);
            this.suggest.hidden = true;
            this.onInput(true);
            this.input.focus();
        }

        // — invio —

        async submit(extra = {}) {
            if (this.disabled || this.busy) return;
            if (this.cooldownUntil > Date.now() && !this.edit) return;

            const text = this.input.value.trim();
            if (!text && !this.files.length && !extra.gif) return;

            const payload = {
                text,
                replyTo: this.reply,
                editId: this.edit ? this.edit.id : null,
                files: this.files.slice(),
                gif: extra.gif || null
            };
            const snapshot = { text: this.input.value, reply: this.reply, edit: this.edit, files: this.files.slice() };

            // Il campo si svuota subito: scrivere il messaggio successivo non
            // deve aspettare la risposta del server.
            this.stopTyping();
            this.closePanel();
            this.suggest.hidden = true;
            this.input.value = '';
            this.reply = null;
            this.edit = null;
            this.context.hidden = true;
            this.files = [];
            this.paintFiles();
            this.onInput(true);
            try {
                if (this.draftKey) localStorage.removeItem('cripsum.draft.' + this.draftKey);
            } catch (_) {
                /* niente */
            }
            this.focus();

            this.busy = !!payload.files.length || !!payload.editId;
            try {
                await this.options.onSend(payload);
            } catch (error) {
                // Non è andata: tutto torna dov'era, a meno che nel frattempo
                // l'utente non abbia già scritto altro.
                if (error && error.restore !== false && this.input.value === '') {
                    this.input.value = snapshot.text;
                    this.reply = snapshot.reply;
                    this.files = snapshot.files;
                    this.paintFiles();
                    if (snapshot.edit) {
                        this.edit = snapshot.edit;
                        this.paintContext('fa-solid fa-pen', t('cmp_editing'), snapshot.text);
                    } else if (snapshot.reply) {
                        this.paintContext('fa-solid fa-reply', t('cmp_replying', { name: snapshot.reply.name }), snapshot.reply.text);
                    }
                    this.onInput(true);
                }
            } finally {
                this.busy = false;
                this.setProgress(0);
                this.refreshSend();
            }
        }
    }

    /** Zona di rilascio dei file su un'area della pagina. */
    function dropZone(area, onFiles) {
        let depth = 0;
        const overlay = h('div', { class: 'ck-drop', hidden: true }, h('div', null, icon('fa-solid fa-cloud-arrow-up'), h('strong', null, t('cmp_drop'))));
        area.appendChild(overlay);
        const hasFiles = (event) => [...(event.dataTransfer?.types || [])].includes('Files');

        area.addEventListener('dragenter', (event) => {
            if (!hasFiles(event)) return;
            event.preventDefault();
            depth += 1;
            overlay.hidden = false;
        });
        area.addEventListener('dragover', (event) => {
            if (hasFiles(event)) event.preventDefault();
        });
        area.addEventListener('dragleave', () => {
            depth = Math.max(0, depth - 1);
            if (depth === 0) overlay.hidden = true;
        });
        area.addEventListener('drop', (event) => {
            if (!hasFiles(event)) return;
            event.preventDefault();
            depth = 0;
            overlay.hidden = true;
            onFiles(event.dataTransfer.files);
        });
    }

    Object.assign(K, { EmojiPicker, GifPanel, MessageList, Composer, reactionPicker, dropZone, videoPoster, localAttachments, releaseLocalAttachments });
})();
