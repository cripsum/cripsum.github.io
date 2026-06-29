<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

if (function_exists('checkBan')) {
    checkBan($mysqli);
}

requireLogin();

$userId = (int)$_SESSION['user_id'];
$currentUser = getCurrentUser($mysqli);

$lang = 'it';
$ogDescription = 'Centro Messaggi di Cripsum™. Controlla le tue notifiche, rispondi ai ticket di supporto e riscatta i tuoi premi.';
$ogUrl = 'https://cripsum.com/it/inbox';
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Centro Messaggi - Cripsum™</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?php echo htmlspecialchars($ogDescription); ?>">

    <!-- Custom styling for inbox -->
    <link rel="stylesheet" href="/css/inbox.css?v=4.5">
    <link rel="stylesheet" href="/css/style-dark.css?v=5.0">
    <style>
        /* Stili aggiuntivi per la Chat dei Ticket */
        .inbox-chat-wrapper {
            display: flex;
            flex-direction: column;
            height: 100%;
            background: rgba(13, 20, 35, 0.2);
            border-radius: 12px;
            overflow: hidden;
        }
        .inbox-chat-messages {
            flex-grow: 1;
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background: rgba(8, 12, 24, 0.15);
        }
        /* Custom scrollbar for chat */
        .inbox-chat-messages::-webkit-scrollbar {
            width: 6px;
        }
        .inbox-chat-messages::-webkit-scrollbar-track {
            background: transparent;
        }
        .inbox-chat-messages::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 4px;
        }
        .inbox-chat-messages::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        .chat-msg-bubble {
            max-width: 75%;
            padding: 0.8rem 1.1rem;
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
            animation: msg-slide-in 0.25s ease-out;
        }
        @keyframes msg-slide-in {
            from { transform: translateY(10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .chat-msg-me {
            background: #8b5cf6;
            margin-left: auto;
            align-self: flex-end;
            border-bottom-right-radius: 2px;
        }
        .chat-msg-them {
            background: rgba(255, 255, 255, 0.04);
            align-self: flex-start;
            border-bottom-left-radius: 2px;
            border: 1px solid rgba(255, 255, 255, 0.04);
        }
        /* Custom categories badges */
        .inbox-card-category.cat-ticket {
            background: rgba(139, 92, 246, 0.12);
            color: #c084fc;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }
    </style>
</head>

<body class="home-v5-body">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="home-bg" aria-hidden="true">
        <span class="home-noise"></span>
        <span class="home-orb home-orb--one" style="background: radial-gradient(circle, rgba(139, 92, 246, 0.4) 0%, transparent 70%);"></span>
        <span class="home-orb home-orb--two" style="background: radial-gradient(circle, rgba(15, 91, 255, 0.4) 0%, transparent 70%);"></span>
        <span class="home-grid"></span>
    </div>

    <main class="inbox-container">
        <!-- Sidebar Categorie -->
        <aside class="inbox-panel inbox-sidebar" aria-label="Categorie messaggi">
            <h3 style="font-weight: 800; color: #fff; margin-bottom: 15px; font-size: 1.25rem;">
                <i class="fa-solid fa-inbox me-2" style="color: #0f5bff;"></i>Messaggi
            </h3>

            <button type="button" class="inbox-category-btn is-active" data-cat="">
                <span><i class="fa-solid fa-mail-bulk"></i>Tutti i messaggi</span>
                <span class="inbox-category-badge" id="badge-all">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="system">
                <span><i class="fa-solid fa-circle-info"></i>Notifiche di Sistema</span>
                <span class="inbox-category-badge" id="badge-system">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="rewards">
                <span><i class="fa-solid fa-gift"></i>Ricompense</span>
                <span class="inbox-category-badge" id="badge-rewards">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="special">
                <span><i class="fa-solid fa-calendar-days"></i>Eventi speciali</span>
                <span class="inbox-category-badge" id="badge-special">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="ticket">
                <span><i class="fa-solid fa-headset"></i>Ticket Supporto</span>
                <span class="inbox-category-badge" id="badge-ticket">0</span>
            </button>
        </aside>

        <!-- Main Layout: Messaggi + Dettaglio -->
        <section class="inbox-panel inbox-main-layout">

            <!-- Lista Messaggi -->
            <div class="inbox-list-pane">
                <div class="inbox-search-bar">
                    <div class="inbox-search-wrapper">
                        <i class="fa-solid fa-search inbox-search-icon"></i>
                        <input type="text" class="inbox-search-input" id="inboxSearchInput" placeholder="Cerca..." autocomplete="off">
                    </div>
                </div>

                <div class="inbox-filters">
                    <button type="button" class="inbox-filter-tab is-active" data-status="">Entrate</button>
                    <button type="button" class="inbox-filter-tab" data-status="unread">Non letti</button>
                    <button type="button" class="inbox-filter-tab" data-status="important">Importanti</button>
                    <button type="button" class="inbox-filter-tab" data-status="archived">Archivio</button>
                </div>

                <div class="inbox-cards-scroll" id="inboxCardsContainer">
                    <!-- Cards renderizzate in JS -->
                </div>
            </div>

            <!-- Vista Dettaglio -->
            <div class="inbox-view-pane" id="inboxDetailContainer">
                <div class="inbox-view-empty">
                    <i class="fa-solid fa-envelope-open"></i>
                    <h4>Seleziona una conversazione</h4>
                    <p class="text-muted" style="font-size: 0.9rem;">Clicca su un messaggio o un ticket a sinistra per leggerlo o rispondere.</p>
                </div>
            </div>

        </section>
    </main>

    <!-- Overlay per Animazione Riscatto Premi -->
    <div class="inbox-reward-modal-backdrop" id="rewardModalBackdrop">
        <div class="inbox-reward-modal">
            <div class="inbox-reward-modal-icon">
                <i class="fa-solid fa-gift"></i>
            </div>
            <div class="inbox-reward-modal-title">Premio Riscattato!</div>
            <div class="inbox-reward-modal-sub">Hai aggiunto con successo i seguenti oggetti al tuo account:</div>

            <div class="inbox-reward-modal-list" id="rewardModalList">
                <!-- Premi riscattati in JS -->
            </div>

            <button type="button" class="inbox-reward-modal-close" id="rewardModalCloseBtn">Ottimo</button>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script>
        (function() {
            'use strict';

            const API_ENDPOINT = '/api/inbox.php';
            let messagesCache = [];
            let currentMessageId = null;
            let chatPollingInterval = null;

            let filterCategory = '';
            let filterStatus = '';
            let filterSearch = '';

            const $ = (sel) => document.querySelector(sel);
            const $$ = (sel) => Array.from(document.querySelectorAll(sel));

            // Inizializzazione
            window.addEventListener('DOMContentLoaded', () => {
                loadMessages();
                setupEventListeners();
            });

            function setupEventListeners() {
                // Click Categorie Sidebar
                $$('.inbox-category-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        $$('.inbox-category-btn').forEach(b => b.classList.remove('is-active'));
                        btn.classList.add('is-active');
                        filterCategory = btn.dataset.cat;
                        currentMessageId = null;
                        
                        // Gestione filtri specifici per i ticket
                        if (filterCategory === 'ticket') {
                            $$('.inbox-filter-tab').forEach(tab => {
                                const status = tab.dataset.status;
                                if (status === 'unread' || status === 'important') {
                                    tab.style.display = 'none';
                                } else {
                                    tab.style.display = 'block';
                                    if (status === '') tab.textContent = 'Aperti';
                                    if (status === 'archived') tab.textContent = 'Chiusi';
                                }
                            });
                        } else {
                            $$('.inbox-filter-tab').forEach(tab => {
                                tab.style.display = 'block';
                                const status = tab.dataset.status;
                                if (status === '') tab.textContent = 'Entrate';
                                if (status === 'archived') tab.textContent = 'Archivio';
                            });
                        }

                        renderEmptyDetails();
                        loadMessages();
                    });
                });

                // Click Filtri Stati
                $$('.inbox-filter-tab').forEach(tab => {
                    tab.addEventListener('click', () => {
                        $$('.inbox-filter-tab').forEach(t => t.classList.remove('is-active'));
                        tab.classList.add('is-active');
                        filterStatus = tab.dataset.status;
                        currentMessageId = null;
                        renderEmptyDetails();
                        loadMessages();
                    });
                });

                // Ricerca con Debounce
                let searchTimeout;
                $('#inboxSearchInput').addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        filterSearch = e.target.value.trim();
                        loadMessages();
                    }, 300);
                });

                // Chiusura Modal Premi
                $('#rewardModalCloseBtn').addEventListener('click', () => {
                    $('#rewardModalBackdrop').classList.remove('is-visible');
                });
            }

            async function loadMessages() {
                const container = $('#inboxCardsContainer');
                container.innerHTML = `<div style="padding: 30px; text-align: center; color: rgba(255,255,255,0.4);"><i class="fa-solid fa-spinner fa-spin me-2"></i>Caricamento...</div>`;

                try {
                    if (filterCategory === 'ticket') {
                        // Carica i ticket di supporto da database
                        const response = await fetch('/api/tickets.php');
                        const res = await response.json();

                        if (!res.ok) {
                            container.innerHTML = `<div style="padding: 20px; text-align: center; color: #ef4444;">${res.error}</div>`;
                            return;
                        }

                        // Mappiamo i ticket nel formato dei messaggi
                        messagesCache = res.tickets.map(t => ({
                            message_id: t.ticket_id,
                            title_it: t.title,
                            title_en: t.title,
                            content_it: `Argomento: ${t.topic} — Stato: ${t.status === 'open' ? 'Aperto' : 'Chiuso'}`,
                            content_en: `Topic: ${t.topic} — Status: ${t.status === 'open' ? 'Open' : 'Closed'}`,
                            category: 'ticket',
                            created_at: t.created_at,
                            is_read: 1,
                            is_important: 0,
                            is_archived: t.status === 'closed' ? 1 : 0,
                            username: t.username || null,
                            topic: t.topic,
                            status: t.status
                        }));

                        // Applica filtro di stato locale (Aperti vs Chiusi)
                        if (filterStatus === 'archived') {
                            messagesCache = messagesCache.filter(m => m.status === 'closed');
                        } else {
                            messagesCache = messagesCache.filter(m => m.status === 'open');
                        }

                        updateCategoryCounters(messagesCache);
                        renderMessageList();

                        if (currentMessageId) {
                            const activeMsg = messagesCache.find(m => m.message_id === currentMessageId);
                            if (activeMsg) {
                                renderMessageDetails(activeMsg);
                            } else {
                                currentMessageId = null;
                                renderEmptyDetails();
                            }
                        } else {
                            renderEmptyDetails();
                        }

                    } else {
                        // Carica i messaggi tradizionali
                        const params = new URLSearchParams({
                            category: filterCategory,
                            status: filterStatus,
                            q: filterSearch
                        });

                        const response = await fetch(`${API_ENDPOINT}?${params}`);
                        const res = await response.json();

                        if (!res.ok) {
                            container.innerHTML = `<div style="padding: 20px; text-align: center; color: #ef4444;">${res.error}</div>`;
                            return;
                        }

                        messagesCache = res.messages || [];
                        updateNavbarBadge(res.unread_count);
                        updateCategoryCounters(messagesCache);
                        renderMessageList();

                        if (currentMessageId) {
                            const activeMsg = messagesCache.find(m => m.message_id === currentMessageId);
                            if (activeMsg) {
                                renderMessageDetails(activeMsg);
                            } else {
                                currentMessageId = null;
                                renderEmptyDetails();
                            }
                        } else {
                            renderEmptyDetails();
                        }
                    }

                } catch (error) {
                    container.innerHTML = `<div style="padding: 20px; text-align: center; color: #ef4444;">Impossibile caricare i messaggi.</div>`;
                }
            }

            function updateNavbarBadge(count) {
                const badge = document.getElementById('inbox-unread-count');
                if (badge) {
                    const c = parseInt(count, 10) || 0;
                    if (c > 0) {
                        badge.textContent = c;
                        badge.classList.remove('d-none');
                    } else {
                        badge.classList.add('d-none');
                    }
                }
            }

            function updateCategoryCounters(messages) {
                const categories = ['all', 'system', 'rewards', 'special', 'ticket'];
                categories.forEach(cat => {
                    const el = document.getElementById(`badge-${cat}`);
                    if (el) el.textContent = '0';
                });

                let counts = {
                    all: messagesCache.length
                };
                messagesCache.forEach(m => {
                    const cat = m.category;
                    if (cat === 'system' || cat === 'changelog' || cat === 'security' || cat === 'moderation') {
                        counts['system'] = (counts['system'] || 0) + 1;
                    } else {
                        counts[cat] = (counts[cat] || 0) + 1;
                    }
                });

                Object.keys(counts).forEach(cat => {
                    const el = document.getElementById(`badge-${cat}`);
                    if (el) el.textContent = counts[cat];
                });
            }

            function renderMessageList() {
                const container = $('#inboxCardsContainer');

                if (messagesCache.length === 0) {
                    container.innerHTML = `
                        <div style="padding: 50px 20px; text-align: center; color: rgba(255,255,255,0.3);">
                            <i class="fa-solid fa-folder-open" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <div>Nessun elemento trovato</div>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = messagesCache.map(msg => {
                    const isUnread = parseInt(msg.is_read) === 0 ? 'is-unread' : '';
                    const isActive = msg.message_id === currentMessageId ? 'is-active' : '';
                    const isStarred = parseInt(msg.is_important) === 1;
                    const dateFormatted = formatMessageDate(msg.created_at);

                    const starIcon = isStarred ? '<i class="fa-solid fa-star text-warning inbox-card-icon is-active"></i>' : '';
                    const giftIcon = parseInt(msg.has_rewards) > 0 ? '<i class="fa-solid fa-gift text-purple inbox-card-icon" style="color:#a78bfa;"></i>' : '';

                    const title = htmlEscape(msg.title_it);
                    const contentPlain = msg.content_it.replace(/<[^>]+>/g, '');
                    const excerpt = htmlEscape(contentPlain.substring(0, 65) + (contentPlain.length > 65 ? '...' : ''));

                    let catLabel = 'Sistema';
                    switch (msg.category) {
                        case 'system':
                        case 'changelog':
                        case 'security':
                        case 'moderation':
                            catLabel = 'Sistema';
                            break;
                        case 'rewards':
                            catLabel = 'Premio';
                            break;
                        case 'special':
                            catLabel = 'Speciale';
                            break;
                        case 'ticket':
                            catLabel = 'Ticket';
                            break;
                    }

                    return `
                        <div class="inbox-card ${isUnread} ${isActive}" data-id="${msg.message_id}">
                            <div class="inbox-card-header">
                                <span class="inbox-card-category cat-${msg.category}">${catLabel}</span>
                                <span class="inbox-card-date">${dateFormatted}</span>
                            </div>
                            <div class="inbox-card-title">${title}</div>
                            <div class="inbox-card-excerpt">${excerpt}</div>
                            <div class="inbox-card-footer">
                                ${starIcon}
                                ${giftIcon}
                            </div>
                        </div>
                    `;
                }).join('');

                $$('.inbox-card').forEach(card => {
                    card.addEventListener('click', () => {
                        const messageId = card.dataset.id;
                        selectMessage(messageId);
                    });
                });
            }

            function selectMessage(messageId) {
                // Interrompe eventuale polling chat attivo
                if (chatPollingInterval) {
                    clearInterval(chatPollingInterval);
                    chatPollingInterval = null;
                }

                currentMessageId = messageId;

                $$('.inbox-card').forEach(c => {
                    c.classList.remove('is-active');
                    if (c.dataset.id === messageId) {
                        c.classList.add('is-active');
                    }
                });

                const msg = messagesCache.find(m => m.message_id === messageId);
                if (msg) {
                    renderMessageDetails(msg);

                    if (msg.category !== 'ticket' && parseInt(msg.is_read) === 0) {
                        markAsRead(messageId);
                    }
                }
            }

            function renderEmptyDetails() {
                const pane = $('#inboxDetailContainer');
                pane.innerHTML = `
                    <div class="inbox-view-empty">
                        <i class="fa-solid fa-envelope-open"></i>
                        <h4>Seleziona una conversazione</h4>
                        <p class="text-muted" style="font-size: 0.9rem;">Clicca su un messaggio o un ticket a sinistra per leggerlo o rispondere.</p>
                    </div>
                `;
                pane.classList.remove('is-open');
            }

            function renderMessageDetails(msg) {
                const pane = $('#inboxDetailContainer');

                // Se il messaggio appartiene alla categoria Ticket, renderizziamo la Chat
                if (msg.category === 'ticket') {
                    renderTicketChat(msg.message_id, msg);
                    return;
                }

                const isStarred = parseInt(msg.is_important) === 1;
                const isArchived = parseInt(msg.is_archived) === 1;
                const dateFormatted = formatMessageDateTime(msg.created_at);

                const starClass = isStarred ? 'is-active' : '';
                const archiveText = isArchived ? '<i class="fa-solid fa-box-open"></i> <span>Ripristina</span>' : '<i class="fa-solid fa-archive"></i> <span>Archivia</span>';

                let rewardsHtml = '';
                if (parseInt(msg.has_rewards) > 0 && msg.rewards && msg.rewards.length) {
                    const isClaimed = msg.claimed_at !== null;

                    const rewardsListHtml = msg.rewards.map(rew => {
                        let icon = '🎁';
                        let label = '';
                        let sub = '';

                        switch (rew.reward_type) {
                            case 'points':
                                icon = '🪙';
                                label = `+${parseInt(rew.reward_value) * parseInt(rew.quantity)} Godos`;
                                sub = 'Valuta del sito';
                                break;
                            case 'character':
                                icon = '👤';
                                label = `Personaggio ID: ${rew.reward_value}`;
                                sub = 'Aggiunto all\'inventario';
                                break;
                            case 'badge':
                                icon = '🏆';
                                label = `Badge personalizzato ID: ${rew.reward_value}`;
                                sub = 'Profilo sbloccato';
                                break;
                            case 'premium':
                                icon = '⭐';
                                label = 'Status Premium';
                                sub = 'Vantaggi VIP attivati';
                                break;
                        }

                        return `
                            <div class="inbox-reward-item">
                                <span class="inbox-reward-icon ${rew.reward_type}">${icon}</span>
                                <div class="inbox-reward-details">
                                    <span class="inbox-reward-name">${label}</span>
                                    <span class="inbox-reward-sub">${sub}</span>
                                </div>
                            </div>
                        `;
                    }).join('');

                    const actionButton = isClaimed ?
                        `<div class="inbox-claimed-badge"><i class="fa-solid fa-circle-check"></i>Premi Riscattati</div>` :
                        `<button type="button" class="inbox-claim-btn" id="claimRewardsBtn" data-id="${msg.message_id}">
                                <i class="fa-solid fa-gift"></i>Riscatta premi
                           </button>`;

                    rewardsHtml = `
                        <div class="inbox-rewards-box">
                            <div class="inbox-rewards-header">
                                <i class="fa-solid fa-box-open"></i>Premi inclusi in questo messaggio
                            </div>
                            <div class="inbox-rewards-list">
                                ${rewardsListHtml}
                            </div>
                            ${actionButton}
                        </div>
                    `;
                }

                pane.innerHTML = `
                    <button class="inbox-action-btn inbox-mobile-back" id="inboxMobileBackBtn"><i class="fa-solid fa-arrow-left"></i> Indietro</button>
                    
                    <div class="inbox-view-header">
                        <div class="inbox-view-meta">
                            <span class="inbox-card-category cat-${msg.category}">${msg.category}</span>
                            <span class="inbox-card-date">${dateFormatted}</span>
                        </div>
                        <div class="inbox-view-actions">
                            <button type="button" class="inbox-action-btn ${starClass}" id="btnStar" data-id="${msg.message_id}"><i class="fa-solid fa-star"></i> <span>Importante</span></button>
                            <button type="button" class="inbox-action-btn" id="btnArchive" data-id="${msg.message_id}">${archiveText}</button>
                            <button type="button" class="inbox-action-btn inbox-action-btn--danger" id="btnDelete" data-id="${msg.message_id}"><i class="fa-solid fa-trash"></i> <span>Elimina</span></button>
                        </div>
                    </div>
                    
                    <h2 class="inbox-view-title">${htmlEscape(msg.title_it)}</h2>
                    <div class="inbox-view-content">${parseMarkdown(msg.content_it)}</div>
                    
                    ${rewardsHtml}
                `;

                $('#btnStar').addEventListener('click', () => toggleImportant(msg.message_id));
                $('#btnArchive').addEventListener('click', () => toggleArchive(msg.message_id));
                $('#btnDelete').addEventListener('click', () => deleteMessage(msg.message_id));

                const claimBtn = $('#claimRewardsBtn');
                if (claimBtn) {
                    claimBtn.addEventListener('click', () => claimRewards(msg.message_id));
                }

                const backBtn = $('#inboxMobileBackBtn');
                if (backBtn) {
                    backBtn.addEventListener('click', () => {
                        pane.classList.remove('is-open');
                    });
                }

                if (window.innerWidth <= 768) {
                    pane.classList.add('is-open');
                }
            }

            // Renderizza la Chat Box del Ticket
            function renderTicketChat(ticketId, ticket) {
                const pane = $('#inboxDetailContainer');
                pane.innerHTML = `
                    <button class="inbox-action-btn inbox-mobile-back" id="inboxMobileBackBtn"><i class="fa-solid fa-arrow-left"></i> Indietro</button>
                    
                    <div class="inbox-chat-wrapper" style="display: flex; flex-direction: column; height: 100%;">
                        <!-- Header Chat -->
                        <div class="inbox-chat-header" style="padding: 1.2rem; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h4 style="font-weight: 700; color: #fff; font-size: 1.1rem; margin-bottom: 2px;">
                                    🎫 Ticket ${ticketId} — ${htmlEscape(ticket.title_it)}
                                </h4>
                                <p style="font-size: 0.82rem; color: var(--text-muted);">
                                    Argomento: <span style="color: #a78bfa; font-weight:600;">${htmlEscape(ticket.topic)}</span>
                                    ${ticket.username ? ` | Utente: <strong>${htmlEscape(ticket.username)}</strong>` : ''}
                                </p>
                            </div>
                            <span class="inbox-card-category cat-ticket" style="background: ${ticket.status === 'open' ? 'rgba(35,165,90,0.12)' : 'rgba(255,255,255,0.05)'}; color: ${ticket.status === 'open' ? '#23a55a' : 'var(--text-muted)'}; border: 1px solid ${ticket.status === 'open' ? 'rgba(35,165,90,0.2)' : 'rgba(255,255,255,0.1)'}; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
                                ${ticket.status === 'open' ? 'Aperto' : 'Chiuso'}
                            </span>
                        </div>

                        <!-- Area Messaggi della Chat -->
                        <div class="inbox-chat-messages" id="chatMessagesContainer" style="flex-grow: 1; padding: 1.5rem; overflow-y: auto; display: flex; flex-direction: column; gap: 1rem; max-height: 480px; min-height: 350px;">
                            <div style="text-align: center; color: rgba(255,255,255,0.3); padding: 20px;"><i class="fa-solid fa-spinner fa-spin me-2"></i>Caricamento chat...</div>
                        </div>

                        <!-- Form Invio Messaggio -->
                        <div class="inbox-chat-input-area" style="padding: 1.2rem; border-top: 1px solid rgba(255,255,255,0.08); background: rgba(13, 20, 35, 0.25);">
                            <form id="chatSendForm" style="display: flex; gap: 10px; align-items: flex-end;">
                                <input type="hidden" name="ticket_id" value="${ticketId}">
                                
                                <!-- Allegato Foto -->
                                <div style="position: relative;">
                                    <label for="chat-attachment" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; color: #a78bfa;" title="Allega un'immagine">
                                        <i class="fa-solid fa-paperclip"></i>
                                    </label>
                                    <input type="file" id="chat-attachment" name="attachment" accept="image/*" style="display: none;">
                                </div>

                                <!-- Textarea Input -->
                                <textarea name="message" id="chatMessageInput" required rows="1" placeholder="Rispondi al ticket..." style="flex-grow: 1; padding: 0.75rem 1rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: white; outline: none; resize: none; font-family: inherit; font-size: 0.95rem; line-height: 1.4; max-height: 120px;"></textarea>

                                <!-- Bottone Invia -->
                                <button type="submit" style="background: #8b5cf6; border: none; border-radius: 8px; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; color: white; cursor: pointer; transition: background 0.2s;" title="Invia">
                                    <i class="fa-solid fa-paper-plane"></i>
                                </button>
                            </form>
                            
                            <!-- Preview dell'allegato -->
                            <div id="chat-preview-container" style="display: none; margin-top: 10px; position: relative; max-width: 120px; border-radius: 6px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1);">
                                <button type="button" id="chat-remove-preview" class="remove-preview-btn" style="position: absolute; top: 2px; right: 2px; background: rgba(239,68,68,0.8); border: none; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
                                <img id="chat-image-preview" src="" style="width: 100%; height: auto; display: block;">
                            </div>
                        </div>
                    </div>
                `;

                loadTicketMessages(ticketId);
                setupChatFormEvents(ticketId);

                const backBtn = $('#inboxMobileBackBtn');
                if (backBtn) {
                    backBtn.addEventListener('click', () => {
                        pane.classList.remove('is-open');
                    });
                }

                if (window.innerWidth <= 768) {
                    pane.classList.add('is-open');
                }
            }

            async function loadTicketMessages(ticketId) {
                if (chatPollingInterval) clearInterval(chatPollingInterval);

                const container = $('#chatMessagesContainer');
                try {
                    const response = await fetch(`/api/tickets.php?ticket_id=${ticketId}`);
                    const res = await response.json();

                    if (!res.ok) {
                        container.innerHTML = `<div style="padding: 20px; text-align: center; color: #ef4444;">${res.error}</div>`;
                        return;
                    }

                    renderChatMessages(res.messages);

                    // Polling ogni 4 secondi per aggiornare la chat in tempo reale
                    chatPollingInterval = setInterval(async () => {
                        if (currentMessageId !== ticketId) {
                            clearInterval(chatPollingInterval);
                            return;
                        }
                        const pollResponse = await fetch(`/api/tickets.php?ticket_id=${ticketId}`);
                        const pollRes = await pollResponse.json();
                        if (pollRes.ok) {
                            const currentCount = container.querySelectorAll('.chat-msg-bubble').length;
                            if (pollRes.messages.length !== currentCount) {
                                renderChatMessages(pollRes.messages);
                            }
                        }
                    }, 4000);

                } catch (e) {
                    container.innerHTML = `<div style="padding: 20px; text-align: center; color: #ef4444;">Impossibile caricare i messaggi della chat.</div>`;
                }
            }

            function renderChatMessages(messages) {
                const container = $('#chatMessagesContainer');
                if (messages.length === 0) {
                    container.innerHTML = `<div style="text-align: center; color: rgba(255,255,255,0.2); padding: 20px;">Nessun messaggio nella chat.</div>`;
                    return;
                }

                const loggedUserId = parseInt('<?php echo $userId; ?>', 10);

                container.innerHTML = messages.map(msg => {
                    const isMe = parseInt(msg.sender_id, 10) === loggedUserId;
                    const bubbleBg = isMe ? 'background: #8b5cf6; margin-left: auto; align-self: flex-end; border-bottom-right-radius: 2px;' : 'background: rgba(255,255,255,0.04); align-self: flex-start; border-bottom-left-radius: 2px; border: 1px solid rgba(255,255,255,0.04);';
                    
                    let roleBadge = '';
                    if (msg.ruolo === 'admin' || msg.ruolo === 'owner') {
                        roleBadge = `<span style="background: rgba(239,68,68,0.15); color: #ef4444; font-size: 0.62rem; font-weight: 700; padding: 1px 5px; border-radius: 4px; margin-left: 5px; text-transform: uppercase; letter-spacing:0.02em;">Staff</span>`;
                    }

                    const timeFormatted = formatMessageTime(msg.created_at);

                    const attachmentMarkup = msg.attachment_url ? `
                        <div style="margin-top: 8px; max-width: 280px; border-radius: 8px; overflow: hidden; border: 1px solid rgba(255,255,255,0.08);">
                            <a href="${msg.attachment_url}" target="_blank">
                                <img src="${msg.attachment_url}" style="width: 100%; display: block; max-height: 200px; object-fit: cover;">
                            </a>
                        </div>
                    ` : '';

                    return `
                        <div class="chat-msg-bubble" style="${bubbleBg}">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; font-size: 0.72rem; color: rgba(255,255,255,0.4); margin-bottom: 2px;">
                                <span><strong>${htmlEscape(msg.username || 'Utente')}</strong>${roleBadge}</span>
                                <span>${timeFormatted}</span>
                            </div>
                            <div style="font-size: 0.92rem; color: #fff; word-break: break-word; white-space: pre-line; line-height: 1.4; text-align: left;">${htmlEscape(msg.message)}</div>
                            ${attachmentMarkup}
                        </div>
                    `;
                }).join('');

                container.scrollTop = container.scrollHeight;
            }

            function setupChatFormEvents(ticketId) {
                const form = $('#chatSendForm');
                const fileInput = $('#chat-attachment');
                const previewContainer = $('#chat-preview-container');
                const imagePreview = $('#chat-image-preview');
                const removePreview = $('#chat-remove-preview');
                const messageInput = $('#chatMessageInput');

                if (fileInput) {
                    fileInput.addEventListener('change', function() {
                        const file = this.files[0];
                        if (file) {
                            if (!file.type.startsWith('image/')) {
                                alert('Per favore seleziona solo file di tipo immagine.');
                                this.value = '';
                                return;
                            }
                            if (file.size > 5 * 1024 * 1024) {
                                alert('L\'immagine non può superare i 5MB.');
                                this.value = '';
                                return;
                            }

                            const reader = new FileReader();
                            reader.onload = function(e) {
                                imagePreview.src = e.target.result;
                                previewContainer.style.display = 'block';
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                }

                if (removePreview) {
                    removePreview.addEventListener('click', function(e) {
                        e.preventDefault();
                        fileInput.value = '';
                        previewContainer.style.display = 'none';
                        imagePreview.src = '';
                    });
                }

                if (form) {
                    form.addEventListener('submit', async function(e) {
                        e.preventDefault();
                        const messageText = messageInput.value.trim();
                        if (!messageText) return;

                        const formData = new FormData(form);
                        
                        const submitBtn = form.querySelector('button[type="submit"]');
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

                        try {
                            const response = await fetch('/api/tickets.php', {
                                method: 'POST',
                                body: formData
                            });
                            const res = await response.json();

                            if (res.ok) {
                                messageInput.value = '';
                                fileInput.value = '';
                                previewContainer.style.display = 'none';
                                imagePreview.src = '';
                                
                                loadTicketMessages(ticketId);
                            } else {
                                alert('Impossibile inviare: ' + res.error);
                            }
                        } catch (err) {
                            alert('Errore di connessione durante l\'invio.');
                        } finally {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i>';
                        }
                    });

                    // Invio con tasto Invio (e Shift+Invio per andare a capo)
                    messageInput.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            form.requestSubmit();
                        }
                    });
                }
            }

            async function markAsRead(messageId) {
                try {
                    const response = await fetch(API_ENDPOINT, {
                        method: 'POST',
                        body: JSON.stringify({
                            action: 'read',
                            message_id: messageId
                        })
                    });
                    const res = await response.json();
                    if (res.ok) {
                        const msg = messagesCache.find(m => m.message_id === messageId);
                        if (msg && parseInt(msg.is_read) === 0) {
                            msg.is_read = 1;

                            const card = $(`.inbox-card[data-id="${messageId}"]`);
                            if (card) card.classList.remove('is-unread');

                            const badge = document.getElementById('inbox-unread-count');
                            if (badge) {
                                let c = parseInt(badge.textContent, 10) || 0;
                                c = Math.max(0, c - 1);
                                if (c > 0) {
                                    badge.textContent = c;
                                } else {
                                    badge.classList.add('d-none');
                                }
                            }
                        }
                    }
                } catch (e) {
                    console.error("Errore nella lettura del messaggio", e);
                }
            }

            async function toggleImportant(messageId) {
                try {
                    const response = await fetch(API_ENDPOINT, {
                        method: 'POST',
                        body: JSON.stringify({
                            action: 'toggle_important',
                            message_id: messageId
                        })
                    });
                    const res = await response.json();
                    if (res.ok) {
                        const msg = messagesCache.find(m => m.message_id === messageId);
                        if (msg) msg.is_important = res.is_important;
                        loadMessages();
                    }
                } catch (e) {
                    alert("Impossibile aggiornare lo stato importante.");
                }
            }

            async function toggleArchive(messageId) {
                currentMessageId = null;
                renderEmptyDetails();
                try {
                    const response = await fetch(API_ENDPOINT, {
                        method: 'POST',
                        body: JSON.stringify({
                            action: 'toggle_archive',
                            message_id: messageId
                        })
                    });
                    const res = await response.json();
                    if (res.ok) {
                        loadMessages();
                    } else {
                        alert("Errore: " + res.error);
                        loadMessages();
                    }
                } catch (e) {
                    alert("Impossibile archiviare il messaggio.");
                    loadMessages();
                }
            }

            async function deleteMessage(messageId) {
                if (!confirm("Sei sicuro di voler eliminare questo messaggio? Questa azione non può essere annullata.")) {
                    return;
                }

                currentMessageId = null;
                renderEmptyDetails();

                try {
                    const response = await fetch(API_ENDPOINT, {
                        method: 'POST',
                        body: JSON.stringify({
                            action: 'delete',
                            message_id: messageId
                        })
                    });
                    const res = await response.json();
                    if (res.ok) {
                        loadMessages();
                    } else {
                        alert(res.error);
                        loadMessages();
                    }
                } catch (e) {
                    alert("Impossibile eliminare il messaggio.");
                    loadMessages();
                }
            }

            async function claimRewards(messageId) {
                const btn = $('#claimRewardsBtn');
                if (!btn) return;

                btn.disabled = true;
                btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-2"></i>Riscatto in corso...`;

                try {
                    const response = await fetch(API_ENDPOINT, {
                        method: 'POST',
                        body: JSON.stringify({
                            action: 'claim_rewards',
                            message_id: messageId
                        })
                    });
                    const res = await response.json();

                    if (!res.ok) {
                        btn.disabled = false;
                        btn.innerHTML = `<i class="fa-solid fa-gift"></i>Riscatta premi`;
                        alert(res.error || "Impossibile riscattare i premi.");
                        return;
                    }

                    const listContainer = $('#rewardModalList');
                    listContainer.innerHTML = res.rewards.map(rew => {
                        let icon = '🎁';
                        if (rew.type === 'points') icon = '🪙';
                        else if (rew.type === 'character') icon = '👤';
                        else if (rew.type === 'badge') icon = '🏆';
                        else if (rew.type === 'premium') icon = '⭐';

                        return `
                            <div class="inbox-reward-modal-item">
                                <span>${icon}</span>
                                <span>${htmlEscape(rew.label)}</span>
                            </div>
                        `;
                    }).join('');

                    $('#rewardModalBackdrop').classList.add('is-visible');
                    loadMessages();

                } catch (e) {
                    btn.disabled = false;
                    btn.innerHTML = `<i class="fa-solid fa-gift"></i>Riscatta premi`;
                    alert("Si è verificato un errore di connessione.");
                }
            }

            // Helpers di formattazione
            function formatMessageDate(dateStr) {
                const date = new Date(dateStr.replace(' ', 'T'));
                if (isNaN(date.getTime())) return dateStr;

                const now = new Date();
                const isToday = date.toDateString() === now.toDateString();

                if (isToday) {
                    return date.toLocaleTimeString('it-IT', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                } else {
                    return date.toLocaleDateString('it-IT', {
                        day: '2-digit',
                        month: '2-digit'
                    });
                }
            }

            function formatMessageDateTime(dateStr) {
                const date = new Date(dateStr.replace(' ', 'T'));
                if (isNaN(date.getTime())) return dateStr;
                return date.toLocaleString('it-IT', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

            function formatMessageTime(dateStr) {
                const date = new Date(dateStr.replace(' ', 'T'));
                if (isNaN(date.getTime())) return dateStr;
                return date.toLocaleTimeString('it-IT', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

            function htmlEscape(str) {
                return String(str || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function parseMarkdown(text) {
                let html = htmlEscape(text);
                
                html = html.replace(/^### (.*?)$/gm, '<h3 style="color:#c084fc; margin:16px 0 8px 0; font-weight:750; font-size:1.2rem;">$1</h3>');
                html = html.replace(/^## (.*?)$/gm, '<h2 style="color:#c084fc; margin:20px 0 10px 0; font-weight:800; font-size:1.4rem;">$1</h2>');
                html = html.replace(/^# (.*?)$/gm, '<h1 style="color:#c084fc; margin:24px 0 12px 0; font-weight:900; font-size:1.6rem;">$1</h1>');
                
                html = html.replace(/^&gt;\s+(.*?)$/gm, '<blockquote style="border-left: 4px solid #8b5cf6; padding-left: 12px; margin: 12px 0; color: rgba(255,255,255,0.7); font-style: italic;">$1</blockquote>');
                
                html = html.replace(/^(?:-|\*)\s+(.*?)$/gm, '<li style="margin-left: 20px; list-style-type: disc; margin-bottom: 4px;">$1</li>');
                
                html = html.replace(/!\[(.*?)\]\(([^)]+)\)/g, function(match, alt, url) {
                    const cleanUrl = url.replace(/&amp;/g, '&');
                    return `<img src="${cleanUrl}" alt="${alt}" class="inbox-embedded-img">`;
                });
                
                html = html.replace(/\[(.*?)\]\(([^)]+)\)/g, function(match, label, url) {
                    const cleanUrl = url.replace(/&amp;/g, '&');
                    return `<a href="${cleanUrl}" target="_blank" rel="noopener noreferrer" style="color:#8b5cf6; text-decoration:underline; font-weight:600; transition:color 0.2s;" onmouseover="this.style.color=\'#a78bfa\'" onmouseout="this.style.color=\'#8b5cf6\'">${label}</a>`;
                });
                
                html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                
                return html;
            }

        })();
    </script>
</body>

</html>