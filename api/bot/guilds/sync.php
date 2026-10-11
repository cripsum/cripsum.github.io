<?php
declare(strict_types=1);

/**
 * Elenco dei server in cui si trova il bot.
 *
 * Lo manda il bot: tutto insieme all'avvio (`full`), poi un server alla volta
 * quando entra, esce o cambia nome. Il pannello parte da qui per sapere in
 * quali server un utente puo' configurare qualcosa.
 *
 * In risposta il bot riceve i server bloccati, che deve ignorare.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();
bot_require_method('POST');

if (!auth_table_exists($mysqli, 'discord_guilds')) {
    bot_json(['ok' => true, 'available' => false, 'blocked' => []]);
}

/** Oltre questo numero il bot deve comunque dividersi in piu' processi. */
const GUILD_SYNC_MAX = 2000;

$body = bot_input();
$guilds = is_array($body['guilds'] ?? null) ? array_values($body['guilds']) : [];
$left = is_array($body['left'] ?? null) ? array_values($body['left']) : [];
$full = !empty($body['full']);

if (count($guilds) > GUILD_SYNC_MAX || count($left) > GUILD_SYNC_MAX) {
    bot_fail('Too many guilds in one call.', 413);
}

$isSnowflake = static fn($value): bool => is_string($value) && preg_match('/^\d{15,25}$/', $value) === 1;

$upsert = $mysqli->prepare(
    'INSERT INTO discord_guilds
        (guild_id, name, icon, owner_discord_id, member_count, locale, bot_present, joined_at, left_at)
     VALUES (?, ?, ?, ?, ?, ?, 1, ?, NULL)
     ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        icon = VALUES(icon),
        owner_discord_id = VALUES(owner_discord_id),
        member_count = VALUES(member_count),
        locale = VALUES(locale),
        bot_present = 1,
        joined_at = COALESCE(VALUES(joined_at), joined_at),
        left_at = NULL'
);

if (!$upsert) {
    bot_fail('Unable to store the guilds.', 500);
}

$present = [];

foreach ($guilds as $guild) {
    if (!is_array($guild) || !$isSnowflake($guild['id'] ?? null)) {
        continue;
    }

    $guildId = (string)$guild['id'];
    $name = mb_substr(trim((string)($guild['name'] ?? '')), 0, 120);
    $icon = preg_match('/^[a-zA-Z0-9_]{1,80}$/', (string)($guild['icon'] ?? '')) ? (string)$guild['icon'] : null;
    $ownerId = $isSnowflake($guild['owner_id'] ?? null) ? (string)$guild['owner_id'] : null;
    $memberCount = max(0, (int)($guild['member_count'] ?? 0));
    $locale = preg_match('/^[a-zA-Z-]{2,12}$/', (string)($guild['locale'] ?? '')) ? (string)$guild['locale'] : null;

    $joinedUnix = (int)($guild['joined_at'] ?? 0);
    $joinedAt = $joinedUnix > 0 ? date('Y-m-d H:i:s', $joinedUnix) : null;

    $upsert->bind_param('ssssiss', $guildId, $name, $icon, $ownerId, $memberCount, $locale, $joinedAt);

    if (!$upsert->execute()) {
        $upsert->close();
        bot_fail('Unable to store the guilds.', 500);
    }

    $present[] = $guildId;
}

$upsert->close();

/**
 * Segna come lasciati i server indicati: le impostazioni restano, cosi' chi
 * rimette il bot ritrova tutto com'era.
 *
 * @param string[] $ids
 */
$markLeft = static function (mysqli $mysqli, string $condition, array $ids): void {
    if ($ids === []) {
        return;
    }

    $stmt = $mysqli->prepare(
        'UPDATE discord_guilds SET bot_present = 0, left_at = COALESCE(left_at, NOW())
         WHERE bot_present = 1 AND guild_id ' . $condition . ' (' . implode(',', array_fill(0, count($ids), '?')) . ')'
    );

    if (!$stmt) {
        bot_fail('Unable to update the guilds.', 500);
    }

    $stmt->bind_param(str_repeat('s', count($ids)), ...$ids);
    $stmt->execute();
    $stmt->close();
};

$leftIds = array_values(array_filter($left, $isSnowflake));
$markLeft($mysqli, 'IN', $leftIds);

// Elenco completo: tutto quello che non c'e' e' un server da cui il bot e'
// uscito mentre era spento. Con un elenco vuoto non si tocca nulla: e' piu'
// probabile un bot appena partito che non ha ancora caricato i server che un
// bot uscito da tutti.
if ($full && $present !== []) {
    $markLeft($mysqli, 'NOT IN', $present);
}

$blocked = [];
$result = $mysqli->query('SELECT guild_id FROM discord_guilds WHERE blocked = 1');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $blocked[] = (string)$row['guild_id'];
    }
    $result->free();
}

bot_json([
    'ok' => true,
    'available' => true,
    'stored' => count($present),
    'left' => count($leftIds),
    'blocked' => $blocked,
]);
