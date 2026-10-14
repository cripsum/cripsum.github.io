<?php
declare(strict_types=1);

/**
 * Messaggi programmati di un server.
 *
 * GET  ?guild=…                                              l'elenco
 * POST {guild, action: add, name, channel_id, when, repeat, tz}
 * POST {guild, action: delete, id}
 *
 * Si programma un messaggio salvato (Messaggi → salvati). `when` sono i
 * secondi Unix calcolati dal browser dall'ora scelta, `tz` il fuso del
 * browser: servono a ripetere "ogni giorno alle 18" alle 18 vere.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/botpanel/schedule.php';

const SCHEDULE_MAX_PER_GUILD = 25;

$user = botpanel_require_user($mysqli);
$guildId = botpanel_request_guild();

if (!auth_table_exists($mysqli, 'discord_scheduled_posts')) {
    botpanel_json(['ok' => true, 'available' => false, 'schedules' => []]);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ['access' => $access, 'input' => $input] = botpanel_begin_action($user, $guildId, 'save', 30);
    $action = (string)($input['action'] ?? '');

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);

        $stmt = $mysqli->prepare('DELETE FROM discord_scheduled_posts WHERE id = ? AND guild_id = ?');
        $stmt->bind_param('is', $id, $guildId);
        $stmt->execute();
        $removed = $stmt->affected_rows > 0;
        $stmt->close();

        if ($removed) {
            botpanel_audit($mysqli, $guildId, $user, 'messages', 'schedule_delete', ['id' => $id], null);
        }

        botpanel_json(['ok' => true, 'removed' => $removed, 'applied' => botpanel_notify_reload($guildId)]);
    }

    if ($action !== 'add') {
        botpanel_fail('bad_action', 'Azione sconosciuta.', 400);
    }

    $saved = botpanel_settings($mysqli, $guildId)['messages'] ?? null;
    if ($saved === null || !$saved['enabled']) {
        botpanel_fail('module_off', 'Accendi e salva il modulo Messaggi prima di programmare.', 409);
    }

    $errors = [];
    $name = (string)($input['name'] ?? '');

    // Un messaggio salvato di questo server, e nient'altro.
    $stmt = $mysqli->prepare("SELECT 1 FROM discord_message_templates WHERE guild_id = ? AND kind = 'custom' AND name = ? LIMIT 1");
    $stmt->bind_param('ss', $guildId, $name);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();

    if (!$exists) {
        botpanel_fail('not_saved', 'Salva il messaggio prima di programmarlo.', 409);
    }

    $ctx = botpanel_validation_context(botpanel_meta($guildId), $access, true);
    $channelId = (string)($input['channel_id'] ?? '');
    $channel = $ctx['channels'][$channelId] ?? null;

    if (!$channel || !in_array($channel['kind'] ?? '', ['text', 'announcement'], true) || empty($channel['bot']['send']) || empty($channel['bot']['embed'])) {
        $errors['schedule_channel'] = 'channel';
    }

    $when = (int)($input['when'] ?? 0);
    if ($when < time() + 30 || $when > time() + 366 * 86400) {
        $errors['schedule_when'] = 'when';
    }

    $repeat = (string)($input['repeat'] ?? 'none');
    if (!in_array($repeat, BOTPANEL_SCHEDULE_REPEATS, true)) {
        $errors['schedule_repeat'] = 'choice';
    }

    if ($errors !== []) {
        botpanel_fail('invalid', 'Alcuni valori non vanno bene.', 422, ['errors' => $errors]);
    }

    $stmt = $mysqli->prepare('SELECT COUNT(*) FROM discord_scheduled_posts WHERE guild_id = ? AND enabled = 1');
    $stmt->bind_param('s', $guildId);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();

    if ($count >= SCHEDULE_MAX_PER_GUILD) {
        botpanel_fail('invalid', 'Hai già il massimo di messaggi programmati.', 422, ['errors' => ['schedule_when' => 'too_many']]);
    }

    $tz = botpanel_schedule_tz((string)($input['tz'] ?? ''));
    $now = time();
    $discordId = $user['discord_id'];

    $stmt = $mysqli->prepare(
        'INSERT INTO discord_scheduled_posts (guild_id, template_name, channel_id, next_unix, repeat_kind, tz, created_by_discord_id, created_unix)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('sssisssi', $guildId, $name, $channelId, $when, $repeat, $tz, $discordId, $now);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();

    botpanel_audit($mysqli, $guildId, $user, 'messages', 'schedule_add', null, [
        'id' => $id, 'name' => $name, 'channel_id' => $channelId, 'when' => $when, 'repeat' => $repeat,
    ]);

    botpanel_json(['ok' => true, 'id' => $id, 'applied' => botpanel_notify_reload($guildId)]);
}

botpanel_access($user, $guildId);
cripsum_release_session();

$stmt = $mysqli->prepare(
    'SELECT id, template_name, channel_id, next_unix, repeat_kind, enabled, last_sent_unix, last_error
     FROM discord_scheduled_posts WHERE guild_id = ?
     ORDER BY enabled DESC, next_unix ASC LIMIT 60'
);
$stmt->bind_param('s', $guildId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

botpanel_json([
    'ok' => true,
    'available' => true,
    'schedules' => array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'name' => (string)$row['template_name'],
        'channel_id' => (string)$row['channel_id'],
        'next' => (int)$row['next_unix'],
        'repeat' => (string)$row['repeat_kind'],
        'active' => (int)$row['enabled'] === 1,
        'last_sent' => $row['last_sent_unix'] !== null ? (int)$row['last_sent_unix'] : null,
        'last_error' => $row['last_error'] !== null ? (string)$row['last_error'] : null,
    ], $rows),
]);
