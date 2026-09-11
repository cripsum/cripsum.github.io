<?php
declare(strict_types=1);

/**
 * Registro delle azioni di moderazione del server Discord.
 *
 * Serve a dare uno storico a /lookup e a ricostruire chi ha fatto cosa. Non
 * modifica nulla del sito: e' solo un diario.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();
bot_require_method('POST');

if (!auth_table_exists($mysqli, 'discord_mod_actions')) {
    bot_json(['ok' => true, 'available' => false]);
}

const MOD_ACTIONS = [
    'ban', 'unban', 'kick', 'timeout', 'untimeout', 'warn', 'unwarn',
    'purge', 'lock', 'unlock', 'slowmode',
];

$body = bot_input();
$guildId = bot_snowflake('guild_id');
$action = bot_text('action', 20, true);

if (!in_array($action, MOD_ACTIONS, true)) {
    bot_fail('Unknown moderation action.', 400);
}

$targetDiscordId = bot_discord_id('target_discord_id', false);
$targetChannelId = bot_snowflake('target_channel_id', false);
$moderatorDiscordId = bot_discord_id('moderator_discord_id');
$reason = bot_text('reason', 500);
$duration = isset($body['duration_seconds']) ? (int)$body['duration_seconds'] : null;
$details = isset($body['details']) && is_array($body['details'])
    ? json_encode($body['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : null;

$moderator = bot_find_user($mysqli, $moderatorDiscordId);
$moderatorUserId = $moderator ? (int)$moderator['id'] : null;

$stmt = $mysqli->prepare(
    'INSERT INTO discord_mod_actions
        (guild_id, action, target_discord_id, target_channel_id, moderator_discord_id,
         moderator_user_id, reason, duration_seconds, details)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

if (!$stmt) {
    bot_fail('Unable to store the moderation action.', 500);
}

$targetDiscordIdOrNull = $targetDiscordId !== '' ? $targetDiscordId : null;
$targetChannelIdOrNull = $targetChannelId !== '' ? $targetChannelId : null;
$reasonOrNull = $reason !== '' ? $reason : null;

$stmt->bind_param(
    'sssssisis',
    $guildId,
    $action,
    $targetDiscordIdOrNull,
    $targetChannelIdOrNull,
    $moderatorDiscordId,
    $moderatorUserId,
    $reasonOrNull,
    $duration,
    $details
);

if (!$stmt->execute()) {
    $stmt->close();
    bot_fail('Unable to store the moderation action.', 500);
}

$id = $mysqli->insert_id;
$stmt->close();

bot_json(['ok' => true, 'available' => true, 'id' => (int)$id]);
