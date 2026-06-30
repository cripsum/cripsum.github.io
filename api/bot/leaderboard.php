<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/discord_oauth.php';

header('Content-Type: application/json; charset=utf-8');

$apiKey = $_SERVER['HTTP_X_CRIPSUM_BOT_KEY'] ?? '';
if (empty($apiKey) || !hash_equals((string)CRIPSUM_BOT_API_KEY, (string)$apiKey)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied.']);
    exit;
}

$type = strtolower(trim((string)($_GET['type'] ?? 'godos')));
$queries = [
    'godos' => "
        SELECT u.username, u.is_premium, u.soldi AS value
        FROM utenti u
        WHERE COALESCE(u.isBannato, 0) = 0
        ORDER BY value DESC, u.username ASC LIMIT 10",
    'shards' => "
        SELECT u.username, u.is_premium, u.godoshards_balance AS value
        FROM utenti u
        WHERE COALESCE(u.isBannato, 0) = 0
        ORDER BY value DESC, u.username ASC LIMIT 10",
    'pulls' => "
        SELECT u.username, u.is_premium, COALESCE(SUM(up.`quantità`), 0) AS value
        FROM utenti u
        LEFT JOIN utenti_personaggi up ON up.utente_id = u.id
        WHERE COALESCE(u.isBannato, 0) = 0
        GROUP BY u.id, u.username, u.is_premium
        ORDER BY value DESC, u.username ASC LIMIT 10",
    'collection' => "
        SELECT u.username, u.is_premium, COUNT(DISTINCT up.personaggio_id) AS value
        FROM utenti u
        LEFT JOIN utenti_personaggi up ON up.utente_id = u.id
        WHERE COALESCE(u.isBannato, 0) = 0
        GROUP BY u.id, u.username, u.is_premium
        ORDER BY value DESC, u.username ASC LIMIT 10",
    'achievements' => "
        SELECT u.username, u.is_premium, COUNT(DISTINCT ua.achievement_id) AS value
        FROM utenti u
        LEFT JOIN utenti_achievement ua ON ua.utente_id = u.id
        WHERE COALESCE(u.isBannato, 0) = 0
        GROUP BY u.id, u.username, u.is_premium
        ORDER BY value DESC, u.username ASC LIMIT 10",
    'missions' => "
        SELECT u.username, u.is_premium, COALESCE(SUM(um.completata), 0) AS value
        FROM utenti u
        LEFT JOIN user_missions um ON um.user_id = u.id
        WHERE COALESCE(u.isBannato, 0) = 0
        GROUP BY u.id, u.username, u.is_premium
        ORDER BY value DESC, u.username ASC LIMIT 10",
    'views' => "
        SELECT u.username, u.is_premium, COALESCE(u.profile_views, 0) AS value
        FROM utenti u
        WHERE COALESCE(u.isBannato, 0) = 0
        ORDER BY value DESC, u.username ASC LIMIT 10",
    'duels' => "
        SELECT u.username, u.is_premium, COALESCE(g.rating, 0) AS value
        FROM utenti u
        LEFT JOIN game_player_stats g ON g.user_id = u.id
        WHERE COALESCE(u.isBannato, 0) = 0
        ORDER BY value DESC, u.username ASC LIMIT 10",
];

if (!isset($queries[$type])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid leaderboard type.']);
    exit;
}

$result = $mysqli->query($queries[$type]);
if (!$result) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Unable to load leaderboard.']);
    exit;
}

$entries = [];
$position = 1;
while ($row = $result->fetch_assoc()) {
    $entries[] = [
        'position' => $position++,
        'username' => (string)$row['username'],
        'is_premium' => (int)($row['is_premium'] ?? 0) === 1,
        'value' => (int)($row['value'] ?? 0),
    ];
}

echo json_encode([
    'ok' => true,
    'type' => $type,
    'entries' => $entries,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
