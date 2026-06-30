/* assets/social/user-card.js - Discord-style User Card Overlay Engine */

(function () {
    let activeTrigger = null;

    document.addEventListener('DOMContentLoaded', () => {
        setupCardContainers();
        bindTriggers();
    });

    // Inject the profile overlay modal into the body
    function setupCardContainers() {
        if (!document.getElementById('socialUserCardOverlay')) {
            const overlay = document.createElement('div');
            overlay.id = 'socialUserCardOverlay';
            overlay.className = 'profile-nav-overlay';
            overlay.setAttribute('aria-hidden', 'true');
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.style.cssText = "position: fixed; inset: 0; z-index: 19000; visibility: hidden; pointer-events: none; display: flex; justify-content: center; align-items: center;";
            
            overlay.innerHTML = `
                <div class="profile-nav-overlay-backdrop js-close-user-card"></div>
                <div class="profile-nav-overlay-container" style="max-width: 340px; padding: 0; overflow: hidden; border: none; background: transparent; position: relative; animation: pop-in 0.2s cubic-bezier(0.18, 0.89, 0.32, 1.28); border-radius: 24px; max-height: 85vh; max-height: 85dvh; display: flex; flex-direction: column;">
                    <button class="profile-nav-overlay-close-btn js-close-user-card" style="z-index: 10; color: rgba(255, 255, 255, 0.7); right: 16px; top: 16px; font-size: 24px; background: transparent; border: none; cursor: pointer; position: absolute; transition: color 0.2s;">&times;</button>
                    <div id="userCardPopup" class="user-card" style="display: block; position: relative; margin: 0; top: 0; left: 0; box-shadow: none; width: 100%; animation: none; overflow-y: auto; max-height: 100%; scrollbar-width: thin;">
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);

            // Close when clicking backdrop or close button
            overlay.querySelectorAll('.js-close-user-card').forEach(el => {
                el.addEventListener('click', closeUserCard);
            });
        }

        // Close on ESC keypress
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeUserCard();
        });
    }

    // Attach click listener to all elements with the .user-card-trigger class
    function bindTriggers() {
        // Use event delegation on body to capture dynamically loaded elements (AJAX)
        document.body.addEventListener('click', (e) => {
            const trigger = e.target.closest('.user-card-trigger');
            if (trigger) {
                e.preventDefault();
                e.stopPropagation();
                activeTrigger = trigger;
                
                const userId = trigger.dataset.userId ? parseInt(trigger.dataset.userId) : (trigger.dataset.id ? parseInt(trigger.dataset.id) : 0);
                const username = trigger.dataset.username || '';
                
                if (userId === 0 && !username) {
                    console.warn("User Card: Ignored click because both userId and username are empty.");
                    return;
                }
                
                openUserCard(userId, username, trigger);
            }
        });
    }

    // Open the User Card using the site's native overlay system
    async function openUserCard(userId, username, triggerElement) {
        const overlay = document.getElementById('socialUserCardOverlay');
        const card = document.getElementById('userCardPopup');
        if (!overlay || !card) return;

        // 1. Show skeleton loader
        card.innerHTML = getSkeletonHtml();
        
        // 2. Open overlay
        overlay.style.visibility = 'visible';
        overlay.style.pointerEvents = 'auto';
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('profile-overlay-open');

        // 3. Fetch real data from the API
        const res = await SocialAPI.getUserCard(userId, username);
        if (res.success) {
            const html = renderCardHtml(res.data);
            card.innerHTML = html;
            applyHeritageStyles(card, res.data.style);
            
            // Bind button click handlers
            bindCardButtons(card, res.data);
        } else {
            card.innerHTML = `
                <div class="text-center py-5 text-danger" style="background: rgba(10, 10, 12, 0.95); border-radius: 24px; border: 1px solid rgba(255, 255, 255, 0.08);">
                    <i class="fa-solid fa-triangle-exclamation mb-2 fs-3"></i><br>
                    ${escapeHtml(res.error.message)}
                </div>
            `;
        }
    }

    // Close the User Card overlay
    function closeUserCard() {
        const overlay = document.getElementById('socialUserCardOverlay');
        if (overlay) {
            overlay.style.visibility = 'hidden';
            overlay.style.pointerEvents = 'none';
            overlay.classList.remove('is-visible');
            overlay.setAttribute('aria-hidden', 'true');
            
            // Remove body scrolling lock only if no other overlays are open
            const anyVisible = document.querySelector('.profile-nav-overlay.is-visible:not(#socialUserCardOverlay), .profile-report-modal.is-visible');
            if (!anyVisible) {
                document.body.classList.remove('profile-overlay-open');
            }
        }
        activeTrigger = null;
    }

    // Apply custom CSS theme variables from the viewed user's profile
    function applyHeritageStyles(element, style) {
        if (!style) return;

        element.classList.remove('custom-profile-inherited');
        element.style.removeProperty('--profile-accent-color');
        element.style.removeProperty('--profile-card-color');
        element.style.removeProperty('--profile-text-color');
        element.style.removeProperty('--profile-card-blur');
        element.style.removeProperty('--ring-color');

        const hasCustomTheme = style.accent_color || style.card_color;
        if (hasCustomTheme) {
            element.classList.add('custom-profile-inherited');
            
            if (style.accent_color) {
                element.style.setProperty('--profile-accent-color', style.accent_color);
            }
            if (style.card_color) {
                let color = style.card_color;
                if (style.card_opacity && color.startsWith('#')) {
                    const alpha = Math.round(parseFloat(style.card_opacity) * 255).toString(16).padStart(2, '0');
                    color = color + alpha;
                }
                element.style.setProperty('--profile-card-color', color);
            }
            if (style.text_color) {
                element.style.setProperty('--profile-text-color', style.text_color);
            }
            if (style.card_blur) {
                element.style.setProperty('--profile-card-blur', style.card_blur + 'px');
            }
            if (style.avatar_ring_enabled && style.avatar_ring_color) {
                element.style.setProperty('--ring-color', style.avatar_ring_color);
            }
            if (style.font) {
                element.style.fontFamily = style.font;
            }
        }
    }

    // Bind click events to inner action buttons (Follow, Friend, Message, Block)
    function bindCardButtons(cardElement, data) {
        const followBtn = cardElement.querySelector('.js-card-follow');
        const friendBtn = cardElement.querySelector('.js-card-friend');
        const blockBtn = cardElement.querySelector('.js-card-block');
        
        if (followBtn) {
            followBtn.addEventListener('click', async () => {
                followBtn.disabled = true;
                const isFollowing = followBtn.classList.contains('social-btn--primary');
                let res;
                if (isFollowing) {
                    res = await SocialAPI.follow(data.id);
                } else {
                    res = await SocialAPI.unfollow(data.id);
                }
                
                if (res.success) {
                    if (isFollowing) {
                        followBtn.className = 'social-btn social-btn--secondary js-card-follow';
                        followBtn.innerHTML = '<i class="fa-solid fa-check"></i> Following';
                    } else {
                        followBtn.className = 'social-btn social-btn--primary js-card-follow';
                        followBtn.innerHTML = '<i class="fa-solid fa-user-plus"></i> Follow';
                    }
                    const countEl = cardElement.querySelector('.js-followers-count');
                    if (countEl) {
                        const currentVal = parseInt(countEl.textContent);
                        countEl.textContent = isFollowing ? currentVal + 1 : currentVal - 1;
                    }
                    if (window.SocialUI && typeof window.SocialUI.loadActiveTab === 'function') {
                        window.SocialUI.loadActiveTab();
                    }
                } else {
                    alert(res.error.message);
                }
                followBtn.disabled = false;
            });
        }

        if (friendBtn) {
            friendBtn.addEventListener('click', async () => {
                friendBtn.disabled = true;
                const action = friendBtn.dataset.action;
                let res;
                
                if (action === 'send') {
                    res = await SocialAPI.sendFriendRequest(data.id);
                } else if (action === 'accept') {
                    res = await SocialAPI.acceptFriendRequest(data.id);
                } else if (action === 'cancel') {
                    res = await SocialAPI.cancelFriendRequest(data.id);
                } else if (action === 'remove') {
                    if (confirm(`Are you sure you want to remove ${data.display_name} from your friends?`)) {
                        res = await SocialAPI.removeFriend(data.id);
                    } else {
                        friendBtn.disabled = false;
                        return;
                    }
                }

                if (res && res.success) {
                    openUserCard(data.id, '', activeTrigger);
                    if (window.SocialUI && typeof window.SocialUI.loadActiveTab === 'function') {
                        window.SocialUI.loadActiveTab();
                    }
                } else if (res) {
                    alert(res.error.message);
                }
                friendBtn.disabled = false;
            });
        }

        if (blockBtn) {
            blockBtn.addEventListener('click', async () => {
                blockBtn.disabled = true;
                const action = blockBtn.dataset.action;
                let res;
                
                if (action === 'unblock') {
                    res = await SocialAPI.unblockUser(data.id);
                } else {
                    if (confirm("Are you sure you want to block this user? All social connections (friendship, follow) will be removed.")) {
                        res = await SocialAPI.blockUser(data.id);
                    } else {
                        blockBtn.disabled = false;
                        return;
                    }
                }

                if (res && res.success) {
                    openUserCard(data.id, '', activeTrigger);
                    if (window.SocialUI && typeof window.SocialUI.loadActiveTab === 'function') {
                        window.SocialUI.loadActiveTab();
                    }
                } else if (res) {
                    alert(res.error.message);
                }
                blockBtn.disabled = false;
            });
        }
    }

    // Skeleton Loading HTML
    function getSkeletonHtml() {
        return `
            <div class="user-card__banner chat-skeleton" style="height: 100px;"></div>
            <div class="user-card__avatar-container">
                <div class="user-card__avatar chat-skeleton" style="width: 88px; height: 88px; border-radius: 50%;"></div>
            </div>
            <div class="user-card__body">
                <div class="user-card__name-section">
                    <div class="chat-skeleton mb-2" style="width: 160px; height: 24px; border-radius: 6px;"></div>
                    <div class="chat-skeleton mb-3" style="width: 100px; height: 16px; border-radius: 4px;"></div>
                </div>
                <div class="user-card__divider"></div>
                <div class="chat-skeleton mb-2" style="width: 80px; height: 14px; border-radius: 4px;"></div>
                <div class="chat-skeleton mb-3" style="width: 100%; height: 44px; border-radius: 8px;"></div>
            </div>
        `;
    }

    // Render User Card HTML
    function renderCardHtml(user) {
        const r = user.relationship;
        const lang = document.documentElement.lang || 'en';
        
        // Relationship Badges - only keeping 'Follows You' / 'Ti segue' to save space
        let badgesHtml = '';
        if (r.is_followed_by) {
            const followsYouLabel = lang === 'it' ? 'Ti segue' : 'Follows You';
            badgesHtml += `<span class="social-badge social-badge--follows-you">${followsYouLabel}</span>`;
        }

        // Follow Button
        let followBtnHtml = '';
        if (!r.is_self && r.can_follow) {
            if (r.is_following) {
                followBtnHtml = `<button class="social-btn social-btn--secondary js-card-follow" type="button"><i class="fa-solid fa-check"></i> Following</button>`;
            } else {
                followBtnHtml = `<button class="social-btn social-btn--primary js-card-follow" type="button"><i class="fa-solid fa-user-plus"></i> Follow</button>`;
            }
        }

        // Friend Button
        let friendBtnHtml = '';
        if (!r.is_self) {
            if (r.is_friend) {
                friendBtnHtml = `<button class="social-btn social-btn--secondary js-card-friend" data-action="remove" type="button" title="Remove Friend"><i class="fa-solid fa-user-minus"></i> Friend</button>`;
            } else if (r.friend_request_sent) {
                friendBtnHtml = `<button class="social-btn social-btn--secondary js-card-friend" data-action="cancel" type="button" title="Cancel Request"><i class="fa-solid fa-user-clock"></i> Sent</button>`;
            } else if (r.friend_request_received) {
                friendBtnHtml = `<button class="social-btn social-btn--primary js-card-friend" data-action="accept" type="button"><i class="fa-solid fa-user-check"></i> Accept</button>`;
            } else if (r.can_send_friend_request) {
                friendBtnHtml = `<button class="social-btn social-btn--primary js-card-friend" data-action="send" type="button"><i class="fa-solid fa-user-plus"></i> Add Friend</button>`;
            }
        }

        // Message & Group Buttons (Primary & Secondary Actions)
        let messageBtnHtml = '';
        let createGroupBtnHtml = '';
        let inviteGroupBtnHtml = '';
        if (!r.is_self && r.can_message) {
            const messageLabel = lang === 'it' ? 'Scrivi' : 'Message';
            messageBtnHtml = `<a class="social-btn social-btn--secondary" href="/${lang}/chat?user_id=${user.id}"><i class="fa-solid fa-envelope"></i> ${messageLabel}</a>`;
            createGroupBtnHtml = `<a class="social-btn social-btn--secondary" href="/${lang}/chat?create_group_with=${user.id}" title="${lang === 'it' ? 'Crea gruppo con utente' : 'Create group with user'}"><i class="fa-solid fa-users"></i></a>`;
            inviteGroupBtnHtml = `<button class="social-btn social-btn--secondary" onclick="openInviteDropdown(event, ${user.id})" title="${lang === 'it' ? 'Invita nel gruppo' : 'Invite to group'}"><i class="fa-solid fa-user-plus"></i></button>`;
        }

        // Block Button
        let blockBtnHtml = '';
        if (!r.is_self) {
            const blockTitle = r.is_blocked_by_viewer ? 'Unblock' : 'Block';
            const blockClass = r.is_blocked_by_viewer ? 'social-btn--danger' : 'social-btn--danger-outline';
            const blockAction = r.is_blocked_by_viewer ? 'unblock' : 'block';
            blockBtnHtml = `<button class="social-btn ${blockClass} js-card-block" data-action="${blockAction}" type="button" title="${blockTitle}"><i class="fa-solid fa-ban"></i></button>`;
        }

        // Mutual Friends
        let mutualsHtml = '';
        if (!r.is_self && user.stats.mutual_friends_count > 0) {
            const label = user.stats.mutual_friends_count === 1 ? 'mutual friend' : 'mutual friends';
            mutualsHtml = `
                <div class="user-card__divider"></div>
                <div class="user-card__section-title">Mutual Friends</div>
                <div class="user-card__mutuals">
                    <div class="user-card__mutual-avatars">
                        ${user.mutual_friends.map(m => `
                            <img class="user-card__mutual-avatar" src="/includes/get_pfp.php?id=${m.id}" alt="${escapeHtml(m.username)}">
                        `).join('')}
                    </div>
                    <span class="user-card__mutual-text">${user.stats.mutual_friends_count} ${label}</span>
                </div>
            `;
        }

        // About Me / Bio
        const bioHtml = user.bio ? `
            <div class="user-card__divider"></div>
            <div class="user-card__section-title">About Me</div>
            <div class="user-card__bio">${escapeHtml(user.bio)}</div>
        ` : '';

        const ringClass = user.style.avatar_ring_enabled ? 'has-ring' : '';

        // Structured Grid of Action Buttons
        let actionsHtml = '';
        if (!r.is_self) {
            const mainButtons = [followBtnHtml, friendBtnHtml, messageBtnHtml].filter(Boolean);
            const secondaryButtons = [createGroupBtnHtml, inviteGroupBtnHtml, blockBtnHtml].filter(Boolean);
            
            actionsHtml = `
                <div class="user-card__actions">
                    ${mainButtons.length > 0 ? `
                        <div class="user-card__actions-primary">
                            ${mainButtons.join('')}
                        </div>
                    ` : ''}
                    ${secondaryButtons.length > 0 ? `
                        <div class="user-card__actions-secondary">
                            ${secondaryButtons.join('')}
                        </div>
                    ` : ''}
                </div>
            `;
        }

        let bannerHtml = '';
        let bannerBgStyle = '';
        if (user.style && user.style.accent_color) {
            if (user.style.secondary_color) {
                bannerBgStyle = `background: linear-gradient(135deg, ${user.style.accent_color} 0%, ${user.style.secondary_color} 100%);`;
            } else {
                bannerBgStyle = `background: ${user.style.accent_color};`;
            }
        }

        if (user.profile_banner_url) {
            const isVideo = user.profile_banner_type && user.profile_banner_type.startsWith('video/');
            if (isVideo) {
                bannerHtml = `<video class="user-card__banner-media" src="${user.profile_banner_url}" autoplay loop muted></video>`;
            } else {
                bannerHtml = `<img class="user-card__banner-media" src="${user.profile_banner_url}" alt="">`;
            }
        }

        return `
            <div class="user-card__banner" style="${bannerBgStyle}">
                ${bannerHtml}
            </div>
            <div class="user-card__avatar-container">
                <img class="user-card__avatar ${ringClass}" src="/includes/get_pfp.php?id=${user.id}" alt="${escapeHtml(user.display_name)}">
            </div>
            <div class="user-card__body">
                <div class="user-card__name-section">
                    <div class="user-card__display-name">
                        <span>${escapeHtml(user.display_name)}</span>
                        ${user.is_premium ? '<i class="fa-solid fa-gem text-warning" style="font-size: 14px;" title="Premium"></i>' : ''}
                    </div>
                    <div class="user-card__username">@${escapeHtml(user.username)}</div>
                </div>
                
                ${badgesHtml ? `<div class="social-card__badges">${badgesHtml}</div>` : ''}

                <div class="user-card__stats">
                    <div class="user-card__stat-item">
                        <span class="user-card__stat-val js-followers-count">${user.stats.followers_count}</span>
                        <span class="user-card__stat-label">Followers</span>
                    </div>
                    <div class="user-card__stat-item">
                        <span class="user-card__stat-val">${user.stats.following_count}</span>
                        <span class="user-card__stat-label">Following</span>
                    </div>
                    <div class="user-card__stat-item">
                        <span class="user-card__stat-val">${user.stats.friends_count}</span>
                        <span class="user-card__stat-label">Friends</span>
                    </div>
                </div>

                ${bioHtml}
                ${mutualsHtml}
                ${actionsHtml}
            </div>
        `;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    window.closeUserCard = closeUserCard;

    window.openInviteDropdown = async function (e, targetUserId) {
        e.stopPropagation();
        e.preventDefault();
        
        // Remove existing dropdowns
        const existing = document.querySelector('#inviteGroupDropdown');
        if (existing) existing.remove();
        
        const lang = document.documentElement.lang || 'en';
        
        // Create drop down list element
        const dropdown = document.createElement('div');
        dropdown.id = 'inviteGroupDropdown';
        dropdown.style.cssText = 'position:absolute;background:#18181b;border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:6px 0;min-width:180px;box-shadow:0 10px 25px rgba(0,0,0,0.5);z-index:999999;';
        
        // Fetch groups
        try {
            const res = await fetch(`/api/chat/my_manageable_groups.php?target_id=${targetUserId}`).then(r => r.json());
            if (res.ok && res.groups && res.groups.length > 0) {
                dropdown.innerHTML = res.groups.map(g => `
                    <div style="padding:8px 15px;font-size:13px;cursor:pointer;color:white;transition:background 0.2s;" 
                         onmouseover="this.style.background='rgba(255,255,255,0.05)'" 
                         onmouseout="this.style.background='transparent'" 
                         onclick="sendGroupInviteFromCard(${g.chat_id}, ${targetUserId})">
                        ${escapeHtml(g.name)}
                    </div>
                `).join('');
            } else {
                dropdown.innerHTML = `<div style="padding:8px 15px;font-size:12px;color:#a1a1aa;text-align:center;">${lang === 'it' ? 'Nessun gruppo disponibile' : 'No groups available'}</div>`;
            }
        } catch (err) {
            dropdown.innerHTML = `<div style="padding:8px 15px;font-size:12px;color:#ef4444;text-align:center;">Errore</div>`;
        }
        
        // Position next to click
        document.body.appendChild(dropdown);
        dropdown.style.left = (e.pageX) + 'px';
        dropdown.style.top = (e.pageY) + 'px';
        
        // Close on clicking outside
        const closeDropdown = () => {
            dropdown.remove();
            document.removeEventListener('click', closeDropdown);
        };
        setTimeout(() => document.addEventListener('click', closeDropdown), 50);
    };
    
    window.sendGroupInviteFromCard = async function (chatId, inviteeId) {
        try {
            const res = await fetch('/api/chat/invite_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ chat_id: chatId, invitee_id: inviteeId })
            }).then(r => r.json());
            
            if (res.ok) {
                alert("Invito inviato con successo!");
            } else {
                alert(res.error || "Errore durante l'invio.");
            }
        } catch (e) {
            alert("Errore di connessione.");
        }
    };
})();
