<?php
declare(strict_types=1);

/**
 * Modelli di messaggio del bot: benvenuto, ringraziamento per i boost ed
 * embed liberi.
 *
 * GET  → legge un modello (o l'elenco di un tipo).
 * POST → salva o cancella.
 *
 * Salvare o cancellare un modello vuol dire decidere cosa pubblica il bot nel
 * server, quindi serve un account Cripsum admin/owner collegato. La lettura no:
 * la fa il bot da solo ogni volta che entra qualcuno.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();

if (!auth_table_exists($mysqli, 'discord_message_templates')) {
    bot_json(['ok' => true, 'available' => false, 'templates' => []]);
}

const TEMPLATE_KINDS = ['welcome', 'boost', 'premium', 'custom'];

$guildId = bot_snowflake('guild_id');
$kind = bot_text('kind', 20, false, 'custom');

if (!in_array($kind, TEMPLATE_KINDS, true)) {
    bot_fail('Unknown template kind.', 400);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $name = bot_text('name', 60);

    if ($name !== '') {
        $stmt = $mysqli->prepare(
            'SELECT id, kind, name, enabled, channel_id, content, embed, updated_at
             FROM discord_message_templates
             WHERE guild_id = ? AND kind = ? AND name = ? LIMIT 1'
        );

        if (!$stmt) {
            bot_fail('Unable to read the template.', 500);
        }

        $stmt->bind_param('sss', $guildId, $kind, $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        bot_json([
            'ok' => true,
            'available' => true,
            'found' => (bool)$row,
            'template' => $row ? [
                'kind' => (string)$row['kind'],
                'name' => (string)$row['name'],
                'enabled' => (int)$row['enabled'] === 1,
                'channel_id' => $row['channel_id'],
                'content' => $row['content'],
                'embed' => $row['embed'] ? json_decode((string)$row['embed'], true) : null,
                'updated_at' => $row['updated_at'],
            ] : null,
        ]);
    }

    $stmt = $mysqli->prepare(
        'SELECT kind, name, enabled, channel_id, updated_at
         FROM discord_message_templates
         WHERE guild_id = ? AND kind = ?
         ORDER BY name ASC LIMIT 50'
    );

    if (!$stmt) {
        bot_fail('Unable to list the templates.', 500);
    }

    $stmt->bind_param('ss', $guildId, $kind);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    bot_json([
        'ok' => true,
        'available' => true,
        'templates' => array_map(static function (array $row): array {
            return [
                'kind' => (string)$row['kind'],
                'name' => (string)$row['name'],
                'enabled' => (int)$row['enabled'] === 1,
                'channel_id' => $row['channel_id'],
                'updated_at' => $row['updated_at'],
            ];
        }, $rows),
    ]);
}

bot_require_method('POST');
$actor = bot_require_actor($mysqli);

$body = bot_input();
$action = bot_text('action', 20, false, 'save');
$name = bot_text('name', 60, false, 'default');

if ($action === 'delete') {
    $stmt = $mysqli->prepare(
        'DELETE FROM discord_message_templates WHERE guild_id = ? AND kind = ? AND name = ? LIMIT 1'
    );

    if (!$stmt) {
        bot_fail('Unable to delete the template.', 500);
    }

    $stmt->bind_param('sss', $guildId, $kind, $name);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();

    bot_log_action($mysqli, $actor, 'discord_template_delete', null, ['kind' => $kind, 'name' => $name]);
    bot_json(['ok' => true, 'available' => true, 'deleted' => $deleted > 0]);
}

$enabled = array_key_exists('enabled', $body) ? (int)(bool)$body['enabled'] : 1;
$channelId = bot_snowflake('channel_id', false);
$content = bot_text('content', 2000);
$embed = $body['embed'] ?? null;

if ($embed !== null && !is_array($embed)) {
    bot_fail('The embed must be an object.', 400);
}

// Un modello senza niente da mostrare non serve a nulla.
if ($content === '' && !$embed) {
    bot_fail('A template needs some content or an embed.', 400);
}

$embedJson = $embed ? json_encode($embed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
if ($embedJson !== null && strlen($embedJson) > 60000) {
    bot_fail('The embed is too large.', 400);
}

$channelOrNull = $channelId !== '' ? $channelId : null;
$contentOrNull = $content !== '' ? $content : null;
$actorDiscordId = (string)$actor['discord_id'];

$stmt = $mysqli->prepare(
    'INSERT INTO discord_message_templates
        (guild_id, kind, name, enabled, channel_id, content, embed, created_by_discord_id)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        enabled = VALUES(enabled),
        channel_id = VALUES(channel_id),
        content = VALUES(content),
        embed = VALUES(embed)'
);

if (!$stmt) {
    bot_fail('Unable to save the template.', 500);
}

$stmt->bind_param('sssissss', $guildId, $kind, $name, $enabled, $channelOrNull, $contentOrNull, $embedJson, $actorDiscordId);

if (!$stmt->execute()) {
    $stmt->close();
    bot_fail('Unable to save the template.', 500);
}

$stmt->close();

bot_log_action($mysqli, $actor, 'discord_template_save', null, [
    'kind' => $kind,
    'name' => $name,
    'enabled' => (bool)$enabled,
    'channel_id' => $channelOrNull,
]);

bot_json([
    'ok' => true,
    'available' => true,
    'saved' => true,
    'kind' => $kind,
    'name' => $name,
    'enabled' => (bool)$enabled,
    'channel_id' => $channelOrNull,
]);
