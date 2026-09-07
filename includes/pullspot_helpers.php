<?php

/**
 * Cripsum™ — Pullspot
 *
 * Il gioco è un Songspot con le musiche delle animazioni di pull: si sente
 * un frammento sempre più lungo (0,1s → 0,5s → 2s → 5s → 8s → 15s) e si deve
 * indovinare di quale personaggio è. Sei tentativi, uno al giorno per tutti.
 *
 * Due regole guidano tutto quello che c'è qui dentro:
 *
 * 1. il client non deve mai poter sapere la risposta prima della fine. Il nome
 *    del file audio la rivelerebbe da solo, quindi l'audio passa da un proxy
 *    (api/pullspot/audio.php) che serve solo i secondi già sbloccati;
 * 2. la tabella dello stato può non esistere ancora (le migration di questo
 *    progetto si applicano a mano). In quel caso la partita vive in sessione:
 *    si gioca lo stesso, si perdono solo statistiche e streak.
 */

if (!defined('CRIPSUM_PULLSPOT_HELPERS')) {
    define('CRIPSUM_PULLSPOT_HELPERS', true);
}

require_once __DIR__ . '/gacha_helpers.php';
require_once __DIR__ . '/security_helpers.php';

/** Durate sbloccate, in secondi, tentativo per tentativo. */
const PULLSPOT_STEPS = [0.1, 0.5, 2.0, 5.0, 8.0, 15.0];

/** Giorno del Pullspot numero 1. */
const PULLSPOT_EPOCH = '2026-09-08';

/**
 * Se lo stesso file audio è condiviso da più personaggi di così è un segnaposto
 * (il suono di default), non la musica di qualcuno: fuori dal mazzo.
 */
const PULLSPOT_MAX_SHARED_AUDIO = 3;

const PULLSPOT_TABLE = 'pullspot_partite';

/** Estensioni che accettiamo come musica di pull. */
const PULLSPOT_AUDIO_EXT = ['mp3', 'ogg', 'wav', 'm4a', 'opus', 'webm'];

function pullspot_steps(): array
{
    return PULLSPOT_STEPS;
}

function pullspot_max_attempts(): int
{
    return count(PULLSPOT_STEPS);
}

/** URL di un asset con la data di modifica in coda, per non servire cache vecchia. */
function pullspot_asset(string $path): string
{
    $stamp = @filemtime(__DIR__ . '/..' . $path);

    return $path . '?v=' . ($stamp !== false ? $stamp : '1');
}

function pullspot_request_lang(): string
{
    $lang = strtolower((string)($_GET['lang'] ?? ''));
    if ($lang === 'it' || $lang === 'en') return $lang;

    return str_contains((string)($_SERVER['HTTP_REFERER'] ?? ''), '/en/') ? 'en' : 'it';
}

function pullspot_msg(string $key, string $lang = 'it'): string
{
    $messages = [
        'unauthenticated' => ['it' => 'Devi essere loggato per giocare.',                 'en' => 'You need to be logged in to play.'],
        'method'          => ['it' => 'Metodo non consentito.',                           'en' => 'Method not allowed.'],
        'csrf'            => ['it' => 'Sessione scaduta, ricarica la pagina.',            'en' => 'Session expired, reload the page.'],
        'no_pool'         => ['it' => 'Nessun personaggio ha ancora una musica di pull.', 'en' => 'No character has a pull track yet.'],
        'finished'        => ['it' => 'Questa partita è già finita.',                     'en' => 'This round is already over.'],
        'bad_guess'       => ['it' => 'Personaggio non valido.',                          'en' => 'Invalid character.'],
        'no_audio'        => ['it' => 'Traccia non disponibile.',                         'en' => 'Track unavailable.'],
    ];

    return $messages[$key][$lang] ?? $messages[$key]['it'] ?? $key;
}

/* ── Calendario ─────────────────────────────────────────────────────────── */

function pullspot_timezone(): DateTimeZone
{
    return new DateTimeZone('Europe/Rome');
}

function pullspot_today(): string
{
    return (new DateTimeImmutable('now', pullspot_timezone()))->format('Y-m-d');
}

