<?php

/**
 * Cripsum™ — Pagina Missioni
 * Accessibile su /it/missions e /en/missions
 */

require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = 'Per accedere alle missioni devi essere loggato.';
    header('Location: accedi');
    exit();
}

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

$userId = (int)$_SESSION['user_id'];
checkBan($mysqli);

// ── Lingua ────────────────────────────────────────────────────
$lang      = str_contains((string)($_SERVER['REQUEST_URI'] ?? ''), '/en/') ? 'en' : 'it';
$isEn      = $lang === 'en';

// ── Meta ──────────────────────────────────────────────────────
$pageTitle  = $isEn ? 'Missions'        : 'Missioni';
$heroSub    = $isEn ? 'Daily & Weekly'  : 'Giornaliere & Settimanali';
$heroDesc   = $isEn
    ? 'Complete missions, earn points, unlock rewards. New challenges every day.'
    : 'Completa le missioni, guadagna punti, sblocca ricompense. Nuove sfide ogni giorno.';

$ogDescription = $isEn
    ? 'Your daily and weekly missions on Cripsum™.'
    : 'Le tue missioni giornaliere e settimanali su Cripsum™.';

$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/it/missions'), '#');

function msn_h(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ — <?php echo msn_h($pageTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?php echo msn_h($ogDescription); ?>">
    <meta property="og:site_name" content="Cripsum™">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo msn_h($pageTitle); ?> — Cripsum™">
    <meta property="og:description" content="<?php echo msn_h($ogDescription); ?>">
    <meta property="og:url" content="<?php echo msn_h($ogUrl); ?>">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="stylesheet" href="/assets/missions/missions.css?v=1.2">
    <script>
        window.CRIPSUM_LANG = '<?php echo $lang; ?>';
    </script>
    <script src="/assets/missions/missions.js?v=1.3" defer></script>
</head>

<body class="msn-page">

    <?php include '../includes/navbar.php'; ?>

    <!-- ── Sfondo ───────────────────────────────────────────── -->
    <div class="msn-bg" aria-hidden="true">
        <span class="msn-orb msn-orb--one"></span>
        <span class="msn-orb msn-orb--two"></span>
        <span class="msn-grid-bg"></span>
    </div>

    <main class="msn-shell">

        <!-- ════════════════════════════════════════════════════
             HERO
        ════════════════════════════════════════════════════ -->
        <section class="msn-hero msn-glass msn-reveal">
            <div class="msn-hero__copy">
                <span class="msn-pill">Cripsum™</span>
                <h1><?php echo msn_h($pageTitle); ?></h1>
                <p><?php echo msn_h($heroDesc); ?></p>
            </div>

            <div class="msn-hero__stats">
                <div class="msn-hero__stat">
                    <strong id="msnStatDaily">—</strong>
                    <span><?php echo $isEn ? 'Daily' : 'Daily completate'; ?></span>
                </div>
                <div class="msn-hero__stat">
                    <strong id="msnStatWeekly">—</strong>
                    <span><?php echo $isEn ? 'Weekly' : 'Weekly completate'; ?></span>
                </div>
                <div class="msn-hero__stat">
                    <strong id="msnStatPoints">0</strong>
                    <span><?php echo $isEn ? 'Points earned' : 'Punti earned'; ?></span>
                </div>
            </div>
        </section>

        <!-- ════════════════════════════════════════════════════
             TIMER RESET
        ════════════════════════════════════════════════════ -->
        <div class="msn-timers msn-reveal">

            <div class="msn-timer msn-timer--daily msn-glass">
                <div class="msn-timer__icon">
                    <i class="fa-solid fa-sun"></i>
                </div>
                <div class="msn-timer__body">
                    <div class="msn-timer__label">
                        <?php echo $isEn ? 'Daily reset in' : 'Nuove daily tra'; ?>
                    </div>
                    <div class="msn-timer__countdown" id="msnDailyCountdown">
                        --:--:--
                    </div>
                </div>
            </div>

            <div class="msn-timer msn-timer--weekly msn-glass">
                <div class="msn-timer__icon">
                    <i class="fa-solid fa-calendar-week"></i>
                </div>
                <div class="msn-timer__body">
                    <div class="msn-timer__label">
                        <?php echo $isEn ? 'Weekly reset in' : 'Nuove weekly tra'; ?>
                    </div>
                    <div class="msn-timer__countdown" id="msnWeeklyCountdown">
                        --:--:--
                    </div>
                </div>
            </div>

        </div>

        <!-- ════════════════════════════════════════════════════
             TAB SWITCHER
        ════════════════════════════════════════════════════ -->
        <div class="msn-tabs msn-reveal" role="tablist" aria-label="<?php echo $isEn ? 'Mission type' : 'Tipo missione'; ?>">

            <button
                type="button"
                class="msn-tab is-active"
                data-msn-tab="daily"
                role="tab"
                aria-selected="true"
                aria-controls="msnDailyPanel">
                <i class="fa-solid fa-sun"></i>
                <?php echo $isEn ? 'Daily' : 'Giornaliere'; ?>
                <span class="msn-tab__count" id="msnTabDailyCount">5</span>
            </button>

            <button
                type="button"
                class="msn-tab"
                data-msn-tab="weekly"
                role="tab"
                aria-selected="false"
                aria-controls="msnWeeklyPanel">
                <i class="fa-solid fa-calendar-week"></i>
                <?php echo $isEn ? 'Weekly' : 'Settimanali'; ?>
                <span class="msn-tab__count" id="msnTabWeeklyCount">3</span>
            </button>

        </div>

        <!-- ════════════════════════════════════════════════════
             CONTENUTO (caricato via JS)
        ════════════════════════════════════════════════════ -->

        <!-- Loading state -->
        <div id="msnLoading" class="msn-loading msn-glass" aria-live="polite">
            <div class="msn-loader">
                <span></span><span></span><span></span>
            </div>
            <p><?php echo $isEn ? 'Loading missions...' : 'Caricamento missioni...'; ?></p>
        </div>

        <!-- Error state -->
        <div id="msnError" class="msn-empty msn-glass" hidden>
            <i class="fa-solid fa-triangle-exclamation"></i>
            <strong><?php echo $isEn ? 'Cannot load missions' : 'Impossibile caricare le missioni'; ?></strong>
            <span><?php echo $isEn ? 'Try again in a moment.' : 'Riprova tra poco.'; ?></span>
        </div>

        <!-- Content -->
        <div id="msnContent" hidden>

            <!-- DAILY PANEL -->
            <div
                id="msnDailyPanel"
                class="msn-panel is-active"
                data-msn-panel="daily"
                role="tabpanel"
                aria-labelledby="msnTabDaily">
                <div class="msn-panel__header">
                    <h2 class="msn-panel__title">
                        <i class="fa-solid fa-sun"></i>
                        <?php echo $isEn ? 'Daily Missions' : 'Missioni Giornaliere'; ?>
                    </h2>
                    <span class="msn-panel__progress" id="msnDailyProgress"></span>
                </div>

                <div class="msn-grid" id="msnDailyGrid" aria-label="<?php echo $isEn ? 'Daily missions' : 'Missioni giornaliere'; ?>">
                    <!-- Skeleton while loading -->
                    <?php for ($i = 0; $i < 5; $i++): ?>
                        <div class="msn-skeleton-card msn-skeleton"></div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- WEEKLY PANEL -->
            <div
                id="msnWeeklyPanel"
                class="msn-panel"
                data-msn-panel="weekly"
                role="tabpanel"
                aria-labelledby="msnTabWeekly">
                <div class="msn-panel__header">
                    <h2 class="msn-panel__title">
                        <i class="fa-solid fa-calendar-week"></i>
                        <?php echo $isEn ? 'Weekly Missions' : 'Missioni Settimanali'; ?>
                    </h2>
                    <span class="msn-panel__progress" id="msnWeeklyProgress"></span>
                </div>

                <div class="msn-grid msn-grid--weekly" id="msnWeeklyGrid" aria-label="<?php echo $isEn ? 'Weekly missions' : 'Missioni settimanali'; ?>">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="msn-skeleton-card msn-skeleton"></div>
                    <?php endfor; ?>
                </div>
            </div>

        </div>
        <!-- /msnContent -->

    </main>

    <!-- ── Toast ─────────────────────────────────────────────── -->
    <div id="msnToast" class="msn-toast" role="status" aria-live="polite" aria-atomic="true">
        <i class="fa-solid fa-circle-check msn-toast__icon" id="msnToastIcon"></i>
        <div>
            <div id="msnToastMsg"></div>
            <div class="msn-toast__pts" id="msnToastPts"></div>
        </div>
    </div>

    <?php include '../includes/scroll_indicator.php'; ?>
    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>