// assets/chat/chat-ui.js
// UI Rendering module for Cripsum™ Group & Private Chat.

const ChatUI = {
    // DOM Cache
    listEl: null,
    messagesEl: null,
    headerNameEl: null,
    headerAvatarEl: null,
    headerStatusEl: null,
    textareaEl: null,
    replyBarEl: null,
    detailsEl: null,
    detailsScrollEl: null,
    toastEl: null,

    init() {
        this.listEl = document.querySelector('.chat-list');
        this.messagesEl = document.querySelector('.chat-messages');
        this.headerNameEl = document.querySelector('#chatHeaderName');
        this.headerAvatarEl = document.querySelector('#chatHeaderAvatar');
        this.headerStatusEl = document.querySelector('#chatHeaderStatus');
        this.textareaEl = document.querySelector('#chatTextarea');
        this.replyBarEl = document.querySelector('#chatReplyBar');
        this.detailsEl = document.querySelector('.chat-details');
        this.detailsScrollEl = document.querySelector('.chat-details__scroll');
        this.toastEl = document.querySelector('#chatToast');

        // Setup Create Group modal button in sidebar header if missing
        this.setupGroupCreationButton();
        this.injectGroupModalHTML();
    },

    setupGroupCreationButton() {
        const titleRow = document.querySelector('.chat-sidebar__title');
        if (titleRow && !document.querySelector('#createGroupBtn')) {
            const btn = document.createElement('button');
            btn.id = 'createGroupBtn';
            btn.className = 'chat-action-btn';
            btn.type = 'button';
            btn.title = 'Crea gruppo';
            btn.style.marginLeft = 'auto';
            btn.innerHTML = '<i class="fa-solid fa-plus" style="color:var(--chat-text-main) !important;"></i>';
            btn.addEventListener('click', () => this.openCreateGroupModal());
            titleRow.appendChild(btn);
        }
    },

    showToast(message, isError = false) {
        if (!this.toastEl) return;
        this.toastEl.textContent = message;
        this.toastEl.style.background = isError ? 'rgba(239, 68, 68, 0.9)' : 'rgba(139, 92, 246, 0.9)';
        this.toastEl.style.color = '#fff';
        this.toastEl.style.padding = '12px 20px';
        this.toastEl.style.borderRadius = '10px';
        this.toastEl.style.opacity = '1';
        this.toastEl.style.pointerEvents = 'auto';
        
        setTimeout(() => {
            this.toastEl.style.opacity = '0';
            this.toastEl.style.pointerEvents = 'none';
        }, 3000);
    },

    // --- RENDER CONVERSATION LIST ---
    renderConversations() {
        if (!this.listEl) return;
        
        const groups = ChatState.conversations.filter(c => c.chat_id !== undefined);
        const privates = ChatState.conversations.filter(c => c.conversation_id !== undefined);
        const invites = ChatState.invites || [];

        if (groups.length === 0 && privates.length === 0 && invites.length === 0) {
            this.listEl.innerHTML = `<div class="text-center py-5 text-muted" style="color:var(--chat-text-muted) !important;"><i class="fa-solid fa-comments fs-2 mb-2"></i><br>Nessuna conversazione trovata</div>`;
            return;
        }

        let html = '';

        // 1. Invites Section
        if (invites.length > 0) {
            html += `<div class="chat-section-label" style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--chat-text-muted);padding:10px 20px;letter-spacing:0.5px;">Inviti di gruppo</div>`;
            invites.forEach(inv => {
                const avatar = inv.chat_avatar || '/assets/static/img/default-group.png';
                html += `
                    <div class="chat-item chat-item--invite" style="border-left: 3px solid var(--chat-accent);background:rgba(139, 92, 246, 0.04);margin-bottom:6px;border-radius:0 10px 10px 0;">
                        <div class="chat-item__avatar-container">
                            <img class="chat-item__avatar" src="${avatar}" style="background:#1e293b;border:2px solid rgba(255,255,255,0.05);width:48px;height:48px;border-radius:50%;object-fit:cover;">
                        </div>
                        <div class="chat-item__info">
                            <div class="chat-item__name-row">
                                <span class="chat-item__name" style="font-weight:700;">${escapeHtml(inv.chat_name)}</span>
                            </div>
                            <div style="font-size:11px;color:var(--chat-text-muted);margin:4px 0;">Invito da @${escapeHtml(inv.inviter_username)}</div>
                            <div style="display:flex;gap:8px;margin-top:6px;">
                                <button class="btn btn-sm py-1 btn-primary" onclick="acceptGroupInvite(${inv.chat_id})" style="background:var(--chat-accent);border:none;font-size:11px;font-weight:700;border-radius:6px;padding:3px 10px;">Accetta</button>
                                <button class="btn btn-sm py-1 btn-secondary" onclick="declineGroupInvite(${inv.chat_id})" style="background:rgba(255,255,255,0.05);border:1px solid var(--chat-border);color:white;font-size:11px;font-weight:700;border-radius:6px;padding:3px 10px;">Rifiuta</button>
                            </div>
                        </div>
                    </div>
                `;
            });
        }

        // 2. Groups Section
        if (groups.length > 0) {
            html += `<div class="chat-section-label" style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--chat-text-muted);padding:10px 20px;letter-spacing:0.5px;">Gruppi</div>`;
            groups.forEach(g => {
                const isActive = (ChatState.currentChatType === 'group' && ChatState.currentChatId === g.chat_id) ? 'is-active' : '';
                const isUnread = g.unread_count > 0 ? 'is-unread' : '';
                const avatar = g.avatar_url || '/assets/static/img/default-group.png';
                const time = g.last_message_time ? formatTime(g.last_message_time) : '';
                
                let preview = 'Nessun messaggio';
                if (g.last_message_body) {
                    preview = g.last_message_type === 'system' ? g.last_message_body : `@${g.last_message_sender_username}: ${g.last_message_body}`;
                }

                html += `
                    <div class="chat-item ${isActive} ${isUnread}" data-type="group" data-id="${g.chat_id}" onclick="selectChat('group', ${g.chat_id})">
                        <div class="chat-item__avatar-container">
                            <img class="chat-item__avatar" src="${avatar}" alt="${escapeHtml(g.name)}" style="background:#1e293b;border:2px solid rgba(255,255,255,0.05);width:48px;height:48px;border-radius:50%;object-fit:cover;">
                        </div>
                        <div class="chat-item__info">
                            <div class="chat-item__name-row">
                                <span class="chat-item__name" style="font-weight:700;">${escapeHtml(g.name)}</span>
                                <span class="chat-item__time" style="color:var(--chat-text-muted) !important;">${time}</span>
                            </div>
                            <div class="chat-item__msg-row">
                                <span class="chat-item__preview" style="color:var(--chat-text-muted) !important;">${escapeHtml(preview)}</span>
                                ${g.is_muted ? '<i class="fa-solid fa-bell-slash chat-item__mute" style="color:var(--chat-text-muted) !important;"></i>' : ''}
                                ${g.unread_count > 0 ? `<span class="chat-item__badge">${g.unread_count}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
        }

        // 3. Private Chats Section
        if (privates.length > 0) {
            html += `<div class="chat-section-label" style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--chat-text-muted);padding:10px 20px;letter-spacing:0.5px;">Messaggi Diretti</div>`;
            privates.forEach(p => {
                const isActive = (ChatState.currentChatType === 'private' && ChatState.currentChatId === p.conversation_id) ? 'is-active' : '';
                const isUnread = p.unread_count > 0 ? 'is-unread' : '';
                const onlineClass = p.is_online ? 'is-online' : '';
                const nickname = p.other_nickname || p.other_username;
                const time = p.last_message_time ? formatTime(p.last_message_time) : '';
                const preview = p.last_message_text || 'Nessun messaggio';

                html += `
                    <div class="chat-item ${isActive} ${isUnread}" data-type="private" data-id="${p.conversation_id}" onclick="selectChat('private', ${p.conversation_id}, ${p.other_user_id})">
                        <div class="chat-item__avatar-container">
                            <img class="chat-item__avatar" src="/includes/get_pfp.php?id=${p.other_user_id}" alt="${escapeHtml(nickname)}" style="background:#1e293b;border:2px solid rgba(255,255,255,0.05);width:48px;height:48px;border-radius:50%;object-fit:cover;">
                            <span class="chat-item__status ${onlineClass}"></span>
                        </div>
                        <div class="chat-item__info">
                            <div class="chat-item__name-row">
                                <span class="chat-item__name" style="font-weight:700;">${escapeHtml(nickname)}</span>
                                <span class="chat-item__time" style="color:var(--chat-text-muted) !important;">${time}</span>
                            </div>
                            <div class="chat-item__msg-row">
                                <span class="chat-item__preview" style="color:var(--chat-text-muted) !important;">${escapeHtml(preview)}</span>
                                ${p.is_muted ? '<i class="fa-solid fa-bell-slash chat-item__mute" style="color:var(--chat-text-muted) !important;"></i>' : ''}
                                ${p.unread_count > 0 ? `<span class="chat-item__badge">${p.unread_count}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
        }

        this.listEl.innerHTML = html;
    },

    // --- RENDER MESSAGES LOG ---
    renderMessages() {
        if (!this.messagesEl) return;
        
        if (ChatState.messages.length === 0) {
            this.messagesEl.innerHTML = `<div class="text-center py-5 text-muted my-auto" style="color:var(--chat-text-muted) !important;"><i class="fa-regular fa-paper-plane fs-1 mb-3" style="color:var(--chat-accent) !important;opacity:0.8;"></i><br>Nessun messaggio presente. Scrivi il primo messaggio!</div>`;
            return;
        }

        let html = '';
        let lastDateLabel = '';

        ChatState.messages.forEach(msg => {
            const isSent = msg.sender_id === ChatState.myUserId;
            
            // Format separator date
            const dateStr = formatDateLabel(msg.created_at);
            if (dateStr !== lastDateLabel) {
                html += `<div class="chat-date-separator" style="color:var(--chat-text-muted) !important;">${dateStr}</div>`;
                lastDateLabel = dateStr;
            }

            // System Message Rendering
            if (msg.message_type === 'system') {
                html += `
                    <div class="chat-system-message-row" style="text-align:center;margin:10px 0;font-size:12px;color:var(--chat-text-muted) !important;font-style:italic;">
                        <i class="fa-solid fa-circle-info" style="color:var(--chat-accent) !important;margin-right:6px;"></i>
                        ${escapeHtml(msg.body || '')}
                    </div>
                `;
                return;
            }

            // Regular Message Rendering
            const wrapperClass = isSent ? 'chat-msg-wrapper--sent' : 'chat-msg-wrapper--recv';
            const formattedTime = formatTime(msg.created_at);
            const senderName = msg.sender_display_name || msg.sender_username;
            const messageBody = parseMessageText(msg.body || '');

            html += `
                <div class="chat-msg-wrapper ${wrapperClass}" id="msg-${msg.id}" oncontextmenu="showGroupContextMenu(event, ${msg.id}, ${isSent})" style="max-width:70%;position:relative;display:flex;flex-direction:column;margin-bottom:8px;">
                    ${!isSent && ChatState.currentChatType === 'group' ? `<small style="font-size:11px;font-weight:700;color:var(--chat-accent);margin-bottom:4px;margin-left:8px;">${escapeHtml(senderName)}</small>` : ''}
                    <div class="chat-msg-bubble" style="padding:12px 16px;border-radius:12px;font-size:14.5px;line-height:1.5;box-shadow:0 4px 15px rgba(0,0,0,0.15);">
                        ${messageBody}
                    </div>
                    <div class="chat-msg-meta" style="font-size:10px;color:var(--chat-text-muted) !important;margin-top:4px;display:flex;align-items:center;gap:6px;justify-content:${isSent ? 'flex-end' : 'flex-start'}">
                        <span>${formattedTime}</span>
                        ${msg.edited_at ? '<span style="font-style:italic;opacity:0.7;">(modificato)</span>' : ''}
                    </div>
                </div>
            `;
        });

        this.messagesEl.innerHTML = html;
        scrollToBottom();
    },

    // --- RENDER SIDEBAR DETAILS ---
    renderDetails(data) {
        if (!this.detailsScrollEl) return;
        
        if (ChatState.currentChatType === 'private') {
            // Render old private details panel layout
            renderPrivateDetailsUI(data);
            return;
        }

        const chat = data.chat;
        const settings = data.settings;
        const myMembership = data.my_membership;
        
        const isOwner = myMembership.role === 'owner';
        const isAdmin = myMembership.role === 'admin';
        const isMuted = myMembership.is_muted;

        let membersHtml = ChatState.members.map(m => {
            const roleBadge = m.role === 'owner' ? '<span style="font-size:10px;background:#f59e0b;color:black;padding:2px 6px;border-radius:4px;font-weight:800;margin-left:auto;">Owner</span>' :
                             m.role === 'admin' ? '<span style="font-size:10px;background:var(--chat-accent);color:white;padding:2px 6px;border-radius:4px;font-weight:800;margin-left:auto;">Admin</span>' : '';
            
            // Build action buttons for owner/admin
            let kickBtn = '';
            let promoteBtn = '';
            
            if (m.user_id !== ChatState.myUserId) {
                if (isOwner) {
                    kickBtn = `<button class="chat-action-btn" onclick="kickGroupMember(${m.user_id})" title="Espelli"><i class="fa-solid fa-user-xmark" style="color:#ef4444 !important;font-size:14px;"></i></button>`;
                    if (m.role === 'member') {
                        promoteBtn = `<button class="chat-action-btn" onclick="promoteGroupAdmin(${m.user_id})" title="Promuovi ad Admin"><i class="fa-solid fa-arrow-up" style="color:var(--chat-online) !important;font-size:14px;"></i></button>`;
                    } else if (m.role === 'admin') {
                        promoteBtn = `<button class="chat-action-btn" onclick="demoteGroupAdmin(${m.user_id})" title="Rimuovi Admin"><i class="fa-solid fa-arrow-down" style="color:#f59e0b !important;font-size:14px;"></i></button>`;
                    }
                } else if (isAdmin && m.role === 'member') {
                    kickBtn = `<button class="chat-action-btn" onclick="kickGroupMember(${m.user_id})" title="Espelli"><i class="fa-solid fa-user-xmark" style="color:#ef4444 !important;font-size:14px;"></i></button>`;
                }
            }

            const avatar = `/includes/get_pfp.php?id=${m.user_id}`;
            const onlineStyle = m.is_online ? 'border: 2px solid var(--chat-online);' : 'border: 2px solid rgba(255,255,255,0.05);';

            return `
                <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--chat-border);">
                    <img src="${avatar}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;${onlineStyle}">
                    <div>
                        <div style="font-size:13px;font-weight:700;color:var(--chat-text-main) !important;">${escapeHtml(m.display_name || m.username)}</div>
                        <div style="font-size:10px;color:var(--chat-text-muted) !important;">@${escapeHtml(m.username)}</div>
                    </div>
                    ${roleBadge}
                    <div style="display:flex;gap:4px;margin-left:auto;">
                        ${promoteBtn}
                        ${kickBtn}
                    </div>
                </div>
            `;
        }).join('');

        this.detailsScrollEl.innerHTML = `
            <div class="chat-details__profile">
                <div style="position:relative;display:inline-block;">
                    <img class="chat-details__avatar" src="${chat.avatar_url || '/assets/static/img/default-group.png'}" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,0.05);">
                    ${isOwner || isAdmin ? `<label for="groupAvatarUpload" style="position:absolute;bottom:0;right:0;background:var(--chat-accent);width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 10px rgba(0,0,0,0.5);"><i class="fa-solid fa-camera" style="font-size:12px;color:white;"></i></label><input type="file" id="groupAvatarUpload" style="display:none;" onchange="uploadGroupAvatar(this)">` : ''}
                </div>
                <div class="chat-details__name" style="color:var(--chat-text-main) !important;font-size:18px;font-weight:800;margin-top:10px;">${escapeHtml(chat.name)}</div>
                <div style="font-size:12px;color:var(--chat-text-muted) !important;margin-top:4px;">${escapeHtml(chat.description || 'Nessuna descrizione')}</div>
            </div>

            <div class="chat-details__section">
                <div class="chat-details__section-title" style="color:var(--chat-text-muted) !important;font-size:11px;font-weight:700;text-transform:uppercase;">Personalizzazione Gruppo</div>
                ${isOwner || isAdmin ? `
                    <div style="display:flex;flex-direction:column;gap:12px;margin-top:10px;">
                        <div>
                            <label style="font-size:11px;color:var(--chat-text-muted);display:block;margin-bottom:6px;">Nome Gruppo</label>
                            <input type="text" id="editGroupNameInput" class="chat-details-input" value="${escapeHtml(chat.name)}" placeholder="Modifica nome..." onchange="updateGroupInfo()">
                        </div>
                        <div>
                            <label style="font-size:11px;color:var(--chat-text-muted);display:block;margin-bottom:6px;">Descrizione</label>
                            <textarea id="editGroupDescInput" class="chat-details-input" rows="2" placeholder="Modifica descrizione..." onchange="updateGroupInfo()">${escapeHtml(chat.description || '')}</textarea>
                        </div>
                    </div>
                ` : `<div style="font-size:12px;color:var(--chat-text-muted);margin-top:6px;">Contatta il proprietario o gli amministratori per modificare i dettagli.</div>`}
            </div>

            <div class="chat-details__section">
                <div class="chat-details__section-title" style="color:var(--chat-text-muted) !important;font-size:11px;font-weight:700;text-transform:uppercase;">Partecipanti (${ChatState.members.length})</div>
                <div style="margin-top:10px;max-height:250px;overflow-y:auto;padding-right:4px;">
                    ${membersHtml}
                </div>
                ${isOwner || isAdmin || settings.invite_permission === 'everyone' ? `
                    <button class="chat-details-btn chat-details-btn--primary" onclick="openInviteUsersModal()" style="margin-top:12px;">
                        <i class="fa-solid fa-user-plus"></i> Invita Utente
                    </button>
                ` : ''}
            </div>

            ${isOwner ? `
                <div class="chat-details__section">
                    <div class="chat-details__section-title" style="color:var(--chat-text-muted) !important;font-size:11px;font-weight:700;text-transform:uppercase;">Permessi Gruppo</div>
                    <div style="display:flex;flex-direction:column;gap:10px;margin-top:10px;">
                        <div>
                            <label style="font-size:11px;color:var(--chat-text-muted);display:block;margin-bottom:4px;">Chi può invitare</label>
                            <select class="chat-details-input" id="setInvitePerm" onchange="updateGroupPermissions()" style="background:#18181b;color:white;border-radius:10px;padding:6px;">
                                <option value="everyone" ${settings.invite_permission === 'everyone' ? 'selected' : ''}>Tutti</option>
                                <option value="owner_admins" ${settings.invite_permission === 'owner_admins' ? 'selected' : ''}>Proprietario & Admin</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:11px;color:var(--chat-text-muted);display:block;margin-bottom:4px;">Chi può scrivere</label>
                            <select class="chat-details-input" id="setMsgPerm" onchange="updateGroupPermissions()" style="background:#18181b;color:white;border-radius:10px;padding:6px;">
                                <option value="members" ${settings.message_permission === 'members' ? 'selected' : ''}>Tutti i membri</option>
                                <option value="admins_only" ${settings.message_permission === 'admins_only' ? 'selected' : ''}>Solo Amministratori</option>
                            </select>
                        </div>
                    </div>
                </div>
            ` : ''}

            <div class="chat-details__section" style="display:flex;flex-direction:column;gap:10px;">
                <div class="chat-details__section-title" style="color:var(--chat-text-muted) !important;font-size:11px;font-weight:700;text-transform:uppercase;">Azioni</div>
                <button class="chat-details-btn chat-details-btn--secondary" onclick="toggleGroupMute(${isMuted})">
                    <i class="fa-solid ${isMuted ? 'fa-bell' : 'fa-bell-slash'}"></i>
                    ${isMuted ? 'Riattiva Notifiche' : 'Silenzia Notifiche'}
                </button>
                <button class="chat-details-btn chat-details-btn--secondary" onclick="toggleArchiveGroupChat()">
                    <i class="fa-solid fa-box-archive"></i>
                    Archivia Chat
                </button>
                <button class="chat-details-btn" onclick="leaveGroupChat()" style="background:rgba(239, 68, 68, 0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.3);">
                    <i class="fa-solid fa-right-from-bracket"></i> Lascia Gruppo
                </button>
            </div>
        `;
    },

    // --- GROUP CREATION MODAL ---
    injectGroupModalHTML() {
        if (document.querySelector('#createGroupModal')) return;
        
        const modal = document.createElement('div');
        modal.id = 'createGroupModal';
        modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:99999;display:none;align-items:center;justify-content:center;backdrop-filter:blur(20px);transition:opacity 0.25s;opacity:0;';
        
        modal.innerHTML = `
            <div class="modal-dialog" style="width:100%;max-width:500px;background:var(--chat-panel-bg);border:1px solid var(--chat-border);border-radius:16px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,0.5);margin:20px;">
                <div style="display:flex;align-items:center;justify-content:between;padding:20px;border-bottom:1px solid var(--chat-border);">
                    <h5 style="margin:0;font-weight:800;color:var(--chat-text-main);">Crea Gruppo Chat</h5>
                    <button class="chat-action-btn" onclick="closeCreateGroupModal()" style="margin-left:auto;"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div style="padding:20px;max-height:60vh;overflow-y:auto;display:flex;flex-direction:column;gap:15px;">
                    <div>
                        <label style="font-size:12px;color:var(--chat-text-muted);display:block;margin-bottom:6px;">Nome Gruppo *</label>
                        <input type="text" id="newGroupNameInput" class="chat-details-input" placeholder="Inserisci il nome del gruppo...">
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--chat-text-muted);display:block;margin-bottom:6px;">Descrizione (opzionale)</label>
                        <textarea id="newGroupDescInput" class="chat-details-input" rows="2" placeholder="Inserisci descrizione del gruppo..."></textarea>
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--chat-text-muted);display:block;margin-bottom:6px;">Seleziona partecipanti (almeno 1) *</label>
                        <div id="friendsChecklist" style="max-height:180px;overflow-y:auto;border:1px solid var(--chat-border);border-radius:10px;padding:10px;background:rgba(0,0,0,0.2);">
                            <!-- Loaded via API -->
                        </div>
                    </div>
                </div>
                <div style="padding:15px 20px;background:rgba(0,0,0,0.1);display:flex;justify-content:flex-end;gap:10px;border-top:1px solid var(--chat-border);">
                    <button class="chat-details-btn chat-details-btn--secondary" onclick="closeCreateGroupModal()" style="width:auto;min-width:100px;">Annulla</button>
                    <button class="chat-details-btn chat-details-btn--primary" onclick="submitCreateGroup()" style="width:auto;min-width:100px;">Crea</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);

        // Inject Invite Modal as well
        const inviteModal = document.createElement('div');
        inviteModal.id = 'inviteUsersModal';
        inviteModal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:99999;display:none;align-items:center;justify-content:center;backdrop-filter:blur(20px);transition:opacity 0.25s;opacity:0;';
        inviteModal.innerHTML = `
            <div class="modal-dialog" style="width:100%;max-width:450px;background:var(--chat-panel-bg);border:1px solid var(--chat-border);border-radius:16px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,0.5);margin:20px;">
                <div style="display:flex;align-items:center;justify-content:between;padding:20px;border-bottom:1px solid var(--chat-border);">
                    <h5 style="margin:0;font-weight:800;color:var(--chat-text-main);">Invita nel gruppo</h5>
                    <button class="chat-action-btn" onclick="closeInviteUsersModal()" style="margin-left:auto;"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div style="padding:20px;max-height:50vh;overflow-y:auto;">
                    <div id="inviteFriendsChecklist" style="max-height:220px;overflow-y:auto;border:1px solid var(--chat-border);border-radius:10px;padding:10px;background:rgba(0,0,0,0.2);">
                        <!-- Loaded via API -->
                    </div>
                </div>
                <div style="padding:15px 20px;background:rgba(0,0,0,0.1);display:flex;justify-content:flex-end;gap:10px;border-top:1px solid var(--chat-border);">
                    <button class="chat-details-btn chat-details-btn--secondary" onclick="closeInviteUsersModal()" style="width:auto;min-width:100px;">Chiudi</button>
                    <button class="chat-details-btn chat-details-btn--primary" onclick="submitInviteUsers()" style="width:auto;min-width:100px;">Invita</button>
                </div>
            </div>
        `;
        document.body.appendChild(inviteModal);
    },

    async openCreateGroupModal() {
        const modal = document.querySelector('#createGroupModal');
        if (!modal) return;
        
        // Fetch friends list to invite
        const friendsBox = document.querySelector('#friendsChecklist');
        friendsBox.innerHTML = '<p class="text-muted" style="font-size:12px;text-align:center;">Caricamento amici...</p>';
        
        modal.style.display = 'flex';
        setTimeout(() => modal.style.opacity = '1', 50);

        try {
            const res = await fetch(`/api/social/friends.php`).then(r => r.json());
            if (res.ok && res.all && res.all.length > 0) {
                friendsBox.innerHTML = res.all.map(f => `
                    <div style="display:flex;align-items:center;gap:10px;padding:6px 0;">
                        <input type="checkbox" name="createGroupInviteCheck" value="${f.id}" id="chk-fr-${f.id}" style="width:16px;height:16px;accent-color:var(--chat-accent);">
                        <img src="/includes/get_pfp.php?id=${f.id}" style="width:26px;height:26px;border-radius:50%;object-fit:cover;">
                        <label for="chk-fr-${f.id}" style="font-size:13px;color:white;cursor:pointer;user-select:none;">${escapeHtml(f.display_name || f.username)} (@${escapeHtml(f.username)})</label>
                    </div>
                `).join('');
            } else {
                friendsBox.innerHTML = '<p class="text-muted" style="font-size:12px;text-align:center;">Nessun amico trovato.</p>';
            }
        } catch (e) {
            friendsBox.innerHTML = '<p class="text-muted" style="font-size:12px;text-align:center;color:#ef4444;">Errore di caricamento.</p>';
        }
    },

    closeCreateGroupModal() {
        const modal = document.querySelector('#createGroupModal');
        if (!modal) return;
        modal.style.opacity = '0';
        setTimeout(() => modal.style.display = 'none', 250);
        // Clear inputs
        document.querySelector('#newGroupNameInput').value = '';
        document.querySelector('#newGroupDescInput').value = '';
    },

    async openInviteUsersModal() {
        const modal = document.querySelector('#inviteUsersModal');
        if (!modal) return;
        
        const checklist = document.querySelector('#inviteFriendsChecklist');
        checklist.innerHTML = '<p class="text-muted" style="font-size:12px;text-align:center;">Caricamento...</p>';
        
        modal.style.display = 'flex';
        setTimeout(() => modal.style.opacity = '1', 50);

        try {
            // Load friends, but filter out people already in group
            const res = await fetch(`/api/social/friends.php`).then(r => r.json());
            if (res.ok && res.all && res.all.length > 0) {
                // Filter friends who are NOT members
                const nonMembers = res.all.filter(f => !ChatState.members.some(m => m.user_id === f.id));
                
                if (nonMembers.length > 0) {
                    checklist.innerHTML = nonMembers.map(f => `
                        <div style="display:flex;align-items:center;gap:10px;padding:6px 0;">
                            <input type="checkbox" name="inviteGroupCheck" value="${f.id}" id="chk-inv-${f.id}" style="width:16px;height:16px;accent-color:var(--chat-accent);">
                            <img src="/includes/get_pfp.php?id=${f.id}" style="width:26px;height:26px;border-radius:50%;object-fit:cover;">
                            <label for="chk-inv-${f.id}" style="font-size:13px;color:white;cursor:pointer;user-select:none;">${escapeHtml(f.display_name || f.username)} (@${escapeHtml(f.username)})</label>
                        </div>
                    `).join('');
                } else {
                    checklist.innerHTML = '<p class="text-muted" style="font-size:12px;text-align:center;">Tutti i tuoi amici fanno già parte di questo gruppo.</p>';
                }
            } else {
                checklist.innerHTML = '<p class="text-muted" style="font-size:12px;text-align:center;">Nessun amico trovato.</p>';
            }
        } catch (e) {
            checklist.innerHTML = '<p class="text-muted" style="font-size:12px;text-align:center;color:#ef4444;">Errore di caricamento.</p>';
        }
    },

    closeInviteUsersModal() {
        const modal = document.querySelector('#inviteUsersModal');
        if (!modal) return;
        modal.style.opacity = '0';
        setTimeout(() => modal.style.display = 'none', 250);
    }
};

// --- GLOBAL EXPOSED CALLBACKS ---
window.closeCreateGroupModal = () => ChatUI.closeCreateGroupModal();
window.closeInviteUsersModal = () => ChatUI.closeInviteUsersModal();
window.openInviteUsersModal = () => ChatUI.openInviteUsersModal();

// Helpers
function formatTime(timestamp) {
    if (!timestamp) return '';
    const date = new Date(timestamp);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function formatDateLabel(timestamp) {
    const d = new Date(timestamp);
    const today = new Date();
    if (d.toDateString() === today.toDateString()) {
        return "Oggi";
    }
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) {
        return "Ieri";
    }
    return d.toLocaleDateString([], { day: 'numeric', month: 'long', year: 'numeric' });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function parseMessageText(text) {
    if (!text) return '';
    let escaped = escapeHtml(text);
    // Convert links
    escaped = escaped.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
    return escaped;
}

function scrollToBottom() {
    const box = document.querySelector('.chat-messages');
    if (box) {
        box.scrollTop = box.scrollHeight;
    }
}