/** Quanti giorni sono passati dal Pullspot #1. Prima dell'epoca non si gioca. */
function pullspot_day_index(string $day): int
{
    $epoch = new DateTimeImmutable(PULLSPOT_EPOCH . ' 00:00:00', pullspot_timezone());
    $date  = new DateTimeImmutable($day . ' 00:00:00', pullspot_timezone());

    return max(0, (int)$epoch->diff($date)->format('%r%a'));
}

/** Secondi che mancano alla mezzanotte italiana, cioè al prossimo puzzle. */
function pullspot_seconds_to_next(): int
{
    $now = new DateTimeImmutable('now', pullspot_timezone());

    return max(0, $now->modify('tomorrow midnight')->getTimestamp() - $now->getTimestamp());
}

/* ── Mazzo dei personaggi ───────────────────────────────────────────────── */

/**
 * Percorso su disco della musica di un personaggio, oppure null se non è
 * utilizzabile: media esterni, estensioni che non sono audio, file spariti e
 * qualunque cosa provi a uscire da /audio finiscono tutti qui.
 */
function pullspot_audio_path(?string $raw): ?string
{
    $raw = trim((string)$raw);
    if ($raw === '' || preg_match('~^https?://~i', $raw)) return null;

    $relative = ltrim(str_replace('\\', '/', $raw), '/');
    if ($relative === '' || str_contains($relative, '..')) return null;

    // Un valore assoluto tipo "/audio/x.mp3" nomina già la cartella: la
    // togliamo, perché la radice consentita è una sola.
    if (str_starts_with($raw, '/')) {
        if (!str_starts_with($relative, 'audio/')) return null;
        $relative = substr($relative, strlen('audio/'));
    }

    $extension = strtolower((string)pathinfo($relative, PATHINFO_EXTENSION));
    if (!in_array($extension, PULLSPOT_AUDIO_EXT, true)) return null;

    $root = realpath(__DIR__ . '/../audio');
    if ($root === false) return null;

    $full = realpath($root . '/' . $relative);
    if ($full === false || !is_file($full)) return null;
    if (!str_starts_with($full, rtrim($root, '\\/') . DIRECTORY_SEPARATOR)) return null;

    return $full;
}

function pullspot_schema_ready(mysqli $mysqli): bool
{
    if (!gacha_table_exists($mysqli, 'personaggi')) return false;

    $columns = gacha_character_columns($mysqli);

    return !empty($columns['name']) && !empty($columns['audio']);
}

/**
 * Tutti i personaggi che possono essere la risposta: hanno una musica, il file
 * esiste davvero e quella musica non è condivisa da mezzo roster.
 *
 * L'ordine è per id, così il mazzo è identico a ogni richiesta e la scelta del
 * giorno resta la stessa per tutti.
 */
function pullspot_pool(mysqli $mysqli): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    if (!pullspot_schema_ready($mysqli)) return $cache = [];

    $columns = gacha_character_columns($mysqli);

    $select  = '`id` AS id';
    $select .= ', ' . gacha_qcol($columns['name']) . ' AS nome';
    $select .= ', ' . gacha_qcol($columns['audio']) . ' AS audio_url';
    $select .= $columns['image']  ? ', ' . gacha_qcol($columns['image']) . ' AS img_url'    : ', NULL AS img_url';
    $select .= $columns['rarity'] ? ', ' . gacha_qcol($columns['rarity']) . ' AS rarita'    : ", '' AS rarita";
    $select .= $columns['video']  ? ', ' . gacha_qcol($columns['video']) . ' AS video_url'  : ', NULL AS video_url';

    $audioColumn = gacha_qcol($columns['audio']);
    $sql = 'SELECT ' . $select . ' FROM `personaggi`'
        . ' WHERE ' . $audioColumn . ' IS NOT NULL AND ' . $audioColumn . " <> ''"
        . ' ORDER BY `id` ASC';

    $result = $mysqli->query($sql);
    if (!$result) return $cache = [];

    $rows  = [];
    $usage = [];

    while ($row = $result->fetch_assoc()) {
        $path = pullspot_audio_path($row['audio_url'] ?? null);
        if ($path === null) continue;

        $name = trim((string)($row['nome'] ?? ''));
        if ($name === '') continue;

        $key = strtolower($path);
        $usage[$key] = ($usage[$key] ?? 0) + 1;

        $rows[] = [
            'id'        => (int)$row['id'],
            'nome'      => $name,
            'audio_key' => $key,
            'audio_url' => (string)$row['audio_url'],
            'img_url'   => $row['img_url'] ?? null,
            'rarita'    => (string)($row['rarita'] ?? ''),
            'video_url' => $row['video_url'] ?? null,
        ];
    }
    $result->free();

    $pool = [];
    foreach ($rows as $row) {
        if (($usage[$row['audio_key']] ?? 0) > PULLSPOT_MAX_SHARED_AUDIO) continue;
        $pool[] = $row;
    }

    return $cache = $pool;
}

