<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';
require_once __DIR__ . '/../includes/profile_v3_helpers.php';

checkBan($mysqli);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    profile_json_response(['ok' => false, 'message' => 'Invalid method.'], 405);
}

$currentUserId = profile_current_user_id();
if (!profile_v3_rate_limit($mysqli, 'check_username', 40, 300, $currentUserId)) {
    profile_json_response(['ok' => false, 'message' => 'Too many checks.'], 429);
}

$username = trim((string)($_GET['username'] ?? ''));
$targetUserId = isset($_GET['target_user_id']) && profile_is_staff() ? (int)$_GET['target_user_id'] : (int)($currentUserId ?? 0);

if (!profile_is_valid_username($username)) {
    profile_json_response([
        'ok' => true,
        'available' => false,
        'valid' => false,
        'message' => 'Use 3-20 letters, numbers, or underscores.',
    ]);
}

$stmt = $mysqli->prepare('SELECT id FROM utenti WHERE LOWER(username) = LOWER(?) AND id != ? LIMIT 1');
if (!$stmt) {
    profile_json_response(['ok' => false, 'message' => 'Database unavailable.'], 500);
}
$stmt->bind_param('si', $username, $targetUserId);
$stmt->execute();
$exists = (bool)$stmt->get_result()->fetch_assoc();
$stmt->close();

profile_json_response([
    'ok' => true,
    'valid' => true,
    'available' => !$exists,
    'message' => $exists ? 'Username already in use.' : 'Username available.',
]);
