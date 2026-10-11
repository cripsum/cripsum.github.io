<?php
declare(strict_types=1);

/**
 * Quello che sta succedendo in un server, per le pagine dei ticket e dei
 * giveaway: i ticket aperti con qualche numero, i giveaway in corso.
 *
 * GET  ?guild=…&what=tickets|giveaways   legge
 * POST {guild, action: "publish_tickets"} fa pubblicare a Poppy il pannello
 *                                         dei ticket nel canale salvato
 */

require_once __DIR__ . '/bootstrap.php';

$user = botpanel_require_user($mysqli);
$guildId = botpanel_request_guild();

/**
 * Nomi e avatar di un gruppo di utenti, chiesti al bot.
 *
 * @param array<string, bool> $ids
 * @return array<string, array{name: string, avatar: ?string}>
 */
function botpanel_activity_users(string $guildId, array $ids): array
{
    if ($ids === []) {
        return [];
    }

    $response = botpanel_bot('/v1/panel/users', ['guild_id' => $guildId, 'ids' => array_map('strval', array_keys($ids))], 5);
    $users = [];

    foreach ((array)($response['users'] ?? []) as $id => $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $avatar = (string)($entry['avatar'] ?? '');

        $users[(string)$id] = [
            'name' => mb_substr((string)($entry['name'] ?? ''), 0, 80),
            // Solo immagini di Discord: finiscono in un <img> della pagina.
            'avatar' => str_starts_with($avatar, 'https://cdn.discordapp.com/') ? $avatar : null,
        ];
    }

    return $users;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    botpanel_require_write();
    botpanel_rate('send', 10, 60);

    $input = botpanel_input();
    $access = botpanel_access($user, $guildId, true);
    cripsum_release_session();
    botpanel_require_home_admin($user, $access);

    if (($input['action'] ?? '') !== 'publish_tickets') {
        botpanel_fail('bad_action', 'Azione sconosciuta.', 400);
    }

    // Si pubblica quello che e' salvato: il canale non arriva dal browser.
    $saved = botpanel_settings($mysqli, $guildId)['tickets'] ?? null;

    if ($saved === null || !$saved['enabled']) {
        botpanel_fail('not_saved', 'Salva e accendi i ticket prima di pubblicare il pannello.', 409);
    }

    $response = botpanel_bot('/v1/panel/tickets/publish', ['guild_id' => $guildId], 12);

    if ($response === null || empty($response['success'])) {
        botpanel_fail('send_failed', 'Poppy non è riuscito a pubblicarlo.', 502, ['reason' => (string)($response['error'] ?? 'unreachable')]);
    }

    botpanel_audit($mysqli, $guildId, $user, 'tickets', 'panel_publish', null, ['channel_id' => (string)($response['channel_id'] ?? '')]);

    $url = (string)($response['url'] ?? '');

    botpanel_json(['ok' => true, 'url' => str_starts_with($url, 'https://discord.com/channels/') ? $url : null]);
}

$access = botpanel_access($user, $guildId);
cripsum_release_session();

$what = (string)($_GET['what'] ?? '');

if ($what === 'tickets') {
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
        "SELECT number, thread_id, opener_discord_id, category_label, subject, claimed_by, opened_at
         FROM discord_tickets
         WHERE guild_id = ? AND status = 'open' AND thread_id IS NOT NULL
         ORDER BY opened_at ASC LIMIT 30"
    );
    $stmt->bind_param('s', $guildId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $ids = [];
    $open = [];

    foreach ($rows as $row) {
        $ids[(string)$row['opener_discord_id']] = true;
        if ($row['claimed_by'] !== null) {
            $ids[(string)$row['claimed_by']] = true;
        }

        $open[] = [
            'number' => (int)$row['number'],
            'thread_id' => (string)$row['thread_id'],
            'opener_id' => (string)$row['opener_discord_id'],
            'category' => (string)$row['category_label'],
            'subject' => (string)$row['subject'],
            'claimed_by' => $row['claimed_by'] !== null ? (string)$row['claimed_by'] : null,
            'when' => strtotime((string)$row['opened_at']),
        ];
    }

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
        'open' => $open,
        'users' => (object)botpanel_activity_users($guildId, $ids),
    ]);
}

if ($what === 'giveaways') {
    $list = [];

    if (auth_table_exists($mysqli, 'discord_guild_giveaways')) {
        $stmt = $mysqli->prepare(
            "SELECT g.id, g.channel_id, g.message_id, g.prize, g.winners_count, g.repeats, g.status, g.ends_at,
                    (SELECT COUNT(*) FROM discord_guild_giveaway_entries e WHERE e.giveaway_id = g.id) AS entries
             FROM discord_guild_giveaways g
             WHERE g.guild_id = ? AND (g.status = 'running' OR (g.status = 'ended' AND g.drawn_at > (NOW() - INTERVAL 14 DAY)))
             ORDER BY (g.status = 'running') DESC, g.ends_at DESC LIMIT 25"
        );

        if ($stmt) {
            $stmt->bind_param('s', $guildId);
            $stmt->execute();

            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $list[] = [
                    'code' => 'c' . (int)$row['id'],
                    'title' => (string)$row['prize'],
                    'site' => false,
                    'running' => (string)$row['status'] === 'running',
                    'repeats' => (int)$row['repeats'] === 1,
                    'winners' => (int)$row['winners_count'],
                    'entries' => (int)$row['entries'],
                    'channel_id' => (string)$row['channel_id'],
                    'message_id' => $row['message_id'] !== null ? (string)$row['message_id'] : null,
                    'ends' => strtotime((string)$row['ends_at']),
                ];
            }

            $stmt->close();
        }
    }

    // Nel server di Cripsum ci sono anche quelli con i premi del sito.
    if ($access['is_home'] && auth_table_exists($mysqli, 'discord_giveaways')) {
        $stmt = $mysqli->prepare(
            "SELECT g.id, g.channel_id, g.message_id, g.title, g.winners_count, g.ends_at,
                    (SELECT COUNT(*) FROM discord_giveaway_entries e WHERE e.giveaway_id = g.id) AS entries
             FROM discord_giveaways g
             WHERE g.guild_id = ? AND g.status = 'running'
             ORDER BY g.ends_at ASC LIMIT 25"
        );

        if ($stmt) {
            $stmt->bind_param('s', $guildId);
            $stmt->execute();

            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $list[] = [
                    'code' => '#' . (int)$row['id'],
                    'title' => (string)$row['title'],
                    'site' => true,
                    'running' => true,
                    'repeats' => false,
                    'winners' => (int)$row['winners_count'],
                    'entries' => (int)$row['entries'],
                    'channel_id' => (string)$row['channel_id'],
                    'message_id' => $row['message_id'] !== null ? (string)$row['message_id'] : null,
                    'ends' => strtotime((string)$row['ends_at']),
                ];
            }

            $stmt->close();
        }
    }

    botpanel_json(['ok' => true, 'available' => auth_table_exists($mysqli, 'discord_guild_giveaways'), 'giveaways' => $list]);
}

botpanel_fail('bad_request', 'Richiesta non valida.', 400);
