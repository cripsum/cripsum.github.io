<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';
require_once __DIR__ . '/../includes/discord_notify.php';


checkBan($mysqli);

$expectsJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
    || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

if (!isLoggedIn()) {
    if ($expectsJson) {
        profile_json_response(['ok' => false, 'message' => 'Session expired. Please sign in again.'], 401);
    }
    header('Location: /en/accedi');
    exit;
}

$csrfToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
    ? $_POST['csrf_token']
    : null;
$validCsrf = profile_validate_csrf($csrfToken)
    || (function_exists('csrf_validate') && csrf_validate($csrfToken));

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$validCsrf) {
    if ($expectsJson) {
        profile_json_response(['ok' => false, 'message' => 'Session expired. Please reload the page.'], 403);
    }

    $_SESSION['profile_flash_error'] = 'Session expired. Please try again.';
    $fallbackUrl = $_SERVER['HTTP_REFERER'] ?? '/en/impostazioni#connections';
    header('Location: ' . $fallbackUrl);
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$targetUserId = isset($_POST['target_user_id']) && profile_is_staff() ? (int)$_POST['target_user_id'] : $currentUserId;

if (!profile_can_edit($targetUserId)) {
    if ($expectsJson) {
        profile_json_response(['ok' => false, 'message' => 'Access denied.'], 403);
    }

    $_SESSION['profile_flash_error'] = 'Access denied.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/en/impostazioni#connections'));
    exit;
}

$stmt = $mysqli->prepare("\n    UPDATE utenti\n    SET discord_id = NULL,\n        discord_username = NULL,\n        discord_global_name = NULL,\n        discord_avatar = NULL,\n        discord_use_avatar = 0,\n        discord_use_display_name = 0,\n        discord_connected_at = NULL,\n        profile_updated_at = NOW()\n    WHERE id = ?\n");
if (!$stmt) {
    if ($expectsJson) {
        profile_json_response(['ok' => false, 'message' => 'Unable to disconnect Discord. Please try again.'], 500);
    }

    $_SESSION['profile_flash_error'] = 'Unable to disconnect Discord. Please try again.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/en/impostazioni#connections'));
    exit;
}

$stmt->bind_param('i', $targetUserId);
$disconnected = $stmt->execute();
$stmt->close();

if (!$disconnected) {
    if ($expectsJson) {
        profile_json_response(['ok' => false, 'message' => 'Unable to disconnect Discord. Please try again.'], 500);
    }

    $_SESSION['profile_flash_error'] = 'Unable to disconnect Discord. Please try again.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/en/impostazioni#connections'));
    exit;
}

profile_record_activity($mysqli, $targetUserId, 'discord', 'Discord disconnected');
notifyDiscordSiteLogs('discord_disconnect', 'Account Discord Scollegato', "L'utente ha scollegato il proprio account Discord.", [], $targetUserId);
$_SESSION['profile_flash_success'] = 'Discord disconnected.';

if ($expectsJson) {
    profile_json_response(['ok' => true, 'message' => 'Discord disconnected.']);
}

$returnUrl = $_POST['return_url'] ?? $_GET['return_url'] ?? $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($returnUrl) && (strpos($returnUrl, '/') === 0 || strpos($returnUrl, 'http') === 0)) {
    if ((strpos($returnUrl, 'impostazioni') !== false) && strpos($returnUrl, '#') === false) {
        $returnUrl .= '#connections';
    }
    $redirect = $returnUrl;
} else {
    $redirect = '/edit-profile.php' . (profile_is_staff() && $targetUserId !== $currentUserId ? '?user_id=' . $targetUserId : '');
}
header('Location: ' . $redirect);
exit;
