<?php
declare(strict_types=1);

/**
 * Annulla un richiamo. Il record resta, con la data di revoca: lo storico di
 * moderazione non si cancella.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();
bot_require_method('POST');

if (!auth_table_exists($mysqli, 'discord_warnings')) {
    bot_json(['ok' => true, 'available' => false]);
}

$body = bot_input();
$warningId = (int)($body['warning_id'] ?? $_GET['warning_id'] ?? 0);
$guildId = bot_snowflake('guild_id');
$moderatorDiscordId = bot_discord_id('moderator_discord_id');

if ($warningId <= 0) {
    bot_fail('Missing warning_id.', 400);
}

$stmt = $mysqli->prepare(
    'SELECT id, discord_user_id, revoked_at FROM discord_warnings
     WHERE id = ? AND guild_id = ? LIMIT 1'
);

if (!$stmt) {
    bot_fail('Unable to read the warning.', 500);
}

$stmt->bind_param('is', $warningId, $guildId);
$stmt->execute();
$warning = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$warning) {
    bot_fail('Warning not found.', 404);
}

if ($warning['revoked_at'] !== null) {
    bot_json(['ok' => true, 'available' => true, 'changed' => false, 'reason' => 'already_revoked']);
}

$update = $mysqli->prepare(
    'UPDATE discord_warnings SET revoked_at = NOW(), revoked_by_discord_id = ? WHERE id = ? LIMIT 1'
);

if (!$update) {
    bot_fail('Unable to revoke the warning.', 500);
}

$update->bind_param('si', $moderatorDiscordId, $warningId);
$ok = $update->execute();
$update->close();

if (!$ok) {
    bot_fail('Unable to revoke the warning.', 500);
}

$active = 0;
$stmtCount = $mysqli->prepare(
    'SELECT COUNT(*) AS total FROM discord_warnings
     WHERE guild_id = ? AND discord_user_id = ? AND revoked_at IS NULL'
);

if ($stmtCount) {
    $targetDiscordId = (string)$warning['discord_user_id'];
    $stmtCount->bind_param('ss', $guildId, $targetDiscordId);
    $stmtCount->execute();
    $active = (int)($stmtCount->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtCount->close();
}

bot_json([
    'ok' => true,
    'available' => true,
    'changed' => true,
    'warning_id' => $warningId,
    'discord_user_id' => (string)$warning['discord_user_id'],
    'active_warnings' => $active,
]);
