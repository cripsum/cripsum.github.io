<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "You must be logged in to view achievements.";

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

$ogDescription = 'Your achievements on Cripsum™.';
$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/it/achievements'), '#');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Achievements</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?php echo achievement_h($ogDescription); ?>">
    <meta property="og:site_name" content="Cripsum™">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Achievements - Cripsum™">
    <meta property="og:description" content="<?php echo achievement_h($ogDescription); ?>">
    <meta property="og:image" content="https://cripsum.com/img/default-achievement.png">
    <meta property="og:url" content="<?php echo achievement_h($ogUrl); ?>">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="stylesheet" href="/assets/achievements/achievements.css?v=2.3">
    <script src="/assets/achievements/achievements.js?v=2.3" defer></script>
</head>

<body class="ach-page">
    <?php include '../includes/navbar.php'; ?>


    <div class="ach-bg" aria-hidden="true">
        <span class="ach-orb ach-orb--one"></span>
        <span class="ach-orb ach-orb--two"></span>
        <span class="ach-grid-bg"></span>
    </div>

    <main class="ach-shell">
        <section class="ach-hero ach-reveal">
            <div class="ach-hero__copy">
                <span class="ach-pill">Cripsum™</span>
                <h1>Achievements</h1>
                <p>Keep track of your progress and see what you've unlocked.</p>
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
                    <span>Progress</span>
                    <strong id="completionText">Loading...</strong>
                </div>
            </div>
        </section>

        <section class="ach-controls ach-reveal">
            <div class="ach-search">
                <i class="fas fa-search"></i>
                <input type="search" id="achievementSearch" placeholder="Search achievements..." autocomplete="off">
            </div>

            <div class="ach-filter-row" aria-label="Filtri achievement">
                <button type="button" class="ach-chip is-active" data-status-filter="all">all</button>
                <button type="button" class="ach-chip" data-status-filter="unlocked">unlocked</button>
                <button type="button" class="ach-chip" data-status-filter="locked">locked</button>
            </div>

            <div class="ach-custom-select" data-ach-custom-select>
                <select id="achievementSort" class="ach-select ach-native-select" aria-label="Sort achievements" tabindex="-1" aria-hidden="true">
                    <option value="default">Original order</option>
                    <option value="name">Name</option>
                    <option value="points-desc">Most points</option>
                    <option value="points-asc">Least points</option>
                    <option value="unlocked-first">Unlocked first</option>
                    <option value="locked-first">Locked first</option>
                </select>

                <button type="button" class="ach-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                    <span class="ach-select-current">Original order</span>
                    <i class="fas fa-chevron-down"></i>
                </button>

                <div class="ach-select-menu" role="listbox" aria-label="Sort achievements">
                    <button type="button" data-value="default">
                        <strong>Original order</strong>
                    </button>
                    <button type="button" data-value="name">
                        <strong>Name</strong>
                        <span>A-Z</span>
                    </button>
                    <button type="button" data-value="points-desc">
                        <strong>Most points</strong>
                        <span>Pts ↓</span>
                    </button>
                    <button type="button" data-value="points-asc">
                        <strong>Least points</strong>
                        <span>Pts ↑</span>
                    </button>
                    <button type="button" data-value="unlocked-first">
                        <strong>Unlocked first</strong>
                    </button>
                    <button type="button" data-value="locked-first">
                        <strong>Locked first</strong>
                    </button>
                </div>
            </div>
        </section>

        <section class="ach-stats ach-reveal">
            <article>
                <strong id="statTotal">0</strong>
                <span>Total</span>
            </article>
            <article>
                <strong id="statUnlocked">0</strong>
                <span>Unlocked</span>
            </article>
            <article>
                <strong id="statLocked">0</strong>
                <span>Locked</span>
            </article>
            <article>
                <strong id="statPoints">0</strong>
                <span>Points</span>
            </article>
        </section>

        <section id="loadingState" class="ach-loading" aria-live="polite">
            <div class="ach-loader">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <p>Loading achievements...</p>
        </section>

        <section id="achievementError" class="ach-empty" hidden>
            <i class="fas fa-triangle-exclamation"></i>
            <strong>Unable to load achievements</strong>
            <span>Please try again later.</span>
        </section>

        <section id="achievementEmpty" class="ach-empty" hidden>
            <i class="fas fa-magnifying-glass"></i>
            <strong>No achievements found</strong>
            <span>Change search or filter.</span>
        </section>

        <section id="achievementsContainer" class="ach-grid" hidden></section>
    </main>

    <div class="ach-modal" id="achievementModal" hidden>
        <div class="ach-modal__backdrop" data-close-ach-modal></div>
        <article class="ach-modal__panel" role="dialog" aria-modal="true" aria-labelledby="achievementModalTitle">
            <button type="button" class="ach-modal__close" data-close-ach-modal aria-label="Close">
                <i class="fas fa-xmark"></i>
            </button>

            <div id="achievementModalContent"></div>
        </article>
    </div>

    <div id="achievementToast" class="ach-toast" role="status" aria-live="polite"></div>

    <?php include '../includes/scroll_indicator.php'; ?>
    <?php include '../includes/footer-en.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>