/** Mescolata deterministica: stesso seme, stesso ordine, ovunque e per sempre. */
function pullspot_seeded_shuffle(array $values, string $seed): array
{
    $state = (int)hexdec(substr(hash('sha256', $seed), 0, 8));
    if ($state === 0) $state = 1;

    for ($i = count($values) - 1; $i > 0; $i--) {
        $state = ($state * 1103515245 + 12345) & 0x7FFFFFFF;
        $j = $state % ($i + 1);
        [$values[$i], $values[$j]] = [$values[$j], $values[$i]];
    }

    return $values;
}

/**
 * Il personaggio del giorno.
 *
 * Il mazzo viene mescolato una volta per "giro" e poi consumato una carta al
 * giorno: nessuno si ripete finché non sono usciti tutti gli altri.
 */
function pullspot_answer_for_day(mysqli $mysqli, int $dayIndex): ?array
{
    $pool = pullspot_pool($mysqli);
    $size = count($pool);
    if ($size === 0) return null;

    $order = pullspot_seeded_shuffle(range(0, $size - 1), 'pullspot|v1|' . intdiv($dayIndex, $size));

    return $pool[$order[$dayIndex % $size]];
}

function pullspot_character_by_id(mysqli $mysqli, int $id): ?array
{
    foreach (pullspot_pool($mysqli) as $character) {
        if ($character['id'] === $id) return $character;
    }

    return null;
}

/** Scheda del personaggio da mostrare a partita finita. */
function pullspot_reveal(array $character): array
{
    return [
        'id'        => $character['id'],
        'nome'      => $character['nome'],
        'rarita'    => $character['rarita'],
        'image_url' => gacha_media_url($character['img_url'] ?? null, '/img/'),
        'video_src' => gacha_media_url($character['video_url'] ?? null, '/vid/'),
    ];
}

/** L'elenco che riempie il campo di ricerca: sono anche le risposte possibili. */
function pullspot_character_list(mysqli $mysqli): array
{
    $list = [];
    foreach (pullspot_pool($mysqli) as $character) {
        $list[] = ['id' => $character['id'], 'nome' => $character['nome']];
    }

    usort($list, static fn(array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));

    return $list;
}

/* ── Partita ────────────────────────────────────────────────────────────── */

/** La tabella dello stato esiste? Se no si gioca lo stesso, ma solo in sessione. */
function pullspot_state_ready(mysqli $mysqli): bool
{
    return auth_table_exists($mysqli, PULLSPOT_TABLE);
}

function pullspot_new_game(string $day, int $characterId): array
{
    return [
        'day'     => $day,
        'char_id' => $characterId,
        'guesses' => [],
        'status'  => 'playing',
    ];
}

/** Ricostruisce una partita da come è stata salvata, scartando ciò che non torna. */
function pullspot_normalize_game(mixed $raw, string $day): ?array
{
    if (!is_array($raw)) return null;

    $characterId = (int)($raw['char_id'] ?? 0);
    if ($characterId <= 0) return null;

    $guesses = [];
    foreach ((array)($raw['guesses'] ?? []) as $guess) {
        if (!is_array($guess)) continue;
        $type = (string)($guess['type'] ?? '');
        if (!in_array($type, ['skip', 'wrong', 'correct'], true)) continue;

        $guesses[] = [
            'type' => $type,
            'id'   => isset($guess['id']) ? (int)$guess['id'] : null,
            'nome' => isset($guess['nome']) ? (string)$guess['nome'] : null,
        ];
        if (count($guesses) >= pullspot_max_attempts()) break;
    }

    $status = (string)($raw['status'] ?? 'playing');
    if (!in_array($status, ['playing', 'won', 'lost'], true)) $status = 'playing';

    return [
        'day'     => (string)($raw['day'] ?? $day),
        'char_id' => $characterId,
        'guesses' => $guesses,
        'status'  => $status,
    ];
}

