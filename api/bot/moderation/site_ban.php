<?php
declare(strict_types=1);

/**
 * Ban e sban **dell'account Cripsum**, eseguiti da Discord.
 *
 * Questa e' moderazione del sito, quindi vale il modello piu' stretto: serve
 * un account Cripsum admin/owner collegato al Discord di chi lancia il
 * comando, la gerarchia dei ruoli e' la stessa del pannello admin, e ogni
 * azione finisce in admin_logs. Non tocca in alcun modo il server Discord.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();
bot_require_method('POST');

const SITE_BAN_DURATIONS = [
    'permanent' => null,
    '1h' => '+1 hour',
    '1d' => '+1 day',
    '3d' => '+3 days',
    '7d' => '+7 days',
    '30d' => '+30 days',
];

$actor = bot_require_actor($mysqli);

$action = bot_text('action', 10, false, 'ban') === 'unban' ? 'unban' : 'ban';
$reason = bot_text('reason', 255);
$duration = bot_text('duration', 20, false, 'permanent');
$targetDiscordId = bot_discord_id('target_discord_id', false);
$targetUsername = bot_text('target_username', 32);

if (!array_key_exists($duration, SITE_BAN_DURATIONS)) {
    bot_fail('Invalid duration.', 400);
}

$target = null;
if ($targetDiscordId !== '') {
    $target = bot_find_user($mysqli, $targetDiscordId);
} elseif ($targetUsername !== '') {
    $target = bot_find_user_by_username($mysqli, $targetUsername);
} else {
    bot_fail('Missing target_discord_id or target_username.', 400);
}

if (!$target) {
    bot_json([
        'ok' => true,
        'found' => false,
        'searched' => $targetDiscordId !== '' ? 'discord_id' : 'username',
    ]);
}

if ((int)$actor['id'] === (int)$target['id']) {
    bot_fail('You cannot moderate your own account.', 403);
}

if (!admin_can_manage_user($actor, $target, false)) {
    bot_fail('Your role is not high enough to moderate this account.', 403);
}

$bannedUntil = null;
if ($action === 'ban' && SITE_BAN_DURATIONS[$duration] !== null) {
    $bannedUntil = date('Y-m-d H:i:s', strtotime(SITE_BAN_DURATIONS[$duration]));
}

$targetId = (int)$target['id'];

if ($action === 'ban') {
    // Le colonne accessorie esistono solo dove la migration e' passata: si
    // aggiornano una per una, come fa il pannello admin.
    $sets = ['isBannato = 1'];
    $types = '';
    $params = [];

    if (auth_column_exists($mysqli, 'utenti', 'motivo_ban')) {
        $sets[] = 'motivo_ban = ?';
        $params[] = $reason !== '' ? $reason : null;
        $types .= 's';
    }
    if (auth_column_exists($mysqli, 'utenti', 'banned_until')) {
        $sets[] = 'banned_until = ?';
        $params[] = $bannedUntil;
        $types .= 's';
    }
    if (auth_column_exists($mysqli, 'utenti', 'banned_at')) {
        $sets[] = 'banned_at = NOW()';
    }
    if (auth_column_exists($mysqli, 'utenti', 'banned_by')) {
        $sets[] = 'banned_by = ?';
        $params[] = (int)$actor['id'];
        $types .= 'i';
    }
} else {
    $sets = ['isBannato = 0'];
    $types = '';
    $params = [];

    if (auth_column_exists($mysqli, 'utenti', 'motivo_ban')) {
        $sets[] = 'motivo_ban = NULL';
    }
    if (auth_column_exists($mysqli, 'utenti', 'banned_until')) {
        $sets[] = 'banned_until = NULL';
    }
}

if (auth_column_exists($mysqli, 'utenti', 'updated_at')) {
    $sets[] = 'updated_at = NOW()';
}

$params[] = $targetId;
$types .= 'i';

$stmt = $mysqli->prepare('UPDATE utenti SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1');
if (!$stmt) {
    bot_fail('Unable to prepare the ban query.', 500);
}

$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) {
    $stmt->close();
    bot_fail('Unable to update the account.', 500);
}
$stmt->close();

bot_log_action($mysqli, $actor, $action === 'ban' ? 'ban_user' : 'unban_user', $targetId, [
    'reason' => $reason,
    'duration' => $action === 'ban' ? $duration : null,
    'banned_until' => $bannedUntil,
]);

bot_json([
    'ok' => true,
    'found' => true,
    'action' => $action,
    'username' => (string)$target['username'],
    'target_discord_id' => $target['discord_id'] ?? null,
    'banned_until' => $bannedUntil,
    'actor' => (string)$actor['username'],
]);
