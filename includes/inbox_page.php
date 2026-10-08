<?php

/**
 * Posta — pagina condivisa fra /it/inbox e /en/inbox.
 *
 * I due file dentro it/ e en/ sono wrapper di tre righe che passano la
 * lingua. La pagina è un guscio con gli stessi componenti delle chat
 * (assets/chat/kit.*): messaggi e ticket li carica assets/inbox/inbox.js da
 * api/inbox.php e api/tickets.php.
 *
 * Link diretti: ?m=<id messaggio>, ?section=tickets, ?section=tickets&t=TK-…
 *
 * Si aspetta $lang già impostata da chi include.
 */

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/inbox_strings.php';

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

if (function_exists('checkBan')) {
    checkBan($mysqli);
}

requireLogin();

$lang        = (isset($lang) && $lang === 'en') ? 'en' : 'it';
$userId      = (int)$_SESSION['user_id'];
$currentUser = getCurrentUser($mysqli);
$isStaff     = in_array($currentUser['ruolo'] ?? '', ['admin', 'owner'], true);

$T = inbox_strings($lang);

$ibE = static fn(?string $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

$config = [
    'lang' => $lang,
    'user' => [
        'id' => $userId,
        'username' => (string)($currentUser['username'] ?? ''),
        'staff' => $isStaff,
    ],
    'supportUrl' => "/$lang/supporto",
    'strings' => $T,
];

// La versione la dà la data del file: cambiare il CSS o il JS senza
// ricordarsi di alzare ?v= vuol dire che nessuno vede la modifica.
$kitCssVer  = @filemtime(__DIR__ . '/../assets/chat/kit.css') ?: 1;
$cssVer     = @filemtime(__DIR__ . '/../css/inbox.css') ?: 1;
$kitJsVer   = @filemtime(__DIR__ . '/../assets/chat/kit.js') ?: 1;
$kitChatVer = @filemtime(__DIR__ . '/../assets/chat/kit-chat.js') ?: 1;
$jsVer      = @filemtime(__DIR__ . '/../assets/inbox/inbox.js') ?: 1;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>"<?= cripsum_theme_html_attr() ?>>

<head>
    <?php $ogDescription = $T['og_description']; ?>
    <?php include __DIR__ . '/head-import.php'; ?>
    <title><?= $ibE($T['page_title']) ?> - Cripsum&trade;</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <meta property="og:url" content="https://cripsum.com/<?= $lang ?>/inbox">
    <link rel="stylesheet" href="/assets/chat/kit.css?v=<?= $kitCssVer ?>">
    <link rel="stylesheet" href="/css/inbox.css?v=<?= $cssVer ?>">
    <?php cripsum_theme_head('chat'); ?>
</head>

<body class="ck-page ib-page" data-user-id="<?= $userId ?>" data-logged-in="1" data-csrf="<?= $ibE(csrf_token()) ?>">
    <?php include __DIR__ . '/navbar.php'; ?>

    <main class="ib-shell" id="ibApp">
        <aside class="ib-panel ib-side" aria-label="<?= $ibE($T['page_title']) ?>">
            <header class="ib-side__head">
                <h1><?= $ibE($T['page_title']) ?></h1>
                <div class="ib-side__actions">
                    <button type="button" class="ck-icon-btn" id="ibSelect" title="<?= $ibE($T['select']) ?>" aria-label="<?= $ibE($T['select']) ?>" aria-pressed="false"><i class="fa-regular fa-square-check" aria-hidden="true"></i></button>
                    <button type="button" class="ck-icon-btn" id="ibMore" title="<?= $ibE($T['more']) ?>" aria-label="<?= $ibE($T['more']) ?>"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
                </div>
            </header>

            <div class="ib-sections" id="ibSections" role="tablist">
                <button type="button" class="ib-section is-active" data-section="messages" role="tab" aria-selected="true">
                    <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                    <span><?= $ibE($T['sec_messages']) ?></span>
                    <b class="ck-count" data-count="messages" hidden>0</b>
                </button>
                <button type="button" class="ib-section" data-section="tickets" role="tab" aria-selected="false">
                    <i class="fa-solid fa-headset" aria-hidden="true"></i>
                    <span><?= $ibE($T['sec_tickets']) ?></span>
                    <b class="ck-count" data-count="tickets" hidden>0</b>
                </button>
            </div>

            <div class="ib-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" class="ck-input" id="ibSearch" placeholder="<?= $ibE($T['search']) ?>" maxlength="80" autocomplete="off">
                <button type="button" class="ib-filter" id="ibFilter" aria-label="<?= $ibE($T['filter']) ?>">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i>
                    <span id="ibFilterLabel"><?= $ibE($T['tab_inbox']) ?></span>
                </button>
            </div>

            <div class="ib-chips" id="ibChips"></div>
            <div class="ib-bulk" id="ibBulk" hidden></div>
            <div class="ib-list ck-scroll" id="ibList"></div>
        </aside>

        <section class="ib-panel ib-main" id="ibMain">
            <div class="ib-welcome" id="ibWelcome">
                <div class="ck-empty">
                    <div class="ck-empty__icon"><i class="fa-regular fa-envelope-open" aria-hidden="true"></i></div>
                    <strong><?= $ibE($T['welcome_title']) ?></strong>
                    <p><?= $ibE($T['welcome_sub']) ?></p>
                </div>
            </div>
            <div class="ib-reader" id="ibReader" hidden></div>
        </section>
    </main>

    <script>
        window.CripsumInbox = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    </script>
    <script src="/assets/chat/kit.js?v=<?= $kitJsVer ?>" defer></script>
    <script src="/assets/chat/kit-chat.js?v=<?= $kitChatVer ?>" defer></script>
    <script src="/assets/inbox/inbox.js?v=<?= $jsVer ?>" defer></script>
    <!-- Serve al menu della navbar. -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