function pullspot_session_slot(string $mode, string $day): string
{
    return $mode === 'practice' ? 'practice' : 'daily|' . $day;
}

function pullspot_session_load(string $slot, string $day): ?array
{
    return pullspot_normalize_game($_SESSION['pullspot'][$slot] ?? null, $day);
}

function pullspot_session_save(string $slot, array $game): void
{
    if (!isset($_SESSION['pullspot']) || !is_array($_SESSION['pullspot'])) {
        $_SESSION['pullspot'] = [];
    }

    // Le partite giornaliere vecchie non servono più: la sessione dura due
    // settimane e non deve diventare un archivio.
    foreach (array_keys($_SESSION['pullspot']) as $key) {
        if (str_starts_with((string)$key, 'daily|') && $key !== $slot) {
            unset($_SESSION['pullspot'][$key]);
        }
    }

    $_SESSION['pullspot'][$slot] = $game;
}

/**
 * Carica la partita del giorno, creandola se è la prima volta.
 *
 * Se il personaggio salvato non esiste più (rimosso dal roster, musica
 * sparita) la giornata riparte da capo: è meglio di una partita che non può
 * più essere vinta.
 */
function pullspot_load_game(mysqli $mysqli, int $userId, string $mode, string $day, array $fallbackCharacter): array
{
    $slot = pullspot_session_slot($mode, $day);
    $game = null;

    if ($mode === 'daily' && pullspot_state_ready($mysqli)) {
        $stmt = $mysqli->prepare(
            'SELECT personaggio_id, tentativi, esito FROM `' . PULLSPOT_TABLE . '`'
            . ' WHERE utente_id = ? AND giorno = ? LIMIT 1'
        );
        if ($stmt) {
            $stmt->bind_param('is', $userId, $day);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                $game = pullspot_normalize_game([
                    'day'     => $day,
                    'char_id' => (int)$row['personaggio_id'],
                    'guesses' => json_decode((string)$row['tentativi'], true),
                    'status'  => ['in_corso' => 'playing', 'vinto' => 'won', 'perso' => 'lost'][$row['esito']] ?? 'playing',
                ], $day);
            }
        }
    }

    if ($game === null) {
        $game = pullspot_session_load($slot, $day);
    }

    if ($game !== null && pullspot_character_by_id($mysqli, $game['char_id']) === null) {
        $game = null;
    }

    return $game ?? pullspot_new_game($day, $fallbackCharacter['id']);
}

function pullspot_save_game(mysqli $mysqli, int $userId, string $mode, array $game): void
{
    pullspot_session_save(pullspot_session_slot($mode, $game['day']), $game);

    if ($mode !== 'daily' || !pullspot_state_ready($mysqli)) return;

    $esito = ['playing' => 'in_corso', 'won' => 'vinto', 'lost' => 'perso'][$game['status']] ?? 'in_corso';
    $tentativi = json_encode($game['guesses'], JSON_UNESCAPED_UNICODE);
    $usati = count($game['guesses']);

    $stmt = $mysqli->prepare(
        'INSERT INTO `' . PULLSPOT_TABLE . '` (utente_id, giorno, personaggio_id, tentativi, esito, tentativi_usati)'
        . ' VALUES (?, ?, ?, ?, ?, ?)'
        . ' ON DUPLICATE KEY UPDATE personaggio_id = VALUES(personaggio_id), tentativi = VALUES(tentativi),'
        . ' esito = VALUES(esito), tentativi_usati = VALUES(tentativi_usati)'
    );
    if (!$stmt) return;

    $stmt->bind_param('isissi', $userId, $game['day'], $game['char_id'], $tentativi, $esito, $usati);
    $stmt->execute();
    $stmt->close();
}

