/* assets/social/user-card.js - Discord-style User Card Overlay Engine */

(function () {
    let activeTrigger = null;

    document.addEventListener('DOMContentLoaded', () => {
        setupCardContainers();
        bindTriggers();
    });

    // Inietta l'overlay del profilo nel body
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
                <div class="profile-nav-overlay-container" style="max-width: 340px; padding: 0; overflow: hidden; border: none; background: transparent; position: relative; animation: discord-pop 0.2s cubic-bezier(0.18, 0.89, 0.32, 1.28);">
                    <button class="profile-nav-overlay-close-btn js-close-user-card" style="z-index: 10; color: white; right: 15px; top: 15px; font-size: 24px; background: transparent; border: none; cursor: pointer; position: absolute;">&times;</button>
                    <div id="discordUserCard" class="discord-card" style="display: block; position: relative; margin: 0; top: 0; left: 0; box-shadow: none; width: 100%; animation: none;">
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);

            // Cliccando sul backdrop o sulla X si chiude
            overlay.querySelectorAll('.js-close-user-card').forEach(el => {
                el.addEventListener('click', closeUserCard);
            });
        }

        // Tasto ESC per chiudere
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeUserCard();
        });
    }

    // Associa l'evento click a tutti gli elementi con la classe .user-card-trigger
    function bindTriggers() {
        // Usiamo la delega degli eventi sul body per catturare anche gli elementi caricati dinamicamente (AJAX)
        document.body.addEventListener('click', (e) => {
            const trigger = e.target.closest('.user-card-trigger');
            if (trigger) {
                e.preventDefault();
                e.stopPropagation();
                activeTrigger = trigger;
                
                const userId = trigger.dataset.userId ? parseInt(trigger.dataset.userId) : 0;
                const username = trigger.dataset.username || '';
                
                openUserCard(userId, username, trigger);
            }
        });
    }

    // Apre la User Card sfruttando il sistema di overlay nativo del sito
    async function openUserCard(userId, username, triggerElement) {
        const overlay = document.getElementById('socialUserCardOverlay');
        const card = document.getElementById('discordUserCard');
        if (!overlay || !card) return;

        // 1. Mostra lo stato di caricamento (Skeleton)
        card.innerHTML = getSkeletonHtml();
        
        // 2. Attiva l'overlay
        overlay.style.visibility = 'visible';
        overlay.style.pointerEvents = 'auto';
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('profile-overlay-open');

        // 3. Carica i dati reali dall'API
        const res = await SocialAPI.getUserCard(userId, username);
        if (res.success) {
            const html = renderCardHtml(res.data);
            card.innerHTML = html;
            applyHeritageStyles(card, res.data.style);
            
            // Collega i gestori di eventi per i pulsanti all'interno della card
            bindCardButtons(card, res.data);
        } else {
            card.innerHTML = `
                <div class="text-center py-5 text-danger" style="background: #111214; border-radius: 16px; border: 1px solid var(--social-border);">
                    <i class="fa-solid fa-triangle-exclamation mb-2 fs-3"></i><br>
                    ${escapeHtml(res.error.message)}
                </div>
            `;
        }
    }

    // Chiude l'overlay della User Card
    function closeUserCard() {
        const overlay = document.getElementById('socialUserCardOverlay');
        if (overlay) {
            overlay.style.visibility = 'hidden';
            overlay.style.pointerEvents = 'none';
            overlay.classList.remove('is-visible');
            overlay.setAttribute('aria-hidden', 'true');
            
            // Rimuove il blocco del body solo se nessun altro overlay è ancora aperto
            const anyVisible = document.querySelector('.profile-nav-overlay.is-visible:not(#socialUserCardOverlay), .profile-report-modal.is-visible');
            if (!anyVisible) {
                document.body.classList.remove('profile-overlay-open');
            }
        }
        activeTrigger = null;
    }

    // Applica le variabili CSS del profilo personalizzato dell'utente visualizzato
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

    // Associa gli eventi ai pulsanti interni alla card (Follow, Amicizia, Messaggio, Blocco)
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
                        followBtn.innerHTML = '<i class="fa-solid fa-check"></i> Seguito';
                    } else {
                        followBtn.className = 'social-btn social-btn--primary js-card-follow';
                        followBtn.innerHTML = '<i class="fa-solid fa-user-plus"></i> Segui';
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
                    if (confirm(`Rimuovere ${data.display_name} dagli amici?`)) {
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
                    if (confirm("Sei sicuro di voler bloccare questo utente? Verranno rimosse tutte le relazioni (amicizia, follow).")) {
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

    // Ritorna l'HTML dello Skeleton Loading
    function getSkeletonHtml() {
        return `
            <div class="discord-card__banner chat-skeleton" style="height: 60px;"></div>
            <div class="discord-card__avatar-container">
                <div class="discord-card__avatar chat-skeleton" style="width: 80px; height: 80px; border-radius: 50%;"></div>
            </div>
            <div class="discord-card__body">
                <div class="chat-skeleton mb-2" style="width: 150px; height: 20px;"></div>
                <div class="chat-skeleton mb-3" style="width: 100px; height: 14px;"></div>
                <div class="discord-card__divider"></div>
                <div class="chat-skeleton mb-2" style="width: 80px; height: 12px;"></div>
                <div class="chat-skeleton mb-3" style="width: 100%; height: 40px;"></div>
            </div>
        `;
    }

    // Renderizza l'HTML effettivo della card utente
    function renderCardHtml(user) {
        const r = user.relationship;
        
        // Badge sociali
        let badgesHtml = '';
        if (r.is_friend) {
            badgesHtml += `<span class="social-badge social-badge--friend"><i class="fa-solid fa-user-group"></i> Amico</span>`;
        }
        if (r.is_mutual_follow) {
            badgesHtml += `<span class="social-badge social-badge--mutual"><i class="fa-solid fa-arrows-left-right"></i> Follow reciproco</span>`;
        } else if (r.is_followed_by) {
            badgesHtml += `<span class="social-badge social-badge--follows-you">Segue te</span>`;
        }

        // Pulsante Follow
        let followBtnHtml = '';
        if (!r.is_self && r.can_follow) {
            if (r.is_following) {
                followBtnHtml = `<button class="social-btn social-btn--secondary js-card-follow" type="button"><i class="fa-solid fa-check"></i> Seguito</button>`;
            } else {
                followBtnHtml = `<button class="social-btn social-btn--primary js-card-follow" type="button"><i class="fa-solid fa-user-plus"></i> Segui</button>`;
            }
        }

        // Pulsante Amicizia
        let friendBtnHtml = '';
        if (!r.is_self) {
            if (r.is_friend) {
                friendBtnHtml = `<button class="social-btn social-btn--secondary js-card-friend" data-action="remove" type="button" title="Rimuovi amico"><i class="fa-solid fa-user-minus"></i> Amico</button>`;
            } else if (r.friend_request_sent) {
                friendBtnHtml = `<button class="social-btn social-btn--secondary js-card-friend" data-action="cancel" type="button" title="Annulla richiesta"><i class="fa-solid fa-user-clock"></i> Inviata</button>`;
            } else if (r.friend_request_received) {
                friendBtnHtml = `<button class="social-btn social-btn--primary js-card-friend" data-action="accept" type="button"><i class="fa-solid fa-user-check"></i> Accetta</button>`;
            } else if (r.can_send_friend_request) {
                friendBtnHtml = `<button class="social-btn social-btn--primary js-card-friend" data-action="send" type="button"><i class="fa-solid fa-user-plus"></i> Aggiungi</button>`;
            }
        }

        // Pulsante Messaggio
        let messageBtnHtml = '';
        if (!r.is_self && r.can_message) {
            messageBtnHtml = `<a class="social-btn social-btn--secondary" href="/${document.documentElement.lang || 'it'}/chat?user_id=${user.id}"><i class="fa-solid fa-envelope"></i> Messaggio</a>`;
        }

        // Pulsante Blocca
        let blockBtnHtml = '';
        if (!r.is_self) {
            if (r.is_blocked_by_viewer) {
                blockBtnHtml = `<button class="social-btn social-btn--danger js-card-block" data-action="unblock" type="button"><i class="fa-solid fa-ban"></i> Sblocca</button>`;
            } else {
                blockBtnHtml = `<button class="social-btn social-btn--danger-outline js-card-block" data-action="block" type="button"><i class="fa-solid fa-ban"></i> Blocca</button>`;
            }
        }

        // Amici in comune
        let mutualsHtml = '';
        if (!r.is_self && user.stats.mutual_friends_count > 0) {
            mutualsHtml = `
                <div class="discord-card__divider"></div>
                <div class="discord-card__section-title">Amici in comune</div>
                <div class="discord-card__mutuals">
                    <div class="discord-card__mutual-avatars">
                        ${user.mutual_friends.map(m => `
                            <img class="discord-card__mutual-avatar" src="/includes/get_pfp.php?id=${m.id}" alt="${escapeHtml(m.username)}">
                        `).join('')}
                    </div>
                    <span class="discord-card__mutual-text">${user.stats.mutual_friends_count} amici in comune</span>
                </div>
            `;
        }

        // Bio
        const bioHtml = user.bio ? `
            <div class="discord-card__divider"></div>
            <div class="discord-card__section-title">Su di me</div>
            <div class="discord-card__bio">${escapeHtml(user.bio)}</div>
        ` : '';

        const ringClass = user.style.avatar_ring_enabled ? 'has-ring' : '';

        return `
            <div class="discord-card__banner"></div>
            <div class="discord-card__avatar-container">
                <img class="discord-card__avatar ${ringClass}" src="/includes/get_pfp.php?id=${user.id}" alt="${escapeHtml(user.display_name)}">
            </div>
            <div class="discord-card__body">
                <div class="discord-card__name-section">
                    <div class="discord-card__display-name">
                        <span>${escapeHtml(user.display_name)}</span>
                        ${user.is_premium ? '<i class="fa-solid fa-gem text-warning" style="font-size: 14px;" title="Premium"></i>' : ''}
                    </div>
                    <div class="discord-card__username">@${escapeHtml(user.username)}</div>
                </div>
                
                <div class="social-card__badges">
                    ${badgesHtml}
                </div>

                <div class="discord-card__stats">
                    <div><span class="discord-card__stat-val js-followers-count">${user.stats.followers_count}</span> <span class="text-muted">Followers</span></div>
                    <div><span class="discord-card__stat-val">${user.stats.following_count}</span> <span class="text-muted">Following</span></div>
                    <div><span class="discord-card__stat-val">${user.stats.friends_count}</span> <span class="text-muted">Amici</span></div>
                </div>

                ${bioHtml}
                ${mutualsHtml}

                <div class="discord-card__actions">
                    ${followBtnHtml}
                    ${friendBtnHtml}
                    ${messageBtnHtml}
                    ${blockBtnHtml}
                </div>
            </div>
        `;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    window.closeUserCard = closeUserCard;
})();
