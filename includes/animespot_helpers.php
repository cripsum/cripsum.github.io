<?php

/**
 * Cripsum™ — Animespot
 *
 * Songspot con le sigle degli anime: parte un frammento di opening o ending e
 * si deve capire di quale serie è. Il frammento si allunga a ogni errore
 * (0,1s → 0,5s → 2s → 8s → 15s) e chi indovina presto prende più punti.
 *
 * Si gioca quanto si vuole, come nel Pullspot: finita una sigla ne parte
 * un'altra, non c'è nessun calendario e nessun limite giornaliero. Quello che
 * si sceglie è la difficoltà — cinque livelli, dalle sigle che conoscono tutti
 * a quelle che non ha sentito nessuno —, l'epoca, se il frammento parte
 * dall'inizio o da metà sigla, e quali frammenti tenere accesi.
 *
 * Quattro regole guidano il codice qui dentro:
 *
 * 1. il client non deve mai poter sapere la risposta prima della fine. Il nome
 *    del file audio è il titolo dell'anime, quindi l'audio passa da un proxy
 *    (api/animespot/audio.php) che serve solo i secondi già sbloccati;
 * 2. il catalogo non si scrive a mano: lo ha costruito da AnimeThemes e Kitsu
 *    lo script d'importazione, che non sta più nel repository perché serviva
 *    una volta sola — si ripesca dal commit f885176 se un giorno va rifatto;
 * 3. le tabelle possono non esistere ancora (le migration di questo progetto si
 *    applicano a mano). Senza catalogo il gioco lo dice, senza storico si gioca
 *    lo stesso e si perdono solo le statistiche;
 * 4. i file audio restano su a.animethemes.moe. Ne teniamo in cache solo
 *    l'inizio, quel tanto che basta al frammento più lungo.
 */

if (!defined('CRIPSUM_ANIMESPOT_HELPERS')) {
    define('CRIPSUM_ANIMESPOT_HELPERS', true);
}

require_once __DIR__ . '/security_helpers.php';

/* ── Regole del gioco ───────────────────────────────────────────────────── */

/** Durate sbloccate, in secondi, tentativo per tentativo. */
const ANIMESPOT_STEPS = [0.1, 0.5, 2.0, 8.0, 15.0];

/** Punti per chi indovina a ciascuno di quei frammenti. */
const ANIMESPOT_POINTS = [1200, 975, 750, 525, 300];

/** Le cinque difficoltà, dalla più gentile alla più cattiva. */
const ANIMESPOT_LEVELS = [1, 2, 3, 4, 5];

/**
 * Le epoche fra cui si può restringere il mazzo, come l'"era" dell'originale.
 *
 * Ogni voce è [primo anno, ultimo anno]; 0 vuol dire tutte. I decenni sono
 * quelli veri dell'animazione giapponese, non fette da dieci anni tirate a
 * caso: prima del Duemila c'è un mondo solo, dopo cambia ogni decennio.
 */
const ANIMESPOT_ERAS = [
    1 => [null, 1999],
    2 => [2000, 2009],
    3 => [2010, 2019],
    4 => [2020, null],
];

/** Da dove parte il frammento quando si gioca "ad anteprima". */
const ANIMESPOT_PREVIEW_FROM = 18.0;
const ANIMESPOT_PREVIEW_TO   = 42.0;

/** Quante sigle appena uscite evitare prima di poterle ripescare. */
const ANIMESPOT_RECENT_MEMORY = 40;

const ANIMESPOT_TABLE_ANIME  = 'animespot_anime';
const ANIMESPOT_TABLE_TITLES = 'animespot_titoli';
const ANIMESPOT_TABLE_TRACKS = 'animespot_tracce';
const ANIMESPOT_TABLE_GAMES  = 'animespot_partite';

/** Dove sta l'inizio dei file audio che abbiamo già scaricato. */
const ANIMESPOT_CACHE_DIR = __DIR__ . '/../scratch/animespot-audio';

/** Quanto può crescere quella cartella prima di potare i file più vecchi. */
const ANIMESPOT_CACHE_MAX = 268435456; // 256 MB

/** Da dove arrivano audio e video delle sigle. */
const ANIMESPOT_AUDIO_HOST = 'https://a.animethemes.moe/';
const ANIMESPOT_VIDEO_HOST = 'https://v.animethemes.moe/';

function animespot_steps(): array
{
    return ANIMESPOT_STEPS;
}

function animespot_max_attempts(): int
{
    return count(ANIMESPOT_STEPS);
}

/** URL di un asset con la data di modifica in coda, per non servire cache vecchia. */
function animespot_asset(string $path): string
{
    $stamp = @filemtime(__DIR__ . '/..' . $path);

    return $path . '?v=' . ($stamp !== false ? $stamp : '1');
}

function animespot_request_lang(): string
{
    $lang = strtolower((string)($_GET['lang'] ?? ''));
    if ($lang === 'it' || $lang === 'en') return $lang;

    return str_contains((string)($_SERVER['HTTP_REFERER'] ?? ''), '/en/') ? 'en' : 'it';
}

function animespot_msg(string $key, string $lang = 'it'): string
{
    $messages = [
        'unauthenticated' => ['it' => 'Devi essere loggato per giocare.',                     'en' => 'You need to be logged in to play.'],
        'method'          => ['it' => 'Metodo non consentito.',                               'en' => 'Method not allowed.'],
        'csrf'            => ['it' => 'Sessione scaduta, ricarica la pagina.',                'en' => 'Session expired, reload the page.'],
        'no_catalog'      => ['it' => 'Il catalogo delle sigle non è ancora stato importato.', 'en' => 'The theme catalogue has not been imported yet.'],
        'no_pool'         => ['it' => 'Nessuna sigla disponibile per questa difficoltà.',      'en' => 'No theme available at this difficulty.'],
        'finished'        => ['it' => 'Questa sigla è già finita.',                            'en' => 'This round is already over.'],
        'bad_guess'       => ['it' => 'Anime non valido.',                                     'en' => 'Invalid anime.'],
        'repeat'          => ['it' => 'Questo anime lo hai già provato.',                      'en' => 'You have already tried that anime.'],
        'no_audio'        => ['it' => 'Traccia non disponibile.',                              'en' => 'Track unavailable.'],
    ];

    return $messages[$key][$lang] ?? $messages[$key]['it'] ?? $key;
}

function animespot_level_name(int $level, string $lang = 'it'): string
{
    $names = [
        'it' => [1 => 'Facile', 2 => 'Media',  3 => 'Difficile', 4 => 'Esperto', 5 => 'Impossibile'],
        'en' => [1 => 'Easy',   2 => 'Medium', 3 => 'Hard',      4 => 'Expert',  5 => 'Impossible'],
    ];

    return $names[$lang][$level] ?? $names['it'][$level] ?? (string)$level;
}

function animespot_era_name(int $era, string $lang = 'it'): string
{
    $names = [
        'it' => [0 => 'Qualsiasi epoca', 1 => 'Classici', 2 => 'Anni 2000', 3 => 'Anni 2010', 4 => 'Anni 2020'],
        'en' => [0 => 'Any era',         1 => 'Classics', 2 => '2000s',     3 => '2010s',     4 => '2020s'],
    ];

    return $names[$lang][$era] ?? $names['it'][$era] ?? (string)$era;
}

/* ── Catalogo ───────────────────────────────────────────────────────────── */

/**
 * La condizione SQL che tiene solo gli anime di un'epoca.
 *
 * Torna una stringa da incollare nella query e non un parametro perché gli
 * estremi vengono da una costante del codice, mai da chi chiama: non c'è
 * niente da legare e una `?` in più renderebbe ogni query diversa dall'altra.
 */
function animespot_era_where(int $era, string $alias = 'a'): string
{
    if (!isset(ANIMESPOT_ERAS[$era])) return '';

    [$from, $to] = ANIMESPOT_ERAS[$era];
    $where = '';

    if ($from !== null) $where .= ' AND `' . $alias . '`.`anno` >= ' . (int)$from;
    if ($to !== null)   $where .= ' AND `' . $alias . '`.`anno` <= ' . (int)$to;

    // Un anime senza anno non appartiene a nessuna epoca: fuori da tutte
    // tranne che da "qualsiasi", dove non si sta chiedendo niente.
    return ' AND `' . $alias . '`.`anno` IS NOT NULL' . $where;
}