/** Due personaggi che condividono la stessa traccia sono entrambi giusti. */
function pullspot_is_correct(array $guess, array $answer): bool
{
    return $guess['id'] === $answer['id'] || $guess['audio_key'] === $answer['audio_key'];
}

/** Aggiunge un tentativo (o un salto, se $guess è null) e aggiorna l'esito. */
function pullspot_apply_guess(array $game, ?array $guess, array $answer): array
{
    if ($guess === null) {
        $game['guesses'][] = ['type' => 'skip', 'id' => null, 'nome' => null];
    } elseif (pullspot_is_correct($guess, $answer)) {
        $game['guesses'][] = ['type' => 'correct', 'id' => $guess['id'], 'nome' => $guess['nome']];
        $game['status'] = 'won';
    } else {
        $game['guesses'][] = ['type' => 'wrong', 'id' => $guess['id'], 'nome' => $guess['nome']];
    }

    if ($game['status'] === 'playing' && count($game['guesses']) >= pullspot_max_attempts()) {
        $game['status'] = 'lost';
    }

    return $game;
}

/**
 * Quanti secondi di traccia sono sbloccati adesso.
 * A partita finita non c'è più niente da nascondere: null vuol dire "tutta".
 */
function pullspot_unlocked_seconds(array $game): ?float
{
    if ($game['status'] !== 'playing') return null;

    return PULLSPOT_STEPS[min(count($game['guesses']), count(PULLSPOT_STEPS) - 1)];
}

/** Il payload che vede il client: la risposta compare solo a partita finita. */
function pullspot_public_state(array $game, array $character, string $mode, ?int $dayIndex): array
{
    $finished = $game['status'] !== 'playing';

    return [
        'mode'         => $mode,
        'status'       => $game['status'],
        'day'          => $game['day'],
        'puzzle'       => $dayIndex === null ? null : $dayIndex + 1,
        'attempt'      => count($game['guesses']),
        'max_attempts' => pullspot_max_attempts(),
        'steps'        => array_map('floatval', PULLSPOT_STEPS),
        'unlocked'     => pullspot_unlocked_seconds($game),
        'full'         => $finished,
        'guesses'      => $game['guesses'],
        'answer'       => $finished ? pullspot_reveal($character) : null,
        'next_in'      => pullspot_seconds_to_next(),
    ];
}

/**
 * Prepara la partita richiesta: quella del giorno oppure una di allenamento.
 *
 * Restituisce null solo se non c'è nessun personaggio giocabile, cioè se
 * nessuno ha ancora una musica di pull utilizzabile.
 */
function pullspot_bootstrap(mysqli $mysqli, int $userId, string $mode, bool $restart = false): ?array
{
    $pool = pullspot_pool($mysqli);
    if (!$pool) return null;

    $day = pullspot_today();

    if ($mode === 'practice') {
        $game = $restart ? null : pullspot_session_load('practice', $day);
        if ($game !== null && pullspot_character_by_id($mysqli, $game['char_id']) === null) {
            $game = null;
        }
        if ($game === null) {
            $game = pullspot_new_game($day, $pool[random_int(0, count($pool) - 1)]['id']);
            pullspot_session_save('practice', $game);
        }

        return [
            'mode'      => 'practice',
            'day_index' => null,
            'game'      => $game,
            'character' => pullspot_character_by_id($mysqli, $game['char_id']),
        ];
    }

    $dayIndex = pullspot_day_index($day);
    $answer   = pullspot_answer_for_day($mysqli, $dayIndex);
    if ($answer === null) return null;

    $game = pullspot_load_game($mysqli, $userId, 'daily', $day, $answer);

    return [
        'mode'      => 'daily',
        'day_index' => $dayIndex,
        'game'      => $game,
        'character' => pullspot_character_by_id($mysqli, $game['char_id']) ?? $answer,
    ];
}

/* ── Statistiche ────────────────────────────────────────────────────────── */

function pullspot_empty_stats(): array
{
    return [
        'played'       => 0,
        'won'          => 0,
        'win_rate'     => 0,
        'streak'       => 0,
        'best_streak'  => 0,
        'distribution' => array_fill(0, count(PULLSPOT_STEPS), 0),
        'persisted'    => false,
    ];
}

