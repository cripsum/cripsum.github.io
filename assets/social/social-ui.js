/* assets/social/social-ui.js - Cripsum™ Friends Page Controller */

const SocialUI = {
    activeTab: 'online', // 'online', 'all', 'requests', 'suggestions', 'search'

    // --- TRANSLATION HELPER ---
    _lang() { return document.documentElement.lang === 'it' ? 'it' : 'en'; },
    _t(key) {
        const lang = this._lang();
        const T = {
            // Empty states
            no_friends_online:   { it: 'Nessun amico online al momento.', en: 'No friends online right now.' },
            no_friends_yet:      { it: 'Non hai ancora aggiunto amici.', en: "You haven't added any friends yet." },
            no_suggestions:      { it: 'Nessun suggerimento disponibile al momento.', en: 'No suggestions available at the moment.' },
            no_search_results:   { it: 'Nessun utente corrisponde alla ricerca.', en: 'No users match your search.' },
            search_placeholder:  { it: 'Digita un nome utente per cercarlo su Cripsum.', en: 'Type a username to search on Cripsum.' },
            empty_quiet_title:   { it: "Sembra un po' vuoto qui...", en: "It's a bit quiet here..." },
            empty_online:        { it: 'Nessuno dei tuoi amici è online al momento. Torna più tardi!', en: 'None of your friends are online right now. Check back later!' },
            empty_all:           { it: 'Inizia a costruire il tuo social graph! Cerca altri utenti o guarda le persone consigliate per te.', en: 'Start building your social graph! Search for other users or check out your recommendations.' },
            discover_people:     { it: 'Scopri persone', en: 'Discover people' },
            no_requests_title:   { it: 'Nessuna richiesta', en: 'No requests' },
            no_requests_text:    { it: 'Non hai richieste di amicizia in sospeso, né inviate né ricevute.', en: 'You have no pending friend requests, sent or received.' },

            // Buttons
            btn_remove:          { it: 'Rimuovi', en: 'Remove' },
            btn_sent:            { it: 'Inviata', en: 'Sent' },
            btn_accept:          { it: 'Accetta', en: 'Accept' },
            btn_decline:         { it: 'Rifiuta', en: 'Decline' },
            btn_add:             { it: 'Aggiungi', en: 'Add' },
            btn_cancel:          { it: 'Annulla', en: 'Cancel' },

            // Sections
            requests_received:   { it: 'Richieste Ricevute', en: 'Received Requests' },
            requests_sent:       { it: 'Richieste Inviate', en: 'Sent Requests' },
            sent_on:             { it: 'Inviata', en: 'Sent' },

            // Toast messages
            toast_now_friends:       { it: 'Ora siete amici!', en: 'You are now friends!' },
            toast_request_sent:      { it: 'Richiesta di amicizia inviata.', en: 'Friend request sent.' },
            toast_request_accepted:  { it: 'Richiesta accettata. Ora siete amici!', en: 'Request accepted. You are now friends!' },
            toast_request_declined:  { it: 'Richiesta rifiutata.', en: 'Request declined.' },
            toast_request_cancelled: { it: 'Richiesta annullata.', en: 'Request cancelled.' },
            toast_friend_removed:    { it: 'Amico rimosso.', en: 'Friend removed.' },
            confirm_remove_friend:   { it: 'Sei sicuro di voler rimuovere questo amico?', en: 'Are you sure you want to remove this friend?' },

            // Errors
            error_loading:       { it: 'Errore durante il caricamento.', en: 'Error loading data.' },
            error_search:        { it: 'Errore durante la ricerca.', en: 'Error during search.' },
            error_action:        { it: "Errore durante l'operazione.", en: 'Error performing action.' },

            // Suggestion reasons
            mutuals:             { it: 'in comune', en: 'mutuals' },
            follows_you:         { it: 'Ti segue', en: 'Follows you' },
            popular:             { it: 'Popolare', en: 'Popular' },
        };
        return T[key] ? (T[key][lang] || T[key]['en']) : key;
    },
    
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
                        this.renderUserList(list, this.activeTab === 'online' ? this._t('no_friends_online') : this._t('no_friends_yet'));
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
                        this.renderUserList(sugRes.data.suggestions, this._t('no_suggestions'));
                    } else {
                        this.renderError(sugRes.error.message);
                    }
                    break;
                    
                case 'search':
                    // Non caricare nulla finche' l'utente non digita
                    const query = document.getElementById('socialSearchInput').value.trim();
                    if (query === '') {
                        document.getElementById('socialGrid').innerHTML = `
                            <div class="social-grid-message">
                                <i class="fa-solid fa-user-gear"></i>
                                ${this._t('search_placeholder')}
                            </div>
                        `;
                    } else {
                        this.performSearch(query);
                    }
                    break;
            }
        } catch (e) {
            this.renderError(this._t('error_loading'));
        }
    },

    // --- RENDERING LISTE UTENTI ---
    renderUserList(users, emptyMessage) {
        const grid = document.getElementById('socialGrid');
        if (!grid) return;

        if (users.length === 0) {
            if (this.activeTab === 'online' || this.activeTab === 'all') {
                grid.innerHTML = `
                    <div class="social-empty-state col-span-full">
                        <div class="social-empty-state__icon">
                            <i class="fa-solid fa-user-astronaut"></i>
                        </div>
                        <h3 class="social-empty-state__title">${this._t('empty_quiet_title')}</h3>
                        <p class="social-empty-state__text">
                            ${this.activeTab === 'online' ? this._t('empty_online') : this._t('empty_all')}
                        </p>
                        ${this.activeTab === 'all' ? `
                            <button class="social-btn social-btn--primary mt-3" onclick="SocialUI.switchTab('suggestions')">
                                <i class="fa-solid fa-compass"></i> ${this._t('discover_people')}
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
                actionsHtml += `<button class="social-btn social-btn--secondary js-social-action" data-action="remove_friend" data-id="${u.id}"><i class="fa-solid fa-user-minus"></i> ${this._t('btn_remove')}</button>`;
            } else if (u.friend_request_sent) {
                actionsHtml += `<button class="social-btn social-btn--secondary js-social-action" data-action="cancel_request" data-id="${u.id}"><i class="fa-solid fa-user-clock"></i> ${this._t('btn_sent')}</button>`;
            } else if (u.friend_request_received) {
                actionsHtml += `<button class="social-btn social-btn--primary js-social-action" data-action="accept_request" data-id="${u.id}"><i class="fa-solid fa-user-check"></i> ${this._t('btn_accept')}</button>`;
            } else if (u.can_send_friend_request) {
                actionsHtml += `<button class="social-btn social-btn--primary js-social-action" data-action="add_friend" data-id="${u.id}"><i class="fa-solid fa-user-plus"></i> ${this._t('btn_add')}</button>`;
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
            grid.innerHTML = `
                <div class="social-empty-state col-span-full">
                    <div class="social-empty-state__icon">
                        <i class="fa-solid fa-envelope-open-text"></i>
                    </div>
                    <h3 class="social-empty-state__title">${this._t('no_requests_title')}</h3>
                    <p class="social-empty-state__text">
                        ${this._t('no_requests_text')}
                    </p>
                </div>
            `;
            return;
        }

        let html = '';
        let globalIndex = 0;

        if (data.received.length > 0) {
            html += `<h3 class="col-span-full mb-3 fw-bold fs-5 text-white static-reveal is-visible"><i class="fa-solid fa-arrow-down text-success me-2"></i> ${this._t('requests_received')} (${data.received.length})</h3>`;
            data.received.forEach(r => {
                const delay = globalIndex * 0.04;
                globalIndex++;
                html += `
                    <div class="social-card" style="animation-delay: ${delay}s;">
                        <div class="social-card__avatar-container user-card-trigger" data-user-id="${r.user_id}" style="cursor:pointer;">
                            <img class="social-card__avatar" src="/includes/get_pfp.php?id=${r.user_id}" alt="${this.escapeHtml(r.username)}">
                        </div>
                        <h4 class="social-card__name user-card-trigger" data-user-id="${r.user_id}" style="cursor:pointer;">${this.escapeHtml(r.username)}</h4>
                        <span class="social-card__username" style="margin-bottom:15px;">${this._t('sent_on')} ${this.formatDate(r.created_at)}</span>
                        <div class="social-card__actions">
                            <button class="social-btn social-btn--primary js-social-action" data-action="accept_request" data-id="${r.user_id}"><i class="fa-solid fa-user-check"></i> ${this._t('btn_accept')}</button>
                            <button class="social-btn social-btn--danger js-social-action" data-action="decline_request" data-id="${r.user_id}"><i class="fa-solid fa-xmark"></i> ${this._t('btn_decline')}</button>
                        </div>
                    </div>
                `;
            });
        }

        if (data.sent.length > 0) {
            html += `<h3 class="col-span-full mt-4 mb-3 fw-bold fs-5 text-white static-reveal is-visible"><i class="fa-solid fa-arrow-up text-primary me-2"></i> ${this._t('requests_sent')} (${data.sent.length})</h3>`;
            data.sent.forEach(r => {
                const delay = globalIndex * 0.04;
                globalIndex++;
                html += `
                    <div class="social-card" style="animation-delay: ${delay}s;">
                        <div class="social-card__avatar-container user-card-trigger" data-user-id="${r.user_id}" style="cursor:pointer;">
                            <img class="social-card__avatar" src="/includes/get_pfp.php?id=${r.user_id}" alt="${this.escapeHtml(r.username)}">
                        </div>
                        <h4 class="social-card__name user-card-trigger" data-user-id="${r.user_id}" style="cursor:pointer;">${this.escapeHtml(r.username)}</h4>
                        <span class="social-card__username" style="margin-bottom:15px;">${this._t('sent_on')} ${this.formatDate(r.created_at)}</span>
                        <div class="social-card__actions">
                            <button class="social-btn social-btn--secondary js-social-action" data-action="cancel_request" data-id="${r.user_id}"><i class="fa-solid fa-user-clock"></i> ${this._t('btn_cancel')}</button>
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
                this.renderUserList(res.data.users, this._t('no_search_results'));
            } else {
                this.renderError(res.error.message);
            }
        } catch (e) {
            this.renderError(this._t('error_search'));
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
                    if (res.success) this.showToast(res.data.status === 'accepted' ? this._t('toast_now_friends') : this._t('toast_request_sent'));
                    break;
                case 'accept_request':
                    res = await SocialAPI.acceptFriendRequest(targetId);
                    if (res.success) this.showToast(this._t('toast_request_accepted'));
                    break;
                case 'decline_request':
                    res = await SocialAPI.declineFriendRequest(targetId);
                    if (res.success) this.showToast(this._t('toast_request_declined'));
                    break;
                case 'cancel_request':
                    res = await SocialAPI.cancelFriendRequest(targetId);
                    if (res.success) this.showToast(this._t('toast_request_cancelled'));
                    break;
                case 'remove_friend':
                    if (confirm(this._t('confirm_remove_friend'))) {
                        res = await SocialAPI.removeFriend(targetId);
                        if (res.success) this.showToast(this._t('toast_friend_removed'));
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
            this.showToast(this._t('error_action'), true);
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
        if (u.mutual_connections > 0) {
            return `
                <span class="social-card__reason">
                    <i class="fa-solid fa-user-group"></i>
                    ${u.mutual_connections} ${this._t('mutuals')}
                </span>
            `;
        } else if (u.is_follower) {
            return `
                <span class="social-card__reason">
                    <i class="fa-solid fa-arrow-left-long"></i>
                    ${this._t('follows_you')}
                </span>
            `;
        } else {
            return `
                <span class="social-card__reason">
                    <i class="fa-solid fa-fire"></i>
                    ${this._t('popular')}
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
