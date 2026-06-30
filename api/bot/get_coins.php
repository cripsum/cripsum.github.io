<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/discord_oauth.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Authenticate Request
$apiKey = $_SERVER['HTTP_X_CRIPSUM_BOT_KEY'] ?? '';
if (empty($apiKey) || $apiKey !== CRIPSUM_BOT_API_KEY) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied. Invalid or missing X-Cripsum-Bot-Key.']);
    exit;
}

// 2. Parse Input
$discordId = isset($_GET['discord_id']) ? trim((string)$_GET['discord_id']) : '';
if (empty($discordId)) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $discordId = isset($input['discord_id']) ? trim((string)$input['discord_id']) : '';
}

if (empty($discordId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing discord_id.']);
    exit;
}

// 3. Find Linked User
$stmt = $mysqli->prepare("SELECT username, soldi, godoshards_balance, is_premium FROM utenti WHERE discord_id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database query preparation failed.']);
    exit;
}

$stmt->bind_param('s', $discordId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['ok' => true, 'linked' => false, 'message' => 'User not linked on Cripsum.com.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'linked' => true,
    'username' => $user['username'],
    'soldi' => (int)$user['soldi'],
    'shards' => (int)$user['godoshards_balance'],
    'is_premium' => (int)$user['is_premium'] === 1
]);
exit;