/** Il catalogo è stato importato? Senza, non c'è niente da giocare. */
function animespot_catalog_ready(mysqli $mysqli): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;

    if (!auth_table_exists($mysqli, ANIMESPOT_TABLE_TRACKS)
        || !auth_table_exists($mysqli, ANIMESPOT_TABLE_ANIME)
        || !auth_table_exists($mysqli, ANIMESPOT_TABLE_TITLES)) {
        return $ready = false;
    }

    $result = $mysqli->query('SELECT 1 FROM `' . ANIMESPOT_TABLE_TRACKS . '` LIMIT 1');

    return $ready = ($result !== false && $result->num_rows > 0);
}

/** La tabella dello storico esiste? Se no si gioca lo stesso, senza statistiche. */
function animespot_state_ready(mysqli $mysqli): bool
{
    return auth_table_exists($mysqli, ANIMESPOT_TABLE_GAMES);
}

/**
 * Quante sigle ci sono per ogni difficoltà dentro un'epoca.
 *
 * Serve due volte: a pescare (senza il totale non si sa dove saltare) e alla
 * pagina, che scrive il numero accanto a ogni livello. È anche il modo in cui
 * si scopre che una combinazione è vuota prima di provarci.
 */
function animespot_counts(mysqli $mysqli, int $era = 0): array
{
    static $cache = [];
    if (isset($cache[$era])) return $cache[$era];

    $counts = array_fill_keys(ANIMESPOT_LEVELS, 0);
    if (!animespot_catalog_ready($mysqli)) return $cache[$era] = $counts;

    $result = $mysqli->query(
        'SELECT t.difficolta, COUNT(*) AS n'
        . ' FROM `' . ANIMESPOT_TABLE_TRACKS . '` t'
        . ' JOIN `' . ANIMESPOT_TABLE_ANIME . '` a ON a.id = t.anime_id'
        . ' WHERE 1' . animespot_era_where($era)
        . ' GROUP BY t.difficolta'
    );
    if (!$result) return $cache[$era] = $counts;

    while ($row = $result->fetch_assoc()) {
        $level = (int)$row['difficolta'];
        if (isset($counts[$level])) $counts[$level] = (int)$row['n'];
    }
    $result->free();

    return $cache[$era] = $counts;
}

/** I conteggi di tutte le epoche insieme, per la fila dei pulsanti in cima. */
function animespot_era_counts(mysqli $mysqli): array
{
    $totals = [];
    foreach (array_merge([0], array_keys(ANIMESPOT_ERAS)) as $era) {
        $totals[$era] = array_sum(animespot_counts($mysqli, $era));
    }

    return $totals;
}

/** La riga completa di una sigla, anime compreso. */
function animespot_track(mysqli $mysqli, int $id): ?array
{
    static $cache = [];
    if (array_key_exists($id, $cache)) return $cache[$id];

    if ($id <= 0 || !animespot_catalog_ready($mysqli)) return $cache[$id] = null;

    $stmt = $mysqli->prepare(
        'SELECT t.id, t.theme_id, t.anime_id, t.tipo, t.slug, t.ordinale, t.canzone_id, t.canzone,'
        . ' t.artisti, t.audio, t.audio_bytes, t.video, t.difficolta,'
        . ' a.nome AS anime, a.slug AS anime_slug, a.anno, a.stagione, a.formato, a.mal_id, a.cover_url'
        . ' FROM `' . ANIMESPOT_TABLE_TRACKS . '` t'
        . ' JOIN `' . ANIMESPOT_TABLE_ANIME . '` a ON a.id = t.anime_id'
        . ' WHERE t.id = ? LIMIT 1'
    );
    if (!$stmt) return $cache[$id] = null;

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $cache[$id] = ($row ?: null);
}

/**
 * Pesca una sigla a caso a una certa difficoltà.
 *
 * Il salto casuale con OFFSET su una colonna indicizzata costa poco e non
 * obbliga a tenere in memoria quattordicimila righe a ogni partita. Le sigle
 * appena uscite si evitano riprovando: dopo qualche tentativo ci si arrende,
 * perché in un mazzo piccolo insistere vorrebbe dire non pescare più niente.
 */
function animespot_pick(mysqli $mysqli, int $level, int $era = 0, array $avoid = []): ?array
{
    $counts = animespot_counts($mysqli, $era);
    $total  = $counts[$level] ?? 0;
    if ($total <= 0) return null;

    $stmt = $mysqli->prepare(
        'SELECT t.id FROM `' . ANIMESPOT_TABLE_TRACKS . '` t'
        . ' JOIN `' . ANIMESPOT_TABLE_ANIME . '` a ON a.id = t.anime_id'
        . ' WHERE t.difficolta = ?' . animespot_era_where($era)
        . ' ORDER BY t.id LIMIT 1 OFFSET ?'
    );
    if (!$stmt) return null;

    $chosen = 0;
    for ($try = 0; $try < 12; $try++) {
        $offset = random_int(0, $total - 1);
        $stmt->bind_param('ii', $level, $offset);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) continue;

        $chosen = (int)$row['id'];
        if (!in_array($chosen, $avoid, true)) break;
    }
    $stmt->close();

    return $chosen > 0 ? animespot_track($mysqli, $chosen) : null;
}

/* ── Ricerca ────────────────────────────────────────────────────────────── */

/**
 * La forma su cui si confrontano i titoli. È la stessa che ha usato lo script
 * d'importazione per riempire la colonna `norm`: cambiarla qui senza rifare il
 * catalogo vuol dire smettere di trovare quello che c'è.
 */
function animespot_normalize(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');

    $accents = [
        'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','ā'=>'a','ă'=>'a','ą'=>'a',
        'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ē'=>'e','ĕ'=>'e','ė'=>'e','ę'=>'e','ě'=>'e',
        'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ī'=>'i','į'=>'i','ı'=>'i',
        'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o','ō'=>'o','ŏ'=>'o',
        'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ū'=>'u','ŭ'=>'u','ů'=>'u',
        'ç'=>'c','ć'=>'c','č'=>'c','ñ'=>'n','ń'=>'n','ß'=>'ss','ÿ'=>'y','ý'=>'y',
        'š'=>'s','ś'=>'s','ž'=>'z','ź'=>'z','ż'=>'z','ł'=>'l','đ'=>'d','þ'=>'th','æ'=>'ae','œ'=>'oe',
        '★'=>' ','☆'=>' ','♪'=>' ','×'=>'x','～'=>' ','〜'=>' ','・'=>' ','　'=>' ',
    ];
    $text = strtr($text, $accents);

    $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

/**
 * Gli anime che si possono scegliere scrivendo `$query`.
 *
 * Cerca su tutti i modi in cui una serie si chiama — titolo giapponese in
 * caratteri latini, titolo inglese, sinonimi — e anche sui titoli delle
 * canzoni, perché in un gioco sulle sigle riconoscere la canzone e non la
 * serie è un modo legittimo di arrivarci.
 *
 * Chi comincia con quello che si è scritto viene prima di chi se lo ritrova in
 * mezzo, e a parità vince l'anime più conosciuto: è l'ordine che rende utile
 * un elenco di dieci righe su ottomila serie.
 */
function animespot_search(mysqli $mysqli, string $query, int $limit = 10, string $mode = 'facile'): array
{
    if (!animespot_catalog_ready($mysqli)) return [];

    $norm = animespot_normalize($query);
    if ($norm === '') return [];

    $limit  = max(1, min(25, $limit));
    $prefix = $norm . '%';
    $word   = '% ' . $norm . '%';
    $inside = '%' . $norm . '%';

    // Il punteggio di rilevanza compare due volte (per ordinare i gruppi e per
    // scegliere quale titolo mostrare dentro il gruppo): scriverlo una volta
    // sola vorrebbe dire una sottoquery, che qui costa più di quanto risparmi.
    $score = 'CASE WHEN t.norm = ? THEN 0 WHEN t.norm LIKE ? THEN 1 WHEN t.norm LIKE ? THEN 2 ELSE 3 END';

    // Ricerca stretta: solo i titoli delle serie (niente canzoni) e solo da
    // inizio parola. È la scelta di chi non vuole che il campo gli suggerisca
    // la risposta appena butta lì tre lettere.
    $strict = $mode === 'stretta';
    $where  = $strict
        ? '(t.norm LIKE ? OR t.norm LIKE ?) AND t.tipo <> \'canzone\''
        : 't.norm LIKE ?';

    $sql =
        'SELECT a.id, a.nome, a.anno, a.formato, a.cover_url,'
        . ' MIN(' . $score . ') AS rilevanza,'
        . ' SUBSTRING_INDEX(GROUP_CONCAT(t.testo ORDER BY ' . $score . ','
        . '   FIELD(t.tipo, \'principale\', \'inglese\', \'sinonimo\', \'giapponese\', \'canzone\')'
        . '   SEPARATOR \'\\n\'), \'\\n\', 1) AS trovato'
        . ' FROM `' . ANIMESPOT_TABLE_TITLES . '` t'
        . ' JOIN `' . ANIMESPOT_TABLE_ANIME . '` a ON a.id = t.anime_id'
        . ' WHERE ' . $where
        . ' GROUP BY a.id, a.nome, a.anno, a.formato, a.cover_url, a.popolarita'
        . ' ORDER BY rilevanza ASC, a.popolarita DESC, a.nome ASC'
        . ' LIMIT ?';

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];

    if ($strict) {
        $stmt->bind_param('ssssssssi', $norm, $prefix, $word, $norm, $prefix, $word, $prefix, $word, $limit);
    } else {
        $stmt->bind_param('sssssssi', $norm, $prefix, $word, $norm, $prefix, $word, $inside, $limit);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $found = (string)$row['trovato'];
        $name  = (string)$row['nome'];

        $rows[] = [
            'id'      => (int)$row['id'],
            'nome'    => $name,
            'anno'    => $row['anno'] !== null ? (int)$row['anno'] : null,
            'formato' => (string)$row['formato'],
            'cover'   => (string)$row['cover_url'],
            // Il titolo con cui si è trovato l'anime, quando non è quello
            // principale: senza, chi scrive il nome inglese vede comparire una
            // riga in giapponese che sembra un'altra serie.
            'via'     => ($found !== '' && $found !== $name) ? $found : null,
        ];
    }
    $stmt->close();

    return $rows;
}

