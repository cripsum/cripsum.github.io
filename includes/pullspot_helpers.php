<?php

/**
 * Cripsum™ — Pullspot
 *
 * Il gioco è un Songspot con le musiche delle animazioni di pull: si sente
 * un frammento sempre più lungo (0,1s → 0,5s → 2s → 5s → 8s → 15s) e si deve
 * indovinare di quale personaggio è. Sei tentativi, e si gioca quanto si vuole:
 * finita una traccia ne parte un'altra.
 *
 * Due regole guidano tutto quello che c'è qui dentro:
 *
 * 1. il client non deve mai poter sapere la risposta prima della fine. Il nome
 *    del file audio la rivelerebbe da solo, quindi l'audio passa da un proxy
 *    (api/pullspot/audio.php) che serve solo i secondi già sbloccati;
 * 2. la tabella dello storico può non esistere ancora (le migration di questo
 *    progetto si applicano a mano). In quel caso si gioca lo stesso: si perdono
 *    solo le statistiche.
 */

if (!defined('CRIPSUM_PULLSPOT_HELPERS')) {
    define('CRIPSUM_PULLSPOT_HELPERS', true);
}

require_once __DIR__ . '/gacha_helpers.php';
require_once __DIR__ . '/security_helpers.php';

/** Durate sbloccate, in secondi, tentativo per tentativo. */
const PULLSPOT_STEPS = [0.1, 0.5, 2.0, 5.0, 8.0, 15.0];

/**
 * Se lo stesso file audio è condiviso da più personaggi di così è un segnaposto
 * (il suono di default), non la musica di qualcuno: fuori dal mazzo.
 */
const PULLSPOT_MAX_SHARED_AUDIO = 3;

/** Quanti personaggi appena usciti evitare prima di poterli ripescare. */
const PULLSPOT_RECENT_MEMORY = 25;

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
    $select .= $columns['image']  ? ', ' . gacha_qcol($columns['image']) . ' AS img_url'   : ', NULL AS img_url';
    $select .= $columns['rarity'] ? ', ' . gacha_qcol($columns['rarity']) . ' AS rarita'   : ", '' AS rarita";
    $select .= $columns['video']  ? ', ' . gacha_qcol($columns['video']) . ' AS video_url' : ', NULL AS video_url';

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

/**
 * L'elenco che riempie il campo di ricerca: sono anche le risposte possibili.
 *
 * Ci va anche l'immagine. Non svela niente proprio perché c'è per tutti: se
 * comparisse solo per qualcuno, quel qualcuno sarebbe la risposta.
 */
function pullspot_character_list(mysqli $mysqli): array
{
    $list = [];
    foreach (pullspot_pool($mysqli) as $character) {
        $list[] = [
            'id'        => $character['id'],
            'nome'      => $character['nome'],
            'image_url' => gacha_media_url($character['img_url'] ?? null, '/img/'),
        ];
    }

    usort($list, static fn(array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));

    return $list;
}

/* ── Partita ────────────────────────────────────────────────────────────── */

/** La tabella dello storico esiste? Se no si gioca lo stesso, senza statistiche. */
function pullspot_state_ready(mysqli $mysqli): bool
{
    return auth_table_exists($mysqli, PULLSPOT_TABLE);
}

function pullspot_new_game(int $characterId): array
{
    return [
        'char_id'  => $characterId,
        'guesses'  => [],
        'status'   => 'playing',
        'recorded' => false,
    ];
}

/** Ricostruisce una partita da come è stata salvata, scartando ciò che non torna. */
function pullspot_normalize_game(mixed $raw): ?array
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
        'char_id'  => $characterId,
        'guesses'  => $guesses,
        'status'   => $status,
        'recorded' => !empty($raw['recorded']),
    ];
}

function pullspot_session_load(): ?array
{
    return pullspot_normalize_game($_SESSION['pullspot']['round'] ?? null);
}

function pullspot_session_save(array $game): void
{
    if (!isset($_SESSION['pullspot']) || !is_array($_SESSION['pullspot'])) {
        $_SESSION['pullspot'] = [];
    }

    $_SESSION['pullspot']['round'] = $game;
}

/** Gli ultimi personaggi usciti, per non riproporli subito. */
function pullspot_recent(): array
{
    $recent = $_SESSION['pullspot']['recent'] ?? [];

    return is_array($recent) ? array_map('intval', $recent) : [];
}

function pullspot_remember(int $characterId): void
{
    $recent = pullspot_recent();
    $recent[] = $characterId;

    if (!isset($_SESSION['pullspot']) || !is_array($_SESSION['pullspot'])) {
        $_SESSION['pullspot'] = [];
    }
    $_SESSION['pullspot']['recent'] = array_slice($recent, -PULLSPOT_RECENT_MEMORY);
}

