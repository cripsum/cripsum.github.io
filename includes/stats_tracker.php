<?php

/**
 * Cripsum™ — Stats Tracker
 *
 * Registra le statistiche utente su cui si basa il Rewind.
 *
 * USO:
 *   require_once __DIR__ . '/stats_tracker.php';
 *   stats_track($mysqli, $userId, 'gacha_pulls', 10);
 *   stats_track_many($mysqli, $userId, ['gacha_pulls' => 10, 'gacha_epic' => 2]);
 *
 * Regole di questo file:
 *   - Non esegue mai DDL. Se migrations/2026_09_rewind_and_stats.sql non è
 *     stata applicata, ogni funzione esce silenziosamente.
 *   - Nessun identificatore SQL arriva dall'esterno: i nomi di colonna
 *     escono sempre dalla whitelist STATS_METRICS.
 *   - Non lancia mai eccezioni verso il chiamante. Una statistica persa
 *     non deve far fallire un pull del gacha o l'invio di un messaggio.
 *
 * @package Cripsum\Stats
 */

require_once __DIR__ . '/security_helpers.php';

/**
 * Metriche tracciabili -> colonna di user_daily_stats.
 *
 * La chiave è il nome pubblico usato dai chiamanti, il valore è la colonna.
 * Sono identici per scelta, ma il passaggio dalla mappa garantisce che una
 * stringa arbitraria non finisca mai dentro una query.
 */
const STATS_METRICS = [
    // Tempo e presenza
    'page_views'             => 'page_views',
    'sessions_count'         => 'sessions_count',

    // Gacha e collezione
    'gacha_pulls'            => 'gacha_pulls',
    'gacha_spent'            => 'gacha_spent',
    'gacha_rare'             => 'gacha_rare',
    'gacha_epic'             => 'gacha_epic',
    'gacha_special'          => 'gacha_special',
    'gacha_secret'           => 'gacha_secret',
    'gacha_new_chars'        => 'gacha_new_chars',
    'gacha_5050_won'         => 'gacha_5050_won',
    'gacha_5050_lost'        => 'gacha_5050_lost',
    'lootboxes_opened'       => 'lootboxes_opened',

    // Social
    'msg_global'             => 'msg_global',
    'msg_private'            => 'msg_private',
    'msg_group'              => 'msg_group',
    'friends_added'          => 'friends_added',
    'likes_given'            => 'likes_given',
    'likes_received'         => 'likes_received',
    'comments_made'          => 'comments_made',
    'profile_visits_made'    => 'profile_visits_made',
    'profile_views_received' => 'profile_views_received',

    // Contenuti
    'posts_created'          => 'posts_created',
    'shitposts_created'      => 'shitposts_created',
    'votes_cast'             => 'votes_cast',
    'downloads'              => 'downloads',
    'edits_viewed'           => 'edits_viewed',
    'profile_edits'          => 'profile_edits',
    'pedia_reads'            => 'pedia_reads',

    // Progressione ed economia
    'achievements_unlocked'  => 'achievements_unlocked',
    'achievement_points'     => 'achievement_points',
    'missions_completed'     => 'missions_completed',
    'missions_claimed'       => 'missions_claimed',
    'godos_earned'           => 'godos_earned',
    'godos_spent'            => 'godos_spent',
    'shards_earned'          => 'shards_earned',
    'shards_spent'           => 'shards_spent',

    // Giochi
    'duels_played'           => 'duels_played',
    'duels_won'              => 'duels_won',
    'duels_lost'             => 'duels_lost',
    'subway_runs'            => 'subway_runs',
    'pullspot_rounds'        => 'pullspot_rounds',
    'pullspot_won'           => 'pullspot_won',
    'pullspot_first_try'     => 'pullspot_first_try',
];

/** Metriche che vanno tenute al massimo raggiunto invece che sommate. */
const STATS_MAX_METRICS = [
    'max_pity_hit'   => 'max_pity_hit',
    'subway_best_ms' => 'subway_best_ms',
];

/** Un buco di oltre 30 minuti chiude la sessione e ne apre una nuova. */
const STATS_SESSION_GAP = 1800;

/** Tetto ai secondi accettati da un singolo heartbeat. */
const STATS_HEARTBEAT_MAX_SECONDS = 120;

/** Sezioni riconosciute del sito, per "le tue pagine preferite". */
const STATS_PAGE_KEYS = [
    'home',
    'profilo',
    'rewind',
    'chat',
    'global-chat',
    'inbox',
    'amici',
    'lootbox',
    'gacha',
    'negozio',
    'inventario',
    'achievements',
    'missions',
    'subway',
    'pullspot',
    'game',
    'gambling',
    'goonland',
    'shitpost',
    'rimasti',
    'cripsumpedia',
    'edits',
    'download',
    'tiktokpedia',
    'merch',
    'donazioni',
    'impostazioni',
    'altro',
];

