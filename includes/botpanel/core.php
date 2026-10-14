<?php
declare(strict_types=1);

/**
 * Pannello del bot: chi puo' entrare, e cosa puo' fare.
 *
 * Tutto il pannello passa da qui. Le regole sono poche e non hanno eccezioni:
 *
 * 1. Chi sei lo dice la sessione del sito, e il tuo Discord e' quello
 *    confermato da Discord quando hai collegato l'account. Un ID scritto a
 *    mano nel profilo non conta (vedi cripsum_linked_discord_sql()).
 * 2. Cosa puoi fare in un server lo dice il bot, che ci sta dentro: sei
 *    proprietario, o hai «Amministratore» o «Gestisci server»? Il sito lo
 *    chiede prima di mostrare o salvare qualsiasi cosa. Per le letture la
 *    risposta vale cinque minuti; prima di ogni salvataggio si richiede da
 *    capo, facendo rileggere il membro da Discord.
 * 3. Se il bot non risponde, la risposta e' no. Mai "nel dubbio si'".
 * 4. Niente di quello che arriva dal browser viene creduto: ogni canale e
 *    ogni ruolo salvato deve esistere nell'elenco che il bot da' per quel
 *    server (vedi includes/botpanel/modules.php).
 * 5. Ogni salvataggio finisce nel registro del server, con il prima e il
 *    dopo.
 */

require_once __DIR__ . '/../security_helpers.php';
require_once __DIR__ . '/../bot_client.php';

/** Per quanto vale un "si', puo' gestirlo" quando si sta solo leggendo. */
const BOTPANEL_ACCESS_TTL = 300;

/** Per quanto si tiene l'elenco dei server di una persona. */
const BOTPANEL_GUILDS_TTL = 300;

/**
 * @param array<string, mixed> $payload
 */
function botpanel_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Risposta d'errore. `$code` e' quello che legge la pagina per decidere cosa
 * mostrare; `$message` e' per chi guarda la console.
 *
 * @param array<string, mixed> $extra
 */
function botpanel_fail(string $code, string $message, int $status = 400, array $extra = []): void
{
    botpanel_json(['ok' => false, 'code' => $code, 'error' => $message] + $extra, $status);
}

function botpanel_is_snowflake($value): bool
{
    return is_string($value) && preg_match('/^\d{15,25}$/', $value) === 1;
}

/**
 * Le tabelle del pannello ci sono? Finche' manca la migrazione il pannello
 * lo dice, invece di rompersi.
 */
function botpanel_tables_ready(mysqli $mysqli): bool
{
    return auth_table_exists($mysqli, 'discord_guilds')
        && auth_table_exists($mysqli, 'discord_guild_settings')
        && auth_table_exists($mysqli, 'discord_guild_audit');
}

/**
 * L'utente collegato, con il suo Discord confermato (o null se non lo e').
 *
 * @return array{id: int, username: string, ruolo: string, discord_id: ?string}|null
 */
function botpanel_current_user(mysqli $mysqli): ?array
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    $stmt = $mysqli->prepare(
        'SELECT id, username, ruolo, isBannato, ' . cripsum_linked_discord_sql() . ' AS discord_id
         FROM utenti WHERE id = ? LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || (int)($row['isBannato'] ?? 0) === 1) {
        return null;
    }

    $discordId = trim((string)($row['discord_id'] ?? ''));

    return [
        'id' => (int)$row['id'],
        'username' => (string)$row['username'],
        'ruolo' => (string)($row['ruolo'] ?? 'utente'),
        'discord_id' => botpanel_is_snowflake($discordId) ? $discordId : null,
    ];
}

/**
 * Come botpanel_current_user(), ma ferma la richiesta se manca qualcosa.
 *
 * @return array{id: int, username: string, ruolo: string, discord_id: string}
 */
function botpanel_require_user(mysqli $mysqli): array
{
    $user = botpanel_current_user($mysqli);

    if ($user === null) {
        botpanel_fail('login_required', 'Devi accedere al sito.', 401);
    }

    if ($user['discord_id'] === null) {
        botpanel_fail('discord_not_linked', 'Collega il tuo account Discord per usare il pannello.', 403);
    }

    return $user;
}

/**
 * Una richiesta che cambia qualcosa: solo POST, solo da una pagina del sito,
 * solo con il token della sessione.
 */
