<?php
declare(strict_types=1);

/**
 * Candidature di un server, dal pannello.
 *
 * GET  ?guild=…                                        in attesa e decise di recente
 * POST {guild, action: publish, channel_id}            pubblica il pannello, o lo riscrive
 * POST {guild, action: decide, id, decision, reason}   accetta o rifiuta
 */

require_once __DIR__ . '/bootstrap.php';

$user = botpanel_require_user($mysqli);
$guildId = botpanel_request_guild();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ['access' => $access, 'input' => $input] = botpanel_begin_action($user, $guildId, 'send', 30);
    $action = (string)($input['action'] ?? '');

    if ($action === 'decide') {
        $id = (int)($input['id'] ?? 0);
        $decision = (string)($input['decision'] ?? '');
        $reason = trim((string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)($input['reason'] ?? '')));

        if ($id <= 0 || !in_array($decision, ['accepted', 'rejected'], true) || mb_strlen($reason) > 500) {
            botpanel_fail('invalid', 'Decisione non valida.', 422);
        }

        $response = botpanel_bot_action('/v1/panel/applications/decide', $user, $guildId, [
            'application_id' => $id,
            'decision' => $decision,
            'reason' => $reason,
        ]);

        botpanel_audit($mysqli, $guildId, $user, 'applications', 'application_' . $decision, null, ['id' => $id]);

        botpanel_json(['ok' => true, 'dm_sent' => !empty($response['dm_sent'])]);
    }

    if ($action !== 'publish') {
        botpanel_fail('bad_action', 'Azione sconosciuta.', 400);
    }

    $saved = botpanel_settings($mysqli, $guildId)['applications'] ?? null;
    if ($saved === null || !$saved['enabled']) {
        botpanel_fail('not_saved', 'Salva e accendi il modulo prima di pubblicare.', 409);
    }

    $ctx = botpanel_validation_context(botpanel_meta($guildId), $access, true);
    $channelId = (string)($input['channel_id'] ?? '');
    $channel = $ctx['channels'][$channelId] ?? null;

    if (!$channel || !in_array($channel['kind'] ?? '', ['text', 'announcement'], true) || empty($channel['bot']['send']) || empty($channel['bot']['embed'])) {
        botpanel_fail('invalid', 'Scegli un canale in cui Poppy può scrivere.', 422, ['errors' => ['channel_id' => 'channel']]);
    }

    $previous = botpanel_posts($mysqli, $guildId)['applications'] ?? null;
    $sameChannel = $previous !== null && $previous['channel_id'] === $channelId;

    $response = botpanel_bot_action('/v1/panel/applications/publish', $user, $guildId, [
        'channel_id' => $channelId,
        'message_id' => $sameChannel ? $previous['message_id'] : '',
    ]);

    botpanel_post_save($mysqli, $guildId, 'applications', (string)($response['channel_id'] ?? ''), (string)($response['message_id'] ?? ''));
    botpanel_audit($mysqli, $guildId, $user, 'applications', !empty($response['updated']) ? 'panel_update' : 'panel_publish', null, ['channel_id' => $channelId]);

    botpanel_json(['ok' => true, 'updated' => !empty($response['updated']), 'url' => botpanel_discord_url($response['url'] ?? '')]);
}

botpanel_access($user, $guildId);
cripsum_release_session();

if (!auth_table_exists($mysqli, 'discord_applications')) {
    botpanel_json(['ok' => true, 'available' => false, 'applications' => [], 'users' => (object)[]]);
}

$weekAgo = time() - 30 * 86400;

$stmt = $mysqli->prepare(
    "SELECT id, form_title, user_discord_id, answers, status, reviewer_discord_id, reason, created_unix, decided_unix
     FROM discord_applications
     WHERE guild_id = ? AND (status = 'pending' OR decided_unix > ?)
     ORDER BY (status = 'pending') DESC, created_unix DESC
     LIMIT 50"
);
$stmt->bind_param('si', $guildId, $weekAgo);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$ids = [];
$list = [];

foreach ($rows as $row) {
    $ids[(string)$row['user_discord_id']] = true;
    if ($row['reviewer_discord_id'] !== null) {
        $ids[(string)$row['reviewer_discord_id']] = true;
    }

    $answers = json_decode((string)$row['answers'], true);

    $list[] = [
        'id' => (int)$row['id'],
        'form' => (string)$row['form_title'],
        'user_id' => (string)$row['user_discord_id'],
        'answers' => is_array($answers) ? array_values(array_filter($answers, 'is_array')) : [],
        'status' => (string)$row['status'],
        'reviewer_id' => $row['reviewer_discord_id'] !== null ? (string)$row['reviewer_discord_id'] : null,
        'reason' => (string)($row['reason'] ?? ''),
        'created' => (int)$row['created_unix'],
        'decided' => $row['decided_unix'] !== null ? (int)$row['decided_unix'] : null,
    ];
}

$users = [];
if ($ids !== []) {
    $response = botpanel_bot('/v1/panel/users', ['guild_id' => $guildId, 'ids' => array_map('strval', array_keys($ids))], 5);
    foreach ((array)($response['users'] ?? []) as $id => $entry) {
        if (is_array($entry)) {
            $avatar = (string)($entry['avatar'] ?? '');
            $users[(string)$id] = [
                'name' => mb_substr((string)($entry['name'] ?? ''), 0, 80),
                'avatar' => str_starts_with($avatar, 'https://cdn.discordapp.com/') ? $avatar : null,
            ];
        }
    }
}

$post = botpanel_posts($mysqli, $guildId)['applications'] ?? null;

botpanel_json([
    'ok' => true,
    'available' => true,
    'applications' => $list,
    'users' => (object)$users,
    'panel' => $post ? $post + ['url' => 'https://discord.com/channels/' . $guildId . '/' . $post['channel_id'] . '/' . $post['message_id']] : null,
]);
