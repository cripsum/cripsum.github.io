<?php
declare(strict_types=1);

/**
 * I ticket di un server, dal pannello: vederli e lavorarci.
 *
 * GET  ?guild=…                    aperti, chiusi di recente, qualche numero
 * GET  ?guild=…&thread=…           la conversazione di un ticket (letta dal bot)
 * GET  ?guild=…&staff=1            a chi si puo' assegnare un ticket
 * POST {guild, action: publish}    pubblica il pannello, o lo riscrive sul posto
 * POST {guild, action: close|reopen|assign|reply, thread, …}
 *
 * Sono i ticket di Discord (#0001). Quelli del supporto del sito (TK-…) si
 * gestiscono dall'area supporto, come sempre.
 */

require_once __DIR__ . '/bootstrap.php';

$user = botpanel_require_user($mysqli);
$guildId = botpanel_request_guild();

/**
 * Nomi e foto di un gruppo di utenti, chiesti al bot.
 *
 * @param array<string, bool> $ids
 * @return array<string, array{name: string, avatar: ?string}>
 */
function botpanel_ticket_users(string $guildId, array $ids): array
{
    if ($ids === []) {
        return [];
    }

    $response = botpanel_bot('/v1/panel/users', ['guild_id' => $guildId, 'ids' => array_map('strval', array_keys($ids))], 5);
    $users = [];

    foreach ((array)($response['users'] ?? []) as $id => $entry) {
        if (is_array($entry)) {
            $avatar = (string)($entry['avatar'] ?? '');
            $users[(string)$id] = [
                'name' => mb_substr((string)($entry['name'] ?? ''), 0, 80),
                'avatar' => str_starts_with($avatar, 'https://cdn.discordapp.com/') ? $avatar : null,
            ];
        }
    }

    return $users;
}

/** Un'immagine o un file allegato: solo indirizzi di Discord. */
function botpanel_cdn_url($url): ?string
{
    $url = (string)$url;
    return preg_match('#^https://(cdn|media)\.discordapp\.(com|net)/#', $url) ? $url : null;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ['input' => $input] = botpanel_begin_action($user, $guildId, 'tickets', 30);
    $action = (string)($input['action'] ?? '');

    if ($action === 'publish') {
        // Si pubblica quello che e' salvato: il canale non arriva dal browser.
        $saved = botpanel_settings($mysqli, $guildId)['tickets'] ?? null;
        if ($saved === null || !$saved['enabled']) {
            botpanel_fail('not_saved', 'Salva e accendi i ticket prima di pubblicare il pannello.', 409);
        }

        $previous = botpanel_posts($mysqli, $guildId)['tickets'] ?? null;
        $response = botpanel_bot_action('/v1/panel/tickets/publish', $user, $guildId, [
            'channel_id' => $previous['channel_id'] ?? '',
            'message_id' => $previous['message_id'] ?? '',
        ]);

        botpanel_post_save($mysqli, $guildId, 'tickets', (string)($response['channel_id'] ?? ''), (string)($response['message_id'] ?? ''));
        botpanel_audit($mysqli, $guildId, $user, 'tickets', !empty($response['updated']) ? 'panel_update' : 'panel_publish', null, [
            'channel_id' => (string)($response['channel_id'] ?? ''),
        ]);

        botpanel_json(['ok' => true, 'updated' => !empty($response['updated']), 'url' => botpanel_discord_url($response['url'] ?? '')]);
    }

    if (!in_array($action, ['close', 'reopen', 'assign', 'reply'], true)) {
        botpanel_fail('bad_action', 'Azione sconosciuta.', 400);
    }

    $threadId = (string)($input['thread'] ?? '');
    if (!botpanel_is_snowflake($threadId)) {
        botpanel_fail('bad_request', 'Ticket non valido.', 400);
    }

    $payload = ['thread_id' => $threadId, 'action' => $action];

    if ($action === 'assign') {
        $assignee = (string)($input['assignee'] ?? '');
        if ($assignee !== '' && !botpanel_is_snowflake($assignee)) {
            botpanel_fail('invalid', 'Persona non valida.', 422);
        }
        $payload['assignee'] = $assignee;
    }

    if ($action === 'reply') {
        $text = trim((string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)($input['text'] ?? '')));
        if ($text === '' || mb_strlen($text) > 4000) {
            botpanel_fail('invalid', 'Scrivi una risposta (al massimo 4000 caratteri).', 422, ['errors' => ['text' => $text === '' ? 'required' : 'too_long']]);
        }
        $payload['text'] = $text;
    }

    $response = botpanel_bot_action('/v1/panel/tickets/act', $user, $guildId, $payload);

    if ($action !== 'reply') {
        botpanel_audit($mysqli, $guildId, $user, 'tickets', 'ticket_' . $action, null, ['thread_id' => $threadId] + (isset($payload['assignee']) ? ['assignee' => $payload['assignee']] : []));
    }

    botpanel_json(['ok' => true, 'outcome' => (string)($response['outcome'] ?? '')]);
}

