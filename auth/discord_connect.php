<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';
require_once __DIR__ . '/../config/discord_oauth.php';

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = '/edit-profile.php';
    $_SESSION['login_message'] = 'Per collegare Discord devi essere loggato';
    header('Location: /it/accedi');
    exit;
}

if (CRIPSUM_DISCORD_CLIENT_ID === 'INSERISCI_CLIENT_ID' || CRIPSUM_DISCORD_CLIENT_SECRET === 'INSERISCI_CLIENT_SECRET') {
    http_response_code(500);
    exit('Discord OAuth non configurato. Imposta client id e secret.');
}

$currentUserId = (int)$_SESSION['user_id'];
$targetUserId = isset($_GET['target_user_id']) && profile_is_staff() ? (int)$_GET['target_user_id'] : $currentUserId;

if (!profile_can_edit($targetUserId)) {
    http_response_code(403);
    exit('Accesso negato.');
}

$state = bin2hex(random_bytes(32));
$_SESSION['discord_oauth_state'] = $state;
$_SESSION['discord_oauth_target_user_id'] = $targetUserId;

$params = http_build_query([
    'client_id' => CRIPSUM_DISCORD_CLIENT_ID,
    'redirect_uri' => CRIPSUM_DISCORD_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'identify',
    'state' => $state,
    'prompt' => 'consent',
]);

header('Location: https://discord.com/oauth2/authorize?' . $params);
exit;
