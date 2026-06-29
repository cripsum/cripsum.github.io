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

$lang = 'en';
$ogDescription = 'Cripsum™ Message Center. Check your notifications, reply to support tickets, and claim your rewards.';
$ogUrl = 'https://cripsum.com/en/inbox';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Message Center - Cripsum™</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?php echo htmlspecialchars($ogDescription); ?>">

    <!-- Custom styling for inbox -->
    <link rel="stylesheet" href="/css/inbox.css?v=4.5">
    <link rel="stylesheet" href="/css/style-dark.css?v=5.0">
    <style>
        /* Additional styling for Ticket Chat */
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
            gap: 1.2rem;
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
        
        /* Message Structure with Profile Picture */
        .chat-msg-row {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            max-width: 80%;
            animation: msg-slide-in 0.2s ease-out;
        }
        .msg-row-me {
            margin-left: auto;
            flex-direction: row-reverse;
        }
        .msg-row-them {
            margin-right: auto;
        }
        
        .chat-msg-bubble {
            padding: 0.8rem 1.1rem;
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
            color: #f1f5f9;
        }
        @keyframes msg-slide-in {
            from { transform: translateY(8px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .chat-msg-me {
            background: #6d28d9; /* Deep Premium Purple */
            border-bottom-right-radius: 2px;
        }
        .chat-msg-them {
            background: #1e293b; /* Dark Slate Blue */
            border-bottom-left-radius: 2px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .inbox-card-category.cat-ticket {
            background: rgba(139, 92, 246, 0.12);
            color: #c084fc;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        /* Toggle Ticket Status Buttons */
        .btn-toggle-ticket {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            background: transparent;
            font-family: inherit;
        }
        .btn-toggle-ticket--close {
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .btn-toggle-ticket--close:hover {
            background: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
        }
        .btn-toggle-ticket--reopen {
            color: #23a55a;
            border: 1px solid rgba(35, 165, 90, 0.3);
        }
        .btn-toggle-ticket--reopen:hover {
            background: rgba(35, 165, 90, 0.1);
            border-color: #23a55a;
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
        <!-- Sidebar Categories -->
        <aside class="inbox-panel inbox-sidebar" aria-label="Message categories">
            <h3 style="font-weight: 800; color: #fff; margin-bottom: 15px; font-size: 1.25rem;">
                <i class="fa-solid fa-inbox me-2" style="color: #0f5bff;"></i>Messages
            </h3>

            <button type="button" class="inbox-category-btn is-active" data-cat="">
                <span><i class="fa-solid fa-mail-bulk"></i>All Messages</span>
                <span class="inbox-category-badge" id="badge-all">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="system">
                <span><i class="fa-solid fa-circle-info"></i>System Notifications</span>
                <span class="inbox-category-badge" id="badge-system">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="rewards">
                <span><i class="fa-solid fa-gift"></i>Rewards</span>
                <span class="inbox-category-badge" id="badge-rewards">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="special">
                <span><i class="fa-solid fa-calendar-days"></i>Special Events</span>
                <span class="inbox-category-badge" id="badge-special">0</span>
            </button>
            
            <!-- Divider to separate support tickets -->
            <div style="margin: 15px 10px 10px 10px; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 12px;">
                <span style="font-size: 0.72rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">Support</span>
            </div>
            
            <button type="button" class="inbox-category-btn" data-cat="ticket">
                <span><i class="fa-solid fa-headset"></i>Support Tickets</span>
                <span class="inbox-category-badge" id="badge-ticket">0</span>
            </button>
        </aside>

        <!-- Main Layout: Messages + Details -->
        <section class="inbox-panel inbox-main-layout">

            <!-- Message List -->
            <div class="inbox-list-pane">
                <div class="inbox-search-bar">
                    <div class="inbox-search-wrapper">
                        <i class="fa-solid fa-search inbox-search-icon"></i>
                        <input type="text" class="inbox-search-input" id="inboxSearchInput" placeholder="Search..." autocomplete="off">
                    </div>
                </div>

                <div class="inbox-filters">
                    <button type="button" class="inbox-filter-tab is-active" data-status="">Inbox</button>
                    <button type="button" class="inbox-filter-tab" data-status="unread">Unread</button>
                    <button type="button" class="inbox-filter-tab" data-status="important">Starred</button>
                    <button type="button" class="inbox-filter-tab" data-status="archived">Archive</button>
                </div>

                <div class="inbox-cards-scroll" id="inboxCardsContainer">
                    <!-- Cards rendered in JS -->
                </div>
            </div>

            <!-- Details Pane -->
            <div class="inbox-view-pane" id="inboxDetailContainer">
                <div class="inbox-view-empty">
                    <i class="fa-solid fa-envelope-open"></i>
                    <h4>Select a conversation</h4>
                    <p class="text-muted" style="font-size: 0.9rem;">Click on a message or a ticket on the left to read or reply.</p>
                </div>
            </div>

        </section>
    </main>

    <!-- Overlay for Rewards Claim Animation -->
    <div class="inbox-reward-modal-backdrop" id="rewardModalBackdrop">
        <div class="inbox-reward-modal">
            <div class="inbox-reward-modal-icon">
                <i class="fa-solid fa-gift"></i>
            </div>
            <div class="inbox-reward-modal-title">Reward Claimed!</div>
            <div class="inbox-reward-modal-sub">You have successfully added the following items to your account:</div>

            <div class="inbox-reward-modal-list" id="rewardModalList">
                <!-- Claimed rewards in JS -->
            </div>

            <button type="button" class="inbox-reward-modal-close" id="rewardModalCloseBtn">Awesome</button>
        </div>
    </div>

    <?php include '../includes/footer-en.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script>
        (function() {
            'use strict';

            const API_ENDPOINT = '/api/inbox.php';
            const isAdmin = <?php echo ($currentUser['ruolo'] === 'admin' || $currentUser['ruolo'] === 'owner') ? 'true' : 'false'; ?>;
            
            // Global caches loaded once to prevent the unread badge dropping to 0 bug
            let globalMessages = [];
            let globalTickets = [];
            let messagesCache = []; // Filtered list currently displayed

            let currentMessageId = null;
            let chatPollingInterval = null;

            let filterCategory = '';
            let filterStatus = '';
            let filterSearch = '';

            const $ = (sel) => document.querySelector(sel);
            const $$ = (sel) => Array.from(document.querySelectorAll(sel));

            // Initialization
            window.addEventListener('DOMContentLoaded', () => {
                loadMessages();
                setupEventListeners();
            });

            function setupEventListeners() {
                // Sidebar Category Clicks
                $$('.inbox-category-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        $$('.inbox-category-btn').forEach(b => b.classList.remove('is-active'));
                        btn.classList.add('is-active');
                        filterCategory = btn.dataset.cat;
                        currentMessageId = null;
                        
                        // Manage specific filters for tickets
                        if (filterCategory === 'ticket') {
                            $$('.inbox-filter-tab').forEach(tab => {
                                const status = tab.dataset.status;
                                if (status === 'unread' || status === 'important') {
                                    tab.style.display = 'none';
                                } else {
                                    tab.style.display = 'block';
                                    if (status === '') tab.textContent = 'Open';
                                    if (status === 'archived') tab.textContent = 'Closed';
                                }
                            });
                        } else {
                            $$('.inbox-filter-tab').forEach(tab => {
                                tab.style.display = 'block';
                                const status = tab.dataset.status;
                                if (status === '') tab.textContent = 'Inbox';
                                if (status === 'archived') tab.textContent = 'Archive';
                            });
                        }

                        renderEmptyDetails();
                        renderFilteredList();
                    });
                });

                // Status Filter Clicks
                $$('.inbox-filter-tab').forEach(tab => {
                    tab.addEventListener('click', () => {
                        $$('.inbox-filter-tab').forEach(t => t.classList.remove('is-active'));
                        tab.classList.add('is-active');
                        filterStatus = tab.dataset.status;
                        currentMessageId = null;
                        renderEmptyDetails();
                        renderFilteredList();
                    });
                });

                // Search with Debounce
                let searchTimeout;
                $('#inboxSearchInput').addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        filterSearch = e.target.value.trim();
                        renderFilteredList();
                    }, 300);
                });

                // Close Rewards Modal
                $('#rewardModalCloseBtn').addEventListener('click', () => {
                    $('#rewardModalBackdrop').classList.remove('is-visible');
                });
            }

            async function loadMessages() {
                const container = $('#inboxCardsContainer');
                if (globalMessages.length === 0 && globalTickets.length === 0) {
                    container.innerHTML = `<div style="padding: 30px; text-align: center; color: rgba(255,255,255,0.4);"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading...</div>`;
                }

                try {
                    // Load messages and tickets in parallel
                    const [msgRes, ticketRes] = await Promise.all([
                        fetch(API_ENDPOINT).then(r => r.json()),
                        fetch('/api/tickets.php').then(r => r.json())
                    ]);

                    if (msgRes.ok) {
                        globalMessages = msgRes.messages || [];
                        updateNavbarBadge(msgRes.unread_count);
                    }
                    
                    if (ticketRes.ok) {
                        globalTickets = ticketRes.tickets || [];
                    }

                    // Calculate counters globally
                    updateCategoryCounters();

                    // Apply filters locally and render
                    renderFilteredList();

                    // If there is an active conversation, update it with fresh server data
                    if (currentMessageId) {
                        let activeMsg = null;
                        if (filterCategory === 'ticket') {
                            const t = globalTickets.find(x => x.ticket_id === currentMessageId);
                            if (t) {
                                activeMsg = {
                                    message_id: t.ticket_id,
                                    title_it: t.title,
                                    title_en: t.title,
                                    category: 'ticket',
                                    topic: t.topic,
                                    status: t.status,
                                    username: t.username
                                };
                            }
                        } else {
                            activeMsg = globalMessages.find(x => x.message_id === parseInt(currentMessageId, 10));
                        }

                        if (activeMsg) {
                            renderMessageDetails(activeMsg);
                        } else {
                            currentMessageId = null;
                            renderEmptyDetails();
                        }
                    }

                } catch (error) {
                    container.innerHTML = `<div style="padding: 20px; text-align: center; color: #ef4444;">Unable to load messages.</div>`;
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

            function updateCategoryCounters() {
                // 1. All Messages (non-archived)
                const activeMessages = globalMessages.filter(m => parseInt(m.is_archived) === 0);
                $('#badge-all').textContent = activeMessages.length;

                // 2. System (system, changelog, security, moderation non-archived)
                const systemCount = activeMessages.filter(m => 
                    m.category === 'system' || m.category === 'changelog' || m.category === 'security' || m.category === 'moderation'
                ).length;
                $('#badge-system').textContent = systemCount;

                // 3. Rewards
                const rewardsCount = activeMessages.filter(m => m.category === 'rewards').length;
                $('#badge-rewards').textContent = rewardsCount;

                // 4. Special Events
                const specialCount = activeMessages.filter(m => m.category === 'special').length;
                $('#badge-special').textContent = specialCount;

                // 5. Open Tickets
                const openTicketsCount = globalTickets.filter(t => t.status === 'open').length;
                $('#badge-ticket').textContent = openTicketsCount;
            }

            function renderFilteredList() {
                const container = $('#inboxCardsContainer');
                let filtered = [];

                if (filterCategory === 'ticket') {
                    filtered = globalTickets.map(t => ({
                        message_id: t.ticket_id,
                        title_it: t.title,
                        title_en: t.title,
                        content_it: `Topic: ${t.topic} — Status: ${t.status === 'open' ? 'Open' : 'Closed'}`,
                        content_en: `Topic: ${t.topic} — Status: ${t.status === 'open' ? 'Open' : 'Closed'}`,
                        category: 'ticket',
                        created_at: t.created_at,
                        is_read: t.is_unread_status, // 1 = read, 0 = unread
                        is_important: 0,
                        is_archived: t.status === 'closed' ? 1 : 0,
                        username: t.username || null,
                        topic: t.topic,
                        status: t.status
                    }));

                    if (filterStatus === 'archived') {
                        filtered = filtered.filter(m => m.status === 'closed');
                    } else {
                        filtered = filtered.filter(m => m.status === 'open');
                    }

                } else {
                    filtered = globalMessages;

                    if (filterCategory !== '') {
                        if (filterCategory === 'system') {
                            filtered = filtered.filter(m => 
                                m.category === 'system' || m.category === 'changelog' || m.category === 'security' || m.category === 'moderation'
                            );
                        } else {
                            filtered = filtered.filter(m => m.category === filterCategory);
                        }
                    }

                    if (filterStatus === 'unread') {
                        filtered = filtered.filter(m => parseInt(m.is_read) === 0 && parseInt(m.is_archived) === 0);
                    } else if (filterStatus === 'important') {
                        filtered = filtered.filter(m => parseInt(m.is_important) === 1 && parseInt(m.is_archived) === 0);
                    } else if (filterStatus === 'archived') {
                        filtered = filtered.filter(m => parseInt(m.is_archived) === 1);
                    } else {
                        filtered = filtered.filter(m => parseInt(m.is_archived) === 0);
                    }
                }

                if (filterSearch !== '') {
                    const q = filterSearch.toLowerCase();
                    filtered = filtered.filter(m => 
                        m.title_it.toLowerCase().includes(q) || 
                        m.title_en.toLowerCase().includes(q) || 
                        m.content_it.toLowerCase().includes(q) || 
                        m.content_en.toLowerCase().includes(q)
                    );
                }

                messagesCache = filtered;

                if (filtered.length === 0) {
                    container.innerHTML = `
                        <div style="padding: 50px 20px; text-align: center; color: rgba(255,255,255,0.3);">
                            <i class="fa-solid fa-folder-open" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <div>No items found</div>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = filtered.map(msg => {
                    const isUnread = parseInt(msg.is_read) === 0 ? 'is-unread' : '';
                    const isActive = msg.message_id === currentMessageId ? 'is-active' : '';
                    const isStarred = parseInt(msg.is_important) === 1;
                    const dateFormatted = formatMessageDate(msg.created_at);

                    const starIcon = isStarred ? '<i class="fa-solid fa-star text-warning inbox-card-icon is-active"></i>' : '';
                    const giftIcon = parseInt(msg.has_rewards) > 0 ? '<i class="fa-solid fa-gift text-purple inbox-card-icon" style="color:#a78bfa;"></i>' : '';

                    const title = htmlEscape(msg.title_en);
                    const contentPlain = msg.content_en.replace(/<[^>]+>/g, '');
                    const excerpt = htmlEscape(contentPlain.substring(0, 65) + (contentPlain.length > 65 ? '...' : ''));

                    let catLabel = 'System';
                    switch (msg.category) {
                        case 'system':
                        case 'changelog':
                        case 'security':
                        case 'moderation':
                            catLabel = 'System';
                            break;
                        case 'rewards':
                            catLabel = 'Reward';
                            break;
                        case 'special':
                            catLabel = 'Special';
                            break;
                        case 'ticket':
                            catLabel = 'Ticket';
                            break;
                    }

                    return `
                        <div class="inbox-card ${isUnread} ${isActive}" data-id="${msg.message_id}" data-cat="${msg.category}">
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
                        selectMessage(card.dataset.id, card.dataset.cat);
                    });
                });
            }

            function selectMessage(messageId, category) {
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

                if (category === 'ticket') {
                    const ticket = globalTickets.find(t => t.ticket_id === messageId);
                    if (ticket) {
                        const mappedTicket = {
                            message_id: ticket.ticket_id,
                            title_it: ticket.title,
                            title_en: ticket.title,
                            category: 'ticket',
                            topic: ticket.topic,
                            status: ticket.status,
                            username: ticket.username
                        };
                        renderMessageDetails(mappedTicket);

                        if (parseInt(ticket.is_unread_status) === 0) {
                            ticket.is_unread_status = 1;
                            
                            const card = $(`.inbox-card[data-id="${messageId}"]`);
                            if (card) card.classList.remove('is-unread');

                            fetch(`/api/tickets.php?ticket_id=${messageId}`).then(() => {
                                fetch(API_ENDPOINT).then(r => r.json()).then(res => {
                                    if (res.ok) updateNavbarBadge(res.unread_count);
                                });
                            });
                        }
                    }
                } else {
                    const msg = globalMessages.find(m => m.message_id === parseInt(messageId, 10));
                    if (msg) {
                        renderMessageDetails(msg);

                        if (parseInt(msg.is_read) === 0) {
                            markAsRead(parseInt(messageId, 10));
                        }
                    }
                }
            }

            function renderEmptyDetails() {
                const pane = $('#inboxDetailContainer');
                pane.innerHTML = `
                    <div class="inbox-view-empty">
                        <i class="fa-solid fa-envelope-open"></i>
                        <h4>Select a conversation</h4>
                        <p class="text-muted" style="font-size: 0.9rem;">Click on a message or a ticket on the left to read or reply.</p>
                    </div>
                `;
                pane.classList.remove('is-open');
            }

            function renderMessageDetails(msg) {
                const pane = $('#inboxDetailContainer');

                if (msg.category === 'ticket') {
                    renderTicketChat(msg.message_id, msg);
                    return;
                }

                const isStarred = parseInt(msg.is_important) === 1;
                const isArchived = parseInt(msg.is_archived) === 1;
                const dateFormatted = formatMessageDateTime(msg.created_at);

                const starClass = isStarred ? 'is-active' : '';
                const archiveText = isArchived ? '<i class="fa-solid fa-box-open"></i> <span>Restore</span>' : '<i class="fa-solid fa-archive"></i> <span>Archive</span>';

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
                                sub = 'Site currency';
                                break;
                            case 'character':
                                icon = '👤';
                                label = `Character ID: ${rew.reward_value}`;
                                sub = 'Added to inventory';
                                break;
                            case 'badge':
                                icon = '🏆';
                                label = `Custom badge ID: ${rew.reward_value}`;
                                sub = 'Profile unlocked';
                                break;
                            case 'premium':
                                icon = '⭐';
                                label = 'Premium Status';
                                sub = 'VIP perks activated';
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
                        `<div class="inbox-claimed-badge"><i class="fa-solid fa-circle-check"></i>Rewards Claimed</div>` :
                        `<button type="button" class="inbox-claim-btn" id="claimRewardsBtn" data-id="${msg.message_id}">
                                <i class="fa-solid fa-gift"></i>Claim rewards
                           </button>`;

                    rewardsHtml = `
                        <div class="inbox-rewards-box">
                            <div class="inbox-rewards-header">
                                <i class="fa-solid fa-box-open"></i>Rewards included in this message
                            </div>
                            <div class="inbox-rewards-list">
                                ${rewardsListHtml}
                            </div>
                            ${actionButton}
                        </div>
                    `;
                }

                pane.innerHTML = `
                    <button class="inbox-action-btn inbox-mobile-back" id="inboxMobileBackBtn"><i class="fa-solid fa-arrow-left"></i> Back</button>
                    
                    <div class="inbox-view-header">
                        <div class="inbox-view-meta">
                            <span class="inbox-card-category cat-${msg.category}">${msg.category}</span>
                            <span class="inbox-card-date">${dateFormatted}</span>
                        </div>
                        <div class="inbox-view-actions">
                            <button type="button" class="inbox-action-btn ${starClass}" id="btnStar" data-id="${msg.message_id}"><i class="fa-solid fa-star"></i> <span>Important</span></button>
                            <button type="button" class="inbox-action-btn" id="btnArchive" data-id="${msg.message_id}">${archiveText}</button>
                            <button type="button" class="inbox-action-btn inbox-action-btn--danger" id="btnDelete" data-id="${msg.message_id}"><i class="fa-solid fa-trash"></i> <span>Delete</span></button>
                        </div>
                    </div>
                    
                    <h2 class="inbox-view-title">${htmlEscape(msg.title_en)}</h2>
                    <div class="inbox-view-content">${parseMarkdown(msg.content_en)}</div>
                    
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

            // Close or Reopen Ticket
            async function toggleTicketStatus(ticketId) {
                const btn = $('#btnToggleTicket');
                if (!btn) return;
                
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'toggle_status');
                    formData.append('ticket_id', ticketId);
                    
                    const response = await fetch('/api/tickets.php', {
                        method: 'POST',
                        body: formData
                    });
                    const res = await response.json();
                    
                    if (res.ok) {
                        loadMessages();
                    } else {
                        alert('Unable to update ticket status: ' + res.error);
                        btn.disabled = false;
                    }
                } catch (e) {
                    alert('Connection error.');
                    btn.disabled = false;
                }
            }

            // Render Ticket Chat Box
            function renderTicketChat(ticketId, ticket) {
                const pane = $('#inboxDetailContainer');
                
                // Toggle Ticket Status Buttons (ONLY FOR ADMIN/OWNER)
                const isClosed = ticket.status === 'closed';
                let toggleBtnHtml = '';
                if (isAdmin) {
                    toggleBtnHtml = isClosed ? 
                        `<button class="btn-toggle-ticket btn-toggle-ticket--reopen" id="btnToggleTicket"><i class="fa-solid fa-envelope-open me-1"></i>Reopen Ticket</button>` :
                        `<button class="btn-toggle-ticket btn-toggle-ticket--close" id="btnToggleTicket"><i class="fa-solid fa-lock me-1"></i>Close Ticket</button>`;
                }

                // If the ticket is closed, replace input form with a banner
                const inputAreaHtml = isClosed ? `
                    <div class="inbox-chat-input-area" style="padding: 1rem 1.2rem; border-top: 1px solid rgba(255,255,255,0.08); background: rgba(239, 68, 68, 0.05); text-align: center; color: #ef4444; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fa-solid fa-lock"></i>
                        <span>This ticket is closed. Reopen it to send new messages.</span>
                    </div>
                ` : `
                    <div class="inbox-chat-input-area" style="padding: 0.7rem 1.2rem; border-top: 1px solid rgba(255,255,255,0.08); background: rgba(13, 20, 35, 0.25);">
                        <form id="chatSendForm" style="display: flex; gap: 10px; align-items: center;">
                            <input type="hidden" name="ticket_id" value="${ticketId}">
                            
                            <!-- File Attachment -->
                            <div style="position: relative;">
                                <label for="chat-attachment" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; color: #a78bfa;" title="Attach an image">
                                    <i class="fa-solid fa-paperclip"></i>
                                </label>
                                <input type="file" id="chat-attachment" name="attachment" accept="image/*" style="display: none;">
                            </div>

                            <!-- Textarea Input -->
                            <textarea name="message" id="chatMessageInput" required rows="1" placeholder="Reply to ticket..." style="flex-grow: 1; padding: 0.5rem 1rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: white; outline: none; resize: none; font-family: inherit; font-size: 0.95rem; line-height: 1.4; height: 38px; min-height: 38px; max-height: 80px;"></textarea>

                            <!-- Send Button -->
                            <button type="submit" style="background: #8b5cf6; border: none; border-radius: 8px; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; color: white; cursor: pointer; transition: background 0.2s;" title="Send">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </form>
                        
                        <!-- Attachment Preview -->
                        <div id="chat-preview-container" style="display: none; margin-top: 10px; position: relative; max-width: 120px; border-radius: 6px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1);">
                            <button type="button" id="chat-remove-preview" class="remove-preview-btn" style="position: absolute; top: 2px; right: 2px; background: rgba(239,68,68,0.8); border: none; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
                            <img id="chat-image-preview" src="" style="width: 100%; height: auto; display: block;">
                        </div>
                    </div>
                `;

                pane.innerHTML = `
                    <button class="inbox-action-btn inbox-mobile-back" id="inboxMobileBackBtn"><i class="fa-solid fa-arrow-left"></i> Back</button>
                    
                    <div class="inbox-chat-wrapper" style="display: flex; flex-direction: column; height: 100%;">
                        <!-- Chat Header -->
                        <div class="inbox-chat-header" style="padding: 1.2rem; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: center; gap: 15px;">
                            <div>
                                <h4 style="font-weight: 700; color: #fff; font-size: 1.1rem; margin-bottom: 2px;">
                                    🎫 Ticket ${ticketId} — ${htmlEscape(ticket.title_en)}
                                </h4>
                                <p style="font-size: 0.82rem; color: #94a3b8;">
                                    Topic: <span style="color: #c084fc; font-weight:600;">${htmlEscape(ticket.topic)}</span>
                                    ${ticket.username ? ` | User: <strong style="color: #f1f5f9;">${htmlEscape(ticket.username)}</strong>` : ''}
                                </p>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                ${toggleBtnHtml}
                                <span class="inbox-card-category cat-ticket" style="background: ${ticket.status === 'open' ? 'rgba(35,165,90,0.12)' : 'rgba(255,255,255,0.05)'}; color: ${ticket.status === 'open' ? '#23a55a' : '#94a3b8'}; border: 1px solid ${ticket.status === 'open' ? 'rgba(35,165,90,0.2)' : 'rgba(255,255,255,0.1)'}; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
                                    ${ticket.status === 'open' ? 'Open' : 'Closed'}
                                </span>
                            </div>
                        </div>

                        <!-- Chat Messages Area -->
                        <div class="inbox-chat-messages" id="chatMessagesContainer" style="flex-grow: 1; padding: 1.5rem; overflow-y: auto; display: flex; flex-direction: column; gap: 1.2rem;">
                            <div style="text-align: center; color: rgba(255,255,255,0.3); padding: 20px;"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading chat...</div>
                        </div>

                        <!-- Form Input or Banner -->
                        ${inputAreaHtml}
                    </div>
                `;

                loadTicketMessages(ticketId);

                if (!isClosed) {
                    setupChatFormEvents(ticketId);
                }

                const toggleBtn = $('#btnToggleTicket');
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', () => toggleTicketStatus(ticketId));
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

                    // Polling every 4 seconds to update chat in real-time
                    chatPollingInterval = setInterval(async () => {
                        if (currentMessageId !== ticketId) {
                            clearInterval(chatPollingInterval);
                            return;
                        }
                        const pollResponse = await fetch(`/api/tickets.php?ticket_id=${ticketId}`);
                        const pollRes = await pollResponse.json();
                        if (pollRes.ok) {
                            const currentCount = container.querySelectorAll('.chat-msg-row').length;
                            if (pollRes.messages.length !== currentCount) {
                                renderChatMessages(pollRes.messages);
                            }
                        }
                    }, 4000);

                } catch (e) {
                    container.innerHTML = `<div style="padding: 20px; text-align: center; color: #ef4444;">Unable to load chat messages.</div>`;
                }
            }

            function renderChatMessages(messages) {
                const container = $('#chatMessagesContainer');
                if (messages.length === 0) {
                    container.innerHTML = `<div style="text-align: center; color: rgba(255,255,255,0.2); padding: 20px;">No messages in this chat.</div>`;
                    return;
                }

                const loggedUserId = parseInt('<?php echo $userId; ?>', 10);

                container.innerHTML = messages.map(msg => {
                    const isMe = parseInt(msg.sender_id, 10) === loggedUserId;
                    const rowClass = msg.sender_id == loggedUserId ? 'msg-row-me' : 'msg-row-them';
                    const bubbleClass = msg.sender_id == loggedUserId ? 'chat-msg-me' : 'chat-msg-them';
                    
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

                    const pfpUrl = parseInt(msg.sender_id, 10) > 0 ? `/includes/get_pfp.php?id=${msg.sender_id}` : '/img/default_pfp.png';

                    return `
                        <div class="chat-msg-row ${rowClass}">
                            <img src="${pfpUrl}" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(255,255,255,0.1); flex-shrink: 0;" onerror="this.src='/img/default_pfp.png'">
                            <div class="chat-msg-bubble ${bubbleClass}">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; font-size: 0.72rem; color: #94a3b8; margin-bottom: 2px;">
                                    <span><strong style="color: ${isMe ? '#e9d5ff' : '#cbd5e1'};">${htmlEscape(msg.username || 'Guest')}</strong>${roleBadge}</span>
                                    <span>${timeFormatted}</span>
                                </div>
                                <div style="font-size: 0.92rem; color: #f1f5f9; word-break: break-word; white-space: pre-line; line-height: 1.4; text-align: left;">${htmlEscape(msg.message)}</div>
                                ${attachmentMarkup}
                            </div>
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
                                alert('Please select image files only.');
                                this.value = '';
                                return;
                            }
                            if (file.size > 5 * 1024 * 1024) {
                                alert('The image cannot exceed 5MB.');
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
                                alert('Unable to send: ' + res.error);
                            }
                        } catch (err) {
                            alert('Connection error while sending.');
                        } finally {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i>';
                        }
                    });

                    // Send on Enter (Shift+Enter for newline)
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
                        const msg = globalMessages.find(m => m.message_id === messageId);
                        if (msg && parseInt(msg.is_read) === 0) {
                            msg.is_read = 1;

                            const card = $(`.inbox-card[data-id="${messageId}"]`);
                            if (card) card.classList.remove('is-unread');

                            // Recalculate counters and update navbar
                            updateCategoryCounters();
                            updateNavbarBadge(res.unread_count);
                        }
                    }
                } catch (e) {
                    console.error("Error reading message", e);
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
                        const msg = globalMessages.find(m => m.message_id === messageId);
                        if (msg) msg.is_important = res.is_important;
                        renderFilteredList();
                    }
                } catch (e) {
                    alert("Unable to update starred state.");
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
                        alert("Error: " + res.error);
                        loadMessages();
                    }
                } catch (e) {
                    alert("Unable to archive message.");
                    loadMessages();
                }
            }

            async function deleteMessage(messageId) {
                if (!confirm("Are you sure you want to delete this message? This action cannot be undone.")) {
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
                    alert("Unable to delete message.");
                    loadMessages();
                }
            }

            async function claimRewards(messageId) {
                const btn = $('#claimRewardsBtn');
                if (!btn) return;

                btn.disabled = true;
                btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-2"></i>Claiming...`;

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
                        btn.innerHTML = `<i class="fa-solid fa-gift"></i>Claim rewards`;
                        alert(res.error || "Unable to claim rewards.");
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
                    btn.innerHTML = `<i class="fa-solid fa-gift"></i>Claim rewards`;
                    alert("A connection error occurred.");
                }
            }

            // Formatting Helpers
            function formatMessageDate(dateStr) {
                const date = new Date(dateStr.replace(' ', 'T'));
                if (isNaN(date.getTime())) return dateStr;

                const now = new Date();
                const isToday = date.toDateString() === now.toDateString();

                if (isToday) {
                    return date.toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                } else {
                    return date.toLocaleDateString('en-US', {
                        day: '2-digit',
                        month: '2-digit'
                    });
                }
            }

            function formatMessageDateTime(dateStr) {
                const date = new Date(dateStr.replace(' ', 'T'));
                if (isNaN(date.getTime())) return dateStr;
                return date.toLocaleString('en-US', {
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
                return date.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

            // HTML Escaping
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