/** Nome e copertina di un anime, per mostrarlo fra i tentativi. */
function animespot_anime(mysqli $mysqli, int $animeId): ?array
{
    if ($animeId <= 0 || !animespot_catalog_ready($mysqli)) return null;

    $stmt = $mysqli->prepare(
        'SELECT id, nome, anno, cover_url FROM `' . ANIMESPOT_TABLE_ANIME . '` WHERE id = ? LIMIT 1'
    );
    if (!$stmt) return null;

    $stmt->bind_param('i', $animeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Il tentativo è giusto?
 *
 * Oltre alla serie esatta vale anche un'altra serie che usa lo stesso identico
 * file audio: capita con i riassunti e le riedizioni, e sarebbe ingiusto dare
 * torto a chi ha riconosciuto la sigla per davvero.
 */
function animespot_is_correct(mysqli $mysqli, int $guessAnimeId, array $track): bool
{
    if ($guessAnimeId === (int)$track['anime_id']) return true;

    $stmt = $mysqli->prepare(
        'SELECT 1 FROM `' . ANIMESPOT_TABLE_TRACKS . '` WHERE anime_id = ? AND audio = ? LIMIT 1'
    );
    if (!$stmt) return false;

    $audio = (string)$track['audio'];
    $stmt->bind_param('is', $guessAnimeId, $audio);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $found;
}

/* ── Opzioni del giocatore ──────────────────────────────────────────────── */

/**
 * Difficoltà scelta e frammenti attivi.
 *
 * I frammenti si possono spegnere (è una funzione dell'originale: chi vuole
 * cominciare da due secondi lo può fare), ma l'ultimo non si tocca mai,
 * altrimenti si arriverebbe alla fine dei tentativi senza aver mai sentito la
 * sigla per intero.
 */
function animespot_options(): array
{
    $raw = $_SESSION['animespot']['opts'] ?? [];

    return animespot_clean_options(is_array($raw) ? $raw : []);
}

/**
 * Ripulisce le opzioni tenendo quelle vecchie per ciò che non è stato detto.
 *
 * Arriva da fuori, quindi non ci si fida di niente: un livello inventato torna
 * a uno, un'epoca inventata torna a "qualsiasi", e l'ultimo frammento si
 * riaccende sempre — senza, si finirebbero i tentativi senza aver mai sentito
 * la sigla per intero.
 */
function animespot_clean_options(array $raw): array
{
    $level = (int)($raw['difficolta'] ?? 1);
    if (!in_array($level, ANIMESPOT_LEVELS, true)) $level = 1;

    $era = (int)($raw['era'] ?? 0);
    if ($era !== 0 && !isset(ANIMESPOT_ERAS[$era])) $era = 0;

    $last  = count(ANIMESPOT_STEPS) - 1;
    $steps = [];
    foreach ((array)($raw['passi'] ?? []) as $index) {
        $index = (int)$index;
        if ($index >= 0 && $index <= $last && !in_array($index, $steps, true)) $steps[] = $index;
    }

    if (!$steps) $steps = range(0, $last);
    if (!in_array($last, $steps, true)) $steps[] = $last;
    sort($steps);

    $start  = (string)($raw['avvio'] ?? 'inizio');
    $search = (string)($raw['ricerca'] ?? 'facile');

    return [
        'difficolta' => $level,
        'era'        => $era,
        'passi'      => array_values($steps),
        'avvio'      => $start === 'anteprima' ? 'anteprima' : 'inizio',
        'ricerca'    => $search === 'stretta' ? 'stretta' : 'facile',
    ];
}

function animespot_options_save(array $options): void
{
    if (!isset($_SESSION['animespot']) || !is_array($_SESSION['animespot'])) {
        $_SESSION['animespot'] = [];
    }

    $_SESSION['animespot']['opts'] = $options;
}

/** I secondi veri di una scala di frammenti, dagli indici scelti. */
function animespot_ladder(array $stepIndexes): array
{
    $ladder = [];
    foreach ($stepIndexes as $index) {
        if (isset(ANIMESPOT_STEPS[$index])) $ladder[] = ANIMESPOT_STEPS[$index];
    }

    return $ladder ?: ANIMESPOT_STEPS;
}

/* ── Una partita ────────────────────────────────────────────────────────── */

/**
 * Da che secondo comincia il frammento.
 *
 * "Dall'inizio" è zero, ed è il modo normale. "Ad anteprima" pesca un punto a
 * caso in mezzo alla sigla: è la stessa idea dell'anteprima di trenta secondi
 * dell'originale — si perde l'attacco, che è la parte che tutti riconoscono, e
 * resta da riconoscere il pezzo. Il punto si decide una volta sola, quando la
 * partita nasce: cambiarlo a ogni frammento vorrebbe dire una canzone diversa
 * a ogni tentativo.
 */
function animespot_start_at(array $options): float
{
    if (($options['avvio'] ?? 'inizio') !== 'anteprima') return 0.0;

    $from = (int)(ANIMESPOT_PREVIEW_FROM * 10);
    $to   = (int)(ANIMESPOT_PREVIEW_TO * 10);

    return random_int($from, $to) / 10;
}

function animespot_new_round(array $track, array $stepIndexes, float $startAt = 0.0): array
{
    return [
        'traccia'    => (int)$track['id'],
        'difficolta' => (int)$track['difficolta'],
        'passi'      => array_values($stepIndexes),
        'avvio'      => $startAt,
        'guesses'    => [],
        'status'     => 'playing',
        'recorded'   => false,
    ];
}

/** Ricostruisce una partita da come è stata salvata, scartando ciò che non torna. */
function animespot_normalize_round(mixed $raw): ?array
{
    if (!is_array($raw)) return null;

    $trackId = (int)($raw['traccia'] ?? 0);
    if ($trackId <= 0) return null;

    $last  = count(ANIMESPOT_STEPS) - 1;
    $steps = [];
    foreach ((array)($raw['passi'] ?? []) as $index) {
        $index = (int)$index;
        if ($index >= 0 && $index <= $last && !in_array($index, $steps, true)) $steps[] = $index;
    }
    if (!$steps) $steps = range(0, $last);
    sort($steps);

    $guesses = [];
    foreach ((array)($raw['guesses'] ?? []) as $guess) {
        if (!is_array($guess)) continue;
        $type = (string)($guess['type'] ?? '');
        if (!in_array($type, ['skip', 'wrong', 'correct'], true)) continue;

        $guesses[] = [
            'type'  => $type,
            'id'    => isset($guess['id']) ? (int)$guess['id'] : null,
            'nome'  => isset($guess['nome']) ? (string)$guess['nome'] : null,
            'cover' => isset($guess['cover']) ? (string)$guess['cover'] : null,
        ];
        if (count($guesses) >= count($steps)) break;
    }

    $status = (string)($raw['status'] ?? 'playing');
    if (!in_array($status, ['playing', 'won', 'lost'], true)) $status = 'playing';

    // Il punto di partenza è un secondo dentro la sigla: negativo non vuol dire
    // niente e oltre il minuto non c'è più sigla in cui cadere.
    $startAt = (float)($raw['avvio'] ?? 0);
    if (!is_finite($startAt) || $startAt < 0 || $startAt > 60) $startAt = 0.0;

    return [
        'traccia'    => $trackId,
        'difficolta' => max(1, min(5, (int)($raw['difficolta'] ?? 1))),
        'passi'      => array_values($steps),
        'avvio'      => $startAt,
        'guesses'    => $guesses,
        'status'     => $status,
        'recorded'   => !empty($raw['recorded']),
    ];
}

/** Le ultime sigle uscite, per non riproporle subito. */
function animespot_recent(): array
{
    $recent = $_SESSION['animespot']['recenti'] ?? [];

    return is_array($recent) ? array_map('intval', $recent) : [];
}

function animespot_remember(int $trackId, int $poolSize): void
{
    $recent   = animespot_recent();
    $recent[] = $trackId;

    $keep = max(1, min(ANIMESPOT_RECENT_MEMORY, (int)floor($poolSize / 2)));

    if (!isset($_SESSION['animespot']) || !is_array($_SESSION['animespot'])) {
        $_SESSION['animespot'] = [];
    }
    $_SESSION['animespot']['recenti'] = array_slice($recent, -$keep);
}

/* ── La serie ───────────────────────────────────────────────────────────── */

/*
 * Non si gioca una sigla per volta: se ne gioca una per difficoltà.
 *
 * All'apertura si pescano cinque sigle, una facile, una media, una difficile,
 * una da esperto e una impossibile. Si comincia dalla facile e finita quella
 * si passa alla successiva, fino all'impossibile; poi se ne pescano altre
 * cinque e si ricomincia. È il giro dell'originale, senza calendario.
 *
 * I cinque posti si possono anche visitare a mano, e questa è la ragione per
 * cui la serie sta in sessione tutta insieme invece che una partita alla
 * volta: tornando su una difficoltà già giocata si deve ritrovare quella
 * sigla lì, con i tentativi che si erano fatti, non una nuova.
 */

/** La serie in corso, ripulita da quello che non torna. */
function animespot_series(): array
{
    $raw = $_SESSION['animespot']['serie'] ?? [];
    if (!is_array($raw)) $raw = [];

    $tracks = [];
    foreach (ANIMESPOT_LEVELS as $slot) {
        $id = (int)(($raw['tracce'][$slot] ?? 0));
        if ($id > 0) $tracks[$slot] = $id;
    }

    $rounds = [];
    foreach (ANIMESPOT_LEVELS as $slot) {
        $round = animespot_normalize_round($raw['rounds'][$slot] ?? null);
        if ($round !== null) $rounds[$slot] = $round;
    }

    return [
        'era'    => (int)($raw['era'] ?? 0),
        'tracce' => $tracks,
        'rounds' => $rounds,
    ];
}

function animespot_series_save(array $series): void
{
    if (!isset($_SESSION['animespot']) || !is_array($_SESSION['animespot'])) {
        $_SESSION['animespot'] = [];
    }

    $_SESSION['animespot']['serie'] = $series;
}

/**
 * Pesca cinque sigle nuove, una per difficoltà.
 *
 * Se a una difficoltà l'epoca scelta non lascia niente si allarga l'epoca solo
 * per quel posto: meglio una serie con dentro un classico fuori decennio che
 * una serie di quattro.
 */
function animespot_series_draw(mysqli $mysqli, int $era): array
{
    $tracks = [];
    $avoid  = animespot_recent();

    foreach (ANIMESPOT_LEVELS as $slot) {
        $track = animespot_pick($mysqli, $slot, $era, $avoid);
        if ($track === null && $era !== 0) $track = animespot_pick($mysqli, $slot, 0, $avoid);
        if ($track === null) continue;

        $tracks[$slot] = (int)$track['id'];
        $avoid[] = (int)$track['id'];
        animespot_remember((int)$track['id'], max(1, array_sum(animespot_counts($mysqli, $era))));
    }

    return $tracks;
}

/** Il posto in cui si sta giocando: è la difficoltà scelta. */
function animespot_slot(): int
{
    $slot = animespot_options()['difficolta'];

    return in_array($slot, ANIMESPOT_LEVELS, true) ? $slot : 1;
}

function animespot_slot_save(int $slot): void
{
    $options = animespot_options();
    $options['difficolta'] = in_array($slot, ANIMESPOT_LEVELS, true) ? $slot : 1;
    animespot_options_save($options);
}

/**
 * Il prossimo posto da giocare dopo quello corrente.
 *
 * Si va avanti in ordine di difficoltà e si girano tutti e cinque i posti:
 * chi salta alla difficile e la finisce torna indietro a prendersi la facile
 * che aveva lasciato, invece di trovarsi la serie finita a metà. Quando non
 * resta niente da giocare si restituisce zero, che vuol dire "serie finita".
 */
function animespot_next_slot(array $series, int $from): int
{
    foreach (ANIMESPOT_LEVELS as $step) {
        $slot  = (($from - 1 + $step) % count(ANIMESPOT_LEVELS)) + 1;
        $round = $series['rounds'][$slot] ?? null;

        if ($round === null || $round['status'] === 'playing') return $slot;
    }

    return 0;
}

/**
 * Prepara la serie e la partita del posto in cui si sta.
 *
 * `$restart` ripesca la sigla di questo posto soltanto: è il tasto "nuova
 * sigla", che serve a chi non ha voglia di quella che gli è capitata.
 * `$advance` dice che si sta chiedendo di andare avanti, ed è l'unico caso in
 * cui una serie finita viene sostituita.
 *
 * Restituisce null solo se il catalogo non è stato importato.
 */
function animespot_bootstrap(mysqli $mysqli, bool $restart = false, bool $advance = false): ?array
{
    if (!animespot_catalog_ready($mysqli)) return null;

    $options = animespot_options();
    $series  = animespot_series();
    $slot    = animespot_slot();

    // Cambiare epoca cambia il mazzo: la serie vecchia non è più di questo
    // mazzo e va rifatta tutta, o si finirebbe a giocare gli anni Dieci
    // avendo chiesto i classici.
    if ($series['era'] !== $options['era'] || !$series['tracce']) {
        $series = ['era' => $options['era'], 'tracce' => animespot_series_draw($mysqli, $options['era']), 'rounds' => []];
        $slot   = 1;
        animespot_slot_save($slot);
    }

    // Una serie tutta finita lascia il posto alla successiva, ma solo quando lo
    // si chiede. Rigenerarla da sola vorrebbe dire che ricaricare la pagina
    // sull'ultima risposta la fa sparire prima di averla letta.
    if ($advance && animespot_next_slot($series, $slot) === 0 && $series['tracce']) {
        $series = ['era' => $options['era'], 'tracce' => animespot_series_draw($mysqli, $options['era']), 'rounds' => []];
        $slot   = 1;
        animespot_slot_save($slot);
    }

    if ($restart) {
        unset($series['rounds'][$slot]);

        $track = animespot_pick($mysqli, $slot, $options['era'], array_merge(animespot_recent(), array_values($series['tracce'])));
        if ($track === null) $track = animespot_pick($mysqli, $slot, 0, animespot_recent());
        if ($track !== null) {
            $series['tracce'][$slot] = (int)$track['id'];
            animespot_remember((int)$track['id'], max(1, array_sum(animespot_counts($mysqli, $options['era']))));
        }
    }

    $trackId = (int)($series['tracce'][$slot] ?? 0);
    $track   = animespot_track($mysqli, $trackId);

    // Sigla sparita dal catalogo (succede dopo un reimport): se ne pesca
    // un'altra invece di lasciare una partita che non si può vincere.
    if ($track === null) {
        $track = animespot_pick($mysqli, $slot, $options['era'], animespot_recent());
        if ($track === null) $track = animespot_pick($mysqli, $slot, 0, animespot_recent());
        if ($track === null) return null;

        $series['tracce'][$slot] = (int)$track['id'];
        unset($series['rounds'][$slot]);
    }

    // La partita nasce quando ci si arriva, non quando si pesca la serie: così
    // prende i frammenti e il tipo di ascolto scelti in quel momento.
    if (!isset($series['rounds'][$slot])) {
        $series['rounds'][$slot] = animespot_new_round($track, $options['passi'], animespot_start_at($options));
    }

    animespot_series_save($series);

    return [
        'round'  => $series['rounds'][$slot],
        'track'  => $track,
        'slot'   => $slot,
        'series' => $series,
    ];
}

/** Scrive la partita di un posto e restituisce la serie aggiornata. */
function animespot_round_save(array $round, int $slot): array
{
    $series = animespot_series();
    $series['rounds'][$slot] = $round;
    animespot_series_save($series);

    return $series;
}

/**
 * Com'è messa la serie, posto per posto: è quello che colora la scala delle
 * difficoltà e dice quale si sta giocando.
 */
function animespot_series_state(array $series, int $slot, string $lang = 'it'): array
{
    $slots = [];

    foreach (ANIMESPOT_LEVELS as $level) {
        $round = $series['rounds'][$level] ?? null;

        $slots[] = [
            'slot'    => $level,
            'name'    => animespot_level_name($level, $lang),
            'status'  => $round === null ? 'nuova' : $round['status'],
            'attempt' => $round === null ? 0 : count($round['guesses']),
            'points'  => $round === null ? 0 : animespot_points($round),
            'current' => $level === $slot,
        ];
    }

    return $slots;
}

function animespot_already_guessed(array $round, int $animeId): bool
{
    foreach ($round['guesses'] as $guess) {
        if ((int)($guess['id'] ?? 0) === $animeId) return true;
    }

    return false;
}

/** Aggiunge un tentativo (o un salto, se $guess è null) e aggiorna l'esito. */
function animespot_apply_guess(array $round, ?array $guess, bool $correct): array
{
    if ($guess === null) {
        $round['guesses'][] = ['type' => 'skip', 'id' => null, 'nome' => null, 'cover' => null];
    } else {
        $entry = [
            'type'  => $correct ? 'correct' : 'wrong',
            'id'    => (int)$guess['id'],
            'nome'  => (string)$guess['nome'],
            'cover' => (string)($guess['cover_url'] ?? ''),
        ];

        $round['guesses'][] = $entry;
        if ($correct) $round['status'] = 'won';
    }

    if ($round['status'] === 'playing' && count($round['guesses']) >= count($round['passi'])) {
        $round['status'] = 'lost';
    }

    return $round;
}

/**
 * Quanti punti vale una partita vinta.
 *
 * Il valore lo decide il frammento a cui si è indovinato, non la posizione nel
 * proprio elenco: chi spegne i primi due frammenti comincia da due secondi e
 * prende i punti dei due secondi, non quelli del decimo di secondo.
 */
function animespot_points(array $round): int
{
    if ($round['status'] !== 'won') return 0;

    $index = count($round['guesses']) - 1;
    $step  = $round['passi'][$index] ?? (count(ANIMESPOT_STEPS) - 1);

    return ANIMESPOT_POINTS[$step] ?? 0;
}

/** Quanti secondi di traccia sono sbloccati adesso. A partita finita, tutta. */
function animespot_unlocked_seconds(array $round): ?float
{
    if ($round['status'] !== 'playing') return null;

    $ladder = animespot_ladder($round['passi']);

    return $ladder[min(count($round['guesses']), count($ladder) - 1)];
}

/* ── Storico e statistiche ──────────────────────────────────────────────── */

/**
 * Scrive la partita finita nello storico, una volta sola.
 *
 * Se la tabella non c'è, il gioco continua: si perdono solo le statistiche.
 */
function animespot_record(mysqli $mysqli, int $userId, array $round): array
{
    if ($round['status'] === 'playing' || !empty($round['recorded'])) return $round;

    $round['recorded'] = true;

    if (!animespot_state_ready($mysqli)) return $round;

    $stmt = $mysqli->prepare(
        'INSERT INTO `' . ANIMESPOT_TABLE_GAMES . '`'
        . ' (utente_id, traccia_id, difficolta, tentativi, esito, tentativi_usati, passi, punti)'
        . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) return $round;

    $outcome  = $round['status'] === 'won' ? 'vinto' : 'perso';
    $attempts = (string)json_encode($round['guesses'], JSON_UNESCAPED_UNICODE);
    $used     = count($round['guesses']);
    $ladder   = count($round['passi']);
    $points   = animespot_points($round);

    $stmt->bind_param(
        'iiissiii',
        $userId, $round['traccia'], $round['difficolta'], $attempts, $outcome, $used, $ladder, $points
    );
    $stmt->execute();
    $stmt->close();

    return $round;
}

function animespot_empty_stats(): array
{
    return [
        'played'       => 0,
        'won'          => 0,
        'win_rate'     => 0,
        'streak'       => 0,
        'best_streak'  => 0,
        'points'       => 0,
        'best_points'  => 0,
        'distribution' => array_fill(0, count(ANIMESPOT_STEPS), 0),
        'by_level'     => array_fill_keys(ANIMESPOT_LEVELS, ['played' => 0, 'won' => 0]),
        'persisted'    => false,
    ];
}

/**
 * Partite giocate, vinte, distribuzione dei frammenti e serie di vittorie.
 *
 * La serie conta le vittorie di fila, non i giorni: si spezza solo perdendo.
 * Guardiamo le ultime cinquecento partite, che è già più storia di quanta ne
 * serva a chiunque.
 */
function animespot_stats(mysqli $mysqli, int $userId): array
{
    $stats = animespot_empty_stats();
    if (!animespot_state_ready($mysqli)) return $stats;

    $stats['persisted'] = true;

    $stmt = $mysqli->prepare(
        'SELECT esito, tentativi_usati, passi, punti, difficolta FROM `' . ANIMESPOT_TABLE_GAMES . '`'
        . ' WHERE utente_id = ? ORDER BY id DESC LIMIT 500'
    );
    if (!$stmt) return $stats;

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'won'      => $row['esito'] === 'vinto',
            'attempts' => (int)$row['tentativi_usati'],
            'ladder'   => max(1, (int)$row['passi']),
            'points'   => (int)$row['punti'],
            'level'    => (int)$row['difficolta'],
        ];
    }
    $stmt->close();

    // La query torna dalla più recente: la serie in corso si legge da lì.
    foreach ($rows as $row) {
        if (!$row['won']) break;
        $stats['streak']++;
    }

    $steps   = count(ANIMESPOT_STEPS);
    $running = 0;

    foreach (array_reverse($rows) as $row) {
        $stats['played']++;
        $stats['points'] += $row['points'];
        $stats['best_points'] = max($stats['best_points'], $row['points']);

        if (isset($stats['by_level'][$row['level']])) {
            $stats['by_level'][$row['level']]['played']++;
        }

        if (!$row['won']) {
            $running = 0;
            continue;
        }

        $stats['won']++;
        $running++;
        $stats['best_streak'] = max($stats['best_streak'], $running);

        if (isset($stats['by_level'][$row['level']])) {
            $stats['by_level'][$row['level']]['won']++;
        }

        // La distribuzione parla di frammenti, non di tentativi: con una scala
        // accorciata il terzo tentativo non è il terzo frammento, e i
        // frammenti spenti sono sempre i primi.
        $index = max(0, min($steps - 1, $row['attempts'] - 1 + ($steps - $row['ladder'])));
        $stats['distribution'][$index]++;
    }

    if ($stats['played'] > 0) {
        $stats['win_rate'] = (int)round($stats['won'] * 100 / $stats['played']);
    }

    return $stats;
}

