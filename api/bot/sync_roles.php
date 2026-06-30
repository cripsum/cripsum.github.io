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

// 2. Fetch Sync Data
$query = "
    SELECT 
        u.discord_id, 
        u.username, 
        u.ruolo, 
        u.is_premium, 
        GROUP_CONCAT(ucb.badge_id) AS badge_ids 
    FROM utenti u
    LEFT JOIN user_custom_badges ucb ON ucb.utente_id = u.id
    WHERE u.discord_id IS NOT NULL AND u.discord_id != ''
    GROUP BY u.id
";

$result = $mysqli->query($query);
if (!$result) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to fetch user role sync data.']);
    exit;
}

$users = [];
while ($row = $result->fetch_assoc()) {
    $badgeIdsStr = $row['badge_ids'] ? trim((string)$row['badge_ids']) : '';
    $badgeIds = $badgeIdsStr !== '' ? array_map('intval', explode(',', $badgeIdsStr)) : [];
    
    $users[] = [
        'discord_id' => $row['discord_id'],
        'username' => $row['username'],
        'ruolo' => $row['ruolo'],
        'is_premium' => (int)$row['is_premium'],
        'badge_ids' => $badgeIds
    ];
}

echo json_encode(['ok' => true, 'users' => $users]);
exit;
