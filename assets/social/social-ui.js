/* assets/social/social-ui.js - Cripsum™ Friends Page Controller */

const SocialUI = {
    activeTab: 'online', // 'online', 'all', 'requests', 'suggestions', 'search'
    
    init() {
        this.bindEvents();
        this.loadActiveTab();
    },

    bindEvents() {
        // Clic sui tab
        document.querySelectorAll('.js-social-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const targetTab = e.currentTarget.dataset.tab;
                this.switchTab(targetTab);
            });
        });

        // Input di ricerca utenti
        const searchInput = document.getElementById('socialSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce((e) => {
                this.performSearch(e.target.value.trim());
            }, 300));
        }

        // Delega eventi per le azioni rapide sulle card degli utenti
        const grid = document.getElementById('socialGrid');
        if (grid) {
            grid.addEventListener('click', (e) => {
                const btn = e.target.closest('.js-social-action');
                if (btn) {
                    e.preventDefault();
                    this.handleAction(btn);
                }
            });
        }
    },

    switchTab(tabName) {
        this.activeTab = tabName;
        
        // Aggiorna classe attiva sui pulsanti dei tab
        document.querySelectorAll('.js-social-tab').forEach(tab => {
            tab.classList.toggle('is-active', tab.dataset.tab === tabName);
        });

        // Mostra/Nascondi la barra di ricerca in base al tab
        const searchContainer = document.getElementById('socialSearchContainer');
        if (searchContainer) {
            searchContainer.style.display = (tabName === 'search') ? 'block' : 'none';
        }

        this.loadActiveTab();
    },

    async loadActiveTab() {
        this.renderSkeleton();
        
        try {
            switch (this.activeTab) {
                case 'online':
                case 'all':
                    const friendsRes = await SocialAPI.getFriends();
                    if (friendsRes.success) {
                        const list = (this.activeTab === 'online') ? friendsRes.data.online : friendsRes.data.all;
                        this.renderUserList(list, this.activeTab === 'online' ? "Nessun amico online al momento." : "Non hai ancora aggiunto amici.");
                    } else {
                        this.renderError(friendsRes.error.message);
                    }
                    break;
                    
                case 'requests':
                    const reqRes = await SocialAPI.getFriendRequests();
                    if (reqRes.success) {
                        this.renderRequestsList(reqRes.data);
                    } else {
                        this.renderError(reqRes.error.message);
                    }
                    break;
                    
                case 'suggestions':
                    const sugRes = await SocialAPI.getSuggestedUsers();
                    if (sugRes.success) {
                        this.renderUserList(sugRes.data.suggestions, "Nessun suggerimento disponibile al momento.");
                    } else {
                        this.renderError(sugRes.error.message);
                    }
                    break;
                    
                case 'search':
                    // Non caricare nulla finche' l'utente non digita
                    const query = document.getElementById('socialSearchInput').value.trim();
                    if (query === '') {
                        const isIt = document.documentElement.lang === 'it';
                        document.getElementById('socialGrid').innerHTML = `
                            <div class="social-grid-message">
                                <i class="fa-solid fa-user-gear"></i>
                                ${isIt ? 'Digita un nome utente per cercarlo su Cripsum.' : 'Type a username to search on Cripsum.'}
                            </div>
                        `;
                    } else {
                        this.performSearch(query);
                    }
                    break;
            }
        } catch (e) {
            this.renderError("Errore durante il caricamento.");
        }
    },

    // --- RENDERING LISTE UTENTI ---
    renderUserList(users, emptyMessage) {
        const grid = document.getElementById('socialGrid');
        if (!grid) return;

        if (users.length === 0) {
            if (this.activeTab === 'online' || this.activeTab === 'all') {
                const isIt = document.documentElement.lang === 'it';
                grid.innerHTML = `
                    <div class="social-empty-state col-span-full">
                        <div class="social-empty-state__icon">
                            <i class="fa-solid fa-user-astronaut"></i>
                        </div>
                        <h3 class="social-empty-state__title">${isIt ? 'Sembra un po\' vuoto qui...' : 'It\'s a bit quiet here...'}</h3>
                        <p class="social-empty-state__text">
                            ${this.activeTab === 'online' 
                                ? (isIt ? 'Nessuno dei tuoi amici è online al momento. Torna più tardi!' : 'None of your friends are online right now. Check back later!')
                                : (isIt ? 'Inizia a costruire il tuo social graph! Cerca altri utenti o guarda le persone consigliate per te.' : 'Start building your social graph! Search for other users or check out your recommendations.')}
                        </p>
                        ${this.activeTab === 'all' ? `
                            <button class="social-btn social-btn--primary mt-3" onclick="SocialUI.switchTab('suggestions')">
                                <i class="fa-solid fa-compass"></i> ${isIt ? 'Scopri persone' : 'Discover people'}
                            </button>
                        ` : ''}
                    </div>
                `;
                return;
            }
            grid.innerHTML = `
                <div class="social-grid-message">
                    <i class="fa-regular fa-face-frown"></i>
                    ${emptyMessage}
                </div>
            `;
            return;
        }

        grid.innerHTML = users.map((u, index) => {
            const onlineClass = u.is_online ? 'is-online' : '';
            const delay = index * 0.04; // 40ms stagger delay
            
            // Relationship badges removed to save space
            let badgesHtml = '';

            // Action Buttons
            let actionsHtml = '';
            
            // Friend Button
            if (u.is_friend) {
                actionsHtml += `<button class="social-btn social-btn--secondary js-social-action" data-action="remove_friend" data-id="${u.id}"><i class="fa-solid fa-user-minus"></i> Rimuovi</button>`;
            } else if (u.friend_request_sent) {
                actionsHtml += `<button class="social-btn social-btn--secondary js-social-action" data-action="cancel_request" data-id="${u.id}"><i class="fa-solid fa-user-clock"></i> Inviata</button>`;
            } else if (u.friend_request_received) {
                actionsHtml += `<button class="social-btn social-btn--primary js-social-action" data-action="accept_request" data-id="${u.id}"><i class="fa-solid fa-user-check"></i> Accetta</button>`;
            } else if (u.can_send_friend_request) {
                actionsHtml += `<button class="social-btn social-btn--primary js-social-action" data-action="add_friend" data-id="${u.id}"><i class="fa-solid fa-user-plus"></i> Aggiungi</button>`;
            }



            return `
                <div class="social-card" style="animation-delay: ${delay}s;">
                    <div class="social-card__avatar-container user-card-trigger" data-user-id="${u.id}" style="cursor:pointer;">
                        <img class="social-card__avatar" src="/includes/get_pfp.php?id=${u.id}" alt="${this.escapeHtml(u.username)}">
                        <span class="social-card__status ${onlineClass}"></span>
                    </div>
                    <h4 class="social-card__name user-card-trigger" data-user-id="${u.id}" style="cursor:pointer;">${this.escapeHtml(u.display_name || u.username)}</h4>
                    <span class="social-card__username" style="${this.activeTab === 'suggestions' ? 'margin-bottom: 8px;' : ''}">@${this.escapeHtml(u.username)}</span>
                    ${this.activeTab === 'suggestions' ? this.getSuggestionReasonHtml(u) : ''}
                    <div class="social-card__badges">
                        ${badgesHtml}
                    </div>
                    <div class="social-card__actions">
                        ${actionsHtml}
                    </div>
                </div>
            `;
        }).join('');
    },

    // Render friend requests (sent and received) in separate sections with animations
    renderRequestsList(data) {
        const grid = document.getElementById('socialGrid');
        if (!grid) return;

        if (data.received.length === 0 && data.sent.length === 0) {
            const isIt = document.documentElement.lang === 'it';
            grid.innerHTML = `
                <div class="social-empty-state col-span-full">
                    <div class="social-empty-state__icon">
                        <i class="fa-solid fa-envelope-open-text"></i>
                    </div>
                    <h3 class="social-empty-state__title">${isIt ? 'Nessuna richiesta' : 'No requests'}</h3>
                    <p class="social-empty-state__text">
                        ${isIt ? 'Non hai richieste di amicizia in sospeso, né inviate né ricevute.' : 'You have no pending friend requests, sent or received.'}
                    </p>
                </div>
            `;
            return;
        }

        let html = '';
        let globalIndex = 0;

        if (data.received.length > 0) {
            html += `<h3 class="col-span-full mb-3 fw-bold fs-5 text-white static-reveal is-visible"><i class="fa-solid fa-arrow-down text-success me-2"></i> Richieste Ricevute (${data.received.length})</h3>`;
            data.received.forEach(r => {
                const delay = globalIndex * 0.04;
                globalIndex++;
                html += `
                    <div class="social-card" style="animation-delay: ${delay}s;">
                        <div class="social-card__avatar-container user-card-trigger" data-user-id="${r.user_id}" style="cursor:pointer;">
                            <img class="social-card__avatar" src="/includes/get_pfp.php?id=${r.user_id}" alt="${this.escapeHtml(r.username)}">
                        </div>
                        <h4 class="social-card__name user-card-trigger" data-user-id="${r.user_id}" style="cursor:pointer;">${this.escapeHtml(r.username)}</h4>
                        <span class="social-card__username" style="margin-bottom:15px;">Inviata ${this.formatDate(r.created_at)}</span>
                        <div class="social-card__actions">
                            <button class="social-btn social-btn--primary js-social-action" data-action="accept_request" data-id="${r.user_id}"><i class="fa-solid fa-user-check"></i> Accetta</button>
                            <button class="social-btn social-btn--danger js-social-action" data-action="decline_request" data-id="${r.user_id}"><i class="fa-solid fa-xmark"></i> Rifiuta</button>
                        </div>
                    </div>
                `;
            });
        }

        if (data.sent.length > 0) {
            html += `<h3 class="col-span-full mt-4 mb-3 fw-bold fs-5 text-white static-reveal is-visible"><i class="fa-solid fa-arrow-up text-primary me-2"></i> Richieste Inviate (${data.sent.length})</h3>`;
            data.sent.forEach(r => {
                const delay = globalIndex * 0.04;
                globalIndex++;
                html += `
                    <div class="social-card" style="animation-delay: ${delay}s;">
                        <div class="social-card__avatar-container user-card-trigger" data-user-id="${r.user_id}" style="cursor:pointer;">
                            <img class="social-card__avatar" src="/includes/get_pfp.php?id=${r.user_id}" alt="${this.escapeHtml(r.username)}">
                        </div>
                        <h4 class="social-card__name user-card-trigger" data-user-id="${r.user_id}" style="cursor:pointer;">${this.escapeHtml(r.username)}</h4>
                        <span class="social-card__username" style="margin-bottom:15px;">Inviata ${this.formatDate(r.created_at)}</span>
                        <div class="social-card__actions">
                            <button class="social-btn social-btn--secondary js-social-action" data-action="cancel_request" data-id="${r.user_id}"><i class="fa-solid fa-user-clock"></i> Annulla</button>
                        </div>
                    </div>
                `;
            });
        }

        grid.innerHTML = html;
    },

    // --- RICERCA UTENTI ---
    async performSearch(query) {
        if (query === '') {
            this.loadActiveTab();
            return;
        }

        this.renderSkeleton();

        try {
            const res = await SocialAPI.searchUsers(query);
            if (res.success) {
                this.renderUserList(res.data.users, "Nessun utente corrisponde alla ricerca.");
            } else {
                this.renderError(res.error.message);
            }
        } catch (e) {
            this.renderError("Errore durante la ricerca.");
        }
    },

    // --- AZIONI SUI PULSANTI ---
    async handleAction(btn) {
        btn.disabled = true;
        const action = btn.dataset.action;
        const targetId = parseInt(btn.dataset.id);
        let res;

        try {
            switch (action) {
                case 'add_friend':
                    res = await SocialAPI.sendFriendRequest(targetId);
                    if (res.success) this.showToast(res.data.status === 'accepted' ? "Ora siete amici!" : "Richiesta di amicizia inviata.");
                    break;
                case 'accept_request':
                    res = await SocialAPI.acceptFriendRequest(targetId);
                    if (res.success) this.showToast("Richiesta accettata. Ora siete amici!");
                    break;
                case 'decline_request':
                    res = await SocialAPI.declineFriendRequest(targetId);
                    if (res.success) this.showToast("Richiesta rifiutata.");
                    break;
                case 'cancel_request':
                    res = await SocialAPI.cancelFriendRequest(targetId);
                    if (res.success) this.showToast("Richiesta annullata.");
                    break;
                case 'remove_friend':
                    if (confirm("Sei sicuro di voler rimuovere questo amico?")) {
                        res = await SocialAPI.removeFriend(targetId);
                        if (res.success) this.showToast("Amico rimosso.");
                    }
                    break;
            }

            if (res && res.success) {
                // Ricarichiamo la tab attiva per aggiornare la UI in modo coerente
                this.loadActiveTab();
                // Aggiorna anche il badge della navbar (se presente)
                this.updateNavbarBadge();
            } else if (res) {
                this.showToast(res.error.message, true);
            }
        } catch (e) {
            this.showToast("Errore durante l'operazione.", true);
        }
        
        btn.disabled = false;
    },

    // --- SKELETON LOADING ---
    renderSkeleton() {
        const grid = document.getElementById('socialGrid');
        if (!grid) return;
        
        grid.innerHTML = Array(4).fill(0).map(() => `
            <div class="social-card">
                <div class="social-card__avatar-container">
                    <div class="social-card__avatar chat-skeleton" style="border:none; width:72px; height:72px; border-radius:50%;"></div>
                </div>
                <div class="chat-skeleton mb-2" style="width: 120px; height: 18px;"></div>
                <div class="chat-skeleton mb-3" style="width: 80px; height: 12px;"></div>
                <div class="social-card__actions" style="width:100%;">
                    <div class="chat-skeleton" style="flex:1; height: 32px; border-radius:8px;"></div>
                    <div class="chat-skeleton" style="flex:1; height: 32px; border-radius:8px;"></div>
                </div>
            </div>
        `).join('');
    },

    renderError(message) {
        const grid = document.getElementById('socialGrid');
        if (grid) {
            grid.innerHTML = `
                <div class="social-grid-message text-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    ${message}
                </div>
            `;
        }
    },

    getSuggestionReasonHtml(u) {
        const isIt = document.documentElement.lang === 'it';
        if (u.mutual_connections > 0) {
            return `
                <span class="social-card__reason">
                    <i class="fa-solid fa-user-group"></i>
                    ${isIt ? `${u.mutual_connections} in comune` : `${u.mutual_connections} mutuals`}
                </span>
            `;
        } else if (u.is_follower) {
            return `
                <span class="social-card__reason">
                    <i class="fa-solid fa-arrow-left-long"></i>
                    ${isIt ? 'Ti segue' : 'Follows you'}
                </span>
            `;
        } else {
            return `
                <span class="social-card__reason">
                    <i class="fa-solid fa-fire"></i>
                    ${isIt ? 'Popolare' : 'Popular'}
                </span>
            `;
        }
    },

    updateNavbarBadge() {
        // Ricarica il polling della chat o esegui una chiamata veloce per aggiornare il contatore navbar
        // In questo sito l'unreadCount viene aggiornato al caricamento pagina o via polling se attivo.
    },

    // --- TOAST NOTIFICATIONS ---
    showToast(message, isError = false) {
        let toast = document.getElementById('socialToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'socialToast';
            toast.className = 'admin-toast';
            toast.style = 'position: fixed; bottom: 20px; right: 20px; z-index: 10000; transition: opacity 0.3s; pointer-events: none; padding: 12px 24px; border-radius: 8px; color: white; font-weight: 700;';
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.style.background = isError ? '#ef4444' : '#8b5cf6';
        toast.style.opacity = '1';
        setTimeout(() => {
            toast.style.opacity = '0';
        }, 3000);
    },

    // --- UTILITIES ---
    debounce(fn, delay) {
        let timer = null;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    },

    escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    },

    formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString([], { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    }
};

// Inizializza al caricamento della pagina amici
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('socialGrid')) {
        SocialUI.init();
    }
});