/**
 * Partite giocate, vinte, distribuzione dei tentativi e serie di vittorie.
 *
 * La serie si spezza sia perdendo sia saltando un giorno; se oggi non è ancora
 * stato giocato la serie di ieri resta viva, come su Songspot.
 */
function pullspot_stats(mysqli $mysqli, int $userId): array
{
    $stats = pullspot_empty_stats();
    if (!pullspot_state_ready($mysqli)) return $stats;

    $stats['persisted'] = true;

    $stmt = $mysqli->prepare(
        'SELECT giorno, esito, tentativi_usati FROM `' . PULLSPOT_TABLE . '`'
        . " WHERE utente_id = ? AND esito <> 'in_corso' ORDER BY giorno ASC"
    );
    if (!$stmt) return $stats;

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $byDay = [];
    $running = 0;

    while ($row = $result->fetch_assoc()) {
        $won = $row['esito'] === 'vinto';
        $byDay[(string)$row['giorno']] = $won;

        $stats['played']++;
        if ($won) {
            $stats['won']++;
            $index = max(1, min(count(PULLSPOT_STEPS), (int)$row['tentativi_usati'])) - 1;
            $stats['distribution'][$index]++;
            $running++;
            $stats['best_streak'] = max($stats['best_streak'], $running);
        } else {
            $running = 0;
        }
    }
    $stmt->close();

    if ($stats['played'] > 0) {
        $stats['win_rate'] = (int)round($stats['won'] * 100 / $stats['played']);
    }

    $cursor = new DateTimeImmutable(pullspot_today(), pullspot_timezone());
    if (!isset($byDay[$cursor->format('Y-m-d')])) {
        $cursor = $cursor->modify('-1 day');
    }
    while (!empty($byDay[$cursor->format('Y-m-d')])) {
        $stats['streak']++;
        $cursor = $cursor->modify('-1 day');
    }

    return $stats;
}

/* ── Taglio della traccia ───────────────────────────────────────────────── */

/**
 * Legge l'header di un frame MPEG audio e ne ricava lunghezza e durata.
 *
 * Serve per tagliare il file esattamente ai secondi sbloccati: mandare al
 * browser la traccia intera e fidarsi che si fermi da solo vorrebbe dire
 * regalare la risposta a chiunque apra la scheda di rete.
 *
 * Restituisce null se a quell'offset non c'è un frame valido.
 */
function pullspot_mp3_frame(string $bytes, int $offset): ?array
{
    if ($offset + 4 > strlen($bytes)) return null;

    $b1 = ord($bytes[$offset + 1]);
    $b2 = ord($bytes[$offset + 2]);
    $b3 = ord($bytes[$offset + 3]);

    $version = ($b1 >> 3) & 0x03;   // 0 = MPEG 2.5, 1 = riservato, 2 = MPEG 2, 3 = MPEG 1
    $layerId = ($b1 >> 1) & 0x03;   // 1 = Layer III, 2 = Layer II, 3 = Layer I
    if ($version === 1 || $layerId === 0) return null;

    $bitrateIndex = ($b2 >> 4) & 0x0F;
    $rateIndex    = ($b2 >> 2) & 0x03;
    $padding      = ($b2 >> 1) & 0x01;
    if ($bitrateIndex === 0 || $bitrateIndex === 15 || $rateIndex === 3) return null;

    $mpeg1 = $version === 3;
    $layer = [3 => 1, 2 => 2, 1 => 3][$layerId];

    $bitrateTable = $mpeg1
        ? [
            1 => [0, 32, 64, 96, 128, 160, 192, 224, 256, 288, 320, 352, 384, 416, 448],
            2 => [0, 32, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 384],
            3 => [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320],
        ]
        : [
            1 => [0, 32, 48, 56, 64, 80, 96, 112, 128, 144, 160, 176, 192, 224, 256],
            2 => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
            3 => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
        ];

    $sampleRates = [
        3 => [44100, 48000, 32000],
        2 => [22050, 24000, 16000],
        0 => [11025, 12000, 8000],
    ];

    $bitrate    = $bitrateTable[$layer][$bitrateIndex] * 1000;
    $sampleRate = $sampleRates[$version][$rateIndex];
    if ($bitrate <= 0 || $sampleRate <= 0) return null;

    if ($layer === 1) {
        $samples = 384;
        $length  = ((int)floor(12 * $bitrate / $sampleRate) + $padding) * 4;
    } else {
        $shortFrame = ($layer === 3 && !$mpeg1);
        $samples = $shortFrame ? 576 : 1152;
        $length  = (int)floor(($shortFrame ? 72 : 144) * $bitrate / $sampleRate) + $padding;
    }

    if ($length < 8) return null;

    // Il primo frame di quasi ogni MP3 è un descrittore Xing/Info/VBRI: non
    // contiene musica, quindi non deve consumare i decimi di secondo concessi.
    $crc      = (($b1 & 0x01) === 0) ? 2 : 0;
    $mono     = ((($b3 >> 6) & 0x03) === 3);
    $sideInfo = $mpeg1 ? ($mono ? 17 : 32) : ($mono ? 9 : 17);
    $tagAt    = $offset + 4 + $crc + $sideInfo;

    $isTag = (substr($bytes, $tagAt, 4) === 'Xing' || substr($bytes, $tagAt, 4) === 'Info')
        || substr($bytes, $offset + 36, 4) === 'VBRI';

    return [
        'length'   => $length,
        'duration' => $samples / $sampleRate,
        'is_tag'   => $isTag,
    ];
}

