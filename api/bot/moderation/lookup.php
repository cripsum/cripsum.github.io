<?php
declare(strict_types=1);

/**
 * Scheda completa di un utente per lo staff: account, stato ban, ticket,
 * richiami Discord e ultime azioni di moderazione.
 *
 * Espone dati di account, quindi richiede un admin/owner collegato.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();
bot_require_method('GET');

$actor = bot_require_actor($mysqli);

$targetDiscordId = bot_discord_id('target_discord_id', false);
$targetUsername = bot_text('target_username', 32);
$guildId = bot_snowflake('guild_id', false);

$target = null;
if ($targetDiscordId !== '') {
    $target = bot_find_user($mysqli, $targetDiscordId);
} elseif ($targetUsername !== '') {
    $target = bot_find_user_by_username($mysqli, $targetUsername);
} else {
    bot_fail('Missing target_discord_id or target_username.', 400);
}

$warnings = [];
$modActions = [];

// I richiami esistono anche per chi non ha l'account collegato.
if ($targetDiscordId !== '' && $guildId !== '' && auth_table_exists($mysqli, 'discord_warnings')) {
    $stmt = $mysqli->prepare(
        'SELECT reason, created_at, revoked_at FROM discord_warnings
         WHERE guild_id = ? AND discord_user_id = ?
         ORDER BY created_at DESC LIMIT 5'
    );

    if ($stmt) {
        $stmt->bind_param('ss', $guildId, $targetDiscordId);
        $stmt->execute();
        $warnings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

if ($targetDiscordId !== '' && $guildId !== '' && auth_table_exists($mysqli, 'discord_mod_actions')) {
    $stmt = $mysqli->prepare(
        'SELECT action, reason, created_at FROM discord_mod_actions
         WHERE guild_id = ? AND target_discord_id = ?
         ORDER BY created_at DESC LIMIT 5'
    );

    if ($stmt) {
        $stmt->bind_param('ss', $guildId, $targetDiscordId);
        $stmt->execute();
        $modActions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

if (!$target) {
    bot_json([
        'ok' => true,
        'linked' => false,
        'discord' => [
            'warnings' => $warnings,
            'actions' => $modActions,
        ],
    ]);
}

$targetId = (int)$target['id'];

$account = [
    'id' => $targetId,
    'username' => (string)$target['username'],
    'display_name' => bot_display_name($target),
    'role' => (string)($target['ruolo'] ?? 'utente'),
    'is_premium' => (int)($target['is_premium'] ?? 0) === 1,
    'is_banned' => (int)($target['isBannato'] ?? 0) === 1,
    'godos' => (int)($target['soldi'] ?? 0),
    'discord_id' => $target['discord_id'] ?? null,
    'profile_url' => '/u/' . rawurlencode((string)$target['username']),
];

foreach (['motivo_ban' => 'ban_reason', 'banned_until' => 'banned_until', 'banned_at' => 'banned_at'] as $column => $key) {
    if (!auth_column_exists($mysqli, 'utenti', $column)) {
        continue;
    }

    $stmt = $mysqli->prepare("SELECT `$column` AS value FROM utenti WHERE id = ? LIMIT 1");
    if (!$stmt) {
        continue;
    }

    $stmt->bind_param('i', $targetId);
    $stmt->execute();
    $account[$key] = $stmt->get_result()->fetch_assoc()['value'] ?? null;
    $stmt->close();
}

$tickets = ['open' => 0, 'total' => 0];
$stmtTickets = $mysqli->prepare(
    "SELECT COUNT(*) AS total, SUM(status = 'open') AS aperti FROM site_tickets WHERE user_id = ?"
);

if ($stmtTickets) {
    $stmtTickets->bind_param('i', $targetId);
    $stmtTickets->execute();
    $row = $stmtTickets->get_result()->fetch_assoc() ?: [];
    $stmtTickets->close();
    $tickets = ['open' => (int)($row['aperti'] ?? 0), 'total' => (int)($row['total'] ?? 0)];
}

$collection = ['characters' => 0, 'achievements' => 0];
$stmtCollection = $mysqli->prepare(
    'SELECT
        (SELECT COUNT(DISTINCT personaggio_id) FROM utenti_personaggi WHERE utente_id = ?) AS personaggi,
        (SELECT COUNT(DISTINCT achievement_id) FROM utenti_achievement WHERE utente_id = ?) AS achievement'
);

if ($stmtCollection) {
    $stmtCollection->bind_param('ii', $targetId, $targetId);
    $stmtCollection->execute();
    $row = $stmtCollection->get_result()->fetch_assoc() ?: [];
    $stmtCollection->close();
    $collection = [
        'characters' => (int)($row['personaggi'] ?? 0),
        'achievements' => (int)($row['achievement'] ?? 0),
    ];
}

bot_log_action($mysqli, $actor, 'lookup_user', $targetId, ['source' => 'discord']);

bot_json([
    'ok' => true,
    'linked' => true,
    'account' => $account,
    'tickets' => $tickets,
    'collection' => $collection,
    'discord' => [
        'warnings' => $warnings,
        'actions' => $modActions,
    ],
]);
