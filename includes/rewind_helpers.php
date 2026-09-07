<?php

/**
 * Cripsum™ — Rewind
 *
 * Costruisce il riepilogo annuale dell'utente: una struttura JSON che il
 * front-end trasforma in diciassette schermate in stile storia.
 *
 * DUE SORGENTI, UNA SOLA USCITA
 *
 * Il tracciamento dettagliato (user_daily_stats) è nato con questa funzione,
 * quindi da solo produrrebbe un Rewind vuoto per chiunque. Per questo il
 * generatore legge anche le tabelle storiche che il sito riempie da anni e
 * che hanno già una data: gacha_pull_history, utenti_achievement,
 * utenti_personaggi, messages, shitposts, game_matches e le altre.
 *
 * Il risultato è che il Rewind è pieno di roba vera dal primo giorno, e le
 * poche cose che nessuno registrava — tempo sul sito, sezioni preferite,
 * fascia oraria — arrivano dalle statistiche nuove, integrate dal seed
 * recuperato dai vecchi cookie.
 *
 * ROBUSTEZZA
 *
 * Lo schema di questo progetto è cresciuto per stratificazioni e non tutte le
 * tabelle hanno gli stessi nomi ovunque. Ogni sezione gira dentro
 * rewind_try(): se una query fallisce, quella sezione resta vuota e il resto
 * del Rewind viene generato lo stesso. Meglio una schermata in meno che una
 * pagina di errore.
 *
 * COSTO
 *
 * Una generazione completa sono circa trenta query aggregate. Non gira a ogni
 * apertura: il risultato finisce in `user_rewind` e da lì viene servito.
 *
 * @package Cripsum\Rewind
 */

require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/stats_tracker.php';

/** Versione dello schema del payload. Cambiarla invalida le cache esistenti. */
const REWIND_PAYLOAD_VERSION = 1;

/**
 * Per quanto tempo un Rewind generato resta valido prima di essere rifatto.
 *
 * Dieci minuti: abbastanza da assorbire i ricaricamenti di chi sta guardando
 * il proprio Rewind, abbastanza poco da far comparire in fretta quello che si
 * è appena fatto. Una generazione costa una sessantina di query ma va a buon
 * fine in una ventina di millisecondi, quindi tenerla fresca non pesa.
 */
const REWIND_CACHE_TTL = 600; // 10 minuti

/** Finestra mobile: gli ultimi 365 giorni. */
const REWIND_ROLLING_DAYS = 365;

/** Prima data plausibile del sito, usata quando manca l'iscrizione. */
const REWIND_EPOCH = '2019-01-01';

/**
 * Quanti giorni al massimo entrano nella heatmap.
 *
 * Il periodo "da sempre" può coprire anni: disegnarli tutti produrrebbe una
 * striscia illeggibile larga migliaia di pixel. La heatmap mostra quindi
 * l'ultimo anno, mentre i conteggi restano su tutto il periodo.
 */
const REWIND_HEATMAP_DAYS = 371;

// ─────────────────────────────────────────────────────────────
//  PERIODO
// ─────────────────────────────────────────────────────────────

/**
 * Traduce una chiave di periodo in un intervallo di date.
 *
 * 'all' è il periodo predefinito e copre tutta la vita dell'account.
 * Su un sito con questo traffico un taglio annuale mostrerebbe una fetta
 * poco rappresentativa di quello che una persona ha davvero fatto — chi ha
 * sbloccato tutti gli achievement negli anni si vedrebbe scritto "3
 * sbloccati" soltanto perché gli altri sono più vecchi di dodici mesi.
 *
 * Restano disponibili 'r365' (ultimi 365 giorni) e un anno solare ('2026'),
 * utili quando il sito sarà più frequentato o per un'edizione di dicembre.
 *
 * @param string|null $memberSince data di iscrizione, per non far partire il
 *                                 periodo prima che l'account esistesse
 * @return array{key:string,start:string,end:string,label_it:string,label_en:string,mode:string}
 */
function rewind_period(string $periodKey = 'all', ?string $memberSince = null): array
{
    $today = date('Y-m-d');

    if (preg_match('/^\d{4}$/', $periodKey)) {
        $year = (int)$periodKey;

        return [
            'key'      => (string)$year,
            'start'    => $year . '-01-01',
            'end'      => min($year . '-12-31', $today),
            'label_it' => 'Il tuo ' . $year,
            'label_en' => 'Your ' . $year,
            'mode'     => 'year',
        ];
    }

    if ($periodKey === 'r365') {
        return [
            'key'      => 'r365',
            'start'    => date('Y-m-d', strtotime('-' . (REWIND_ROLLING_DAYS - 1) . ' days')),
            'end'      => $today,
            'label_it' => 'Il tuo ultimo anno',
            'label_en' => 'Your last year',
            'mode'     => 'rolling',
        ];
    }

    // Da quando esiste l'account. Il ripiego copre comunque tutta la storia
    // del sito, così un'iscrizione senza data non taglia fuori nulla.
    $start = REWIND_EPOCH;
    if ($memberSince) {
        $parsed = strtotime((string)$memberSince);
        if ($parsed !== false) {
            $start = date('Y-m-d', $parsed);
        }
    }

    if ($start > $today) {
        $start = $today;
    }

    return [
        'key'      => 'all',
        'start'    => $start,
        'end'      => $today,
        'label_it' => 'Da sempre',
        'label_en' => 'All time',
        'mode'     => 'all',
    ];
}

/** Legge la data di iscrizione, per delimitare il periodo "da sempre". */
function rewind_member_since(mysqli $mysqli, int $userId): ?string
{
    $row = rewind_row($mysqli, 'SELECT data_creazione FROM utenti WHERE id = ? LIMIT 1', 'i', [$userId]);

    return $row['data_creazione'] ?? null;
}

/** Estremi come DATETIME, per le tabelle che salvano l'ora oltre alla data. */
function rewind_bounds(array $period): array
{
    return [$period['start'] . ' 00:00:00', $period['end'] . ' 23:59:59'];
}

// ─────────────────────────────────────────────────────────────
//  ESECUZIONE DIFENSIVA DELLE QUERY
// ─────────────────────────────────────────────────────────────

/**
 * Esegue una parte del calcolo isolandone i guasti.
 *
 * Una tabella rinominata o una colonna che qui non esiste non deve far
 * saltare l'intero Rewind: la sezione interessata torna al suo valore di
 * riserva e il resto prosegue.
 *
 * @template T
 * @param callable():T $fn
 * @param T $fallback
 * @return T
 */
function rewind_try(callable $fn, $fallback, string $label = '')
{
    try {
        $value = $fn();
        return $value === null ? $fallback : $value;
    } catch (Throwable $e) {
        error_log('[rewind:' . ($label ?: 'sezione') . '] ' . $e->getMessage());
        return $fallback;
    }
}

/**
 * Esegue una query preparata e restituisce tutte le righe.
 *
 * @return array<int,array<string,mixed>>
 */
function rewind_rows(mysqli $mysqli, string $sql, string $types = '', array $params = []): array
{
    $stmt = @$mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows ?: [];
}

/** Prima riga di una query, oppure null. */
function rewind_row(mysqli $mysqli, string $sql, string $types = '', array $params = []): ?array
{
    $rows = rewind_rows($mysqli, $sql, $types, $params);
    return $rows[0] ?? null;
}

/** Primo valore della prima riga, con valore di riserva. */
function rewind_scalar(mysqli $mysqli, string $sql, string $types = '', array $params = [], $fallback = 0)
{
    $row = rewind_row($mysqli, $sql, $types, $params);
    if ($row === null) {
        return $fallback;
    }

    $value = reset($row);
    return $value === null ? $fallback : $value;
}

/** Conteggio intero, zero se la query non è eseguibile. */
function rewind_count(mysqli $mysqli, string $sql, string $types = '', array $params = []): int
{
    return (int)rewind_scalar($mysqli, $sql, $types, $params, 0);
}

// ─────────────────────────────────────────────────────────────
//  CACHE
// ─────────────────────────────────────────────────────────────

function rewind_available(mysqli $mysqli): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $cached = stats_session_cache_get('rewind_schema_ok', STATS_SCHEMA_CACHE_TTL);
    if ($cached !== null) {
        return $ready = (bool)$cached;
    }

    try {
        $ready = auth_table_exists($mysqli, 'user_rewind');
    } catch (Throwable $e) {
        $ready = false;
    }

    stats_session_cache_set('rewind_schema_ok', $ready ? 1 : 0);

    return $ready;
}

/**
 * Restituisce il Rewind dell'utente, generandolo se serve.
 *
 * @param bool $force ignora la cache e ricalcola
 * @return array|null null se lo schema non è pronto
 */
function rewind_get_or_build(mysqli $mysqli, int $userId, string $periodKey = 'all', bool $force = false): ?array
{
    if ($userId <= 0 || !rewind_available($mysqli)) {
        return null;
    }

    // Il periodo "da sempre" parte dall'iscrizione, quindi va risolto
    // conoscendo l'utente.
    $period = rewind_period($periodKey, rewind_member_since($mysqli, $userId));

    if (!$force) {
        $cached = rewind_read_cache($mysqli, $userId, $period['key']);
        if ($cached !== null) {
            return $cached;
        }
    }

    $payload = rewind_build($mysqli, $userId, $period);
    rewind_write_cache($mysqli, $userId, $period, $payload);

    return $payload;
}

/** Legge il payload salvato, se ancora fresco e della versione giusta. */
function rewind_read_cache(mysqli $mysqli, int $userId, string $periodKey): ?array
{
    $row = rewind_row(
        $mysqli,
        'SELECT payload, generated_at, share_token, is_public, share_views
         FROM user_rewind
         WHERE utente_id = ? AND period_key = ?
         LIMIT 1',
        'is',
        [$userId, $periodKey]
    );

    if ($row === null) {
        return null;
    }

    $generatedAt = strtotime((string)$row['generated_at']);
    if ($generatedAt === false || (time() - $generatedAt) > REWIND_CACHE_TTL) {
        return null;
    }

    $payload = json_decode((string)$row['payload'], true);
    if (!is_array($payload) || ($payload['version'] ?? 0) !== REWIND_PAYLOAD_VERSION) {
        return null;
    }

    // Lo stato di condivisione vive nelle colonne, non nel payload: così
    // pubblicare o revocare un link non richiede di rigenerare tutto.
    $payload['share'] = [
        'token'     => $row['share_token'] ?: null,
        'is_public' => (int)$row['is_public'] === 1,
        'views'     => (int)$row['share_views'],
    ];

    return $payload;
}