botpanel_access($user, $guildId);
cripsum_release_session();

if (isset($_GET['thread'])) {
    $threadId = (string)$_GET['thread'];
    if (!botpanel_is_snowflake($threadId)) {
        botpanel_fail('bad_request', 'Ticket non valido.', 400);
    }

    $response = botpanel_bot('/v1/panel/tickets/thread', ['guild_id' => $guildId, 'thread_id' => $threadId, 'actor_discord_id' => $user['discord_id']], 10);

    if ($response === null) {
        botpanel_fail('bot_unreachable', 'Il bot non risponde: riprova fra poco.', 503);
    }
    if (empty($response['success'])) {
        botpanel_fail('ticket_not_found', 'Ticket non trovato.', 404);
    }

    // Tutto quello che arriva dal bot finisce nella pagina: si ripulisce qui.
    $messages = [];
    foreach (array_slice((array)($response['messages'] ?? []), -100) as $message) {
        if (!is_array($message)) {
            continue;
        }

        $messages[] = [
            'author' => [
                'id' => (string)($message['author']['id'] ?? ''),
                'name' => mb_substr((string)($message['author']['name'] ?? ''), 0, 80),
                'avatar' => botpanel_cdn_url($message['author']['avatar'] ?? ''),
                'bot' => !empty($message['author']['bot']),
            ],
            'when' => (int)($message['when'] ?? 0),
            'content' => mb_substr((string)($message['content'] ?? ''), 0, 4000),
            'attachments' => array_values(array_filter(array_map(static fn($file): ?array => is_array($file) && botpanel_cdn_url($file['url'] ?? '') ? [
                'name' => mb_substr((string)($file['name'] ?? 'file'), 0, 120),
                'url' => botpanel_cdn_url($file['url']),
                'image' => !empty($file['image']),
            ] : null, array_slice((array)($message['attachments'] ?? []), 0, 10)))),
            'embeds' => array_map(static fn($embed): array => [
                'author' => mb_substr((string)($embed['author'] ?? ''), 0, 200),
                'title' => mb_substr((string)($embed['title'] ?? ''), 0, 256),
                'description' => mb_substr((string)($embed['description'] ?? ''), 0, 4000),
                'fields' => array_map(static fn($field): array => [
                    'name' => mb_substr((string)($field['name'] ?? ''), 0, 256),
                    'value' => mb_substr((string)($field['value'] ?? ''), 0, 1024),
                ], array_slice((array)($embed['fields'] ?? []), 0, 10)),
            ], array_filter(array_slice((array)($message['embeds'] ?? []), 0, 3), 'is_array')),
        ];
    }

    botpanel_json([
        'ok' => true,
        'ticket' => $response['ticket'] ?? null,
        'url' => botpanel_discord_url($response['url'] ?? ''),
        'messages' => $messages,
    ]);
}

if (isset($_GET['staff'])) {
    $response = botpanel_bot('/v1/panel/tickets/staff', ['guild_id' => $guildId, 'actor_discord_id' => $user['discord_id']], 8);
    $staff = [];

    foreach ((array)($response['staff'] ?? []) as $entry) {
        if (is_array($entry) && botpanel_is_snowflake($entry['id'] ?? null)) {
            $staff[] = [
                'id' => (string)$entry['id'],
                'name' => mb_substr((string)($entry['name'] ?? ''), 0, 80),
                'avatar' => botpanel_cdn_url($entry['avatar'] ?? ''),
            ];
        }
    }

    botpanel_json(['ok' => true, 'staff' => $staff]);
}

