/* assets/social/user-card.js - Discord-style User Card & Bottom Sheet Engine */

(function () {
    let activeTrigger = null;

    document.addEventListener('DOMContentLoaded', () => {
        setupCardContainers();
        bindTriggers();
    });

    // Inietta i contenitori HTML necessari nel body
    function setupCardContainers() {
        // 1. Discord Card per Desktop
        if (!document.getElementById('discordUserCard')) {
            const card = document.createElement('div');
            card.id = 'discordUserCard';
            card.className = 'discord-card';
            document.body.appendChild(card);
        }

        // 2. Bottom Sheet per Mobile
        if (!document.getElementById('bottomSheetCard')) {
            const backdrop = document.createElement('div');
            backdrop.id = 'bottomSheetBackdrop';
            backdrop.className = 'bottom-sheet__backdrop';
            document.body.appendChild(backdrop);

            const sheet = document.createElement('div');
            sheet.id = 'bottomSheetCard';
            sheet.className = 'bottom-sheet';
            sheet.innerHTML = `
                <div class="bottom-sheet__handle"></div>
                <div class="bottom-sheet__content"></div>
            `;
            document.body.appendChild(sheet);

            // Chiusura cliccando sulla sfocatura di sfondo
            backdrop.addEventListener('click', closeUserCard);
        }

        // Tasto ESC per chiudere
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeUserCard();
        });

        // Click esterno per chiudere la card desktop
        document.addEventListener('click', (e) => {
            const card = document.getElementById('discordUserCard');
            if (card && card.style.display === 'block') {
                if (!card.contains(e.target) && (!activeTrigger || !activeTrigger.contains(e.target))) {
                    closeUserCard();
                }
            }
        });
    }

    // Associa l'evento click a tutti gli elementi con la classe .user-card-trigger e ai bottoni profilo
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

        // Gestione dei pulsanti social nel menu a discesa del profilo (profile.php)
        document.body.addEventListener('click', async (e) => {
            const followBtn = e.target.closest('.js-profile-follow');
            if (followBtn) {
                e.preventDefault();
                followBtn.disabled = true;
                const targetId = parseInt(followBtn.dataset.id);
                const isFollowing = followBtn.dataset.following === '1';
                let res;
                if (isFollowing) {
                    res = await SocialAPI.unfollow(targetId);
                } else {
                    res = await SocialAPI.follow(targetId);
                }
                if (res.success) {
                    followBtn.dataset.following = isFollowing ? '0' : '1';
                    const icon = followBtn.querySelector('i');
                    if (icon) {
                        icon.className = isFollowing ? 'fa-solid fa-user-plus' : 'fa-solid fa-user-minus';
                    }
                    const text = followBtn.querySelector('span');
                    const isIt = document.documentElement.lang === 'it';
                    if (text) {
                        text.textContent = isFollowing ? (isIt ? 'Segui' : 'Follow') : (isIt ? 'Smetti di seguire' : 'Unfollow');
                    }
                } else {
                    alert(res.error.message);
                }
                followBtn.disabled = false;
            }

            const friendBtn = e.target.closest('.js-profile-friend');
            if (friendBtn) {
                e.preventDefault();
                friendBtn.disabled = true;
                const targetId = parseInt(friendBtn.dataset.id);
                const action = friendBtn.dataset.action;
                let res;
                if (action === 'send') {
                    res = await SocialAPI.sendFriendRequest(targetId);
                } else if (action === 'accept') {
                    res = await SocialAPI.acceptFriendRequest(targetId);
                } else if (action === 'cancel') {
                    res = await SocialAPI.cancelFriendRequest(targetId);
                } else if (action === 'remove') {
                    if (confirm("Rimuovere questo utente dagli amici?")) {
                        res = await SocialAPI.removeFriend(targetId);
                    } else {
                        friendBtn.disabled = false;
                        return;
                    }
                }
                if (res && res.success) {
                    window.location.reload();
                } else if (res) {
                    alert(res.error.message);
                }
                friendBtn.disabled = false;
            }

            const blockBtn = e.target.closest('.js-profile-block');
            if (blockBtn) {
                e.preventDefault();
                blockBtn.disabled = true;
                const targetId = parseInt(blockBtn.dataset.id);
                const isBlocked = blockBtn.dataset.blocked === '1';
                let res;
                if (isBlocked) {
                    res = await SocialAPI.unblockUser(targetId);
                } else {
                    if (confirm("Sei sicuro di voler bloccare questo utente? Verranno rimosse tutte le relazioni (amicizia, follow).")) {
                        res = await SocialAPI.blockUser(targetId);
                    } else {
                        blockBtn.disabled = false;
                        return;
                    }
                }
                if (res && res.success) {
                    window.location.reload();
                } else if (res) {
                    alert(res.error.message);
                }
                blockBtn.disabled = false;
            }
        });
    }

    // Apre la User Card posizionandola correttamente
    async function openUserCard(userId, username, triggerElement) {
        const isMobile = window.innerWidth <= 768;
        const cardDesktop = document.getElementById('discordUserCard');
        const sheetMobile = document.getElementById('bottomSheetCard');
        const backdrop = document.getElementById('bottomSheetBackdrop');

        // 1. Mostra lo stato di caricamento (Skeleton)
        const skeletonHtml = getSkeletonHtml();
        if (isMobile) {
            sheetMobile.querySelector('.bottom-sheet__content').innerHTML = skeletonHtml;
            sheetMobile.classList.add('is-open');
            backdrop.style.display = 'block';
        } else {
            cardDesktop.innerHTML = skeletonHtml;
            cardDesktop.className = 'discord-card';
            cardDesktop.style.display = 'block';
            positionCard(cardDesktop, triggerElement);
        }

        // 2. Carica i dati reali dall'API
        const res = await SocialAPI.getUserCard(userId, username);
        if (res.success) {
            const html = renderCardHtml(res.data);
            
            if (isMobile) {
                sheetMobile.querySelector('.bottom-sheet__content').innerHTML = html;
                applyHeritageStyles(sheetMobile, res.data.style);
            } else {
                cardDesktop.innerHTML = html;
                applyHeritageStyles(cardDesktop, res.data.style);
                // Riposiziona in caso di cambio altezza del contenuto
                positionCard(cardDesktop, triggerElement);
            }
            
            // Collega i gestori di eventi per i pulsanti all'interno della card
            bindCardButtons(isMobile ? sheetMobile : cardDesktop, res.data);
        } else {
            const errorHtml = `<div class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation mb-2 fs-3"></i><br>${res.error.message}</div>`;
            if (isMobile) {
                sheetMobile.querySelector('.bottom-sheet__content').innerHTML = errorHtml;
            } else {
                cardDesktop.innerHTML = errorHtml;
            }
        }
    }

    function closeUserCard() {
        const cardDesktop = document.getElementById('discordUserCard');
        const sheetMobile = document.getElementById('bottomSheetCard');
        const backdrop = document.getElementById('bottomSheetBackdrop');

        if (cardDesktop) cardDesktop.style.display = 'none';
        if (sheetMobile) sheetMobile.classList.remove('is-open');
        if (backdrop) backdrop.style.display = 'none';
        activeTrigger = null;
    }

    // Posizionamento intelligente del popup desktop vicino all'elemento cliccato
    function positionCard(card, trigger) {
        const rect = trigger.getBoundingClientRect();
        const cardWidth = card.offsetWidth;
        const cardHeight = card.offsetHeight;
        const scrollX = window.scrollX;
        const scrollY = window.scrollY;

        let left = rect.left + scrollX + (rect.width / 2) - (cardWidth / 2);
        let top = rect.bottom + scrollY + 8; // Di default sotto l'elemento

        // Se esce a destra dello schermo, allinealo al bordo destro dell'elemento
        if (left + cardWidth > window.innerWidth + scrollX - 20) {
            left = rect.right + scrollX - cardWidth;
        }
        // Se esce a sinistra dello schermo, allinealo al bordo sinistro dell'elemento
        if (left < scrollX + 20) {
            left = rect.left + scrollX;
        }

        // Se esce in basso allo schermo, posizionalo SOPRA l'elemento
        if (rect.bottom + cardHeight + 20 > window.innerHeight) {
            top = rect.top + scrollY - cardHeight - 8;
        }

        card.style.left = left + 'px';
        card.style.top = top + 'px';
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
                // Se c'è un'opacità, la applichiamo al colore esadecimale
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

    // Associa gli eventi ai pulsanti interni alla card (Follow, Amicizia, Messaggio)
    function bindCardButtons(cardElement, data) {
        const followBtn = cardElement.querySelector('.js-card-follow');
        const friendBtn = cardElement.querySelector('.js-card-friend');
        
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
                    // Cambia lo stato del pulsante
                    if (isFollowing) {
                        followBtn.className = 'social-btn social-btn--secondary js-card-follow';
                        followBtn.innerHTML = '<i class="fa-solid fa-check"></i> Seguito';
                    } else {
                        followBtn.className = 'social-btn social-btn--primary js-card-follow';
                        followBtn.innerHTML = '<i class="fa-solid fa-user-plus"></i> Segui';
                    }
                    // Aggiorna contatore follower nella card
                    const countEl = cardElement.querySelector('.js-followers-count');
                    if (countEl) {
                        const currentVal = parseInt(countEl.textContent);
                        countEl.textContent = isFollowing ? currentVal + 1 : currentVal - 1;
                    }
                    // Se siamo nella pagina amici, ricarichiamo i dati
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
                    // Ricarichiamo la card intera per mostrare il nuovo stato relazionale in modo pulito
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

        const blockBtn = cardElement.querySelector('.js-card-block');
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
                    // Ricarichiamo la card intera per mostrare il nuovo stato relazionale in modo pulito
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

    // Ritorna l'HTML dello Skeleton Loading per il caricamento
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
        const onlineClass = user.is_online ? 'is-online' : '';

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

    // Rende la chiusura accessibile globalmente
    window.closeUserCard = closeUserCard;
})();
