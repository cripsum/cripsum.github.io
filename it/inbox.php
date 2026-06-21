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
$ogDescription = 'Centro Messaggi di Cripsum™. Controlla le tue notifiche, aggiornamenti, avvisi e riscatta i tuoi premi.';
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
        <!-- Sidebar Categorie -->
        <aside class="inbox-panel inbox-sidebar" aria-label="Categorie messaggi">
            <h3 style="font-weight: 800; color: #fff; margin-bottom: 15px; font-size: 1.25rem;">
                <i class="fa-solid fa-inbox me-2" style="color: #0f5bff;"></i>Posta interna
            </h3>

            <button type="button" class="inbox-category-btn is-active" data-cat="">
                <span><i class="fa-solid fa-mail-bulk"></i>Tutti i messaggi</span>
                <span class="inbox-category-badge" id="badge-all">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="system">
                <span><i class="fa-solid fa-circle-info"></i>Sistema</span>
                <span class="inbox-category-badge" id="badge-system">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="changelog">
                <span><i class="fa-solid fa-code-compare"></i>Aggiornamenti</span>
                <span class="inbox-category-badge" id="badge-changelog">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="security">
                <span><i class="fa-solid fa-shield-halved"></i>Sicurezza</span>
                <span class="inbox-category-badge" id="badge-security">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="moderation">
                <span><i class="fa-solid fa-gavel"></i>Moderazione</span>
                <span class="inbox-category-badge" id="badge-moderation">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="rewards">
                <span><i class="fa-solid fa-gift"></i>Ricompense</span>
                <span class="inbox-category-badge" id="badge-rewards">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="special">
                <span><i class="fa-solid fa-calendar-days"></i>Eventi speciali</span>
                <span class="inbox-category-badge" id="badge-special">0</span>
            </button>
            <button type="button" class="inbox-category-btn" data-cat="staff">
                <span><i class="fa-solid fa-user-shield"></i>Staff</span>
                <span class="inbox-category-badge" id="badge-staff">0</span>
            </button>
        </aside>

        <!-- Main Layout: Messaggi + Dettaglio -->
        <section class="inbox-panel inbox-main-layout">

            <!-- Lista Messaggi -->
            <div class="inbox-list-pane">
                <div class="inbox-search-bar">
                    <div class="inbox-search-wrapper">
                        <i class="fa-solid fa-search inbox-search-icon"></i>
                        <input type="text" class="inbox-search-input" id="inboxSearchInput" placeholder="Cerca nei messaggi..." autocomplete="off">
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
                    <h4>Seleziona un messaggio</h4>
                    <p class="text-muted" style="font-size: 0.9rem;">Clicca su un messaggio nella lista a sinistra per leggerlo.</p>
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

                    // Aggiorna contatore Navbar se presente
                    updateNavbarBadge(res.unread_count);

                    // Aggiorna contatori categorie
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
                // Resetta contatori a 0
                const categories = ['all', 'system', 'changelog', 'security', 'moderation', 'rewards', 'special', 'staff'];
                categories.forEach(cat => {
                    const el = document.getElementById(`badge-${cat}`);
                    if (el) el.textContent = '0';
                });

                // Popola i badge in base alla cache di tutti i messaggi della vista corrente
                // (Nota: per un conteggio globale accurato sarebbe meglio caricarlo separatamente, 
                // ma contare gli elementi filtrati o mostrare il conteggio dei non letti va benissimo)
                let counts = {
                    all: messagesCache.length
                };
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
                            <div>Nessun messaggio trovato</div>
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

                    // Titolo ed estratto
                    const title = htmlEscape(msg.title_it);
                    const contentPlain = msg.content_it.replace(/<[^>]+>/g, '');
                    const excerpt = htmlEscape(contentPlain.substring(0, 60) + (contentPlain.length > 60 ? '...' : ''));

                    let catLabel = 'Sistema';
                    switch (msg.category) {
                        case 'system':
                            catLabel = 'Sistema';
                            break;
                        case 'changelog':
                            catLabel = 'Novità';
                            break;
                        case 'security':
                            catLabel = 'Sicurezza';
                            break;
                        case 'moderation':
                            catLabel = 'Moderazione';
                            break;
                        case 'rewards':
                            catLabel = 'Premio';
                            break;
                        case 'special':
                            catLabel = 'Speciale';
                            break;
                        case 'staff':
                            catLabel = 'Staff';
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

                // Add click events to cards
                $$('.inbox-card').forEach(card => {
                    card.addEventListener('click', () => {
                        const messageId = parseInt(card.dataset.id, 10);
                        selectMessage(messageId);
                    });
                });
            }

            function selectMessage(messageId) {
                currentMessageId = messageId;

                // Aggiorna classi active
                $$('.inbox-card').forEach(c => {
                    c.classList.remove('is-active');
                    if (parseInt(c.dataset.id, 10) === messageId) {
                        c.classList.add('is-active');
                    }
                });

                const msg = messagesCache.find(m => m.message_id === messageId);
                if (msg) {
                    renderMessageDetails(msg);

                    // Se il messaggio era non letto, lo segna come letto sul server
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
                        <h4>Seleziona un messaggio</h4>
                        <p class="text-muted" style="font-size: 0.9rem;">Clicca su un messaggio nella lista a sinistra per leggerlo.</p>
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
                const archiveText = isArchived ? '<i class="fa-solid fa-box-open"></i> <span>Ripristina</span>' : '<i class="fa-solid fa-archive"></i> <span>Archivia</span>';

                // Rewards Markup
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
                                label = `+${parseInt(rew.reward_value) * parseInt(rew.quantity)} Punti`;
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
                    <!-- Pulsante indietro mobile -->
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
                    <div class="inbox-view-content">${htmlEscape(msg.content_it)}</div>
                    
                    ${rewardsHtml}
                `;

                // Add event listeners on details actions
                $('#btnStar').addEventListener('click', () => toggleImportant(msg.message_id));
                $('#btnArchive').addEventListener('click', () => toggleArchive(msg.message_id));
                $('#btnDelete').addEventListener('click', () => deleteMessage(msg.message_id));

                const claimBtn = $('#claimRewardsBtn');
                if (claimBtn) {
                    claimBtn.addEventListener('click', () => claimRewards(msg.message_id));
                }

                // Mobile back button action
                const backBtn = $('#inboxMobileBackBtn');
                if (backBtn) {
                    backBtn.addEventListener('click', () => {
                        pane.classList.remove('is-open');
                    });
                }

                // Su mobile, apri il pane dettagli
                if (window.innerWidth <= 768) {
                    pane.classList.add('is-open');
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
                        // Aggiorna localmente lo stato del messaggio per evitare di ricaricare tutto
                        const msg = messagesCache.find(m => m.message_id === messageId);
                        if (msg && parseInt(msg.is_read) === 0) {
                            msg.is_read = 1;

                            // Aggiorna classe card
                            const card = $(`.inbox-card[data-id="${messageId}"]`);
                            if (card) card.classList.remove('is-unread');

                            // Ricalcola contatori
                            // Aggiorna il contatore della navbar riducendo di 1
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

                        // Aggiorna la vista
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
                    alert("Impossibile archiviare/ripristinare il messaggio.");
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

                    // Mostra la modale di riscatto avvenuto con successo
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

                    // Ricarica la lista dei messaggi per aggiornare lo stato di riscattato
                    loadMessages();

                } catch (e) {
                    btn.disabled = false;
                    btn.innerHTML = `<i class="fa-solid fa-gift"></i>Riscatta premi`;
                    alert("Si è verificato un errore di connessione.");
                }
            }

            // Helpers di formattazione data
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