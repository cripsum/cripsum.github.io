<?php
declare(strict_types=1);

/**
 * Richiami (warn) della moderazione Discord.
 *
 * GET  → elenco dei richiami di un membro.
 * POST → nuovo richiamo.
 *
 * Questa e' moderazione **del server Discord**, tenuta separata da quella del
 * sito: non tocca lo stato dell'account Cripsum. L'autorita' e' il ruolo staff
 * su Discord, verificato dal bot prima di chiamare; qui si registra e basta.
 * Il collegamento con l'account Cripsum, quando c'e', serve solo a far
 * comparire lo storico in /lookup.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();

if (!auth_table_exists($mysqli, 'discord_warnings')) {
    bot_json(['ok' => true, 'available' => false, 'warnings' => [], 'total' => 0]);
}

$guildId = bot_snowflake('guild_id');
$targetDiscordId = bot_discord_id('discord_id');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $includeRevoked = bot_text('include_revoked', 5) === '1';
    $where = $includeRevoked ? '' : ' AND revoked_at IS NULL';

    $stmt = $mysqli->prepare(
        "SELECT w.id, w.reason, w.created_at, w.revoked_at, w.moderator_discord_id, u.username AS moderator
         FROM discord_warnings w
         LEFT JOIN utenti u ON u.id = w.moderator_user_id
         WHERE w.guild_id = ? AND w.discord_user_id = ?$where
         ORDER BY w.created_at DESC
         LIMIT 50"
    );

    if (!$stmt) {
        bot_fail('Unable to read the warnings.', 500);
    }

    $stmt->bind_param('ss', $guildId, $targetDiscordId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $warnings = array_map(static function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'reason' => (string)$row['reason'],
            'moderator' => $row['moderator'] ?: null,
            'moderator_discord_id' => (string)$row['moderator_discord_id'],
            'created_at' => $row['created_at'],
            'revoked_at' => $row['revoked_at'],
        ];
    }, $rows);

    bot_json([
        'ok' => true,
        'available' => true,
        'warnings' => $warnings,
        'total' => count($warnings),
    ]);
}

bot_require_method('POST');

$moderatorDiscordId = bot_discord_id('moderator_discord_id');
$reason = bot_text('reason', 500, false, 'Nessun motivo indicato.');

$target = bot_find_user($mysqli, $targetDiscordId);
$moderator = bot_find_user($mysqli, $moderatorDiscordId);
$targetUserId = $target ? (int)$target['id'] : null;
$moderatorUserId = $moderator ? (int)$moderator['id'] : null;

$stmt = $mysqli->prepare(
    'INSERT INTO discord_warnings
        (guild_id, discord_user_id, user_id, moderator_discord_id, moderator_user_id, reason)
     VALUES (?, ?, ?, ?, ?, ?)'
);

if (!$stmt) {
    bot_fail('Unable to store the warning.', 500);
}

$stmt->bind_param('ssisis', $guildId, $targetDiscordId, $targetUserId, $moderatorDiscordId, $moderatorUserId, $reason);

if (!$stmt->execute()) {
    $stmt->close();
    bot_fail('Unable to store the warning.', 500);
}

$warningId = $mysqli->insert_id;
$stmt->close();

// Quanti richiami attivi ha adesso: serve al bot per le sanzioni progressive.
$active = 0;
$stmtCount = $mysqli->prepare(
    'SELECT COUNT(*) AS total FROM discord_warnings
     WHERE guild_id = ? AND discord_user_id = ? AND revoked_at IS NULL'
);

if ($stmtCount) {
    $stmtCount->bind_param('ss', $guildId, $targetDiscordId);
    $stmtCount->execute();
    $active = (int)($stmtCount->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtCount->close();
}

bot_json([
    'ok' => true,
    'available' => true,
    'warning_id' => (int)$warningId,
    'active_warnings' => $active,
    'target_username' => $target['username'] ?? null,
]);
