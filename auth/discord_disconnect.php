<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

checkBan($mysqli);

if (!isLoggedIn()) {
    header('Location: /it/accedi');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !profile_validate_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Sessione scaduta.');
}

$currentUserId = (int)$_SESSION['user_id'];
$targetUserId = isset($_POST['target_user_id']) && profile_is_staff() ? (int)$_POST['target_user_id'] : $currentUserId;

if (!profile_can_edit($targetUserId)) {
    http_response_code(403);
    exit('Accesso negato.');
}

$stmt = $mysqli->prepare("\n    UPDATE utenti\n    SET discord_id = NULL,\n        discord_username = NULL,\n        discord_global_name = NULL,\n        discord_avatar = NULL,\n        discord_use_avatar = 0,\n        discord_use_display_name = 0,\n        discord_connected_at = NULL,\n        profile_updated_at = NOW()\n    WHERE id = ?\n");
$stmt->bind_param('i', $targetUserId);
$stmt->execute();
$stmt->close();

profile_record_activity($mysqli, $targetUserId, 'discord', 'Ha scollegato Discord');
$_SESSION['profile_flash_success'] = 'Discord scollegato.';

$redirect = '/edit-profile.php' . (profile_is_staff() && $targetUserId !== $currentUserId ? '?user_id=' . $targetUserId : '');
header('Location: ' . $redirect);
exit;
