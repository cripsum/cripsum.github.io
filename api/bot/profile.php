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

$requesterDiscordId = trim((string)($_GET['requester_discord_id'] ?? ''));
$targetDiscordId = trim((string)($_GET['target_discord_id'] ?? $requesterDiscordId));

if (!preg_match('/^\d{15,25}$/', $targetDiscordId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid Discord ID.']);
    exit;
}

$stmt = $mysqli->prepare("
    SELECT
        u.id, u.username, u.display_name, u.bio, u.ruolo, u.is_premium,
        u.profile_visibility, u.discord_id, u.discord_avatar, u.discord_use_avatar,
        u.profile_banner_type, u.accent_color, u.profile_views, u.data_creazione,
        u.ultimo_accesso, u.custom_alias,
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
    WHERE u.discord_id = ? LIMIT 1
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Unable to prepare profile query.']);
    exit;
}

$stmt->bind_param('s', $targetDiscordId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
    echo json_encode(['ok' => true, 'linked' => false]);
    exit;
}

$isSelf = $requesterDiscordId !== '' && hash_equals($requesterDiscordId, $targetDiscordId);
$visibility = (string)($profile['profile_visibility'] ?? 'public');
$isPrivate = !$isSelf && $visibility !== 'public';
$avatarUrl = null;

if ((int)$profile['discord_use_avatar'] === 1 && !empty($profile['discord_avatar'])) {
    $avatarHash = (string)$profile['discord_avatar'];
    $extension = str_starts_with($avatarHash, 'a_') ? 'gif' : 'png';
    $avatarUrl = 'https://cdn.discordapp.com/avatars/' . rawurlencode($targetDiscordId)
        . '/' . rawurlencode($avatarHash) . '.' . $extension . '?size=512';
} else {
    $avatarUrl = '/includes/get_pfp.php?id=' . (int)$profile['id'];
}

$publicData = [
    'id' => (int)$profile['id'],
    'username' => (string)$profile['username'],
    'display_name' => trim((string)($profile['display_name'] ?? '')) ?: (string)$profile['username'],
    'is_premium' => (int)$profile['is_premium'] === 1,
    'private' => $isPrivate,
    'visibility' => $visibility,
];

if (!$isPrivate) {
    $publicData += [
        'bio' => (string)($profile['bio'] ?? ''),
        'role' => (string)($profile['ruolo'] ?? 'user'),
        'accent_color' => (string)($profile['accent_color'] ?? ''),
        'profile_views' => (int)($profile['profile_views'] ?? 0),
        'joined_at' => $profile['data_creazione'],
        'last_seen' => $profile['ultimo_accesso'],
        'unique_characters' => (int)$profile['num_personaggi'],
        'total_characters' => (int)$profile['total_personaggi'],
        'achievements' => (int)$profile['num_achievement'],
        'avatar_url' => $avatarUrl,
        'banner_url' => !empty($profile['profile_banner_type'])
            ? '/includes/get_profile_banner.php?id=' . (int)$profile['id']
            : null,
        'profile_url' => '/u/' . rawurlencode((string)$profile['username']),
    ];
}

echo json_encode([
    'ok' => true,
    'linked' => true,
    'profile' => $publicData,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
