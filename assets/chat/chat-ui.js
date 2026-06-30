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
                const avatar = inv.chat_avatar || '/img/Susremaster.png';
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
                const avatar = g.avatar_url || '/img/Susremaster.png';
                const time = g.last_message_time ? formatTime(g.last_message_time) : '';
                
                let preview = 'Nessun messaggio';
                if (g.last_message_id) {
                    const sender = parseInt(g.last_message_sender_id) === ChatState.myUserId ? 'Tu' : `@${g.last_message_sender_username}`;
                    if (g.last_message_type === 'system') {
                        preview = g.last_message_body || 'Notifica di sistema';
                    } else if (g.last_message_type === 'gif') {
                        preview = `${sender}: [GIF]`;
                    } else if (g.last_message_type === 'media' || !g.last_message_body) {
                        preview = `${sender}: [Allegato]`;
                    } else {
                        preview = `${sender}: ${g.last_message_body}`;
                    }
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
                
                let preview = 'Nessun messaggio';
                if (p.last_message_id) {
                    const sender = parseInt(p.last_message_sender_id) === ChatState.myUserId ? 'Tu' : `@${p.other_username}`;
                    if (p.last_message_type === 'system') {
                        preview = p.last_message_text || 'Notifica di sistema';
                    } else if (p.last_message_type === 'gif') {
                        preview = `${sender}: [GIF]`;
                    } else if (p.last_message_type === 'media' || !p.last_message_text) {
                        const attType = p.last_message_attachment_type || 'Allegato';
                        preview = `${sender}: [${attType.toUpperCase()}]`;
                    } else {
                        preview = `${sender}: ${p.last_message_text}`;
                    }
                }

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

    // --- RENDER MESSAGES LOG (GLOBAL CHAT STYLE MATCHING chat-v2.css) ---
    renderMessages(forceScroll = false) {
        if (!this.messagesEl) return;
        
        if (ChatState.messages.length === 0) {
            this.messagesEl.innerHTML = `<div class="text-center py-5 text-muted my-auto" style="color:var(--chat-text-muted) !important;"><i class="fa-regular fa-paper-plane fs-1 mb-3" style="color:var(--chat-accent) !important;opacity:0.8;"></i><br>Nessun messaggio presente. Scrivi il primo messaggio!</div>`;
            return;
        }

        // If first load, clear the container
        if (ChatState.firstLoad) {
            this.messagesEl.innerHTML = '';
        } else {
            // Remove empty placeholder if it exists
            const placeholder = this.messagesEl.querySelector('.text-muted.my-auto');
            if (placeholder) placeholder.remove();
        }

        // Determine if we should scroll to bottom
        const box = this.messagesEl;
        const isNearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 200;
        const shouldScroll = forceScroll || isNearBottom || ChatState.firstLoad;

        // Clean up deleted messages from the DOM
        const renderedBubbles = this.messagesEl.querySelectorAll('.chat-bubble-wrap');
        renderedBubbles.forEach(wrap => {
            const wrapId = parseInt(wrap.id.replace('msg-', ''));
            if (!ChatState.messages.some(m => m.id === wrapId)) {
                const article = wrap.closest('.chat-message');
                if (article) article.remove();
            }
        });

        let lastDateLabel = '';
        const dayElements = this.messagesEl.querySelectorAll('.chat-day span');
        if (dayElements.length > 0) {
            lastDateLabel = dayElements[dayElements.length - 1].textContent;
        }

        let prevMsg = null;
        if (ChatState.messages.length > 0) {
            const lastMsgEl = this.messagesEl.querySelector('.chat-message:last-of-type .chat-bubble-wrap');
            if (lastMsgEl) {
                const lastId = parseInt(lastMsgEl.id.replace('msg-', ''));
                prevMsg = ChatState.messages.find(m => m.id === lastId);
            }
        }

        ChatState.messages.forEach(msg => {
            const isMine = parseInt(msg.sender_id) === ChatState.myUserId;
            
            // GIF & text content rendering
            const body = msg.body || msg.message || '';
            let messageContent = '';
            if (msg.message_type === 'gif') {
                let mediaUrl = msg.media_url;
                let mediaTitle = msg.media_title || 'GIF';
                if (!mediaUrl && msg.metadata) {
                    mediaUrl = msg.metadata.media_url;
                    mediaTitle = msg.metadata.media_title || 'GIF';
                }
                if (mediaUrl) {
                    let preview = mediaUrl;
                    if (preview.includes('giphy.com')) {
                        preview = preview
                            .replace('/fixed_width_small.gif', '/giphy.gif')
                            .replace('/fixed_height_small.gif', '/giphy.gif')
                            .replace('/fixed_width_small.webp', '/giphy.webp')
                            .replace('/fixed_height_small.webp', '/giphy.webp')
                            .replace('/100w.gif', '/giphy.gif')
                            .replace('/200w.gif', '/giphy.gif')
                            .replace('/200.gif', '/giphy.gif');
                    }
                    const caption = body ? `<figcaption>${parseMessageText(body)}</figcaption>` : '';
                    messageContent = `<figure class="chat-gif-message"><a href="${mediaUrl}" target="_blank" rel="noopener noreferrer"><img src="${preview}" alt="${escapeHtml(mediaTitle)}" loading="lazy"></a>${caption}</figure>`;
                } else {
                    messageContent = parseMessageText(body);
                }
            } else {
                messageContent = parseMessageText(body);
            }

            let attachmentsHtml = '';
            if (msg.attachments && msg.attachments.length > 0) {
                attachmentsHtml = `<div class="chat-msg-attachments" style="margin-top:4px; display:flex; flex-direction:column; gap:6px;">` + msg.attachments.map(att => {
                    if (att.file_type === 'image') {
                        return `<img class="chat-attachment-image" src="${att.file_path}" alt="${escapeHtml(att.file_name)}" onclick="openMediaPreview('${att.file_path}', 'image')" style="max-width:100%; max-height:360px; object-fit:cover; border-radius:8px; cursor:pointer;">`;
                    } else if (att.file_type === 'video') {
                        return `<video class="chat-attachment-video" src="${att.file_path}" controls style="max-width:100%; max-height:360px; border-radius:8px;"></video>`;
                    } else if (att.file_type === 'audio') {
                        return `<audio src="${att.file_path}" controls style="max-width:100%;"></audio>`;
                    } else {
                        return `
                            <a class="chat-attachment-file" href="${att.file_path}" download="${escapeHtml(att.file_name)}" style="display:flex; align-items:center; gap:8px; padding:6px 10px; background:rgba(0,0,0,0.2); border:1px solid var(--chat-border); border-radius:8px; color:white; text-decoration:none;">
                                <i class="fa-solid fa-file-arrow-down" style="font-size:16px;"></i>
                                <span style="font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(att.file_name)}</span>
                            </a>
                        `;
                    }
                }).join('') + `</div>`;
            }

            const existingWrap = document.getElementById(`msg-${msg.id}`);

            // 1. If message is already in DOM, reconcile differences
            if (existingWrap) {
                const contentEl = existingWrap.querySelector('.chat-message-content');
                if (contentEl) {
                    const newHtml = messageContent + attachmentsHtml;
                    if (contentEl.innerHTML !== newHtml) {
                        contentEl.innerHTML = newHtml;
                    }
                }

                // Update reactions
                let reactionsHtml = '';
                if (msg.reactions && msg.reactions.length > 0) {
                    reactionsHtml = msg.reactions.map(r => {
                        const reactionStr = r.reaction || r.emoji;
                        const countVal = r.count || r.total;
                        const didIReact = r.user_reacted || r.mine;
                        const badgeClass = ['chat-reaction-badge', didIReact ? 'user-reacted' : ''].filter(Boolean).join(' ');
                        const titleText = escapeHtml(r.usernames || (didIReact ? 'Tu' : ''));
                        return `
                            <span class="${badgeClass}" title="${titleText}" onclick="window.toggleReaction(event, ${msg.id}, '${reactionStr}')">
                                <span class="chat-reaction-emoji">${reactionStr}</span>
                                <span class="chat-reaction-count">${countVal}</span>
                            </span>
                        `;
                    }).join('');
                }

                let reactionsContainer = existingWrap.querySelector('.chat-msg-reactions');
                if (reactionsHtml) {
                    if (!reactionsContainer) {
                        reactionsContainer = document.createElement('div');
                        reactionsContainer.className = 'chat-msg-reactions';
                        existingWrap.appendChild(reactionsContainer);
                    }
                    if (reactionsContainer.innerHTML !== reactionsHtml) {
                        reactionsContainer.innerHTML = reactionsHtml;
                    }
                } else if (reactionsContainer) {
                    reactionsContainer.remove();
                }

                // Reconcile edit status
                const isEdited = msg.edited_at || msg.is_edited;
                const bubble = existingWrap.closest('.chat-message');
                if (bubble) {
                    const timeEl = bubble.querySelector('.chat-time');
                    if (timeEl && isEdited && !timeEl.querySelector('.chat-edited')) {
                        const editedSpan = document.createElement('span');
                        editedSpan.className = 'chat-edited';
                        editedSpan.textContent = ' modificato';
                        timeEl.appendChild(editedSpan);
                    }
                }

                prevMsg = msg;
                return;
            }

            // 2. Otherwise render and append the new message
            const dateStr = formatDateLabel(msg.created_at);
            if (dateStr !== lastDateLabel) {
                const dayDiv = document.createElement('div');
                dayDiv.className = 'chat-day';
                dayDiv.innerHTML = `<span>${escapeHtml(dateStr)}</span>`;
                this.messagesEl.appendChild(dayDiv);
                lastDateLabel = dateStr;
                prevMsg = null;
            }

            if (msg.message_type === 'system') {
                const sysDiv = document.createElement('div');
                sysDiv.style.cssText = "text-align:center; margin:12px 0; font-size:12px; color:var(--chat-text-muted); font-style:italic;";
                sysDiv.innerHTML = `
                    <i class="fa-solid fa-circle-info" style="color:var(--chat-accent); margin-right:6px;"></i>
                    ${escapeHtml(msg.body || msg.message || '')}
                `;
                this.messagesEl.appendChild(sysDiv);
                prevMsg = null;
                return;
            }

            let isConsecutive = false;
            if (prevMsg && parseInt(prevMsg.sender_id) === parseInt(msg.sender_id)) {
                const prevTime = parseUtcDate(prevMsg.created_at).getTime();
                const currTime = parseUtcDate(msg.created_at).getTime();
                if (!Number.isNaN(prevTime) && !Number.isNaN(currTime)) {
                    if (Math.abs(currTime - prevTime) < 180000) {
                        isConsecutive = true;
                    }
                }
            }

            const formattedTime = formatTime(msg.created_at);
            const senderName = msg.sender_display_name || msg.sender_username;
            const classes = ['chat-message', isMine ? 'is-mine' : '', isConsecutive ? 'is-consecutive' : ''].filter(Boolean).join(' ');
            const avatar = `<a class="chat-avatar-link" href="#"><img class="chat-avatar" src="/includes/get_pfp.php?id=${msg.sender_id}" alt=""></a>`;
            const editedText = msg.edited_at || msg.is_edited ? '<span class="chat-edited">modificato</span>' : '';

            // Render reactions if present
            let reactionsHtml = '';
            if (msg.reactions && msg.reactions.length > 0) {
                reactionsHtml = `<div class="chat-msg-reactions">` + msg.reactions.map(r => {
                    const reactionStr = r.reaction || r.emoji;
                    const countVal = r.count || r.total;
                    const didIReact = r.user_reacted || r.mine;
                    const badgeClass = ['chat-reaction-badge', didIReact ? 'user-reacted' : ''].filter(Boolean).join(' ');
                    const titleText = escapeHtml(r.usernames || (didIReact ? 'Tu' : ''));
                    return `
                        <span class="${badgeClass}" title="${titleText}" onclick="window.toggleReaction(event, ${msg.id}, '${reactionStr}')">
                            <span class="chat-reaction-emoji">${reactionStr}</span>
                            <span class="chat-reaction-count">${countVal}</span>
                        </span>
                    `;
                }).join('') + `</div>`;
            }

            const main = `
                <div class="chat-message-main">
                    <div class="chat-message-meta">
                        <a class="chat-username" href="#">@${escapeHtml(senderName)}</a>
                        <time class="chat-time">${formattedTime}${editedText}</time>
                    </div>
                    <div class="chat-bubble-wrap" id="msg-${msg.id}" oncontextmenu="showGroupContextMenu(event, ${msg.id}, ${isMine})">
                        <div class="chat-bubble ${msg.message_type === 'gif' ? 'chat-bubble--pure-gif' : ''}">
                            <div class="chat-message-content">${messageContent}${attachmentsHtml}</div>
                        </div>
                        ${reactionsHtml}
                    </div>
                </div>
            `;

            const article = document.createElement('article');
            article.className = classes;
            article.innerHTML = isMine ? main + avatar : avatar + main;
            this.messagesEl.appendChild(article);

            prevMsg = msg;
        });

        if (shouldScroll) {
            scrollToBottom();
            setTimeout(scrollToBottom, 50);
            setTimeout(scrollToBottom, 150);
            setTimeout(scrollToBottom, 300);
            ChatState.firstLoad = false;
        }
    },

    // --- RENDER SIDEBAR DETAILS ---
    renderDetails(data) {
        if (!this.detailsScrollEl) return;
        
        if (ChatState.currentChatType === 'private') {
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
                    <img class="chat-details__avatar" src="${chat.avatar_url || '/img/Susremaster.png'}" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,0.05);">
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

    // --- RENDER GIFS ---
    renderGifs(gifs, append = false) {
        const grid = document.querySelector('#chatGifGrid');
        if (!grid) return;
        
        const html = gifs.map(g => `
            <div class="chat-gif-item" onclick="sendGifMessage('${g.url}', '${g.title || 'GIF'}')" style="cursor:pointer; overflow:hidden; border-radius:6px; height:80px; position:relative; background:rgba(0,0,0,0.1);">
                <img src="${g.preview_url || g.url}" style="width:100%; height:100%; object-fit:cover;" loading="lazy">
            </div>
        `).join('');
        
        if (append) {
            grid.innerHTML += html;
        } else {
            grid.innerHTML = html;
        }
    },

    // --- GROUP CREATION MODAL ---
    // --- GROUP CREATION MODAL ---
    injectGroupModalHTML() {
        if (document.querySelector('#createGroupModal')) return;
        
        const modal = document.createElement('div');
        modal.id = 'createGroupModal';
        modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(10,10,12,0.9);z-index:99999;display:none;align-items:center;justify-content:center;backdrop-filter:blur(8px);transition:opacity 0.25s;opacity:0;';
        
        modal.innerHTML = `
            <div class="chat-modal-dialog" style="width:100%;max-width:500px;background:var(--chat-panel-bg);border:1px solid var(--chat-border);border-radius:16px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,0.5);margin:20px;pointer-events:auto !important;">
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

        const inviteModal = document.createElement('div');
        inviteModal.id = 'inviteUsersModal';
        inviteModal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(10,10,12,0.9);z-index:99999;display:none;align-items:center;justify-content:center;backdrop-filter:blur(8px);transition:opacity 0.25s;opacity:0;';
        inviteModal.innerHTML = `
            <div class="chat-modal-dialog" style="width:100%;max-width:450px;background:var(--chat-panel-bg);border:1px solid var(--chat-border);border-radius:16px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,0.5);margin:20px;pointer-events:auto !important;">
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
        
        const friendsBox = document.querySelector('#friendsChecklist');
        friendsBox.innerHTML = '<p class="text-muted" style="font-size:12px;text-align:center;">Caricamento amici...</p>';
        
        modal.style.display = 'flex';
        setTimeout(() => modal.style.opacity = '1', 50);

        try {
            const res = await fetch(`/api/social/friends.php`).then(r => r.json());
            const friends = (res.success && res.data && res.data.all) ? res.data.all : [];
            
            if (friends.length > 0) {
                friendsBox.innerHTML = friends.map(f => `
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
        
        const nameInput = document.querySelector('#newGroupNameInput');
        if (nameInput) nameInput.value = '';
        const descInput = document.querySelector('#newGroupDescInput');
        if (descInput) descInput.value = '';
    },

    async openInviteUsersModal() {
        const modal = document.querySelector('#inviteUsersModal');
        if (!modal) return;
        
        const checklist = document.querySelector('#inviteFriendsChecklist');
        checklist.innerHTML = '<p class="text-muted" style="font-size:12px;text-align:center;">Caricamento...</p>';
        
        modal.style.display = 'flex';
        setTimeout(() => modal.style.opacity = '1', 50);

        try {
            const res = await fetch(`/api/social/friends.php`).then(r => r.json());
            const friends = (res.success && res.data && res.data.all) ? res.data.all : [];
            
            if (friends.length > 0) {
                const nonMembers = friends.filter(f => !ChatState.members.some(m => m.user_id === f.id));
                
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

window.closeCreateGroupModal = () => ChatUI.closeCreateGroupModal();
window.closeInviteUsersModal = () => ChatUI.closeInviteUsersModal();
window.openInviteUsersModal = () => ChatUI.openInviteUsersModal();
window.formatDateTime = formatDateTime;

// Helpers
function formatDateTime(timeStr) {
    const date = parseUtcDate(timeStr);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleString('it-IT', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}
function parseUtcDate(dateString) {
    if (!dateString) return new Date(NaN);
    let cleanStr = String(dateString).replace(' ', 'T');
    return new Date(cleanStr);
}

function formatTime(timestamp) {
    if (!timestamp) return '';
    const date = parseUtcDate(timestamp);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
}

function formatDateLabel(timestamp) {
    const d = parseUtcDate(timestamp);
    if (Number.isNaN(d.getTime())) return '';
    const today = new Date();
    if (d.toDateString() === today.toDateString()) {
        return "Oggi";
    }
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) {
        return "Ieri";
    }
    return d.toLocaleDateString('it-IT', { day: 'numeric', month: 'long', year: 'numeric' });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function parseMessageText(text) {
    if (!text) return '';
    let escaped = escapeHtml(text);
    escaped = escaped.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
    return escaped;
}

function scrollToBottom() {
    const box = document.querySelector('.chat-messages');
    if (box) {
        box.scrollTop = box.scrollHeight;
    }
}

// Media Preview Lightbox
window.openMediaPreview = function(path, type) {
    let previewModal = document.querySelector('#mediaPreviewModal');
    if (!previewModal) {
        previewModal = document.createElement('div');
        previewModal.id = 'mediaPreviewModal';
        previewModal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.95);z-index:999999;display:none;align-items:center;justify-content:center;backdrop-filter:blur(10px);';
        previewModal.innerHTML = `
            <button class="chat-action-btn" onclick="closeMediaPreview()" style="position:absolute; top:20px; right:20px; font-size:24px; color:white; background:none; border:none; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
            <div id="mediaPreviewContent" style="max-width:90%; max-height:85vh; display:flex; align-items:center; justify-content:center;"></div>
        `;
        document.body.appendChild(previewModal);
    }

    const contentBox = previewModal.querySelector('#mediaPreviewContent');
    if (type === 'image') {
        contentBox.innerHTML = `<img src="${path}" style="max-width:100%; max-height:85vh; object-fit:contain; border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,0.5);">`;
    } else {
        contentBox.innerHTML = `<video src="${path}" controls autoplay style="max-width:100%; max-height:85vh; border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,0.5);"></video>`;
    }

    previewModal.style.display = 'flex';
};

window.closeMediaPreview = function() {
    const previewModal = document.querySelector('#mediaPreviewModal');
    if (previewModal) {
        previewModal.style.display = 'none';
        const contentBox = previewModal.querySelector('#mediaPreviewContent');
        if (contentBox) {
            contentBox.innerHTML = '';
        }
    }
};

// Fallback private panel with Pinned and Media gallery
function renderPrivateDetailsUI(data) {
    const box = document.querySelector('.chat-details__scroll');
    if (!box) return;

    const otherUser = data.participants.find(p => p.id !== ChatState.myUserId);
    const nickname = otherUser ? (otherUser.nickname || otherUser.username) : 'Utente';
    const isMuted = !!data.settings.is_muted;
    const isArchived = !!data.settings.is_archived;

    // 1. Pinned Messages Html
    let pinnedHtml = '';
    if (data.pinned_messages && data.pinned_messages.length > 0) {
        pinnedHtml = data.pinned_messages.map(m => `
            <div class="chat-details-pinned-item" onclick="scrollToMessage(${m.message_id})" style="cursor:pointer; padding:8px 10px; background:rgba(255,255,255,0.03); border:1px solid var(--chat-border); border-radius:8px; margin-bottom:6px; transition: background 0.2s;">
                <div style="display:flex; justify-content:space-between; font-size:10px; color:var(--chat-accent); margin-bottom:4px; font-weight:700;">
                    <span>@${escapeHtml(m.sender_username)}</span>
                    <span>${formatTime(m.created_at)}</span>
                </div>
                <div style="font-size:12px; color:var(--chat-text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(m.message || '[File]')}</div>
            </div>
        `).join('');
    } else {
        pinnedHtml = `<div style="font-size:11px; color:var(--chat-text-muted); text-align:center; padding:10px 0;">Nessun messaggio fissato</div>`;
    }

    // 2. Gallery Media Html
    let mediaHtml = '';
    if (data.gallery && data.gallery.media && data.gallery.media.length > 0) {
        mediaHtml = `
            <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:6px; margin-top:8px;">
                ${data.gallery.media.map(m => {
                    if (m.file_type === 'image') {
                        return `<div class="chat-gallery-item" onclick="openMediaPreview('${m.file_path}', 'image')" style="aspect-ratio:1; border-radius:6px; overflow:hidden; cursor:pointer; background:rgba(0,0,0,0.2);"><img src="${m.file_path}" style="width:100%; height:100%; object-fit:cover;"></div>`;
                    } else {
                        return `<div class="chat-gallery-item" onclick="openMediaPreview('${m.file_path}', 'video')" style="aspect-ratio:1; border-radius:6px; overflow:hidden; cursor:pointer; background:rgba(0,0,0,0.2); position:relative; display:flex; align-items:center; justify-content:center;"><video src="${m.file_path}" style="width:100%; height:100%; object-fit:cover; pointer-events:none;"></video><i class="fa-solid fa-play" style="position:absolute; color:white; font-size:16px;"></i></div>`;
                    }
                }).join('')}
            </div>
        `;
    } else {
        mediaHtml = `<div style="font-size:11px; color:var(--chat-text-muted); text-align:center; padding:10px 0;">Nessun media condiviso</div>`;
    }

    // 3. Gallery Files Html
    let filesHtml = '';
    if (data.gallery && data.gallery.files && data.gallery.files.length > 0) {
        filesHtml = data.gallery.files.map(f => {
            const sizeKb = Math.round(f.file_size / 1024);
            return `
                <a href="${f.file_path}" target="_blank" style="display:flex; align-items:center; gap:10px; padding:6px 0; border-bottom:1px solid var(--chat-border); text-decoration:none;">
                    <i class="fa-solid fa-file-lines" style="color:var(--chat-accent); font-size:18px;"></i>
                    <div style="flex:1; overflow:hidden;">
                        <div style="font-size:12px; color:var(--chat-text-main); font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(f.file_name)}</div>
                        <div style="font-size:10px; color:var(--chat-text-muted);">${sizeKb} KB</div>
                    </div>
                    <i class="fa-solid fa-download" style="color:var(--chat-text-muted);"></i>
                </a>
            `;
        }).join('');
    } else {
        filesHtml = `<div style="font-size:11px; color:var(--chat-text-muted); text-align:center; padding:10px 0;">Nessun documento condiviso</div>`;
    }

    box.innerHTML = `
        <div class="chat-details__profile">
            <img class="chat-details__avatar" src="/includes/get_pfp.php?id=${otherUser.id}" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,0.05);">
            <div class="chat-details__name" style="color:var(--chat-text-main) !important;font-size:18px;font-weight:800;margin-top:10px;">${escapeHtml(nickname)}</div>
            <div style="font-size:12px;color:var(--chat-text-muted) !important;">@${escapeHtml(otherUser.username)}</div>
        </div>
        
        <div class="chat-details__section">
            <div class="chat-details__section-title" style="color:var(--chat-text-muted) !important;font-size:11px;font-weight:700;text-transform:uppercase;">Personalizzazione</div>
            <div>
                <label style="font-size:12px;color:var(--chat-text-muted);margin-bottom:6px;display:block;">Nickname locale</label>
                <input type="text" id="settingNicknameInput" class="chat-details-input" value="${escapeHtml(otherUser.nickname || '')}" placeholder="Imposta nickname..." onchange="updateLocalPrivateNickname(this.value)">
            </div>
        </div>

        <div class="chat-details__section">
            <div class="chat-details__section-title" style="color:var(--chat-text-muted) !important;font-size:11px;font-weight:700;text-transform:uppercase;">Messaggi Fissati</div>
            <div style="margin-top:8px;">${pinnedHtml}</div>
        </div>

        <div class="chat-details__section">
            <div class="chat-details__section-title" style="color:var(--chat-text-muted) !important;font-size:11px;font-weight:700;text-transform:uppercase;">Media Condivisi</div>
            <div>${mediaHtml}</div>
        </div>

        <div class="chat-details__section">
            <div class="chat-details__section-title" style="color:var(--chat-text-muted) !important;font-size:11px;font-weight:700;text-transform:uppercase;">Documenti Condivisi</div>
            <div style="margin-top:8px;">${filesHtml}</div>
        </div>

        <div class="chat-details__section" style="display:flex;flex-direction:column;gap:10px;">
            <div class="chat-details__section-title" style="color:var(--chat-text-muted) !important;font-size:11px;font-weight:700;text-transform:uppercase;">Azioni</div>
            <button class="chat-details-btn chat-details-btn--secondary" onclick="togglePrivateMute(${isMuted})">
                <i class="fa-solid ${isMuted ? 'fa-bell' : 'fa-bell-slash'}"></i>
                ${isMuted ? 'Riattiva Notifiche' : 'Silenzia Notifiche'}
            </button>
            <button class="chat-details-btn ${isArchived ? 'chat-details-btn--secondary' : 'chat-details-btn--primary'}" onclick="togglePrivateArchive(${isArchived})">
                <i class="fa-solid ${isArchived ? 'fa-box-open' : 'fa-box-archive'}"></i>
                ${isArchived ? 'Ripristina Chat' : 'Archivia Chat'}
            </button>
        </div>
    `;
}
