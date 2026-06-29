/* assets/chat/chat.js - Premium Private Chat SPA Engine */

(function () {
    const state = {
        currentConversationId: 0,
        lastMessageId: 0,
        recipientId: 0,
        isTyping: false,
        typingTimer: null,
        pollTimer: null,
        activeTab: 'active', // 'active' o 'archived'
        conversations: [],
        messages: [],
        replyToId: null,
        editMessageId: null,
        ephemeralTimer: 0, // 0 = disattivato
        isDetailsOpen: false
    };

    // DOM Elements Cache
    const $ = selector => document.querySelector(selector);
    const $$ = selector => document.querySelectorAll(selector);

    // Inizializzazione
    document.addEventListener('DOMContentLoaded', () => {
        init();
    });

    function init() {
        loadConversations();
        setupEventListeners();
        startPolling();
        requestNotificationPermission();

        // Controlla se c'è un destinatario o un ticket nell'URL (per aprire la chat direttamente)
        const urlParams = new URLSearchParams(window.location.search);
        const startUser = urlParams.get('user_id');
        if (startUser) {
            startNewChat(parseInt(startUser));
        }
    }

    // --- EVENT LISTENERS ---
    function setupEventListeners() {
        // Tasti tab (Attive / Archiviate)
        $('#tab-active')?.addEventListener('click', () => switchTab('active'));
        $('#tab-archived')?.addEventListener('click', () => switchTab('archived'));

        // Ricerca conversazioni / utenti
        $('#chatSearchInput')?.addEventListener('input', debounce((e) => {
            performSearch(e.target.value.trim());
        }, 300));

        // Area di input testo (invio con Invio, a capo con Maiusc+Invio, digitazione)
        const textarea = $('#chatTextarea');
        if (textarea) {
            textarea.addEventListener('keydown', handleTextareaKeydown);
            textarea.addEventListener('input', handleTextareaInput);
        }

        // Tasto invio messaggio
        $('#chatSendBtn')?.addEventListener('click', () => sendMessage());

        // Gestione drag & drop per allegati
        const chatArea = $('.chat-area');
        if (chatArea) {
            chatArea.addEventListener('dragover', handleDragOver);
            chatArea.addEventListener('dragleave', handleDragLeave);
            chatArea.addEventListener('drop', handleDrop);
        }

        // Tasto allegato (File input nascosto)
        $('#chatAttachBtn')?.addEventListener('click', () => $('#chatFileInput')?.click());
        $('#chatFileInput')?.addEventListener('change', handleFileSelect);

        // Tasto Info (Mostra/Nascondi dettagli a destra)
        $('#chatInfoBtn')?.addEventListener('click', toggleDetails);
        $('#chatDetailsCloseBtn')?.addEventListener('click', toggleDetails);

        // Tasto Indietro (Mobile)
        $('#chatBackBtn')?.addEventListener('click', () => {
            $('.chat-shell').classList.remove('is-chat-open');
            state.currentConversationId = 0;
            state.lastMessageId = 0;
            stopActiveChat();
        });

        // Chiudi menu contestuale cliccando altrove
        document.addEventListener('click', () => {
            $('#chatContextMenu').style.display = 'none';
        });

        // Annulla risposte o modifiche
        $('#cancelReplyBtn')?.addEventListener('click', cancelReply);
    }

    function switchTab(tab) {
        state.activeTab = tab;
        $('#tab-active')?.classList.toggle('is-active', tab === 'active');
        $('#tab-archived')?.classList.toggle('is-active', tab === 'archived');
        loadConversations();
    }

    // --- CARICAMENTO CONVERSAZIONI ---
    async function loadConversations() {
        try {
            const res = await api(`get_conversations.php?archived=${state.activeTab === 'archived' ? 1 : 0}`);
            if (res.ok) {
                state.conversations = res.conversations || [];
                renderConversations();
            }
        } catch (e) {
            console.error("Errore caricamento chat:", e);
        }
    }

    function renderConversations() {
        const list = $('.chat-list');
        if (!list) return;

        if (state.conversations.length === 0) {
            list.innerHTML = `<div class="text-center py-5 text-muted"><i class="fa-solid fa-comments fs-2 mb-2"></i><br>Nessuna conversazione trovata</div>`;
            return;
        }

        list.innerHTML = state.conversations.map(c => {
            const isActive = c.conversation_id === state.currentConversationId ? 'is-active' : '';
            const isUnread = c.unread_count > 0 ? 'is-unread' : '';
            const onlineClass = c.is_online ? 'is-online' : '';
            const nickname = c.nickname || c.other_username;
            const preview = c.preview_text || 'Nessun messaggio';
            
            // Formatta data
            const time = c.last_message_time ? formatTime(c.last_message_time) : '';

            return `
                <div class="chat-item ${isActive} ${isUnread}" data-id="${c.conversation_id}" onclick="selectConversation(${c.conversation_id})">
                    <div class="chat-item__avatar-container">
                        <img class="chat-item__avatar" src="/includes/get_pfp.php?id=${c.other_user_id}" alt="${nickname}">
                        <span class="chat-item__status ${onlineClass}"></span>
                    </div>
                    <div class="chat-item__info">
                        <div class="chat-item__name-row">
                            <span class="chat-item__name">${escapeHtml(nickname)}</span>
                            <span class="chat-item__time">${time}</span>
                        </div>
                        <div class="chat-item__msg-row">
                            <span class="chat-item__preview">${escapeHtml(preview)}</span>
                            ${c.is_muted ? '<i class="fa-solid fa-bell-slash chat-item__mute"></i>' : ''}
                            ${c.unread_count > 0 ? `<span class="chat-item__badge">${c.unread_count}</span>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    window.selectConversation = selectConversation; // Rendi accessibile a onclick inline

    // --- SELEZIONE CONVERSAZIONE ---
    async function selectConversation(id) {
        state.currentConversationId = id;
        state.lastMessageId = 0;
        state.recipientId = 0;
        cancelReply();

        // Evidenzia elemento attivo nella lista
        $$('.chat-item').forEach(el => {
            el.classList.toggle('is-active', parseInt(el.dataset.id) === id);
            if (parseInt(el.dataset.id) === id) {
                el.classList.remove('is-unread');
                const badge = el.querySelector('.chat-item__badge');
                if (badge) badge.remove();
            }
        });

        // Mostra area chat su mobile
        $('.chat-shell').classList.add('is-chat-open');

        // Renderizza skeleton loading
        renderSkeletonMessages();

        try {
            // Carica messaggi dal server
            const res = await api(`get_messages.php?conversation_id=${id}`);
            if (res.ok) {
                state.messages = res.messages || [];
                if (state.messages.length > 0) {
                    state.lastMessageId = state.messages[state.messages.length - 1].id;
                }
                
                // Configura intestazione chat
                const conv = state.conversations.find(c => c.conversation_id === id);
                if (conv) {
                    setupChatHeader(conv);
                    applyChatCustomization(conv);
                }

                renderMessages();
                
                if (state.isDetailsOpen) {
                    loadDetails();
                }
            }
        } catch (e) {
            console.error("Errore caricamento messaggi:", e);
        }
    }

    function setupChatHeader(conv) {
        const nickname = conv.nickname || conv.other_username;
        $('#chatHeaderName').textContent = nickname;
        const avatar = $('#chatHeaderAvatar');
        avatar.src = `/includes/get_pfp.php?id=${conv.other_user_id}`;
        avatar.style.display = 'block';
        
        let statusText = conv.is_online ? "Online" : (conv.last_seen ? "Ultimo accesso " + formatDateTime(conv.last_seen) : "Offline");
        $('#chatHeaderStatus').textContent = statusText;
        $('#chatHeaderStatus').className = 'chat-area__user-status' + (conv.is_online ? ' is-online' : '');
    }

    function applyChatCustomization(conv) {
        const chatArea = $('.chat-area');
        if (!chatArea) return;
        
        // Applica tema colore
        if (conv.theme_color) {
            document.documentElement.style.setProperty('--chat-accent', conv.theme_color);
        } else {
            document.documentElement.style.removeProperty('--chat-accent');
        }

        // Applica sfondo personalizzato
        if (conv.theme_bg) {
            chatArea.style.backgroundImage = `url(${conv.theme_bg})`;
            chatArea.style.backgroundSize = 'cover';
        } else {
            chatArea.style.backgroundImage = 'none';
        }
    }

    function stopActiveChat() {
        // Disattiva stato
        state.currentConversationId = 0;
        state.lastMessageId = 0;
        $('#chatHeaderName').textContent = "Seleziona una chat";
        const avatar = $('#chatHeaderAvatar');
        avatar.src = "";
        avatar.style.display = 'none';
        $('#chatHeaderStatus').textContent = "";
        $('.chat-messages').innerHTML = `<div class="text-center py-5 text-muted my-auto"><i class="fa-regular fa-paper-plane fs-1 mb-3"></i><br>Scegli un utente per iniziare a chattare.</div>`;
    }

    // --- SKELETON LOADING ---
    function renderSkeletonMessages() {
        const box = $('.chat-messages');
        if (!box) return;
        box.innerHTML = Array(4).fill(0).map((_, idx) => `
            <div class="chat-msg-wrapper ${idx % 2 === 0 ? 'chat-msg-wrapper--recv' : 'chat-msg-wrapper--sent'}" style="width: 250px;">
                <div class="chat-msg-bubble chat-skeleton" style="height: 50px;"></div>
            </div>
        `).join('');
    }

    // --- RENDERING MESSAGGI ---
    function renderMessages(append = false) {
        const box = $('.chat-messages');
        if (!box) return;

        if (state.messages.length === 0) {
            box.innerHTML = `<div class="text-center py-5 text-muted my-auto">Nessun messaggio. Scrivi qualcosa per iniziare!</div>`;
            return;
        }

        let html = '';
        let lastDate = '';

        state.messages.forEach(msg => {
            // Aggiungi separatore di data
            const msgDate = new Date(msg.created_at).toLocaleDateString();
            if (msgDate !== lastDate) {
                html += `<div class="chat-date-separator">${formatDateSeparator(msg.created_at)}</div>`;
                lastDate = msgDate;
            }

            const isSent = msg.sender_id === parseInt(document.body.dataset.adminId || document.body.dataset.userId);
            const wrapperClass = isSent ? 'chat-msg-wrapper--sent' : 'chat-msg-wrapper--recv';
            
            // Gestione messaggi eliminati per tutti
            if (msg.deleted_for_all) {
                html += `
                    <div class="chat-msg-wrapper ${wrapperClass}">
                        <div class="chat-msg-bubble" style="font-style: italic; opacity: 0.5;">
                            🚫 Questo messaggio è stato eliminato
                        </div>
                    </div>
                `;
                return;
            }

            // Gestione risposta
            let replyHtml = '';
            if (msg.reply_to_id && msg.reply_message_text) {
                replyHtml = `
                    <div class="chat-msg-reply-preview" onclick="scrollToMessage(${msg.reply_to_id})">
                        <strong>${escapeHtml(msg.reply_username)}:</strong> ${escapeHtml(msg.reply_message_text)}
                    </div>
                `;
            }

            // Gestione allegati
            let attachmentsHtml = '';
            if (msg.attachments && msg.attachments.length > 0) {
                attachmentsHtml = `<div class="chat-msg-attachments">` + msg.attachments.map(att => {
                    if (att.file_type === 'image') {
                        return `<img class="chat-attachment-image" src="${att.file_path}" alt="${escapeHtml(att.file_name)}" onclick="openMediaViewer('${att.file_path}')">`;
                    } else if (att.file_type === 'video') {
                        return `<video class="chat-attachment-video" src="${att.file_path}" controls></video>`;
                    } else if (att.file_type === 'audio') {
                        return `<audio src="${att.file_path}" controls style="width: 100%; max-width: 250px;"></audio>`;
                    } else {
                        return `
                            <a class="chat-attachment-file" href="${att.file_path}" download="${escapeHtml(att.file_name)}">
                                <i class="fa-solid fa-file-arrow-down chat-attachment-icon"></i>
                                <div>
                                    <div style="font-weight:700;">${escapeHtml(att.file_name)}</div>
                                    <div style="font-size:11px;opacity:0.7;">${formatBytes(att.file_size)}</div>
                                </div>
                            </a>
                        `;
                    }
                }).join('') + `</div>`;
            }

            // Gestione reazioni
            let reactionsHtml = '';
            if (msg.reactions && msg.reactions.length > 0) {
                reactionsHtml = `<div class="chat-msg-reactions">` + msg.reactions.map(r => {
                    const activeClass = r.user_reacted ? 'user-reacted' : '';
                    return `
                        <span class="chat-reaction-badge ${activeClass}" onclick="toggleReaction(${msg.id}, '${r.reaction}')" title="${escapeHtml(r.usernames)}">
                            <span>${r.reaction}</span>
                            <span>${r.count}</span>
                        </span>
                    `;
                }).join('') + `</div>`;
            }

            // Testo con emoji/formatting
            const textHtml = msg.message ? `<div class="chat-msg-text">${parseMessageText(msg.message)}</div>` : '';

            // Meta (orario, spunta lettura)
            const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const statusIcon = isSent ? `<i class="fa-solid fa-check chat-msg-status ${msg.is_read ? 'is-read' : ''}"></i>` : '';
            
            html += `
                <div class="chat-msg-wrapper ${wrapperClass}" id="msg-${msg.id}" oncontextmenu="showContextMenu(event, ${msg.id}, ${isSent})">
                    ${replyHtml}
                    <div class="chat-msg-bubble">
                        ${textHtml}
                        ${attachmentsHtml}
                        ${msg.ephemeral_timer > 0 ? `<div class="chat-msg-ephemeral-timer"><i class="fa-solid fa-hourglass-half"></i> Effimero</div>` : ''}
                    </div>
                    <div class="chat-msg-meta">
                        <span>${time}</span>
                        ${msg.is_edited ? '<span style="font-style:italic;">(modificato)</span>' : ''}
                        ${statusIcon}
                    </div>
                    ${reactionsHtml}
                </div>
            `;
        });

        box.innerHTML = html;
        scrollToBottom();
    }

    // --- INVIO MESSAGGIO ---
    async function sendMessage() {
        const textarea = $('#chatTextarea');
        if (!textarea) return;
        const text = textarea.value.trim();

        if (text === '') return;

        const payload = {
            conversation_id: state.currentConversationId,
            recipient_id: state.recipientId,
            message: text,
            reply_to_id: state.replyToId,
            ephemeral_timer: state.ephemeralTimer
        };

        textarea.value = '';
        textarea.style.height = 'auto';
        cancelReply();

        try {
            const res = await api('send_message.php', { method: 'POST', body: payload });
            if (res.ok) {
                // Se abbiamo creato una nuova conversazione, aggiorniamo la lista
                if (state.currentConversationId === 0 && res.message.conversation_id) {
                    state.currentConversationId = res.message.conversation_id;
                    await loadConversations();
                }
                
                state.messages.push(res.message);
                state.lastMessageId = res.message.id;
                renderMessages();
            } else {
                showToast(res.error, true);
            }
        } catch (e) {
            showToast("Errore di connessione.", true);
        }
    }

    // --- GESTIONE ALLEGATI ---
    function handleDragOver(e) {
        e.preventDefault();
        $('.chat-area').classList.add('drag-over');
    }

    function handleDragLeave(e) {
        e.preventDefault();
        $('.chat-area').classList.remove('drag-over');
    }

    function handleDrop(e) {
        e.preventDefault();
        $('.chat-area').classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            uploadFile(files[0]);
        }
    }

    function handleFileSelect(e) {
        const files = e.target.files;
        if (files.length > 0) {
            uploadFile(files[0]);
        }
    }

    async function uploadFile(file) {
        if (state.currentConversationId === 0) {
            showToast("Seleziona prima una chat per inviare file.", true);
            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        formData.append('conversation_id', state.currentConversationId);
        if (state.replyToId) formData.append('reply_to_id', state.replyToId);
        if (state.ephemeralTimer > 0) formData.append('ephemeral_timer', state.ephemeralTimer);

        showToast("Caricamento file in corso...");

        try {
            const res = await fetch('/api/chat/upload_media.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json());

            if (res.ok) {
                state.messages.push(res.message);
                state.lastMessageId = res.message.id;
                renderMessages();
                showToast("File inviato con successo!");
            } else {
                showToast(res.error, true);
            }
        } catch (e) {
            showToast("Errore durante il caricamento del file.", true);
        }
    }

    // --- REAZIONI ---
    async function toggleReaction(messageId, reaction) {
        try {
            const res = await api('manage_reaction.php', {
                method: 'POST',
                body: { message_id: messageId, reaction: reaction, action: 'toggle' }
            });
            if (res.ok) {
                const msg = state.messages.find(m => m.id === messageId);
                if (msg) {
                    msg.reactions = res.reactions;
                    renderMessages();
                }
            }
        } catch (e) {
            console.error("Errore reazione:", e);
        }
    }

    // --- MENU CONTESTUALE ---
    window.showContextMenu = function (e, messageId, isSent) {
        e.preventDefault();
        const menu = $('#chatContextMenu');
        if (!menu) return;

        menu.style.left = e.clientX + 'px';
        menu.style.top = e.clientY + 'px';
        menu.style.display = 'block';

        // Configura le voci del menu
        let html = `
            <div class="chat-context-menu__item" onclick="replyToMessage(${messageId})"><i class="fa-solid fa-reply"></i> Rispondi</div>
            <div class="chat-context-menu__item" onclick="copyMessageText(${messageId})"><i class="fa-solid fa-copy"></i> Copia Testo</div>
            <div class="chat-context-menu__item" onclick="togglePinMessage(${messageId})"><i class="fa-solid fa-thumbtack"></i> Fissa/Sfissa</div>
            <div class="chat-context-menu__item" onclick="toggleFavoriteMessage(${messageId})"><i class="fa-solid fa-star"></i> Preferito</div>
        `;

        if (isSent) {
            html += `
                <div class="chat-context-menu__item" onclick="startEditMessage(${messageId})"><i class="fa-solid fa-pen"></i> Modifica</div>
                <div class="chat-context-menu__item chat-context-menu__item--danger" onclick="deleteMessageForAll(${messageId})"><i class="fa-solid fa-trash-can"></i> Elimina per tutti</div>
            `;
        }

        html += `
            <div class="chat-context-menu__item chat-context-menu__item--danger" onclick="deleteMessageForSelf(${messageId})"><i class="fa-solid fa-trash"></i> Rimuovi per me</div>
        `;

        menu.innerHTML = html;
    };

    window.replyToMessage = function (id) {
        state.replyToId = id;
        const msg = state.messages.find(m => m.id === id);
        if (msg) {
            const bar = $('#chatReplyBar');
            bar.style.display = 'flex';
            bar.querySelector('.chat-reply-user').textContent = msg.sender_username;
            bar.querySelector('.chat-reply-text').textContent = msg.message || "[Allegato]";
        }
    };

    window.copyMessageText = function (id) {
        const msg = state.messages.find(m => m.id === id);
        if (msg && msg.message) {
            navigator.clipboard.writeText(msg.message);
            showToast("Testo copiato negli appunti.");
        }
    };

    window.togglePinMessage = async function (id) {
        try {
            const res = await api('manage_message.php', {
                method: 'POST',
                body: { action: 'toggle_pin', message_id: id }
            });
            if (res.ok) {
                showToast(res.pinned ? "Messaggio fissato." : "Messaggio sfissato.");
                if (state.isDetailsOpen) loadDetails();
            }
        } catch (e) {
            console.error(e);
        }
    };

    window.toggleFavoriteMessage = async function (id) {
        try {
            const res = await api('manage_message.php', {
                method: 'POST',
                body: { action: 'toggle_favorite', message_id: id }
            });
            if (res.ok) {
                showToast(res.favorited ? "Aggiunto ai preferiti." : "Rimosso dai preferiti.");
            }
        } catch (e) {
            console.error(e);
        }
    };

    window.startEditMessage = function (id) {
        state.editMessageId = id;
        const msg = state.messages.find(m => m.id === id);
        if (msg && msg.message) {
            const textarea = $('#chatTextarea');
            textarea.value = msg.message;
            textarea.focus();
            
            // Mostra barra di modifica
            const bar = $('#chatReplyBar');
            bar.style.display = 'flex';
            bar.querySelector('.chat-reply-user').textContent = "Modifica messaggio";
            bar.querySelector('.chat-reply-text').textContent = msg.message;
        }
    };

    window.deleteMessageForAll = function (id) {
        confirmAction("Eliminare questo messaggio per tutti?", async () => {
            const res = await api('manage_message.php', {
                method: 'POST',
                body: { action: 'delete_for_all', message_id: id }
            });
            if (res.ok) {
                const msg = state.messages.find(m => m.id === id);
                if (msg) {
                    msg.deleted_for_all = true;
                    renderMessages();
                }
                showToast("Messaggio eliminato per tutti.");
            }
        });
    };

    window.deleteMessageForSelf = function (id) {
        const res = api('manage_message.php', {
            method: 'POST',
            body: { action: 'delete_for_self', message_id: id }
        }).then(res => {
            if (res.ok) {
                state.messages = state.messages.filter(m => m.id !== id);
                renderMessages();
                showToast("Messaggio rimosso.");
            }
        });
    };

    function cancelReply() {
        state.replyToId = null;
        state.editMessageId = null;
        $('#chatReplyBar').style.display = 'none';
    }

    // --- PANNELLO DETTAGLI (DESTRA) ---
    function toggleDetails() {
        const panel = $('.chat-details');
        if (!panel) return;
        state.isDetailsOpen = !state.isDetailsOpen;
        panel.classList.toggle('is-hidden', !state.isDetailsOpen);
        panel.classList.toggle('is-open', state.isDetailsOpen);
        if (state.isDetailsOpen) {
            loadDetails();
        }
    }

    async function loadDetails() {
        if (state.currentConversationId === 0) return;
        try {
            const res = await fetch(`/api/chat/get_chat_details.php?conversation_id=${state.currentConversationId}`).then(r => r.json());
            if (res.ok) {
                renderDetails(res);
            }
        } catch (e) {
            console.error(e);
        }
    }

    function renderDetails(data) {
        const box = $('.chat-details__scroll');
        if (!box) return;

        // Renderizza galleria media condivisi
        let mediaHtml = '';
        if (data.gallery.media.length > 0) {
            mediaHtml = `
                <div class="chat-shared-media-grid">
                    ${data.gallery.media.slice(0, 6).map(m => `
                        <div class="chat-shared-media-item" onclick="openMediaViewer('${m.file_path}')">
                            ${m.file_type === 'image' ? `<img src="${m.file_path}">` : `<video src="${m.file_path}"></video>`}
                        </div>
                    `).join('')}
                </div>
            `;
        } else {
            mediaHtml = `<p class="text-muted" style="font-size:13px;">Nessun media condiviso.</p>`;
        }

        // Renderizza messaggi fissati
        let pinnedHtml = '';
        if (data.pinned_messages.length > 0) {
            pinnedHtml = data.pinned_messages.map(m => `
                <div style="background: rgba(255,255,255,0.02); padding: 8px; border-radius: 6px; margin-bottom: 8px; font-size:13px; border-left: 2px solid var(--chat-accent);">
                    <strong>${escapeHtml(m.sender_username)}:</strong> ${escapeHtml(m.message || '[Allegato]')}
                </div>
            `).join('');
        } else {
            pinnedHtml = `<p class="text-muted" style="font-size:13px;">Nessun messaggio fissato.</p>`;
        }

        const otherUser = data.participants.find(p => p.id !== parseInt(document.body.dataset.adminId || document.body.dataset.userId));
        const nickname = otherUser ? (otherUser.nickname || otherUser.username) : 'Utente';

        const isArchived = !!data.settings.is_archived;
        box.innerHTML = `
            <div class="chat-details__profile">
                <img class="chat-details__avatar" src="/includes/get_pfp.php?id=${otherUser.id}">
                <div class="chat-details__name">${escapeHtml(nickname)}</div>
                <div class="text-muted" style="font-size:13px;">@${escapeHtml(otherUser.username)}</div>
            </div>
            
            <div class="chat-details__section">
                <div class="chat-details__section-title">Personalizzazione</div>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <div>
                        <label style="font-size:12px;color:var(--chat-text-muted);margin-bottom:6px;display:block;">Nickname locale</label>
                        <input type="text" id="settingNicknameInput" class="chat-details-input" value="${escapeHtml(otherUser.nickname || '')}" placeholder="Imposta nickname..." onchange="updateLocalNickname(this.value)">
                    </div>
                </div>
            </div>

            <div class="chat-details__section">
                <div class="chat-details__section-title">Messaggi Fissati</div>
                ${pinnedHtml}
            </div>

            <div class="chat-details__section">
                <div class="chat-details__section-title">Media Condivisi</div>
                ${mediaHtml}
            </div>

            <div class="chat-details__section">
                <div class="chat-details__section-title">Azioni</div>
                <button class="chat-details-btn ${isArchived ? 'chat-details-btn--secondary' : 'chat-details-btn--primary'}" onclick="toggleArchiveChat(${isArchived})">
                    <i class="fa-solid ${isArchived ? 'fa-box-open' : 'fa-box-archive'}"></i>
                    ${isArchived ? 'Ripristina Chat' : 'Archivia Chat'}
                </button>
            </div>
        `;
    }

    window.updateLocalNickname = async function (val) {
        try {
            const res = await api('update_chat_settings.php', {
                method: 'POST',
                body: { conversation_id: state.currentConversationId, nickname: val }
            });
            if (res.ok) {
                showToast("Nickname aggiornato con successo.");
                loadConversations();
                // Aggiorna header
                $('#chatHeaderName').textContent = val || state.conversations.find(c => c.conversation_id === state.currentConversationId).other_username;
            }
        } catch (e) {
            console.error(e);
        }
    };

    window.toggleArchiveChat = async function (isArchived) {
        const action = isArchived ? 'unarchive' : 'archive';
        try {
            const res = await api('privacy_settings.php', {
                method: 'POST',
                body: { action: action, conversation_id: state.currentConversationId }
            });
            if (res.ok) {
                showToast(isArchived ? "Conversazione ripristinata." : "Conversazione archiviata.");
                loadConversations();
                
                // Se archiviamo, torniamo allo stato di selezione chat
                if (!isArchived) {
                    stopActiveChat();
                    const panel = $('.chat-details');
                    if (panel) {
                        panel.classList.add('is-hidden');
                        panel.classList.remove('is-open');
                    }
                    state.isDetailsOpen = false;
                } else {
                    loadDetails();
                }
            } else {
                showToast(res.error || "Errore durante l'operazione.", true);
            }
        } catch (e) {
            console.error(e);
            showToast("Errore di connessione.", true);
        }
    };

    // --- CONTROLLO PRESENZA & POLLING IN TEMPO REALE ---
    function startPolling() {
        state.pollTimer = setInterval(async () => {
            if (state.currentConversationId === 0) return;
            
            const payload = {
                conversation_id: state.currentConversationId,
                typing_status: state.isTyping ? 'typing' : 'idle',
                last_message_id: state.lastMessageId
            };

            try {
                const res = await fetch('/api/chat/presence.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).then(r => r.json());

                if (res.ok) {
                    // 1. Aggiorna lo stato online dell'altro utente nell'header
                    const statusEl = $('#chatHeaderStatus');
                    if (statusEl) {
                        if (res.other_typing) {
                            statusEl.textContent = "sta scrivendo...";
                            statusEl.className = "chat-area__user-status is-typing";
                        } else if (res.other_online) {
                            statusEl.textContent = "Online";
                            statusEl.className = "chat-area__user-status is-online";
                        } else {
                            statusEl.textContent = res.other_last_seen ? "Ultimo accesso " + formatDateTime(res.other_last_seen) : "Offline";
                            statusEl.className = "chat-area__user-status";
                        }
                    }

                    // 2. Se ci sono nuovi messaggi, li appendiamo
                    if (res.new_messages && res.new_messages.length > 0) {
                        res.new_messages.forEach(msg => {
                            // Evita duplicati
                            if (!state.messages.find(m => m.id === msg.id)) {
                                state.messages.push(msg);
                                state.lastMessageId = msg.id;
                            }
                        });
                        renderMessages();
                        playNotificationSound();
                        triggerBrowserNotification("Nuovo messaggio su Cripsum™", res.new_messages[0].message || "Hai ricevuto un allegato.");
                    }
                }
            } catch (e) {
                console.error("Errore polling:", e);
            }
        }, 3000);
    }

    // --- FUNZIONI DI RICERCA UTENTI ---
    async function performSearch(query) {
        if (query === '') {
            loadConversations();
            return;
        }

        try {
            const res = await api(`search.php?type=users&q=${encodeURIComponent(query)}`);
            if (res.ok && res.results) {
                renderSearchResults(res.results);
            }
        } catch (e) {
            console.error(e);
        }
    }

    function renderSearchResults(results) {
        const list = $('.chat-list');
        if (!list) return;

        if (results.length === 0) {
            list.innerHTML = `<div class="text-center py-5 text-muted">Nessun utente trovato</div>`;
            return;
        }

        list.innerHTML = results.map(u => `
            <div class="chat-item" onclick="startNewChat(${u.id})">
                <div class="chat-item__avatar-container">
                    <img class="chat-item__avatar" src="/includes/get_pfp.php?id=${u.id}" alt="${u.username}">
                </div>
                <div class="chat-item__info">
                    <div class="chat-item__name-row">
                        <span class="chat-item__name">${escapeHtml(u.username)}</span>
                    </div>
                    <div class="chat-item__msg-row">
                        <span class="chat-item__preview">Clicca per avviare una chat privata</span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    async function startNewChat(otherUserId) {
        state.recipientId = otherUserId;
        state.currentConversationId = 0;
        state.lastMessageId = 0;
        state.messages = [];
        
        // Configura header provvisorio
        $('#chatHeaderName').textContent = "Nuova Conversazione";
        const avatar = $('#chatHeaderAvatar');
        avatar.src = `/includes/get_pfp.php?id=${otherUserId}`;
        avatar.style.display = 'block';
        $('#chatHeaderStatus').textContent = "Pronto a inviare";
        
        $('.chat-messages').innerHTML = `<div class="text-center py-5 text-muted my-auto">Invia un messaggio per iniziare la chat privata.</div>`;
        $('.chat-shell').classList.add('is-chat-open');
    }

    // --- UTILITY E AIUTI ---
    async function api(endpoint, options = {}) {
        const url = `/api/chat/${endpoint}`;
        options.headers = options.headers || {};
        options.headers['Content-Type'] = 'application/json';
        if (options.body && typeof options.body === 'object') {
            options.body = JSON.stringify(options.body);
        }
        const res = await fetch(url, options);
        return res.json();
    }

    function debounce(fn, delay) {
        let timer = null;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    function handleTextareaInput(e) {
        const textarea = e.target;
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';

        // Gestione Typing status
        if (!state.isTyping) {
            state.isTyping = true;
        }
        clearTimeout(state.typingTimer);
        state.typingTimer = setTimeout(() => {
            state.isTyping = false;
        }, 3000);
    }

    function handleTextareaKeydown(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    }

    function scrollToBottom() {
        const box = $('.chat-messages');
        if (box) {
            box.scrollTop = box.scrollHeight;
        }
    }

    window.scrollToMessage = function (id) {
        const el = document.getElementById(`msg-${id}`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('chat-msg-highlight');
            setTimeout(() => el.classList.remove('chat-msg-highlight'), 2000);
        }
    };

    window.openMediaViewer = function (path) {
        let viewer = document.createElement('div');
        viewer.id = 'chatMediaViewer';
        viewer.style = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:pointer;animation:fadeIn 0.2s;';
        viewer.innerHTML = `<img src="${path}" style="max-width:90%;max-height:90%;object-fit:contain;border-radius:8px;box-shadow:0 10px 25px rgba(0,0,0,0.5);">`;
        viewer.addEventListener('click', () => viewer.remove());
        document.body.appendChild(viewer);
    };

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function parseMessageText(text) {
        if (!text) return '';
        let escaped = escapeHtml(text);
        
        // Rilevamento link e auto-conversione in tag <a>
        escaped = escaped.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
        
        return escaped;
    }

    // --- AUDIO & NOTIFICHE ---
    function playNotificationSound() {
        const audio = new Audio('/assets/static/sounds/notification.mp5'); // O altro file audio presente
        audio.volume = 0.4;
        audio.play().catch(() => {}); // Ignora se bloccato dal browser
    }

    function requestNotificationPermission() {
        if ("Notification" in window) {
            Notification.requestPermission();
        }
    }

    function triggerBrowserNotification(title, body) {
        if ("Notification" in window && Notification.permission === "granted") {
            new Notification(title, { body: body, icon: '/img/favicon.png' });
        }
    }

    // --- TIME FORMATTING ---
    function formatTime(timeStr) {
        const date = new Date(timeStr);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function formatDateTime(timeStr) {
        const date = new Date(timeStr);
        return date.toLocaleString([], { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    function formatDateSeparator(timeStr) {
        const date = new Date(timeStr);
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);

        if (date.toDateString() === today.toDateString()) {
            return "Oggi";
        } else if (date.toDateString() === yesterday.toDateString()) {
            return "Ieri";
        } else {
            return date.toLocaleDateString([], { weekday: 'long', day: 'numeric', month: 'long' });
        }
    }

    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    // --- DIALOG DI CONFERMA CUSTOM ---
    function confirmAction(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }

    // --- TOAST NOTIFICATIONS ---
    function showToast(message, isError = false) {
        const toast = $('#adminToast') || $('#chatToast');
        if (!toast) return;
        toast.textContent = message;
        toast.style.background = isError ? '#ef4444' : '#8b5cf6';
        toast.classList.add('is-visible');
        setTimeout(() => toast.classList.remove('is-visible'), 3000);
    }
})();
