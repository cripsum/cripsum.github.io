<?php

/**
 * Amici — pagina condivisa fra /it/amici e /en/amici.
 *
 * I due file dentro it/ e en/ sono wrapper che passano la lingua. La pagina
 * è un guscio: elenchi, richieste e ricerca li carica
 * assets/social/social-ui.js dagli endpoint in api/social/.
 *
 * Si aspetta $lang già impostata da chi include.
 */

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

$lang = (isset($lang) && $lang === 'en') ? 'en' : 'it';

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = $lang === 'en'
        ? 'You must be logged in to see your friends'
        : 'Per vedere i tuoi amici devi essere loggato';
    header('Location: accedi');
    exit();
}

$T = [
    'it' => [
        'title' => 'Amici',
        'description' => 'I tuoi amici su Cripsum: richieste, persone suggerite e ricerca utenti.',
        'lead' => 'Chi c\'è online, le richieste in arrivo e le persone che potresti conoscere.',
        'search' => 'Cerca un utente per nome...',
        'clear' => 'Cancella la ricerca',
        'chat' => 'Apri le chat',
        'privacy' => 'Privacy',
        'tabs' => 'Sezioni',
    ],
    'en' => [
        'title' => 'Friends',
        'description' => 'Your friends on Cripsum: requests, suggested people and user search.',
        'lead' => 'Who is online, incoming requests and people you may know.',
        'search' => 'Search a user by name...',
        'clear' => 'Clear search',
        'chat' => 'Open chats',
        'privacy' => 'Privacy',
        'tabs' => 'Sections',
    ],
][$lang];

$spE = static fn(?string $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$userId = (int)$_SESSION['user_id'];

$kitCssVer = @filemtime(__DIR__ . '/../assets/chat/kit.css') ?: 1;
$cssVer = @filemtime(__DIR__ . '/../assets/social/social.css') ?: 1;
$kitJsVer = @filemtime(__DIR__ . '/../assets/chat/kit.js') ?: 1;
$cardVer = @filemtime(__DIR__ . '/../assets/social/user-card.js') ?: 1;
$jsVer = @filemtime(__DIR__ . '/../assets/social/social-ui.js') ?: 1;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <?php $ogDescription = $T['description']; ?>
    <?php include __DIR__ . '/head-import.php'; ?>
    <title><?= $spE($T['title']) ?> - Cripsum&trade;</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/chat/kit.css?v=<?= $kitCssVer ?>">
    <link rel="stylesheet" href="/assets/social/social.css?v=<?= $cssVer ?>">
</head>

<body class="ck-page sp-page" data-user-id="<?= $userId ?>" data-logged-in="1" data-csrf="<?= $spE(csrf_token()) ?>">
    <?php include __DIR__ . '/navbar.php'; ?>

    <main class="sp-shell" id="spApp">
        <header class="sp-hero">
            <div class="sp-hero__text">
                <h1><?= $spE($T['title']) ?></h1>
                <p><?= $spE($T['lead']) ?></p>
            </div>
            <div class="sp-hero__actions">
                <a class="ck-btn ck-btn--ghost" href="/<?= $lang ?>/chat"><i class="fa-solid fa-comments" aria-hidden="true"></i> <?= $spE($T['chat']) ?></a>
                <button type="button" class="ck-btn ck-btn--ghost" id="spPrivacy"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> <?= $spE($T['privacy']) ?></button>
            </div>
        </header>

        <section class="sp-stats" id="spStats" aria-live="polite"></section>

        <section class="sp-panel">
            <div class="sp-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" class="ck-input" id="spSearch" placeholder="<?= $spE($T['search']) ?>" maxlength="30" autocomplete="off" spellcheck="false">
                <button type="button" class="ck-icon-btn" id="spSearchClear" aria-label="<?= $spE($T['clear']) ?>" hidden><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <nav class="sp-tabs" id="spTabs" role="tablist" aria-label="<?= $spE($T['tabs']) ?>"></nav>
            <div class="sp-content" id="spContent"></div>
        </section>
    </main>

    <?php include __DIR__ . '/footer.php'; ?>

    <script src="/assets/chat/kit.js?v=<?= $kitJsVer ?>" defer></script>
    <script src="/assets/social/user-card.js?v=<?= $cardVer ?>" defer></script>
    <script src="/assets/social/social-ui.js?v=<?= $jsVer ?>" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