// ─────────────────────────────────────────────────────────────
//  DISPONIBILITÀ
// ─────────────────────────────────────────────────────────────

/**
 * Costo del tracciamento, e come lo teniamo basso.
 *
 * Verificare lo schema e le preferenze a ogni evento significherebbe tre o
 * quattro interrogazioni a information_schema per richiesta: più della
 * scrittura che devono proteggere. Entrambe le risposte cambiano di rado,
 * quindi vivono in sessione:
 *
 *   - lo schema per un'ora (la migration si applica una volta sola);
 *   - le preferenze finché l'utente non le modifica, e in quel momento
 *     stats_forget_prefs() ripulisce la cache.
 *
 * Dopo la prima richiesta di una sessione, un evento costa esattamente una
 * query: l'INSERT ... ON DUPLICATE KEY UPDATE che serve davvero.
 */
const STATS_SCHEMA_CACHE_TTL = 3600;

/** Legge un valore dalla cache di sessione, se ancora valido. */
function stats_session_cache_get(string $key, int $ttl = 0): ?int
{
    if (!isset($_SESSION) || !array_key_exists($key, $_SESSION)) {
        return null;
    }

    if ($ttl > 0) {
        $storedAt = (int)($_SESSION[$key . '_at'] ?? 0);
        if (($_SERVER['REQUEST_TIME'] ?? time()) - $storedAt >= $ttl) {
            return null;
        }
    }

    return (int)$_SESSION[$key];
}

/**
 * Scrive in cache di sessione.
 *
 * Se la sessione è già stata chiusa (cripsum_release_session) la scrittura
 * non viene persistita: nessun errore, semplicemente la richiesta successiva
 * ricalcola. È il compromesso giusto — non vale la pena riaprire la sessione
 * per memorizzare un booleano.
 */
function stats_session_cache_set(string $key, int $value): void
{
    if (!isset($_SESSION)) {
        return;
    }

    $_SESSION[$key] = $value;
    $_SESSION[$key . '_at'] = $_SERVER['REQUEST_TIME'] ?? time();
}

/**
 * Vero se la migration è stata applicata.
 *
 * Controlla una sola tabella: le otto arrivano tutte dallo stesso file di
 * migration, quindi se c'è user_daily_stats ci sono anche le altre. Le
 * funzioni che toccano le tabelle accessorie restano comunque protette dai
 * loro try/catch.
 */
function stats_available(mysqli $mysqli): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $cached = stats_session_cache_get('stats_schema_ok', STATS_SCHEMA_CACHE_TTL);
    if ($cached !== null) {
        return $ready = (bool)$cached;
    }

    try {
        $ready = auth_table_exists($mysqli, 'user_daily_stats');
    } catch (Throwable $e) {
        error_log('[stats_available] ' . $e->getMessage());
        $ready = false;
    }

    stats_session_cache_set('stats_schema_ok', $ready ? 1 : 0);

    return $ready;
}

/**
 * Vero se il server sa manipolare JSON (MySQL 5.7+, MariaDB 10.2+).
 * Senza, l'istogramma orario viene semplicemente saltato.
 */
function stats_json_supported(mysqli $mysqli): bool
{
    static $supported = null;
    if ($supported !== null) {
        return $supported;
    }

    $cached = stats_session_cache_get('stats_json_ok', STATS_SCHEMA_CACHE_TTL);
    if ($cached !== null) {
        return $supported = (bool)$cached;
    }

    try {
        $res = @$mysqli->query("SELECT JSON_SET('{}', '$.a', 1) AS probe");
        $supported = $res !== false;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
    } catch (Throwable $e) {
        $supported = false;
    }

    stats_session_cache_set('stats_json_ok', $supported ? 1 : 0);

    return $supported;
}

/**
 * Vero se l'utente non ha disattivato il tracciamento dettagliato.
 * Una riga assente in user_stats_prefs vale come "attivo".
 */
function stats_tracking_allowed(mysqli $mysqli, int $userId): bool
{
    static $cache = [];
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }

    // La cache di sessione vale solo per l'utente della sessione: tracciare
    // un altro utente (l'amicizia conta per entrambi) rilegge dal database.
    $isSessionUser = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId;
    if ($isSessionUser) {
        $cached = stats_session_cache_get('stats_prefs_on');
        if ($cached !== null) {
            return $cache[$userId] = (bool)$cached;
        }
    }

    $allowed = true;

    try {
        $stmt = @$mysqli->prepare('SELECT tracking_enabled FROM user_stats_prefs WHERE utente_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row !== null) {
                $allowed = (int)$row['tracking_enabled'] === 1;
            }
        }
    } catch (Throwable $e) {
        // Tabella assente o non leggibile: il tracciamento resta attivo,
        // tanto stats_available() ha già deciso se scrivere o no.
    }

    if ($isSessionUser) {
        stats_session_cache_set('stats_prefs_on', $allowed ? 1 : 0);
    }

    return $cache[$userId] = $allowed;
}

