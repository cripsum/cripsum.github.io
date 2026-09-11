<?php
declare(strict_types=1);

/**
 * Partecipazione a un giveaway.
 *
 * Serve l'account Cripsum collegato: e' l'unico modo per consegnare il premio
 * in automatico, ed evita che vinca un account che non esiste.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();
bot_require_method('POST');

if (!auth_table_exists($mysqli, 'discord_giveaways')) {
    bot_json(['ok' => true, 'available' => false]);
}

$body = bot_input();
$giveawayId = (int)($body['giveaway_id'] ?? 0);
$discordId = bot_discord_id('discord_id');

if ($giveawayId <= 0) {
    bot_fail('Missing giveaway_id.', 400);
}

$stmt = $mysqli->prepare(
    'SELECT id, status, ends_at, title FROM discord_giveaways WHERE id = ? LIMIT 1'
);

if (!$stmt) {
    bot_fail('Unable to read the giveaway.', 500);
}

$stmt->bind_param('i', $giveawayId);
$stmt->execute();
$giveaway = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$giveaway) {
    bot_fail('Giveaway not found.', 404);
}

if ((string)$giveaway['status'] !== 'running' || strtotime((string)$giveaway['ends_at']) <= time()) {
    bot_json(['ok' => true, 'available' => true, 'entered' => false, 'reason' => 'closed']);
}

$user = bot_find_user($mysqli, $discordId);
if (!$user) {
    bot_json(['ok' => true, 'available' => true, 'entered' => false, 'reason' => 'not_linked']);
}

if ((int)($user['isBannato'] ?? 0) === 1) {
    bot_json(['ok' => true, 'available' => true, 'entered' => false, 'reason' => 'banned']);
}

$userId = (int)$user['id'];

// Un account, una partecipazione: la chiave primaria impedisce i doppioni per
// Discord ID, questo controllo impedisce due Discord sullo stesso account.
$stmtDup = $mysqli->prepare(
    'SELECT discord_user_id FROM discord_giveaway_entries WHERE giveaway_id = ? AND user_id = ? LIMIT 1'
);

if ($stmtDup) {
    $stmtDup->bind_param('ii', $giveawayId, $userId);
    $stmtDup->execute();
    $existing = $stmtDup->get_result()->fetch_assoc();
    $stmtDup->close();

    if ($existing) {
        bot_json([
            'ok' => true,
            'available' => true,
            'entered' => false,
            'reason' => (string)$existing['discord_user_id'] === $discordId ? 'already_entered' : 'account_already_entered',
            'username' => (string)$user['username'],
        ]);
    }
}

$stmtInsert = $mysqli->prepare(
    'INSERT INTO discord_giveaway_entries (giveaway_id, discord_user_id, user_id) VALUES (?, ?, ?)'
);

if (!$stmtInsert) {
    bot_fail('Unable to register the entry.', 500);
}

$stmtInsert->bind_param('isi', $giveawayId, $discordId, $userId);

if (!$stmtInsert->execute()) {
    $stmtInsert->close();
    bot_json(['ok' => true, 'available' => true, 'entered' => false, 'reason' => 'already_entered']);
}

$stmtInsert->close();

$total = 0;
$stmtCount = $mysqli->prepare('SELECT COUNT(*) AS total FROM discord_giveaway_entries WHERE giveaway_id = ?');
if ($stmtCount) {
    $stmtCount->bind_param('i', $giveawayId);
    $stmtCount->execute();
    $total = (int)($stmtCount->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtCount->close();
}

bot_json([
    'ok' => true,
    'available' => true,
    'entered' => true,
    'username' => (string)$user['username'],
    'entries' => $total,
    'title' => (string)$giveaway['title'],
]);