function botpanel_require_write(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        botpanel_fail('method', 'Metodo non consentito.', 405);
    }

    // Un sito esterno non puo' leggere il token, ma si controlla anche da
    // dove arriva la richiesta: due serrature diverse sulla stessa porta.
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $originHost = strtolower((string)parse_url($origin, PHP_URL_HOST));
        $host = strtolower((string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));

        if ($originHost === '' || $originHost !== $host) {
            botpanel_fail('origin', 'Richiesta da un\'origine non consentita.', 403);
        }
    }

    if (!csrf_validate((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
        botpanel_fail('csrf', 'Sessione scaduta: ricarica la pagina.', 403);
    }
}

/**
 * Corpo JSON della richiesta, sempre come array.
 *
 * @return array<string, mixed>
 */
function botpanel_input(): array
{
    static $input = null;

    if ($input === null) {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        $input = is_array($decoded) ? $decoded : [];
    }

    return $input;
}

/**
 * Limite di frequenza per sessione: `$max` richieste ogni `$window` secondi.
 * Serve a non far martellare il bot (e Discord) da una pagina impazzita o da
 * qualcuno che ci prova.
 */
function botpanel_rate(string $bucket, int $max, int $window): void
{
    $now = time();
    $hits = array_values(array_filter(
        (array)($_SESSION['botpanel_rate'][$bucket] ?? []),
        static fn($at): bool => is_int($at) && $at > $now - $window
    ));

    if (count($hits) >= $max) {
        botpanel_fail('rate_limited', 'Troppe richieste: aspetta qualche secondo.', 429, [
            'retry_after' => max(1, ($hits[0] + $window) - $now),
        ]);
    }

    $hits[] = $now;
    $_SESSION['botpanel_rate'][$bucket] = $hits;
}

/**
 * Chiamata firmata al bot. Restituisce la risposta decodificata, o null se
 * il bot non ha risposto o ha risposto con qualcosa che non e' JSON.
 *
 * @param array<string, mixed> $payload
 * @return array<string, mixed>|null
 */
function botpanel_bot(string $path, array $payload, int $timeout = 6): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $body = (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init(cripsum_bot_endpoint($path));
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => cripsum_bot_headers([], $path, $body),
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $decoded = is_string($response) ? json_decode($response, true) : null;

    return is_array($decoded) ? $decoded : null;
}

/**
 * I server in cui questa persona puo' gestire il pannello.
 *
 * L'elenco lo calcola il bot con lo stesso controllo che protegge ogni
 * pagina. Si tiene in sessione qualche minuto solo per non ricalcolarlo a
 * ogni clic: non decide nulla, serve a disegnare la pagina di scelta.
 *
 * @param array{discord_id: string} $user
 * @return array{guilds: array<int, array<string, mixed>>, invite_url: ?string}|null null = il bot non risponde
 */
function botpanel_guilds(array $user, bool $refresh = false): ?array
{
    $cached = $_SESSION['botpanel_guilds'] ?? null;

    if (!$refresh
        && is_array($cached)
        && ($cached['discord_id'] ?? '') === $user['discord_id']
        && time() - (int)($cached['at'] ?? 0) < BOTPANEL_GUILDS_TTL
    ) {
        return $cached['data'];
    }

    $response = botpanel_bot('/v1/panel/guilds', ['discord_user_id' => $user['discord_id']], 12);
    if ($response === null || empty($response['success'])) {
        return null;
    }

    $guilds = [];
    foreach ((array)($response['guilds'] ?? []) as $guild) {
        if (!is_array($guild) || !botpanel_is_snowflake($guild['id'] ?? null)) {
            continue;
        }

        $guilds[] = [
            'id' => (string)$guild['id'],
            'name' => mb_substr((string)($guild['name'] ?? ''), 0, 120),
            'icon' => preg_match('/^[a-zA-Z0-9_]{1,80}$/', (string)($guild['icon'] ?? '')) ? (string)$guild['icon'] : null,
            'member_count' => max(0, (int)($guild['member_count'] ?? 0)),
            'is_home' => !empty($guild['is_home']),
            'owner' => !empty($guild['owner']),
        ];
    }

    $invite = (string)($response['invite_url'] ?? '');
    $avatar = (string)($response['bot']['avatar'] ?? '');
    $data = [
        'guilds' => $guilds,
        // Solo un indirizzo di Discord: finisce in un link della pagina.
        'invite_url' => str_starts_with($invite, 'https://discord.com/oauth2/authorize?') ? $invite : null,
        'bot' => [
            'name' => mb_substr((string)($response['bot']['name'] ?? 'Poppy'), 0, 40),
            // Solo immagini di Discord: finisce in un <img> della pagina.
            'avatar' => str_starts_with($avatar, 'https://cdn.discordapp.com/') ? $avatar : null,
        ],
    ];

    $_SESSION['botpanel_guilds'] = ['at' => time(), 'discord_id' => $user['discord_id'], 'data' => $data];

    return $data;
}

/**
 * Puo' questa persona gestire questo server? Se no, la richiesta finisce qui.
 *
 * `$fresh` va messo a true prima di ogni salvataggio: la risposta non viene
 * presa dalla sessione, e il bot rilegge il membro da Discord.
 *
 * @param array{discord_id: string} $user
 * @return array{owner: bool, administrator: bool, manage_guild: bool, top_role_position: int, is_home: bool}
 */
function botpanel_access(array $user, string $guildId, bool $fresh = false): array
{
    if (!botpanel_is_snowflake($guildId)) {
        botpanel_fail('bad_guild', 'Server non valido.', 400);
    }

    $cached = $_SESSION['botpanel_access'][$guildId] ?? null;

    if (!$fresh
        && is_array($cached)
        && ($cached['discord_id'] ?? '') === $user['discord_id']
        && time() - (int)($cached['at'] ?? 0) < BOTPANEL_ACCESS_TTL
    ) {
        return $cached['data'];
    }

    // Da qui in poi qualsiasi esito diverso da un si' esplicito toglie anche
    // quello che c'era in sessione: un permesso revocato non sopravvive.
    unset($_SESSION['botpanel_access'][$guildId]);

    $response = botpanel_bot('/v1/panel/access', [
        'guild_id' => $guildId,
        'discord_user_id' => $user['discord_id'],
        'fresh' => $fresh,
    ]);

    if ($response === null) {
        botpanel_fail('bot_unreachable', 'Il bot non risponde: riprova fra poco.', 503);
    }

    if (empty($response['success'])) {
        $missing = ($response['error'] ?? '') === 'bot_not_in_guild';
        botpanel_fail(
            $missing ? 'bot_not_in_guild' : 'bot_unreachable',
            $missing ? 'Il bot non e\' in questo server.' : 'Il bot non ha saputo rispondere.',
            $missing ? 404 : 503
        );
    }

    $access = is_array($response['access'] ?? null) ? $response['access'] : [];

    if (($response['guild_id'] ?? '') !== $guildId || empty($access['can_manage'])) {
        botpanel_fail('forbidden', 'Non hai i permessi per gestire questo server.', 403);
    }

    $data = [
        'owner' => !empty($access['owner']),
        'administrator' => !empty($access['administrator']),
        'manage_guild' => !empty($access['manage_guild']),
        'top_role_position' => (int)($access['top_role_position'] ?? 0),
        'is_home' => !empty($response['is_home']),
    ];

    // Al massimo una ventina di server in sessione: il piu' vecchio esce.
    $all = (array)($_SESSION['botpanel_access'] ?? []);
    if (count($all) >= 20) {
        uasort($all, static fn($a, $b): int => (int)($a['at'] ?? 0) <=> (int)($b['at'] ?? 0));
        array_shift($all);
    }
    $all[$guildId] = ['at' => time(), 'discord_id' => $user['discord_id'], 'data' => $data];
    $_SESSION['botpanel_access'] = $all;

    return $data;
}

/**
 * Canali, ruoli ed emoji del server, cosi' come li vede il bot adesso.
 * Senza, non si puo' ne' disegnare una pagina ne' controllare un salvataggio.
 *
 * @return array<string, mixed>
 */
function botpanel_meta(string $guildId): array
{
    $response = botpanel_bot('/v1/panel/meta', ['guild_id' => $guildId], 8);

    if ($response === null || empty($response['success']) || !is_array($response['guild'] ?? null)) {
        botpanel_fail('bot_unreachable', 'Il bot non risponde: riprova fra poco.', 503);
    }

    if (($response['guild']['id'] ?? '') !== $guildId) {
        botpanel_fail('bot_unreachable', 'Risposta del bot non valida.', 503);
    }

    return $response['guild'];
}

/**
 * Impostazioni salvate di un server: modulo => [enabled, config, version].
 *
 * @return array<string, array{enabled: bool, config: array<string, mixed>, version: int}>
 */
function botpanel_settings(mysqli $mysqli, string $guildId): array
{
    $stmt = $mysqli->prepare(
        'SELECT module, enabled, config, version FROM discord_guild_settings WHERE guild_id = ?'
    );

    if (!$stmt) {
        botpanel_fail('database', 'Impossibile leggere le impostazioni.', 500);
    }

    $stmt->bind_param('s', $guildId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $settings = [];
    foreach ($rows as $row) {
        $config = $row['config'] !== null ? json_decode((string)$row['config'], true) : null;

        $settings[(string)$row['module']] = [
            'enabled' => (int)$row['enabled'] === 1,
            'config' => is_array($config) ? $config : [],
            'version' => (int)$row['version'],
        ];
    }

    return $settings;
}

/**
 * Riga nel registro del server.
 *
 * @param array{id: int, discord_id: string} $user
 * @param mixed $before
 * @param mixed $after
 */
function botpanel_audit(
    mysqli $mysqli,
    string $guildId,
    array $user,
    string $module,
    string $action,
    $before,
    $after,
    string $source = 'panel'
): void {
    $stmt = $mysqli->prepare(
        'INSERT INTO discord_guild_audit
            (guild_id, actor_user_id, actor_discord_id, source, module, action, before_json, after_json, ip)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$stmt) {
        return;
    }

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    $beforeJson = $before === null ? null : json_encode($before, $flags);
    $afterJson = $after === null ? null : json_encode($after, $flags);
    $ip = function_exists('auth_client_ip') ? auth_client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $userId = (int)$user['id'];
    $discordId = (string)$user['discord_id'];

    $stmt->bind_param('sisssssss', $guildId, $userId, $discordId, $source, $module, $action, $beforeJson, $afterJson, $ip);
    $stmt->execute();
    $stmt->close();
}

/**
 * Salva le impostazioni di un modulo.
 *
 * `$expectedVersion` e' la versione che aveva davanti chi sta salvando: se
 * nel frattempo qualcun altro ha salvato, non si sovrascrive il suo lavoro e
 * si risponde con un conflitto.
 *
 * @param array<string, mixed> $config gia' controllata da botpanel_validate_module()
 * @param array{id: int, discord_id: string} $user
 * @return array{ok: bool, version: int, conflict?: bool}
 */
function botpanel_save_settings(
    mysqli $mysqli,
    string $guildId,
    string $module,
    bool $enabled,
    array $config,
    int $expectedVersion,
    array $user,
    string $action = 'save',
    string $source = 'panel'
): array {
    $current = botpanel_settings($mysqli, $guildId)[$module] ?? null;
    $currentVersion = $current['version'] ?? 0;

    if ($currentVersion !== $expectedVersion) {
        return ['ok' => false, 'version' => $currentVersion, 'conflict' => true];
    }

    $json = (string)json_encode((object)$config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (strlen($json) > 200000) {
        botpanel_fail('too_large', 'Impostazioni troppo grandi.', 413);
    }

    $enabledInt = $enabled ? 1 : 0;
    $userId = (int)$user['id'];
    $discordId = (string)$user['discord_id'];

    if ($current === null) {
        $stmt = $mysqli->prepare(
            'INSERT INTO discord_guild_settings
                (guild_id, module, enabled, config, version, updated_by_user_id, updated_by_discord_id)
             VALUES (?, ?, ?, ?, 1, ?, ?)'
        );

        if (!$stmt) {
            botpanel_fail('database', 'Impossibile salvare.', 500);
        }

        $stmt->bind_param('ssisis', $guildId, $module, $enabledInt, $json, $userId, $discordId);
        $saved = $stmt->execute();
        $stmt->close();

        // Due salvataggi nello stesso istante: il secondo trova la riga gia'
        // creata dal primo, e deve ripartire da quella.
        if (!$saved) {
            return ['ok' => false, 'version' => 1, 'conflict' => true];
        }

        $newVersion = 1;
    } else {
        // La versione sta anche nella condizione: se e' cambiata fra la
        // lettura qui sopra e questa riga, non si aggiorna niente.
        $stmt = $mysqli->prepare(
            'UPDATE discord_guild_settings
             SET enabled = ?, config = ?, version = version + 1, updated_by_user_id = ?, updated_by_discord_id = ?
             WHERE guild_id = ? AND module = ? AND version = ?'
        );

        if (!$stmt) {
            botpanel_fail('database', 'Impossibile salvare.', 500);
        }

        $stmt->bind_param('isisssi', $enabledInt, $json, $userId, $discordId, $guildId, $module, $expectedVersion);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();

        if ($changed !== 1) {
            return ['ok' => false, 'version' => $currentVersion, 'conflict' => true];
        }

        $newVersion = $expectedVersion + 1;
    }

    botpanel_audit(
        $mysqli,
        $guildId,
        $user,
        $module,
        $action,
        $current === null ? null : ['enabled' => $current['enabled'], 'config' => $current['config']],
        ['enabled' => $enabled, 'config' => $config],
        $source
    );

    return ['ok' => true, 'version' => $newVersion];
}

/**
 * Dice al bot di rileggere le impostazioni di un server. Se non risponde non
 * e' un errore del salvataggio: le rilegge comunque da solo entro mezz'ora.
 */
function botpanel_notify_reload(string $guildId): bool
{
    $response = botpanel_bot('/v1/guilds/' . $guildId . '/reload', [], 5);

    return is_array($response) && !empty($response['reloaded']);
}
