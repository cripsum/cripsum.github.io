<?php
declare(strict_types=1);

/**
 * Registro delle stanze vocali temporanee create dal bot.
 *
 * Serve a una cosa sola: dopo un riavvio il bot deve sapere quali canali
 * vocali ha creato lui, per cancellare quelli rimasti vuoti senza toccare i
 * canali veri del server.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();
bot_require_method('POST');

if (!auth_table_exists($mysqli, 'discord_voice_rooms')) {
    bot_json(['ok' => true, 'available' => false, 'rooms' => []]);
}

$action = bot_text('action', 10, true);

switch ($action) {
    case 'list': {
        $rows = $mysqli->query('SELECT channel_id, guild_id, owner_discord_id FROM discord_voice_rooms LIMIT 2000');

        bot_json(['ok' => true, 'available' => true, 'rooms' => $rows ? $rows->fetch_all(MYSQLI_ASSOC) : []]);
    }

    case 'add': {
        $channelId = bot_snowflake('channel_id');
        $guildId = bot_snowflake('guild_id');
        $ownerId = bot_discord_id('owner_discord_id');

        $stmt = $mysqli->prepare(
            'INSERT IGNORE INTO discord_voice_rooms (channel_id, guild_id, owner_discord_id) VALUES (?, ?, ?)'
        );
        $stmt->bind_param('sss', $channelId, $guildId, $ownerId);
        $stmt->execute();
        $stmt->close();

        bot_json(['ok' => true]);
    }

    case 'remove': {
        $channelId = bot_snowflake('channel_id');

        $stmt = $mysqli->prepare('DELETE FROM discord_voice_rooms WHERE channel_id = ?');
        $stmt->bind_param('s', $channelId);
        $stmt->execute();
        $stmt->close();

        bot_json(['ok' => true]);
    }

    default:
        bot_fail('Unknown action.', 400);
}
