<?php

/**
 * Chat private e di gruppo — pagina condivisa fra /it/chat e /en/chat.
 *
 * I due file dentro it/ e en/ sono wrapper che passano la lingua. La pagina
 * è un guscio: liste, messaggi e dettagli li carica assets/chat/private.js
 * dagli endpoint in api/chat/.
 *
 * Si aspetta $lang già impostata da chi include.
 */

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/chat_config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/chat_v2_helpers.php';
require_once __DIR__ . '/chat_groups.php';

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

$lang = (isset($lang) && $lang === 'en') ? 'en' : 'it';

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = $lang === 'en'
        ? 'You must be logged in to open your chats'
        : 'Per aprire le chat devi essere loggato';
    header('Location: accedi');
    exit();
}

$T = [
    'it' => [
        'title' => 'Chat',
        'description' => 'Le tue chat private e di gruppo su Cripsum.',
        'search' => 'Cerca chat o persone...',
        'new_chat' => 'Nuova chat',
        'new_group' => 'Nuovo gruppo',
        'menu' => 'Altro',
        'empty_title' => 'Le tue chat',
        'empty_text' => 'Scegli una conversazione dalla lista, oppure iniziane una nuova.',
        'back' => 'Torna alla lista',
        'details' => 'Dettagli',
    ],
    'en' => [
        'title' => 'Chats',
        'description' => 'Your private and group chats on Cripsum.',
        'search' => 'Search chats or people...',
        'new_chat' => 'New chat',
        'new_group' => 'New group',
        'menu' => 'More',
        'empty_title' => 'Your chats',
        'empty_text' => 'Pick a conversation from the list, or start a new one.',
        'back' => 'Back to the list',
        'details' => 'Details',
    ],
][$lang];

$pcE = static fn(?string $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

$userId = (int)$_SESSION['user_id'];
$stmt = $mysqli->prepare('SELECT username, display_name, is_premium FROM utenti WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$pcUser = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$config = [
    'user' => [
        'id' => $userId,
        'username' => (string)($pcUser['username'] ?? ($_SESSION['username'] ?? '')),
        'displayName' => (string)(($pcUser['display_name'] ?? '') ?: ($pcUser['username'] ?? '')),
        'premium' => (int)($pcUser['is_premium'] ?? 0) === 1,
    ],
    'maxLength' => CC_MAX_LEN,
    'editWindow' => CC_EDIT_WINDOW,
    'maxFiles' => CC_MAX_FILES,
    'maxMembers' => CG_MAX_MEMBERS,
    'emojis' => cc_custom_emojis($mysqli),
    'supportUrl' => "/$lang/supporto",
    'friendsUrl' => "/$lang/amici",
    'policyUrl' => "/$lang/chat-policy",
];

$kitCssVer = @filemtime(__DIR__ . '/../assets/chat/kit.css') ?: 1;
$cssVer = @filemtime(__DIR__ . '/../assets/chat/chat.css') ?: 1;
$kitJsVer = @filemtime(__DIR__ . '/../assets/chat/kit.js') ?: 1;
$kitChatVer = @filemtime(__DIR__ . '/../assets/chat/kit-chat.js') ?: 1;
$cardVer = @filemtime(__DIR__ . '/../assets/social/user-card.js') ?: 1;
$jsVer = @filemtime(__DIR__ . '/../assets/chat/private.js') ?: 1;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <?php $ogDescription = $T['description']; ?>
    <?php include __DIR__ . '/head-import.php'; ?>
    <title><?= $pcE($T['title']) ?> - Cripsum&trade;</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <link rel="stylesheet" href="/assets/chat/kit.css?v=<?= $kitCssVer ?>">
    <link rel="stylesheet" href="/assets/chat/chat.css?v=<?= $cssVer ?>">
</head>

<body class="ck-page pc-page" data-user-id="<?= $userId ?>" data-logged-in="1" data-csrf="<?= $pcE(csrf_token()) ?>">
    <?php include __DIR__ . '/navbar.php'; ?>

    <main class="pc-shell" id="pcApp">
        <aside class="pc-panel pc-side" aria-label="<?= $pcE($T['title']) ?>">
            <header class="pc-side__head">
                <h1><?= $pcE($T['title']) ?></h1>
                <div class="pc-side__actions">
                    <button type="button" class="ck-icon-btn" id="pcNewChat" title="<?= $pcE($T['new_chat']) ?>" aria-label="<?= $pcE($T['new_chat']) ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>
                    <button type="button" class="ck-icon-btn" id="pcNewGroup" title="<?= $pcE($T['new_group']) ?>" aria-label="<?= $pcE($T['new_group']) ?>"><i class="fa-solid fa-user-group" aria-hidden="true"></i></button>
                    <button type="button" class="ck-icon-btn" id="pcMenu" title="<?= $pcE($T['menu']) ?>" aria-label="<?= $pcE($T['menu']) ?>"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
                </div>
            </header>
            <div class="pc-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" class="ck-input" id="pcSearch" placeholder="<?= $pcE($T['search']) ?>" maxlength="40" autocomplete="off">
            </div>
            <div class="pc-filters" id="pcFilters" role="tablist"></div>
            <div class="pc-list ck-scroll" id="pcList"></div>
        </aside>

        <section class="pc-panel pc-main" id="pcMain">
            <div class="pc-welcome" id="pcWelcome">
                <div class="ck-empty">
                    <div class="ck-empty__icon"><i class="fa-regular fa-comments" aria-hidden="true"></i></div>
                    <strong><?= $pcE($T['empty_title']) ?></strong>
                    <p><?= $pcE($T['empty_text']) ?></p>
                    <button type="button" class="ck-btn ck-btn--primary" id="pcWelcomeNew"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> <?= $pcE($T['new_chat']) ?></button>
                </div>
            </div>

            <div class="pc-conversation" id="pcConversation" hidden>
                <header class="pc-head">
                    <button type="button" class="ck-icon-btn pc-head__back" id="pcBack" aria-label="<?= $pcE($T['back']) ?>"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                    <button type="button" class="pc-head__who" id="pcHeadWho" aria-label="<?= $pcE($T['details']) ?>">
                        <span class="pc-avatar" id="pcHeadAvatar"></span>
                        <span class="pc-head__text">
                            <strong id="pcHeadName"></strong>
                            <small id="pcHeadStatus"></small>
                        </span>
                    </button>
                    <div class="pc-head__actions" id="pcHeadActions"></div>
                </header>

                <div class="pc-strip" id="pcRequest" hidden></div>
                <div class="pc-strip pc-strip--pinned" id="pcPinned" hidden></div>
                <div class="pc-searchbar" id="pcChatSearch" hidden></div>
                <div class="pc-strip pc-strip--history" id="pcBanner" hidden></div>

                <div class="pc-messages" id="pcMessages"></div>
                <div id="pcComposer"></div>
            </div>
        </section>

        <aside class="pc-panel pc-details" id="pcDetails" hidden></aside>
        <div class="pc-details-backdrop" id="pcDetailsBackdrop" hidden></div>
    </main>

    <script>
        window.CripsumPrivateChat = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    </script>
    <script src="/assets/chat/kit.js?v=<?= $kitJsVer ?>" defer></script>
    <script src="/assets/chat/kit-chat.js?v=<?= $kitChatVer ?>" defer></script>
    <script src="/assets/social/user-card.js?v=<?= $cardVer ?>" defer></script>
    <script src="/assets/chat/private.js?v=<?= $jsVer ?>" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
