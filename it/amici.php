<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['error_message'] = "devi essere loggato per accedere alla pagina degli amici.";
    header('Location: home');
    exit();
}

if (!isOwner()) {
    $_SESSION['error_message'] = "mi dispiace, ma la pagina degli amici è in manutenzione. riprova più tardi.";
    header('Location: home');
    exit();
}


$myUserId = (int)$_SESSION['user_id'];

$pendingCount = 0;
$stmtCount = $mysqli->prepare("SELECT COUNT(*) FROM friendship_requests WHERE receiver_id = ? AND status = 'pending'");
if ($stmtCount) {
    $stmtCount->bind_param("i", $myUserId);
    $stmtCount->execute();
    $stmtCount->bind_result($pendingCount);
    $stmtCount->fetch();
    $stmtCount->close();
}

if (empty($_SESSION['social_csrf'])) {
    $_SESSION['social_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['social_csrf'];
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Amici</title>

    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/static/static.css?v=1.2-static">
    <link rel="stylesheet" href="/assets/social/social.css?v=2.1">
</head>

<body class="static-page" data-csrf="<?php echo $csrfToken; ?>">
    <?php include '../includes/navbar.php'; ?>

    <div class="static-bg" aria-hidden="true">
        <span class="static-orb static-orb--one"></span>
        <span class="static-orb static-orb--two"></span>
        <span class="static-grid-bg"></span>
    </div>

    <main class="static-shell">
        <section class="static-hero static-reveal">
            <div>
                <h1>Amici</h1>
                <p>Gestisci i tuoi amici, segui nuovi utenti e connettiti con la community di Cripsum™.</p>
            </div>
        </section>

        <div class="static-card p-4 static-reveal">
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

            <div id="socialSearchContainer" style="display: none;">
                <div class="social-search-input-wrap">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="socialSearchInput" placeholder="Digita l'username da cercare...">
                </div>
            </div>

            <div class="social-grid" id="socialGrid">
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script src="/assets/static/static.js" defer></script>
    <script src="/assets/social/social-api.js?v=1.5" defer></script>
    <script src="/assets/social/social-ui.js?v=1.7" defer></script>
    <script src="/assets/social/user-card.js?v=2.2" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>