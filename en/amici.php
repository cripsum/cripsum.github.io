<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Security: User must be logged in to access the friends page
if (!isLoggedIn()) {
    header("Location: login");
    exit();
}

$myUserId = (int)$_SESSION['user_id'];

// Retrieve the count of pending incoming friend requests for the badge
$pendingCount = 0;
$stmtCount = $mysqli->prepare("SELECT COUNT(*) FROM friendship_requests WHERE receiver_id = ? AND status = 'pending'");
if ($stmtCount) {
    $stmtCount->bind_param("i", $myUserId);
    $stmtCount->execute();
    $stmtCount->bind_result($pendingCount);
    $stmtCount->fetch();
    $stmtCount->close();
}

// Generate the CSRF token for the API calls
if (empty($_SESSION['social_csrf'])) {
    $_SESSION['social_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['social_csrf'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Relationships & Friends</title>
    
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link class="static-css" rel="stylesheet" href="/assets/static/static.css?v=1.2-static">
     <link rel="stylesheet" href="/assets/social/social.css?v=2.1">
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
                <h1>Friends</h1>
                <p>Manage your friends, follow new users and connect with the Cripsum™ community.</p>
            </div>
        </section>

        <!-- Social Layout -->
        <div class="static-card p-4 static-reveal">
            <!-- Navigation Tabs -->
            <div class="social-tabs">
                <button class="social-tab-btn js-social-tab is-active" data-tab="online" type="button">
                    <i class="fa-solid fa-circle text-success" style="font-size:10px;"></i> Online Friends
                </button>
                <button class="social-tab-btn js-social-tab" data-tab="all" type="button">
                    <i class="fa-solid fa-user-group"></i> All Friends
                </button>
                <button class="social-tab-btn js-social-tab" data-tab="requests" type="button">
                    <i class="fa-solid fa-user-clock"></i> Requests
                    <?php if ($pendingCount > 0): ?>
                        <span class="social-tab-badge"><?php echo $pendingCount; ?></span>
                    <?php endif; ?>
                </button>
                <button class="social-tab-btn js-social-tab" data-tab="suggestions" type="button">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Suggestions
                </button>
                <button class="social-tab-btn js-social-tab" data-tab="search" type="button">
                    <i class="fa-solid fa-magnifying-glass"></i> Search Users
                </button>
            </div>

            <!-- Contenitore barra di ricerca (visibile solo nel tab 'search') -->
            <div id="socialSearchContainer" style="display: none;">
                <div class="social-search-input-wrap">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="socialSearchInput" placeholder="Type username to search...">
                </div>
            </div>

            <!-- Users Grid (Loaded dynamically via JS) -->
            <div class="social-grid" id="socialGrid">
                <!-- Loaded via Skeleton Loader -->
            </div>
        </div>
    </main>

    <?php include '../includes/footer-en.php'; ?>

    <!-- Social Modules Import -->
    <script src="/assets/static/static.js" defer></script>
    <script src="/assets/social/social-api.js?v=1.5" defer></script>
    <script src="/assets/social/social-ui.js?v=1.6" defer></script>
    <script src="/assets/social/user-card.js?v=2.1" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
