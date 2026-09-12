<?php
declare(strict_types=1);

/**
 * Messaggi gia' pubblicati a partire da un modello.
 *
 * Serve a ritrovare il messaggio delle regole (o di qualsiasi altro annuncio
 * fisso) quando si modifica il modello, per aggiornarlo invece di ripubblicarlo.
 *
 * GET  → ultimo messaggio pubblicato con quel nome.
 * POST → registra un messaggio appena pubblicato, o ne dimentica uno.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();

if (!auth_table_exists($mysqli, 'discord_sent_messages')) {
    bot_json(['ok' => true, 'available' => false]);
}

$guildId = bot_snowflake('guild_id');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $name = bot_text('name', 60, true);

    $stmt = $mysqli->prepare(
        'SELECT channel_id, message_id, created_at
         FROM discord_sent_messages
         WHERE guild_id = ? AND template_name = ?
         ORDER BY id DESC LIMIT 1'
    );

    if (!$stmt) {
        bot_fail('Unable to read the sent messages.', 500);
    }

    $stmt->bind_param('ss', $guildId, $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    bot_json([
        'ok' => true,
        'available' => true,
        'found' => (bool)$row,
        'message' => $row ?: null,
    ]);
}

bot_require_method('POST');
$actor = bot_require_actor($mysqli);

$body = bot_input();
$action = bot_text('action', 20, false, 'record');
$messageId = bot_snowflake('message_id');

if ($action === 'forget') {
    $stmt = $mysqli->prepare('DELETE FROM discord_sent_messages WHERE guild_id = ? AND message_id = ? LIMIT 1');
    if (!$stmt) {
        bot_fail('Unable to forget the message.', 500);
    }

    $stmt->bind_param('ss', $guildId, $messageId);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();

    bot_json(['ok' => true, 'available' => true, 'forgotten' => $deleted > 0]);
}

$channelId = bot_snowflake('channel_id');
$name = bot_text('name', 60, true);
$actorDiscordId = (string)$actor['discord_id'];

$stmt = $mysqli->prepare(
    'INSERT INTO discord_sent_messages (guild_id, channel_id, message_id, template_name, sent_by_discord_id)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE template_name = VALUES(template_name), channel_id = VALUES(channel_id)'
);

if (!$stmt) {
    bot_fail('Unable to record the message.', 500);
}

$stmt->bind_param('sssss', $guildId, $channelId, $messageId, $name, $actorDiscordId);

if (!$stmt->execute()) {
    $stmt->close();
    bot_fail('Unable to record the message.', 500);
}

$stmt->close();

bot_json(['ok' => true, 'available' => true, 'recorded' => true, 'message_id' => $messageId]);
