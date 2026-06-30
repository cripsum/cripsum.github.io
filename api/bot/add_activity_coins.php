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
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$discordId = isset($input['discord_id']) ? trim((string)$input['discord_id']) : '';

if (empty($discordId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing discord_id in request body.']);
    exit;
}

// 3. Find Linked User
$stmt = $mysqli->prepare("SELECT id, username, soldi, last_discord_activity FROM utenti WHERE discord_id = ? LIMIT 1");
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
    // Return successfully but indicate that the user is not linked on the website
    echo json_encode(['ok' => true, 'linked' => false, 'message' => 'User not linked on Cripsum.com.']);
    exit;
}

// 4. Verify Activity Cooldown (max 1 coin per 60 seconds)
$userId = (int)$user['id'];
$lastActivity = $user['last_discord_activity'] ? strtotime($user['last_discord_activity']) : 0;
$timeNow = time();
$cooldownSeconds = 60;

if (($timeNow - $lastActivity) < $cooldownSeconds) {
    echo json_encode([
        'ok' => true,
        'linked' => true,
        'cooldown' => true,
        'username' => $user['username'],
        'soldi' => (int)$user['soldi'],
        'message' => 'Message registered, but activity is on cooldown.'
    ]);
    exit;
}

// 5. Add 1 Coin & Update Activity Timestamp
$newSoldi = (int)$user['soldi'] + 1;
$stmtUpdate = $mysqli->prepare("
    UPDATE utenti 
    SET soldi = ?, last_discord_activity = NOW() 
    WHERE id = ? 
    LIMIT 1
");

if ($stmtUpdate) {
    $stmtUpdate->bind_param('ii', $newSoldi, $userId);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    echo json_encode([
        'ok' => true,
        'linked' => true,
        'cooldown' => false,
        'username' => $user['username'],
        'soldi' => $newSoldi,
        'message' => '1 Coin added successfully.'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to update user balance.']);
}
exit;
