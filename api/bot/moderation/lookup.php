<?php
declare(strict_types=1);

/**
 * Scheda completa di un utente per lo staff: account, contatti, accessi,
 * economia, collezione, ticket, richiami Discord e azioni di moderazione.
 *
 * Espone dati personali (email, indirizzi IP), quindi richiede un account
 * Cripsum admin o owner collegato, e ogni consultazione finisce in admin_logs.
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

/**
 * Legge dalla riga utente solo le colonne che esistono davvero: lo schema e'
 * cresciuto per stratificazioni e non tutte sono ovunque.
 */
$optional = [
    'email' => 'email',
    'email_verificata' => 'email_verified',
    'data_creazione' => 'created_at',
    'ultimo_accesso' => 'last_seen',
    'custom_alias' => 'alias',
    'discord_username' => 'discord_username',
    'discord_connected_at' => 'discord_connected_at',
    'godoshards_balance' => 'godoshards',
    'last_premium_claim' => 'last_premium_claim',
    'motivo_ban' => 'ban_reason',
    'banned_until' => 'banned_until',
    'banned_at' => 'banned_at',
    'banned_by' => 'banned_by_id',
    'profile_visibility' => 'profile_visibility',
    'profile_views' => 'profile_views',
];

$available = [];
foreach ($optional as $column => $key) {
    if (auth_column_exists($mysqli, 'utenti', $column)) {
        $available[$column] = $key;
    }
}

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

if ($available) {
    $columns = implode(', ', array_map(static fn($c) => "`$c`", array_keys($available)));
    $stmt = $mysqli->prepare("SELECT $columns FROM utenti WHERE id = ? LIMIT 1");

    if ($stmt) {
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        foreach ($available as $column => $key) {
            $account[$key] = $row[$column] ?? null;
        }
    }
}

// Chi ha eseguito il ban, se la colonna c'e'.
if (!empty($account['banned_by_id'])) {
    $bannedBy = bot_find_user_by_id($mysqli, (int)$account['banned_by_id']);
    $account['banned_by'] = $bannedBy['username'] ?? ('#' . (int)$account['banned_by_id']);
}

// Ultimi indirizzi usati e tentativi di accesso falliti: vengono da
// login_attempts, la stessa tabella che regge il rate limit del sito.
$access = ['ips' => [], 'failed_24h' => 0];

if (auth_table_exists($mysqli, 'login_attempts')) {
    $stmt = $mysqli->prepare(
        'SELECT ip_address, MAX(created_at) AS ultimo, COUNT(*) AS accessi
         FROM login_attempts
         WHERE user_id = ? AND success = 1
         GROUP BY ip_address
         ORDER BY ultimo DESC
         LIMIT 5'
    );

    if ($stmt) {
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $access['ips'] = array_map(static function (array $row): array {
            return [
                'ip' => (string)$row['ip_address'],
                'last_seen' => $row['ultimo'],
                'logins' => (int)$row['accessi'],
            ];
        }, $rows);
    }

    $stmtFailed = $mysqli->prepare(
        'SELECT COUNT(*) AS totale FROM login_attempts
         WHERE user_id = ? AND success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)'
    );

    if ($stmtFailed) {
        $stmtFailed->bind_param('i', $targetId);
        $stmtFailed->execute();
        $access['failed_24h'] = (int)($stmtFailed->get_result()->fetch_assoc()['totale'] ?? 0);
        $stmtFailed->close();
    }
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

$collection = ['characters' => 0, 'copies' => 0, 'achievements' => 0, 'codes' => 0];
$stmtCollection = $mysqli->prepare(
    'SELECT
        (SELECT COUNT(DISTINCT personaggio_id) FROM utenti_personaggi WHERE utente_id = ?) AS personaggi,
        (SELECT COALESCE(SUM(`quantità`), 0) FROM utenti_personaggi WHERE utente_id = ?) AS copie,
        (SELECT COUNT(DISTINCT achievement_id) FROM utenti_achievement WHERE utente_id = ?) AS achievement'
);

if ($stmtCollection) {
    $stmtCollection->bind_param('iii', $targetId, $targetId, $targetId);
    $stmtCollection->execute();
    $row = $stmtCollection->get_result()->fetch_assoc() ?: [];
    $stmtCollection->close();
    $collection['characters'] = (int)($row['personaggi'] ?? 0);
    $collection['copies'] = (int)($row['copie'] ?? 0);
    $collection['achievements'] = (int)($row['achievement'] ?? 0);
}

if (auth_table_exists($mysqli, 'codici_riscattati')) {
    $stmtCodes = $mysqli->prepare('SELECT COUNT(*) AS totale FROM codici_riscattati WHERE user_id = ?');
    if ($stmtCodes) {
        $stmtCodes->bind_param('i', $targetId);
        $stmtCodes->execute();
        $collection['codes'] = (int)($stmtCodes->get_result()->fetch_assoc()['totale'] ?? 0);
        $stmtCodes->close();
    }
}

// Provvedimenti gia' presi sul sito contro questo account.
$siteActions = [];
if (auth_table_exists($mysqli, 'admin_logs')) {
    $stmt = $mysqli->prepare(
        'SELECT l.action, l.details, l.created_at, u.username AS admin
         FROM admin_logs l
         LEFT JOIN utenti u ON u.id = l.admin_id
         WHERE l.target_user_id = ?
         ORDER BY l.created_at DESC
         LIMIT 5'
    );

    if ($stmt) {
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $siteActions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

bot_log_action($mysqli, $actor, 'lookup_user', $targetId, ['source' => 'discord']);

bot_json([
    'ok' => true,
    'linked' => true,
    'account' => $account,
    'access' => $access,
    'tickets' => $tickets,
    'collection' => $collection,
    'site_actions' => $siteActions,
    'discord' => [
        'warnings' => $warnings,
        'actions' => $modActions,
    ],
]);
