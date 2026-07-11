// assets/chat/chat.js
// SPA Orchestrator Engine for Cripsum™ Group & Private Chat.

(function () {
    const _lang = document.documentElement.lang === 'it' ? 'it' : 'en';
    const _T = {
        error_load_chats:        { it: 'Errore caricamento lista chat:',                          en: 'Error loading chat list:' },
        error_load_messages:     { it: 'Impossibile caricare i messaggi.',                        en: 'Could not load messages.' },
        error_load_messages_console: { it: 'Errore caricamento messaggi:',                        en: 'Error loading messages:' },
        group_chat:              { it: 'Gruppo di chat',                                          en: 'Group chat' },
        status_online:           { it: 'Online',                                                  en: 'Online' },
        status_offline:          { it: 'Offline',                                                 en: 'Offline' },
        select_a_chat:           { it: 'Seleziona una chat',                                      en: 'Select a chat' },
        choose_user_to_chat:     { it: 'Scegli un utente o un gruppo per iniziare a chattare.',    en: 'Choose a user or group to start chatting.' },
        msg_edited:              { it: 'Messaggio modificato.',                                    en: 'Message edited.' },
        error_edit:              { it: 'Errore durante la modifica.',                              en: 'Error editing message.' },
        error_connection:        { it: 'Errore di connessione.',                                   en: 'Connection error.' },
        error_send_message:      { it: 'Impossibile inviare il messaggio.',                        en: 'Could not send message.' },
        error_send_gif:          { it: 'Impossibile inviare la GIF.',                              en: 'Could not send GIF.' },
        error_network:           { it: 'Errore di rete.',                                          en: 'Network error.' },
        error_chat_details:      { it: 'Errore dettagli chat:',                                    en: 'Error loading chat details:' },
        select_chat_to_send_file:{ it: 'Seleziona prima una chat per inviare file.',                en: 'Select a chat first to send files.' },
        uploading_file:          { it: 'Caricamento file in corso...',                              en: 'Uploading file...' },
        file_sent:               { it: 'File inviato con successo!',                               en: 'File sent successfully!' },
        error_upload_file:       { it: 'Errore durante il caricamento del file.',                   en: 'Error uploading file.' },
        group_name_required:     { it: 'Il nome del gruppo è obbligatorio.',                       en: 'Group name is required.' },
        select_participants:     { it: 'Seleziona almeno un partecipante da invitare.',             en: 'Select at least one participant to invite.' },
        group_created:           { it: 'Gruppo creato con successo!',                               en: 'Group created successfully!' },
        error_create_group:      { it: 'Impossibile creare il gruppo.',                             en: 'Could not create group.' },
        error_invite:            { it: "Errore durante l'invio degli inviti.",                      en: 'Error sending invitations.' },
        typing:                  { it: 'sta scrivendo...',                                          en: 'is typing...' },
        last_seen:               { it: 'Ultimo accesso ',                                           en: 'Last seen ' },
        invite_accepted:         { it: 'Invito accettato.',                                         en: 'Invite accepted.' },
        invite_declined:         { it: 'Invito rifiutato.',                                         en: 'Invite declined.' },
        user_promoted:           { it: 'Utente promosso ad admin.',                                 en: 'User promoted to admin.' },
        admin_revoked:           { it: 'Privilegi admin revocati.',                                 en: 'Admin privileges revoked.' },
        member_removed:          { it: 'Membro rimosso dal gruppo.',                                en: 'Member removed from group.' },
        avatar_updated:          { it: 'Avatar del gruppo aggiornato.',                             en: 'Group avatar updated.' },
        error_avatar:            { it: 'Errore caricamento avatar.',                                en: 'Error uploading avatar.' },
        group_details_updated:   { it: 'Dettagli gruppo modificati.',                               en: 'Group details updated.' },
        permissions_updated:     { it: 'Permessi aggiornati.',                                      en: 'Permissions updated.' },
        notifications_unmuted:   { it: 'Notifiche riattivate.',                                     en: 'Notifications unmuted.' },
        notifications_muted:     { it: 'Notifiche silenziate.',                                     en: 'Notifications muted.' },
        error_mute:              { it: 'Errore mute.',                                              en: 'Mute error.' },
        group_archived:          { it: 'Gruppo archiviato.',                                        en: 'Group archived.' },
        error_archive:           { it: 'Errore archiviazione.',                                     en: 'Error archiving.' },
        confirm_leave_group:     { it: 'Sei sicuro di voler lasciare questo gruppo?',               en: 'Are you sure you want to leave this group?' },
        left_group:              { it: 'Hai lasciato il gruppo.',                                   en: 'You left the group.' },
        error_generic:           { it: 'Errore.',                                                   en: 'Error.' },
        add_reaction:            { it: 'Aggiungi reazione',                                         en: 'Add reaction' },
        ctx_reply:               { it: 'Rispondi',                                                  en: 'Reply' },
        ctx_copy:                { it: 'Copia Testo',                                               en: 'Copy Text' },
        ctx_pin:                 { it: 'Fissa/Sfissa',                                              en: 'Pin/Unpin' },
        ctx_favorite:            { it: 'Preferito',                                                 en: 'Favorite' },
        ctx_edit:                { it: 'Modifica',                                                  en: 'Edit' },
        ctx_delete_all:          { it: 'Elimina per tutti',                                         en: 'Delete for everyone' },
        ctx_moderate_delete:     { it: 'Modera ed Elimina',                                         en: 'Moderate and Delete' },
        ctx_delete_self:         { it: 'Rimuovi per me',                                            en: 'Remove for me' },
        error_reaction:          { it: 'Impossibile aggiornare la reazione.',                        en: 'Could not update reaction.' },
        file_attachment:         { it: '[File/Allegato]',                                            en: '[File/Attachment]' },
        text_copied:             { it: 'Testo copiato negli appunti.',                               en: 'Text copied to clipboard.' },
        added_favorite:          { it: 'Aggiunto ai preferiti.',                                     en: 'Added to favorites.' },
        removed_favorite:        { it: 'Rimosso dai preferiti.',                                     en: 'Removed from favorites.' },
        msg_removed_self:        { it: 'Messaggio rimosso per te.',                                  en: 'Message removed for you.' },
        edit_message:            { it: 'Modifica Messaggio',                                         en: 'Edit Message' },
        confirm_delete_msg:      { it: 'Sei sicuro di voler eliminare questo messaggio?',            en: 'Are you sure you want to delete this message?' },
        msg_deleted:             { it: 'Messaggio eliminato.',                                       en: 'Message deleted.' },
        msg_pinned:              { it: 'Messaggio fissato.',                                         en: 'Message pinned.' },
        msg_unpinned:            { it: 'Messaggio sfissato.',                                        en: 'Message unpinned.' },
        nickname_updated:        { it: 'Nickname aggiornato.',                                       en: 'Nickname updated.' },
        conv_restored:           { it: 'Conversazione ripristinata.',                                en: 'Conversation restored.' },
        conv_archived:           { it: 'Conversazione archiviata.',                                  en: 'Conversation archived.' },
        new_conversation:        { it: 'Nuova Conversazione',                                        en: 'New Conversation' },
        ready_to_send:           { it: 'Pronto a inviare',                                           en: 'Ready to send' },
        send_to_start:           { it: 'Invia un messaggio per iniziare la chat privata.',            en: 'Send a message to start the private chat.' }
    };
    function _t(key) { return _T[key] ? (_T[key][_lang] || _T[key]['en']) : key; }

    let currentGifQuery = '';
    let nextGifOffset = '';

    // Initializer
    document.addEventListener('DOMContentLoaded', () => {
        init();
    });

    async function init() {
        const userId = document.body.dataset.adminId || document.body.dataset.userId || 0;
        ChatAPI.init();
        ChatState.init(userId);
        ChatUI.init();

        setupEventListeners();
        initEmojiStrip();
        await loadAllConversations();
        startRealtimePolling();

    }

    function initEmojiStrip() {
        const strip = document.querySelector('#chatEmojiStrip');
        if (!strip) return;

        const standardEmojis = ['🔥', '💀', '👏', '😳', '⚡', '👀'];
        let html = '';

        standardEmojis.forEach(emoji => {
            html += `<button type="button" data-emoji="${emoji}" style="background:none; border:none; font-size: 20px; cursor:pointer;">${emoji}</button>`;
        });

        if (window.CHAT_CUSTOM_EMOJIS) {
            window.CHAT_CUSTOM_EMOJIS.forEach(emoji => {
                html += `
                    <button type="button" data-emoji=":${emoji.code}:" style="background:none; border:none; padding:4px; cursor:pointer;" title=":${emoji.code}:">
                        <img src="${emoji.url}" alt="${emoji.code}" style="width:24px; height:24px; object-fit:contain; pointer-events:none; vertical-align:middle;" />
                    </button>
                `;
            });
        }

        strip.innerHTML = html;

        // Process URL Query parameters
        const urlParams = new URLSearchParams(window.location.search);
        const startUser = urlParams.get('user_id');
        const createWithUser = urlParams.get('create_group_with');

        if (startUser) {
            const userIdNum = parseInt(startUser);
            const existingConv = ChatState.conversations.find(c => !c.isGroupChat && parseInt(c.other_user_id) === userIdNum);
            if (existingConv) {
                selectChat('private', existingConv.conversation_id, userIdNum);
            } else {
                startNewPrivateConversation(userIdNum);
            }
        } else if (createWithUser) {
            openCreateGroupWith(parseInt(createWithUser));
        }
    }

    // --- CARICAMENTO CONVERSAZIONI ---
    async function loadAllConversations() {
        try {
            const res = await ChatAPI.getChatList(ChatState.activeTab || 'active');
            if (res.ok) {
                ChatState.invites = res.invites || [];
                
                const groups = (res.groups || []).map(g => {
                    g.isGroupChat = true;
                    return g;
                });
                const privates = (res.privates || []).map(p => {
                    p.isGroupChat = false;
                    return p;
                });
                
                ChatState.conversations = [...groups, ...privates];
                ChatUI.renderConversations();
            }
        } catch (e) {
            console.error(_t('error_load_chats'), e);
        }
    }

    function setupEventListeners() {
        // Tab switching
        document.querySelector('#tab-active')?.addEventListener('click', () => switchSidebarTab('active'));
        document.querySelector('#tab-archived')?.addEventListener('click', () => switchSidebarTab('archived'));

        // Search conversations
        document.querySelector('#chatSearchInput')?.addEventListener('input', debounce((e) => {
            filterConversations(e.target.value.trim());
        }, 300));

        // Textarea events
        const textarea = document.querySelector('#chatTextarea');
        if (textarea) {
            textarea.addEventListener('keydown', handleComposerKeydown);
            textarea.addEventListener('input', handleComposerInput);
        }

        // Send message button
        document.querySelector('#chatSendBtn')?.addEventListener('click', () => triggerMessageSend());

        // Info button details panel toggle
        document.querySelector('#chatInfoBtn')?.addEventListener('click', toggleDetailsPanel);
        document.querySelector('#chatDetailsCloseBtn')?.addEventListener('click', toggleDetailsPanel);
        document.querySelector('#chatHeaderUser')?.addEventListener('click', (e) => {
            if (e.target.closest('#chatBackBtn')) return;
            if (!ChatState.currentChatId) return;
            toggleDetailsPanel();
        });

        // Mobile back button
        document.querySelector('#chatBackBtn')?.addEventListener('click', () => {
            document.querySelector('.chat-shell').classList.remove('is-chat-open');
            ChatState.resetActiveChat();
            stopActiveChatUI();
        });

        // Close context menu clicking outside
        document.addEventListener('click', () => {
            const menu = document.querySelector('#chatContextMenu');
            if (menu) menu.style.display = 'none';
        });
        document.addEventListener('contextmenu', (e) => {
            if (!e.target.closest('.chat-bubble-wrap')) {
                const menu = document.querySelector('#chatContextMenu');
                if (menu) menu.style.display = 'none';
            }
        });

        // Cancel replies
        document.querySelector('#cancelReplyBtn')?.addEventListener('click', cancelReplyMode);

        // --- GIF & EMOJIS COMPOSER EVENTS ---
        document.querySelector('.js-toggle-emojis')?.addEventListener('click', () => {
            const strip = document.querySelector('#chatEmojiStrip');
            if (strip) strip.hidden = !strip.hidden;
            document.querySelector('#chatGifPanel').hidden = true;
        });

        document.querySelector('#chatEmojiStrip')?.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (btn && btn.dataset.emoji) {
                const textarea = document.querySelector('#chatTextarea');
                if (textarea) {
                    textarea.value += btn.dataset.emoji;
                    textarea.focus();
                }
            }
        });

        document.querySelector('.js-toggle-gifs')?.addEventListener('click', () => {
            const panel = document.querySelector('#chatGifPanel');
            if (panel) {
                panel.hidden = !panel.hidden;
                if (!panel.hidden) {
                    loadGifs('');
                }
            }
            document.querySelector('#chatEmojiStrip').hidden = true;
        });

        document.querySelector('.js-close-gifs')?.addEventListener('click', () => {
            document.querySelector('#chatGifPanel').hidden = true;
        });

        document.querySelector('#chatGifSearch')?.addEventListener('input', debounce((e) => {
            loadGifs(e.target.value.trim());
        }, 400));

        document.querySelector('.js-more-gifs')?.addEventListener('click', () => {
            loadMoreGifs();
        });

        document.querySelector('#chatGifGrid')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.chat-gif-item');
            if (!btn?.dataset.gif) return;
            try {
                const gif = JSON.parse(btn.dataset.gif);
                sendGifMessage(gif.url, gif.title || 'GIF');
            } catch (err) {
                ChatUI.showToast('GIF non valida.', true);
            }
        });

        // --- FILE ATTACHMENTS & DRAG/DROP EVENTS ---
        const chatArea = document.querySelector('.chat-area');
        if (chatArea) {
            chatArea.addEventListener('dragover', handleDragOver);
            chatArea.addEventListener('dragleave', handleDragLeave);
            chatArea.addEventListener('drop', handleDrop);
        }

        document.querySelector('#chatAttachBtn')?.addEventListener('click', () => {
            document.querySelector('#chatFileInput')?.click();
        });
        document.querySelector('#chatFileInput')?.addEventListener('change', handleFileSelect);
    }

    function switchSidebarTab(tab) {
        ChatState.activeTab = tab;
        document.querySelector('#tab-active')?.classList.toggle('is-active', tab === 'active');
        document.querySelector('#tab-archived')?.classList.toggle('is-active', tab === 'archived');
        loadAllConversations();
    }

    function filterConversations(query) {
        ChatState.searchQuery = query.toLowerCase();
        const items = document.querySelectorAll('.chat-item');
        items.forEach(el => {
            const name = el.querySelector('.chat-item__name')?.textContent.toLowerCase() || '';
            const matches = name.includes(ChatState.searchQuery);
            el.style.display = matches ? 'flex' : 'none';
        });
    }

    // --- SELEZIONE CHAT ---
    async function selectChat(type, id, recipientId = 0) {
        ChatState.setActiveChat(type, id, recipientId);
        ChatState.firstLoad = true;
        cancelReplyMode();
        
        // Hide panel elements
        document.querySelector('#chatEmojiStrip').hidden = true;
        document.querySelector('#chatGifPanel').hidden = true;

        // Highlight active sidebar item
        document.querySelectorAll('.chat-item').forEach(el => {
            const elId = parseInt(el.dataset.id);
            const elType = el.dataset.type;
            const active = (elId === id && elType === type);
            el.classList.toggle('is-active', active);
            if (active) {
                el.classList.remove('is-unread');
                const badge = el.querySelector('.chat-item__badge');
                if (badge) badge.remove();
            }
        });

        // Show chat screen on mobile
        document.querySelector('.chat-shell').classList.add('is-chat-open');

        renderSkeletonUI();

        try {
            let res;
            if (type === 'group') {
                res = await ChatAPI.getMessages(id);
            } else {
                res = await ChatAPI.getPrivateMessages(id);
            }

            if (res.ok) {
                ChatState.messages = res.messages || [];
                if (ChatState.messages.length > 0) {
                    ChatState.lastMessageId = ChatState.messages[ChatState.messages.length - 1].id;
                }
                
                // Setup Header
                const chatData = ChatState.getChatFromList(type, id);
                if (chatData) {
                    setupChatHeaderUI(chatData);
                }

                ChatUI.renderMessages();
                
                if (ChatState.isDetailsOpen) {
                    await loadDetailsPanelInfo();
                }
            } else {
                ChatUI.showToast(res.error || _t('error_load_messages'), true);
            }
        } catch (e) {
            console.error(_t('error_load_messages_console'), e);
        }
    }

    function setupChatHeaderUI(chat) {
        const nameEl = document.querySelector('#chatHeaderName');
        const avatarEl = document.querySelector('#chatHeaderAvatar');
        const statusEl = document.querySelector('#chatHeaderStatus');

        if (chat.isGroupChat) {
            nameEl.textContent = chat.name;
            avatarEl.src = chat.avatar_url || '/img/Susremaster.png';
            avatarEl.style.display = 'block';
            statusEl.textContent = _t('group_chat');
            statusEl.className = 'chat-area__user-status';
        } else {
            const nickname = chat.other_nickname || chat.other_username;
            nameEl.textContent = nickname;
            avatarEl.src = `/includes/get_pfp.php?id=${chat.other_user_id}`;
            avatarEl.style.display = 'block';
            statusEl.textContent = chat.is_online ? _t('status_online') : _t('status_offline');
            statusEl.className = 'chat-area__user-status' + (chat.is_online ? ' is-online' : '');
        }
    }

    function stopActiveChatUI() {
        document.querySelector('#chatHeaderName').textContent = _t('select_a_chat');
        const avatar = document.querySelector('#chatHeaderAvatar');
        avatar.src = "";
        avatar.style.display = 'none';
        document.querySelector('#chatHeaderStatus').textContent = "";
        document.querySelector('.chat-messages').innerHTML = `<div class="text-center py-5 text-muted my-auto"><i class="fa-regular fa-paper-plane fs-1 mb-3" style="color:var(--chat-accent) !important;opacity:0.8;"></i><br>${_t('choose_user_to_chat')}</div>`;
    }

    // --- SEND / EDIT / DELETE ---
    async function triggerMessageSend() {
        const textarea = document.querySelector('#chatTextarea');
        if (!textarea) return;
        const text = textarea.value.trim();

        if (text === '') return;

        // EDIT MODE
        if (ChatState.editMessageId) {
            const msgId = ChatState.editMessageId;
            textarea.value = '';
            textarea.style.height = 'auto';
            cancelReplyMode();
            
            try {
                let res;
                if (ChatState.currentChatType === 'group') {
                    res = await ChatAPI.editMessage(msgId, text);
                } else {
                    res = await ChatAPI.editPrivateMessage(msgId, text);
                }
                
                if (res.ok) {
                    const msgObj = ChatState.messages.find(m => m.id === msgId);
                    if (msgObj) {
                        msgObj.body = text;
                        msgObj.message = text;
                        msgObj.edited_at = new Date().toISOString();
                    }
                    ChatUI.renderMessages();
                    ChatUI.showToast(_t('msg_edited'));
                } else {
                    ChatUI.showToast(res.error || _t('error_edit'), true);
                }
            } catch (e) {
                ChatUI.showToast(_t('error_connection'), true);
            }
            return;
        }

        // SEND MODE
        const replyTo = ChatState.replyToId;
        textarea.value = '';
        textarea.style.height = 'auto';
        cancelReplyMode();

        try {
            let res;
            if (ChatState.currentChatType === 'group') {
                res = await ChatAPI.sendMessage(ChatState.currentChatId, text, replyTo);
            } else {
                res = await ChatAPI.sendPrivateMessage(ChatState.currentChatId, ChatState.recipientId, text, replyTo);
            }

            if (res.ok) {
                const msg = res.message;
                if (msg.body === undefined && msg.message !== undefined) {
                    msg.body = msg.message;
                }
                ChatState.messages.push(msg);
                ChatState.lastMessageId = msg.id;
                ChatUI.renderMessages(true);
                loadAllConversations();
            } else {
                ChatUI.showToast(res.error || _t('error_send_message'), true);
            }
        } catch (e) {
            ChatUI.showToast(_t('error_connection'), true);
        }
    }

    // --- KLIPY GIF CALLS ---
    async function loadGifs(query) {
        currentGifQuery = query;
        nextGifOffset = '';
        
        try {
            const res = await ChatAPI.getGifs(query, '');
            if (res.ok && res.gifs) {
                ChatUI.renderGifs(res.gifs, false);
                nextGifOffset = res.next || '';
                document.querySelector('.js-more-gifs').hidden = !nextGifOffset;
            }
        } catch (e) {
            console.error("Klipy load error:", e);
        }
    }

    async function loadMoreGifs() {
        if (!nextGifOffset) return;
        try {
            const res = await ChatAPI.getGifs(currentGifQuery, nextGifOffset);
            if (res.ok && res.gifs) {
                ChatUI.renderGifs(res.gifs, true);
                nextGifOffset = res.next || '';
                document.querySelector('.js-more-gifs').hidden = !nextGifOffset;
            }
        } catch (e) {
            console.error("Klipy load more error:", e);
        }
    }

    async function sendGifMessage(url, title) {
        document.querySelector('#chatGifPanel').hidden = true;
        
        try {
            let res;
            const extra = {
                message_type: 'gif',
                media_url: url,
                media_title: title
            };
            
            if (ChatState.currentChatType === 'group') {
                res = await ChatAPI.sendMessage(ChatState.currentChatId, '', null, extra);
            } else {
                res = await ChatAPI.sendPrivateMessage(ChatState.currentChatId, ChatState.recipientId, '', null, extra);
            }

            if (res.ok) {
                const msg = res.message;
                if (msg.body === undefined && msg.message !== undefined) {
                    msg.body = msg.message;
                }
                ChatState.messages.push(msg);
                ChatState.lastMessageId = msg.id;
                ChatUI.renderMessages(true);
                loadAllConversations();
            } else {
                ChatUI.showToast(res.error || _t('error_send_gif'), true);
            }
        } catch (e) {
            ChatUI.showToast(_t('error_network'), true);
        }
    }

    // --- DETTAGLI GRUPPO / PRIVATE ---
    async function toggleDetailsPanel() {
        const panel = document.querySelector('.chat-details');
        if (!panel) return;
        ChatState.isDetailsOpen = !ChatState.isDetailsOpen;
        panel.classList.toggle('is-hidden', !ChatState.isDetailsOpen);
        panel.classList.toggle('is-open', ChatState.isDetailsOpen);
        if (ChatState.isDetailsOpen) {
            await loadDetailsPanelInfo();
        }
    }

    async function loadDetailsPanelInfo() {
        if (ChatState.currentChatId === 0) return;
        
        try {
            if (ChatState.currentChatType === 'group') {
                const detailsRes = await ChatAPI.getChatDetails(ChatState.currentChatId);
                const membersRes = await ChatAPI.getChatMembers(ChatState.currentChatId);
                
                if (detailsRes.ok && membersRes.ok) {
                    ChatState.members = membersRes.members || [];
                    ChatUI.renderDetails({
                        chat: detailsRes.chat,
                        settings: detailsRes.settings,
                        my_membership: detailsRes.my_membership
                    });
                }
            } else {
                const res = await fetch(`/api/chat/get_chat_details.php?conversation_id=${ChatState.currentChatId}`).then(r => r.json());
                if (res.ok) {
                    ChatUI.renderDetails(res);
                }
            }
        } catch (e) {
            console.error(_t('error_chat_details'), e);
        }
    }

    // --- FILE ATTACHMENTS HANDLING ---
    function handleDragOver(e) {
        e.preventDefault();
        document.querySelector('.chat-area')?.classList.add('drag-over');
    }

    function handleDragLeave(e) {
        e.preventDefault();
        document.querySelector('.chat-area')?.classList.remove('drag-over');
    }

    function handleDrop(e) {
        e.preventDefault();
        document.querySelector('.chat-area')?.classList.remove('drag-over');
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
        if (ChatState.currentChatId === 0) {
            ChatUI.showToast(_t('select_chat_to_send_file'), true);
            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        if (ChatState.currentChatType === 'group') {
            formData.append('chat_id', ChatState.currentChatId);
        } else {
            formData.append('conversation_id', ChatState.currentChatId);
        }
        if (ChatState.replyToId) formData.append('reply_to_id', ChatState.replyToId);

        ChatUI.showToast(_t('uploading_file'));

        try {
            const res = await fetch('/api/chat/upload_media.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-Token': ChatAPI.csrfToken
                }
            }).then(r => r.json());

            if (res.ok) {
                const msg = res.message;
                if (msg.body === undefined && msg.message !== undefined) {
                    msg.body = msg.message;
                }
                ChatState.messages.push(msg);
                ChatState.lastMessageId = msg.id;
                ChatUI.renderMessages(true);
                ChatUI.showToast(_t('file_sent'));
                await loadAllConversations();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast(_t('error_upload_file'), true);
        }
    }

    // --- SUBMIT GROUP / INVITE ---
    async function submitCreateGroup() {
        const nameInput = document.querySelector('#newGroupNameInput');
        const descInput = document.querySelector('#newGroupDescInput');
        const name = nameInput.value.trim();
        const desc = descInput.value.trim();

        if (name === '') {
            ChatUI.showToast(_t('group_name_required'), true);
            return;
        }

        const checkboxes = document.querySelectorAll('input[name="createGroupInviteCheck"]:checked');
        const invitedUsers = Array.from(checkboxes).map(chk => parseInt(chk.value));

        if (invitedUsers.length === 0) {
            ChatUI.showToast(_t('select_participants'), true);
            return;
        }

        try {
            const res = await ChatAPI.createGroup(name, desc, invitedUsers);
            if (res.ok) {
                ChatUI.showToast(_t('group_created'));
                ChatUI.closeCreateGroupModal();
                await loadAllConversations();
                if (res.chat_id) {
                    selectChat('group', res.chat_id);
                }
            } else {
                ChatUI.showToast(res.error || _t('error_create_group'), true);
            }
        } catch (e) {
            ChatUI.showToast(_t('error_connection'), true);
        }
    }

    async function submitInviteUsers() {
        const checkboxes = document.querySelectorAll('input[name="inviteGroupCheck"]:checked');
        const invitedUsers = Array.from(checkboxes).map(chk => parseInt(chk.value));

        if (invitedUsers.length === 0) {
            ChatUI.closeInviteUsersModal();
            return;
        }

        try {
            let successCount = 0;
            for (const userId of invitedUsers) {
                const res = await ChatAPI.inviteUser(ChatState.currentChatId, userId);
                if (res.ok) successCount++;
            }
            
            ChatUI.showToast(_lang === 'it' ? `Invitati ${successCount} utenti nel gruppo.` : `Invited ${successCount} users to the group.`);
            ChatUI.closeInviteUsersModal();
            await loadDetailsPanelInfo();
        } catch (e) {
            ChatUI.showToast(_t('error_invite'), true);
        }
    }

    // --- POLLING REALE ---
    function startRealtimePolling() {
        if (ChatState.pollInterval) clearInterval(ChatState.pollInterval);
        
        ChatState.pollInterval = setInterval(async () => {
            if (ChatState.currentChatId === 0) return;
            
            try {
                let res;
                if (ChatState.currentChatType === 'group') {
                    res = await ChatAPI.getMessages(ChatState.currentChatId, 0, ChatState.lastMessageId);
                } else {
                    const payload = {
                        conversation_id: ChatState.currentChatId,
                        typing_status: 'idle',
                        last_message_id: ChatState.lastMessageId
                    };
                    res = await fetch('/api/chat/presence.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    }).then(r => r.json());
                }

                if (res.ok) {
                    // Aggiorna lo stato online dell'altro utente nell'header (solo per chat private)
                    if (ChatState.currentChatType === 'private') {
                        const statusEl = document.querySelector('#chatHeaderStatus');
                        if (statusEl) {
                            if (res.other_typing) {
                                statusEl.textContent = _t('typing');
                                statusEl.className = "chat-area__user-status is-typing";
                            } else if (res.other_online) {
                                statusEl.textContent = _t('status_online');
                                statusEl.className = "chat-area__user-status is-online";
                            } else {
                                statusEl.textContent = res.other_last_seen ? _t('last_seen') + window.formatDateTime(res.other_last_seen) : _t('status_offline');
                                statusEl.className = "chat-area__user-status";
                            }
                        }
                    }

                    // Se ci sono nuovi messaggi, li appendiamo
                    const newMsgs = ChatState.currentChatType === 'group' ? res.messages : res.new_messages;

                    if (newMsgs && newMsgs.length > 0) {
                        newMsgs.forEach(msg => {
                            if (msg.body === undefined && msg.message !== undefined) {
                                msg.body = msg.message;
                            }
                            if (!ChatState.messages.some(m => m.id === msg.id)) {
                                ChatState.messages.push(msg);
                                ChatState.lastMessageId = msg.id;
                            }
                        });
                        ChatUI.renderMessages();
                        
                        if (ChatState.currentChatType === 'group') {
                            await ChatAPI.markRead(ChatState.currentChatId, ChatState.lastMessageId);
                        } else {
                            await ChatAPI.markPrivateRead(ChatState.currentChatId, ChatState.lastMessageId);
                        }
                    }
                }
            } catch (e) {
                console.error("Polling error:", e);
            }
        }, 3000);
    }

    // --- OTHER ACTIONS BINDINGS ---
    window.selectChat = (type, id, recipientId = 0) => selectChat(type, id, recipientId);
    window.submitCreateGroup = () => submitCreateGroup();
    window.submitInviteUsers = () => submitInviteUsers();
    window.sendGifMessage = (url, title) => sendGifMessage(url, title);

    window.acceptGroupInvite = async (chatId) => {
        try {
            const res = await ChatAPI.acceptInvite(chatId);
            if (res.ok) {
                ChatUI.showToast(_t('invite_accepted'));
                await loadAllConversations();
                selectChat('group', chatId);
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast(_t('error_connection'), true);
        }
    };

    window.declineGroupInvite = async (chatId) => {
        try {
            const res = await ChatAPI.declineInvite(chatId);
            if (res.ok) {
                ChatUI.showToast(_t('invite_declined'));
                await loadAllConversations();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast(_t('error_connection'), true);
        }
    };

    window.promoteGroupAdmin = async (memberId) => {
        try {
            const res = await ChatAPI.promoteAdmin(ChatState.currentChatId, memberId);
            if (res.ok) {
                ChatUI.showToast(_t('user_promoted'));
                await loadDetailsPanelInfo();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast(_t('error_generic'), true);
        }
    };

    window.demoteGroupAdmin = async (memberId) => {
        try {
            const res = await ChatAPI.demoteAdmin(ChatState.currentChatId, memberId);
            if (res.ok) {
                ChatUI.showToast(_t('admin_revoked'));
                await loadDetailsPanelInfo();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast(_t('error_generic'), true);
        }
    };

    window.kickGroupMember = async (memberId) => {
        try {
            const res = await ChatAPI.removeMember(ChatState.currentChatId, memberId);
            if (res.ok) {
                ChatUI.showToast(_t('member_removed'));
                await loadDetailsPanelInfo();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast(_t('error_generic'), true);
        }
    };

    window.uploadGroupAvatar = async (input) => {
        if (!input.files || input.files.length === 0) return;
        const file = input.files[0];
        
        const formData = new FormData();
        formData.append('chat_id', ChatState.currentChatId);
        formData.append('avatar', file);

        try {
            const res = await ChatAPI.updateAvatar(formData);
            if (res.ok) {
                ChatUI.showToast(_t('avatar_updated'));
                await loadDetailsPanelInfo();
                await loadAllConversations();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast(_t('error_avatar'), true);
        }
    };

    window.updateGroupInfo = async () => {
        const name = document.querySelector('#editGroupNameInput').value.trim();
        const desc = document.querySelector('#editGroupDescInput').value.trim();
        
        if (name === '') return;

        try {
            const res = await ChatAPI.updateGroup(ChatState.currentChatId, { name, description: desc });
            if (res.ok) {
                ChatUI.showToast(_t('group_details_updated'));
                await loadDetailsPanelInfo();
                await loadAllConversations();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast(_t('error_network'), true);
        }
    };

    window.updateGroupPermissions = async () => {
        const invite = document.querySelector('#setInvitePerm').value;
        const message = document.querySelector('#setMsgPerm').value;

        try {
            const res = await ChatAPI.updateGroup(ChatState.currentChatId, {
                invite_permission: invite,
                message_permission: message
            });
            if (res.ok) {
                ChatUI.showToast(_t('permissions_updated'));
                await loadDetailsPanelInfo();
            }
        } catch (e) {
            ChatUI.showToast(_t('error_generic'), true);
        }
    };

    window.toggleGroupMute = async (isMuted) => {
        try {
            let res;
            if (isMuted) {
                res = await ChatAPI.unmuteChat(ChatState.currentChatId);
            } else {
                res = await ChatAPI.muteChat(ChatState.currentChatId, -1);
            }

            if (res.ok) {
                ChatUI.showToast(isMuted ? _t('notifications_unmuted') : _t('notifications_muted'));
                await loadDetailsPanelInfo();
                await loadAllConversations();
            }
        } catch (e) {
            ChatUI.showToast(_t('error_mute'), true);
        }
    };

    window.toggleArchiveGroupChat = async () => {
        try {
            const res = await ChatAPI.archiveChat(ChatState.currentChatId);
            if (res.ok) {
                ChatUI.showToast(_t('group_archived'));
                ChatState.resetActiveChat();
                stopActiveChatUI();
                await toggleDetailsPanel();
                await loadAllConversations();
            }
        } catch (e) {
            ChatUI.showToast(_t('error_archive'), true);
        }
    };

    window.leaveGroupChat = async () => {
        if (!confirm(_t('confirm_leave_group'))) return;
        
        try {
            const res = await ChatAPI.leaveChat(ChatState.currentChatId);
            if (res.ok) {
                ChatUI.showToast(_t('left_group'));
                ChatState.resetActiveChat();
                stopActiveChatUI();
                await toggleDetailsPanel();
                await loadAllConversations();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast(_t('error_generic'), true);
        }
    };

    // --- DEBOUNCE E UTILS ---
    function debounce(fn, delay) {
        let timer = null;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    function renderSkeletonUI() {
        const box = document.querySelector('.chat-messages');
        if (box) {
            box.innerHTML = `
                <div class="chat-skeleton" style="height:50px;width:60%;margin-bottom:15px;border-radius:12px;"></div>
                <div class="chat-skeleton" style="height:80px;width:40%;align-self:flex-end;margin-bottom:15px;border-radius:12px;"></div>
                <div class="chat-skeleton" style="height:40px;width:70%;margin-bottom:15px;border-radius:12px;"></div>
            `;
        }
    }

    // --- CONTEXT MENUS ---
    window.showGroupContextMenu = function (e, msgId, isMine) {
        e.preventDefault();
        
        const menu = document.querySelector('#chatContextMenu');
        if (!menu) return;

        const msg = ChatState.messages.find(m => parseInt(m.id) === parseInt(msgId));
        const hasText = msg && (msg.body || msg.message);

        const standardEmojis = ['🔥', '💀', '👏', '😳', '⚡', '👀'];
        let quickReactionsHtml = '';

        standardEmojis.forEach(emoji => {
            quickReactionsHtml += `<button class="chat-quick-reaction-btn" onclick="window.addQuickReaction(event, ${msgId}, '${emoji}')" type="button">${emoji}</button>`;
        });

        if (window.CHAT_CUSTOM_EMOJIS) {
            window.CHAT_CUSTOM_EMOJIS.forEach(emoji => {
                quickReactionsHtml += `
                    <button class="chat-quick-reaction-btn chat-quick-reaction-btn--custom" onclick="window.addQuickReaction(event, ${msgId}, '${emoji.code}')" type="button" title="${emoji.code}">
                        <img src="${emoji.url}" alt="${emoji.code}" style="width:20px; height:20px; object-fit:contain; pointer-events:none; vertical-align:middle;" />
                    </button>
                `;
            });
        }

        const showLeftClass = (e.clientX + 180 + 240 > window.innerWidth) ? 'show-left' : '';

        let menuHtml = `
            <div class="chat-context-menu__item chat-context-menu__item--reactions-trigger">
                <span><i class="fa-regular fa-face-smile"></i> ${_t('add_reaction')}</span>
                <div class="chat-context-menu__reactions-submenu ${showLeftClass}">
                    ${quickReactionsHtml}
                </div>
            </div>
            <div class="chat-context-menu__divider"></div>
            <div class="chat-context-menu__item" onclick="window.enterReplyMode(${msgId})"><i class="fa-solid fa-reply"></i> ${_t('ctx_reply')}</div>
        `;
        
        if (hasText) {
            menuHtml += `<div class="chat-context-menu__item" onclick="window.copyMessageText(${msgId})"><i class="fa-solid fa-copy"></i> ${_t('ctx_copy')}</div>`;
        }
        
        menuHtml += `
            <div class="chat-context-menu__item" onclick="window.togglePrivatePin(${msgId})"><i class="fa-solid fa-thumbtack"></i> ${_t('ctx_pin')}</div>
        `;

        if (ChatState.currentChatType === 'private') {
            menuHtml += `<div class="chat-context-menu__item" onclick="window.toggleFavoriteMessage(${msgId})"><i class="fa-solid fa-star"></i> ${_t('ctx_favorite')}</div>`;
        }

        if (isMine) {
            menuHtml += `
                <div class="chat-context-menu__item" onclick="window.enterEditMode(${msgId})"><i class="fa-solid fa-pen"></i> ${_t('ctx_edit')}</div>
                <div class="chat-context-menu__item chat-context-menu__item--danger" onclick="window.triggerDeleteMessage(${msgId})"><i class="fa-solid fa-trash-can"></i> ${_t('ctx_delete_all')}</div>
            `;
        } else {
            // Se sono admin/owner in un gruppo, posso moderare ed eliminare
            if (ChatState.currentChatType === 'group') {
                const myRole = (ChatState.members || []).find(m => m.user_id === ChatState.myUserId)?.role;
                if (myRole === 'owner' || myRole === 'admin') {
                    menuHtml += `
                        <div class="chat-context-menu__item chat-context-menu__item--danger" onclick="window.triggerDeleteMessage(${msgId})"><i class="fa-solid fa-trash"></i> ${_t('ctx_moderate_delete')}</div>
                    `;
                }
            }
        }

        if (ChatState.currentChatType === 'private') {
            menuHtml += `
                <div class="chat-context-menu__item chat-context-menu__item--danger" onclick="window.deleteMessageForSelf(${msgId})"><i class="fa-solid fa-trash"></i> ${_t('ctx_delete_self')}</div>
            `;
        }

        menu.innerHTML = menuHtml;

        // Position menu off-screen to measure actual browser-rendered height
        menu.style.visibility = 'hidden';
        menu.style.display = 'block';
        menu.style.left = '-9999px';
        menu.style.top = '-9999px';

        const menuWidth = menu.offsetWidth;
        const menuHeight = menu.offsetHeight;

        menu.style.visibility = 'visible';

        const mouseX = e.clientX;
        const mouseY = e.clientY;

        let posX = mouseX;
        let posY = mouseY;

        // Screen boundary safety checks
        if (mouseX + menuWidth > window.innerWidth) {
            posX = window.innerWidth - menuWidth - 10;
        }
        if (mouseY + menuHeight > window.innerHeight) {
            posY = window.innerHeight - menuHeight - 10;
        }

        menu.style.left = posX + 'px';
        menu.style.top = posY + 'px';
        menu.style.right = 'auto';
    };

    window.toggleReaction = async function (e, msgId, emoji) {
        if (e) e.stopPropagation();
        
        try {
            let res;
            if (ChatState.currentChatType === 'group') {
                res = await ChatAPI.call('group_react.php', {
                    method: 'POST',
                    body: { message_id: msgId, reaction: emoji }
                });
            } else {
                // For private chats, check if user already reacted
                const msg = ChatState.messages.find(m => m.id === msgId);
                const hasReacted = msg && msg.reactions && msg.reactions.some(r => r.reaction === emoji && (r.user_reacted || r.mine));
                const action = hasReacted ? 'remove' : 'add';
                
                res = await ChatAPI.call('manage_reaction.php', {
                    method: 'POST',
                    body: { message_id: msgId, reaction: emoji, action: action }
                });
            }

            if (res.ok) {
                // Update local message reactions
                const msg = ChatState.messages.find(m => m.id === msgId);
                if (msg) {
                    msg.reactions = res.reactions || [];
                    ChatUI.renderMessages();
                }
            } else {
                ChatUI.showToast(res.error || _t('error_reaction'), true);
            }
        } catch (err) {
            console.error("Reaction error:", err);
        }
    };

    window.addQuickReaction = async function (e, msgId, emoji) {
        if (e) e.stopPropagation();
        
        const menu = document.querySelector('#chatContextMenu');
        if (menu) menu.style.display = 'none';

        await window.toggleReaction(null, msgId, emoji);
    };

    window.enterReplyMode = function (msgId) {
        const msg = ChatState.messages.find(m => m.id === msgId);
        if (!msg) return;
        ChatState.replyToId = msgId;
        const bar = document.querySelector('#chatReplyBar');
        bar.style.display = 'flex';
        bar.querySelector('.chat-reply-user').textContent = msg.sender_display_name || msg.sender_username;
        bar.querySelector('.chat-reply-text').textContent = msg.body || msg.message || _t('file_attachment');
    };

    window.copyMessageText = function (msgId) {
        const msg = ChatState.messages.find(m => m.id === msgId);
        if (msg && (msg.body || msg.message)) {
            navigator.clipboard.writeText(msg.body || msg.message);
            ChatUI.showToast(_t('text_copied'));
        }
    };

    window.toggleFavoriteMessage = async function (msgId) {
        try {
            const res = await ChatAPI.call('manage_message.php', {
                method: 'POST',
                body: { action: 'toggle_favorite', message_id: msgId }
            });
            if (res.ok) {
                ChatUI.showToast(res.favorited ? _t('added_favorite') : _t('removed_favorite'));
            }
        } catch (e) {
            console.error(e);
        }
    };

    window.deleteMessageForSelf = async function (msgId) {
        try {
            const res = await ChatAPI.call('manage_message.php', {
                method: 'POST',
                body: { action: 'delete_for_self', message_id: msgId }
            });
            if (res.ok) {
                ChatState.messages = ChatState.messages.filter(m => m.id !== msgId);
                ChatUI.renderMessages();
                ChatUI.showToast(_t('msg_removed_self'));
            }
        } catch (e) {}
    };

    window.enterEditMode = function (msgId) {
        const msg = ChatState.messages.find(m => m.id === msgId);
        if (!msg) return;

        ChatState.editMessageId = msgId;
        const textarea = document.querySelector('#chatTextarea');
        textarea.value = msg.body || msg.message || '';
        textarea.focus();

        const replyBar = document.querySelector('#chatReplyBar');
        replyBar.querySelector('.chat-reply-user').textContent = _t('edit_message');
        replyBar.querySelector('.chat-reply-text').textContent = msg.body || msg.message || '';
        replyBar.style.display = 'flex';
    };

    window.triggerDeleteMessage = async function (msgId) {
        if (!confirm(_t('confirm_delete_msg'))) return;
        
        try {
            let res;
            if (ChatState.currentChatType === 'group') {
                res = await ChatAPI.deleteMessage(msgId);
            } else {
                res = await ChatAPI.deletePrivateMessage(msgId);
            }

            if (res.ok) {
                ChatUI.showToast(_t('msg_deleted'));
                ChatState.messages = ChatState.messages.filter(m => m.id !== msgId);
                ChatUI.renderMessages();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast(_t('error_connection'), true);
        }
    };

    window.togglePrivatePin = async function (msgId) {
        try {
            const res = await ChatAPI.call('manage_message.php', {
                method: 'POST',
                body: { action: 'toggle_pin', message_id: msgId }
            });
            if (res.ok) {
                ChatUI.showToast(res.pinned ? _t('msg_pinned') : _t('msg_unpinned'));
                if (ChatState.isDetailsOpen) {
                    await loadDetailsPanelInfo();
                }
            }
        } catch (e) {
            console.error(e);
        }
    };

    function cancelReplyMode() {
        ChatState.replyToId = null;
        ChatState.editMessageId = null;
        const bar = document.querySelector('#chatReplyBar');
        if (bar) bar.style.display = 'none';
        const textarea = document.querySelector('#chatTextarea');
        if (textarea) textarea.value = '';
    }

    // --- OTHER FALLBACK UI FUNCTIONS FOR PRIVATE CHATS ---
    window.updateLocalPrivateNickname = async function (val) {
        try {
            const res = await ChatAPI.call('update_chat_settings.php', {
                method: 'POST',
                body: { conversation_id: ChatState.currentChatId, nickname: val }
            });
            if (res.ok) {
                ChatUI.showToast(_t('nickname_updated'));
                await loadAllConversations();
                document.querySelector('#chatHeaderName').textContent = val || ChatState.getChatFromList('private', ChatState.currentChatId).other_username;
            }
        } catch (e) {}
    };

    window.togglePrivateMute = async function (isMuted) {
        try {
            const action = isMuted ? 'unmute' : 'mute';
            const res = await ChatAPI.call('privacy_settings.php', {
                method: 'POST',
                body: { action: action, conversation_id: ChatState.currentChatId }
            });
            if (res.ok) {
                ChatUI.showToast(isMuted ? _t('notifications_unmuted') : _t('notifications_muted'));
                await loadDetailsPanelInfo();
                await loadAllConversations();
            }
        } catch (e) {}
    };

    window.togglePrivateArchive = async function (isArchived) {
        try {
            const action = isArchived ? 'unarchive' : 'archive';
            const res = await ChatAPI.call('privacy_settings.php', {
                method: 'POST',
                body: { action: action, conversation_id: ChatState.currentChatId }
            });
            if (res.ok) {
                ChatUI.showToast(isArchived ? _t('conv_restored') : _t('conv_archived'));
                await loadAllConversations();
                if (!isArchived) {
                    ChatState.resetActiveChat();
                    stopActiveChatUI();
                    toggleDetailsPanel();
                } else {
                    await loadDetailsPanelInfo();
                }
            }
        } catch (e) {}
    };

    async function startNewPrivateConversation(otherUserId) {
        ChatState.setActiveChat('private', 0, otherUserId);
        
        document.querySelector('#chatHeaderName').textContent = _t('new_conversation');
        const avatar = document.querySelector('#chatHeaderAvatar');
        avatar.src = `/includes/get_pfp.php?id=${otherUserId}`;
        avatar.style.display = 'block';
        document.querySelector('#chatHeaderStatus').textContent = _t('ready_to_send');
        document.querySelector('.chat-messages').innerHTML = `<div class="text-center py-5 text-muted my-auto">${_t('send_to_start')}</div>`;
        document.querySelector('.chat-shell').classList.add('is-chat-open');
    }

    async function openCreateGroupWith(otherUserId) {
        await ChatUI.openCreateGroupModal();
        const chk = document.querySelector(`#chk-fr-${otherUserId}`);
        if (chk) chk.checked = true;
    }

    // --- COMPOSER INPUT RESIZING ---
    function handleComposerKeydown(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            triggerMessageSend();
        }
    }

    function handleComposerInput(e) {
        e.target.style.height = 'auto';
        e.target.style.height = (e.target.scrollHeight - 10) + 'px';
    }

    window.scrollToMessage = function (id) {
        const el = document.getElementById(`msg-${id}`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('chat-msg-highlight');
            setTimeout(() => el.classList.remove('chat-msg-highlight'), 2000);
        }
    };
})();