/**
 * I primi $seconds secondi di un MP3, tagliati sul confine di un frame.
 * Restituisce null se il file non si lascia leggere: in quel caso il chiamante
 * serve la traccia intera e ci si affida al limite lato client.
 */
function pullspot_mp3_clip(string $bytes, float $seconds): ?string
{
    $total = strlen($bytes);
    $cursor = 0;

    // Un tag ID3v2 in testa non è audio e confonderebbe la ricerca del sync.
    if ($total > 10 && substr($bytes, 0, 3) === 'ID3') {
        $size = ((ord($bytes[6]) & 0x7F) << 21)
            | ((ord($bytes[7]) & 0x7F) << 14)
            | ((ord($bytes[8]) & 0x7F) << 7)
            | (ord($bytes[9]) & 0x7F);
        $cursor = 10 + $size;
        if (ord($bytes[5]) & 0x10) $cursor += 10;   // footer
    }

    $start   = null;
    $end     = null;
    $elapsed = 0.0;

    while ($cursor + 4 <= $total) {
        if (ord($bytes[$cursor]) !== 0xFF || (ord($bytes[$cursor + 1]) & 0xE0) !== 0xE0) {
            $cursor++;
            continue;
        }

        $frame = pullspot_mp3_frame($bytes, $cursor);
        if ($frame === null) {
            $cursor++;
            continue;
        }
        if ($cursor + $frame['length'] > $total) break;

        if ($frame['is_tag'] && $start === null) {
            $cursor += $frame['length'];
            continue;
        }

        if ($start === null) $start = $cursor;

        $elapsed += $frame['duration'];
        $cursor  += $frame['length'];
        $end      = $cursor;

        if ($elapsed >= $seconds - 1e-9) break;
    }

    if ($start === null || $end === null) return null;

    return substr($bytes, $start, $end - $start);
}

function pullspot_audio_mime(string $path): string
{
    return [
        'mp3'  => 'audio/mpeg',
        'ogg'  => 'audio/ogg',
        'opus' => 'audio/ogg',
        'wav'  => 'audio/wav',
        'm4a'  => 'audio/mp4',
        'webm' => 'audio/webm',
    ][strtolower((string)pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
}

/**
 * I byte da mandare al browser: i primi $seconds secondi, oppure la traccia
 * intera se $seconds è null (partita finita) o se il formato non è tagliabile.
 */
function pullspot_clip_bytes(string $path, ?float $seconds): ?string
{
    $bytes = @file_get_contents($path);
    if ($bytes === false) return null;

    if ($seconds === null) return $bytes;

    if (strtolower((string)pathinfo($path, PATHINFO_EXTENSION)) === 'mp3') {
        $clip = pullspot_mp3_clip($bytes, $seconds);
        if ($clip !== null) return $clip;
    }

    return $bytes;
}