if (!auth_table_exists($mysqli, 'discord_tickets')) {
    botpanel_json(['ok' => true, 'available' => false]);
}

$stmt = $mysqli->prepare(
    "SELECT
        COALESCE(SUM(status = 'open'), 0) AS open_now,
        COALESCE(SUM(status = 'closed' AND closed_at > (NOW() - INTERVAL 30 DAY)), 0) AS closed_30d,
        COALESCE(SUM(rating IS NOT NULL AND closed_at > (NOW() - INTERVAL 30 DAY)), 0) AS rated_30d,
        AVG(CASE WHEN rating IS NOT NULL AND closed_at > (NOW() - INTERVAL 30 DAY) THEN rating END) AS rating_30d,
        AVG(CASE WHEN status = 'closed' AND close_reason = 'manual' AND closed_at > (NOW() - INTERVAL 30 DAY)
                 THEN TIMESTAMPDIFF(MINUTE, opened_at, closed_at) END) AS minutes_30d
     FROM discord_tickets WHERE guild_id = ?"
);

if (!$stmt) {
    botpanel_fail('database', 'Impossibile leggere i ticket.', 500);
}

$stmt->bind_param('s', $guildId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$stmt = $mysqli->prepare(
    "SELECT number, thread_id, opener_discord_id, category_label, subject, status, claimed_by, closed_by,
            close_reason, rating, UNIX_TIMESTAMP(opened_at) AS opened_unix, UNIX_TIMESTAMP(closed_at) AS closed_unix
     FROM discord_tickets
     WHERE guild_id = ? AND thread_id IS NOT NULL
       AND (status = 'open' OR closed_at > (NOW() - INTERVAL 14 DAY))
     ORDER BY (status = 'open') DESC, opened_at DESC
     LIMIT 60"
);
$stmt->bind_param('s', $guildId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$ids = [];
$list = [];

foreach ($rows as $row) {
    foreach (['opener_discord_id', 'claimed_by', 'closed_by'] as $column) {
        if ($row[$column] !== null) {
            $ids[(string)$row[$column]] = true;
        }
    }

    $list[] = [
        'number' => (int)$row['number'],
        'thread_id' => (string)$row['thread_id'],
        'opener_id' => (string)$row['opener_discord_id'],
        'category' => (string)$row['category_label'],
        'subject' => (string)$row['subject'],
        'status' => (string)$row['status'],
        'claimed_by' => $row['claimed_by'] !== null ? (string)$row['claimed_by'] : null,
        'closed_by' => $row['closed_by'] !== null ? (string)$row['closed_by'] : null,
        'close_reason' => $row['close_reason'] !== null ? (string)$row['close_reason'] : null,
        'rating' => $row['rating'] !== null ? (int)$row['rating'] : null,
        'opened' => (int)$row['opened_unix'],
        'closed' => $row['closed_unix'] !== null ? (int)$row['closed_unix'] : null,
    ];
}

$post = botpanel_posts($mysqli, $guildId)['tickets'] ?? null;

botpanel_json([
    'ok' => true,
    'available' => true,
    'stats' => [
        'open' => (int)($stats['open_now'] ?? 0),
        'closed_30d' => (int)($stats['closed_30d'] ?? 0),
        'rated_30d' => (int)($stats['rated_30d'] ?? 0),
        'rating_30d' => $stats['rating_30d'] !== null ? round((float)$stats['rating_30d'], 1) : null,
        'minutes_30d' => $stats['minutes_30d'] !== null ? (int)round((float)$stats['minutes_30d']) : null,
    ],
    'tickets' => $list,
    'users' => (object)botpanel_ticket_users($guildId, $ids),
    'panel' => $post ? ['url' => 'https://discord.com/channels/' . $guildId . '/' . $post['channel_id'] . '/' . $post['message_id']] : null,
]);
