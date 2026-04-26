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
        pending: new Map(),
        soundEnabled: localStorage.getItem('cripsum.chat.sound') === 'on',
        pollingTimer: null,
        typingTimer: null,
        stopTypingTimer: null,
        toastTimer: null,
        unread: 0
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
        notificationSound: document.getElementById('notificationSound')
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

    const linkify = (text) => {
        const escaped = escapeHtml(text);
        return escaped.replace(/(https?:\/\/[^\s<]+)/gi, (url) => {
            const clean = url.replace(/[.,!?;:)]+$/g, '');
            const tail = url.slice(clean.length);
            return `<a href="${clean}" target="_blank" rel="noopener noreferrer">${clean}</a>${escapeHtml(tail)}`;
        });
    };

    const showToast = (message) => {
        if (!el.toast) return;
        el.toast.textContent = message;
        el.toast.classList.add('is-visible');
        clearTimeout(state.toastTimer);
        state.toastTimer = setTimeout(() => el.toast.classList.remove('is-visible'), 2400);
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
        if (!el.scrollBottom || !el.newMessages) return;
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
        const date = new Date(dateString.replace(' ', 'T'));
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
        const actions = [actionButton('reply', 'fas fa-reply', 'Rispondi')];
        actions.push(actionButton('copy', 'fas fa-copy', 'Copia'));
        if (msg.can_edit) actions.push(actionButton('edit', 'fas fa-pen', 'Modifica'));
        if (msg.can_delete) actions.push(actionButton('delete', 'fas fa-trash', 'Elimina', 'is-danger'));
        if (msg.can_report) actions.push(actionButton('report', 'fas fa-flag', 'Segnala'));
        if (msg.can_report) actions.push(actionButton('mute', 'fas fa-user-slash', 'Muta utente'));
        return `<div class="chat-message-actions">${actions.join('')}</div>`;
    };

    const messageHtml = (msg, pending = false, error = false) => {
        const mine = msg.is_mine || Number(msg.user_id) === Number(cfg.userId);
        const deleted = msg.is_deleted;
        const classes = [
            'chat-message',
            mine ? 'is-mine' : '',
            deleted ? 'is-deleted' : '',
            pending ? 'is-pending' : '',
            error ? 'is-error' : ''
        ].filter(Boolean).join(' ');

        const reply = msg.reply ? `
            <a class="chat-reply-card" href="#msg-${msg.reply.id}" data-scroll-message="${msg.reply.id}">
                <strong>@${escapeHtml(msg.reply.username)}</strong>
                <span>${escapeHtml(msg.reply.message || 'Messaggio')}</span>
            </a>` : '';

        const edited = msg.edited_at && !deleted ? '<span class="chat-edited">modificato</span>' : '';
        const body = deleted ? '<em>Messaggio eliminato</em>' : linkify(msg.message || '');

        const avatar = `
            <a class="chat-avatar-link" href="${escapeHtml(msg.profile_url || '#')}" tabindex="-1">
                <img src="${escapeHtml(msg.avatar_url || '/img/abdul.jpg')}" alt="">
            </a>`;

        const main = `
            <div class="chat-message-main">
                <div class="chat-message-meta">
                    <a class="chat-username" href="${escapeHtml(msg.profile_url || '#')}">@${escapeHtml(msg.username || 'utente')}</a>
                    ${roleBadgeHtml(msg.role_badge)}
                    ${profileBadgeHtml(msg.badge)}
                    <time class="chat-time" datetime="${escapeHtml(msg.created_at || '')}">${escapeHtml(msg.created_label || '')}${edited}</time>
                </div>
                <div class="chat-bubble-wrap">
                    <div class="chat-bubble">${reply}${body}</div>
                    ${messageActionsHtml(msg)}
                </div>
            </div>`;

        return `<article class="${classes}" id="msg-${msg.id}" data-id="${msg.id}" data-user-id="${msg.user_id}" data-message="${escapeHtml(msg.message || '')}" data-username="${escapeHtml(msg.username || 'utente')}">${mine ? main + avatar : avatar + main}</article>`;
    };

    const renderMessages = (messages, mode = 'replace') => {
        if (!el.messages) return;
        const shouldStick = isAtBottom();
        let html = '';
        let lastDay = null;

        if (mode === 'replace') {
            el.messages.innerHTML = '';
            state.messages.clear();
            state.lastId = 0;
            state.firstId = 0;
        }

        messages.forEach((msg) => {
            state.messages.set(Number(msg.id), msg);
            state.lastId = Math.max(state.lastId, Number(msg.id));
            state.firstId = state.firstId === 0 ? Number(msg.id) : Math.min(state.firstId, Number(msg.id));

            const label = dayLabel(msg.created_at || '');
            if (mode === 'replace' && label && label !== lastDay) {
                html += `<div class="chat-day"><span>${escapeHtml(label)}</span></div>`;
                lastDay = label;
            }
            html += messageHtml(msg);
        });

        if (mode === 'replace') {
            el.messages.innerHTML = html || emptyHtml();
            requestAnimationFrame(() => scrollToBottom('auto'));
            return;
        }

        if (mode === 'append') {
            if (el.messages.querySelector('.chat-empty')) el.messages.innerHTML = '';
            el.messages.insertAdjacentHTML('beforeend', html);
            if (shouldStick) {
                requestAnimationFrame(() => scrollToBottom('smooth'));
            } else if (messages.length) {
                state.unread += messages.filter((m) => !m.is_mine).length;
                updateFloatingButtons();
                playNotification(messages);
            }
        }

        if (mode === 'prepend') {
            const oldHeight = el.messages.scrollHeight;
            el.messages.insertAdjacentHTML('afterbegin', html);
            const delta = el.messages.scrollHeight - oldHeight;
            el.messages.scrollTop += delta;
        }
    };

    const emptyHtml = () => `
        <div class="chat-empty">
            <div class="chat-empty-inner">
                <i class="fas fa-comments"></i>
                <strong>Nessun messaggio</strong>
                <p>Scrivi il primo.</p>
            </div>
        </div>`;

    const playNotification = (messages) => {
        if (!state.soundEnabled || !el.notificationSound) return;
        if (!messages.some((m) => !m.is_mine)) return;
        el.notificationSound.currentTime = 0;
        el.notificationSound.play().catch(() => {});
    };

    const loadInitial = async () => {
        if (!cfg.endpoints?.messages || state.loading) return;
        state.loading = true;
        try {
            const data = await api(`${cfg.endpoints.messages}?limit=40`);
            renderMessages(data.messages || [], 'replace');
            updateStatus(data);
            if (el.loadOlder) el.loadOlder.hidden = (data.messages || []).length < 40;
        } catch (error) {
            el.messages.innerHTML = `<div class="chat-empty"><div class="chat-empty-inner"><i class="fas fa-triangle-exclamation"></i><strong>Errore chat</strong><p>${escapeHtml(error.message)}</p></div></div>`;
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
        } catch (error) {
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
        try {
            await api(cfg.endpoints.typing, { method: 'POST', body: JSON.stringify({ typing, csrf: cfg.csrf }) });
        } catch (_) {}
    };

    const sendMessage = async (event) => {
        event.preventDefault();
        const message = (el.input?.value || '').trim();
        if (!message) return;

        const nonce = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        const tempId = `tmp-${nonce}`;
        const pendingMessage = {
            id: tempId,
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
            reply: state.replyTo ? { id: state.replyTo.id, username: state.replyTo.username, message: state.replyTo.message } : null
        };

        if (state.editing) {
            await updateMessage(state.editing.id, message);
            return;
        }

        if (el.messages.querySelector('.chat-empty')) el.messages.innerHTML = '';
        el.messages.insertAdjacentHTML('beforeend', messageHtml(pendingMessage, true));
        scrollToBottom('smooth');
        el.input.value = '';
        autoResize();
        clearReply();
        setSending(true);

        try {
            const data = await api(cfg.endpoints.send, {
                method: 'POST',
                body: JSON.stringify({ message, reply_to: pendingMessage.reply?.id || null, client_nonce: nonce, csrf: cfg.csrf })
            });
            const pendingEl = document.getElementById(`msg-${CSS.escape(tempId)}`);
            pendingEl?.remove();
            if (data.message) renderMessages([data.message], 'append');
            showToast('Messaggio inviato.');
        } catch (error) {
            const node = document.getElementById(`msg-${CSS.escape(tempId)}`);
            node?.classList.remove('is-pending');
            node?.classList.add('is-error');
            showToast(error.message);
        } finally {
            setSending(false);
            sendTyping(false);
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

    const replaceMessage = (msg) => {
        state.messages.set(Number(msg.id), msg);
        const current = document.getElementById(`msg-${msg.id}`);
        if (!current) return renderMessages([msg], 'append');
        current.outerHTML = messageHtml(msg);
    };

    const setSending = (sending) => {
        if (el.send) el.send.disabled = sending;
    };

    const setReply = (msg) => {
        state.replyTo = { id: Number(msg.id), username: msg.username, message: msg.message };
        if (!el.replyPreview) return;
        el.replyUsername.textContent = `@${msg.username}`;
        el.replyText.textContent = msg.message;
        el.replyPreview.hidden = false;
        el.input?.focus();
    };

    const clearReply = () => {
        state.replyTo = null;
        if (el.replyPreview) el.replyPreview.hidden = true;
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

    const clearEdit = () => {
        state.editing = null;
        if (el.input) {
            el.input.value = '';
            autoResize();
        }
        if (el.editPreview) el.editPreview.hidden = true;
    };

    const getMessageFromNode = (node) => {
        const article = node.closest('.chat-message');
        if (!article) return null;
        return {
            id: Number(article.dataset.id),
            user_id: Number(article.dataset.userId),
            username: article.dataset.username || 'utente',
            message: article.dataset.message || ''
        };
    };

    const onMessageAction = async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const action = button.dataset.action;
        const msg = getMessageFromNode(button);
        if (!msg) return;

        if (action === 'reply') return setReply(msg);
        if (action === 'edit') return setEdit(msg);
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
                replaceMessage({ ...(current || msg), message: 'Messaggio eliminato', is_deleted: true, deleted_at: new Date().toISOString(), can_delete: false, can_edit: false });
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
        state.search = (el.searchInput?.value || '').trim();
        state.loading = true;
        el.messages.innerHTML = '<div class="chat-loading"><span></span><span></span><span></span></div>';
        try {
            const url = `${cfg.endpoints.messages}?limit=60${state.search ? `&search=${encodeURIComponent(state.search)}` : ''}`;
            const data = await api(url);
            renderMessages(data.messages || [], 'replace');
            updateStatus(data);
        } catch (error) {
            showToast(error.message);
        } finally {
            state.loading = false;
        }
    };

    const debounce = (fn, wait = 350) => {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), wait);
        };
    };

    const initEvents = () => {
        el.form?.addEventListener('submit', sendMessage);
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
            }
        });

        el.messages?.addEventListener('click', onMessageAction);
        el.messages?.addEventListener('click', (event) => {
            const target = event.target.closest('[data-scroll-message]');
            if (!target) return;
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
        el.searchInput?.addEventListener('input', debounce(runSearch, 420));
        document.querySelector('.js-clear-search')?.addEventListener('click', () => {
            if (el.searchInput) el.searchInput.value = '';
            state.search = '';
            runSearch();
        });

        el.soundButton?.addEventListener('click', () => {
            state.soundEnabled = !state.soundEnabled;
            localStorage.setItem('cripsum.chat.sound', state.soundEnabled ? 'on' : 'off');
            syncSoundButton();
            showToast(state.soundEnabled ? 'Audio notifiche attivo.' : 'Audio notifiche disattivato.');
        });

        window.addEventListener('beforeunload', () => sendTyping(false));
    };

    const syncSoundButton = () => {
        if (!el.soundButton) return;
        const icon = el.soundButton.querySelector('i');
        if (icon) icon.className = state.soundEnabled ? 'fas fa-volume-high' : 'fas fa-volume-xmark';
        el.soundButton.classList.toggle('is-active', state.soundEnabled);
    };

    const startPolling = () => {
        clearInterval(state.pollingTimer);
        state.pollingTimer = setInterval(pollNew, Math.max(1600, Number(cfg.refreshInterval || 2500)));
    };

    const init = () => {
        if (!el.app || !el.messages) return;
        syncSoundButton();
        autoResize();
        initEvents();
        loadInitial();
        startPolling();
    };

    document.addEventListener('DOMContentLoaded', init);
})();
