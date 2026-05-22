<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';
require_once __DIR__ . '/../includes/profile_v3_helpers.php';

checkBan($mysqli);

$allowed = ['💎', '🔥', '🖤', '⚡'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $profileId = (int)($_GET['profile_id'] ?? 0);
    if ($profileId <= 0) profile_json_response(['ok' => false, 'message' => 'Missing profile.'], 400);
    $profile = profile_get_public_profile($mysqli, (string)$profileId);
    if (!$profile) profile_json_response(['ok' => false, 'message' => 'Profile not found.'], 404);
    if (($profile['profile_visibility'] ?? 'public') === 'private' && !profile_can_edit($profileId)) {
        profile_json_response(['ok' => false, 'message' => 'Profile is private.'], 403);
    }
    if (($profile['profile_visibility'] ?? 'public') === 'logged_in' && !isLoggedIn()) {
        profile_json_response(['ok' => false, 'message' => 'Login required.'], 401);
    }

    $counts = array_fill_keys($allowed, 0);
    if (profile_v3_table_exists($mysqli, 'profile_reactions')) {
        $stmt = $mysqli->prepare('SELECT reaction, COUNT(*) AS total FROM profile_reactions WHERE profile_id = ? GROUP BY reaction');
        if ($stmt) {
            $stmt->bind_param('i', $profileId);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [] as $row) {
                if (in_array($row['reaction'], $allowed, true)) $counts[$row['reaction']] = (int)$row['total'];
            }
            $stmt->close();
        }
    }

    profile_json_response(['ok' => true, 'counts' => $counts]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    profile_json_response(['ok' => false, 'message' => 'Invalid method.'], 405);
}

$payload = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($payload)) $payload = $_POST;

$profileId = (int)($payload['profile_id'] ?? 0);
$reaction = (string)($payload['reaction'] ?? '');

if ($profileId <= 0 || !in_array($reaction, $allowed, true)) {
    profile_json_response(['ok' => false, 'message' => 'Invalid reaction.'], 422);
}

if (!profile_v3_rate_limit($mysqli, 'profile_reaction', 18, 300, profile_current_user_id())) {
    profile_json_response(['ok' => false, 'message' => 'Too many reactions.'], 429);
}

$profile = profile_get_public_profile($mysqli, (string)$profileId);
if (!$profile) {
    profile_json_response(['ok' => false, 'message' => 'Profile not found.'], 404);
}
if (($profile['profile_visibility'] ?? 'public') === 'private' && !profile_can_edit($profileId)) {
    profile_json_response(['ok' => false, 'message' => 'Profile is private.'], 403);
}
if (($profile['profile_visibility'] ?? 'public') === 'logged_in' && !isLoggedIn()) {
    profile_json_response(['ok' => false, 'message' => 'Login required.'], 401);
}

if (!profile_v3_table_exists($mysqli, 'profile_reactions')) {
    profile_json_response(['ok' => false, 'message' => 'Reactions table is not installed.'], 503);
}

$userId = profile_current_user_id();
$ipHash = hash('sha256', profile_v3_client_ip() . '|cripsum-profile-reaction');
$stmt = $mysqli->prepare('INSERT INTO profile_reactions (profile_id, user_id, reaction, ip_hash, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE updated_at = NOW()');
if (!$stmt) {
    profile_json_response(['ok' => false, 'message' => 'Database unavailable.'], 500);
}
$stmt->bind_param('iiss', $profileId, $userId, $reaction, $ipHash);
$stmt->execute();
$stmt->close();

profile_v3_track_event($mysqli, $profileId, 'reaction', null, json_encode(['reaction' => $reaction], JSON_UNESCAPED_UNICODE));

$counts = array_fill_keys($allowed, 0);
$stmt = $mysqli->prepare('SELECT reaction, COUNT(*) AS total FROM profile_reactions WHERE profile_id = ? GROUP BY reaction');
$stmt->bind_param('i', $profileId);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [] as $row) {
    if (in_array($row['reaction'], $allowed, true)) $counts[$row['reaction']] = (int)$row['total'];
}
$stmt->close();

profile_json_response(['ok' => true, 'counts' => $counts]);
