<?php
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/discord_oauth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Authenticate Request
$apiKey = $_SERVER['HTTP_X_CRIPSUM_BOT_KEY'] ?? '';
if (empty($apiKey) || $apiKey !== CRIPSUM_BOT_API_KEY) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Invalid or missing X-Cripsum-Bot-Key.']);
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
    echo json_encode(['status' => 'error', 'message' => 'Missing discord_id.']);
    exit;
}

$stmt = $mysqli->prepare("SELECT id FROM utenti WHERE discord_id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database query preparation failed.']);
    exit;
}

$stmt->bind_param('s', $discordId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode([
        'status' => 'error',
        'linked' => false,
        'message' => 'Account Discord non collegato.'
    ]);
    exit;
}

$userId = (int)$user['id'];

// 3. Mock user session to bypass auth inside api_gacha_banners.php
$_SESSION['user_id'] = $userId;

// 4. Delegate to official banners script
require __DIR__ . '/../api_gacha_banners.php';
exit;
