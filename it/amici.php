<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Sicurezza: L'utente deve essere loggato per accedere alla pagina amici
if (!isLoggedIn()) {
    header("Location: accedi");
    exit();
}

$myUserId = (int)$_SESSION['user_id'];

// Recuperiamo il conteggio delle richieste di amicizia ricevute pendenti per il badge
$pendingCount = 0;
$stmtCount = $mysqli->prepare("SELECT COUNT(*) FROM friendship_requests WHERE receiver_id = ? AND status = 'pending'");
if ($stmtCount) {
    $stmtCount->bind_param("i", $myUserId);
    $stmtCount->execute();
    $stmtCount->bind_result($pendingCount);
    $stmtCount->fetch();
    $stmtCount->close();
}

// Generiamo il token CSRF per le chiamate API
if (empty($_SESSION['social_csrf'])) {
    $_SESSION['social_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['social_csrf'];
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Relazioni e Amici</title>
    
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/static/static.css?v=1.2-static">
     <link rel="stylesheet" href="/assets/social/social.css?v=1.4">
</head>

<body class="static-page" data-csrf="<?php echo $csrfToken; ?>">
    <?php include '../includes/navbar.php'; ?>

    <!-- Background Orbs -->
    <div class="static-bg" aria-hidden="true">
        <span class="static-orb static-orb--one"></span>
        <span class="static-orb static-orb--two"></span>
        <span class="static-grid-bg"></span>
    </div>

    <main class="static-shell">
        <!-- Hero Section -->
        <section class="static-hero static-reveal">
            <div>
                <span class="static-pill">Social</span>
                <h1>Centro Relazioni</h1>
                <p>Gestisci i tuoi amici, segui nuovi utenti e connettiti con la community di Cripsum™.</p>
            </div>
        </section>

        <!-- Social Layout -->
        <div class="static-card p-4 static-reveal">
            <!-- Tabs di navigazione -->
            <div class="social-tabs">
                <button class="social-tab-btn js-social-tab is-active" data-tab="online" type="button">
                    <i class="fa-solid fa-circle text-success" style="font-size:10px;"></i> Amici Online
                </button>
                <button class="social-tab-btn js-social-tab" data-tab="all" type="button">
                    <i class="fa-solid fa-user-group"></i> Tutti gli Amici
                </button>
                <button class="social-tab-btn js-social-tab" data-tab="requests" type="button">
                    <i class="fa-solid fa-user-clock"></i> Richieste
                    <?php if ($pendingCount > 0): ?>
                        <span class="social-tab-badge"><?php echo $pendingCount; ?></span>
                    <?php endif; ?>
                </button>
                <button class="social-tab-btn js-social-tab" data-tab="suggestions" type="button">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Suggeriti
                </button>
                <button class="social-tab-btn js-social-tab" data-tab="search" type="button">
                    <i class="fa-solid fa-magnifying-glass"></i> Cerca Utenti
                </button>
            </div>

            <!-- Contenitore barra di ricerca (visibile solo nel tab 'search') -->
            <div class="mb-4" id="socialSearchContainer" style="display: none;">
                <div class="position-relative" style="max-width: 400px;">
                    <input type="text" id="socialSearchInput" class="form-control bg-dark text-white border-secondary" placeholder="Digita l'username da cercare..." style="padding-left: 35px; border-radius: 8px;">
                    <i class="fa-solid fa-magnifying-glass position-absolute text-muted" style="left: 12px; top: 50%; transform: translateY(-50%);"></i>
                </div>
            </div>

            <!-- Griglia Utenti (Caricata dinamicamente via JS) -->
            <div class="social-grid" id="socialGrid">
                <!-- Caricamento tramite Skeleton Loader -->
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <!-- Importazione Moduli Social -->
    <script src="/assets/static/static.js" defer></script>
    <script src="/assets/social/social-api.js?v=1.3" defer></script>
    <script src="/assets/social/social-ui.js?v=1.3" defer></script>
    <script src="/assets/social/user-card.js?v=1.5" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