/** Da chiamare quando l'utente cambia le proprie preferenze statistiche. */
function stats_forget_prefs(): void
{
    unset($_SESSION['stats_prefs_on'], $_SESSION['stats_prefs_on_at']);
}

// ─────────────────────────────────────────────────────────────
//  SCRITTURA DEI CONTATORI
// ─────────────────────────────────────────────────────────────

/**
 * Buffer di scrittura differita.
 *
 * Gli eventi non vanno sul database uno per uno: si accumulano qui e partono
 * in un'unica istruzione a fine richiesta. La differenza non è teorica — una
 * multi-pull da dieci chiama trackMissionProgress fino a cinquanta volte fra
 * apertura lootbox e soglie di rarità. Senza buffer sarebbero cinquanta
 * INSERT; con il buffer è una sola.
 *
 * &return array<int,array{sums:array<string,int>,maxes:array<string,int>}>
 */
function &stats_buffer(): array
{
    static $buffer = [];
    return $buffer;
}

/**
 * Incrementa una singola metrica per l'utente, sul giorno corrente.
 */
function stats_track(mysqli $mysqli, int $userId, string $metric, int $amount = 1): void
{
    stats_track_many($mysqli, $userId, [$metric => $amount]);
}

/**
 * Accumula più metriche. La scrittura avviene a fine richiesta.
 *
 * @param array<string,int> $deltas metrica => incremento
 */
function stats_track_many(mysqli $mysqli, int $userId, array $deltas): void
{
    if ($userId <= 0 || $deltas === []) {
        return;
    }

    if (!stats_available($mysqli) || !stats_tracking_allowed($mysqli, $userId)) {
        return;
    }

    $buffer = &stats_buffer();
    if (!isset($buffer[$userId])) {
        $buffer[$userId] = ['sums' => [], 'maxes' => []];
    }

    foreach ($deltas as $metric => $amount) {
        $amount = (int)$amount;
        if ($amount <= 0) {
            continue;
        }

        if (isset(STATS_METRICS[$metric])) {
            $column = STATS_METRICS[$metric];
            $buffer[$userId]['sums'][$column] = ($buffer[$userId]['sums'][$column] ?? 0) + $amount;
        } elseif (isset(STATS_MAX_METRICS[$metric])) {
            $column = STATS_MAX_METRICS[$metric];
            $buffer[$userId]['maxes'][$column] = max($buffer[$userId]['maxes'][$column] ?? 0, $amount);
        }
        // Metrica sconosciuta: ignorata di proposito, come fa il mission tracker.
    }

    stats_register_flush($mysqli);
}

/**
 * Programma lo svuotamento del buffer a fine richiesta.
 *
 * register_shutdown_function scatta anche dopo un exit(), che è il modo in cui
 * quasi tutti gli endpoint di questo sito terminano.
 */
function stats_register_flush(mysqli $mysqli): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    register_shutdown_function(static function () use ($mysqli) {
        stats_flush($mysqli);
    });
}

/**
 * Scrive il buffer sul database e lo svuota.
 *
 * Chiamabile anche a mano: serve prima di leggere le statistiche appena
 * scritte, o prima di un $mysqli->close() esplicito.
 */
function stats_flush(mysqli $mysqli): void
{
    $buffer = &stats_buffer();
    if ($buffer === []) {
        return;
    }

    $pending = $buffer;
    $buffer = [];

    $today = date('Y-m-d');

    foreach ($pending as $userId => $entry) {
        if ($entry['sums'] === [] && $entry['maxes'] === []) {
            continue;
        }

        try {
            stats_upsert_day($mysqli, (int)$userId, $today, $entry['sums'], $entry['maxes']);
        } catch (Throwable $e) {
            // Tipicamente una connessione già chiusa dal chiamante. Le
            // statistiche di quella richiesta si perdono, il resto no.
            error_log('[stats_flush] ' . $e->getMessage());
        }
    }
}

/**
 * INSERT ... ON DUPLICATE KEY UPDATE su user_daily_stats.
 *
 * I nomi di colonna arrivano già filtrati dalle whitelist, quindi
 * interpolarli è sicuro; i valori restano parametri legati.
 *
 * @param array<string,int> $sums   colonna => incremento
 * @param array<string,int> $maxes  colonna => valore candidato al massimo
 */
