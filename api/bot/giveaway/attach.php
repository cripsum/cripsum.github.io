<?php
declare(strict_types=1);

/**
 * Collega il messaggio Discord al giveaway appena creato, cosi' il bot sa
 * quale annuncio aggiornare quando l'estrazione finisce.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();
bot_require_method('POST');

if (!auth_table_exists($mysqli, 'discord_giveaways')) {
    bot_json(['ok' => true, 'available' => false]);
}

$body = bot_input();
$giveawayId = (int)($body['giveaway_id'] ?? 0);
$messageId = bot_snowflake('message_id');

if ($giveawayId <= 0) {
    bot_fail('Missing giveaway_id.', 400);
}

// Stessa regola di bot_require_same_guild(), scritta nella query: il messaggio
// si collega solo a un giveaway del server da cui arriva la richiesta.
$guildId = bot_snowflake('guild_id', false);

$stmt = $mysqli->prepare(
    'UPDATE discord_giveaways SET message_id = ? WHERE id = ?'
    . ($guildId !== '' ? ' AND guild_id = ?' : '')
    . ' LIMIT 1'
);
if (!$stmt) {
    bot_fail('Unable to attach the message.', 500);
}

if ($guildId !== '') {
    $stmt->bind_param('sis', $messageId, $giveawayId, $guildId);
} else {
    $stmt->bind_param('si', $messageId, $giveawayId);
}
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    bot_fail('Unable to attach the message.', 500);
}

bot_json(['ok' => true, 'available' => true, 'giveaway_id' => $giveawayId, 'message_id' => $messageId]);
