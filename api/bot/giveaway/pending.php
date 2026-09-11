<?php
declare(strict_types=1);

/**
 * Giveaway in corso e giveaway scaduti da estrarre.
 *
 * Il bot interroga questo endpoint a intervalli regolari: quello che trova in
 * `due` va estratto.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/reward_mail.php';

bot_require_key();
bot_require_method('GET');

if (!auth_table_exists($mysqli, 'discord_giveaways')) {
    bot_json(['ok' => true, 'available' => false, 'due' => [], 'running' => []]);
}

$guildId = bot_snowflake('guild_id', false);
$where = "status = 'running'";
$params = [];
$types = '';

if ($guildId !== '') {
    $where .= ' AND guild_id = ?';
    $params[] = $guildId;
    $types .= 's';
}

$sql =
    "SELECT g.id, g.channel_id, g.message_id, g.title, g.winners_count, g.rewards, g.ends_at,
            (SELECT COUNT(*) FROM discord_giveaway_entries e WHERE e.giveaway_id = g.id) AS entries
     FROM discord_giveaways g
     WHERE $where
     ORDER BY g.ends_at ASC
     LIMIT 100";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    bot_fail('Unable to read the giveaways.', 500);
}

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$now = time();
$due = [];
$running = [];

foreach ($rows as $row) {
    $rewards = cripsum_normalize_rewards(json_decode((string)$row['rewards'], true) ?: []);

    $entry = [
        'id' => (int)$row['id'],
        'channel_id' => (string)$row['channel_id'],
        'message_id' => $row['message_id'],
        'title' => (string)$row['title'],
        'winners_count' => (int)$row['winners_count'],
        'entries' => (int)$row['entries'],
        'ends_at' => $row['ends_at'],
        'ends_unix' => strtotime((string)$row['ends_at']),
        'rewards' => $rewards['ok'] ? array_map('cripsum_reward_label', $rewards['rewards']) : [],
    ];

    if ($entry['ends_unix'] <= $now) {
        $due[] = $entry;
    } else {
        $running[] = $entry;
    }
}

bot_json([
    'ok' => true,
    'available' => true,
    'due' => $due,
    'running' => $running,
]);
