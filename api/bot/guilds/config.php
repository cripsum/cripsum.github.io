<?php
declare(strict_types=1);

/**
 * Impostazioni dei moduli, server per server.
 *
 * Il bot le legge tutte insieme all'avvio (`all=1`) e poi le tiene in
 * memoria: non richiama a ogni comando. Quando il pannello salva qualcosa e'
 * il sito ad avvisarlo, e solo allora rilegge quel server (`guild_id=`).
 *
 * Qui si legge soltanto. Scrivere e' compito del pannello, che controlla chi
 * sta salvando e che cosa.
 */

require_once __DIR__ . '/../bootstrap.php';

bot_require_key();
bot_require_method('GET');

if (!auth_table_exists($mysqli, 'discord_guild_settings') || !auth_table_exists($mysqli, 'discord_guilds')) {
    bot_json(['ok' => true, 'available' => false, 'guilds' => (object)[]]);
}

$guildId = bot_snowflake('guild_id', false);
$all = bot_text('all', 1) === '1';

if ($guildId === '' && !$all) {
    bot_fail('Missing guild_id.', 400);
}

if ($guildId !== '') {
    $stmt = $mysqli->prepare(
        'SELECT guild_id, module, enabled, config, version
         FROM discord_guild_settings WHERE guild_id = ?'
    );
} else {
    // Solo i server in cui il bot c'e' ancora: le impostazioni di quelli
    // lasciati restano nel database ma non servono in memoria.
    $stmt = $mysqli->prepare(
        'SELECT s.guild_id, s.module, s.enabled, s.config, s.version
         FROM discord_guild_settings s
         JOIN discord_guilds g ON g.guild_id = s.guild_id AND g.bot_present = 1'
    );
}

if (!$stmt) {
    bot_fail('Unable to read the settings.', 500);
}

if ($guildId !== '') {
    $stmt->bind_param('s', $guildId);
}

$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$guilds = [];

// Il server chiesto per nome compare sempre, anche senza righe: al bot serve
// distinguere "niente di configurato" da "non ho ricevuto risposta".
if ($guildId !== '') {
    $guilds[$guildId] = ['modules' => []];
}

foreach ($rows as $row) {
    $config = $row['config'] !== null ? json_decode((string)$row['config'], true) : null;

    // Le impostazioni di un modulo sono sempre un oggetto con dei nomi: un
    // testo che non e' JSON, un elenco o un oggetto vuoto escono tutti come
    // {}, perche' in PHP un oggetto vuoto e un elenco vuoto sono la stessa
    // cosa e al bot arriverebbe [].
    $isObject = is_array($config) && $config !== [] && array_keys($config) !== range(0, count($config) - 1);

    $guilds[(string)$row['guild_id']]['modules'][(string)$row['module']] = [
        'enabled' => (int)$row['enabled'] === 1,
        'config' => $isObject ? $config : (object)[],
        'version' => (int)$row['version'],
    ];
}

// Un elenco vuoto in PHP diventerebbe [] in JSON: per il bot e' un oggetto.
foreach ($guilds as $id => $guild) {
    $guilds[$id]['modules'] = (object)$guild['modules'];
}

bot_json([
    'ok' => true,
    'available' => true,
    'guilds' => (object)$guilds,
]);