/** Salva il payload appena calcolato, conservando il token di condivisione. */
function rewind_write_cache(mysqli $mysqli, int $userId, array $period, array $payload): void
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        error_log('[rewind_write_cache] serializzazione fallita per utente ' . $userId);
        return;
    }

    $persona = (string)($payload['persona']['slug'] ?? '');

    try {
        $stmt = $mysqli->prepare('
            INSERT INTO user_rewind
                (utente_id, period_key, period_start, period_end, payload, persona, generated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                period_start = VALUES(period_start),
                period_end   = VALUES(period_end),
                payload      = VALUES(payload),
                persona      = VALUES(persona),
                generated_at = NOW()
        ');
        if (!$stmt) {
            return;
        }

        $stmt->bind_param(
            'isssss',
            $userId,
            $period['key'],
            $period['start'],
            $period['end'],
            $json,
            $persona
        );
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('[rewind_write_cache] ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────
//  COSTRUZIONE DEL PAYLOAD
// ─────────────────────────────────────────────────────────────

/**
 * Calcola l'intero Rewind di un utente.
 *
 * Ogni sezione è indipendente: se una non riesce, le altre restano valide.
 */
function rewind_build(mysqli $mysqli, int $userId, array $period): array
{
    // Gli eventi ancora in coda devono finire nel conteggio.
    stats_flush($mysqli);

    $payload = [
        'version'      => REWIND_PAYLOAD_VERSION,
        'generated_at' => date('c'),
        'period'       => $period,
    ];

    $payload['user']         = rewind_try(fn() => rewind_section_user($mysqli, $userId, $period), [], 'user');
    $payload['time']         = rewind_try(fn() => rewind_section_time($mysqli, $userId, $period), [], 'time');
    $payload['hours']        = rewind_try(fn() => rewind_section_hours($mysqli, $userId, $period), [], 'hours');
    $payload['calendar']     = rewind_try(fn() => rewind_section_calendar($mysqli, $userId, $period), [], 'calendar');
    $payload['pages']        = rewind_try(fn() => rewind_section_pages($mysqli, $userId, $period), [], 'pages');
    $payload['gacha']        = rewind_try(fn() => rewind_section_gacha($mysqli, $userId, $period), [], 'gacha');
    $payload['collection']   = rewind_try(fn() => rewind_section_collection($mysqli, $userId, $period), [], 'collection');
    $payload['achievements'] = rewind_try(fn() => rewind_section_achievements($mysqli, $userId, $period), [], 'achievements');
    $payload['missions']     = rewind_try(fn() => rewind_section_missions($mysqli, $userId, $period), [], 'missions');
    $payload['social']       = rewind_try(fn() => rewind_section_social($mysqli, $userId, $period), [], 'social');
    $payload['profile']      = rewind_try(fn() => rewind_section_profile($mysqli, $userId, $period), [], 'profile');
    $payload['games']        = rewind_try(fn() => rewind_section_games($mysqli, $userId, $period), [], 'games');
    $payload['content']      = rewind_try(fn() => rewind_section_content($mysqli, $userId, $period), [], 'content');
    $payload['economy']      = rewind_try(fn() => rewind_section_economy($mysqli, $userId, $period), [], 'economy');
    $payload['ranking']      = rewind_try(fn() => rewind_section_ranking($mysqli, $userId, $period, $payload), [], 'ranking');

    $payload = rewind_normalize_media($payload);

    $payload['persona'] = rewind_persona($payload);
    $payload['slides']  = rewind_slide_plan($payload);

    return $payload;
}

/**
 * Trasforma in indirizzi utilizzabili tutti gli \`img_url\` del payload.
 *
 * Sta in un punto solo perche' la regola e' sempre la stessa e ripeterla
 * dentro ogni query sarebbe il modo migliore per dimenticarsene in una.
 */
function rewind_normalize_media(array $payload): array
{
    $single = [
        ['gacha', 'best_pull'],
        ['collection', 'rarest'],
        ['collection', 'first'],
        ['collection', 'most_duplicated'],
        ['collection', 'most_pulled'],
        ['collection', 'least_pulled'],
        ['achievements', 'rarest'],
    ];

    foreach ($single as [$section, $key]) {
        if (isset($payload[$section][$key]['img_url'])) {
            $payload[$section][$key]['img_url'] = rewind_media_url($payload[$section][$key]['img_url']);
        }
    }

    if (!empty($payload['achievements']['timeline']) && is_array($payload['achievements']['timeline'])) {
        foreach ($payload['achievements']['timeline'] as $i => $item) {
            if (isset($item['img_url'])) {
                $payload['achievements']['timeline'][$i]['img_url'] = rewind_media_url($item['img_url']);
            }
        }
    }

    return $payload;
}

// ── Utente ───────────────────────────────────────────────────

function rewind_section_user(mysqli $mysqli, int $userId, array $period): array
{
    $row = rewind_row(
        $mysqli,
        'SELECT username, display_name, data_creazione, accent_color, is_premium, ruolo
         FROM utenti WHERE id = ? LIMIT 1',
        'i',
        [$userId]
    );

    $createdAt = $row['data_creazione'] ?? null;
    $daysOnSite = 0;
    if ($createdAt) {
        $created = strtotime((string)$createdAt);
        if ($created !== false) {
            $daysOnSite = max(0, (int)floor((time() - $created) / 86400));
        }
    }

    return [
        'id'           => $userId,
        'username'     => (string)($row['username'] ?? ''),
        'display_name' => (string)($row['display_name'] ?? ($row['username'] ?? '')),
        'member_since' => $createdAt,
        'days_on_site' => $daysOnSite,
        'accent_color' => (string)($row['accent_color'] ?? '#2f6bff'),
        'is_premium'   => (int)($row['is_premium'] ?? 0) === 1,
        // get_pfp.php gestisce da solo avatar Discord, caricamenti e
        // immagine predefinita: qui basta l'indirizzo.
        'avatar'       => rewind_avatar_url($userId, 256),
    ];
}

/** Indirizzo dell'avatar di un utente, alla dimensione richiesta. */
function rewind_avatar_url(int $userId, int $size = 128): string
{
    return '/includes/get_pfp.php?id=' . $userId . '&size=' . $size;
}

/**
 * Normalizza un `img_url` del database in un indirizzo utilizzabile.
 *
 * Le tabelle `personaggi` e `achievement` conservano il solo nome del file
 * (`sus.png`) e tutto il sito ci antepone `/img/`. Un valore già assoluto o
 * già radicato viene lasciato com'è.
 */
function rewind_media_url(?string $raw): ?string
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }

    // Indirizzo esterno: si lascia com'è.
    if (preg_match('#^(https?:)?//#i', $raw)) {
        return $raw;
    }

    // Il pannello admin salva i percorsi come li scrivono le pagine dentro
    // it/ ed en/, cioè relativi: `../img/segone4.png`. Da quelle pagine il
    // `../` risale alla radice e funziona, ma il Rewind serve lo stesso
    // payload a pagine di livello diverso (e alla card, e ai link pubblici),
    // quindi qui il percorso va reso assoluto una volta per tutte.
    $raw = (string)preg_replace('#^(?:\.{1,2}/)+#', '', $raw);
    if ($raw === '') {
        return null;
    }

    if (str_starts_with($raw, '/')) {
        return $raw;
    }

    // `img/x.png` era il caso che rompeva tutto: diventava `/img/img/x.png`.
    if (str_starts_with($raw, 'img/')) {
        return '/' . $raw;
    }

    return '/img/' . $raw;
}

// ── Tempo e presenza ─────────────────────────────────────────

function rewind_section_time(mysqli $mysqli, int $userId, array $period): array
{
    $row = rewind_row(
        $mysqli,
        'SELECT
            COALESCE(SUM(seconds_active), 0) AS seconds,
            COALESCE(SUM(page_views), 0)     AS page_views,
            COUNT(*)                          AS days_active
         FROM user_daily_stats
         WHERE utente_id = ? AND `day` BETWEEN ? AND ?',
        'iss',
        [$userId, $period['start'], $period['end']]
    ) ?? [];

    $tracked = (int)($row['seconds'] ?? 0);
    $daysTracked = (int)($row['days_active'] ?? 0);

    // Il seed recuperato dai cookie. Il vecchio contatore girava anche a
    // scheda nascosta, quindi è una sovrastima: lo teniamo separato e lo
    // dichiariamo come stima, ma lo sommiamo perché senza il Rewind di chi
    // usa il sito da anni mostrerebbe pochi minuti.
    $totals = stats_get_totals($mysqli, $userId);
    $legacySeconds = (int)($totals['legacy_seconds'] ?? 0);

    // I giorni ripescati dal cookie che cadono nella finestra.
    $legacyDays = rewind_count(
        $mysqli,
        'SELECT COUNT(*) FROM user_legacy_days
         WHERE utente_id = ? AND `day` BETWEEN ? AND ?
           AND `day` NOT IN (SELECT `day` FROM user_daily_stats WHERE utente_id = ?)',
        'issi',
        [$userId, $period['start'], $period['end'], $userId]
    );

    $sessions = rewind_row(
        $mysqli,
        'SELECT COUNT(*) AS n, COALESCE(SUM(seconds), 0) AS s, COALESCE(MAX(seconds), 0) AS longest
         FROM user_activity_sessions
         WHERE utente_id = ? AND DATE(started_at) BETWEEN ? AND ?',
        'iss',
        [$userId, $period['start'], $period['end']]
    ) ?? [];

    $sessionCount = (int)($sessions['n'] ?? 0);
    $totalSeconds = $tracked + $legacySeconds;

    return [
        'seconds'          => $totalSeconds,
        'seconds_tracked'  => $tracked,
        'seconds_legacy'   => $legacySeconds,
        'is_estimated'     => $legacySeconds > $tracked,
        'hours'            => (int)round($totalSeconds / 3600),
        'minutes'          => (int)round($totalSeconds / 60),
        'page_views'       => (int)($row['page_views'] ?? 0),
        'days_active'      => $daysTracked + $legacyDays,
        'days_tracked'     => $daysTracked,
        'days_legacy'      => $legacyDays,
        'sessions'         => $sessionCount,
        'longest_session'  => (int)($sessions['longest'] ?? 0),
        'avg_session'      => $sessionCount > 0 ? (int)round((int)$sessions['s'] / $sessionCount) : 0,
        'current_streak'   => (int)($totals['current_streak'] ?? 0),
        'longest_streak'   => (int)($totals['longest_streak'] ?? 0),
    ];
}

// ── Fascia oraria ────────────────────────────────────────────

function rewind_section_hours(mysqli $mysqli, int $userId, array $period): array
{
    $rows = rewind_rows(
        $mysqli,
        'SELECT hour_buckets FROM user_daily_stats
         WHERE utente_id = ? AND `day` BETWEEN ? AND ? AND hour_buckets IS NOT NULL',
        'iss',
        [$userId, $period['start'], $period['end']]
    );

    $buckets = array_fill(0, 24, 0);
    foreach ($rows as $row) {
        $decoded = json_decode((string)$row['hour_buckets'], true);
        if (!is_array($decoded)) {
            continue;
        }
        foreach ($decoded as $hour => $seconds) {
            $hour = (int)$hour;
            if ($hour >= 0 && $hour < 24) {
                $buckets[$hour] += (int)$seconds;
            }
        }
    }

    $total = array_sum($buckets);
    $peakHour = 0;
    $peakValue = 0;
    foreach ($buckets as $hour => $seconds) {
        if ($seconds > $peakValue) {
            $peakValue = $seconds;
            $peakHour = $hour;
        }
    }

    // Quote per fasce, usate per assegnare il cronotipo.
    $slice = static function (array $hours) use ($buckets, $total): float {
        if ($total <= 0) {
            return 0.0;
        }
        $sum = 0;
        foreach ($hours as $hour) {
            $sum += $buckets[$hour];
        }
        return $sum / $total;
    };

    $night   = $slice([0, 1, 2, 3, 4, 5]);
    $morning = $slice([6, 7, 8, 9, 10, 11]);
    $day     = $slice([12, 13, 14, 15, 16, 17]);
    $evening = $slice([18, 19, 20, 21, 22, 23]);

    $chronotype = 'equilibrato';
    $best = max($night, $morning, $day, $evening);
    if ($total > 0) {
        if ($best === $night)        $chronotype = 'nottambulo';
        elseif ($best === $morning)  $chronotype = 'mattiniero';
        elseif ($best === $day)      $chronotype = 'diurno';
        else                         $chronotype = 'serale';
    }

    return [
        'buckets'      => array_values($buckets),
        'total'        => $total,
        'peak_hour'    => $peakHour,
        'peak_share'   => $total > 0 ? round($peakValue / $total * 100) : 0,
        'chronotype'   => $chronotype,
        'share_night'  => round($night * 100),
        'share_morning' => round($morning * 100),
        'share_day'    => round($day * 100),
        'share_evening' => round($evening * 100),
        'has_data'     => $total > 0,
    ];
}

// ── Calendario e giorno più intenso ──────────────────────────

function rewind_section_calendar(mysqli $mysqli, int $userId, array $period): array
{
    $rows = rewind_rows(
        $mysqli,
        'SELECT `day`, seconds_active, page_views,
                (gacha_pulls + msg_global + msg_private + msg_group + achievements_unlocked
                 + duels_played + subway_runs + posts_created + shitposts_created) AS actions
         FROM user_daily_stats
         WHERE utente_id = ? AND `day` BETWEEN ? AND ?
         ORDER BY `day` ASC',
        'iss',
        [$userId, $period['start'], $period['end']]
    );

    $days = [];
    $busiest = null;
    $busiestScore = -1;

    foreach ($rows as $row) {
        $day = (string)$row['day'];
        $seconds = (int)$row['seconds_active'];
        $actions = (int)$row['actions'];

        $days[$day] = [
            'seconds' => $seconds,
            'actions' => $actions,
        ];

        // Un giorno "intenso" mescola tempo e cose fatte: solo il tempo
        // premierebbe una scheda lasciata aperta.
        $score = (int)round($seconds / 60) + $actions * 5;
        if ($score > $busiestScore) {
            $busiestScore = $score;
            $busiest = [
                'date'    => $day,
                'seconds' => $seconds,
                'actions' => $actions,
                'score'   => $score,
            ];
        }
    }

    // I giorni ripescati dal cookie non hanno dettagli, ma sanno di essere
    // esistiti: entrano nella heatmap con intensità minima.
    $legacy = rewind_rows(
        $mysqli,
        'SELECT `day` FROM user_legacy_days WHERE utente_id = ? AND `day` BETWEEN ? AND ?',
        'iss',
        [$userId, $period['start'], $period['end']]
    );
    foreach ($legacy as $row) {
        $day = (string)$row['day'];
        if (!isset($days[$day])) {
            $days[$day] = ['seconds' => 0, 'actions' => 0, 'legacy' => true];
        }
    }

    ksort($days);

    // Il periodo "da sempre" puo' coprire anni: la heatmap ne disegna al
    // massimo l'ultimo, mentre i conteggi restano su tutto il periodo.
    $heatmapStart = $period['start'];
    $cap = date('Y-m-d', strtotime($period['end'] . ' -' . (REWIND_HEATMAP_DAYS - 1) . ' days'));
    if ($heatmapStart < $cap) {
        $heatmapStart = $cap;
    }

    return [
        'days'          => $days,
        'total_days'    => count($days),
        'busiest_day'   => $busiest,
        'heatmap_start' => $heatmapStart,
        'heatmap_end'   => $period['end'],
        'is_clipped'    => $heatmapStart > $period['start'],
    ];
}

// ── Sezioni preferite ────────────────────────────────────────

function rewind_section_pages(mysqli $mysqli, int $userId, array $period): array
{
    $years = range((int)date('Y', strtotime($period['start'])), (int)date('Y', strtotime($period['end'])));
    $placeholders = implode(',', array_fill(0, count($years), '?'));

    $rows = rewind_rows(
        $mysqli,
        "SELECT page_key, SUM(seconds) AS seconds, SUM(views) AS views
         FROM user_page_stats
         WHERE utente_id = ? AND `year` IN ($placeholders)
         GROUP BY page_key
         ORDER BY seconds DESC
         LIMIT 8",
        'i' . str_repeat('i', count($years)),
        array_merge([$userId], $years)
    );

    $total = 0;
    foreach ($rows as $row) {
        $total += (int)$row['seconds'];
    }

    $pages = [];
    foreach ($rows as $row) {
        $seconds = (int)$row['seconds'];
        $pages[] = [
            'key'     => (string)$row['page_key'],
            'seconds' => $seconds,
            'views'   => (int)$row['views'],
            'share'   => $total > 0 ? round($seconds / $total * 100) : 0,
        ];
    }

    return [
        'top'      => $pages,
        'total'    => $total,
        'has_data' => $pages !== [],
    ];
}

// ── Gacha ────────────────────────────────────────────────────

function rewind_section_gacha(mysqli $mysqli, int $userId, array $period): array
{
    [$from, $to] = rewind_bounds($period);

    // Storico reale: esiste da prima del tracciamento, quindi il Rewind del
    // gacha è completo anche per gli utenti storici.
    $summary = rewind_row(
        $mysqli,
        'SELECT
            COUNT(*)                                       AS pulls,
            SUM(is_new = 1)                                AS new_chars,
            SUM(esito_50_50 = 1)                           AS won_5050,
            SUM(esito_50_50 = 0)                           AS lost_5050,
            COALESCE(MAX(pity_al_momento), 0)              AS max_pity
         FROM gacha_pull_history
         WHERE utente_id = ? AND created_at BETWEEN ? AND ?',
        'iss',
        [$userId, $from, $to]
    ) ?? [];

    $byRarity = [];
    foreach (rewind_rows(
        $mysqli,
        'SELECT `rarità` AS rarita, COUNT(*) AS n
         FROM gacha_pull_history
         WHERE utente_id = ? AND created_at BETWEEN ? AND ?
         GROUP BY `rarità`
         ORDER BY n DESC',
        'iss',
        [$userId, $from, $to]
    ) as $row) {
        $byRarity[(string)$row['rarita']] = (int)$row['n'];
    }

    // Il colpo di fortuna: la rarità più alta ottenuta col pity più basso.
    $rarityRank = "FIELD(gph.`rarità`, 'theone', 'segreto', 'speciale', 'leggendario', 'epico', 'raro', 'comune')";
    $bestPull = rewind_row(
        $mysqli,
        "SELECT p.nome, p.img_url, gph.`rarità` AS rarita, gph.pity_al_momento, gph.created_at, gph.is_new
         FROM gacha_pull_history gph
         INNER JOIN personaggi p ON p.id = gph.personaggio_id
         WHERE gph.utente_id = ? AND gph.created_at BETWEEN ? AND ?
         ORDER BY $rarityRank ASC, gph.pity_al_momento ASC
         LIMIT 1",
        'iss',
        [$userId, $from, $to]
    );

    $won = (int)($summary['won_5050'] ?? 0);
    $lost = (int)($summary['lost_5050'] ?? 0);

    // Godos spesi: solo il tracciamento nuovo lo sa, lo storico dei pull non
    // registra il costo.
    $spent = rewind_count(
        $mysqli,
        'SELECT COALESCE(SUM(gacha_spent), 0) FROM user_daily_stats
         WHERE utente_id = ? AND `day` BETWEEN ? AND ?',
        'iss',
        [$userId, $period['start'], $period['end']]
    );

    return [
        'pulls'      => (int)($summary['pulls'] ?? 0),
        'new_chars'  => (int)($summary['new_chars'] ?? 0),
        'won_5050'   => $won,
        'lost_5050'  => $lost,
        'rate_5050'  => ($won + $lost) > 0 ? round($won / ($won + $lost) * 100) : null,
        'max_pity'   => (int)($summary['max_pity'] ?? 0),
        'by_rarity'  => $byRarity,
        'best_pull'  => $bestPull,
        'godos_spent' => $spent,
        'has_data'   => (int)($summary['pulls'] ?? 0) > 0,
    ];
}

// ── Collezione ───────────────────────────────────────────────

function rewind_section_collection(mysqli $mysqli, int $userId, array $period): array
{
    [$from, $to] = rewind_bounds($period);

    $newInPeriod = rewind_count(
        $mysqli,
        'SELECT COUNT(*) FROM utenti_personaggi
         WHERE utente_id = ? AND `data` BETWEEN ? AND ?',
        'iss',
        [$userId, $from, $to]
    );

    $owned = rewind_count($mysqli, 'SELECT COUNT(*) FROM utenti_personaggi WHERE utente_id = ?', 'i', [$userId]);
    $catalogue = rewind_count($mysqli, 'SELECT COUNT(*) FROM personaggi');

    $rarityRank = "FIELD(p.`rarità`, 'theone', 'segreto', 'speciale', 'leggendario', 'epico', 'raro', 'comune')";

    $rarest = rewind_row(
        $mysqli,
        "SELECT p.nome, p.img_url, p.`rarità` AS rarita, up.`data`
         FROM utenti_personaggi up
         INNER JOIN personaggi p ON p.id = up.personaggio_id
         WHERE up.utente_id = ? AND up.`data` BETWEEN ? AND ?
         ORDER BY $rarityRank ASC, up.`data` ASC
         LIMIT 1",
        'iss',
        [$userId, $from, $to]
    );

    $first = rewind_row(
        $mysqli,
        'SELECT p.nome, p.img_url, p.`rarità` AS rarita, up.`data`
         FROM utenti_personaggi up
         INNER JOIN personaggi p ON p.id = up.personaggio_id
         WHERE up.utente_id = ? AND up.`data` BETWEEN ? AND ?
         ORDER BY up.`data` ASC LIMIT 1',
        'iss',
        [$userId, $from, $to]
    );

    $mostDuplicated = rewind_row(
        $mysqli,
        'SELECT p.nome, p.img_url, p.`rarità` AS rarita, up.`quantità` AS quantita
         FROM utenti_personaggi up
         INNER JOIN personaggi p ON p.id = up.personaggio_id
         WHERE up.utente_id = ?
         ORDER BY up.`quantità` DESC LIMIT 1',
        'i',
        [$userId]
    );

    // Chi esce di continuo e chi non si è quasi mai visto: la coppia dice
    // molto più del semplice conteggio dei pull.
    $pullCounts = rewind_rows(
        $mysqli,
        'SELECT p.id, p.nome, p.img_url, p.`rarità` AS rarita, COUNT(*) AS pulls
         FROM gacha_pull_history gph
         INNER JOIN personaggi p ON p.id = gph.personaggio_id
         WHERE gph.utente_id = ? AND gph.created_at BETWEEN ? AND ?
         GROUP BY p.id, p.nome, p.img_url, p.`rarità`
         ORDER BY pulls DESC',
        'iss',
        [$userId, $from, $to]
    );

    $mostPulled = $pullCounts[0] ?? null;

    // Il "meno trovato" ha senso solo se ci sono almeno due personaggi
    // diversi: altrimenti sarebbe lo stesso del più trovato.
    $leastPulled = count($pullCounts) > 1 ? end($pullCounts) : null;

    return [
        'new_in_period'   => $newInPeriod,
        'owned'           => $owned,
        'catalogue'       => $catalogue,
        'completion'      => $catalogue > 0 ? round($owned / $catalogue * 100) : 0,
        'rarest'          => $rarest,
        'first'           => $first,
        'most_duplicated' => $mostDuplicated,
        'most_pulled'     => $mostPulled,
        'least_pulled'    => $leastPulled,
        'distinct_pulled' => count($pullCounts),
        'has_data'        => $owned > 0,
    ];
}

// ── Achievement ──────────────────────────────────────────────

function rewind_section_achievements(mysqli $mysqli, int $userId, array $period): array
{
    [$from, $to] = rewind_bounds($period);

    $summary = rewind_row(
        $mysqli,
        'SELECT COUNT(*) AS n, COALESCE(SUM(a.punti), 0) AS punti
         FROM utenti_achievement ua
         INNER JOIN achievement a ON a.id = ua.achievement_id
         WHERE ua.utente_id = ? AND ua.`data` BETWEEN ? AND ?',
        'iss',
        [$userId, $from, $to]
    ) ?? [];

    $totalUnlocked = rewind_count($mysqli, 'SELECT COUNT(*) FROM utenti_achievement WHERE utente_id = ?', 'i', [$userId]);
    $catalogue = rewind_count($mysqli, 'SELECT COUNT(*) FROM achievement');

    // Quanti utenti hanno almeno un achievement: è il denominatore giusto
    // per dire "solo il 2% ce l'ha", meglio del totale iscritti.
    $activeUsers = rewind_count($mysqli, 'SELECT COUNT(DISTINCT utente_id) FROM utenti_achievement');

    // Il più raro fra quelli sbloccati nel periodo.
    $rarest = rewind_row(
        $mysqli,
        'SELECT a.id, a.nome, a.nome_en, a.img_url, a.punti, ua.`data`,
                (SELECT COUNT(DISTINCT x.utente_id) FROM utenti_achievement x WHERE x.achievement_id = a.id) AS owners
         FROM utenti_achievement ua
         INNER JOIN achievement a ON a.id = ua.achievement_id
         WHERE ua.utente_id = ? AND ua.`data` BETWEEN ? AND ?
         ORDER BY owners ASC, a.punti DESC
         LIMIT 1',
        'iss',
        [$userId, $from, $to]
    );

    if ($rarest !== null && $activeUsers > 0) {
        $rarest['owners_pct'] = round((int)$rarest['owners'] / $activeUsers * 100, 1);
    }

    $timeline = rewind_rows(
        $mysqli,
        'SELECT a.nome, a.nome_en, a.img_url, a.punti, ua.`data`
         FROM utenti_achievement ua
         INNER JOIN achievement a ON a.id = ua.achievement_id
         WHERE ua.utente_id = ? AND ua.`data` BETWEEN ? AND ?
         ORDER BY ua.`data` ASC
         LIMIT 12',
        'iss',
        [$userId, $from, $to]
    );

    return [
        'unlocked_in_period' => (int)($summary['n'] ?? 0),
        'points_in_period'   => (int)($summary['punti'] ?? 0),
        'total_unlocked'     => $totalUnlocked,
        'catalogue'          => $catalogue,
        'completion'         => $catalogue > 0 ? round($totalUnlocked / $catalogue * 100) : 0,
        'rarest'             => $rarest,
        'timeline'           => $timeline,
        'has_data'           => (int)($summary['n'] ?? 0) > 0,
    ];
}

// ── Missioni ─────────────────────────────────────────────────

function rewind_section_missions(mysqli $mysqli, int $userId, array $period): array
{
    [$from, $to] = rewind_bounds($period);

    $completed = rewind_count(
        $mysqli,
        'SELECT COUNT(*) FROM user_missions
         WHERE user_id = ? AND completata = 1 AND completed_at BETWEEN ? AND ?',
        'iss',
        [$userId, $from, $to]
    );

    $claimed = rewind_count(
        $mysqli,
        'SELECT COUNT(*) FROM user_missions
         WHERE user_id = ? AND riscattata = 1 AND claimed_at BETWEEN ? AND ?',
        'iss',
        [$userId, $from, $to]
    );

    $byType = [];
    foreach (rewind_rows(
        $mysqli,
        'SELECT tipo, COUNT(*) AS n FROM user_missions
         WHERE user_id = ? AND completata = 1 AND completed_at BETWEEN ? AND ?
         GROUP BY tipo',
        'iss',
        [$userId, $from, $to]
    ) as $row) {
        $byType[(string)$row['tipo']] = (int)$row['n'];
    }

    return [
        'completed'  => $completed,
        'claimed'    => $claimed,
        'claim_rate' => $completed > 0 ? round($claimed / $completed * 100) : 0,
        'by_type'    => $byType,
        'has_data'   => $completed > 0,
    ];
}

// ── Social ───────────────────────────────────────────────────

function rewind_section_social(mysqli $mysqli, int $userId, array $period): array
{
    [$from, $to] = rewind_bounds($period);

    $global = rewind_count(
        $mysqli,
        'SELECT COUNT(*) FROM messages WHERE user_id = ? AND created_at BETWEEN ? AND ?',
        'iss',
        [$userId, $from, $to]
    );

    $private = rewind_count(
        $mysqli,
        'SELECT COUNT(*) FROM private_messages WHERE sender_id = ? AND created_at BETWEEN ? AND ?',
        'iss',
        [$userId, $from, $to]
    );

    $group = rewind_count(
        $mysqli,
        'SELECT COUNT(*) FROM chat_messages WHERE sender_id = ? AND created_at BETWEEN ? AND ?',
        'iss',
        [$userId, $from, $to]
    );

    $friends = rewind_count(
        $mysqli,
        'SELECT COUNT(*) FROM friendships
         WHERE (user_one_id = ? OR user_two_id = ?) AND created_at BETWEEN ? AND ?',
        'iiss',
        [$userId, $userId, $from, $to]
    );

    $totalFriends = rewind_count(
        $mysqli,
        'SELECT COUNT(*) FROM friendships WHERE user_one_id = ? OR user_two_id = ?',
        'ii',
        [$userId, $userId]
    );

    // Giorno più chiacchierone, dalla chat globale (l'unica con una data
    // pubblica e senza implicazioni sugli altri).
    $busiestDay = rewind_row(
        $mysqli,
        'SELECT DATE(created_at) AS giorno, COUNT(*) AS n
         FROM messages WHERE user_id = ? AND created_at BETWEEN ? AND ?
         GROUP BY DATE(created_at) ORDER BY n DESC LIMIT 1',
        'iss',
        [$userId, $from, $to]
    );

    return [
        'msg_total'    => $global + $private + $group,
        'msg_global'   => $global,
        'msg_private'  => $private,
        'msg_group'    => $group,
        'friends_added' => $friends,
        'friends_total' => $totalFriends,
        'busiest_day'  => $busiestDay,
        'top_partner'  => rewind_top_partner($mysqli, $userId, $period),
        'has_data'     => ($global + $private + $group) > 0,
    ];
}

/**
 * La persona con cui si è scambiato più messaggi privati.
 *
 * Questa è l'unica statistica del Rewind che parla di qualcun altro, quindi
 * ha regole più severe delle altre:
 *
 *   - dev'esserci un'amicizia reciproca già registrata;
 *   - l'altra persona non deve aver disattivato la condivisione;
 *   - il conteggio dei messaggi non viene mai esposto, solo il nome;
 *   - la card pubblica non la include (vedi rewind_public_payload).
 *
 * Se una qualsiasi condizione non regge, la sezione resta nulla e il
 * front-end mostra il totale dei messaggi senza nominare nessuno.
 */
function rewind_top_partner(mysqli $mysqli, int $userId, array $period): ?array
{
    [$from, $to] = rewind_bounds($period);

    // Chi ha chiesto di non comparire nei Rewind altrui viene escluso a monte.
    $row = rewind_row(
        $mysqli,
        "SELECT other.user_id AS partner_id, u.username, u.display_name
         FROM private_messages m
         INNER JOIN private_conversation_participants me
                 ON me.conversation_id = m.conversation_id AND me.user_id = ?
         INNER JOIN private_conversation_participants other
                 ON other.conversation_id = m.conversation_id AND other.user_id <> ?
         INNER JOIN utenti u ON u.id = other.user_id
         INNER JOIN friendships f
                 ON f.user_one_id = LEAST(?, other.user_id)
                AND f.user_two_id = GREATEST(?, other.user_id)
         LEFT JOIN user_stats_prefs p ON p.utente_id = other.user_id
         WHERE m.created_at BETWEEN ? AND ?
           AND COALESCE(p.share_top_friend, 1) = 1
         GROUP BY other.user_id, u.username, u.display_name
         ORDER BY COUNT(*) DESC
         LIMIT 1",
        'iiiiss',
        [$userId, $userId, $userId, $userId, $from, $to]
    );

    if ($row === null) {
        return null;
    }

    $partnerId = (int)$row['partner_id'];

    return [
        'id'           => $partnerId,
        'username'     => (string)$row['username'],
        'display_name' => (string)($row['display_name'] ?: $row['username']),
        'avatar'       => rewind_avatar_url($partnerId, 128),
    ];
}

// ── Profilo ──────────────────────────────────────────────────

function rewind_section_profile(mysqli $mysqli, int $userId, array $period): array
{
    [$from, $to] = rewind_bounds($period);

    $viewsTracked = rewind_count(
        $mysqli,
        'SELECT COALESCE(SUM(profile_views_received), 0) FROM user_daily_stats
         WHERE utente_id = ? AND `day` BETWEEN ? AND ?',
        'iss',
        [$userId, $period['start'], $period['end']]
    );

    // Il contatore storico non ha date: lo mostriamo come totale di sempre.
    $viewsLifetime = rewind_count($mysqli, 'SELECT profile_views FROM utenti WHERE id = ?', 'i', [$userId]);

    $edits = rewind_count(
        $mysqli,
        'SELECT COUNT(*) FROM utenti_profile_activity
         WHERE utente_id = ? AND created_at BETWEEN ? AND ?',
        'iss',
        [$userId, $from, $to]
    );

    $topActivity = rewind_rows(
        $mysqli,
        'SELECT activity_type, COUNT(*) AS n FROM utenti_profile_activity
         WHERE utente_id = ? AND created_at BETWEEN ? AND ?
         GROUP BY activity_type ORDER BY n DESC LIMIT 3',
        'iss',
        [$userId, $from, $to]
    );

    return [
        'views_in_period' => $viewsTracked,
        'views_lifetime'  => $viewsLifetime,
        'changes'         => $edits,
        'top_activity'    => $topActivity,
        'has_data'        => $viewsLifetime > 0 || $edits > 0,
    ];
}

// ── Giochi ───────────────────────────────────────────────────

function rewind_section_games(mysqli $mysqli, int $userId, array $period): array
{
    [$from, $to] = rewind_bounds($period);

    $duels = rewind_row(
        $mysqli,
        "SELECT
            COUNT(*)              AS played,
            SUM(winner_id = ?)    AS wins,
            SUM(loser_id = ?)     AS losses
         FROM game_matches
         WHERE (player1_id = ? OR player2_id = ?)
           AND status = 'finished'
           AND finished_at BETWEEN ? AND ?",
        'iiiiss',
        [$userId, $userId, $userId, $userId, $from, $to]
    ) ?? [];

    $played = (int)($duels['played'] ?? 0);
    $wins = (int)($duels['wins'] ?? 0);

    $subway = rewind_row(
        $mysqli,
        'SELECT best_time_ms, map_slug FROM subway_leaderboard WHERE utente_id = ? LIMIT 1',
        'i',
        [$userId]
    );

    $subwayRank = null;
    if ($subway !== null) {
        $subwayRank = rewind_count(
            $mysqli,
            'SELECT COUNT(*) + 1 FROM subway_leaderboard WHERE best_time_ms > ?',
            'i',
            [(int)$subway['best_time_ms']]
        );
    }

    $subwayRuns = rewind_count(
        $mysqli,
        'SELECT COALESCE(SUM(subway_runs), 0) FROM user_daily_stats
         WHERE utente_id = ? AND `day` BETWEEN ? AND ?',
        'iss',
        [$userId, $period['start'], $period['end']]
    );

    return [
        'duels_played'   => $played,
        'duels_won'      => $wins,
        'duels_lost'     => (int)($duels['losses'] ?? 0),
        'winrate'        => $played > 0 ? round($wins / $played * 100) : null,
        'subway_best_ms' => $subway ? (int)$subway['best_time_ms'] : 0,
        'subway_map'     => $subway['map_slug'] ?? null,
        'subway_rank'    => $subwayRank,
        'subway_runs'    => $subwayRuns,
        'has_data'       => $played > 0 || $subway !== null,
    ];
}

// ── Contenuti ────────────────────────────────────────────────

function rewind_section_content(mysqli $mysqli, int $userId, array $period): array
{
    [$from, $to] = rewind_bounds($period);

    $shitposts = rewind_count(
        $mysqli,
        'SELECT COUNT(*) FROM shitposts WHERE id_utente = ? AND data_creazione BETWEEN ? AND ?',
        'iss',
        [$userId, $from, $to]
    );

    // I Top Rimasti sono l'altra meta' dei contenuti pubblicati: contarli
    // separatamente e poi sommarli tiene distinte le due sezioni del sito
    // senza far sparire meta' del lavoro di chi pubblica soprattutto li'.
    $rimasti = rewind_count(
        $mysqli,
        'SELECT COUNT(*) FROM toprimasti WHERE id_utente = ? AND data_creazione BETWEEN ? AND ?',
        'iss',
        [$userId, $from, $to]
    );

    $votesReceived = rewind_count(
        $mysqli,
        'SELECT COUNT(*) FROM voti_toprimasti v
         INNER JOIN toprimasti t ON t.id = v.id_post
         WHERE t.id_utente = ? AND v.data_voto BETWEEN ? AND ?',
        'iss',
        [$userId, $from, $to]
    );

    $comments = rewind_count(
        $mysqli,
        'SELECT COUNT(*) FROM commenti_shitpost WHERE id_utente = ? AND data_commento BETWEEN ? AND ?',
        'iss',
        [$userId, $from, $to]
    );

    $votes = rewind_count(
        $mysqli,
        'SELECT COUNT(*) FROM voti_toprimasti WHERE id_utente = ? AND data_voto BETWEEN ? AND ?',
        'iss',
        [$userId, $from, $to]
    );

    $likesGiven = rewind_count(
        $mysqli,
        'SELECT COUNT(*) FROM shitpost_likes WHERE id_utente = ? AND created_at BETWEEN ? AND ?',
        'iss',
        [$userId, $from, $to]
    );

    // Il post più apprezzato dell'anno.
    $bestPost = rewind_row(
        $mysqli,
        'SELECT s.id, s.titolo, s.data_creazione, (s.foto_shitpost IS NOT NULL) AS has_media,
                (SELECT COUNT(*) FROM shitpost_likes l WHERE l.id_shitpost = s.id) AS likes
         FROM shitposts s
         WHERE s.id_utente = ? AND s.data_creazione BETWEEN ? AND ? AND s.approvato = 1
         ORDER BY likes DESC
         LIMIT 1',
        'iss',
        [$userId, $from, $to]
    );

    $likesReceived = rewind_count(
        $mysqli,
        'SELECT COUNT(*) FROM shitpost_likes l
         INNER JOIN shitposts s ON s.id = l.id_shitpost
         WHERE s.id_utente = ? AND l.created_at BETWEEN ? AND ?',
        'iss',
        [$userId, $from, $to]
    );

    // Le visualizzazioni stanno in content_views, che indicizza per tipo di
    // contenuto: qui interessano solo gli shitpost dell'utente.
    $viewsReceived = rewind_count(
        $mysqli,
        "SELECT COUNT(*) FROM content_views v
         INNER JOIN shitposts s ON s.id = v.post_id
         WHERE v.content_type = 'shitpost' AND s.id_utente = ?
           AND v.created_at BETWEEN ? AND ?",
        'iss',
        [$userId, $from, $to]
    );

    $mostViewed = rewind_row(
        $mysqli,
        "SELECT s.id, s.titolo, s.data_creazione, (s.foto_shitpost IS NOT NULL) AS has_media,
                (SELECT COUNT(*) FROM content_views v
                  WHERE v.content_type = 'shitpost' AND v.post_id = s.id) AS views
         FROM shitposts s
         WHERE s.id_utente = ? AND s.data_creazione BETWEEN ? AND ? AND s.approvato = 1
         ORDER BY views DESC
         LIMIT 1",
        'iss',
        [$userId, $from, $to]
    );

    $mostCommented = rewind_row(
        $mysqli,
        'SELECT s.id, s.titolo, (s.foto_shitpost IS NOT NULL) AS has_media,
                (SELECT COUNT(*) FROM commenti_shitpost c WHERE c.id_shitpost = s.id) AS comments
         FROM shitposts s
         WHERE s.id_utente = ? AND s.data_creazione BETWEEN ? AND ? AND s.approvato = 1
         ORDER BY comments DESC
         LIMIT 1',
        'iss',
        [$userId, $from, $to]
    );

    // Il Top Rimasto piu' votato: e' il primato proprio di quella sezione,
    // dove conta il voto e non il like.
    $topRimasto = rewind_row(
        $mysqli,
        'SELECT t.id, t.titolo, t.data_creazione, (t.foto_rimasto IS NOT NULL) AS has_media,
                (SELECT COUNT(*) FROM voti_toprimasti v WHERE v.id_post = t.id) AS votes
         FROM toprimasti t
         WHERE t.id_utente = ? AND t.data_creazione BETWEEN ? AND ? AND t.approvato = 1
         ORDER BY votes DESC
         LIMIT 1',
        'iss',
        [$userId, $from, $to]
    );

    if ($topRimasto) {
        $topRimasto['media_url'] = !empty($topRimasto['has_media'])
            ? '/api/content/media.php?type=rimasto&id=' . (int)$topRimasto['id']
            : null;
        unset($topRimasto['has_media']);

        if ((int)$topRimasto['votes'] === 0) {
            $topRimasto = null;
        }
    }

    // I media dei post sono BLOB serviti da un endpoint dedicato, che
    // controlla l'approvazione: qui passiamo solo l'indirizzo.
    foreach ([&$bestPost, &$mostViewed, &$mostCommented] as &$post) {
        if (is_array($post)) {
            $post['media_url'] = !empty($post['has_media'])
                ? '/api/content/media.php?type=shitpost&id=' . (int)$post['id']
                : null;
            unset($post['has_media']);
        }
    }
    unset($post);

    // Un post con zero interazioni non merita una schermata dedicata.
    if ($mostViewed && (int)$mostViewed['views'] === 0)       $mostViewed = null;
    if ($mostCommented && (int)$mostCommented['comments'] === 0) $mostCommented = null;
    if ($bestPost && (int)$bestPost['likes'] === 0)            $bestPost = null;

    return [
        'shitposts'      => $shitposts,
        'rimasti'        => $rimasti,
        'posts_total'    => $shitposts + $rimasti,
        'comments'       => $comments,
        'votes'          => $votes,
        'votes_received' => $votesReceived,
        'likes_given'    => $likesGiven,
        'likes_received' => $likesReceived,
        'views_received' => $viewsReceived,
        'best_post'      => $bestPost,
        'most_viewed'    => $mostViewed,
        'most_commented' => $mostCommented,
        'top_rimasto'    => $topRimasto,
        'has_data'       => ($shitposts + $rimasti + $comments + $votes + $likesGiven) > 0,
    ];
}

// ── Economia ─────────────────────────────────────────────────

function rewind_section_economy(mysqli $mysqli, int $userId, array $period): array
{
    $row = rewind_row(
        $mysqli,
        'SELECT
            COALESCE(SUM(godos_earned), 0)  AS earned,
            COALESCE(SUM(godos_spent), 0)   AS spent,
            COALESCE(SUM(shards_spent), 0)  AS shards_spent
         FROM user_daily_stats
         WHERE utente_id = ? AND `day` BETWEEN ? AND ?',
        'iss',
        [$userId, $period['start'], $period['end']]
    ) ?? [];

    $balance = rewind_count($mysqli, 'SELECT soldi FROM utenti WHERE id = ?', 'i', [$userId]);

    $earned = (int)($row['earned'] ?? 0);
    $spent = (int)($row['spent'] ?? 0);

    return [
        'earned'       => $earned,
        'spent'        => $spent,
        'shards_spent' => (int)($row['shards_spent'] ?? 0),
        'balance'      => $balance,
        'net'          => $earned - $spent,
        'has_data'     => ($earned + $spent) > 0,
    ];
}

// ── Posizione fra gli utenti ─────────────────────────────────

/**
 * "Sei nel top X%".
 *
 * I percentili vengono precalcolati una volta al giorno in
 * rewind_year_aggregates: confrontarsi con tutta la base utenti a ogni
 * apertura sarebbe la query più cara di tutto il Rewind.
 */
function rewind_section_ranking(mysqli $mysqli, int $userId, array $period, array $payload): array
{
    $seconds = (int)($payload['time']['seconds'] ?? 0);
    if ($seconds <= 0) {
        return ['has_data' => false];
    }

    $row = rewind_row(
        $mysqli,
        'SELECT p50, p75, p90, p95, p99, total_users
         FROM rewind_year_aggregates
         WHERE period_key = ? AND metric = ?
         LIMIT 1',
        'ss',
        [$period['key'], 'seconds_active']
    );

    if ($row === null || (int)$row['total_users'] < 10) {
        // Senza distribuzione affidabile non inventiamo una posizione.
        return ['has_data' => false];
    }

    $percentile = 100;
    if ($seconds >= (int)$row['p99'])      $percentile = 1;
    elseif ($seconds >= (int)$row['p95'])  $percentile = 5;
    elseif ($seconds >= (int)$row['p90'])  $percentile = 10;
    elseif ($seconds >= (int)$row['p75'])  $percentile = 25;
    elseif ($seconds >= (int)$row['p50'])  $percentile = 50;

    return [
        'metric'      => 'time',
        'top_percent' => $percentile,
        'total_users' => (int)$row['total_users'],
        'has_data'    => $percentile < 100,
    ];
}

// ─────────────────────────────────────────────────────────────
//  ARCHETIPO
// ─────────────────────────────────────────────────────────────

/**
 * Assegna un archetipo in base a cosa l'utente ha fatto davvero.
 *
 * Ogni candidato produce un punteggio; vince il più alto. Le soglie sono
 * tarate perché quasi tutti ottengano qualcosa di riconoscibile e non il
 * ripiego generico.
 */
function rewind_persona(array $payload): array
{
    $time    = $payload['time'] ?? [];
    $hours   = $payload['hours'] ?? [];
    $gacha   = $payload['gacha'] ?? [];
    $social  = $payload['social'] ?? [];
    $games   = $payload['games'] ?? [];
    $profile = $payload['profile'] ?? [];
    $ach     = $payload['achievements'] ?? [];
    $content = $payload['content'] ?? [];
    $coll    = $payload['collection'] ?? [];

    $scores = [
        'nottambulo' => (($hours['chronotype'] ?? '') === 'nottambulo' ? 60 : 0)
            + (int)($hours['share_night'] ?? 0),

        'collezionista' => min(60, (int)($gacha['pulls'] ?? 0) / 5)
            + min(40, (int)($coll['completion'] ?? 0)),

        'sociale' => min(70, (int)($social['msg_total'] ?? 0) / 10)
            + min(30, (int)($social['friends_total'] ?? 0) * 3),

        'competitivo' => min(60, (int)($games['duels_played'] ?? 0) * 2)
            + min(40, (int)($games['subway_runs'] ?? 0)),

        'architetto' => min(80, (int)($profile['changes'] ?? 0) * 4)
            + min(20, (int)($profile['views_lifetime'] ?? 0) / 50),

        'completista' => min(70, (int)($ach['unlocked_in_period'] ?? 0) * 6)
            + min(30, (int)($ach['completion'] ?? 0)),

        'creatore' => min(70, (int)($content['shitposts'] ?? 0) * 8)
            + min(30, (int)($content['likes_received'] ?? 0)),

        'maratoneta' => min(70, (int)($time['hours'] ?? 0) / 2)
            + min(30, (int)($time['longest_streak'] ?? 0) * 2),

        'fantasma' => ((int)($time['days_active'] ?? 0) < 20 && (int)($time['hours'] ?? 0) < 5) ? 50 : 0,
    ];

    arsort($scores);
    $slug = (string)array_key_first($scores);
    if (($scores[$slug] ?? 0) <= 0) {
        $slug = 'esploratore';
    }

    $catalogue = rewind_persona_catalogue();
    $persona = $catalogue[$slug] ?? $catalogue['esploratore'];
    $persona['slug'] = $slug;
    $persona['score'] = (int)($scores[$slug] ?? 0);

    // Icona disegnata a mano, se c'e'. Il file viene cercato per nome
    // dell'archetipo: img/rewind/nottambulo.png e cosi' via. Se manca,
    // resta l'icona vettoriale gia' presente in 'icon'.
    $custom = __DIR__ . '/../img/rewind/' . $slug . '.png';
    $persona['image'] = is_file($custom) ? '/img/rewind/' . $slug . '.png' : null;

    return $persona;
}

/** @return array<string,array<string,string>> */
function rewind_persona_catalogue(): array
{
    return [
        'nottambulo' => [
            'name_it' => 'Il Nottambulo',
            'name_en' => 'The Night Owl',
            'desc_it' => 'Il sito lo vivi quando tutti gli altri dormono. Le tue ore migliori iniziano quando finisce la giornata degli altri.',
            'desc_en' => 'You live here while everyone else sleeps. Your best hours start when their day ends.',
            'color'   => '#7c5cff',
            'color_2' => '#2b1a5e',
            'icon'    => 'fa-solid fa-moon',
        ],
        'collezionista' => [
            'name_it' => 'Il Collezionista',
            'name_en' => 'The Collector',
            'desc_it' => 'Ogni pull è un passo verso il completamento. La tua collezione parla da sola.',
            'desc_en' => 'Every pull is a step toward completion. Your collection speaks for itself.',
            'color'   => '#fbbf24',
            'color_2' => '#5c3a00',
            'icon'    => 'fa-solid fa-gem',
        ],
        'sociale' => [
            'name_it' => 'L\'Anima della Chat',
            'name_en' => 'The Chat Soul',
            'desc_it' => 'Dove c\'è una conversazione, ci sei tu. Il sito senza di te sarebbe molto più silenzioso.',
            'desc_en' => 'Wherever there is a conversation, there you are. This place would be much quieter without you.',
            'color'   => '#34d399',
            'color_2' => '#064e3b',
            'icon'    => 'fa-solid fa-comments',
        ],
        'competitivo' => [
            'name_it' => 'Il Competitivo',
            'name_en' => 'The Competitor',
            'desc_it' => 'Duelli, classifiche, record da battere. Per te giocare significa vincere.',
            'desc_en' => 'Duels, leaderboards, records to break. For you, playing means winning.',
            'color'   => '#f87171',
            'color_2' => '#5c1414',
            'icon'    => 'fa-solid fa-trophy',
        ],
        'architetto' => [
            'name_it' => 'L\'Architetto',
            'name_en' => 'The Architect',
            'desc_it' => 'Il tuo profilo non è mai finito. Ogni settimana un colore nuovo, un blocco spostato, un dettaglio in più.',
            'desc_en' => 'Your profile is never finished. Every week a new color, a moved block, one more detail.',
            'color'   => '#38bdf8',
            'color_2' => '#0c4a6e',
            'icon'    => 'fa-solid fa-palette',
        ],
        'completista' => [
            'name_it' => 'Il Completista',
            'name_en' => 'The Completionist',
            'desc_it' => 'Una lista con una casella vuota ti tiene sveglio. Gli achievement non ti sfuggono.',
            'desc_en' => 'An unchecked box keeps you up at night. No achievement escapes you.',
            'color'   => '#a78bfa',
            'color_2' => '#3b1e75',
            'icon'    => 'fa-solid fa-list-check',
        ],
        'creatore' => [
            'name_it' => 'Il Creatore',
            'name_en' => 'The Creator',
            'desc_it' => 'Non consumi soltanto: produci. Metà di quello che gli altri guardano l\'hai messo tu.',
            'desc_en' => 'You do not just consume, you produce. Half of what others look at, you put there.',
            'color'   => '#fb923c',
            'color_2' => '#5c2a00',
            'icon'    => 'fa-solid fa-wand-magic-sparkles',
        ],
        'maratoneta' => [
            'name_it' => 'Il Maratoneta',
            'name_en' => 'The Marathoner',
            'desc_it' => 'Non salti un giorno. La costanza è la tua statistica migliore.',
            'desc_en' => 'You never skip a day. Consistency is your best stat.',
            'color'   => '#2f6bff',
            'color_2' => '#0b2a6b',
            'icon'    => 'fa-solid fa-fire-flame-curved',
        ],
        'fantasma' => [
            'name_it' => 'Il Fantasma',
            'name_en' => 'The Ghost',
            'desc_it' => 'Passi, guardi, sparisci. Ci sei, ma quasi nessuno se ne accorge. E forse ti piace così.',
            'desc_en' => 'You drop by, you look, you vanish. You are here, but almost nobody notices. Maybe that is the point.',
            'color'   => '#94a3b8',
            'color_2' => '#1e293b',
            'icon'    => 'fa-solid fa-ghost',
        ],
        'esploratore' => [
            'name_it' => 'L\'Esploratore',
            'name_en' => 'The Explorer',
            'desc_it' => 'Un po\' di tutto, senza fissazioni. Il sito lo giri tutto, senza fermarti da nessuna parte.',
            'desc_en' => 'A bit of everything, no obsessions. You wander the whole site without settling anywhere.',
            'color'   => '#22d3ee',
            'color_2' => '#083344',
            'icon'    => 'fa-solid fa-compass',
        ],
    ];
}

// ─────────────────────────────────────────────────────────────
//  PIANO DELLE SCHERMATE
// ─────────────────────────────────────────────────────────────

/**
 * Decide quali schermate mostrare e in che ordine.
 *
 * Una sezione senza dati verrebbe fuori come una schermata con degli zeri:
 * meglio saltarla. Chi ha appena aperto l'account vede un Rewind corto ma
 * onesto, non uno lungo e vuoto.
 *
 * Riceve il payload per riferimento perche', oltre a scegliere le
 * schermate, sposta un paio di dati fra l'una e l'altra quando due si
 * fondono (il pity del colpo fortunato finisce sul pezzo piu' raro).
 *
 * @return array<int,string>
 */
function rewind_slide_plan(array &$payload): array
{
    $slides = ['intro'];

    if (($payload['time']['seconds'] ?? 0) > 0)        $slides[] = 'time';
    if (!empty($payload['hours']['has_data']))         $slides[] = 'hours';
    if (($payload['calendar']['total_days'] ?? 0) > 3) $slides[] = 'calendar';
    if (!empty($payload['pages']['has_data']))         $slides[] = 'pages';
    if (!empty($payload['gacha']['has_data']))         $slides[] = 'gacha';
    // Il colpo fortunato e il pezzo piu' raro sono quasi sempre lo stesso
    // personaggio: in quel caso una sola schermata, con dentro anche il
    // dettaglio del pity. Due di fila sullo stesso nome sono una
    // ripetizione, non due momenti diversi.
    $bestPullName = $payload['gacha']['best_pull']['nome'] ?? null;
    $rarestName   = $payload['collection']['rarest']['nome'] ?? null;
    $pullIsRarest = $bestPullName !== null && $bestPullName === $rarestName;

    if ($bestPullName !== null && !$pullIsRarest) $slides[] = 'best_pull';
    if (!empty($payload['collection']['has_data']))    $slides[] = 'collection';

    // La sequenza cinematica dei personaggi ha senso solo con abbastanza
    // varietà: con due o tre personaggi diversi il confronto non dice nulla.
    if (($payload['collection']['distinct_pulled'] ?? 0) >= 4
        && !empty($payload['collection']['most_pulled'])
        && !empty($payload['collection']['least_pulled'])) {
        $slides[] = 'cast';
    }

    // Il piu' raro non si mescola agli altri due: ha la sua schermata,
    // altrimenti comparirebbe tre volte nello stesso racconto.
    if (!empty($payload['collection']['rarest'])) {
        $slides[] = 'rarest';
        $payload['collection']['rarest']['was_lucky_pull'] = $pullIsRarest;
        $payload['collection']['rarest']['pity'] = $pullIsRarest
            ? (int)($payload['gacha']['best_pull']['pity_al_momento'] ?? 0)
            : null;
    }

    if (!empty($payload['achievements']['has_data']))  $slides[] = 'achievements';
    if (!empty($payload['missions']['has_data']))      $slides[] = 'missions';
    if (!empty($payload['social']['has_data']))        $slides[] = 'social';
    if (!empty($payload['profile']['has_data']))       $slides[] = 'profile';
    if (($payload['games']['duels_played'] ?? 0) > 0) $slides[] = 'games';

    // Subway ha una schermata sua: il record e un tempo, non un conteggio.
    if (!empty($payload['games']['subway_best_ms']))     $slides[] = 'subway';
    if (!empty($payload['content']['has_data']))       $slides[] = 'content';

    // Il post migliore merita la sua schermata solo se qualcuno lo ha
    // davvero guardato, commentato o apprezzato.
    if (!empty($payload['content']['best_post'])
        || !empty($payload['content']['most_viewed'])
        || !empty($payload['content']['most_commented'])
        || !empty($payload['content']['top_rimasto'])) {
        $slides[] = 'top_post';
    }

    if (!empty($payload['economy']['has_data']))       $slides[] = 'economy';
    if (!empty($payload['calendar']['busiest_day']))   $slides[] = 'busiest_day';

    $slides[] = 'persona';
    $slides[] = 'summary';

    // La card chiude il racconto: e quello che si porta via.
    $slides[] = 'card';

    return $slides;
}

// ─────────────────────────────────────────────────────────────
//  CONDIVISIONE
// ─────────────────────────────────────────────────────────────

/**
 * Genera (o riusa) il token pubblico del Rewind.
 *
 * @return string|null il token, oppure null se qualcosa non va
 */
function rewind_enable_share(mysqli $mysqli, int $userId, string $periodKey): ?string
{
    if (!rewind_available($mysqli)) {
        return null;
    }

    $existing = rewind_row(
        $mysqli,
        'SELECT share_token FROM user_rewind WHERE utente_id = ? AND period_key = ? LIMIT 1',
        'is',
        [$userId, $periodKey]
    );

    if ($existing === null) {
        return null; // niente Rewind da condividere
    }

    $token = (string)($existing['share_token'] ?? '');
    if ($token === '') {
        // 16 byte in base64url: 22 caratteri, non indovinabili.
        $token = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    $stmt = $mysqli->prepare(
        'UPDATE user_rewind SET share_token = ?, is_public = 1 WHERE utente_id = ? AND period_key = ?'
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('sis', $token, $userId, $periodKey);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok ? $token : null;
}

/** Revoca il link pubblico. Il token viene azzerato, non riutilizzato. */
function rewind_disable_share(mysqli $mysqli, int $userId, string $periodKey): bool
{
    if (!rewind_available($mysqli)) {
        return false;
    }

    $stmt = $mysqli->prepare(
        'UPDATE user_rewind SET is_public = 0, share_token = NULL WHERE utente_id = ? AND period_key = ?'
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('is', $userId, $periodKey);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/**
 * Carica un Rewind dal suo token pubblico e ne conta la visita.
 *
 * @return array|null payload già ripulito per la vista pubblica
 */
function rewind_load_shared(mysqli $mysqli, string $token): ?array
{
    if (!rewind_available($mysqli) || !preg_match('/^[A-Za-z0-9_-]{22}$/', $token)) {
        return null;
    }

    $row = rewind_row(
        $mysqli,
        'SELECT utente_id, payload, period_key FROM user_rewind
         WHERE share_token = ? AND is_public = 1 LIMIT 1',
        's',
        [$token]
    );

    if ($row === null) {
        return null;
    }

    $payload = json_decode((string)$row['payload'], true);
    if (!is_array($payload)) {
        return null;
    }

    $stmt = $mysqli->prepare('UPDATE user_rewind SET share_views = share_views + 1 WHERE share_token = ?');
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $stmt->close();
    }

    return rewind_public_payload($payload);
}

/**
 * Riduce il payload a ciò che può stare su una pagina pubblica.
 *
 * Via tutto ciò che riguarda altre persone o che l'utente non si aspetta di
 * rendere pubblico condividendo un riepilogo: il nome dell'amico più
 * chattato, il saldo Godos, i conteggi delle visite ricevute.
 */
function rewind_public_payload(array $payload): array
{
    unset(
        $payload['social']['top_partner'],
        $payload['social']['busiest_day'],
        $payload['economy']['balance'],
        $payload['profile']['views_in_period'],
        $payload['calendar']['days']
    );

    $payload['is_public_view'] = true;

    return $payload;
}

// ─────────────────────────────────────────────────────────────
//  PRECALCOLO
// ─────────────────────────────────────────────────────────────

/**
 * Ricalcola le distribuzioni globali usate per il "sei nel top X%".
 *
 * I percentili si ottengono ordinando i totali di tutti gli utenti e
 * pescando le posizioni giuste. Su un'installazione di questa taglia sono
 * poche migliaia di righe: si fa in memoria, senza finestre analitiche che
 * MariaDB 10.1 non avrebbe.
 */
function rewind_recompute_aggregates(mysqli $mysqli, string $periodKey = 'all'): bool
{
    if (!rewind_available($mysqli)) {
        return false;
    }

    $period = rewind_period($periodKey);

    try {
        $rows = rewind_rows(
            $mysqli,
            'SELECT COALESCE(SUM(seconds_active), 0) AS total
             FROM user_daily_stats
             WHERE `day` BETWEEN ? AND ?
             GROUP BY utente_id
             HAVING total > 0
             ORDER BY total ASC',
            'ss',
            [$period['start'], $period['end']]
        );

        $values = array_map(static fn($r) => (int)$r['total'], $rows);
        $count = count($values);
        if ($count === 0) {
            return false;
        }

        $at = static function (float $p) use ($values, $count): int {
            $index = (int)floor(($count - 1) * $p);
            return $values[max(0, min($count - 1, $index))];
        };

        $stmt = $mysqli->prepare('
            INSERT INTO rewind_year_aggregates
                (period_key, metric, p50, p75, p90, p95, p99, max_value, total_users, computed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                p50 = VALUES(p50), p75 = VALUES(p75), p90 = VALUES(p90),
                p95 = VALUES(p95), p99 = VALUES(p99), max_value = VALUES(max_value),
                total_users = VALUES(total_users), computed_at = NOW()
        ');
        if (!$stmt) {
            return false;
        }

        $metric = 'seconds_active';
        $p50 = $at(0.50);
        $p75 = $at(0.75);
        $p90 = $at(0.90);
        $p95 = $at(0.95);
        $p99 = $at(0.99);
        $max = end($values);

        $stmt->bind_param(
            'ssiiiiiii',
            $period['key'], $metric, $p50, $p75, $p90, $p95, $p99, $max, $count
        );
        $stmt->execute();
        $stmt->close();

        return true;
    } catch (Throwable $e) {
        error_log('[rewind_recompute_aggregates] ' . $e->getMessage());
        return false;
    }
}

/**
 * Rigenera i Rewind più stantii, poche unità alla volta.
 *
 * Stessa strategia già usata da account_maybe_run_scheduled_purge: il lavoro
 * viaggia sul traffico normale del sito, al massimo una volta l'ora, e nessun
 * visitatore paga più di qualche decina di millisecondi. Se qualcuno imposta
 * un cron vero su api/rewind/precompute.php, questa diventa una funzione che
 * non trova mai niente da fare.
 */
function rewind_maybe_precompute(mysqli $mysqli, int $batch = 2): void
{
    $lock = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cripsum_rewind_last_run';

    $last = @filemtime($lock);
    if ($last !== false && (time() - $last) < 3600) {
        return;
    }

    // Il file va toccato subito: due richieste in parallelo non devono
    // entrambe mettersi a rigenerare.
    @touch($lock);

    try {
        rewind_recompute_aggregates($mysqli, 'all');
        rewind_precompute_batch($mysqli, $batch);
    } catch (Throwable $e) {
        error_log('[rewind_maybe_precompute] ' . $e->getMessage());
    }
}

/**
 * Rigenera i Rewind scaduti degli utenti attivi di recente.
 *
 * @return int quanti ne sono stati rigenerati
 */
function rewind_precompute_batch(mysqli $mysqli, int $limit = 10): int
{
    if (!rewind_available($mysqli)) {
        return 0;
    }

    // Solo chi è passato nell'ultima settimana: rigenerare il Rewind di un
    // account fermo da mesi è lavoro sprecato, verrà creato su richiesta.
    $rows = rewind_rows(
        $mysqli,
        'SELECT t.utente_id
         FROM user_stat_totals t
         LEFT JOIN user_rewind r ON r.utente_id = t.utente_id AND r.period_key = ?
         WHERE t.last_active_day >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
           AND (r.generated_at IS NULL OR r.generated_at < DATE_SUB(NOW(), INTERVAL 1 DAY))
         ORDER BY r.generated_at IS NOT NULL, r.generated_at ASC
         LIMIT ' . max(1, min(100, $limit)),
        's',
        ['all']
    );

    $done = 0;
    foreach ($rows as $row) {
        $userId = (int)$row['utente_id'];
        try {
            rewind_get_or_build($mysqli, $userId, 'all', true);
            $done++;
        } catch (Throwable $e) {
            error_log('[rewind_precompute_batch] utente ' . $userId . ': ' . $e->getMessage());
        }
    }

    return $done;
}

// ─────────────────────────────────────────────────────────────
//  PREFERENZE PRIVACY
// ─────────────────────────────────────────────────────────────

/** Valori validi quando l'utente non ha mai toccato nulla. */
function rewind_default_prefs(): array
{
    return [
        'tracking_enabled' => true,
        'rewind_public'    => false,
        'share_top_friend' => true,
    ];
}

/** @return array{tracking_enabled:bool,rewind_public:bool,share_top_friend:bool} */
function rewind_get_prefs(mysqli $mysqli, int $userId): array
{
    $defaults = rewind_default_prefs();

    $row = rewind_row(
        $mysqli,
        'SELECT tracking_enabled, rewind_public, share_top_friend
         FROM user_stats_prefs WHERE utente_id = ? LIMIT 1',
        'i',
        [$userId]
    );

    if ($row === null) {
        return $defaults;
    }

    return [
        'tracking_enabled' => (int)$row['tracking_enabled'] === 1,
        'rewind_public'    => (int)$row['rewind_public'] === 1,
        'share_top_friend' => (int)$row['share_top_friend'] === 1,
    ];
}

/** Salva le preferenze e invalida la cache di sessione del tracker. */
function rewind_save_prefs(mysqli $mysqli, int $userId, bool $tracking, bool $shareTopFriend): bool
{
    $stmt = $mysqli->prepare('
        INSERT INTO user_stats_prefs (utente_id, tracking_enabled, share_top_friend)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            tracking_enabled = VALUES(tracking_enabled),
            share_top_friend = VALUES(share_top_friend)
    ');
    if (!$stmt) {
        return false;
    }

    $trackingInt = $tracking ? 1 : 0;
    $shareInt = $shareTopFriend ? 1 : 0;
    $stmt->bind_param('iii', $userId, $trackingInt, $shareInt);
    $ok = $stmt->execute();
    $stmt->close();

    // Senza questo, stats_tracking_allowed() continuerebbe a rispondere col
    // valore vecchio fino alla scadenza della sessione.
    stats_forget_prefs();

    return $ok;
}

/**
 * Cancella tutte le statistiche dell'utente.
 *
 * Tocca solo le tabelle introdotte da questa funzionalità. Achievement, pull
 * e messaggi restano dove sono: appartengono ad altre parti del sito e
 * cancellarli qui sarebbe una sorpresa spiacevole per chi voleva solo
 * togliere di mezzo il conteggio del tempo.
 */
function rewind_purge_stats(mysqli $mysqli, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }

    // Gli eventi ancora in coda verrebbero scritti dopo la cancellazione.
    stats_flush($mysqli);

    $tables = [
        'user_daily_stats',
        'user_page_stats',
        'user_activity_sessions',
        'user_legacy_days',
        'user_stat_totals',
        'user_rewind',
    ];

    $ok = true;

    foreach ($tables as $table) {
        // I nomi sono costanti scritte qui sopra, non arrivano da fuori.
        $stmt = @$mysqli->prepare("DELETE FROM `$table` WHERE utente_id = ?");
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) {
            $ok = false;
        }
        $stmt->close();
    }

    return $ok;
}

/**
 * Gestisce le due azioni POST del pannello impostazioni.
 *
 * Ritorna null se l'azione non è di sua competenza, altrimenti
 * ['ok' => bool, 'message' => string].
 */
function rewind_settings_handle_post(mysqli $mysqli, int $userId, string $action, bool $isEn = false): ?array
{
    if ($action === 'update_rewind_prefs') {
        $ok = rewind_save_prefs(
            $mysqli,
            $userId,
            !empty($_POST['rewind_tracking']),
            !empty($_POST['rewind_share_partner'])
        );

        return [
            'ok' => $ok,
            'message' => $ok
                ? ($isEn ? 'Settings saved.' : 'Impostazioni salvate.')
                : ($isEn ? 'Could not save. Try again.' : 'Non è stato possibile salvare. Riprova.'),
        ];
    }

    if ($action === 'purge_rewind_stats') {
        if (empty($_POST['rewind_purge_ack'])) {
            return [
                'ok' => false,
                'message' => $isEn ? 'Tick the confirmation box first.' : 'Spunta prima la casella di conferma.',
            ];
        }

        $ok = rewind_purge_stats($mysqli, $userId);

        return [
            'ok' => $ok,
            'message' => $ok
                ? ($isEn ? 'Your stats have been deleted.' : 'Le tue statistiche sono state cancellate.')
                : ($isEn ? 'Deletion failed. Try again.' : 'Cancellazione non riuscita. Riprova.'),
        ];
    }

    return null;
}

// ─────────────────────────────────────────────────────────────
//  LINGUA
// ─────────────────────────────────────────────────────────────

/**
 * Determina la lingua della richiesta corrente.
 *
 * Nell'ordine: un parametro esplicito, il prefisso nel percorso o nel
 * referer (/it/ oppure /en/), e infine la preferenza dichiarata dal browser.
 * Serve soprattutto agli endpoint API e alla pagina pubblica, che non stanno
 * dentro le cartelle it/ ed en/ e quindi non possono dedurla dal percorso.
 */
function rewind_request_lang(?string $explicit = null): string
{
    $candidates = [
        $explicit,
        $_GET['lang'] ?? null,
        $_POST['lang'] ?? null,
    ];

    foreach ($candidates as $value) {
        if ($value === 'en' || $value === 'it') {
            return $value;
        }
    }

    foreach ([$_SERVER['REQUEST_URI'] ?? '', $_SERVER['HTTP_REFERER'] ?? ''] as $path) {
        $path = (string)$path;
        if (str_contains($path, '/en/')) return 'en';
        if (str_contains($path, '/it/')) return 'it';
    }

    // Accept-Language: basta il primo tag, non serve pesare le qualità.
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    if ($accept !== '' && !str_starts_with(ltrim($accept), 'it')) {
        // Qualsiasi cosa che non sia italiano viene servita in inglese:
        // è la scelta meno peggio per un visitatore che arriva da fuori.
        return 'en';
    }

    return 'it';
}

/**
 * Messaggi degli endpoint del Rewind nelle due lingue.
 *
 * Il front-end mostra `result.error` così com'è quando qualcosa va storto,
 * quindi questi testi finiscono davvero sotto gli occhi di chi legge: non
 * possono restare solo in italiano.
 */
function rewind_msg(string $key, string $lang = 'it'): string
{
    static $messages = [
        'unauthenticated' => ['it' => 'Non autenticato.', 'en' => 'Not signed in.'],
        'method'          => ['it' => 'Metodo non consentito.', 'en' => 'Method not allowed.'],
        'schema_missing'  => ['it' => 'Il Rewind non è ancora attivo.', 'en' => 'Rewind is not active yet.'],
        'internal'        => ['it' => 'Errore interno.', 'en' => 'Internal error.'],
        'build_failed'    => ['it' => 'Non è stato possibile generare il Rewind.', 'en' => 'Could not generate your Rewind.'],
        'csrf'            => ['it' => 'Sessione scaduta. Ricarica la pagina.', 'en' => 'Session expired. Please reload the page.'],
        'no_rewind'       => ['it' => 'Genera prima il tuo Rewind.', 'en' => 'Generate your Rewind first.'],
        'bad_action'      => ['it' => 'Azione non valida.', 'en' => 'Invalid action.'],
    ];

    $lang = $lang === 'en' ? 'en' : 'it';

    return $messages[$key][$lang] ?? $messages[$key]['it'] ?? $key;
}

/**
 * URL pubblico di un Rewind condiviso.
 *
 * Il prefisso di lingua è nell'indirizzo perché la pagina pubblica non ha una
 * sessione da cui dedurla: chi condivide dall'inglese passa un link inglese.
 */
function rewind_share_url(string $token, string $lang = 'it'): string
{
    $lang = $lang === 'en' ? 'en' : 'it';

    return 'https://cripsum.com/' . $lang . '/rewind/' . $token;
}

/**
 * Indirizzo di un asset del Rewind con la versione presa dal file stesso.
 *
 * I `?v=` scritti a mano vanno aggiornati a ogni modifica, e prima o poi ci
 * si dimentica: il browser continua a servire il vecchio file e sembra che
 * le modifiche non siano state applicate. Qui la versione è la data di
 * modifica del file, quindi cambia da sola ogni volta che il file cambia e
 * resta stabile finché non cambia.
 */
function rewind_asset(string $path): string
{
    $full = __DIR__ . '/..' . $path;
    $stamp = @filemtime($full);

    return $path . '?v=' . ($stamp !== false ? $stamp : '1');
}
