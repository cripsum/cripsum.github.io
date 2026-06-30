<?php
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/discord_oauth.php';
require_once __DIR__ . '/../../includes/mission_generator.php';

header('Content-Type: application/json; charset=utf-8');

$apiKey = $_SERVER['HTTP_X_CRIPSUM_BOT_KEY'] ?? '';
if (empty($apiKey) || !hash_equals((string)CRIPSUM_BOT_API_KEY, (string)$apiKey)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied.']);
    exit;
}

$discordId = trim((string)($_GET['discord_id'] ?? ''));
if (!preg_match('/^\d{15,25}$/', $discordId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid Discord ID.']);
    exit;
}

$stmt = $mysqli->prepare('SELECT id, username FROM utenti WHERE discord_id = ? LIMIT 1');
$stmt->bind_param('s', $discordId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['ok' => true, 'linked' => false]);
    exit;
}

try {
    $data = getMissionsPageData($mysqli, (int)$user['id'], 'en');
    echo json_encode([
        'ok' => true,
        'linked' => true,
        'username' => $user['username'],
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    error_log('[Bot Missions] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Unable to load missions.']);
}
exit;
