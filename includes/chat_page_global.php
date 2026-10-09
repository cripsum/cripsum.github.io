<?php

/**
 * Chat globale — pagina condivisa fra /it/global-chat e /en/global-chat.
 *
 * I due file dentro it/ e en/ sono wrapper che passano la lingua. I testi
 * dell'interfaccia stanno in assets/chat/global.js (li sceglie dalla lingua
 * della pagina), quelli scritti dal server qui sotto in $T.
 *
 * Si aspetta $lang già impostata da chi include.
 */

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/chat_config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/chat_v2_helpers.php';
require_once __DIR__ . '/chat_global.php';

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

$lang = (isset($lang) && $lang === 'en') ? 'en' : 'it';

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = $lang === 'en'
        ? 'You must be logged in to access the global chat'
        : 'Per accedere alla chat globale devi essere loggato';
    header('Location: accedi');
    exit();
}

$T = [
    'it' => [
        'title' => 'Chat Globale',
        'kicker' => 'Aperta a tutti',
        'gate_title' => 'Prima di entrare',
        'gate_text' => 'La chat globale è uno spazio comune: rispetta gli altri, niente spam, niente contenuti pericolosi o per adulti. Lo staff può rimuovere messaggi e sospendere chi non segue le regole.',
        'gate_link' => 'Leggi il regolamento della chat',
        'gate_check' => 'Ho letto il regolamento e lo accetto',
        'gate_button' => 'Entra in chat',
        'side_title' => 'In chat adesso',
        'online_site' => 'online sul sito',
        'search' => 'Cerca nei messaggi',
        'menu' => 'Altro',
        'people' => 'Chi c\'è in chat',
    ],
    'en' => [
        'title' => 'Global Chat',
        'kicker' => 'Open to everyone',
        'gate_title' => 'Before you enter',
        'gate_text' => 'The global chat is a shared space: respect others, no spam, no harmful or adult content. The staff can remove messages and suspend people who break the rules.',
        'gate_link' => 'Read the chat policy',
        'gate_check' => 'I have read the policy and I accept it',
        'gate_button' => 'Enter the chat',
        'side_title' => 'In the chat now',
        'online_site' => 'online on the site',
        'search' => 'Search messages',
        'menu' => 'More',
        'people' => 'Who is in the chat',
    ],
][$lang];

