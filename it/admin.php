<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin/admin_helpers.php';

mysqli_report(MYSQLI_REPORT_OFF);
if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

$currentAdmin = admin_require_access($mysqli, false);
$csrfToken = admin_csrf_token();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include __DIR__ . '/../includes/head-import.php'; ?>
    <title>Cripsum™ - Admin V2</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/admin-v2/admin.css?v=2.1-content-tabs">
</head>
<body class="admin-v2-body" data-csrf="<?php echo admin_h($csrfToken); ?>" data-admin-id="<?php echo (int)$currentAdmin['id']; ?>" data-admin-role="<?php echo admin_h($currentAdmin['ruolo']); ?>">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/impostazioni.php'; ?>

    <div class="admin-bg" aria-hidden="true">
        <span class="admin-orb admin-orb--one"></span>
        <span class="admin-orb admin-orb--two"></span>
        <span class="admin-grid"></span>
    </div>

    <main class="admin-shell">
        <aside class="admin-sidebar" aria-label="Navigazione admin">
            <div class="admin-brand-card">
                <span class="admin-brand-mark"><i class="fas fa-shield-halved"></i></span>
                <div>
                    <strong>Admin Panel</strong>
                    <small><?php echo admin_h($currentAdmin['username']); ?> · <?php echo admin_h($currentAdmin['ruolo']); ?></small>
                </div>
            </div>

            <nav class="admin-nav" data-admin-nav>
                <button type="button" class="is-active" data-section="dashboard"><i class="fas fa-chart-line"></i><span>Dashboard</span></button>
                <button type="button" data-section="users"><i class="fas fa-users"></i><span>Utenti</span></button>
                <button type="button" data-section="characters"><i class="fas fa-box-open"></i><span>Personaggi</span></button>
                <button type="button" data-section="achievements"><i class="fas fa-trophy"></i><span>Achievement</span></button>
                <button type="button" data-section="shitposts"><i class="fas fa-image"></i><span>Shitpost</span></button>
                <button type="button" data-section="toprimasti"><i class="fas fa-ranking-star"></i><span>Top Rimasti</span></button>
                <button type="button" data-section="logs"><i class="fas fa-clock-rotate-left"></i><span>Log</span></button>
            </nav>

            <a class="admin-side-link" href="/api/admin/export_users_csv.php?csrf_token=<?php echo urlencode($csrfToken); ?>">
                <i class="fas fa-file-csv"></i>
                <span>Esporta utenti CSV</span>
            </a>
        </aside>

        <section class="admin-main">
            <header class="admin-header">
                <div>
                    <p class="admin-eyebrow">Cripsum Control</p>
                    <h1>Pannello Admin</h1>
                </div>
                <div class="admin-header-actions">
                    <label class="admin-global-search">
                        <i class="fas fa-search"></i>
                        <input type="search" id="adminGlobalSearch" placeholder="Cerca utenti, personaggi, achievement">
                    </label>
                    <button type="button" class="admin-icon-btn" id="adminRefreshBtn" title="Aggiorna"><i class="fas fa-rotate"></i></button>
                </div>
            </header>

            <section class="admin-section is-active" id="section-dashboard" data-section-panel="dashboard">
                <div class="admin-stats-grid" id="adminStatsGrid">
                    <article class="admin-stat-card is-loading"></article>
                    <article class="admin-stat-card is-loading"></article>
                    <article class="admin-stat-card is-loading"></article>
                    <article class="admin-stat-card is-loading"></article>
                </div>

                <div class="admin-grid-two">
                    <article class="admin-panel">
                        <div class="admin-panel-head"><div><strong>Ultimi utenti</strong><small>Registrazioni recenti</small></div></div>
                        <div id="latestUsersBox" class="admin-stack"></div>
                    </article>
                    <article class="admin-panel">
                        <div class="admin-panel-head"><div><strong>Ultime azioni</strong><small>Log admin recenti</small></div></div>
                        <div id="dashboardLogsBox" class="admin-stack"></div>
                    </article>
                </div>
            </section>

            <section class="admin-section" id="section-users" data-section-panel="users">
                <div class="admin-toolbar">
                    <div>
                        <strong>Utenti</strong>
                        <small>Gestisci account, ban, ruoli, inventario e achievement.</small>
                    </div>
                    <div class="admin-toolbar-actions">
                        <select id="usersStatusFilter" class="admin-input">
                            <option value="all">Tutti</option>
                            <option value="active">Attivi</option>
                            <option value="banned">Bannati</option>
                        </select>
                        <select id="usersRoleFilter" class="admin-input">
                            <option value="all">Tutti i ruoli</option>
                            <option value="utente">Utenti</option>
                            <option value="admin">Admin</option>
                            <option value="owner">Owner</option>
                        </select>
                    </div>
                </div>
                <div id="usersTable" class="admin-table-card"></div>
                <div id="usersPagination" class="admin-pagination"></div>
            </section>

            <section class="admin-section" id="section-characters" data-section-panel="characters">
                <div class="admin-toolbar">
                    <div><strong>Personaggi</strong><small>Crea, modifica o rimuovi personaggi dal database.</small></div>
                    <button type="button" class="admin-btn admin-btn--primary" id="createCharacterBtn"><i class="fas fa-plus"></i> Nuovo</button>
                </div>
                <div id="charactersTable" class="admin-table-card"></div>
                <div id="charactersPagination" class="admin-pagination"></div>
            </section>

            <section class="admin-section" id="section-achievements" data-section-panel="achievements">
                <div class="admin-toolbar">
                    <div><strong>Achievement</strong><small>Gestisci badge e obiettivi sbloccabili.</small></div>
                    <button type="button" class="admin-btn admin-btn--primary" id="createAchievementBtn"><i class="fas fa-plus"></i> Nuovo</button>
                </div>
                <div id="achievementsTable" class="admin-table-card"></div>
                <div id="achievementsPagination" class="admin-pagination"></div>
            </section>

            <section class="admin-section" id="section-shitposts" data-section-panel="shitposts">
                <div class="admin-toolbar">
                    <div>
                        <strong>Shitpost</strong>
                        <small>Modera contenuti, testo e approvazione.</small>
                    </div>
                    <div class="admin-toolbar-actions">
                        <select id="shitpostsStatusFilter" class="admin-input">
                            <option value="all">Tutti</option>
                            <option value="approved">Approvati</option>
                            <option value="pending">In attesa</option>
                        </select>
                    </div>
                </div>
                <div id="shitpostsTable" class="admin-table-card"></div>
                <div id="shitpostsPagination" class="admin-pagination"></div>
            </section>

            <section class="admin-section" id="section-toprimasti" data-section-panel="toprimasti">
                <div class="admin-toolbar">
                    <div>
                        <strong>Top Rimasti</strong>
                        <small>Modera post, motivazioni e voti.</small>
                    </div>
                    <div class="admin-toolbar-actions">
                        <select id="toprimastiStatusFilter" class="admin-input">
                            <option value="all">Tutti</option>
                            <option value="approved">Approvati</option>
                            <option value="pending">In attesa</option>
                        </select>
                    </div>
                </div>
                <div id="toprimastiTable" class="admin-table-card"></div>
                <div id="toprimastiPagination" class="admin-pagination"></div>
            </section>

            <section class="admin-section" id="section-logs" data-section-panel="logs">
                <div class="admin-toolbar"><div><strong>Log admin</strong><small>Azioni recenti del pannello.</small></div></div>
                <div id="logsTable" class="admin-table-card"></div>
            </section>
        </section>
    </main>

    <div class="modal fade admin-modal" id="adminModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="adminModalTitle">Admin</h5>
                        <small id="adminModalSubtitle"></small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body" id="adminModalBody"></div>
                <div class="modal-footer" id="adminModalFooter"></div>
            </div>
        </div>
    </div>

    <div class="modal fade admin-modal" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmTitle">Conferma</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body" id="confirmBody"></div>
                <div class="modal-footer">
                    <button type="button" class="admin-btn" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="admin-btn admin-btn--danger" id="confirmActionBtn">Conferma</button>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-toast" id="adminToast" role="status" aria-live="polite"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="/assets/admin-v2/admin.js?v=2.1-content-tabs"></script>
</body>
</html>