/**
 * Le colonne che la tabella ha davvero.
 *
 * Le metriche nuove arrivano con una migration applicata a mano: finché non lo
 * è, scriverle farebbe fallire l'intera riga del giorno e si perderebbero anche
 * le metriche vecchie. Meglio scartare quella che manca e salvare il resto.
 */
function stats_existing_columns(mysqli $mysqli): array
{
    static $columns = null;
    if ($columns !== null) return $columns;

    $columns = [];

    try {
        $result = $mysqli->query('SHOW COLUMNS FROM user_daily_stats');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if (!empty($row['Field'])) $columns[$row['Field']] = true;
            }
            $result->free();
        }
    } catch (Throwable $e) {
        error_log('[stats_existing_columns] ' . $e->getMessage());
    }

    return $columns;
}

function stats_upsert_day(mysqli $mysqli, int $userId, string $day, array $sums, array $maxes = []): void
{
    $known = stats_existing_columns($mysqli);
    if ($known !== []) {
        $sums  = array_intersect_key($sums, $known);
        $maxes = array_intersect_key($maxes, $known);
    }

    $columns = ['`utente_id`', '`day`'];
    $placeholders = ['?', '?'];
    $types = 'is';
    $values = [$userId, $day];
    $updates = [];

    foreach ($sums as $column => $amount) {
        $columns[] = "`$column`";
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $amount;
        $updates[] = "`$column` = `$column` + VALUES(`$column`)";
    }

    foreach ($maxes as $column => $amount) {
        $columns[] = "`$column`";
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $amount;
        $updates[] = "`$column` = GREATEST(`$column`, VALUES(`$column`))";
    }

    if ($updates === []) {
        return;
    }

    $sql = 'INSERT INTO user_daily_stats (' . implode(', ', $columns) . ') '
        . 'VALUES (' . implode(', ', $placeholders) . ') '
        . 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log('[stats_upsert_day] prepare fallita: ' . $mysqli->error);
        return;
    }

    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();
}

/**
 * Aggiorna i totali lifetime. Chiamata dall'heartbeat, non da ogni evento.
 *
 * Il "giorno nuovo" si decide qui, confrontando `last_active_day` con oggi
 * dentro la stessa istruzione. Dedurlo dalla riga di user_daily_stats non
 * funzionerebbe: un evento qualsiasi — una pull, un messaggio — crea quella
 * riga prima del primo battito, e il giorno resterebbe per sempre non
 * contato.
 *
 * L'ordine delle assegnazioni conta: MySQL le valuta da sinistra a destra,
 * quindi `total_days_active` e `current_streak` leggono ancora il vecchio
 * `last_active_day`, `longest_streak` legge già il nuovo `current_streak`, e
 * `last_active_day` si aggiorna per ultimo.
 */
