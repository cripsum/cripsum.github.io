<?php
declare(strict_types=1);

/**
 * Chi sta guardando il pannello e quali server puo' gestire.
 *
 * E' la prima chiamata della pagina di scelta. Non fallisce se manca
 * qualcosa: dice cosa manca (accesso al sito, Discord collegato, bot che non
 * risponde), cosi' la pagina puo' spiegarlo invece di mostrare un errore.
 */

require_once __DIR__ . '/bootstrap.php';

$user = botpanel_current_user($mysqli);

if ($user === null) {
    botpanel_json(['ok' => true, 'logged' => false]);
}

$base = [
    'ok' => true,
    'logged' => true,
    'username' => $user['username'],
    'csrf' => csrf_token(),
];

if ($user['discord_id'] === null) {
    botpanel_json($base + ['discord_linked' => false]);
}

$refresh = ($_GET['refresh'] ?? '') === '1';
if ($refresh) {
    // Ricalcolare l'elenco costa al bot una richiesta a Discord per server.
    botpanel_rate('guilds_refresh', 6, 60);
}

$list = botpanel_guilds($user, $refresh);
cripsum_release_session();

if ($list === null) {
    botpanel_json($base + ['discord_linked' => true, 'bot_online' => false, 'guilds' => []]);
}

botpanel_json($base + [
    'discord_linked' => true,
    'bot_online' => true,
    'guilds' => $list['guilds'],
    'invite_url' => $list['invite_url'],
]);
