<?php
declare(strict_types=1);

/**
 * Estrazione dei vincitori e consegna dei premi.
 *
 * La chiama il bot da solo quando scade il tempo, oppure uno staff con
 * /giveaway end e /giveaway reroll. La chiusura anticipata e il reroll
 * richiedono un account admin collegato; l'estrazione a tempo scaduto no,
 * perche' a lanciarla e' il bot stesso.
 *
 * Il premio viene consegnato con la posta del sito, la stessa che usa il
 * pannello admin: il vincitore lo riscuote dalla sua casella.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/reward_mail.php';

bot_require_key();
bot_require_method('POST');

if (!auth_table_exists($mysqli, 'discord_giveaways')) {
    bot_json(['ok' => true, 'available' => false]);
}

$body = bot_input();
$giveawayId = (int)($body['giveaway_id'] ?? 0);
$reroll = !empty($body['reroll']);
$force = !empty($body['force']);

if ($giveawayId <= 0) {
    bot_fail('Missing giveaway_id.', 400);
}

$stmt = $mysqli->prepare(
    'SELECT id, guild_id, channel_id, message_id, title, winners_count, rewards, status, ends_at,
            created_by_user_id
     FROM discord_giveaways WHERE id = ? LIMIT 1'
);

if (!$stmt) {
    bot_fail('Unable to read the giveaway.', 500);
}

$stmt->bind_param('i', $giveawayId);
$stmt->execute();
$giveaway = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$giveaway) {
    bot_fail('Giveaway not found.', 404);
}

$expired = strtotime((string)$giveaway['ends_at']) <= time();
$actor = null;

// Solo l'estrazione automatica di un giveaway scaduto puo' fare a meno
// dell'account admin.
if ($reroll || $force || !$expired) {
    $actor = bot_require_actor($mysqli);
}

if ((string)$giveaway['status'] === 'cancelled') {
    bot_json(['ok' => true, 'available' => true, 'drawn' => false, 'reason' => 'cancelled']);
}

if ((string)$giveaway['status'] === 'ended' && !$reroll) {
    bot_json(['ok' => true, 'available' => true, 'drawn' => false, 'reason' => 'already_drawn']);
}

$normalized = cripsum_normalize_rewards(json_decode((string)$giveaway['rewards'], true) ?: []);
if (!$normalized['ok']) {
    bot_fail('The giveaway rewards are not valid: ' . ($normalized['error'] ?? ''), 500);
}

$winnersCount = max(1, (int)$giveaway['winners_count']);

// Il reroll estrae un solo sostituto, escludendo chi ha gia' vinto.
$limit = $reroll ? 1 : $winnersCount;

$sql =
    'SELECT e.discord_user_id, e.user_id, u.username
     FROM discord_giveaway_entries e
     JOIN utenti u ON u.id = e.user_id
     WHERE e.giveaway_id = ?
       AND COALESCE(u.isBannato, 0) = 0
       AND e.user_id NOT IN (
           SELECT w.user_id FROM discord_giveaway_winners w WHERE w.giveaway_id = ?
       )
     ORDER BY RAND()
     LIMIT ' . (int)$limit;

$stmtEntries = $mysqli->prepare($sql);
if (!$stmtEntries) {
    bot_fail('Unable to read the entries.', 500);
}

$stmtEntries->bind_param('ii', $giveawayId, $giveawayId);
$stmtEntries->execute();
$winners = $stmtEntries->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtEntries->close();

$closeGiveaway = static function (mysqli $mysqli, int $giveawayId): void {
    $stmt = $mysqli->prepare(
        "UPDATE discord_giveaways SET status = 'ended', drawn_at = NOW() WHERE id = ? LIMIT 1"
    );

    if ($stmt) {
        $stmt->bind_param('i', $giveawayId);
        $stmt->execute();
        $stmt->close();
    }
};

if (!$winners) {
    if (!$reroll) {
        $closeGiveaway($mysqli, $giveawayId);
    }

    bot_json([
        'ok' => true,
        'available' => true,
        'drawn' => true,
        'winners' => [],
        'reason' => 'no_entries',
        'title' => (string)$giveaway['title'],
        'message_id' => $giveaway['message_id'],
        'channel_id' => (string)$giveaway['channel_id'],
    ]);
}

$rewardLabels = array_map('cripsum_reward_label', $normalized['rewards']);
$rewardText = implode(', ', $rewardLabels);
$senderId = $giveaway['created_by_user_id'] !== null ? (int)$giveaway['created_by_user_id'] : null;
$delivered = [];

foreach ($winners as $winner) {
    $userId = (int)$winner['user_id'];

    $mail = cripsum_send_reward_mail(
        $mysqli,
        $senderId,
        [$userId],
        '🎉 Hai vinto un giveaway!',
        '🎉 You won a giveaway!',
        "Complimenti! Hai vinto **{$giveaway['title']}** nel server Discord di Cripsum.\n\n"
            . "Premio: {$rewardText}\n\nRiscuotilo da questo messaggio.",
        "Congratulations! You won **{$giveaway['title']}** in the Cripsum Discord server.\n\n"
            . "Prize: {$rewardText}\n\nClaim it from this message.",
        $normalized['rewards'],
        'system'
    );

    $stmtWinner = $mysqli->prepare(
        'INSERT IGNORE INTO discord_giveaway_winners (giveaway_id, discord_user_id, user_id, site_message_id)
         VALUES (?, ?, ?, ?)'
    );

    if ($stmtWinner) {
        $discordUserId = (string)$winner['discord_user_id'];
        $messageId = $mail['message_id'];
        $stmtWinner->bind_param('isii', $giveawayId, $discordUserId, $userId, $messageId);
        $stmtWinner->execute();
        $stmtWinner->close();
    }

    $delivered[] = [
        'discord_user_id' => (string)$winner['discord_user_id'],
        'username' => (string)$winner['username'],
        'prize_delivered' => $mail['ok'],
        'delivery_error' => $mail['error'],
    ];

    if (!$mail['ok']) {
        error_log('[Giveaway] Premio non consegnato a ' . $winner['username'] . ': ' . ($mail['error'] ?? ''));
    }
}

if (!$reroll) {
    $closeGiveaway($mysqli, $giveawayId);
}

if ($actor) {
    bot_log_action($mysqli, $actor, $reroll ? 'giveaway_reroll' : 'giveaway_draw', null, [
        'giveaway_id' => $giveawayId,
        'winners' => array_column($delivered, 'username'),
    ]);
}

bot_json([
    'ok' => true,
    'available' => true,
    'drawn' => true,
    'reroll' => $reroll,
    'title' => (string)$giveaway['title'],
    'channel_id' => (string)$giveaway['channel_id'],
    'message_id' => $giveaway['message_id'],
    'rewards' => $rewardLabels,
    'winners' => $delivered,
]);
