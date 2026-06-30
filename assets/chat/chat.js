// assets/chat/chat.js
// SPA Orchestrator Engine for Cripsum™ Group & Private Chat.

(function () {
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
        await loadAllConversations();
        startRealtimePolling();

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
            console.error("Errore caricamento lista chat:", e);
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

        // --- GIPHY & EMOJIS COMPOSER EVENTS ---
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
                ChatUI.showToast(res.error || "Impossibile caricare i messaggi.", true);
            }
        } catch (e) {
            console.error("Errore caricamento messaggi:", e);
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
            statusEl.textContent = "Gruppo di chat";
            statusEl.className = 'chat-area__user-status';
        } else {
            const nickname = chat.other_nickname || chat.other_username;
            nameEl.textContent = nickname;
            avatarEl.src = `/includes/get_pfp.php?id=${chat.other_user_id}`;
            avatarEl.style.display = 'block';
            statusEl.textContent = chat.is_online ? "Online" : "Offline";
            statusEl.className = 'chat-area__user-status' + (chat.is_online ? ' is-online' : '');
        }
    }

    function stopActiveChatUI() {
        document.querySelector('#chatHeaderName').textContent = "Seleziona una chat";
        const avatar = document.querySelector('#chatHeaderAvatar');
        avatar.src = "";
        avatar.style.display = 'none';
        document.querySelector('#chatHeaderStatus').textContent = "";
        document.querySelector('.chat-messages').innerHTML = `<div class="text-center py-5 text-muted my-auto"><i class="fa-regular fa-paper-plane fs-1 mb-3" style="color:var(--chat-accent) !important;opacity:0.8;"></i><br>Scegli un utente o un gruppo per iniziare a chattare.</div>`;
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
                    ChatUI.showToast("Messaggio modificato.");
                } else {
                    ChatUI.showToast(res.error || "Errore durante la modifica.", true);
                }
            } catch (e) {
                ChatUI.showToast("Errore di connessione.", true);
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
                ChatUI.showToast(res.error || "Impossibile inviare il messaggio.", true);
            }
        } catch (e) {
            ChatUI.showToast("Errore di connessione.", true);
        }
    }

    // --- GIPHY CALLS ---
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
            console.error("Giphy load error:", e);
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
            console.error("Giphy load more error:", e);
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
                ChatUI.showToast(res.error || "Impossibile inviare la GIF.", true);
            }
        } catch (e) {
            ChatUI.showToast("Errore di rete.", true);
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
            console.error("Errore dettagli chat:", e);
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
            ChatUI.showToast("Seleziona prima una chat per inviare file.", true);
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

        ChatUI.showToast("Caricamento file in corso...");

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
                ChatUI.showToast("File inviato con successo!");
                await loadAllConversations();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast("Errore durante il caricamento del file.", true);
        }
    }

    // --- SUBMIT GROUP / INVITE ---
    async function submitCreateGroup() {
        const nameInput = document.querySelector('#newGroupNameInput');
        const descInput = document.querySelector('#newGroupDescInput');
        const name = nameInput.value.trim();
        const desc = descInput.value.trim();

        if (name === '') {
            ChatUI.showToast("Il nome del gruppo è obbligatorio.", true);
            return;
        }

        const checkboxes = document.querySelectorAll('input[name="createGroupInviteCheck"]:checked');
        const invitedUsers = Array.from(checkboxes).map(chk => parseInt(chk.value));

        if (invitedUsers.length === 0) {
            ChatUI.showToast("Seleziona almeno un partecipante da invitare.", true);
            return;
        }

        try {
            const res = await ChatAPI.createGroup(name, desc, invitedUsers);
            if (res.ok) {
                ChatUI.showToast("Gruppo creato con successo!");
                ChatUI.closeCreateGroupModal();
                await loadAllConversations();
                if (res.chat_id) {
                    selectChat('group', res.chat_id);
                }
            } else {
                ChatUI.showToast(res.error || "Impossibile creare il gruppo.", true);
            }
        } catch (e) {
            ChatUI.showToast("Errore di connessione.", true);
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
            
            ChatUI.showToast(`Invitati ${successCount} utenti nel gruppo.`);
            ChatUI.closeInviteUsersModal();
            await loadDetailsPanelInfo();
        } catch (e) {
            ChatUI.showToast("Errore durante l'invio degli inviti.", true);
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
                                statusEl.textContent = "sta scrivendo...";
                                statusEl.className = "chat-area__user-status is-typing";
                            } else if (res.other_online) {
                                statusEl.textContent = "Online";
                                statusEl.className = "chat-area__user-status is-online";
                            } else {
                                statusEl.textContent = res.other_last_seen ? "Ultimo accesso " + window.formatDateTime(res.other_last_seen) : "Offline";
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
                ChatUI.showToast("Invito accettato.");
                await loadAllConversations();
                selectChat('group', chatId);
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast("Errore di connessione.", true);
        }
    };

    window.declineGroupInvite = async (chatId) => {
        try {
            const res = await ChatAPI.declineInvite(chatId);
            if (res.ok) {
                ChatUI.showToast("Invito rifiutato.");
                await loadAllConversations();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast("Errore di connessione.", true);
        }
    };

    window.promoteGroupAdmin = async (memberId) => {
        try {
            const res = await ChatAPI.promoteAdmin(ChatState.currentChatId, memberId);
            if (res.ok) {
                ChatUI.showToast("Utente promosso ad admin.");
                await loadDetailsPanelInfo();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast("Errore.", true);
        }
    };

    window.demoteGroupAdmin = async (memberId) => {
        try {
            const res = await ChatAPI.demoteAdmin(ChatState.currentChatId, memberId);
            if (res.ok) {
                ChatUI.showToast("Privilegi admin revocati.");
                await loadDetailsPanelInfo();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast("Errore.", true);
        }
    };

    window.kickGroupMember = async (memberId) => {
        try {
            const res = await ChatAPI.removeMember(ChatState.currentChatId, memberId);
            if (res.ok) {
                ChatUI.showToast("Membro rimosso dal gruppo.");
                await loadDetailsPanelInfo();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast("Errore.", true);
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
                ChatUI.showToast("Avatar del gruppo aggiornato.");
                await loadDetailsPanelInfo();
                await loadAllConversations();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast("Errore caricamento avatar.", true);
        }
    };

    window.updateGroupInfo = async () => {
        const name = document.querySelector('#editGroupNameInput').value.trim();
        const desc = document.querySelector('#editGroupDescInput').value.trim();
        
        if (name === '') return;

        try {
            const res = await ChatAPI.updateGroup(ChatState.currentChatId, { name, description: desc });
            if (res.ok) {
                ChatUI.showToast("Dettagli gruppo modificati.");
                await loadDetailsPanelInfo();
                await loadAllConversations();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast("Errore di rete.", true);
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
                ChatUI.showToast("Permessi aggiornati.");
                await loadDetailsPanelInfo();
            }
        } catch (e) {
            ChatUI.showToast("Errore.", true);
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
                ChatUI.showToast(isMuted ? "Notifiche riattivate." : "Notifiche silenziate.");
                await loadDetailsPanelInfo();
                await loadAllConversations();
            }
        } catch (e) {
            ChatUI.showToast("Errore mute.", true);
        }
    };

    window.toggleArchiveGroupChat = async () => {
        try {
            const res = await ChatAPI.archiveChat(ChatState.currentChatId);
            if (res.ok) {
                ChatUI.showToast("Gruppo archiviato.");
                ChatState.resetActiveChat();
                stopActiveChatUI();
                await toggleDetailsPanel();
                await loadAllConversations();
            }
        } catch (e) {
            ChatUI.showToast("Errore archiviazione.", true);
        }
    };

    window.leaveGroupChat = async () => {
        if (!confirm("Sei sicuro di voler lasciare questo gruppo?")) return;
        
        try {
            const res = await ChatAPI.leaveChat(ChatState.currentChatId);
            if (res.ok) {
                ChatUI.showToast("Hai lasciato il gruppo.");
                ChatState.resetActiveChat();
                stopActiveChatUI();
                await toggleDetailsPanel();
                await loadAllConversations();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast("Errore.", true);
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

        const allowedEmojis = ['😭','🙏','🔥','💀','💯','😂','❤️','👍','👀','🗣️'];

        let menuHtml = `
            <div class="chat-context-menu__quick-reactions">
                ${allowedEmojis.map(emoji => `
                    <button class="chat-quick-reaction-btn" onclick="window.addQuickReaction(event, ${msgId}, '${emoji}')" type="button">${emoji}</button>
                `).join('')}
            </div>
            <div class="chat-context-menu__divider"></div>
            <div class="chat-context-menu__item" onclick="window.enterReplyMode(${msgId})"><i class="fa-solid fa-reply"></i> Rispondi</div>
        `;
        
        if (hasText) {
            menuHtml += `<div class="chat-context-menu__item" onclick="window.copyMessageText(${msgId})"><i class="fa-solid fa-copy"></i> Copia Testo</div>`;
        }
        
        menuHtml += `
            <div class="chat-context-menu__item" onclick="window.togglePrivatePin(${msgId})"><i class="fa-solid fa-thumbtack"></i> Fissa/Sfissa</div>
        `;

        if (ChatState.currentChatType === 'private') {
            menuHtml += `<div class="chat-context-menu__item" onclick="window.toggleFavoriteMessage(${msgId})"><i class="fa-solid fa-star"></i> Preferito</div>`;
        }

        if (isMine) {
            menuHtml += `
                <div class="chat-context-menu__item" onclick="window.enterEditMode(${msgId})"><i class="fa-solid fa-pen"></i> Modifica</div>
                <div class="chat-context-menu__item chat-context-menu__item--danger" onclick="window.triggerDeleteMessage(${msgId})"><i class="fa-solid fa-trash-can"></i> Elimina per tutti</div>
            `;
        } else {
            // Se sono admin/owner in un gruppo, posso moderare ed eliminare
            if (ChatState.currentChatType === 'group') {
                const myRole = ChatState.members.find(m => m.user_id === ChatState.myUserId)?.role;
                if (myRole === 'owner' || myRole === 'admin') {
                    menuHtml += `
                        <div class="chat-context-menu__item chat-context-menu__item--danger" onclick="window.triggerDeleteMessage(${msgId})"><i class="fa-solid fa-trash"></i> Modera ed Elimina</div>
                    `;
                }
            }
        }

        if (ChatState.currentChatType === 'private') {
            menuHtml += `
                <div class="chat-context-menu__item chat-context-menu__item--danger" onclick="window.deleteMessageForSelf(${msgId})"><i class="fa-solid fa-trash"></i> Rimuovi per me</div>
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
                ChatUI.showToast(res.error || "Impossibile aggiornare la reazione.", true);
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
        bar.querySelector('.chat-reply-text').textContent = msg.body || msg.message || "[File/Allegato]";
    };

    window.copyMessageText = function (msgId) {
        const msg = ChatState.messages.find(m => m.id === msgId);
        if (msg && (msg.body || msg.message)) {
            navigator.clipboard.writeText(msg.body || msg.message);
            ChatUI.showToast("Testo copiato negli appunti.");
        }
    };

    window.toggleFavoriteMessage = async function (msgId) {
        try {
            const res = await ChatAPI.call('manage_message.php', {
                method: 'POST',
                body: { action: 'toggle_favorite', message_id: msgId }
            });
            if (res.ok) {
                ChatUI.showToast(res.favorited ? "Aggiunto ai preferiti." : "Rimosso dai preferiti.");
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
                ChatUI.showToast("Messaggio rimosso per te.");
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
        replyBar.querySelector('.chat-reply-user').textContent = "Modifica Messaggio";
        replyBar.querySelector('.chat-reply-text').textContent = msg.body || msg.message || '';
        replyBar.style.display = 'flex';
    };

    window.triggerDeleteMessage = async function (msgId) {
        if (!confirm("Sei sicuro di voler eliminare questo messaggio?")) return;
        
        try {
            let res;
            if (ChatState.currentChatType === 'group') {
                res = await ChatAPI.deleteMessage(msgId);
            } else {
                res = await ChatAPI.deletePrivateMessage(msgId);
            }

            if (res.ok) {
                ChatUI.showToast("Messaggio eliminato.");
                ChatState.messages = ChatState.messages.filter(m => m.id !== msgId);
                ChatUI.renderMessages();
            } else {
                ChatUI.showToast(res.error, true);
            }
        } catch (e) {
            ChatUI.showToast("Errore di connessione.", true);
        }
    };

    window.togglePrivatePin = async function (msgId) {
        try {
            const res = await ChatAPI.call('manage_message.php', {
                method: 'POST',
                body: { action: 'toggle_pin', message_id: msgId }
            });
            if (res.ok) {
                ChatUI.showToast(res.pinned ? "Messaggio fissato." : "Messaggio sfissato.");
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
                ChatUI.showToast("Nickname aggiornato.");
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
                ChatUI.showToast(isMuted ? "Notifiche riattivate." : "Notifiche silenziate.");
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
                ChatUI.showToast(isArchived ? "Conversazione ripristinata." : "Conversazione archiviata.");
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
        
        document.querySelector('#chatHeaderName').textContent = "Nuova Conversazione";
        const avatar = document.querySelector('#chatHeaderAvatar');
        avatar.src = `/includes/get_pfp.php?id=${otherUserId}`;
        avatar.style.display = 'block';
        document.querySelector('#chatHeaderStatus').textContent = "Pronto a inviare";
        document.querySelector('.chat-messages').innerHTML = `<div class="text-center py-5 text-muted my-auto">Invia un messaggio per iniziare la chat privata.</div>`;
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
