/**
 * Cripsum™ — chat globale.
 *
 * I messaggi nuovi, le modifiche, le reazioni e chi sta scrivendo arrivano
 * dal controllo leggero del sito (assets/rt/rt.js → api/rt/poll.php), che
 * non apre il database: questa pagina non ha un giro di richieste suo.
 * Al server si parla solo per fare qualcosa (inviare, reagire, cercare) o
 * per caricare messaggi che non sono ancora qui.
 */
(() => {
    'use strict';

    const K = window.ChatKit;
    const cfg = window.CripsumChat;
    if (!K || !cfg) return;

    const { h, icon, t } = K;
    const RT = window.CripsumRT || null;
    const me = cfg.user;

    K.extend({
        gc_live: { it: 'In diretta', en: 'Live' },
        gc_offline: { it: 'Riconnessione...', en: 'Reconnecting...' },
        gc_typing_one: { it: '{a} sta scrivendo', en: '{a} is typing' },
        gc_typing_two: { it: '{a} e {b} stanno scrivendo', en: '{a} and {b} are typing' },
        gc_typing_many: { it: '{a}, {b} e altri stanno scrivendo', en: '{a}, {b} and others are typing' },
        gc_empty_title: { it: 'Ancora nessun messaggio', en: 'No messages yet' },
        gc_empty_text: { it: 'Rompi il ghiaccio: scrivi il primo.', en: 'Break the ice: write the first one.' },
        gc_placeholder: { it: 'Scrivi a tutti... (@ per menzionare)', en: 'Write to everyone... (@ to mention)' },
        gc_copy: { it: 'Copia testo', en: 'Copy text' },
        gc_edit: { it: 'Modifica', en: 'Edit' },
        gc_delete: { it: 'Elimina', en: 'Delete' },
        gc_delete_title: { it: 'Eliminare il messaggio?', en: 'Delete this message?' },
        gc_delete_text: { it: 'Sparirà per tutti. Non si può annullare.', en: 'It will disappear for everyone. This cannot be undone.' },
        gc_delete_mod_title: { it: 'Rimuovi messaggio', en: 'Remove message' },
        gc_delete_mod_label: { it: 'Motivo (arriva all\'autore, facoltativo)', en: 'Reason (sent to the author, optional)' },
        gc_deleted: { it: 'Messaggio eliminato.', en: 'Message deleted.' },
        gc_report: { it: 'Segnala', en: 'Report' },
        gc_report_title: { it: 'Segnala messaggio', en: 'Report message' },
        gc_report_intro: { it: 'Stai segnalando un messaggio di {name}. Lo staff lo controllerà.', en: 'You are reporting a message from {name}. The staff will review it.' },
        gc_report_r1: { it: 'Contenuto inappropriato o spinto', en: 'Inappropriate or explicit content' },
        gc_report_r2: { it: 'Spam o messaggi ripetitivi', en: 'Spam or repetitive messages' },
        gc_report_r3: { it: 'Insulti, bullismo, odio', en: 'Insults, bullying, hate' },
        gc_report_r4: { it: 'Altro', en: 'Other' },
        gc_report_details: { it: 'Dettagli (facoltativo)', en: 'Details (optional)' },
        gc_report_send: { it: 'Invia segnalazione', en: 'Send report' },
        gc_mute: { it: 'Muta utente', en: 'Mute user' },
        gc_mute_done: { it: 'Non vedrai più i messaggi di {name}.', en: 'You will no longer see messages from {name}.' },
        gc_undo: { it: 'Annulla', en: 'Undo' },
        gc_profile: { it: 'Vedi profilo', en: 'View profile' },
        gc_pin: { it: 'Fissa in alto', en: 'Pin to top' },
        gc_unpin: { it: 'Togli avviso', en: 'Remove notice' },
        gc_pinned_by: { it: 'Avviso dello staff', en: 'Staff notice' },
        gc_timeout: { it: 'Sospendi dalla chat', en: 'Suspend from chat' },
        gc_timeout_title: { it: 'Sospendi {name}', en: 'Suspend {name}' },
        gc_timeout_text: { it: 'Durante la sospensione potrà leggere ma non scrivere. Riceverà un avviso nella posta.', en: 'While suspended they can read but not write. They will get a notice in their inbox.' },
        gc_timeout_done: { it: 'Utente sospeso.', en: 'User suspended.' },
        gc_timeout_off: { it: 'Sospensione dalla chat non ancora attiva su questo server.', en: 'Chat suspension is not available on this server yet.' },
        gc_min5: { it: '5 minuti', en: '5 minutes' },
        gc_hour1: { it: '1 ora', en: '1 hour' },
        gc_day1: { it: '1 giorno', en: '1 day' },
        gc_week1: { it: '7 giorni', en: '7 days' },
        gc_reason: { it: 'Motivo (facoltativo)', en: 'Reason (optional)' },
        gc_suspended: { it: 'Sei sospeso dalla chat globale fino alle {time}. Puoi leggere, non scrivere.', en: 'You are suspended from the global chat until {time}. You can read, not write.' },
        gc_search_placeholder: { it: 'Cerca nei messaggi...', en: 'Search messages...' },
        gc_search_none: { it: 'Nessun messaggio trovato.', en: 'No messages found.' },
        gc_search_hint: { it: 'Scrivi almeno due lettere.', en: 'Type at least two letters.' },
        gc_history: { it: 'Stai guardando messaggi vecchi.', en: 'You are viewing older messages.' },
        gc_history_back: { it: 'Torna al presente', en: 'Back to now' },
        gc_history_new: { it: '{n} nuovi messaggi', en: '{n} new messages' },
        gc_menu_alerts: { it: 'Avvisi e notifiche', en: 'Alerts and notifications' },
        gc_menu_sound_on: { it: 'Suono a ogni messaggio: attivo', en: 'Sound on every message: on' },
        gc_menu_sound_off: { it: 'Suono a ogni messaggio: spento', en: 'Sound on every message: off' },
        gc_menu_muted: { it: 'Utenti mutati', en: 'Muted users' },
        gc_menu_policy: { it: 'Regolamento della chat', en: 'Chat policy' },
        gc_menu_mod: { it: 'Moderazione', en: 'Moderation' },
        gc_muted_title: { it: 'Utenti mutati', en: 'Muted users' },
        gc_muted_empty: { it: 'Non hai mutato nessuno.', en: 'You have not muted anyone.' },
        gc_unmute: { it: 'Togli muto', en: 'Unmute' },
        gc_mod_title: { it: 'Moderazione', en: 'Moderation' },
        gc_mod_slow: { it: 'Modalità lenta', en: 'Slow mode' },
        gc_mod_slow_hint: { it: 'Attesa minima fra due messaggi dello stesso utente. Lo staff è escluso.', en: 'Minimum wait between two messages from the same user. Staff is exempt.' },
        gc_mod_off: { it: 'Spenta', en: 'Off' },
        gc_mod_seconds: { it: '{n} secondi', en: '{n} seconds' },
        gc_mod_words: { it: 'Parole bloccate', en: 'Blocked words' },
        gc_mod_words_hint: { it: 'Un messaggio che ne contiene una non viene inviato.', en: 'A message containing one of them is not sent.' },
        gc_mod_word_add: { it: 'Aggiungi', en: 'Add' },
        gc_mod_word_placeholder: { it: 'Nuova parola...', en: 'New word...' },
        gc_mod_saved: { it: 'Salvato.', en: 'Saved.' },
        gc_present_empty: { it: 'Per ora ci sei solo tu.', en: 'It is just you for now.' },
        gc_you: { it: 'tu', en: 'you' },
        gc_start: { it: 'Qui comincia la chat globale.', en: 'This is where the global chat begins.' }
    });

    K.setCustomEmojis(cfg.emojis);

    const els = {
        app: document.getElementById('gcApp'),
        main: document.querySelector('.gc-main'),
        list: document.getElementById('gcList'),
        typing: document.getElementById('gcTyping'),
        composer: document.getElementById('gcComposer'),
        pinned: document.getElementById('gcPinned'),
        search: document.getElementById('gcSearch'),
        banner: document.getElementById('gcBanner'),
        live: document.getElementById('gcLive'),
        subtitle: document.getElementById('gcSubtitle'),
        presentCount: document.getElementById('gcPresentCount'),
        onlineCount: document.getElementById('gcOnlineCount'),
        present: document.getElementById('gcPresent'),
        side: document.getElementById('gcSide'),
        sideBackdrop: document.getElementById('gcSideBackdrop')
    };

    const state = {
        slow: Number(cfg.state?.slow) || 0,
        detached: false,
        missed: 0,
        reachedStart: false,
        loadingOlder: false,
        soundAll: false,
        pending: new Map(),
        known: new Map(),
        present: []
    };

    try {
        state.soundAll = localStorage.getItem('cripsum.chat.sound') === 'on';
    } catch (_) {
        state.soundAll = false;
    }

    // ── Dal messaggio del server alla forma della lista ────────────────────

    function remember(message) {
        if (message && message.username) {
            state.known.set(String(message.username).toLowerCase(), { id: message.user_id, username: message.username, display_name: message.display_name });
        }
    }

    function toItem(message) {
        remember(message);
        const reply = message.reply ? {
            id: message.reply.id,
            name: '@' + message.reply.username,
            text: message.reply.message_type === 'deleted' ? '' : (message.reply.message_type === 'gif' ? 'GIF' : message.reply.message)
        } : null;

        return {
            id: message.id,
            ts: Number(message.ts) || Math.floor(Date.now() / 1000),
            mine: !!message.is_mine,
            author: {
                id: message.user_id,
                name: '@' + message.username,
                username: message.username,
                url: message.profile_url,
                avatar: message.avatar_url,
                premium: !!message.is_premium,
                role: message.role,
                badge: message.badge || null
            },
            deleted: !!message.is_deleted,
            text: message.is_deleted ? '' : (message.message || ''),
            gif: !message.is_deleted && message.message_type === 'gif' && message.media_url
                ? { url: message.media_url, preview: message.media_preview_url || message.media_url, title: message.media_title || 'GIF' }
                : null,
            attachments: [],
            reply,
            edited: !!message.edited_at,
            reactions: (message.reactions || []).map((r) => ({ emoji: r.emoji, count: r.count, mine: !!r.mine })),
            mention: !!message.mentions_me,
            can: {
                edit: !!message.can_edit,
                remove: !!message.can_delete,
                report: !!message.can_report,
                moderate: !!message.can_moderate
            }
        };
    }

    // ── Lista ──────────────────────────────────────────────────────────────

    const SEEN_KEY = 'cripsum.gc.seen.' + me.id;
    const readSeen = () => {
        try {
            return Number(localStorage.getItem(SEEN_KEY)) || 0;
        } catch (_) {
            return 0;
        }
    };
    const markSeen = () => {
        if (state.detached || document.visibilityState !== 'visible') return;
        const last = list.lastId();
        if (!last) return;
        try {
            localStorage.setItem(SEEN_KEY, String(last));
        } catch (_) {
            /* niente memoria: il separatore dei nuovi non comparirà */
        }
    };

    const list = new K.MessageList({
        container: els.list,
        authors: 'always',
        mentions: true,
        me: me.username,
        onAction: handleAction,
        onReachTop: loadOlder,
        onBottom: () => {
            markSeen();
            list.clearUnreadDivider();
        }
    });

    function showInitial(messages, focusId) {
        const items = messages.map(toItem);
        if (!items.length) {
            list.showPlaceholder(K.emptyState('fa-regular fa-comments', t('gc_empty_title'), t('gc_empty_text')));
            return;
        }
        const seen = readSeen();
        const last = Number(items[items.length - 1].id);
        list.reset(items, {
            unreadAfter: !focusId && seen > 0 && seen < last ? seen : null,
            focusId: focusId || null
        });
        state.reachedStart = messages.length < 40;
    }

    async function reloadLatest() {
        try {
            const data = await K.api('/api/chat/messages.php?limit=40');
            state.detached = false;
            state.missed = 0;
            paintBanner();
            showInitial(data.messages || []);
            if (data.state) {
                applyAux(data.state);
                if (RT) RT.watchGlobal(data.state);
            }
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    async function loadOlder() {
        if (state.loadingOlder || state.reachedStart || !list.firstId()) return;
        state.loadingOlder = true;
        try {
            const data = await K.api('/api/chat/messages.php?before=' + list.firstId() + '&limit=40');
            const messages = data.messages || [];
            if (messages.length < 40) state.reachedStart = true;
            list.upsert(messages.map(toItem), { older: true });
        } catch (_) {
            /* si riproverà al prossimo scorrimento */
        } finally {
            state.loadingOlder = false;
        }
    }

    /** Porta a un messaggio: se è già caricato ci scorre, altrimenti carica i messaggi intorno. */
    async function jumpTo(id) {
        if (list.reveal(id)) return;
        try {
            const data = await K.api('/api/chat/messages.php?around=' + encodeURIComponent(id) + '&limit=40');
            const messages = data.messages || [];
            if (!messages.some((m) => Number(m.id) === Number(id))) {
                K.toast(t('msg_reply_gone'), 'error');
                return;
            }
            state.detached = true;
            state.missed = 0;
            state.reachedStart = false;
            list.reset(messages.map(toItem), { focusId: id });
            paintBanner();
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    function paintBanner() {
        K.clear(els.banner);
        els.banner.hidden = !state.detached;
        if (!state.detached) return;
        els.banner.append(
            h('span', null, icon('fa-solid fa-clock-rotate-left'), ' ',
                state.missed > 0 ? t('gc_history_new', { n: state.missed }) : t('gc_history')),
            h('button', { type: 'button', class: 'ck-btn ck-btn--primary ck-btn--sm', onClick: reloadLatest }, t('gc_history_back')));
    }

    // ── Tempo reale ────────────────────────────────────────────────────────

    function onGlobalEvent(event) {
        if (event.t === 'resync') {
            if (!state.detached) reloadLatest();
            return;
        }
        const message = event.m;
        if (!message) return;

        if (event.t === 'upd') {
            if (list.get(message.id)) list.upsert([toItem(message)]);
            return;
        }

        if (state.detached) {
            state.missed += 1;
            paintBanner();
            return;
        }

        // Un mio messaggio può arrivare da qui prima della risposta
        // all'invio: la bolla in attesa viene sostituita lì, non duplicata.
        const wasEmpty = list.size === 0;
        if (wasEmpty) list.showPlaceholder(null);
        list.upsert([toItem(message)]);

        if (!message.is_mine) {
            const hidden = document.visibilityState !== 'visible';
            if (state.soundAll && hidden && RT) RT.playSound();
            if (!hidden && list.atBottom()) markSeen();
        }
    }

    function applyAux(aux) {
        if (!aux) return;
        if (typeof aux.slow === 'number') state.slow = aux.slow;
        if (typeof aux.online_count === 'number') els.onlineCount.textContent = String(Math.max(aux.online_count, aux.present_count || 0));
        if (Array.isArray(aux.present)) paintPresent(aux.present);
        if (Array.isArray(aux.typing)) paintTyping(aux.typing);
        if ('pinned' in aux) paintPinned(aux.pinned);
    }

    function paintTyping(users) {
        const names = users.map((u) => '@' + u.username);
        if (!names.length) {
            els.typing.hidden = true;
            return;
        }
        K.clear(els.typing);
        const label = names.length === 1 ? t('gc_typing_one', { a: names[0] })
            : names.length === 2 ? t('gc_typing_two', { a: names[0], b: names[1] })
                : t('gc_typing_many', { a: names[0], b: names[1] });
        els.typing.append(h('span', { class: 'gc-typing__dots' }, h('i'), h('i'), h('i')), label);
        els.typing.hidden = false;
        // Se il segnale di «ha smesso» non arriva, l'indicatore non resta appeso.
        clearTimeout(paintTyping.timer);
        paintTyping.timer = setTimeout(() => {
            els.typing.hidden = true;
        }, 8000);
    }

    function paintPresent(users) {
        state.present = users;
        users.forEach((u) => state.known.set(String(u.username).toLowerCase(), u));
        els.presentCount.textContent = String(users.length);
        K.clear(els.present);
        if (users.length <= 1) {
            els.present.appendChild(h('p', { class: 'gc-side__empty' }, t('gc_present_empty')));
        }
        users.forEach((user) => {
            els.present.appendChild(h('button', {
                type: 'button', class: 'gc-person', dataset: { userId: user.id },
                onClick: () => openUser(user.id, user.username)
            },
                h('span', { class: 'gc-person__avatar' }, h('img', { src: K.avatarUrl(user.id), alt: '', loading: 'lazy' }), h('span', { class: 'ck-dot is-online' })),
                h('span', { class: 'gc-person__body' },
                    h('strong', null, user.display_name || user.username, user.is_premium ? K.premiumGem() : null),
                    h('small', null, '@' + user.username + (Number(user.id) === Number(me.id) ? ' · ' + t('gc_you') : ''))),
                K.roleBadge(user.role)));
        });
    }

    function paintPinned(pinned) {
        K.clear(els.pinned);
        els.pinned.hidden = !pinned;
        if (!pinned) return;
        els.pinned.appendChild(
            h('button', { type: 'button', class: 'gc-pinned__body', onClick: () => jumpTo(pinned.id) },
                icon('fa-solid fa-thumbtack'),
                h('span', null, h('strong', null, t('gc_pinned_by') + ' · @' + pinned.username), h('em', null, pinned.message || ''))));
        if (me.isMod) {
            els.pinned.appendChild(h('button', { type: 'button', class: 'ck-icon-btn', title: t('gc_unpin'), 'aria-label': t('gc_unpin'), onClick: () => moderate({ action: 'unpin' }) }, icon('fa-solid fa-xmark')));
        }
    }

    function setLive(ok) {
        els.live.classList.toggle('is-off', !ok);
        els.live.title = t(ok ? 'gc_live' : 'gc_offline');
    }

    // ── Invio ──────────────────────────────────────────────────────────────

    const composer = new K.Composer({
        root: els.composer,
        maxLength: cfg.maxLength,
        placeholder: t('gc_placeholder'),
        attachments: false,
        gif: true,
        onSend: send,
        onTyping: (typing) => {
            K.api('/api/chat/typing.php', { body: { typing } }).catch(() => {});
        },
        onEditLast: () => {
            const mine = list.all().filter((m) => m.mine && m.can?.edit && !m.deleted);
            if (mine.length) composer.setEdit(mine[mine.length - 1]);
        },
        mentionSource: (query) => {
            const out = [];
            state.known.forEach((user, key) => {
                if (Number(user.id) !== Number(me.id) && key.startsWith(query)) out.push(user);
            });
            return out.sort((a, b) => a.username.localeCompare(b.username));
        }
    });
    composer.setDraftKey('global');

    function pendingItem(nonce, payload) {
        return {
            id: 'tmp-' + nonce,
            ts: Math.floor(Date.now() / 1000),
            mine: true,
            author: { id: me.id, name: '@' + me.username, username: me.username, url: '/u/' + encodeURIComponent(me.username), avatar: K.avatarUrl(me.id), premium: me.premium, role: me.role },
            text: payload.text,
            gif: payload.gif ? { url: payload.gif.url, preview: payload.gif.preview_url || payload.gif.url, title: payload.gif.title || 'GIF' } : null,
            reply: payload.replyTo ? { id: payload.replyTo.id, name: payload.replyTo.name, text: payload.replyTo.text } : null,
            attachments: [],
            reactions: [],
            status: 'sending'
        };
    }

    async function send(payload) {
        if (payload.editId) {
            try {
                const data = await K.api('/api/chat/edit.php', { body: { id: payload.editId, message: payload.text } });
                if (data.message) list.upsert([toItem(data.message)]);
            } catch (error) {
                K.toast(error.message, 'error');
                throw error;
            }
            return;
        }

        if (state.detached) await reloadLatest();

        const nonce = Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
        const body = {
            type: payload.gif ? 'gif' : 'text',
            message: payload.text,
            reply_to: payload.replyTo ? payload.replyTo.id : null,
            client_nonce: nonce
        };
        if (payload.gif) {
            body.media_url = payload.gif.url;
            body.media_preview_url = payload.gif.preview_url || payload.gif.url;
            body.media_title = payload.gif.title || 'GIF';
        }

        if (list.size === 0) list.showPlaceholder(null);
        const temp = pendingItem(nonce, payload);
        list.upsert([temp]);
        await deliver(temp.id, body);
    }

    async function deliver(tempId, body) {
        try {
            const data = await K.api('/api/chat/send.php', { body });
            state.pending.delete(tempId);
            list.swap(tempId, toItem(data.message));
            if (!me.isMod && state.slow > 0) composer.setCooldown(state.slow);
            markSeen();
        } catch (error) {
            const refused = error.status >= 400 && error.status < 500;
            if (refused) {
                // Il server ha detto di no (troppo veloce, filtro, sospensione):
                // la bolla sparisce e il testo torna nel campo.
                state.pending.delete(tempId);
                list.remove(tempId);
                K.toast(error.message, 'error');
                const wait = /(\d+)\s*s/.exec(error.message || '');
                if (error.status === 429 && wait) composer.setCooldown(Number(wait[1]));
                throw error;
            }
            // Rete o server giù: la bolla resta, con «Riprova».
            state.pending.set(tempId, body);
            list.patch(tempId, { status: 'error', errorText: error.message });
            const keep = new Error(error.message);
            keep.restore = false;
            throw keep;
        }
    }

    // ── Azioni sui messaggi ────────────────────────────────────────────────

    function openUser(userId, username) {
        if (Number(userId) === Number(me.id)) {
            window.location.href = '/u/' + encodeURIComponent(me.username);
            return;
        }
        if (window.CripsumUserCard) window.CripsumUserCard.open({ id: userId });
        else window.location.href = '/u/' + encodeURIComponent(username);
    }

    async function react(item, emoji) {
        try {
            const data = await K.api('/api/chat/react.php', { body: { id: item.id, emoji } });
            if (data.message) list.upsert([toItem(data.message)]);
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    function handleAction(action, item, event, element) {
        switch (action) {
            case 'reply':
                composer.setReply(item);
                break;
            case 'react-pick':
                K.reactionPicker(element, (emoji) => react(item, emoji));
                break;
            case 'react':
                react(item, element.dataset.emoji);
                break;
            case 'user':
                openUser(item.author.id, item.author.username);
                break;
            case 'jump':
                jumpTo(item.id);
                break;
            case 'retry': {
                const body = state.pending.get(item.id);
                if (!body) return list.remove(item.id);
                list.patch(item.id, { status: 'sending', errorText: '' });
                deliver(item.id, body).catch(() => {});
                break;
            }
            case 'discard':
                state.pending.delete(item.id);
                list.remove(item.id);
                break;
            case 'more':
                openMessageMenu(item, event, element);
                break;
        }
    }

    function openMessageMenu(item, event, element) {
        if (item.deleted) return;
        const anchor = event && event.type === 'contextmenu' ? { x: event.clientX, y: event.clientY } : element;
        const items = [
            { label: t('act_reply'), icon: 'fa-solid fa-reply', onSelect: () => composer.setReply(item) },
            { label: t('act_react'), icon: 'fa-regular fa-face-smile', onSelect: () => K.reactionPicker(anchor, (emoji) => react(item, emoji)) },
            item.text ? { label: t('gc_copy'), icon: 'fa-regular fa-copy', onSelect: () => K.copy(item.text) } : null,
            item.can.edit ? { label: t('gc_edit'), icon: 'fa-solid fa-pen', onSelect: () => composer.setEdit(item) } : null
        ];

        if (!item.mine) {
            items.push({ divider: true });
            items.push({ label: t('gc_profile'), icon: 'fa-regular fa-user', onSelect: () => openUser(item.author.id, item.author.username) });
            items.push({ label: t('gc_mute'), icon: 'fa-solid fa-volume-xmark', onSelect: () => muteUser(item.author) });
            if (item.can.report) items.push({ label: t('gc_report'), icon: 'fa-regular fa-flag', onSelect: () => reportMessage(item) });
        }

        if (me.isMod) {
            items.push({ divider: true });
            items.push({ label: t('gc_pin'), icon: 'fa-solid fa-thumbtack', onSelect: () => moderate({ action: 'pin', id: item.id }) });
            if (item.can.moderate) items.push({ label: t('gc_timeout'), icon: 'fa-solid fa-hourglass-half', onSelect: () => timeoutUser(item.author) });
        }

        if (item.can.remove) {
            items.push({ divider: true });
            items.push({ label: t('gc_delete'), icon: 'fa-regular fa-trash-can', danger: true, onSelect: () => deleteMessage(item) });
        }

        K.menu(anchor, items, { header: item.author.name });
    }

    async function deleteMessage(item) {
        let reason = '';
        if (item.mine) {
            const ok = await K.confirm({ title: t('gc_delete_title'), text: t('gc_delete_text'), confirmLabel: t('gc_delete'), danger: true, icon: 'fa-regular fa-trash-can' });
            if (!ok) return;
        } else {
            const answer = await K.prompt({ title: t('gc_delete_mod_title'), label: t('gc_delete_mod_label'), maxLength: 200, confirmLabel: t('gc_delete'), icon: 'fa-solid fa-shield-halved' });
            if (answer === null) return;
            reason = answer;
        }
        try {
            const data = await K.api('/api/chat/delete.php', { body: { id: item.id, reason } });
            if (data.message) list.upsert([toItem(data.message)]);
            K.toast(t('gc_deleted'));
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    async function reportMessage(item) {
        const reasons = [t('gc_report_r1'), t('gc_report_r2'), t('gc_report_r3'), t('gc_report_r4')];
        const group = h('div', { class: 'gc-radio' });
        reasons.forEach((label, index) => {
            group.appendChild(h('label', { class: 'gc-radio__row' },
                h('input', { type: 'radio', name: 'gcReason', value: label, checked: index === 0 }),
                h('span', null, label)));
        });
        const details = h('textarea', { class: 'ck-input', rows: '2', maxlength: '200', placeholder: t('gc_report_details') });
        const body = h('div', { class: 'ck-field' }, h('p', { class: 'ck-dialog__text' }, t('gc_report_intro', { name: item.author.name })), group, details);

        const sent = await K.dialog({
            title: t('gc_report_title'),
            icon: 'fa-regular fa-flag',
            body,
            actions: [
                { label: t('cancel'), value: null, kind: 'ghost' },
                {
                    label: t('gc_report_send'), kind: 'danger',
                    onClick: async () => {
                        const reason = group.querySelector('input:checked')?.value || reasons[0];
                        const extra = details.value.trim();
                        try {
                            const data = await K.api('/api/chat/report.php', { body: { id: item.id, reason: extra ? reason + ' - ' + extra : reason } });
                            K.toast(data.message || t('ok'), 'success');
                            return true;
                        } catch (error) {
                            K.toast(error.message, 'error');
                            return false;
                        }
                    }
                }
            ]
        });
        return sent;
    }

    async function muteUser(author) {
        try {
            await K.api('/api/chat/mute.php', { body: { user_id: author.id, muted: true } });
            list.all().filter((m) => Number(m.author?.id) === Number(author.id)).forEach((m) => list.remove(m.id));
            const undo = await K.toastAction(t('gc_mute_done', { name: author.name }), t('gc_undo'));
            if (undo) {
                await K.api('/api/chat/mute.php', { body: { user_id: author.id, muted: false } });
                reloadLatest();
            }
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    async function moderate(body) {
        try {
            const data = await K.api('/api/chat/moderate.php', { body });
            K.toast(t('gc_mod_saved'), 'success');
            return data;
        } catch (error) {
            K.toast(error.message, 'error');
            return null;
        }
    }

    async function timeoutUser(author) {
        if (!cfg.timeoutAvailable) {
            K.toast(t('gc_timeout_off'), 'error');
            return;
        }
        const select = h('select', { class: 'ck-input' },
            h('option', { value: '5' }, t('gc_min5')),
            h('option', { value: '60' }, t('gc_hour1')),
            h('option', { value: '1440' }, t('gc_day1')),
            h('option', { value: '10080' }, t('gc_week1')));
        const reason = h('input', { class: 'ck-input', type: 'text', maxlength: '200', placeholder: t('gc_reason') });
        const ok = await K.dialog({
            title: t('gc_timeout_title', { name: author.name }),
            icon: 'fa-solid fa-hourglass-half',
            body: h('div', { class: 'ck-field' }, h('p', { class: 'ck-dialog__text' }, t('gc_timeout_text')), select, reason),
            actions: [
                { label: t('cancel'), value: false, kind: 'ghost' },
                { label: t('gc_timeout'), value: true, kind: 'danger' }
            ]
        });
        if (!ok) return;
        const data = await moderate({ action: 'timeout', user_id: author.id, minutes: Number(select.value), reason: reason.value.trim() });
        if (data) K.toast(t('gc_timeout_done'), 'success');
    }

    // ── Ricerca ────────────────────────────────────────────────────────────

    function toggleSearch(open) {
        const show = open === undefined ? els.search.hidden : open;
        els.search.hidden = !show;
        K.clear(els.search);
        if (!show) return;

        const results = h('div', { class: 'gc-search__results ck-scroll' }, h('p', { class: 'gc-search__hint' }, t('gc_search_hint')));
        const input = h('input', { type: 'search', class: 'ck-input', placeholder: t('gc_search_placeholder'), maxlength: '80', autocomplete: 'off' });

        const run = K.debounce(async () => {
            const query = input.value.trim();
            K.clear(results);
            if (query.length < 2) {
                results.appendChild(h('p', { class: 'gc-search__hint' }, t('gc_search_hint')));
                return;
            }
            results.appendChild(K.spinner());
            try {
                const data = await K.api('/api/chat/messages.php?limit=30&search=' + encodeURIComponent(query));
                K.clear(results);
                const found = (data.messages || []).slice().reverse();
                if (!found.length) {
                    results.appendChild(h('p', { class: 'gc-search__hint' }, t('gc_search_none')));
                    return;
                }
                found.forEach((message) => {
                    results.appendChild(h('button', {
                        type: 'button', class: 'gc-result',
                        onClick: () => {
                            toggleSearch(false);
                            jumpTo(message.id);
                        }
                    },
                        h('img', { src: K.safeUrl(message.avatar_url, ''), alt: '', loading: 'lazy' }),
                        h('span', { class: 'gc-result__body' },
                            h('strong', null, '@' + message.username, h('time', null, K.dayLabel(message.ts) + ' ' + K.formatTime(message.ts))),
                            h('span', null, message.message_type === 'gif' ? 'GIF ' + (message.message || '') : message.message))));
                });
            } catch (error) {
                K.clear(results);
                results.appendChild(h('p', { class: 'gc-search__hint' }, error.message));
            }
        }, 350);

        input.addEventListener('input', run);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') toggleSearch(false);
        });

        els.search.append(
            h('div', { class: 'gc-search__bar' }, icon('fa-solid fa-magnifying-glass'), input,
                h('button', { type: 'button', class: 'ck-icon-btn', 'aria-label': t('close'), onClick: () => toggleSearch(false) }, icon('fa-solid fa-xmark'))),
            results);
        input.focus();
    }

    // ── Menu della pagina ──────────────────────────────────────────────────

    function openPageMenu(anchor) {
        K.menu(anchor, [
            { label: t('gc_menu_alerts'), icon: 'fa-regular fa-bell', onSelect: () => RT && RT.openSettings() },
            {
                label: t(state.soundAll ? 'gc_menu_sound_on' : 'gc_menu_sound_off'),
                icon: state.soundAll ? 'fa-solid fa-volume-high' : 'fa-solid fa-volume-xmark',
                onSelect: () => {
                    state.soundAll = !state.soundAll;
                    try {
                        localStorage.setItem('cripsum.chat.sound', state.soundAll ? 'on' : 'off');
                    } catch (_) {
                        /* vale per questa pagina */
                    }
                    if (state.soundAll && RT) RT.playSound();
                }
            },
            { label: t('gc_menu_muted'), icon: 'fa-solid fa-user-slash', onSelect: openMuted },
            { label: t('gc_menu_policy'), icon: 'fa-solid fa-book-open', onSelect: () => window.open(cfg.policyUrl, '_blank', 'noopener') },
            me.isMod ? { divider: true } : null,
            me.isMod ? { label: t('gc_menu_mod'), icon: 'fa-solid fa-shield-halved', onSelect: openModeration } : null
        ], { alignRight: true });
    }

    async function openMuted() {
        const body = h('div', { class: 'gc-rows' }, K.spinner());
        K.dialog({ title: t('gc_muted_title'), icon: 'fa-solid fa-user-slash', body, actions: [{ label: t('close'), kind: 'ghost', value: null }] })
            .then(() => {
                if (body.dataset.changed) reloadLatest();
            });
        try {
            const data = await K.api('/api/chat/mute.php');
            K.clear(body);
            if (!(data.muted || []).length) {
                body.appendChild(h('p', { class: 'ck-dialog__text' }, t('gc_muted_empty')));
                return;
            }
            data.muted.forEach((user) => {
                const row = h('div', { class: 'gc-row' },
                    h('img', { src: K.avatarUrl(user.id), alt: '' }),
                    h('span', { class: 'gc-row__body' }, h('strong', null, user.display_name), h('small', null, '@' + user.username)),
                    h('button', {
                        type: 'button', class: 'ck-btn ck-btn--ghost ck-btn--sm',
                        onClick: async () => {
                            try {
                                await K.api('/api/chat/mute.php', { body: { user_id: user.id, muted: false } });
                                row.remove();
                                body.dataset.changed = '1';
                                if (!body.children.length) body.appendChild(h('p', { class: 'ck-dialog__text' }, t('gc_muted_empty')));
                            } catch (error) {
                                K.toast(error.message, 'error');
                            }
                        }
                    }, t('gc_unmute')));
                body.appendChild(row);
            });
        } catch (error) {
            K.clear(body);
            body.appendChild(h('p', { class: 'ck-dialog__text' }, error.message));
        }
    }

    async function openModeration() {
        const slow = h('select', { class: 'ck-input' });
        [0, 2, 4, 6, 10, 15, 30, 60, 120].forEach((seconds) => {
            slow.appendChild(h('option', { value: String(seconds) }, seconds === 0 ? t('gc_mod_off') : t('gc_mod_seconds', { n: seconds })));
        });
        const words = h('div', { class: 'gc-words' });
        const wordInput = h('input', { class: 'ck-input', type: 'text', maxlength: '80', placeholder: t('gc_mod_word_placeholder') });

        const paintWords = (list) => {
            K.clear(words);
            list.forEach((word) => {
                words.appendChild(h('span', { class: 'gc-word' }, word.word,
                    h('button', {
                        type: 'button', 'aria-label': t('gc_delete'),
                        onClick: async () => {
                            const data = await moderate({ action: 'word_remove', word_id: word.id });
                            if (data) paintWords(data.words || []);
                        }
                    }, icon('fa-solid fa-xmark'))));
            });
        };
        const addWord = async () => {
            const word = wordInput.value.trim();
            if (!word) return;
            const data = await moderate({ action: 'word_add', word });
            if (data) {
                wordInput.value = '';
                paintWords(data.words || []);
            }
        };
        wordInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addWord();
            }
        });
        slow.addEventListener('change', () => moderate({ action: 'slow', seconds: Number(slow.value) }));

        const body = h('div', { class: 'gc-mod' },
            h('div', { class: 'ck-field' }, h('label', { class: 'ck-label' }, t('gc_mod_slow')), slow, h('small', { class: 'ck-hint' }, t('gc_mod_slow_hint'))),
            h('div', { class: 'ck-field' }, h('label', { class: 'ck-label' }, t('gc_mod_words')),
                h('div', { class: 'gc-mod__add' }, wordInput, h('button', { type: 'button', class: 'ck-btn ck-btn--primary', onClick: addWord }, t('gc_mod_word_add'))),
                words, h('small', { class: 'ck-hint' }, t('gc_mod_words_hint'))));

        K.dialog({ title: t('gc_mod_title'), icon: 'fa-solid fa-shield-halved', wide: true, body, actions: [{ label: t('close'), kind: 'ghost', value: null }] });

        try {
            const data = await K.api('/api/chat/moderate.php');
            const current = Number(data.settings?.slow) || 0;
            if (![...slow.options].some((o) => Number(o.value) === current)) slow.appendChild(h('option', { value: String(current) }, t('gc_mod_seconds', { n: current })));
            slow.value = String(current);
            paintWords(data.words || []);
        } catch (error) {
            K.toast(error.message, 'error');
        }
    }

    // ── Pannello «In chat adesso» su schermi stretti ───────────────────────

    function toggleSide(open) {
        const show = open === undefined ? !els.side.classList.contains('is-open') : open;
        els.side.classList.toggle('is-open', show);
        els.side.parentElement.classList.toggle('has-side-open', show);
        els.sideBackdrop.hidden = !show;
    }

    // ── Sospensione ────────────────────────────────────────────────────────

    function applyTimeout() {
        const until = Number(cfg.timeoutUntil) || 0;
        const left = until - Math.floor(Date.now() / 1000);
        if (left <= 0) {
            composer.setDisabled(false);
            return;
        }
        composer.setDisabled(true, t('gc_suspended', { time: K.formatTime(until) }));
        setTimeout(applyTimeout, Math.min(left * 1000 + 500, 2147483000));
    }

    // ── Layout: la chat sta sotto la navbar e si adatta alla tastiera ──────

    function layout() {
        const navbar = document.querySelector('.navbarutenti');
        const bottom = navbar ? Math.max(0, navbar.getBoundingClientRect().bottom) : 0;
        document.documentElement.style.setProperty('--ck-nav', Math.round(bottom + 12) + 'px');
        const viewport = window.visualViewport;
        document.documentElement.style.setProperty('--ck-vh', Math.round(viewport ? viewport.height : window.innerHeight) + 'px');
    }

    // ── Avvio ──────────────────────────────────────────────────────────────

    layout();
    window.addEventListener('resize', layout, { passive: true });
    window.visualViewport?.addEventListener('resize', layout, { passive: true });
    setTimeout(layout, 400);

    document.getElementById('gcSearchBtn').addEventListener('click', () => toggleSearch());
    document.getElementById('gcMenuBtn').addEventListener('click', (event) => openPageMenu(event.currentTarget));
    document.getElementById('gcPeopleBtn').addEventListener('click', () => toggleSide());
    document.getElementById('gcSideClose').addEventListener('click', () => toggleSide(false));
    els.sideBackdrop.addEventListener('click', () => toggleSide(false));

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            toggleSearch(true);
        }
    });

    // Clic su una menzione: card dell'utente invece di cambiare pagina.
    els.list.addEventListener('click', (event) => {
        const mention = event.target.closest('.ck-mention');
        if (!mention) return;
        const user = state.known.get(String(mention.dataset.username || '').toLowerCase());
        if (user && window.CripsumUserCard) {
            event.preventDefault();
            openUser(user.id, user.username);
        }
    });

    const focusId = Number(new URLSearchParams(window.location.search).get('message')) || 0;
    showInitial(cfg.messages || []);
    applyAux(cfg.state);
    applyTimeout();
    setLive(true);
    if (focusId) jumpTo(focusId);
    composer.focus();

    if (RT) {
        RT.setMode('chat');
        RT.watchGlobal(cfg.state);
        RT.onGlobal(onGlobalEvent);
        RT.onAux(applyAux);

        // Con la chat davanti una menzione è già vista: niente riquadro, e
        // il numero in navbar non deve restare acceso. A scheda nascosta
        // resta da vedere, e si spegne al ritorno.
        RT.setActiveChat({ kind: 'global', id: 0 });
        let pings = false;
        const seePings = () => {
            if (!pings || document.visibilityState !== 'visible') return;
            pings = false;
            K.api('/api/chat/mentions_read.php', { body: {} }).catch(() => {
                pings = true;
            });
        };
        RT.onUser((event) => {
            if (event.t !== 'mn') return;
            pings = true;
            seePings();
        });
        document.addEventListener('visibilitychange', seePings);
        // Senza timbri sul server si ripiega su un controllo lento che passa dal database.
        let lastFallback = 0;
        RT.onFallback(async () => {
            if (state.detached || Date.now() - lastFallback < 5500) return;
            lastFallback = Date.now();
            try {
                const data = await K.api('/api/chat/messages.php?after=' + list.lastId() + '&limit=60');
                (data.messages || []).forEach((message) => onGlobalEvent({ t: 'msg', m: message }));
                applyAux(data.state);
                setLive(true);
            } catch (_) {
                setLive(false);
            }
        });
    }

    window.addEventListener('online', () => setLive(true));
    window.addEventListener('offline', () => setLive(false));
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && list.atBottom()) markSeen();
    });
    window.addEventListener('pagehide', () => {
        if (navigator.sendBeacon) navigator.sendBeacon('/api/chat/status.php?leave=1');
    });
})();
