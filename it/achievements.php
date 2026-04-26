<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Per accedere agli achievement devi essere loggato";

    header('Location: accedi');
    exit();
}

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

$userId = (int)$_SESSION['user_id'];

checkBan($mysqli);

function achievement_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$ogDescription = 'I tuoi achievement su Cripsum™.';
$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/it/achievements'), '#');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Achievement</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?php echo achievement_h($ogDescription); ?>">
    <meta property="og:site_name" content="Cripsum™">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Achievement - Cripsum™">
    <meta property="og:description" content="<?php echo achievement_h($ogDescription); ?>">
    <meta property="og:image" content="https://cripsum.com/img/default-achievement.png">
    <meta property="og:url" content="<?php echo achievement_h($ogUrl); ?>">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="stylesheet" href="/assets/achievements/achievements.css?v=2.0-ui">
    <script src="/assets/achievements/achievements.js?v=2.0-ui" defer></script>
</head>

<body class="ach-page">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="ach-bg" aria-hidden="true">
        <span class="ach-orb ach-orb--one"></span>
        <span class="ach-orb ach-orb--two"></span>
        <span class="ach-grid-bg"></span>
    </div>

    <main class="ach-shell">
        <section class="ach-hero ach-reveal">
            <div class="ach-hero__copy">
                <span class="ach-pill">Cripsum™</span>
                <h1>Achievement</h1>
                <p>Guarda cosa hai sbloccato e cosa ti manca.</p>
            </div>

            <div class="ach-summary" aria-label="Completamento achievement">
                <div class="ach-ring">
                    <svg class="ach-ring__svg" width="132" height="132" viewBox="0 0 132 132" aria-hidden="true">
                        <circle class="ach-ring__bg" cx="66" cy="66" r="54"></circle>
                        <circle class="ach-ring__fill" cx="66" cy="66" r="54" id="completionCircle"></circle>
                    </svg>
                    <strong id="completionPercentage">0%</strong>
                </div>

                <div class="ach-summary__text">
                    <span>Completamento</span>
                    <strong id="completionText">Caricamento...</strong>
                </div>
            </div>
        </section>

        <section class="ach-controls ach-reveal">
            <div class="ach-search">
                <i class="fas fa-search"></i>
                <input type="search" id="achievementSearch" placeholder="Cerca achievement..." autocomplete="off">
            </div>

            <div class="ach-filter-row" aria-label="Filtri achievement">
                <button type="button" class="ach-chip is-active" data-status-filter="all">Tutti</button>
                <button type="button" class="ach-chip" data-status-filter="unlocked">Sbloccati</button>
                <button type="button" class="ach-chip" data-status-filter="locked">Bloccati</button>
            </div>

            <select id="achievementSort" class="ach-select" aria-label="Ordina achievement">
                <option value="default">Ordine originale</option>
                <option value="name">Nome</option>
                <option value="points-desc">Più punti</option>
                <option value="points-asc">Meno punti</option>
                <option value="unlocked-first">Sbloccati prima</option>
                <option value="locked-first">Bloccati prima</option>
            </select>
        </section>

        <section class="ach-stats ach-reveal">
            <article>
                <strong id="statTotal">0</strong>
                <span>Totali</span>
            </article>
            <article>
                <strong id="statUnlocked">0</strong>
                <span>Sbloccati</span>
            </article>
            <article>
                <strong id="statLocked">0</strong>
                <span>Bloccati</span>
            </article>
            <article>
                <strong id="statPoints">0</strong>
                <span>Punti</span>
            </article>
        </section>

        <section id="loadingState" class="ach-loading" aria-live="polite">
            <div class="ach-loader">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <p>Caricamento achievement...</p>
        </section>

        <section id="achievementError" class="ach-empty" hidden>
            <i class="fas fa-triangle-exclamation"></i>
            <strong>Non riesco a caricare gli achievement</strong>
            <span>Riprova tra poco.</span>
        </section>

        <section id="achievementEmpty" class="ach-empty" hidden>
            <i class="fas fa-magnifying-glass"></i>
            <strong>Nessun achievement trovato</strong>
            <span>Cambia ricerca o filtro.</span>
        </section>

        <section id="achievementsContainer" class="ach-grid" hidden></section>
    </main>

    <div class="ach-modal" id="achievementModal" hidden>
        <div class="ach-modal__backdrop" data-close-ach-modal></div>
        <article class="ach-modal__panel" role="dialog" aria-modal="true" aria-labelledby="achievementModalTitle">
            <button type="button" class="ach-modal__close" data-close-ach-modal aria-label="Chiudi">
                <i class="fas fa-xmark"></i>
            </button>

            <div id="achievementModalContent"></div>
        </article>
    </div>

    <div id="achievementToast" class="ach-toast" role="status" aria-live="polite"></div>

    <?php include '../includes/scroll_indicator.php'; ?>
    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