function stats_bump_totals(mysqli $mysqli, int $userId, int $seconds, int $pageViews, bool $newSession): void
{
    if (!stats_available($mysqli)) {
        return;
    }

    $today = date('Y-m-d');
    $sessionInc = $newSession ? 1 : 0;

    try {
        $stmt = $mysqli->prepare("
            INSERT INTO user_stat_totals
                (utente_id, total_seconds, total_page_views, total_sessions, total_days_active,
                 current_streak, longest_streak, last_active_day)
            VALUES (?, ?, ?, ?, 1, 1, 1, ?)
            ON DUPLICATE KEY UPDATE
                total_seconds     = total_seconds + VALUES(total_seconds),
                total_page_views  = total_page_views + VALUES(total_page_views),
                total_sessions    = total_sessions + VALUES(total_sessions),
                total_days_active = total_days_active + IF(last_active_day <=> VALUES(last_active_day), 0, 1),
                current_streak    = IF(
                    last_active_day <=> VALUES(last_active_day),
                    current_streak,
                    IF(last_active_day = DATE_SUB(VALUES(last_active_day), INTERVAL 1 DAY), current_streak + 1, 1)
                ),
                longest_streak    = GREATEST(longest_streak, current_streak),
                last_active_day   = VALUES(last_active_day)
        ");
        if (!$stmt) {
            return;
        }

        $stmt->bind_param('iiiis', $userId, $seconds, $pageViews, $sessionInc, $today);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('[stats_bump_totals] ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────
//  HEARTBEAT
// ─────────────────────────────────────────────────────────────

/**
 * Normalizza un percorso in una delle sezioni note del sito.
 *
 * Il valore arriva dal client, quindi non viene mai usato direttamente:
 * o corrisponde a una voce di STATS_PAGE_KEYS, o diventa 'altro'.
 */
function stats_page_key_from_path(string $path): string
{
    $path = strtolower(trim($path));
    $path = (string)preg_replace('/[?#].*$/', '', $path);
    $path = trim($path, '/');

    // Toglie il prefisso di lingua.
    $segments = $path === '' ? [] : explode('/', $path);
    if (isset($segments[0]) && in_array($segments[0], ['it', 'en'], true)) {
        array_shift($segments);
    }

    $first = $segments[0] ?? '';
    $first = (string)preg_replace('/\.(php|html)$/', '', $first);

    // Alias verso le chiavi canoniche.
    $aliases = [
        ''             => 'home',
        'index'        => 'home',
        'home'         => 'home',
        'u'            => 'profilo',
        'user'         => 'profilo',
        'profile'      => 'profilo',
        'profilo'      => 'profilo',
        'rewind'       => 'rewind',
        'chat'         => 'chat',
        'global-chat'  => 'global-chat',
        'inbox'        => 'inbox',
        'amici'        => 'amici',
        'lootbox'      => 'lootbox',
        'shop'         => 'gacha',
        'negozio'      => 'negozio',
        'inventario'   => 'inventario',
        'achievements' => 'achievements',
        'pullspot'     => 'pullspot',
        'missions'     => 'missions',
        'subway'       => 'subway',
        'game'         => 'game',
        'gambling'     => 'gambling',
        'goonland'     => 'goonland',
        'shitpost'     => 'shitpost',
        'rimasti'      => 'rimasti',
        'cripsumpedia' => 'cripsumpedia',
        'edits'        => 'edits',
        'download'     => 'download',
        'tiktokpedia'  => 'tiktokpedia',
        'merch'        => 'merch',
        'donazioni'    => 'donazioni',
        'impostazioni' => 'impostazioni',
    ];

    if (isset($aliases[$first])) {
        return $aliases[$first];
    }

    // Gli alias personalizzati del profilo sono URL di primo livello
    // (RewriteRule su profile.php): valgono come visita a un profilo.
    if ($first !== '' && preg_match('/^[a-z0-9_-]{3,30}$/', $first)) {
        return 'profilo';
    }

    return 'altro';
}

/**
 * Svuota il buffer dell'heartbeat: tutto quello che si è accumulato in un
 * minuto finisce sul database in un solo passaggio.
 *
 * Il conto delle query non dipende da quante pagine ha aperto l'utente:
 *   1  riga giornaliera (secondi, visualizzazioni, istogramma orario)
 *   1  sezioni del sito (una sola INSERT multi-riga)
 *   1-2 sessione di navigazione
 *   1  totali lifetime
 *
 * @param array<string,array{seconds:int,views:int}> $buffer sezione => contatori
 */
function stats_flush_buffer(mysqli $mysqli, int $userId, array $buffer): void
{
    if ($userId <= 0 || $buffer === [] || !stats_available($mysqli) || !stats_tracking_allowed($mysqli, $userId)) {
        return;
    }

    $totalSeconds = 0;
    $totalViews = 0;
    $pages = [];

    foreach ($buffer as $pageKey => $counters) {
        $seconds = max(0, min((int)($counters['seconds'] ?? 0), STATS_HEARTBEAT_MAX_SECONDS * 4));
        $views = max(0, (int)($counters['views'] ?? 0));

        if ($seconds === 0 && $views === 0) {
            continue;
        }

        $key = in_array($pageKey, STATS_PAGE_KEYS, true) ? $pageKey : 'altro';
        if (!isset($pages[$key])) {
            $pages[$key] = ['seconds' => 0, 'views' => 0];
        }
        $pages[$key]['seconds'] += $seconds;
        $pages[$key]['views'] += $views;

        $totalSeconds += $seconds;
        $totalViews += $views;
    }

    if ($totalSeconds === 0 && $totalViews === 0) {
        return;
    }

    try {
        stats_write_day($mysqli, $userId, $totalSeconds, $totalViews);
        stats_bump_pages($mysqli, $userId, $pages);

        // La sessione si prolunga solo se c'è stato tempo attivo: una pagina
        // aperta e chiusa all'istante non apre una sessione di navigazione.
        $newSession = $totalSeconds > 0
            ? stats_touch_session($mysqli, $userId, $totalSeconds, $totalViews > 0)
            : false;

        stats_bump_totals($mysqli, $userId, $totalSeconds, $totalViews, $newSession);
    } catch (Throwable $e) {
        error_log('[stats_flush_buffer] ' . $e->getMessage());
    }
}

/** Scrive secondi, visualizzazioni, orari e istogramma sulla riga di oggi. */
function stats_write_day(mysqli $mysqli, int $userId, int $seconds, int $views): void
{
    $today = date('Y-m-d');
    $now = date('H:i:s');
    $hour = (int)date('G');

    $sums = [];
    if ($views > 0) {
        $sums['page_views'] = $views;
    }

    $extraValues = [
        'seconds_active'  => ['placeholder' => '?', 'type' => 'i', 'value' => $seconds],
        'first_seen_time' => ['placeholder' => '?', 'type' => 's', 'value' => $now],
        'last_seen_time'  => ['placeholder' => '?', 'type' => 's', 'value' => $now],
    ];
    $extraUpdates = [
        'seconds_active'  => '`seconds_active` = `seconds_active` + VALUES(`seconds_active`)',
        'first_seen_time' => '`first_seen_time` = LEAST(COALESCE(`first_seen_time`, VALUES(`first_seen_time`)), VALUES(`first_seen_time`))',
        'last_seen_time'  => '`last_seen_time` = GREATEST(COALESCE(`last_seen_time`, VALUES(`last_seen_time`)), VALUES(`last_seen_time`))',
    ];

    if ($seconds > 0 && stats_json_supported($mysqli)) {
        $extraValues['hour_buckets'] = [
            'placeholder' => 'JSON_OBJECT(?, ?)',
            'type'        => 'si',
            'value'       => [(string)$hour, $seconds],
        ];
        // $hour è un intero da date('G'): interpolarlo è sicuro.
        $extraUpdates['hour_buckets'] = sprintf(
            '`hour_buckets` = JSON_SET(COALESCE(`hour_buckets`, JSON_OBJECT()), \'$."%d"\', '
                . 'COALESCE(JSON_EXTRACT(`hour_buckets`, \'$."%d"\'), 0) + %d)',
            $hour,
            $hour,
            $seconds
        );
    }

    stats_upsert_heartbeat($mysqli, $userId, $today, $sums, $extraValues, $extraUpdates);
}


/**
 * Variante di stats_upsert_day che gestisce i valori compositi dell'heartbeat.
 *
 * @return int righe toccate: 1 = riga creata (giorno nuovo), 2 = aggiornata.
 */
function stats_upsert_heartbeat(mysqli $mysqli, int $userId, string $day, array $sums, array $extraValues, array $extraUpdates): int
{
    $columns = ['`utente_id`', '`day`'];
    $placeholders = ['?', '?'];
    $types = 'is';
    $values = [$userId, $day];
    $updates = [];

    foreach ($sums as $column => $amount) {
        $columns[] = "`$column`";
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = (int)$amount;
        $updates[] = "`$column` = `$column` + VALUES(`$column`)";
    }

    foreach ($extraValues as $column => $spec) {
        $columns[] = "`$column`";
        $placeholders[] = $spec['placeholder'];

        $specTypes = (string)($spec['type'] ?? '');
        $specValue = $spec['value'] ?? null;

        if ($specTypes !== '') {
            $types .= $specTypes;
            if (is_array($specValue)) {
                foreach ($specValue as $one) {
                    $values[] = $one;
                }
            } else {
                $values[] = $specValue;
            }
        }

        if (isset($extraUpdates[$column])) {
            $updates[] = $extraUpdates[$column];
        }
    }

    if ($updates === []) {
        return 0;
    }

    $sql = 'INSERT INTO user_daily_stats (' . implode(', ', $columns) . ') '
        . 'VALUES (' . implode(', ', $placeholders) . ') '
        . 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log('[stats_upsert_heartbeat] prepare fallita: ' . $mysqli->error);
        return 0;
    }

    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    return $affected;
}


/**
 * Aggiorna più sezioni con una sola INSERT multi-riga.
 *
 * @param array<string,array{seconds:int,views:int}> $pages
 */
function stats_bump_pages(mysqli $mysqli, int $userId, array $pages): void
{
    if ($pages === []) {
        return;
    }

    $year = (int)date('Y');

    $rows = [];
    $types = '';
    $values = [];

    foreach ($pages as $pageKey => $counters) {
        // La chiave è già stata validata contro STATS_PAGE_KEYS dal
        // chiamante, ma viene comunque passata come parametro legato.
        $key = in_array((string)$pageKey, STATS_PAGE_KEYS, true) ? (string)$pageKey : 'altro';

        $rows[] = '(?, ?, ?, ?, ?)';
        $types .= 'iisii';
        $values[] = $userId;
        $values[] = $year;
        $values[] = $key;
        $values[] = max(0, (int)($counters['seconds'] ?? 0));
        $values[] = max(0, (int)($counters['views'] ?? 0));
    }

    $stmt = $mysqli->prepare(
        'INSERT INTO user_page_stats (utente_id, `year`, page_key, seconds, views)
         VALUES ' . implode(', ', $rows) . '
         ON DUPLICATE KEY UPDATE
            seconds = seconds + VALUES(seconds),
            views   = views + VALUES(views)'
    );
    if (!$stmt) {
        return;
    }

    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();
}

/**
 * Estende la sessione aperta o ne apre una nuova.
 * Restituisce true se ne è stata aperta una nuova.
 */
function stats_touch_session(mysqli $mysqli, int $userId, int $seconds, bool $isNewPageView): bool
{
    $pages = $isNewPageView ? 1 : 0;

    // Prima si prova a prolungare la sessione aperta: se la UPDATE non tocca
    // nessuna riga vuol dire che non ce n'è una recente, e allora se ne apre
    // una nuova. Una SELECT in meno rispetto a cercarla e poi aggiornarla.
    $update = $mysqli->prepare('
        UPDATE user_activity_sessions
        SET ended_at = NOW(), seconds = seconds + ?, pages = pages + ?
        WHERE utente_id = ?
          AND ended_at >= DATE_SUB(NOW(), INTERVAL ' . STATS_SESSION_GAP . ' SECOND)
        ORDER BY started_at DESC
        LIMIT 1
    ');
    if (!$update) {
        return false;
    }

    $update->bind_param('iii', $seconds, $pages, $userId);
    $update->execute();
    $extended = $update->affected_rows > 0;
    $update->close();

    if ($extended) {
        return false;
    }

    $insert = $mysqli->prepare('
        INSERT INTO user_activity_sessions (utente_id, started_at, ended_at, seconds, pages)
        VALUES (?, NOW(), NOW(), ?, ?)
    ');
    if ($insert) {
        $insert->bind_param('iii', $userId, $seconds, $pages);
        $insert->execute();
        $insert->close();
    }

    return true;
}

// ─────────────────────────────────────────────────────────────
//  IMPORT DEI VECCHI COOKIE
// ─────────────────────────────────────────────────────────────

/**
 * Importa una tantum `timeSpent` e `daysVisited` dai cookie del browser.
 *
 * Il vecchio contatore incrementava un secondo al secondo anche a scheda
 * nascosta, quindi il totale è gonfiato. Lo conserviamo lo stesso — meglio
 * un dato approssimato che un Rewind vuoto — ma in colonne `legacy_*`
 * separate, così resta distinguibile dal tracciamento affidabile e le
 * statistiche future non ne vengono inquinate.
 *
 * L'import avviene una sola volta per utente: `legacy_imported_at` fa da
 * sentinella. Se lo stesso utente accede da un secondo browser con un
 * cookie più ricco, teniamo il valore più alto dei due.
 *
 * @param int|null   $timeSpent    secondi dal cookie timeSpent
 * @param array|null $daysVisited  date 'Y-m-d' dal cookie daysVisited
 * @return bool true se qualcosa è stato importato
 */
function stats_import_legacy(mysqli $mysqli, int $userId, ?int $timeSpent, ?array $daysVisited): bool
{
    if ($userId <= 0 || !stats_available($mysqli)) {
        return false;
    }

    $timeSpent = max(0, min((int)$timeSpent, 100 * 365 * 24 * 3600));
    $days = stats_sanitize_legacy_days($daysVisited);

    if ($timeSpent === 0 && $days === []) {
        return false;
    }

    try {
        $already = stats_legacy_import_state($mysqli, $userId);

        // Già importato e il nuovo cookie non aggiunge niente: esci.
        if ($already !== null
            && $timeSpent <= (int)$already['legacy_seconds']
            && count($days) <= (int)$already['legacy_days']
        ) {
            return false;
        }

        $firstDay = $days === [] ? null : min($days);

        $stmt = $mysqli->prepare('
            INSERT INTO user_stat_totals
                (utente_id, legacy_seconds, legacy_days, legacy_first_day, legacy_imported_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                legacy_seconds     = GREATEST(legacy_seconds, VALUES(legacy_seconds)),
                legacy_days        = GREATEST(legacy_days, VALUES(legacy_days)),
                legacy_first_day   = LEAST(COALESCE(legacy_first_day, VALUES(legacy_first_day)), COALESCE(VALUES(legacy_first_day), legacy_first_day)),
                legacy_imported_at = NOW()
        ');
        if (!$stmt) {
            return false;
        }

        $dayCount = count($days);
        $stmt->bind_param('iiis', $userId, $timeSpent, $dayCount, $firstDay);
        $stmt->execute();
        $stmt->close();

        stats_store_legacy_days($mysqli, $userId, $days);

        return true;
    } catch (Throwable $e) {
        error_log('[stats_import_legacy] ' . $e->getMessage());
        return false;
    }
}

/** @return array<int,string> date valide, uniche, non future, ordinate */
function stats_sanitize_legacy_days(?array $days): array
{
    if (!is_array($days)) {
        return [];
    }

    $today = date('Y-m-d');
    $clean = [];

    foreach ($days as $day) {
        if (!is_string($day) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            continue;
        }
        // Scarta date impossibili e date nel futuro (cookie manipolato).
        [$y, $m, $d] = array_map('intval', explode('-', $day));
        if (!checkdate($m, $d, $y) || $day > $today || $y < 2020) {
            continue;
        }
        $clean[$day] = true;
    }

    $clean = array_keys($clean);
    sort($clean);

    // Tetto difensivo: oltre 4000 giorni il cookie non è genuino.
    return array_slice($clean, 0, 4000);
}

/** @return array{legacy_seconds:int,legacy_days:int}|null */
function stats_legacy_import_state(mysqli $mysqli, int $userId): ?array
{
    $stmt = $mysqli->prepare('
        SELECT legacy_seconds, legacy_days
        FROM user_stat_totals
        WHERE utente_id = ? AND legacy_imported_at IS NOT NULL
        LIMIT 1
    ');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Salva i giorni recuperati dal cookie, ignorando quelli già presenti.
 *
 * Un cookie può contenere anni di date: inserirle una alla volta significa
 * migliaia di viaggi verso il database dentro una singola richiesta. Vanno
 * quindi a blocchi di duecento, ed è comunque un'operazione che ogni utente
 * paga una volta sola nella vita.
 */
function stats_store_legacy_days(mysqli $mysqli, int $userId, array $days): void
{
    if ($days === []) {
        return;
    }

    foreach (array_chunk($days, 200) as $chunk) {
        $rows = implode(', ', array_fill(0, count($chunk), "(?, ?, 'cookie')"));
        $stmt = $mysqli->prepare(
            "INSERT IGNORE INTO user_legacy_days (utente_id, `day`, source) VALUES $rows"
        );
        if (!$stmt) {
            return;
        }

        $types = str_repeat('is', count($chunk));
        $values = [];
        foreach ($chunk as $day) {
            $values[] = $userId;
            $values[] = $day;
        }

        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();
    }
}

// ─────────────────────────────────────────────────────────────
//  LETTURA
// ─────────────────────────────────────────────────────────────

/**
 * Totali lifetime dell'utente, con i valori legacy già sommati.
 *
 * @return array<string,int|string|null>
 */
function stats_get_totals(mysqli $mysqli, int $userId): array
{
    $empty = [
        'total_seconds'     => 0,
        'total_page_views'  => 0,
        'total_sessions'    => 0,
        'total_days_active' => 0,
        'current_streak'    => 0,
        'longest_streak'    => 0,
        'last_active_day'   => null,
        'legacy_seconds'    => 0,
        'legacy_days'       => 0,
        'legacy_first_day'  => null,
    ];

    if (!stats_available($mysqli)) {
        return $empty;
    }

    // Gli eventi di questa richiesta sono ancora nel buffer: senza svuotarlo
    // la lettura mostrerebbe valori più vecchi di quanto l'utente ha appena
    // fatto.
    stats_flush($mysqli);

    try {
        $stmt = $mysqli->prepare('SELECT * FROM user_stat_totals WHERE utente_id = ? LIMIT 1');
        if (!$stmt) {
            return $empty;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return $empty;
        }

        foreach ($empty as $key => $default) {
            if (!array_key_exists($key, $row)) {
                $row[$key] = $default;
            }
        }

        return $row;
    } catch (Throwable $e) {
        error_log('[stats_get_totals] ' . $e->getMessage());
        return $empty;
    }
}

// ─────────────────────────────────────────────────────────────
//  ACCESSO ANTICIPATO
// ─────────────────────────────────────────────────────────────

/**
 * Il Rewind è visibile solo allo staff finché non viene aperto a tutti.
 *
 * Il tracciamento gira già per chiunque: è proprio il punto. Quando la
 * pagina verrà aperta, le persone troveranno mesi di dati alle spalle invece
 * di un riepilogo vuoto — che è il motivo per cui questa porta resta chiusa.
 *
 * Per aprirla a tutti basta mettere questa costante a true: non c'è altro da
 * cambiare, i controlli passano tutti da rewind_user_can_view().
 */
const REWIND_OPEN_TO_EVERYONE = false;

/** Vero se l'utente corrente può aprire il proprio Rewind. */
function rewind_user_can_view(): bool
{
    if (REWIND_OPEN_TO_EVERYONE) {
        return true;
    }

    $role = $_SESSION['ruolo'] ?? '';

    return $role === 'admin' || $role === 'owner';
}

/** Messaggio mostrato a chi arriva sulla pagina prima dell'apertura. */
function rewind_locked_message(string $lang = 'it'): string
{
    return $lang === 'en'
        ? 'Cripsum Rewind is still being built. It is collecting your stats in the meantime, so there will be something to show when it opens.'
        : 'Cripsum Rewind è ancora in lavorazione. Intanto sta raccogliendo le tue statistiche, così all\'apertura ci sarà qualcosa da guardare.';
}