/* ── Che cosa vede il client ────────────────────────────────────────────── */

/** La scheda della sigla, da mostrare solo a partita finita. */
function animespot_reveal(array $track): array
{
    $video = (string)$track['video'];
    $slug  = (string)($track['anime_slug'] ?? '');

    return [
        'anime_id'  => (int)$track['anime_id'],
        'anime'     => (string)$track['anime'],
        'anno'      => $track['anno'] !== null ? (int)$track['anno'] : null,
        'stagione'  => (string)$track['stagione'],
        'formato'   => (string)$track['formato'],
        'sigla'     => (string)$track['slug'],
        'tipo'      => (string)$track['tipo'],
        'canzone'   => (string)$track['canzone'],
        'artisti'   => (string)$track['artisti'],
        'cover'     => (string)$track['cover_url'],
        // A risposta svelata il nome del file non nasconde più niente: la
        // traccia intera e il video possono arrivare dal loro sito, che li
        // serve meglio di quanto faremmo noi.
        'audio_url' => ANIMESPOT_AUDIO_HOST . rawurlencode((string)$track['audio']),
        'video_url' => $video !== '' ? ANIMESPOT_VIDEO_HOST . rawurlencode($video) : null,
        'link'      => $slug !== '' ? 'https://animethemes.moe/anime/' . rawurlencode($slug) : null,
        'mal'       => $track['mal_id'] !== null ? 'https://myanimelist.net/anime/' . (int)$track['mal_id'] : null,
    ];
}

