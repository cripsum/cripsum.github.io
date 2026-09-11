<?php
declare(strict_types=1);

/**
 * Crea un giveaway.
 *
 * Un giveaway regala valore reale (Godos, personaggi, premium), quindi vale il
 * modello stretto: serve un account Cripsum admin/owner collegato al Discord
 * di chi lancia il comando, e l'azione finisce nei log admin.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/reward_mail.php';

bot_require_key();
bot_require_method('POST');

if (!auth_table_exists($mysqli, 'discord_giveaways')) {
    bot_json(['ok' => false, 'available' => false, 'error' => 'Tabella discord_giveaways mancante.'], 503);
}

$actor = bot_require_actor($mysqli);

$body = bot_input();
$guildId = bot_snowflake('guild_id');
$channelId = bot_snowflake('channel_id');
$title = bot_text('title', 200, true);
$description = bot_text('description', 1000);
$winners = max(1, min(20, (int)($body['winners'] ?? 1)));
$durationSeconds = (int)($body['duration_seconds'] ?? 0);

if ($durationSeconds < 60 || $durationSeconds > 60 * 60 * 24 * 30) {
    bot_fail('Duration must be between 1 minute and 30 days.', 400);
}

$normalized = cripsum_normalize_rewards(is_array($body['rewards'] ?? null) ? $body['rewards'] : []);
if (!$normalized['ok']) {
    bot_fail($normalized['error'] ?? 'Invalid rewards.', 400);
}

$rewardsJson = json_encode($normalized['rewards'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$endsAt = date('Y-m-d H:i:s', time() + $durationSeconds);
$actorDiscordId = (string)$actor['discord_id'];
$actorId = (int)$actor['id'];

$stmt = $mysqli->prepare(
    'INSERT INTO discord_giveaways
        (guild_id, channel_id, title, description, winners_count, rewards, ends_at,
         created_by_discord_id, created_by_user_id)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

if (!$stmt) {
    bot_fail('Unable to create the giveaway.', 500);
}

$descriptionOrNull = $description !== '' ? $description : null;
$stmt->bind_param(
    'ssssisssi',
    $guildId,
    $channelId,
    $title,
    $descriptionOrNull,
    $winners,
    $rewardsJson,
    $endsAt,
    $actorDiscordId,
    $actorId
);

if (!$stmt->execute()) {
    $stmt->close();
    bot_fail('Unable to create the giveaway.', 500);
}

$giveawayId = (int)$mysqli->insert_id;
$stmt->close();

bot_log_action($mysqli, $actor, 'giveaway_create', null, [
    'giveaway_id' => $giveawayId,
    'title' => $title,
    'winners' => $winners,
    'rewards' => $normalized['rewards'],
    'ends_at' => $endsAt,
]);

bot_json([
    'ok' => true,
    'available' => true,
    'giveaway_id' => $giveawayId,
    'ends_at' => $endsAt,
    'ends_unix' => strtotime($endsAt),
    'winners' => $winners,
    'rewards' => array_map(
        static fn(array $reward): array => $reward + ['label' => cripsum_reward_label($reward)],
        $normalized['rewards']
    ),
    'actor' => (string)$actor['username'],
]);