$gcE = static fn(?string $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

$userId = (int)$_SESSION['user_id'];
$timeoutColumn = rt_has_col($mysqli, 'utenti', 'chat_timeout_until') ? ', chat_timeout_until' : '';
$stmt = $mysqli->prepare("SELECT id, username, display_name, ruolo, is_premium, lineeGuidaChat$timeoutColumn FROM utenti WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$gcUser = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$gcUser['id'] = $userId;
$gcUser['username'] = (string)($gcUser['username'] ?? ($_SESSION['username'] ?? 'utente'));
$gcUser['ruolo'] = (string)($gcUser['ruolo'] ?? 'utente');
$gcUser['is_premium'] = (int)($gcUser['is_premium'] ?? 0) === 1;
$gcUser['is_mod'] = in_array($gcUser['ruolo'], ['admin', 'owner'], true);

$accepted = (int)($gcUser['lineeGuidaChat'] ?? 0) === 1;
$_SESSION['lineeGuidaChat'] = $accepted ? 1 : 0;
$csrf = csrf_token();

$config = null;
if ($accepted) {
    $hidden = sc_refresh_hidden($mysqli, $userId);
    gc_presence_touch($userId, gc_presence_entry($gcUser));
    gc_online_refresh($mysqli);
    gc_restore_pinned($mysqli);

    // Aprire la chat vale come aver visto le menzioni: va fatto prima della
    // navbar, che altrimenti mostrerebbe ancora il numero.
    rt_mentions_clear($userId);

    // I messaggi prima, il timbro dopo: se nel mezzo ne arriva uno nuovo il
    // browser lo riceve due volte (e lo riconosce dall'id) invece di perderlo.
    $stamp = rt_read('g');
    $initial = gc_views(gc_fetch($mysqli, ['limit' => 40]), $gcUser, $hidden);
    $aux = gc_aux_view($stamp, $userId, $hidden);

    $timeoutTs = !empty($gcUser['chat_timeout_until']) ? strtotime((string)$gcUser['chat_timeout_until']) : 0;

    $config = [
        'user' => [
            'id' => $userId,
            'username' => $gcUser['username'],
            'role' => $gcUser['ruolo'],
            'isMod' => $gcUser['is_mod'],
            'isOwner' => $gcUser['ruolo'] === 'owner',
            'premium' => $gcUser['is_premium'],
        ],
        'maxLength' => (int)MAX_MESSAGE_LENGTH,
        'editWindow' => (int)CHAT_EDIT_WINDOW_SECONDS,
        'timeoutUntil' => $timeoutTs > time() ? $timeoutTs : 0,
        'timeoutAvailable' => rt_has_col($mysqli, 'utenti', 'chat_timeout_until'),
        'messages' => $initial,
        'state' => ['seq' => (int)($stamp['seq'] ?? 0), 'aux' => (int)($stamp['aux'] ?? 0)] + $aux,
        'emojis' => cc_custom_emojis($mysqli),
        'policyUrl' => "/$lang/chat-policy",
    ];
}

$kitCssVer = @filemtime(__DIR__ . '/../assets/chat/kit.css') ?: 1;
$cssVer = @filemtime(__DIR__ . '/../assets/chat/global.css') ?: 1;
$kitJsVer = @filemtime(__DIR__ . '/../assets/chat/kit.js') ?: 1;
$kitChatVer = @filemtime(__DIR__ . '/../assets/chat/kit-chat.js') ?: 1;
$cardVer = @filemtime(__DIR__ . '/../assets/social/user-card.js') ?: 1;
$jsVer = @filemtime(__DIR__ . '/../assets/chat/global.js') ?: 1;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>"<?= cripsum_theme_html_attr() ?>>

<head>
    <?php $ogDescription = $T['gate_text']; ?>
    <?php include __DIR__ . '/head-import.php'; ?>
    <title><?= $gcE($T['title']) ?> - Cripsum&trade;</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <link rel="stylesheet" href="/assets/chat/kit.css?v=<?= $kitCssVer ?>">
    <link rel="stylesheet" href="/assets/chat/global.css?v=<?= $cssVer ?>">
    <?php cripsum_theme_head(); ?>
</head>

<body class="ck-page gc-page" data-user-id="<?= $userId ?>" data-logged-in="1" data-csrf="<?= $gcE($csrf) ?>">
    <?php include __DIR__ . '/navbar.php'; ?>

    <?php if (!$accepted): ?>
        <main class="gc-gate">
            <form class="gc-gate__card" method="POST" action="/includes/accept_chat_terms.php">
                <input type="hidden" name="csrf_token" value="<?= $gcE($csrf) ?>">
                <input type="hidden" name="lang" value="<?= $lang ?>">
                <div class="gc-gate__icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>
                <h1><?= $gcE($T['gate_title']) ?></h1>
                <p><?= $gcE($T['gate_text']) ?></p>
                <a class="ck-link" href="/<?= $lang ?>/chat-policy" target="_blank" rel="noopener"><i class="fa-solid fa-book-open" aria-hidden="true"></i> <?= $gcE($T['gate_link']) ?></a>
                <label class="gc-gate__check">
                    <input type="checkbox" name="accept" value="1" required>
                    <span><?= $gcE($T['gate_check']) ?></span>
                </label>
                <button type="submit" class="ck-btn ck-btn--primary ck-btn--block"><?= $gcE($T['gate_button']) ?></button>
            </form>
        </main>
    <?php else: ?>
        <main class="gc-shell" id="gcApp">
            <section class="gc-panel gc-main" aria-label="<?= $gcE($T['title']) ?>">
                <header class="gc-head">
                    <div class="gc-head__title">
                        <div>
                            <h1><?= $gcE($T['title']) ?></h1>
                            <p><span class="gc-live" id="gcLive"></span><span id="gcSubtitle"><?= $gcE($T['kicker']) ?></span></p>
                        </div>
                    </div>
                    <div class="gc-head__actions">
                        <button type="button" class="gc-people-btn" id="gcPeopleBtn" aria-label="<?= $gcE($T['people']) ?>">
                            <span class="ck-dot is-online"></span>
                            <strong id="gcPresentCount">0</strong>
                        </button>
                        <button type="button" class="ck-icon-btn" id="gcSearchBtn" aria-label="<?= $gcE($T['search']) ?>" title="<?= $gcE($T['search']) ?>"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
                        <button type="button" class="ck-icon-btn" id="gcMenuBtn" aria-label="<?= $gcE($T['menu']) ?>" title="<?= $gcE($T['menu']) ?>"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
                    </div>
                </header>

                <div class="gc-pinned" id="gcPinned" hidden></div>
                <div class="gc-search" id="gcSearch" hidden></div>
                <div class="gc-banner" id="gcBanner" hidden></div>

                <div class="gc-list" id="gcList" aria-live="polite"></div>
                <div class="gc-typing" id="gcTyping" hidden></div>
                <div id="gcComposer"></div>
            </section>

            <aside class="gc-panel gc-side" id="gcSide" aria-label="<?= $gcE($T['side_title']) ?>">
                <header class="gc-side__head">
                    <div>
                        <h2><?= $gcE($T['side_title']) ?></h2>
                        <p><strong id="gcOnlineCount">0</strong> <?= $gcE($T['online_site']) ?></p>
                    </div>
                    <button type="button" class="ck-icon-btn gc-side__close" id="gcSideClose" aria-label="OK"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </header>
                <div class="gc-side__list ck-scroll" id="gcPresent"></div>
            </aside>
            <div class="gc-side-backdrop" id="gcSideBackdrop" hidden></div>
        </main>

        <script>
            window.CripsumChat = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        </script>
        <script src="/assets/chat/kit.js?v=<?= $kitJsVer ?>" defer></script>
        <script src="/assets/chat/kit-chat.js?v=<?= $kitChatVer ?>" defer></script>
        <script src="/assets/social/user-card.js?v=<?= $cardVer ?>" defer></script>
        <script src="/assets/chat/global.js?v=<?= $jsVer ?>" defer></script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
