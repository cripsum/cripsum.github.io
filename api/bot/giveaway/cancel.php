<?php
declare(strict_types=1);

/**
 * Annulla un giveaway prima dell'estrazione. Nessun premio viene consegnato.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();
bot_require_method('POST');

if (!auth_table_exists($mysqli, 'discord_giveaways')) {
    bot_json(['ok' => true, 'available' => false]);
}

$actor = bot_require_actor($mysqli);

$body = bot_input();
$giveawayId = (int)($body['giveaway_id'] ?? 0);

if ($giveawayId <= 0) {
    bot_fail('Missing giveaway_id.', 400);
}

$stmt = $mysqli->prepare('SELECT id, title, status, channel_id, message_id FROM discord_giveaways WHERE id = ? LIMIT 1');
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

if ((string)$giveaway['status'] !== 'running') {
    bot_json([
        'ok' => true,
        'available' => true,
        'cancelled' => false,
        'reason' => (string)$giveaway['status'],
    ]);
}

$update = $mysqli->prepare("UPDATE discord_giveaways SET status = 'cancelled', drawn_at = NOW() WHERE id = ? LIMIT 1");
if (!$update) {
    bot_fail('Unable to cancel the giveaway.', 500);
}

$update->bind_param('i', $giveawayId);
$ok = $update->execute();
$update->close();

if (!$ok) {
    bot_fail('Unable to cancel the giveaway.', 500);
}

bot_log_action($mysqli, $actor, 'giveaway_cancel', null, [
    'giveaway_id' => $giveawayId,
    'title' => (string)$giveaway['title'],
]);

bot_json([
    'ok' => true,
    'available' => true,
    'cancelled' => true,
    'giveaway_id' => $giveawayId,
    'title' => (string)$giveaway['title'],
    'channel_id' => (string)$giveaway['channel_id'],
    'message_id' => $giveaway['message_id'],
    'actor' => (string)$actor['username'],
]);
