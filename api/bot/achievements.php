<?php
declare(strict_types=1);

/**
 * Achievement sbloccati da un utente, per il comando /achievements del bot.
 */

require_once __DIR__ . '/bootstrap.php';

bot_require_key();
bot_require_method('GET');

$requesterDiscordId = bot_discord_id('requester_discord_id');
$targetDiscordId = bot_discord_id('target_discord_id', false) ?: $requesterDiscordId;

$user = bot_find_user($mysqli, $targetDiscordId);
if (!$user) {
    bot_json(['ok' => true, 'linked' => false]);
}

$isSelf = hash_equals($requesterDiscordId, $targetDiscordId);
if (bot_profile_is_private($user, $isSelf)) {
    bot_json([
        'ok' => true,
        'linked' => true,
        'private' => true,
        'username' => (string)$user['username'],
    ]);
}

// Lo schema si verifica a runtime: se le tabelle non ci sono la funzione si
// disattiva in silenzio invece di produrre query rotte.
if (!auth_table_exists($mysqli, 'achievement') || !auth_table_exists($mysqli, 'utenti_achievement')) {
    bot_json([
        'ok' => true,
        'linked' => true,
        'available' => false,
        'username' => (string)$user['username'],
    ]);
}

$userId = (int)$user['id'];

// Il bot parla inglese: si usano le colonne _en dove esistono.
$nameSelect = auth_column_exists($mysqli, 'achievement', 'nome_en')
    ? "COALESCE(NULLIF(a.nome_en, ''), a.nome)"
    : 'a.nome';
$descriptionSelect = auth_column_exists($mysqli, 'achievement', 'descrizione_en')
    ? "COALESCE(NULLIF(a.descrizione_en, ''), a.descrizione)"
    : 'a.descrizione';

$stmt = $mysqli->prepare(
    'SELECT a.id, ' . $nameSelect . ' AS nome, ' . $descriptionSelect . ' AS descrizione,
            a.punti, a.img_url, ua.data
     FROM utenti_achievement ua
     JOIN achievement a ON a.id = ua.achievement_id
     WHERE ua.utente_id = ?
     ORDER BY ua.data DESC, a.nome ASC
     LIMIT 200'
);

if (!$stmt) {
    bot_fail('Unable to prepare the achievements query.', 500);
}

$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$unlocked = [];
$points = 0;

while ($row = $result->fetch_assoc()) {
    $points += (int)($row['punti'] ?? 0);
    $unlocked[] = [
        'id' => (int)$row['id'],
        'name' => (string)($row['nome'] ?? ''),
        'description' => (string)($row['descrizione'] ?? ''),
        'points' => (int)($row['punti'] ?? 0),
        'image_url' => $row['img_url'] ?? null,
        'unlocked_at' => $row['data'] ?? null,
    ];
}

$stmt->close();

$catalogTotal = 0;
$catalogResult = $mysqli->query('SELECT COUNT(*) AS total FROM achievement');
if ($catalogResult) {
    $catalogTotal = (int)($catalogResult->fetch_assoc()['total'] ?? 0);
    $catalogResult->free();
}

bot_json([
    'ok' => true,
    'linked' => true,
    'available' => true,
    'private' => false,
    'is_self' => $isSelf,
    'username' => (string)$user['username'],
    'display_name' => bot_display_name($user),
    'is_premium' => (int)($user['is_premium'] ?? 0) === 1,
    'totals' => [
        'unlocked' => count($unlocked),
        'catalog' => $catalogTotal,
        'points' => $points,
        'truncated' => count($unlocked) >= 200,
    ],
    'achievements' => $unlocked,
    'profile_url' => '/u/' . rawurlencode((string)$user['username']),
]);