/** Il payload che vede il client: la risposta compare solo a partita finita. */
function animespot_public_round(array $round, array $track, string $lang = 'it'): array
{
    $finished = $round['status'] !== 'playing';
    $ladder   = animespot_ladder($round['passi']);

    return [
        'status'          => $round['status'],
        'difficulty'      => (int)$round['difficolta'],
        'difficulty_name' => animespot_level_name((int)$round['difficolta'], $lang),
        // Da che secondo della sigla comincia il frammento: alla pagina serve
        // solo per dirlo, il taglio l'ha già fatto il server.
        'start_at'        => round((float)($round['avvio'] ?? 0), 1),
        'attempt'         => count($round['guesses']),
        'max_attempts'    => count($ladder),
        'steps'           => array_map('floatval', $ladder),
        'step_index'      => array_values($round['passi']),
        'unlocked'        => animespot_unlocked_seconds($round),
        'full'            => $finished,
        'guesses'         => $round['guesses'],
        'points'          => animespot_points($round),
        'answer'          => $finished ? animespot_reveal($track) : null,
    ];
}

/**
 * Tutto quello che serve alla pagina in una risposta sola.
 *
 * Le tre chiamate che cambiano qualcosa — stato, tentativo, opzioni —
 * rispondono con la stessa identica forma. Il client non deve mai mettere
 * insieme due risposte diverse per sapere dov'è: è la fine di una categoria di
 * disallineamenti fra quello che si vede e quello che il server ha in mente.
 */