/**
 * Pesca un personaggio a caso evitando quelli appena usciti — a meno che il
 * mazzo sia così piccolo da non lasciare scelta.
 */
function pullspot_pick(mysqli $mysqli): ?array
{
    $pool = pullspot_pool($mysqli);
    if (!$pool) return null;

    $recent = pullspot_recent();
    $fresh = array_values(array_filter(
        $pool,
        static fn(array $character): bool => !in_array($character['id'], $recent, true)
    ));

    $from = $fresh ?: $pool;

    return $from[random_int(0, count($from) - 1)];
}

/**
 * Prepara la partita in corso, creandone una nuova se non ce n'è o se è stata
 * chiesta esplicitamente.
 *
 * Restituisce null solo se non c'è nessun personaggio giocabile, cioè se
 * nessuno ha ancora una musica di pull utilizzabile.
 */
function pullspot_bootstrap(mysqli $mysqli, bool $restart = false): ?array
{
    if (!pullspot_pool($mysqli)) return null;

    $game = $restart ? null : pullspot_session_load();

    // Un personaggio tolto dal roster mentre ci si giocava lascia una partita
    // che non si può più vincere: meglio ricominciare.
    if ($game !== null && pullspot_character_by_id($mysqli, $game['char_id']) === null) {
        $game = null;
    }

    if ($game === null) {
        $character = pullspot_pick($mysqli);
        if ($character === null) return null;

        $game = pullspot_new_game($character['id']);
        pullspot_remember($character['id']);
        pullspot_session_save($game);
    }

    return [
        'game'      => $game,
        'character' => pullspot_character_by_id($mysqli, $game['char_id']),
    ];
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
 * Scrive la partita finita nello storico, una volta sola.
 *
 * Se la tabella non c'è, il gioco continua: si perdono solo le statistiche.
 */
function pullspot_record_result(mysqli $mysqli, int $userId, array $game): array
{
    if ($game['status'] === 'playing' || !empty($game['recorded'])) return $game;

    $game['recorded'] = true;

    if (!pullspot_state_ready($mysqli)) return $game;

    $esito = $game['status'] === 'won' ? 'vinto' : 'perso';
    $tentativi = json_encode($game['guesses'], JSON_UNESCAPED_UNICODE);
    $usati = count($game['guesses']);

    $stmt = $mysqli->prepare(
        'INSERT INTO `' . PULLSPOT_TABLE . '` (utente_id, personaggio_id, tentativi, esito, tentativi_usati)'
        . ' VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) return $game;

    $stmt->bind_param('iissi', $userId, $game['char_id'], $tentativi, $esito, $usati);
    $stmt->execute();
    $stmt->close();

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
function pullspot_public_state(array $game, array $character): array
{
    $finished = $game['status'] !== 'playing';

    return [
        'status'       => $game['status'],
        'attempt'      => count($game['guesses']),
        'max_attempts' => pullspot_max_attempts(),
        'steps'        => array_map('floatval', PULLSPOT_STEPS),
        'unlocked'     => pullspot_unlocked_seconds($game),
        'full'         => $finished,
        'guesses'      => $game['guesses'],
        'answer'       => $finished ? pullspot_reveal($character) : null,
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
 * Senza più un calendario la serie conta le vittorie di fila, non i giorni:
 * si spezza solo perdendo. Guardiamo le ultime cinquecento partite, che è già
 * più storia di quanta ne serva a chiunque.
 */
function pullspot_stats(mysqli $mysqli, int $userId): array
{
    $stats = pullspot_empty_stats();
    if (!pullspot_state_ready($mysqli)) return $stats;

    $stats['persisted'] = true;

    $stmt = $mysqli->prepare(
        'SELECT esito, tentativi_usati FROM `' . PULLSPOT_TABLE . '`'
        . ' WHERE utente_id = ? ORDER BY id DESC LIMIT 500'
    );
    if (!$stmt) return $stats;

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = ['won' => $row['esito'] === 'vinto', 'attempts' => (int)$row['tentativi_usati']];
    }
    $stmt->close();

    // La query torna dalla più recente: la serie in corso si legge da lì.
    foreach ($rows as $row) {
        if (!$row['won']) break;
        $stats['streak']++;
    }

    $running = 0;
    foreach (array_reverse($rows) as $row) {
        $stats['played']++;

        if (!$row['won']) {
            $running = 0;
            continue;
        }

        $stats['won']++;
        $index = max(1, min(count(PULLSPOT_STEPS), $row['attempts'])) - 1;
        $stats['distribution'][$index]++;
        $running++;
        $stats['best_streak'] = max($stats['best_streak'], $running);
    }

    if ($stats['played'] > 0) {
        $stats['win_rate'] = (int)round($stats['won'] * 100 / $stats['played']);
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
