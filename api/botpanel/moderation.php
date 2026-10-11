<?php
declare(strict_types=1);

/**
 * Storico della moderazione di un server: le ultime azioni fatte con /mod,
 * comprese quelle automatiche.
 *
 * I nomi li da' il bot, da quello che ha gia' in memoria: chi non e' piu' nel
 * server compare con il suo ID.
 */

require_once __DIR__ . '/bootstrap.php';

const MODERATION_PAGE_SIZE = 20;

$user = botpanel_require_user($mysqli);
$guildId = botpanel_request_guild();
botpanel_access($user, $guildId);
cripsum_release_session();

if (!auth_table_exists($mysqli, 'discord_mod_actions')) {
    botpanel_json(['ok' => true, 'available' => false, 'actions' => [], 'users' => (object)[], 'more' => false]);
}

$page = max(0, min(200, (int)($_GET['page'] ?? 0)));
$limit = MODERATION_PAGE_SIZE + 1;
$offset = $page * MODERATION_PAGE_SIZE;

$stmt = $mysqli->prepare(
    'SELECT id, action, target_discord_id, target_channel_id, moderator_discord_id, reason,
            duration_seconds, details, created_at
     FROM discord_mod_actions
     WHERE guild_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT ? OFFSET ?'
);

if (!$stmt) {
    botpanel_fail('database', 'Impossibile leggere lo storico.', 500);
}

$stmt->bind_param('sii', $guildId, $limit, $offset);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$more = count($rows) > MODERATION_PAGE_SIZE;
$rows = array_slice($rows, 0, MODERATION_PAGE_SIZE);

$ids = [];
$actions = [];

foreach ($rows as $row) {
    $details = $row['details'] !== null ? json_decode((string)$row['details'], true) : null;

    foreach (['target_discord_id', 'moderator_discord_id'] as $column) {
        if (botpanel_is_snowflake($row[$column] ?? null)) {
            $ids[(string)$row[$column]] = true;
        }
    }

    $actions[] = [
        'id' => (int)$row['id'],
        'action' => (string)$row['action'],
        'target_id' => $row['target_discord_id'],
        'channel_id' => $row['target_channel_id'],
        'moderator_id' => (string)$row['moderator_discord_id'],
        'reason' => (string)($row['reason'] ?? ''),
        'duration' => $row['duration_seconds'] !== null ? (int)$row['duration_seconds'] : null,
        'automatic' => is_array($details) && !empty($details['automatic']),
        'when' => strtotime((string)$row['created_at']),
    ];
}

$users = [];
if ($ids !== []) {
    $response = botpanel_bot('/v1/panel/users', ['guild_id' => $guildId, 'ids' => array_map('strval', array_keys($ids))], 5);

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
}

botpanel_json([
    'ok' => true,
    'available' => true,
    'actions' => $actions,
    'users' => (object)$users,
    'more' => $more,
]);
