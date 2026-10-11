<?php
declare(strict_types=1);

/**
 * Messaggi del bot di un server.
 *
 * GET  → tutti i messaggi salvati, i testi di partenza e dove sono stati
 *        pubblicati quelli fissi.
 * POST → salva, cancella, oppure fa provare o pubblicare un messaggio al bot.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/botpanel/templates.php';

$user = botpanel_require_user($mysqli);
$guildId = botpanel_request_guild();

if (!auth_table_exists($mysqli, 'discord_message_templates')) {
    botpanel_fail('not_installed', 'Manca la tabella dei messaggi.', 503);
}

$hasSent = auth_table_exists($mysqli, 'discord_sent_messages');

/**
 * Un messaggio salvato, o null.
 *
 * @return array<string, mixed>|null
 */
$findTemplate = static function (string $kind, string $name) use ($mysqli, $guildId): ?array {
    $stmt = $mysqli->prepare(
        'SELECT kind, name, enabled, channel_id, content, embed
         FROM discord_message_templates WHERE guild_id = ? AND kind = ? AND name = ? LIMIT 1'
    );

    if (!$stmt) {
        botpanel_fail('database', 'Impossibile leggere il messaggio.', 500);
    }

    $stmt->bind_param('sss', $guildId, $kind, $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'kind' => (string)$row['kind'],
        'name' => (string)$row['name'],
        'enabled' => (int)$row['enabled'] === 1,
        'channel_id' => (string)($row['channel_id'] ?? ''),
        'content' => (string)($row['content'] ?? ''),
        'embed' => $row['embed'] ? json_decode((string)$row['embed'], true) : null,
    ];
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $access = botpanel_access($user, $guildId);
    cripsum_release_session();

    $stmt = $mysqli->prepare(
        'SELECT kind, name, enabled, channel_id, content, embed
         FROM discord_message_templates WHERE guild_id = ? ORDER BY kind ASC, name ASC LIMIT 200'
    );

    if (!$stmt) {
        botpanel_fail('database', 'Impossibile leggere i messaggi.', 500);
    }

    $stmt->bind_param('s', $guildId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $templates = [];
    foreach ($rows as $row) {
        if ((string)$row['kind'] === 'premium' && !$access['is_home']) {
            continue;
        }

        $templates[] = [
            'kind' => (string)$row['kind'],
            'name' => (string)$row['name'],
            'enabled' => (int)$row['enabled'] === 1,
            'channel_id' => (string)($row['channel_id'] ?? ''),
            'content' => (string)($row['content'] ?? ''),
            'embed' => $row['embed'] ? json_decode((string)$row['embed'], true) : null,
        ];
    }

    // Dove e' stato pubblicato ogni messaggio fisso: serve ad aggiornarlo sul
    // posto invece di ripubblicarlo.
    $sent = [];
    if ($hasSent) {
        $stmt = $mysqli->prepare(
            'SELECT template_name, channel_id, message_id FROM discord_sent_messages
             WHERE guild_id = ? ORDER BY id DESC LIMIT 200'
        );

        if ($stmt) {
            $stmt->bind_param('s', $guildId);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $name = (string)$row['template_name'];
                if (!isset($sent[$name])) {
                    $sent[$name] = ['channel_id' => (string)$row['channel_id'], 'message_id' => (string)$row['message_id']];
                }
            }
            $stmt->close();
        }
    }

    $defaults = botpanel_bot('/v1/panel/template/defaults', ['guild_id' => $guildId], 5);

    botpanel_json([
        'ok' => true,
        'templates' => $templates,
        'defaults' => is_array($defaults['defaults'] ?? null) ? $defaults['defaults'] : (object)[],
        'sent' => (object)$sent,
        'custom_max' => BOTPANEL_TEMPLATE_CUSTOM_MAX,
    ]);
}

botpanel_require_write();
botpanel_rate('save', 30, 60);

$input = botpanel_input();
$access = botpanel_access($user, $guildId, true);
cripsum_release_session();
botpanel_require_home_admin($user, $access);

$action = (string)($input['action'] ?? 'save');
$kind = (string)($input['kind'] ?? '');

if (!in_array($kind, BOTPANEL_TEMPLATE_KINDS, true) || ($kind === 'premium' && !$access['is_home'])) {
    botpanel_fail('bad_kind', 'Tipo di messaggio sconosciuto.', 400);
}

$name = $kind === 'custom' ? botpanel_template_name($input['name'] ?? '') : 'default';
if ($name === null) {
    botpanel_fail('invalid', 'Nome non valido.', 422, ['errors' => ['name' => 'name']]);
}

$existing = $findTemplate($kind, $name);

if ($action === 'delete') {
    $stmt = $mysqli->prepare('DELETE FROM discord_message_templates WHERE guild_id = ? AND kind = ? AND name = ? LIMIT 1');
    if (!$stmt) {
        botpanel_fail('database', 'Impossibile cancellare.', 500);
    }

    $stmt->bind_param('sss', $guildId, $kind, $name);
    $stmt->execute();
    $stmt->close();

    if ($existing !== null) {
        botpanel_audit($mysqli, $guildId, $user, 'messages', 'template_delete', ['kind' => $kind, 'name' => $name] + $existing, null);
    }

    botpanel_json(['ok' => true, 'deleted' => $existing !== null, 'applied' => botpanel_notify_reload($guildId)]);
}

