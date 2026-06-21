<?php
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
$ogDescription = 'Cripsum™ Inbox. Check your system notifications, changelogs, safety alerts, and claim your rewards.';
$ogUrl = 'https://cripsum.com/en/inbox';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Inbox - Cripsum™</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?php echo htmlspecialchars($ogDescription); ?>">
    
    <link rel="stylesheet" href="/css/inbox.css?v=4.2">
    <link rel="stylesheet" href="/css/style-dark.css?v=5.0">
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
        <!-- Categories Sidebar -->
        <aside class="inbox-panel inbox-sidebar" aria-label="Message categories">
            <h3 style="font-weight: 800; color: #fff; margin-bottom: 15px; font-size: 1.25rem;">
                <i class="fa-solid fa-inbox me-2" style="color: #0f5bff;"></i>Internal Mail
            </h3>
            
            <button type="button" class="inbox-category-btn is-active" data-cat="">
                <span><i class="fa-solid fa-mail-bulk"></i>All messages</span>
                <span class="inbox-category-badge" id="badge-all">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="system">
                <span><i class="fa-solid fa-circle-info"></i>System</span>
                <span class="inbox-category-badge" id="badge-system">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="changelog">
                <span><i class="fa-solid fa-code-compare"></i>Updates</span>
                <span class="inbox-category-badge" id="badge-changelog">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="security">
                <span><i class="fa-solid fa-shield-halved"></i>Security</span>
                <span class="inbox-category-badge" id="badge-security">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="moderation">
                <span><i class="fa-solid fa-gavel"></i>Moderation</span>
                <span class="inbox-category-badge" id="badge-moderation">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="rewards">
                <span><i class="fa-solid fa-gift"></i>Rewards</span>
                <span class="inbox-category-badge" id="badge-rewards">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="special">
                <span><i class="fa-solid fa-calendar-days"></i>Special events</span>
                <span class="inbox-category-badge" id="badge-special">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="staff">
                <span><i class="fa-solid fa-user-shield"></i>Staff</span>
                <span class="inbox-category-badge" id="badge-staff">0</span>
            </button>
        </aside>

        <!-- Main Layout: Messages + Detail -->
        <section class="inbox-panel inbox-main-layout">
            
            <!-- Message List -->
            <div class="inbox-list-pane">
                <div class="inbox-search-bar">
                    <div class="inbox-search-wrapper">
                        <i class="fa-solid fa-search inbox-search-icon"></i>
                        <input type="text" class="inbox-search-input" id="inboxSearchInput" placeholder="Search messages..." autocomplete="off">
                    </div>
                </div>
                
                <div class="inbox-filters">
                    <button type="button" class="inbox-filter-tab is-active" data-status="">Inbox</button>
                    <button type="button" class="inbox-filter-tab" data-status="unread">Unread</button>
                    <button type="button" class="inbox-filter-tab" data-status="important">Starred</button>
                    <button type="button" class="inbox-filter-tab" data-status="archived">Archived</button>
                </div>
                
                <div class="inbox-cards-scroll" id="inboxCardsContainer">
                    <!-- Cards rendered in JS -->
                </div>
            </div>
            
            <!-- Detail View -->
            <div class="inbox-view-pane" id="inboxDetailContainer">
                <div class="inbox-view-empty">
                    <i class="fa-solid fa-envelope-open"></i>
                    <h4>Select a message</h4>
                    <p class="text-muted" style="font-size: 0.9rem;">Click on a message in the list on the left to read it.</p>
                </div>
            </div>
            
        </section>
    </main>

    <!-- Overlay for Reward Claiming Animation -->
    <div class="inbox-reward-modal-backdrop" id="rewardModalBackdrop">
        <div class="inbox-reward-modal">
            <div class="inbox-reward-modal-icon">
                <i class="fa-solid fa-gift"></i>
            </div>
            <div class="inbox-reward-modal-title">Reward Claimed!</div>
            <div class="inbox-reward-modal-sub">You have successfully added the following items to your account:</div>
            
            <div class="inbox-reward-modal-list" id="rewardModalList">
                <!-- Rewards listed in JS -->
            </div>
            
            <button type="button" class="inbox-reward-modal-close" id="rewardModalCloseBtn">Awesome</button>
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
            
            let filterCategory = '';
            let filterStatus = '';
            let filterSearch = '';

            const $ = (sel) => document.querySelector(sel);
            const $$ = (sel) => Array.from(document.querySelectorAll(sel));

            window.addEventListener('DOMContentLoaded', () => {
                loadMessages();
                setupEventListeners();
            });

            function setupEventListeners() {
                $$('.inbox-category-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        $$('.inbox-category-btn').forEach(b => b.classList.remove('is-active'));
                        btn.classList.add('is-active');
                        filterCategory = btn.dataset.cat;
                        currentMessageId = null;
                        renderEmptyDetails();
                        loadMessages();
                    });
                });

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

                let searchTimeout;
                $('#inboxSearchInput').addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        filterSearch = e.target.value.trim();
                        loadMessages();
                    }, 300);
                });

                $('#rewardModalCloseBtn').addEventListener('click', () => {
                    $('#rewardModalBackdrop').classList.remove('is-visible');
                });
            }

            async function loadMessages() {
                const container = $('#inboxCardsContainer');
                container.innerHTML = `<div style="padding: 30px; text-align: center; color: rgba(255,255,255,0.4);"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading...</div>`;
                
                try {
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
                    
                } catch (error) {
                    container.innerHTML = `<div style="padding: 20px; text-align: center; color: #ef4444;">Could not load messages.</div>`;
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
                const categories = ['all', 'system', 'changelog', 'security', 'moderation', 'rewards', 'special', 'staff'];
                categories.forEach(cat => {
                    const el = document.getElementById(`badge-${cat}`);
                    if (el) el.textContent = '0';
                });
                
                let counts = { all: messagesCache.length };
                messagesCache.forEach(m => {
                    const cat = m.category;
                    counts[cat] = (counts[cat] || 0) + 1;
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
                            <div>No messages found</div>
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
                    
                    const title = htmlEscape(msg.title_en);
                    const contentPlain = msg.content_en.replace(/<[^>]+>/g, '');
                    const excerpt = htmlEscape(contentPlain.substring(0, 60) + (contentPlain.length > 60 ? '...' : ''));
                    
                    let catLabel = 'System';
                    switch(msg.category) {
                        case 'system': catLabel = 'System'; break;
                        case 'changelog': catLabel = 'Changelog'; break;
                        case 'security': catLabel = 'Security'; break;
                        case 'moderation': catLabel = 'Moderation'; break;
                        case 'rewards': catLabel = 'Reward'; break;
                        case 'special': catLabel = 'Special'; break;
                        case 'staff': catLabel = 'Staff'; break;
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
                        const messageId = parseInt(card.dataset.id, 10);
                        selectMessage(messageId);
                    });
                });
            }

            function selectMessage(messageId) {
                currentMessageId = messageId;
                
                $$('.inbox-card').forEach(c => {
                    c.classList.remove('is-active');
                    if (parseInt(c.dataset.id, 10) === messageId) {
                        c.classList.add('is-active');
                    }
                });
                
                const msg = messagesCache.find(m => m.message_id === messageId);
                if (msg) {
                    renderMessageDetails(msg);
                    if (parseInt(msg.is_read) === 0) {
                        markAsRead(messageId);
                    }
                }
            }

            function renderEmptyDetails() {
                const pane = $('#inboxDetailContainer');
                pane.innerHTML = `
                    <div class="inbox-view-empty">
                        <i class="fa-solid fa-envelope-open"></i>
                        <h4>Select a message</h4>
                        <p class="text-muted" style="font-size: 0.9rem;">Click on a message in the list on the left to read it.</p>
                    </div>
                `;
                pane.classList.remove('is-open');
            }

            function renderMessageDetails(msg) {
                const pane = $('#inboxDetailContainer');
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
                        
                        switch(rew.reward_type) {
                            case 'points': 
                                icon = '🪙'; 
                                label = `+${parseInt(rew.reward_value) * parseInt(rew.quantity)} Points`; 
                                sub = 'Site currency';
                                break;
                            case 'character': 
                                icon = '👤'; 
                                label = `Character ID: ${rew.reward_value}`; 
                                sub = 'Added to inventory';
                                break;
                            case 'badge': 
                                icon = '🏆'; 
                                label = `Custom Badge ID: ${rew.reward_value}`; 
                                sub = 'Unlocked on profile';
                                break;
                            case 'premium': 
                                icon = '⭐'; 
                                label = 'Premium Membership'; 
                                sub = 'VIP benefits activated';
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
                    
                    const actionButton = isClaimed 
                        ? `<div class="inbox-claimed-badge"><i class="fa-solid fa-circle-check"></i>Rewards Claimed</div>`
                        : `<button type="button" class="inbox-claim-btn" id="claimRewardsBtn" data-id="${msg.message_id}">
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
                            <button type="button" class="inbox-action-btn ${starClass}" id="btnStar" data-id="${msg.message_id}"><i class="fa-solid fa-star"></i> <span>Starred</span></button>
                            <button type="button" class="inbox-action-btn" id="btnArchive" data-id="${msg.message_id}">${archiveText}</button>
                            <button type="button" class="inbox-action-btn inbox-action-btn--danger" id="btnDelete" data-id="${msg.message_id}"><i class="fa-solid fa-trash"></i> <span>Delete</span></button>
                        </div>
                    </div>
                    
                    <h2 class="inbox-view-title">${htmlEscape(msg.title_en)}</h2>
                    <div class="inbox-view-content">${htmlEscape(msg.content_en)}</div>
                    
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

            async function markAsRead(messageId) {
                try {
                    const response = await fetch(API_ENDPOINT, {
                        method: 'POST',
                        body: JSON.stringify({ action: 'read', message_id: messageId })
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
                    console.error("Error reading message", e);
                }
            }

            async function toggleImportant(messageId) {
                try {
                    const response = await fetch(API_ENDPOINT, {
                        method: 'POST',
                        body: JSON.stringify({ action: 'toggle_important', message_id: messageId })
                    });
                    const res = await response.json();
                    if (res.ok) {
                        const msg = messagesCache.find(m => m.message_id === messageId);
                        if (msg) msg.is_important = res.is_important;
                        loadMessages();
                    }
                } catch (e) {
                    alert("Could not update starred state.");
                }
            }

            async function toggleArchive(messageId) {
                currentMessageId = null;
                renderEmptyDetails();
                try {
                    const response = await fetch(API_ENDPOINT, {
                        method: 'POST',
                        body: JSON.stringify({ action: 'toggle_archive', message_id: messageId })
                    });
                    const res = await response.json();
                    if (res.ok) {
                        loadMessages();
                    } else {
                        alert("Error: " + res.error);
                        loadMessages();
                    }
                } catch (e) {
                    alert("Could not archive/restore message.");
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
                        body: JSON.stringify({ action: 'delete', message_id: messageId })
                    });
                    const res = await response.json();
                    if (res.ok) {
                        loadMessages();
                    } else {
                        alert(res.error);
                        loadMessages();
                    }
                } catch (e) {
                    alert("Could not delete message.");
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
                        body: JSON.stringify({ action: 'claim_rewards', message_id: messageId })
                    });
                    const res = await response.json();
                    
                    if (!res.ok) {
                        btn.disabled = false;
                        btn.innerHTML = `<i class="fa-solid fa-gift"></i>Claim rewards`;
                        alert(res.error || "Could not claim rewards.");
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

            function formatMessageDate(dateStr) {
                const date = new Date(dateStr.replace(' ', 'T'));
                if (isNaN(date.getTime())) return dateStr;
                
                const now = new Date();
                const isToday = date.toDateString() === now.toDateString();
                
                if (isToday) {
                    return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                } else {
                    return date.toLocaleDateString('en-US', { day: '2-digit', month: '2-digit' });
                }
            }

            function formatMessageDateTime(dateStr) {
                const date = new Date(dateStr.replace(' ', 'T'));
                if (isNaN(date.getTime())) return dateStr;
                return date.toLocaleString('en-US', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            }

            function htmlEscape(str) {
                return String(str || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

        })();
    </script>
</body>

</html>