function animespot_full_payload(
    mysqli $mysqli,
    int $userId,
    string $lang,
    array $round,
    array $track,
    int $slot,
    array $series
): array {
    $options = animespot_options();

    $payload = animespot_public_round($round, $track, $lang);

    $payload['ok']        = true;
    $payload['slot']      = $slot;
    $payload['slots']     = animespot_series_state($series, $slot, $lang);
    $payload['next_slot'] = animespot_next_slot($series, $slot);
    $payload['options']   = $options;
    $payload['pool']      = animespot_counts($mysqli, $options['era']);
    $payload['eras']      = animespot_era_counts($mysqli);
    $payload['stats']     = animespot_stats($mysqli, $userId);

    return $payload;
}

/* ── L'audio ────────────────────────────────────────────────────────────── */

/** Il nome del file è quello del catalogo: niente barre, niente risalite. */
function animespot_audio_name(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '' || strlen($raw) > 200) return null;
    if (!preg_match('/^[A-Za-z0-9._!()\'\[\]&+,~-]+$/', $raw)) return null;
    if (str_contains($raw, '..')) return null;

    return $raw;
}

function animespot_cache_path(string $basename): string
{
    return ANIMESPOT_CACHE_DIR . '/' . sha1($basename) . '.ogg';
}

/**
 * Tiene la cartella della cache sotto il quarto di giga buttando i file
 * toccati da più tempo. Si guarda solo ogni tanto: contare i file a ogni
 * frammento costerebbe più di quanto costi tenerli.
 */
function animespot_cache_prune(): void
{
    if (random_int(1, 60) !== 1) return;
    if (!is_dir(ANIMESPOT_CACHE_DIR)) return;

    $files = glob(ANIMESPOT_CACHE_DIR . '/*.ogg') ?: [];
    $total = 0;
    $rows  = [];

    foreach ($files as $file) {
        $size   = (int)@filesize($file);
        $total += $size;
        $rows[] = ['file' => $file, 'size' => $size, 'time' => (int)@filemtime($file)];
    }

    if ($total <= ANIMESPOT_CACHE_MAX) return;

    usort($rows, static fn(array $a, array $b): int => $a['time'] <=> $b['time']);

    foreach ($rows as $row) {
        if ($total <= ANIMESPOT_CACHE_MAX * 0.8) break;
        if (@unlink($row['file'])) $total -= $row['size'];
    }
}

/**
 * L'inizio di un file audio, preso da AnimeThemes e tenuto da parte.
 *
 * Si scarica solo quello che serve: per quindici secondi bastano poche
 * centinaia di kilobyte su un file da un mega e mezzo. La cache cresce a
 * pezzi, quindi la seconda richiesta della stessa sigla di solito non tocca
 * più la rete.
 */
function animespot_audio_prefix(string $basename, int $want, int $fullSize = 0): ?string
{
    $name = animespot_audio_name($basename);
    if ($name === null) return null;

    if (!is_dir(ANIMESPOT_CACHE_DIR) && !@mkdir(ANIMESPOT_CACHE_DIR, 0775, true) && !is_dir(ANIMESPOT_CACHE_DIR)) {
        return animespot_audio_download($name, 0, $want);
    }

    $path = animespot_cache_path($name);
    clearstatcache(true, $path);
    $have = is_file($path) ? (int)filesize($path) : 0;

    if ($have >= $want || ($fullSize > 0 && $have >= $fullSize)) {
        @touch($path);

        return (string)file_get_contents($path);
    }

    $chunk = animespot_audio_download($name, $have, $want - $have);
    if ($chunk === null) return $have > 0 ? (string)file_get_contents($path) : null;

    $handle = @fopen($path, $have > 0 ? 'ab' : 'wb');
    if ($handle === false) {
        return $have > 0 ? (string)file_get_contents($path) . $chunk : $chunk;
    }

    @flock($handle, LOCK_EX);
    // Fra il conteggio e la scrittura un'altra richiesta può aver già
    // allungato il file: riscrivere in coda a quel punto lo corromperebbe.
    clearstatcache(true, $path);
    if ((int)@filesize($path) === $have) fwrite($handle, $chunk);
    @flock($handle, LOCK_UN);
    fclose($handle);

    animespot_cache_prune();
    clearstatcache(true, $path);

    return (string)file_get_contents($path);
}

/** Scarica un pezzo di file, con Range se non lo si vuole tutto. */
function animespot_audio_download(string $basename, int $from, int $length): ?string
{
    $url  = ANIMESPOT_AUDIO_HOST . rawurlencode($basename);
    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_USERAGENT      => 'Cripsum-Animespot/1.0 (+https://cripsum.com)',
        CURLOPT_RANGE          => $from . '-' . ($from + max(1, $length) - 1),
    ]);

    $body   = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if (!is_string($body) || $body === '') return null;

    // 200 vuol dire che il Range è stato ignorato e il corpo è tutto il file:
    // se avevamo già un pezzo, quello che serve è solo la parte nuova.
    if ($status === 200 && $from > 0) return substr($body, $from) ?: null;

    return in_array($status, [200, 206], true) ? $body : null;
}