if ($action === 'send') {
    botpanel_rate('send', 10, 60);

    $mode = (string)($input['mode'] ?? '');
    if (!in_array($mode, ['test', 'publish', 'update'], true) || $existing === null) {
        botpanel_fail('not_saved', 'Salva il messaggio prima di inviarlo.', 409);
    }

    $payload = ['guild_id' => $guildId, 'kind' => $kind, 'name' => $name, 'mode' => $mode, 'user_id' => $user['discord_id']];

    if ($mode === 'update') {
        // Il messaggio da riscrivere e' l'ultimo pubblicato con quel nome in
        // questo server: l'ID non arriva dal browser.
        $stmt = $hasSent ? $mysqli->prepare(
            'SELECT channel_id, message_id FROM discord_sent_messages
             WHERE guild_id = ? AND template_name = ? ORDER BY id DESC LIMIT 1'
        ) : null;

        $last = null;
        if ($stmt) {
            $stmt->bind_param('ss', $guildId, $name);
            $stmt->execute();
            $last = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if (!$last) {
            botpanel_fail('never_published', 'Questo messaggio non è mai stato pubblicato.', 409);
        }

        $payload['channel_id'] = (string)$last['channel_id'];
        $payload['message_id'] = (string)$last['message_id'];
    } elseif ($mode === 'publish') {
        $channelId = is_string($input['channel_id'] ?? null) && $input['channel_id'] !== ''
            ? $input['channel_id']
            : $existing['channel_id'];

        // Si pubblica solo in un canale di questo server in cui il bot puo'
        // scrivere: lo dice l'elenco del bot, non la richiesta.
        $ctx = botpanel_validation_context(botpanel_meta($guildId), $access, true);
        $channel = $ctx['channels'][$channelId] ?? null;

        if (!$channel || !in_array($channel['kind'] ?? '', ['text', 'announcement'], true) || empty($channel['bot']['send'])) {
            botpanel_fail('invalid', 'Scegli un canale in cui Poppy può scrivere.', 422, ['errors' => ['publish_channel' => 'channel']]);
        }

        $payload['channel_id'] = (string)$channelId;
    }

    $response = botpanel_bot('/v1/panel/template/send', $payload, 12);

    if ($response === null || empty($response['success'])) {
        botpanel_fail('send_failed', 'Poppy non è riuscito a inviarlo.', 502, ['reason' => (string)($response['error'] ?? 'unreachable')]);
    }

    if ($mode === 'publish' && $hasSent && botpanel_is_snowflake($response['message_id'] ?? null)) {
        $stmt = $mysqli->prepare(
            'INSERT INTO discord_sent_messages (guild_id, channel_id, message_id, template_name, sent_by_discord_id)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE template_name = VALUES(template_name), channel_id = VALUES(channel_id)'
        );

        if ($stmt) {
            $channelId = (string)$response['channel_id'];
            $messageId = (string)$response['message_id'];
            $discordId = $user['discord_id'];
            $stmt->bind_param('sssss', $guildId, $channelId, $messageId, $name, $discordId);
            $stmt->execute();
            $stmt->close();
        }

        botpanel_audit($mysqli, $guildId, $user, 'messages', 'template_publish', null, ['name' => $name, 'channel_id' => (string)$response['channel_id']]);
    }

    $url = (string)($response['url'] ?? '');

    botpanel_json([
        'ok' => true,
        'mode' => $mode,
        'dm' => !empty($response['dm']),
        'channel_id' => (string)($response['channel_id'] ?? ''),
        'message_id' => (string)($response['message_id'] ?? ''),
        'url' => str_starts_with($url, 'https://discord.com/channels/') ? $url : null,
    ]);
}

if ($action !== 'save') {
    botpanel_fail('bad_action', 'Azione sconosciuta.', 400);
}

if ($kind === 'custom' && $existing === null) {
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM discord_message_templates WHERE guild_id = ? AND kind = 'custom'");
    if ($stmt) {
        $stmt->bind_param('s', $guildId);
        $stmt->execute();
        $total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt->close();

        if ($total >= BOTPANEL_TEMPLATE_CUSTOM_MAX) {
            botpanel_fail('invalid', 'Hai raggiunto il numero massimo di messaggi salvati.', 422, ['errors' => ['name' => 'too_many']]);
        }
    }
}

$check = botpanel_validate_template(
    $kind,
    $input['template'] ?? [],
    botpanel_validation_context(botpanel_meta($guildId), $access, true)
);

if (!$check['ok']) {
    botpanel_fail('invalid', 'Alcuni valori non vanno bene.', 422, ['errors' => $check['errors']]);
}

$enabledInt = $check['enabled'] ? 1 : 0;
$channelOrNull = $check['channel_id'] !== '' ? $check['channel_id'] : null;
$contentOrNull = $check['content'] !== '' ? $check['content'] : null;
$embedJson = $check['embed'] !== null
    ? json_encode($check['embed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : null;
$discordId = $user['discord_id'];

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
    botpanel_fail('database', 'Impossibile salvare.', 500);
}

$stmt->bind_param('sssissss', $guildId, $kind, $name, $enabledInt, $channelOrNull, $contentOrNull, $embedJson, $discordId);

if (!$stmt->execute()) {
    $stmt->close();
    botpanel_fail('database', 'Impossibile salvare.', 500);
}

$stmt->close();

$saved = [
    'kind' => $kind,
    'name' => $name,
    'enabled' => $check['enabled'],
    'channel_id' => $check['channel_id'],
    'content' => $check['content'],
    'embed' => $check['embed'],
];

botpanel_audit($mysqli, $guildId, $user, 'messages', 'template_save', $existing, $saved);

botpanel_json([
    'ok' => true,
    'template' => $saved,
    'applied' => botpanel_notify_reload($guildId),
]);
