<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/discord_oauth.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$apiKey = $_SERVER['HTTP_X_CRIPSUM_BOT_KEY'] ?? '';
if (empty($apiKey) || !hash_equals((string)CRIPSUM_BOT_API_KEY, (string)$apiKey)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$username = trim((string)($_GET['username'] ?? ''));
if (!preg_match('/^[a-zA-Z0-9_.-]{2,30}$/', $username)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid username.']);
    exit;
}

$stmt = $mysqli->prepare("
    SELECT
        u.id, u.username, u.display_name, u.bio, u.ruolo, u.is_premium,
        u.profile_visibility, u.profile_banner_type, u.accent_color,
        u.profile_views, u.data_creazione, u.ultimo_accesso, u.custom_alias,
        COALESCE(ach.num_achievement, 0) AS num_achievement,
        COALESCE(inv.num_personaggi, 0) AS num_personaggi,
        COALESCE(inv.total_personaggi, 0) AS total_personaggi
    FROM utenti u
    LEFT JOIN (
        SELECT utente_id, COUNT(DISTINCT achievement_id) AS num_achievement
        FROM utenti_achievement GROUP BY utente_id
    ) ach ON ach.utente_id = u.id
    LEFT JOIN (
        SELECT utente_id, COUNT(DISTINCT personaggio_id) AS num_personaggi,
               COALESCE(SUM(`quantità`), 0) AS total_personaggi
        FROM utenti_personaggi GROUP BY utente_id
    ) inv ON inv.utente_id = u.id
    WHERE COALESCE(u.isBannato, 0) = 0
      AND (LOWER(u.username) = LOWER(?) OR LOWER(u.custom_alias) = LOWER(?))
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Unable to prepare profile query.']);
    exit;
}

$stmt->bind_param('ss', $username, $username);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
    echo json_encode(['ok' => true, 'found' => false]);
    exit;
}

$visibility = (string)($profile['profile_visibility'] ?? 'public');
$isPrivate = $visibility !== 'public';

$publicData = [
    'username' => (string)$profile['username'],
    'display_name' => trim((string)($profile['display_name'] ?? '')) ?: (string)$profile['username'],
    'private' => $isPrivate,
    'visibility' => $visibility,
    'profile_url' => '/u/' . rawurlencode((string)$profile['username']),
];

if (!$isPrivate) {
    $publicData += [
        'is_premium' => (int)$profile['is_premium'] === 1,
        'bio' => (string)($profile['bio'] ?? ''),
        'role' => (string)($profile['ruolo'] ?? 'user'),
        'accent_color' => (string)($profile['accent_color'] ?? ''),
        'profile_views' => (int)($profile['profile_views'] ?? 0),
        'joined_at' => $profile['data_creazione'],
        'last_seen' => $profile['ultimo_accesso'],
        'unique_characters' => (int)$profile['num_personaggi'],
        'total_characters' => (int)$profile['total_personaggi'],
        'achievements' => (int)$profile['num_achievement'],
        'avatar_url' => '/includes/get_pfp.php?id=' . (int)$profile['id'],
        'banner_url' => !empty($profile['profile_banner_type'])
            ? '/includes/get_profile_banner.php?id=' . (int)$profile['id']
            : null,
    ];
}

echo json_encode([
    'ok' => true,
    'found' => true,
    'public' => !$isPrivate,
    'profile' => $publicData,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
