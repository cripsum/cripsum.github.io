(() => {
    'use strict';

    const cfg = window.CripsumChat || {};
    const state = {
        messages: new Map(),
        lastId: 0,
        firstId: 0,
        loading: false,
        olderLoading: false,
        search: '',
        replyTo: null,
        editing: null,
        nearBottom: true,
        soundEnabled: localStorage.getItem('cripsum.chat.sound') === 'on',
        pollingTimer: null,
        typingTimer: null,
        stopTypingTimer: null,
        toastTimer: null,
        searchTimer: null,
        unread: 0,
        gifOpen: false,
        gifQuery: '',
        gifNext: '',
        gifLoading: false,
        reactionPopover: null,
        loadedOnce: false
    };

    const el = {
        app: document.getElementById('chatApp'),
        messages: document.getElementById('chatMessages'),
        form: document.getElementById('chatForm'),
        input: document.getElementById('chatInput'),
        send: document.getElementById('chatSendButton'),
        counter: document.getElementById('chatCounter'),
        toast: document.getElementById('chatToast'),
        online: document.getElementById('chatOnlineCount'),
        typing: document.getElementById('chatTyping'),
        searchBox: document.getElementById('chatSearch'),
        searchInput: document.getElementById('chatSearchInput'),
        loadOlder: document.querySelector('.js-load-older'),
        newMessages: document.querySelector('.js-new-messages'),
        scrollBottom: document.querySelector('.js-scroll-bottom'),
        replyPreview: document.getElementById('replyPreview'),
        replyUsername: document.getElementById('replyUsername'),
        replyText: document.getElementById('replyText'),
        editPreview: document.getElementById('editPreview'),
        soundButton: document.querySelector('.js-toggle-sound'),
        searchButton: document.querySelector('.js-toggle-search'),
        syncState: document.getElementById('chatSyncState'),
        notificationSound: document.getElementById('notificationSound'),
        gifPanel: document.getElementById('chatGifPanel'),
        gifGrid: document.getElementById('chatGifGrid'),
        gifSearch: document.getElementById('chatGifSearch'),
        gifMore: document.querySelector('.js-more-gifs'),
        gifButton: document.querySelector('.js-toggle-gifs'),
        emojiButton: document.querySelector('.js-toggle-emojis'),
        emojiStrip: document.getElementById('chatEmojiStrip')
    };


    const setViewportHeight = () => {
        const height = window.visualViewport?.height || window.innerHeight;
        document.documentElement.style.setProperty('--chat-vh', `${height}px`);
    };

    const isMobileChat = () => window.matchMedia('(max-width: 760px)').matches;

    const closeOpenMessageActions = (except = null) => {
        document.querySelectorAll('.chat-message.is-actions-open').forEach((item) => {
            if (item !== except) item.classList.remove('is-actions-open');
        });
    };

    const toggleMobileMessageActions = (event) => {
        if (!isMobileChat()) return;
        if (event.target.closest('a, button, input, textarea, .chat-reaction-popover')) return;

        const bubble = event.target.closest('.chat-bubble-wrap');
        if (!bubble) {
            closeOpenMessageActions();
            return;
        }

        const article = bubble.closest('.chat-message');
        if (!article || !article.querySelector('.chat-message-actions')) return;

        const willOpen = !article.classList.contains('is-actions-open');
        closeOpenMessageActions(article);
        article.classList.toggle('is-actions-open', willOpen);
    };

    const keepBottomAfterMediaLoad = (event) => {
        const target = event.target;
        if (!target || target.tagName !== 'IMG') return;
        if (state.nearBottom || isAtBottom()) {
            requestAnimationFrame(() => scrollToBottom('auto'));
        }
    };

    const api = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                ...(options.body ? { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf } : {})
            },
            ...options
        });

        let data = null;
        try { data = await response.json(); } catch (_) { data = null; }

        if (!response.ok || !data || data.ok === false) {
            const message = data?.error || `Errore ${response.status}`;
            const err = new Error(message);
            err.data = data;
            err.status = response.status;
            throw err;
        }
        return data;
    };

    const escapeHtml = (value = '') => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const safeUrl = (url = '') => {
        const value = String(url || '');
        return /^https?:\/\//i.test(value) || value.startsWith('/') ? value : '#';
    };

    const linkify = (text) => {
        const escaped = escapeHtml(text);
        return escaped.replace(/(https?:\/\/[^\s<]+)/gi, (url) => {
            const clean = url.replace(/[.,!?;:)]+$/g, '');
            const tail = url.slice(clean.length);
            return `<a href="${escapeHtml(clean)}" target="_blank" rel="noopener noreferrer">${escapeHtml(clean)}</a>${escapeHtml(tail)}`;
        });
    };

    const showToast = (message) => {
        if (!el.toast) return;
        el.toast.textContent = message;
        el.toast.classList.add('is-visible');
        clearTimeout(state.toastTimer);
        state.toastTimer = setTimeout(() => el.toast.classList.remove('is-visible'), 2400);
    };

    const setSyncState = (mode, label) => {
        if (!el.syncState) return;
        el.syncState.dataset.state = mode;
        const text = el.syncState.querySelector('span');
        if (text) text.textContent = label;
    };

    const isAtBottom = () => {
        if (!el.messages) return true;
        return el.messages.scrollHeight - el.messages.scrollTop - el.messages.clientHeight < 120;
    };

    const scrollToBottom = (behavior = 'smooth') => {
        if (!el.messages) return;
        el.messages.scrollTo({ top: el.messages.scrollHeight, behavior });
        state.unread = 0;
        updateFloatingButtons();
    };

    const updateFloatingButtons = () => {
        if (!el.scrollBottom || !el.newMessages || !el.messages) return;
        state.nearBottom = isAtBottom();
        el.scrollBottom.hidden = state.nearBottom;
        el.newMessages.hidden = state.nearBottom || state.unread <= 0;
        if (state.unread > 0) el.newMessages.textContent = `${state.unread} nuovi messaggi`;
    };

    const autoResize = () => {
        if (!el.input) return;
        el.input.style.height = 'auto';
        el.input.style.height = `${Math.min(el.input.scrollHeight, 140)}px`;
        if (el.counter) el.counter.textContent = `${el.input.value.length}/${cfg.maxLength || 500}`;
    };

    const dayLabel = (dateString) => {
        const date = new Date(String(dateString || '').replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return '';
        const today = new Date();
        const yesterday = new Date();
        yesterday.setDate(today.getDate() - 1);
        const sameDay = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
        if (sameDay(date, today)) return 'Oggi';
        if (sameDay(date, yesterday)) return 'Ieri';
        return date.toLocaleDateString('it-IT', { day: '2-digit', month: 'short', year: 'numeric' });
    };

    const roleBadgeHtml = (roleBadge) => {
        if (!roleBadge) return '';
        return `<span class="chat-role chat-role--${escapeHtml(roleBadge.class)}">${escapeHtml(roleBadge.label)}</span>`;
    };

    const profileBadgeHtml = (badge) => {
        if (!badge) return '';
        const image = badge.image ? `<img src="${escapeHtml(badge.image)}" alt="">` : '<i class="fas fa-medal"></i>';
        return `<span class="chat-user-badge" title="${escapeHtml(badge.name)}">${image}${escapeHtml(badge.name)}</span>`;
    };

    const actionButton = (name, icon, label, extraClass = '') => {
        return `<button type="button" class="chat-message-action ${extraClass}" data-action="${name}" title="${label}" aria-label="${label}"><i class="${icon}"></i></button>`;
    };

    const messageActionsHtml = (msg) => {
        if (msg.is_deleted) return '';
        const actions = [
            actionButton('reply', 'fas fa-reply', 'Rispondi'),
            actionButton('react', 'far fa-face-smile', 'Reagisci'),
            actionButton('copy', 'fas fa-copy', 'Copia'),
        ];
        if (msg.can_edit) actions.push(actionButton('edit', 'fas fa-pen', 'Modifica'));
        if (msg.can_delete) actions.push(actionButton('delete', 'fas fa-trash', 'Elimina', 'is-danger'));
        if (msg.can_report) actions.push(actionButton('report', 'fas fa-flag', 'Segnala'));
        if (msg.can_report) actions.push(actionButton('mute', 'fas fa-user-slash', 'Muta utente'));
        return `<div class="chat-message-actions">${actions.join('')}</div>`;
    };

    const replyTextFor = (msg) => {
        if (!msg) return '';
        if (msg.message_type === 'gif') return `[GIF] ${msg.media_title || msg.message || ''}`.trim();
        return msg.message || '';
    };

    const replyHtml = (reply) => {
        if (!reply) return '';
        const text = reply.message_type === 'gif' ? '[GIF]' : (reply.message || 'Messaggio');
        return `<a class="chat-reply-card" href="#msg-${Number(reply.id)}" data-scroll-message="${Number(reply.id)}"><strong>@${escapeHtml(reply.username)}</strong><span>${escapeHtml(text)}</span></a>`;
    };

    const reactionsHtml = (reactions = []) => {
        if (!Array.isArray(reactions) || reactions.length === 0) return '';
        return `<div class="chat-reactions">${reactions.map((r) => `<button type="button" class="chat-reaction ${r.mine ? 'is-mine' : ''}" data-action="quick-react" data-emoji="${escapeHtml(r.emoji)}"><span>${escapeHtml(r.emoji)}</span><strong>${Number(r.count) || 0}</strong></button>`).join('')}</div>`;
    };

    const messageBodyHtml = (msg) => {
        if (msg.is_deleted) return '<em>Messaggio eliminato</em>';
        const text = msg.message || '';

        if (msg.message_type === 'gif' && msg.media_url) {
            const preview = safeUrl(msg.media_preview_url || msg.media_url);
            const full = safeUrl(msg.media_url);
            const title = msg.media_title || 'GIF';
            const caption = text ? `<figcaption>${linkify(text)}</figcaption>` : '';
            return `<figure class="chat-gif-message"><a href="${escapeHtml(full)}" target="_blank" rel="noopener noreferrer"><img src="${escapeHtml(preview)}" alt="${escapeHtml(title)}" loading="lazy"></a>${caption}</figure>`;
        }

        return linkify(text);
    };

    const messageHtml = (msg, pending = false, error = false) => {
        const mine = msg.is_mine || Number(msg.user_id) === Number(cfg.userId);
        const deleted = msg.is_deleted;
        const classes = ['chat-message', mine ? 'is-mine' : '', deleted ? 'is-deleted' : '', pending ? 'is-pending' : '', error ? 'is-error' : ''].filter(Boolean).join(' ');
        const msgId = String(msg.id);
        const bodyText = msg.message_type === 'gif' ? replyTextFor(msg) : (msg.message || '');
        const edited = msg.edited_at && !deleted ? '<span class="chat-edited">modificato</span>' : '';

        const avatar = `<a class="chat-avatar-link" href="${escapeHtml(safeUrl(msg.profile_url || '#'))}" tabindex="-1"><img src="${escapeHtml(safeUrl(msg.avatar_url || '/img/abdul.jpg'))}" alt="" loading="lazy"></a>`;
        const main = `
            <div class="chat-message-main">
                <div class="chat-message-meta">
                    <a class="chat-username" href="${escapeHtml(safeUrl(msg.profile_url || '#'))}">@${escapeHtml(msg.username || 'utente')}</a>
                    ${roleBadgeHtml(msg.role_badge)}
                    ${profileBadgeHtml(msg.badge)}
                    <time class="chat-time" datetime="${escapeHtml(msg.created_at || '')}">${escapeHtml(msg.created_label || '')}${edited}</time>
                </div>
                <div class="chat-bubble-wrap">
                    <div class="chat-bubble">${replyHtml(msg.reply)}<div class="chat-message-content">${messageBodyHtml(msg)}</div></div>
                    ${messageActionsHtml(msg)}
                </div>
                ${reactionsHtml(msg.reactions)}
            </div>`;

        return `<article class="${classes}" id="msg-${escapeHtml(msgId)}" data-id="${escapeHtml(msgId)}" data-user-id="${Number(msg.user_id) || 0}" data-message="${escapeHtml(bodyText)}" data-username="${escapeHtml(msg.username || 'utente')}">${mine ? main + avatar : avatar + main}</article>`;
    };

    const emptyHtml = () => `<div class="chat-empty"><div class="chat-empty-inner"><i class="fas fa-comments"></i><strong>Nessun messaggio</strong><p>Scrivi il primo.</p></div></div>`;

    const addDaySeparators = (messages) => {
        let html = '';
        let lastDay = null;
        messages.forEach((msg) => {
            const label = dayLabel(msg.created_at || '');
            if (label && label !== lastDay) {
                html += `<div class="chat-day"><span>${escapeHtml(label)}</span></div>`;
                lastDay = label;
            }
            html += messageHtml(msg);
        });
        return html;
    };

    const renderMessages = (messages, mode = 'replace') => {
        if (!el.messages) return;
        const shouldStick = isAtBottom();
        const clean = Array.isArray(messages) ? messages.filter(Boolean) : [];

        if (mode === 'replace') {
            state.messages.clear();
            state.lastId = 0;
            state.firstId = 0;
            clean.forEach((msg) => {
                state.messages.set(Number(msg.id), msg);
                state.lastId = Math.max(state.lastId, Number(msg.id));
                state.firstId = state.firstId === 0 ? Number(msg.id) : Math.min(state.firstId, Number(msg.id));
            });
            el.messages.innerHTML = clean.length ? addDaySeparators(clean) : emptyHtml();
            state.loadedOnce = true;
            el.app?.classList.add('is-loaded');
            requestAnimationFrame(() => requestAnimationFrame(() => scrollToBottom('auto')));
            return;
        }

        let html = '';
        clean.forEach((msg) => {
            const id = Number(msg.id);
            if (state.messages.has(id)) return;
            state.messages.set(id, msg);
            state.lastId = Math.max(state.lastId, id);
            state.firstId = state.firstId === 0 ? id : Math.min(state.firstId, id);
            html += messageHtml(msg);
        });
        if (!html) return;

        if (mode === 'append') {
            if (el.messages.querySelector('.chat-empty')) el.messages.innerHTML = '';
            el.messages.insertAdjacentHTML('beforeend', html);
            if (shouldStick) {
                requestAnimationFrame(() => scrollToBottom('smooth'));
            } else {
                state.unread += clean.filter((m) => !m.is_mine).length;
                updateFloatingButtons();
                playNotification(clean);
            }
            return;
        }

        if (mode === 'prepend') {
            const oldHeight = el.messages.scrollHeight;
            el.messages.insertAdjacentHTML('afterbegin', html);
            el.messages.scrollTop += el.messages.scrollHeight - oldHeight;
        }
    };

    const replaceMessage = (msg) => {
        if (!msg) return;
        state.messages.set(Number(msg.id), msg);
        const current = document.getElementById(`msg-${msg.id}`);
        if (!current) return renderMessages([msg], 'append');
        current.outerHTML = messageHtml(msg);
    };

    const playNotification = (messages) => {
        if (!state.soundEnabled || !el.notificationSound) return;
        if (!messages.some((m) => !m.is_mine)) return;
        el.notificationSound.currentTime = 0;
        el.notificationSound.play().catch(() => {});
    };

    const loadInitial = async ({ silent = false } = {}) => {
        if (!cfg.endpoints?.messages || state.loading) return;
        state.loading = true;
        setSyncState('loading', 'Sync');
        if (!silent && !state.loadedOnce && el.messages) {
            el.messages.innerHTML = '<div class="chat-loading"><span></span><span></span><span></span></div>';
        }
        try {
            const url = `${cfg.endpoints.messages}?limit=40${state.search ? `&search=${encodeURIComponent(state.search)}` : ''}`;
            const data = await api(url);
            renderMessages(data.messages || [], 'replace');
            updateStatus(data);
            if (el.loadOlder) el.loadOlder.hidden = (data.messages || []).length < 40;
            setSyncState('ok', 'Live');
        } catch (error) {
            if (el.messages) el.messages.innerHTML = `<div class="chat-empty"><div class="chat-empty-inner"><i class="fas fa-triangle-exclamation"></i><strong>Errore chat</strong><p>${escapeHtml(error.message)}</p></div></div>`;
            setSyncState('error', 'Errore');
        } finally {
            state.loading = false;
        }
    };

    const pollNew = async () => {
        if (state.loading || state.search) return;
        try {
            const data = await api(`${cfg.endpoints.messages}?after_id=${state.lastId}&limit=80`);
            const messages = (data.messages || []).filter((m) => !state.messages.has(Number(m.id)));
            if (messages.length) renderMessages(messages, 'append');
            updateStatus(data);
            setSyncState('ok', 'Live');
        } catch (error) {
            setSyncState('error', 'Offline');
            console.debug('Poll chat:', error.message);
        }
    };

    const loadOlder = async () => {
        if (state.olderLoading || !state.firstId) return;
        state.olderLoading = true;
        if (el.loadOlder) el.loadOlder.disabled = true;
        try {
            const url = `${cfg.endpoints.messages}?before_id=${state.firstId}&limit=30${state.search ? `&search=${encodeURIComponent(state.search)}` : ''}`;
            const data = await api(url);
            const messages = (data.messages || []).filter((m) => !state.messages.has(Number(m.id)));
            if (messages.length) renderMessages(messages, 'prepend');
            if (el.loadOlder) el.loadOlder.hidden = messages.length < 30;
        } catch (error) {
            showToast(error.message);
        } finally {
            state.olderLoading = false;
            if (el.loadOlder) el.loadOlder.disabled = false;
        }
    };

    const updateStatus = (data) => {
        if (el.online && typeof data.online_count !== 'undefined') el.online.textContent = data.online_count;
        updateTyping(data.typing || []);
    };

    const updateTyping = (typing) => {
        if (!el.typing) return;
        if (!typing.length) {
            el.typing.hidden = true;
            el.typing.textContent = '';
            return;
        }
        const names = typing.map((u) => `@${u.username}`).join(', ');
        el.typing.textContent = `${names} sta scrivendo...`;
        el.typing.hidden = false;
    };

    const sendTyping = async (typing) => {
        if (!cfg.endpoints?.typing) return;
        try { await api(cfg.endpoints.typing, { method: 'POST', body: JSON.stringify({ typing, csrf: cfg.csrf }) }); } catch (_) {}
    };

    const setSending = (sending) => {
        if (el.send) el.send.disabled = sending;
    };

    const clearReply = () => {
        state.replyTo = null;
        if (el.replyPreview) el.replyPreview.hidden = true;
    };

    const clearEdit = () => {
        state.editing = null;
        if (el.input) {
            el.input.value = '';
            autoResize();
        }
        if (el.editPreview) el.editPreview.hidden = true;
    };

    const makePendingBase = (nonce, message, extra = {}) => ({
        id: `tmp-${nonce}`,
        user_id: cfg.userId,
        username: cfg.username,
        profile_url: `/u/${encodeURIComponent(cfg.username || '')}`,
        avatar_url: `/includes/get_pfp.php?id=${cfg.userId}`,
        role_badge: cfg.role === 'owner' ? { label: 'Owner', class: 'owner' } : (cfg.role === 'admin' ? { label: 'Admin', class: 'admin' } : null),
        message,
        created_at: new Date().toISOString(),
        created_label: 'ora',
        is_mine: true,
        is_deleted: false,
        can_edit: false,
        can_delete: false,
        reactions: [],
        reply: state.replyTo ? { id: state.replyTo.id, username: state.replyTo.username, message: state.replyTo.message, message_type: state.replyTo.message_type || 'text' } : null,
        ...extra
    });

    const appendPending = (pendingMessage) => {
        if (el.messages?.querySelector('.chat-empty')) el.messages.innerHTML = '';
        el.messages?.insertAdjacentHTML('beforeend', messageHtml(pendingMessage, true));
        scrollToBottom('smooth');
    };

    const removePending = (tempId) => {
        document.getElementById(`msg-${CSS.escape(tempId)}`)?.remove();
    };

    const markPendingError = (tempId) => {
        const node = document.getElementById(`msg-${CSS.escape(tempId)}`);
        node?.classList.remove('is-pending');
        node?.classList.add('is-error');
    };

    const sendTextMessage = async (event) => {
        event.preventDefault();
        const message = (el.input?.value || '').trim();
        if (!message) return;

        if (state.editing) {
            await updateMessage(state.editing.id, message);
            return;
        }

        const nonce = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        const pending = makePendingBase(nonce, message, { message_type: 'text' });
        appendPending(pending);
        el.input.value = '';
        autoResize();
        clearReply();
        setSending(true);

        try {
            const data = await api(cfg.endpoints.send, {
                method: 'POST',
                body: JSON.stringify({ type: 'text', message, reply_to: pending.reply?.id || null, client_nonce: nonce, csrf: cfg.csrf })
            });
            removePending(pending.id);
            if (data.message) renderMessages([data.message], 'append');
        } catch (error) {
            markPendingError(pending.id);
            showToast(error.message);
        } finally {
            setSending(false);
            sendTyping(false);
        }
    };

    const sendGif = async (gif) => {
        if (!gif?.url || state.editing) return;
        const caption = (el.input?.value || '').trim();
        const nonce = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        const pending = makePendingBase(nonce, caption, {
            message_type: 'gif',
            media_url: gif.url,
            media_preview_url: gif.preview_url || gif.url,
            media_title: gif.title || 'GIF'
        });

        appendPending(pending);
        el.input.value = '';
        autoResize();
        closeGifPanel();
        clearReply();
        setSending(true);

        try {
            const data = await api(cfg.endpoints.send, {
                method: 'POST',
                body: JSON.stringify({
                    type: 'gif',
                    message: caption,
                    media_url: gif.url,
                    media_preview_url: gif.preview_url || gif.url,
                    media_title: gif.title || 'GIF',
                    reply_to: pending.reply?.id || null,
                    client_nonce: nonce,
                    csrf: cfg.csrf
                })
            });
            removePending(pending.id);
            if (data.message) renderMessages([data.message], 'append');
        } catch (error) {
            markPendingError(pending.id);
            showToast(error.message);
        } finally {
            setSending(false);
        }
    };

    const updateMessage = async (id, message) => {
        setSending(true);
        try {
            const data = await api(cfg.endpoints.edit, { method: 'POST', body: JSON.stringify({ id, message, csrf: cfg.csrf }) });
            if (data.message) replaceMessage(data.message);
            clearEdit();
            showToast('Messaggio modificato.');
        } catch (error) {
            showToast(error.message);
        } finally {
            setSending(false);
        }
    };

    const setReply = (msg) => {
        state.replyTo = { id: Number(msg.id), username: msg.username, message: msg.message, message_type: msg.message_type || 'text' };
        if (!el.replyPreview) return;
        el.replyUsername.textContent = `@${msg.username}`;
        el.replyText.textContent = msg.message;
        el.replyPreview.hidden = false;
        el.input?.focus();
    };

    const setEdit = (msg) => {
        state.editing = { id: Number(msg.id), message: msg.message };
        if (el.input) {
            el.input.value = msg.message;
            autoResize();
            el.input.focus();
        }
        clearReply();
        if (el.editPreview) el.editPreview.hidden = false;
    };

    const getMessageFromNode = (node) => {
        const article = node.closest('.chat-message');
        if (!article) return null;
        return {
            id: Number(article.dataset.id),
            user_id: Number(article.dataset.userId),
            username: article.dataset.username || 'utente',
            message: article.dataset.message || '',
            message_type: article.querySelector('.chat-gif-message') ? 'gif' : 'text'
        };
    };

    const closeReactionPopover = () => {
        state.reactionPopover?.remove();
        state.reactionPopover = null;
    };

    const reactToMessage = async (msgId, emoji) => {
        try {
            const data = await api(cfg.endpoints.react, { method: 'POST', body: JSON.stringify({ id: msgId, emoji, csrf: cfg.csrf }) });
            if (data.message) replaceMessage(data.message);
        } catch (error) {
            showToast(error.message);
        }
    };

    const openReactionPopover = (button, msg) => {
        closeReactionPopover();
        const emojis = ['😭','🙏','🔥','💀','💯','😂','❤️','👍','👀','🗣️'];
        const box = document.createElement('div');
        box.className = 'chat-reaction-popover';
        box.innerHTML = emojis.map((emoji) => `<button type="button" data-emoji="${emoji}">${emoji}</button>`).join('');
        document.body.appendChild(box);

        const rect = button.getBoundingClientRect();
        const left = Math.min(Math.max(8, rect.left + rect.width / 2 - 140), window.innerWidth - 288);
        const top = Math.max(8, rect.top - 52);
        box.style.left = `${left}px`;
        box.style.top = `${top}px`;

        box.addEventListener('click', async (event) => {
            const emoji = event.target.closest('[data-emoji]')?.dataset.emoji;
            if (!emoji) return;
            closeReactionPopover();
            await reactToMessage(msg.id, emoji);
        });
        state.reactionPopover = box;
    };

    const onMessageAction = async (event) => {
        const reactionButton = event.target.closest('[data-action="quick-react"]');
        if (reactionButton) {
            const msg = getMessageFromNode(reactionButton);
            if (msg) await reactToMessage(msg.id, reactionButton.dataset.emoji);
            return;
        }

        const button = event.target.closest('[data-action]');
        if (!button) return;
        const action = button.dataset.action;
        const msg = getMessageFromNode(button);
        if (!msg) return;

        if (action === 'reply') return setReply(msg);
        if (action === 'edit') return setEdit(msg);
        if (action === 'react') return openReactionPopover(button, msg);
        if (action === 'copy') {
            try {
                await navigator.clipboard.writeText(msg.message);
                showToast('Messaggio copiato.');
            } catch (_) {
                showToast('Copia non riuscita.');
            }
            return;
        }
        if (action === 'delete') {
            if (!confirm('Eliminare questo messaggio?')) return;
            try {
                await api(cfg.endpoints.delete, { method: 'POST', body: JSON.stringify({ id: msg.id, csrf: cfg.csrf }) });
                const current = state.messages.get(msg.id);
                replaceMessage({ ...(current || msg), message: 'Messaggio eliminato', is_deleted: true, deleted_at: new Date().toISOString(), can_delete: false, can_edit: false, reactions: [] });
                showToast('Messaggio eliminato.');
            } catch (error) {
                showToast(error.message);
            }
            return;
        }
        if (action === 'report') {
            const reason = prompt('Motivo della segnalazione?', 'Messaggio inappropriato');
            if (reason === null) return;
            try {
                const data = await api(cfg.endpoints.report, { method: 'POST', body: JSON.stringify({ id: msg.id, reason, csrf: cfg.csrf }) });
                showToast(data.message || 'Segnalazione inviata.');
            } catch (error) {
                showToast(error.message);
            }
            return;
        }
        if (action === 'mute') {
            if (!confirm(`Mutare @${msg.username}? Non vedrai più i suoi messaggi.`)) return;
            try {
                await api(cfg.endpoints.mute, { method: 'POST', body: JSON.stringify({ user_id: msg.user_id, muted: true, csrf: cfg.csrf }) });
                document.querySelectorAll(`.chat-message[data-user-id="${msg.user_id}"]`).forEach((item) => item.remove());
                showToast(`@${msg.username} mutato.`);
            } catch (error) {
                showToast(error.message);
            }
        }
    };

    const runSearch = async () => {
        const value = (el.searchInput?.value || '').trim();
        state.search = value;
        await loadInitial({ silent: true });
    };

    const clearSearch = async () => {
        clearTimeout(state.searchTimer);
        if (el.searchInput) el.searchInput.value = '';
        state.search = '';
        if (el.searchBox) el.searchBox.hidden = true;
        await loadInitial({ silent: true });
    };

    const debounceSearch = () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(runSearch, 360);
    };

    const insertAtCursor = (text) => {
        if (!el.input) return;
        const start = el.input.selectionStart ?? el.input.value.length;
        const end = el.input.selectionEnd ?? el.input.value.length;
        const before = el.input.value.slice(0, start);
        const after = el.input.value.slice(end);
        el.input.value = `${before}${text}${after}`;
        const next = start + text.length;
        el.input.setSelectionRange(next, next);
        el.input.focus();
        autoResize();
    };

    const renderGifGrid = (gifs, append = false) => {
        if (!el.gifGrid) return;
        if (!append) el.gifGrid.innerHTML = '';
        if (!gifs.length && !append) {
            el.gifGrid.innerHTML = '<div class="chat-gif-empty">Nessuna GIF trovata.</div>';
            return;
        }
        const html = gifs.map((gif) => `<button type="button" class="chat-gif-item" data-gif='${escapeHtml(JSON.stringify(gif))}' title="${escapeHtml(gif.title || 'GIF')}"><img src="${escapeHtml(gif.preview_url)}" alt="${escapeHtml(gif.title || 'GIF')}" loading="lazy"></button>`).join('');
        el.gifGrid.insertAdjacentHTML('beforeend', html);
    };

    const loadGifs = async ({ append = false } = {}) => {
        if (!cfg.endpoints?.gifs || state.gifLoading) return;
        state.gifLoading = true;
        if (!append && el.gifGrid) el.gifGrid.innerHTML = '<div class="chat-gif-loading"><span></span><span></span><span></span></div>';
        if (el.gifMore) el.gifMore.hidden = true;

        try {
            const params = new URLSearchParams();
            if (state.gifQuery) params.set('q', state.gifQuery);
            if (append && state.gifNext) params.set('pos', state.gifNext);
            const data = await api(`${cfg.endpoints.gifs}?${params.toString()}`);
            renderGifGrid(data.gifs || [], append);
            state.gifNext = data.next || '';
            if (el.gifMore) el.gifMore.hidden = !state.gifNext;
        } catch (error) {
            if (el.gifGrid) el.gifGrid.innerHTML = `<div class="chat-gif-empty">${escapeHtml(error.message)}</div>`;
        } finally {
            state.gifLoading = false;
        }
    };

    const openGifPanel = async () => {
        if (!el.gifPanel) return;
        state.gifOpen = true;
        if (el.emojiStrip) el.emojiStrip.hidden = true;
        el.emojiButton?.classList.remove('is-active');
        el.gifPanel.hidden = false;
        el.gifButton?.classList.add('is-active');
        el.gifSearch?.focus();
        if (!el.gifGrid?.children.length) await loadGifs();
    };

    const closeGifPanel = () => {
        state.gifOpen = false;
        if (el.gifPanel) el.gifPanel.hidden = true;
        el.gifButton?.classList.remove('is-active');
    };

    const syncSoundButton = () => {
        if (!el.soundButton) return;
        const icon = el.soundButton.querySelector('i');
        if (icon) icon.className = state.soundEnabled ? 'fas fa-volume-high' : 'fas fa-volume-xmark';
        el.soundButton.classList.toggle('is-active', state.soundEnabled);
    };

    const initEvents = () => {
        el.form?.addEventListener('submit', sendTextMessage);
        el.input?.addEventListener('input', () => {
            autoResize();
            clearTimeout(state.stopTypingTimer);
            if (!state.typingTimer) {
                sendTyping(true);
                state.typingTimer = setTimeout(() => { state.typingTimer = null; }, 1600);
            }
            state.stopTypingTimer = setTimeout(() => sendTyping(false), 2200);
        });

        el.input?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                el.form?.requestSubmit();
            }
            if (event.key === 'Escape') {
                clearReply();
                clearEdit();
                closeGifPanel();
                closeReactionPopover();
            }
        });

        el.messages?.addEventListener('click', onMessageAction);
        el.messages?.addEventListener('click', toggleMobileMessageActions);
        el.messages?.addEventListener('load', keepBottomAfterMediaLoad, true);
        el.messages?.addEventListener('click', (event) => {
            const target = event.target.closest('[data-scroll-message]');
            if (!target) return;
            event.preventDefault();
            const id = target.dataset.scrollMessage;
            const message = document.getElementById(`msg-${id}`);
            if (message) {
                message.scrollIntoView({ behavior: 'smooth', block: 'center' });
                message.classList.add('is-actions-open');
                setTimeout(() => message.classList.remove('is-actions-open'), 1600);
            }
        });

        el.messages?.addEventListener('scroll', updateFloatingButtons, { passive: true });
        el.scrollBottom?.addEventListener('click', () => scrollToBottom('smooth'));
        el.newMessages?.addEventListener('click', () => scrollToBottom('smooth'));
        el.loadOlder?.addEventListener('click', loadOlder);
        document.querySelector('.js-cancel-reply')?.addEventListener('click', clearReply);
        document.querySelector('.js-cancel-edit')?.addEventListener('click', clearEdit);

        el.searchButton?.addEventListener('click', () => {
            if (!el.searchBox) return;
            el.searchBox.hidden = !el.searchBox.hidden;
            if (!el.searchBox.hidden) el.searchInput?.focus();
        });
        el.searchInput?.addEventListener('input', debounceSearch);
        document.querySelector('.js-clear-search')?.addEventListener('click', (event) => {
            event.preventDefault();
            clearSearch();
        });

        el.soundButton?.addEventListener('click', () => {
            state.soundEnabled = !state.soundEnabled;
            localStorage.setItem('cripsum.chat.sound', state.soundEnabled ? 'on' : 'off');
            syncSoundButton();
            showToast(state.soundEnabled ? 'Audio notifiche attivo.' : 'Audio notifiche disattivato.');
        });

        el.gifButton?.addEventListener('click', () => state.gifOpen ? closeGifPanel() : openGifPanel());
        document.querySelector('.js-close-gifs')?.addEventListener('click', closeGifPanel);
        el.gifSearch?.addEventListener('input', () => {
            clearTimeout(state.gifTimer);
            state.gifTimer = setTimeout(() => {
                state.gifQuery = (el.gifSearch.value || '').trim();
                state.gifNext = '';
                loadGifs();
            }, 360);
        });
        el.gifMore?.addEventListener('click', () => loadGifs({ append: true }));
        el.gifGrid?.addEventListener('click', (event) => {
            const button = event.target.closest('.chat-gif-item');
            if (!button) return;
            try {
                const gif = JSON.parse(button.dataset.gif || '{}');
                sendGif(gif);
            } catch (_) {
                showToast('GIF non valida.');
            }
        });

        el.emojiButton?.addEventListener('click', () => {
            if (!el.emojiStrip) return;
            const willOpen = el.emojiStrip.hidden;
            if (willOpen) closeGifPanel();
            el.emojiStrip.hidden = !willOpen;
            el.emojiButton.classList.toggle('is-active', willOpen);
        });
        el.emojiStrip?.addEventListener('click', (event) => {
            const emoji = event.target.closest('[data-emoji]')?.dataset.emoji;
            if (emoji) insertAtCursor(emoji);
        });

        document.addEventListener('click', (event) => {
            if (state.reactionPopover && !event.target.closest('.chat-reaction-popover') && !event.target.closest('[data-action="react"]')) {
                closeReactionPopover();
            }
        });

        document.addEventListener('keydown', (event) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                if (el.searchBox) el.searchBox.hidden = false;
                el.searchInput?.focus();
            }
        });

        window.addEventListener('resize', setViewportHeight, { passive: true });
        window.visualViewport?.addEventListener('resize', setViewportHeight, { passive: true });
        window.addEventListener('orientationchange', () => setTimeout(setViewportHeight, 250), { passive: true });
        window.addEventListener('beforeunload', () => sendTyping(false));
    };

    const startPolling = () => {
        clearInterval(state.pollingTimer);
        state.pollingTimer = setInterval(pollNew, Math.max(1600, Number(cfg.refreshInterval || 2500)));
    };

    const init = () => {
        if (!el.app || !el.messages) return;
        setViewportHeight();
        document.body.classList.add('chat-app-ready');
        syncSoundButton();
        autoResize();
        initEvents();
        loadInitial();
        startPolling();
    };

    document.addEventListener('DOMContentLoaded', init);
})();
