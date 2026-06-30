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
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$discordId = isset($input['discord_id']) ? trim((string)$input['discord_id']) : '';

if (empty($discordId)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing discord_id in request body.']);
    exit;
}

// 3. Find Linked User
$stmt = $mysqli->prepare("SELECT id, username, ruolo FROM utenti WHERE discord_id = ? LIMIT 1");
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
    echo json_encode(['status' => 'error', 'linked' => false, 'message' => 'User not linked on Cripsum.com.']);
    exit;
}

// 4. Mock user session to bypass auth inside api_gacha_pull.php
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['ruolo'] = $user['ruolo'];
unset($_SESSION['gacha_last_pull_ts']); // Bypass rate limit for bot calls if desired

// 5. Delegate to official gacha pull script
// We use require instead of include to ensure it halts if not found.
require __DIR__ . '/../api_gacha_pull.php';
exit;