/* ── Taglio dei file Ogg ────────────────────────────────────────────────── */

/**
 * Il CRC delle pagine Ogg: polinomio 0x04c11db7, senza riflessioni e senza xor
 * finale. Ricalcolarlo serve perché il taglio riscrive due campi dell'ultima
 * pagina, e una pagina con il CRC sbagliato viene buttata dal decoder senza un
 * fiato.
 */
function animespot_ogg_crc(string $page): int
{
    static $table = null;

    if ($table === null) {
        $table = [];
        for ($i = 0; $i < 256; $i++) {
            $crc = $i << 24;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x80000000)
                    ? ((($crc << 1) & 0xFFFFFFFF) ^ 0x04c11db7)
                    : (($crc << 1) & 0xFFFFFFFF);
            }
            $table[$i] = $crc;
        }
    }

    $crc    = 0;
    $length = strlen($page);

    for ($i = 0; $i < $length; $i++) {
        // I quattro byte del CRC contano come zero nel proprio calcolo.
        $byte = ($i >= 22 && $i <= 25) ? 0 : ord($page[$i]);
        $crc  = ((($crc << 8) & 0xFFFFFFFF) ^ $table[(($crc >> 24) & 0xFF) ^ $byte]);
    }

    return $crc;
}

/** Lunghezza, bandierine e posizione granulare della pagina che sta a $offset. */
function animespot_ogg_page(string $bytes, int $offset): ?array
{
    if ($offset < 0 || $offset + 27 > strlen($bytes)) return null;
    if (substr($bytes, $offset, 4) !== 'OggS') return null;

    $segments = ord($bytes[$offset + 26]);
    $header   = 27 + $segments;
    if ($offset + $header > strlen($bytes)) return null;

    $payload = 0;
    for ($i = 0; $i < $segments; $i++) {
        $payload += ord($bytes[$offset + 27 + $i]);
    }

    $length = $header + $payload;
    if ($offset + $length > strlen($bytes)) return null;

    // La posizione granulare è a 64 bit: su PHP a 64 bit ci sta, e -1 (tutti
    // uno) vuol dire "in questa pagina non finisce nessun pacchetto".
    $granule = unpack('P', substr($bytes, $offset + 6, 8))[1] ?? 0;

    return [
        'length'   => $length,
        'segments' => $segments,
        'flags'    => ord($bytes[$offset + 5]),
        'granule'  => $granule,
    ];
}

/** Riscrive i campi di una pagina Ogg e le rifà il CRC. */
function animespot_ogg_rewrite(string $page, ?int $granule, ?int $sequence, bool $eos = false): string
{
    if ($eos) $page[5] = chr(ord($page[5]) | 0x04);
    if ($granule !== null)  $page = substr_replace($page, pack('P', $granule), 6, 8);
    if ($sequence !== null) $page = substr_replace($page, pack('V', $sequence), 18, 4);

    return substr_replace($page, pack('V', animespot_ogg_crc($page)), 22, 4);
}

/**
 * I pacchetti che finiscono in una pagina, con quanto pesano.
 *
 * La tabella dei segmenti dice quanto è lungo ogni pezzo: un pacchetto continua
 * finché i segmenti valgono 255 e finisce al primo che vale meno. La durata di
 * ciascuno si ricava dividendo il tempo coperto dalla pagina per quanti
 * pacchetti ci sono finiti dentro — in un file prodotto da un encoder solo
 * durano tutti uguale, e questo basta per capire dov'è il silenzio.
 */
function animespot_ogg_packets(string $bytes, int $offset, int $segments): array
{
    $sizes = [];
    $size  = 0;

    for ($i = 0; $i < $segments; $i++) {
        $value = ord($bytes[$offset + 27 + $i]);
        $size += $value;

        if ($value < 255) {
            $sizes[] = $size;
            $size = 0;
        }
    }

    return $sizes;
}

/**
 * Da che campione comincia davvero la musica.
 *
 * Parecchie sigle hanno uno o due secondi di silenzio digitale prima
 * dell'attacco: a chi gioca arriva un frammento da un decimo di secondo in cui
 * non si sente niente, e non è una difficoltà, è un difetto.
 *
 * Il silenzio si riconosce da quanto pesa. Opus è a bitrate variabile e il
 * silenzio non costa quasi niente: dove la musica viaggia a quaranta kilobyte
 * al secondo, il silenzio ne occupa uno. Non serve decodificare niente, basta
 * guardare quanti byte occupa ogni pacchetto — quaranta volte meno non è una
 * sfumatura, è un altro contenuto.
 *
 * Si taglia al massimo sei secondi: oltre non è più un attacco muto, è un
 * pezzo che comincia piano, e quello va lasciato com'è.
 */
function animespot_ogg_audio_start(string $bytes, int $preSkip): int
{
    $limit  = $preSkip + 12 * 48000;
    $offset = 0;
    $prev   = 0;
    $packets = [];

    while (($page = animespot_ogg_page($bytes, $offset)) !== null) {
        $granule = $page['granule'];
        $offset += $page['length'];

        if ($granule <= 0) continue;
        if ($granule <= $prev) { $prev = $granule; continue; }

        $sizes = animespot_ogg_packets($bytes, $offset - $page['length'], $page['segments']);
        $count = count($sizes);

        if ($count > 0) {
            $span = intdiv($granule - $prev, $count);

            foreach ($sizes as $index => $size) {
                $at = $prev + $index * $span;
                $packets[] = ['at' => $at, 'rate' => $span > 0 ? $size * 48000 / $span : 0];
            }
        }

        $prev = $granule;
        if ($granule >= $limit) break;
    }

    if (count($packets) < 4) return $preSkip;

    // Il riferimento è il pacchetto più pesante dei primi dieci secondi: è
    // sempre dentro a quello che si scarica comunque, quindi la soglia non
    // cambia da un frammento all'altro della stessa partita.
    $reference = 0;
    foreach ($packets as $packet) {
        if ($packet['at'] > $preSkip + 10 * 48000) break;
        $reference = max($reference, $packet['rate']);
    }

    if ($reference <= 0) return $preSkip;

    $threshold = $reference * 0.15;
    $ceiling   = $preSkip + 6 * 48000;
    $total     = count($packets);

    // Servono due pacchetti pieni di fila. Il primo pacchetto di un flusso
    // Opus è grosso anche quando è muto — si porta dietro l'avvio del decoder —
    // e da solo direbbe che la musica comincia subito quando invece dopo di lui
    // arriva un secondo di pacchetti da tre byte. Due di fila non capitano per
    // sbaglio.
    for ($i = 0; $i + 1 < $total; $i++) {
        if ($packets[$i]['rate'] >= $threshold && $packets[$i + 1]['rate'] >= $threshold) {
            return min(max($packets[$i]['at'], $preSkip), $ceiling);
        }
    }

    return $preSkip;
}

/**
 * Un pezzo di Ogg Opus lungo `$seconds`, che comincia dove deve cominciare.
 *
 * In Opus la posizione granulare conta sempre campioni a 48 kHz e comprende il
 * pre-skip dichiarato nell'intestazione. Il taglio in coda è quello previsto
 * dal formato: sull'ultima pagina si riscrive la posizione al campione esatto
 * — così il frammento è netto invece che arrotondato alla pagina — e si alza
 * il bit di fine flusso, perché il browser sappia che non deve aspettare altro.
 *
 * Il taglio in testa è più interessante. Le pagine si possono buttare solo
 * intere, ma il punto da cui si vuole partire cade quasi sempre in mezzo a una:
 * allora si tiene quella pagina e si riscrive il pre-skip dell'intestazione,
 * che è esattamente il campo con cui il formato dice "di questi campioni, i
 * primi tot buttali". Ne esce un taglio al millisecondo invece che al secondo,
 * e in più il decoder si scalda sui campioni buttati, quindi non c'è il
 * crepitio che si sente saltando in mezzo a un flusso.
 *
 * Le posizioni granulari delle pagine tenute vanno traslate, o il lettore
 * mostrerebbe una traccia che parte al trentesimo secondo; e i numeri di
 * pagina vanno rifatti in fila, perché un salto nella numerazione è un buco, e
 * un buco fa scartare l'audio.
 *
 * Restituisce null se il file non è un Ogg Opus o se quello che abbiamo in
 * mano non arriva fin dove serve: in quel caso chi chiama scarica di più.
 */
function animespot_ogg_clip(string $bytes, float $seconds, float $from = 0.0): ?string
{
    if (strncmp($bytes, 'OggS', 4) !== 0) return null;

    $first = animespot_ogg_page($bytes, 0);
    if ($first === null) return null;

    $headStart = 27 + $first['segments'];
    if (strncmp(substr($bytes, $headStart, 8), 'OpusHead', 8) !== 0) return null;

    $preSkip = unpack('v', substr($bytes, $headStart + 10, 2))[1] ?? 0;

    // Le pagine di intestazione (OpusHead e OpusTags) hanno posizione zero e
    // vanno tenute sempre: sono la ricetta con cui si decodifica il resto.
    $offset  = 0;
    $headers = [];

    while (($page = animespot_ogg_page($bytes, $offset)) !== null && $page['granule'] === 0) {
        $headers[] = substr($bytes, $offset, $page['length']);
        $offset += $page['length'];
    }

    if (!$headers) return null;

    // Dove si vuole cominciare: a metà sigla se lo chiede il giocatore, se no
    // dove finisce l'eventuale silenzio iniziale.
    $target = $from > 0
        ? $preSkip + (int)round($from * 48000)
        : animespot_ogg_audio_start($bytes, $preSkip);

    // La prima pagina che arriva oltre quel punto: è quella da cui si parte, e
    // il pre-skip nuovo butta via il pezzo di troppo che si porta dietro.
    $audio = $offset;
    $shift = 0;
    $found = false;

    while (($page = animespot_ogg_page($bytes, $audio)) !== null) {
        if ($page['granule'] > $target && ($page['flags'] & 0x01) === 0) { $found = true; break; }
        if ($page['granule'] >= 0) $shift = $page['granule'];
        $audio += $page['length'];
    }

    if (!$found) return null;

    $discard = $target - $shift;

    // Il pre-skip sta in due byte: se il pezzo da buttare non ci sta (pagine
    // lunghissime, o un salto capitato male) si torna al confine di pagina,
    // che è meno preciso ma sempre corretto.
    if ($discard < 0 || $discard > 65535) {
        $discard = $preSkip;
        $shift   = 0;
        $audio   = $offset;
    }

    $headers[0] = animespot_ogg_rewrite(
        substr_replace($headers[0], pack('v', $discard), $headStart + 10, 2),
        null,
        null
    );

    $end   = $discard + (int)round($seconds * 48000);
    $body  = '';
    $pages = count($headers);
    $done  = false;

    while (($page = animespot_ogg_page($bytes, $audio)) !== null) {
        $raw     = substr($bytes, $audio, $page['length']);
        $granule = $page['granule'];

        // -1 su 64 bit: pagina senza fine di pacchetto, non dice niente sul
        // tempo trascorso e va tenuta senza guardarla.
        $moved = $granule >= 0 ? $granule - $shift : $granule;

        if ($granule >= 0 && $moved >= $end) {
            $body .= animespot_ogg_rewrite($raw, $end, $pages, true);
            $done  = true;
            break;
        }

        $body .= animespot_ogg_rewrite($raw, $granule >= 0 ? $moved : null, $pages);
        $audio += $page['length'];
        $pages++;
    }

    return $done ? implode('', $headers) . $body : null;
}

/**
 * Chiude la risposta lasciando il processo libero di lavorare ancora.
 *
 * Restituisce false dove non si può fare davvero. È una distinzione che conta:
 * senza una chiusura vera, tutto quello che si fa dopo lo aspetta comunque il
 * browser, e un precaricamento "in background" diventerebbe esattamente
 * l'attesa che si voleva togliere. Meglio non farlo.
 */
function animespot_finish_request(): bool
{
    ignore_user_abort(true);
    while (ob_get_level() > 0) @ob_end_flush();
    @flush();

    if (function_exists('litespeed_finish_request')) { litespeed_finish_request(); return true; }
    if (function_exists('fastcgi_finish_request'))    { fastcgi_finish_request();    return true; }

    return false;
}

/**
 * Scarica il resto della sigla dopo aver risposto.
 *
 * Il primo frammento chiede pochi byte perché deve arrivare subito; gli altri
 * quattro ne vogliono via via di più, e chiederli alla rete uno alla volta
 * mentre il giocatore aspetta è esattamente il tempo morto che si vuole
 * togliere. Qui la richiesta è già chiusa: quando arriva il primo errore, i
 * quindici secondi sono già sul disco.
 */
function animespot_cache_warm(array $track, float $from = 0.0): void
{
    $basename = animespot_audio_name((string)$track['audio']);
    if ($basename === null) return;

    $fullSize = (int)($track['audio_bytes'] ?? 0);
    $longest  = ANIMESPOT_STEPS[count(ANIMESPOT_STEPS) - 1];

    // 48 KB/s, non 32: qui si può essere larghi e conviene esserlo. Se la stima
    // resta corta il frammento più lungo torna a chiedere alla rete proprio
    // all'ultimo tentativo, che è il momento peggiore per farlo aspettare.
    $want = 65536 + (int)ceil(($from + $longest + 2) * 49152);

    if ($fullSize > 0) $want = min($want, $fullSize);

    $path = animespot_cache_path($basename);
    clearstatcache(true, $path);
    if (is_file($path) && filesize($path) >= $want) return;

    animespot_audio_prefix($basename, $want, $fullSize);
}

/**
 * Il frammento da mandare al browser, scaricando solo quanto serve.
 *
 * Non si sa quanto pesi un secondo di una certa sigla finché non la si è
 * letta, quindi si parte da una stima larga e si raddoppia finché la pagina
 * con il traguardo non è arrivata. In pratica il primo tentativo basta quasi
 * sempre, e il secondo chiude anche i file più generosi.
 */
function animespot_clip(array $track, ?float $seconds, float $from = 0.0): ?array
{
    $basename = animespot_audio_name((string)$track['audio']);
    if ($basename === null) return null;

    $fullSize = (int)($track['audio_bytes'] ?? 0);

    if ($seconds === null) {
        $bytes = animespot_audio_prefix($basename, $fullSize > 0 ? $fullSize : 8388608, $fullSize);

        return $bytes === null ? null : ['bytes' => $bytes, 'exact' => true];
    }

    // 32 KB/s è una stima prudente per l'Opus di AnimeThemes, più 64 KB di
    // intestazioni e commenti (che contengono la copertina e non sono pochi).
    // Il pavimento di 256 KB copre i primi quattro frammenti in una volta
    // sola: sono cinque richieste per partita, e farne cinque alla rete per
    // risparmiare due etti di banda vorrebbe dire un'attesa a ogni errore.
    $want = max(262144, 65536 + (int)ceil(($from + $seconds) * 32768));

    for ($round = 0; $round < 4; $round++) {
        $bytes = animespot_audio_prefix($basename, $want, $fullSize);
        if ($bytes === null) return null;

        $clip = animespot_ogg_clip($bytes, $seconds, $from);
        if ($clip !== null) return ['bytes' => $clip, 'exact' => true];

        $whole = ($fullSize > 0 && strlen($bytes) >= $fullSize) || strlen($bytes) < $want;

        // Abbiamo il file intero e il punto di partenza non ci sta dentro: la
        // sigla è più corta di quanto si sperava. Si riparte dall'inizio, che
        // è sempre meglio di un silenzio.
        if ($whole) {
            if ($from > 0) return animespot_clip($track, $seconds, 0.0);
            break;
        }

        $want *= 2;
    }

    // Taglio impossibile (formato inatteso, file più corto del frammento): si
    // manda quello che c'è e a fermarsi al punto giusto pensa il client.
    $bytes = animespot_audio_prefix($basename, $want, $fullSize);

    return $bytes === null ? null : ['bytes' => $bytes, 'exact' => false];
}
