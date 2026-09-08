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
 * (il suono di default), non la musica di qualcuno: fuori dal mazzo. La soglia
 * è larga apposta — un gruppo di personaggi che condivide un tema deve restare
 * giocabile, a sparire è solo il suono buono per tutti.
 */
const PULLSPOT_MAX_SHARED_AUDIO = 6;

/**
 * Quanti personaggi appena usciti evitare prima di poterli ripescare. Non può
 * mai superare metà del mazzo: se lo superasse, la lista dei recenti coprirebbe
 * quasi tutti e la scelta ripiegherebbe ogni volta sul mazzo intero, che è
 * proprio quello che si voleva evitare.
 */
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
        'repeat'          => ['it' => 'Questo personaggio lo hai già provato.',            'en' => 'You have already tried that character.'],
        'no_audio'        => ['it' => 'Traccia non disponibile.',                         'en' => 'Track unavailable.'],
    ];

    return $messages[$key][$lang] ?? $messages[$key]['it'] ?? $key;
}

/* ── Mazzo dei personaggi ───────────────────────────────────────────────── */

/**
 * Cerca un file dentro una cartella, prima com'e' scritto e poi ignorando le
 * maiuscole.
 *
 * In produzione il disco distingue "Hitori.mp3" da "hitori.mp3", il database
 * no: senza questo secondo tentativo un personaggio scritto con una maiuscola
 * di troppo sparisce dal gioco e non lo si capisce guardando il database.
 */
function pullspot_find_file(string $root, string $relative): ?string
{
    $direct = realpath($root . '/' . $relative);
    if ($direct !== false && is_file($direct)) return $direct;

    static $index = [];

    if (!isset($index[$root])) {
        $index[$root] = [];

        try {
            $walk = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($walk as $entry) {
                if (!$entry->isFile()) continue;
                $key = strtolower(str_replace('\\', '/', substr($entry->getPathname(), strlen($root) + 1)));
                $index[$root][$key] = $entry->getPathname();
            }
        } catch (Throwable $error) {
            // Cartella illeggibile: si resta senza indice, non senza gioco.
        }
    }

    return $index[$root][strtolower(str_replace('\\', '/', $relative))] ?? null;
}

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

    $full = pullspot_find_file($root, $relative);
    if ($full === null) return null;
    if (!str_starts_with($full, rtrim($root, '\\/') . DIRECTORY_SEPARATOR)) return null;

    return $full;
}

/**
 * Perché un personaggio non è nel mazzo. Serve al pannello di diagnosi: senza,
 * un personaggio che non esce mai è un mistero che si può sciogliere solo
 * frugando nel database a mano.
 */
function pullspot_exclusion_reason(array $row): ?string
{
    $audio = trim((string)($row['audio_url'] ?? ''));

    if ($audio === '') return 'audio_url vuoto';
    if (preg_match('~^https?://~i', $audio)) return 'audio esterno (http)';

    $extension = strtolower((string)pathinfo($audio, PATHINFO_EXTENSION));
    if (!in_array($extension, PULLSPOT_AUDIO_EXT, true)) {
        return 'estensione non audio: .' . $extension;
    }

    if (pullspot_audio_path($audio) === null) return 'file non trovato in /audio';
    if (trim((string)($row['nome'] ?? '')) === '') return 'nome vuoto';

    return null;
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

/* ── Colore del personaggio ─────────────────────────────────────────────── */

/** Estensioni di immagine da cui sappiamo tirare fuori un colore. */
const PULLSPOT_IMAGE_EXT = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'];

/** Come per l'audio: solo file veri dentro le cartelle dei media del sito. */
function pullspot_image_path(?string $raw): ?string
{
    $raw = trim((string)$raw);
    if ($raw === '' || preg_match('~^https?://~i', $raw)) return null;

    $relative = ltrim(str_replace('\\', '/', $raw), '/');
    if ($relative === '' || str_contains($relative, '..')) return null;

    $roots = ['img', 'uploads'];
    $folder = 'img';

    if (str_starts_with($raw, '/')) {
        $first = explode('/', $relative)[0];
        if (!in_array($first, $roots, true)) return null;
        $folder = $first;
        $relative = substr($relative, strlen($first) + 1);
    }

    $extension = strtolower((string)pathinfo($relative, PATHINFO_EXTENSION));
    if (!in_array($extension, PULLSPOT_IMAGE_EXT, true)) return null;

    $root = realpath(__DIR__ . '/../' . $folder);
    if ($root === false) return null;

    $full = pullspot_find_file($root, $relative);
    if ($full === null) return null;
    if (!str_starts_with($full, rtrim($root, '\\/') . DIRECTORY_SEPARATOR)) return null;

    return $full;
}

/**
 * Tinta di ripiego, stabile per personaggio: serve quando l'immagine non c'è
 * o quando GD non è compilato. Il passo di 137,5 gradi è l'angolo aureo, che
 * sparpaglia le tinte invece di raggrupparle.
 */
function pullspot_accent_from_id(int $id): array
{
    return ['h' => (int)round(($id * 137.508)) % 360, 's' => 78, 'l' => 64];
}

/** Da RGB a una tinta che si legge sul nero: satura e a media luminosità. */
function pullspot_rgb_to_accent(float $r, float $g, float $b): array
{
    $max = max($r, $g, $b) / 255;
    $min = min($r, $g, $b) / 255;
    $delta = $max - $min;
    $h = 0.0;

    if ($delta > 0) {
        if ($max === $r / 255)      $h = fmod((($g - $b) / 255) / $delta, 6);
        elseif ($max === $g / 255)  $h = (($b - $r) / 255) / $delta + 2;
        else                        $h = (($r - $g) / 255) / $delta + 4;

        $h *= 60;
        if ($h < 0) $h += 360;
    }

    $l = ($max + $min) / 2;
    $s = $delta === 0.0 ? 0.0 : $delta / (1 - abs(2 * $l - 1));

    return [
        'h' => (int)round($h),
        's' => (int)round(max(.62, min(.95, $s)) * 100),
        'l' => (int)round(max(52, min(68, $l * 100))),
    ];
}

/**
 * Il colore del personaggio, ricavato dalla sua immagine.
 *
 * Lo calcola il server perché il colore accompagna tutta la partita, non solo
 * la rivelazione: mandare al client l'immagine per farglielo estrarre da solo
 * vorrebbe dire mandargli la risposta.
 *
 * Senza GD (o senza immagine) si ripiega sulla tinta legata all'id: cambia
 * comunque da personaggio a personaggio.
 */
function pullspot_accent(array $character): array
{
    $id = (int)$character['id'];

    if (isset($_SESSION['pullspot']['accents'][$id])) {
        return $_SESSION['pullspot']['accents'][$id];
    }

    $accent = pullspot_accent_from_id($id);
    $path = pullspot_image_path($character['img_url'] ?? null);

    if ($path !== null && function_exists('imagecreatefromstring')) {
        $bytes = @file_get_contents($path);
        $source = $bytes === false ? false : @imagecreatefromstring($bytes);

        if ($source !== false) {
            $size = 24;
            $thumb = @imagecreatetruecolor($size, $size);

            if ($thumb !== false) {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                imagecopyresampled(
                    $thumb, $source, 0, 0, 0, 0,
                    $size, $size, imagesx($source), imagesy($source)
                );

                $r = $g = $b = $weight = 0.0;

                for ($x = 0; $x < $size; $x++) {
                    for ($y = 0; $y < $size; $y++) {
                        $rgba = imagecolorat($thumb, $x, $y);
                        if ((($rgba >> 24) & 0x7F) > 64) continue;   // troppo trasparente

                        $pr = ($rgba >> 16) & 0xFF;
                        $pg = ($rgba >> 8) & 0xFF;
                        $pb = $rgba & 0xFF;

                        // I pixel spenti pesano poco: il colore lo danno i vivi.
                        $w = (max($pr, $pg, $pb) - min($pr, $pg, $pb)) / 255 + .06;

                        $r += $pr * $w;
                        $g += $pg * $w;
                        $b += $pb * $w;
                        $weight += $w;
                    }
                }

                if ($weight > 0) {
                    $accent = pullspot_rgb_to_accent($r / $weight, $g / $weight, $b / $weight);
                }

                imagedestroy($thumb);
            }

            imagedestroy($source);
        }
    }

    if (!isset($_SESSION['pullspot']) || !is_array($_SESSION['pullspot'])) {
        $_SESSION['pullspot'] = [];
    }
    if (!isset($_SESSION['pullspot']['accents']) || !is_array($_SESSION['pullspot']['accents'])) {
        $_SESSION['pullspot']['accents'] = [];
    }
    $_SESSION['pullspot']['accents'][$id] = $accent;

    return $accent;
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

function pullspot_remember(int $characterId, int $poolSize): void
{
    $recent = pullspot_recent();
    $recent[] = $characterId;

    $keep = max(1, min(PULLSPOT_RECENT_MEMORY, (int)floor($poolSize / 2)));

    if (!isset($_SESSION['pullspot']) || !is_array($_SESSION['pullspot'])) {
        $_SESSION['pullspot'] = [];
    }
    $_SESSION['pullspot']['recent'] = array_slice($recent, -$keep);
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
        pullspot_remember($character['id'], count(pullspot_pool($mysqli)));
        pullspot_session_save($game);
    }

    return [
        'game'      => $game,
        'character' => pullspot_character_by_id($mysqli, $game['char_id']),
    ];
}

/**
 * Un nome già provato non si ripropone: la ricerca lo toglie dall'elenco e
 * questo controllo chiude la porta anche a chi la chiama a mano.
 */
function pullspot_already_guessed(array $game, int $characterId): bool
{
    foreach ($game['guesses'] as $guess) {
        if ((int)($guess['id'] ?? 0) === $characterId) return true;
    }

    return false;
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
        // Il colore accompagna tutta la partita, non solo la fine: è una
        // scelta di gioco, non una svista. Toglierlo da qui lo riporta a
        // comparire solo alla rivelazione.
        'accent'       => pullspot_accent($character),
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
    $crc         = (($b1 & 0x01) === 0) ? 2 : 0;
    $channelBits = ($b3 >> 6) & 0x03;
    $mono        = $channelBits === 3;
    $sideInfo = $mpeg1 ? ($mono ? 17 : 32) : ($mono ? 9 : 17);
    $tagAt    = $offset + 4 + $crc + $sideInfo;

    $isTag = (substr($bytes, $tagAt, 4) === 'Xing' || substr($bytes, $tagAt, 4) === 'Info')
        || substr($bytes, $offset + 36, 4) === 'VBRI';

    return [
        'length'   => $length,
        'duration' => $samples / $sampleRate,
        'is_tag'   => $isTag,
        'shape'    => $version . ':' . $layer . ':' . $sampleRate . ':' . $channelBits,
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

    $audioStart = $cursor;
    $start   = null;
    $end     = null;
    $elapsed = 0.0;
    $frames  = 0;
    $shape   = null;

    while ($cursor + 4 <= $total) {
        if (ord($bytes[$cursor]) !== 0xFF || (ord($bytes[$cursor + 1]) & 0xE0) !== 0xE0) {
            // Un flusso MPEG vero comincia subito dopo il tag. Se bisogna
            // rovistare per chilometri prima di trovare un sync, quello che si
            // sta leggendo non è un MP3: meglio ammetterlo e non tagliare.
            if ($cursor - $audioStart > 4096) return null;
            $cursor++;
            continue;
        }

        $frame = pullspot_mp3_frame($bytes, $cursor);
        if ($frame === null) {
            if ($cursor - $audioStart > 4096) return null;
            $cursor++;
            continue;
        }
        if ($cursor + $frame['length'] > $total) break;

        if ($frame['is_tag'] && $start === null) {
            $cursor += $frame['length'];
            $audioStart = $cursor;
            continue;
        }

        // Versione, layer e frequenza non cambiano mai dentro un file vero:
        // se cambiano, i "frame" sono coincidenze dentro dati di altro tipo.
        if ($shape === null) {
            $shape = $frame['shape'];
        } elseif ($shape !== $frame['shape']) {
            return null;
        }

        if ($start === null) $start = $cursor;

        $frames++;
        $elapsed += $frame['duration'];
        $cursor  += $frame['length'];
        $end      = $cursor;

        if ($elapsed >= $seconds - 1e-9) break;
    }

    // Quattro frame sono meno di un decimo di secondo: sotto quella soglia
    // non c'è abbastanza flusso per dire che il file sia stato capito.
    if ($start === null || $end === null || $frames < 4) return null;

    return substr($bytes, $start, $end - $start);
}

/* ── Che cosa c'è davvero dentro il file ─────────────────────────────────── */

/**
 * Riconosce il contenitore dai byte, non dal nome.
 *
 * Una quindicina di tracce del sito sono MP4/AAC con l'estensione .mp3: dare
 * retta all'estensione voleva dire cercare frame MPEG dentro dati MP4, trovare
 * per caso due byte che sembravano un sync e servire spazzatura che nessun
 * browser sa suonare.
 */
function pullspot_sniff(string $bytes): string
{
    $at = 0;

    // Un tag ID3 può stare davanti a qualsiasi cosa: si guarda oltre.
    if (strlen($bytes) > 10 && strncmp($bytes, 'ID3', 3) === 0) {
        $at = 10
            + (((ord($bytes[6]) & 0x7F) << 21) | ((ord($bytes[7]) & 0x7F) << 14)
             | ((ord($bytes[8]) & 0x7F) << 7)  | (ord($bytes[9]) & 0x7F));
        if (ord($bytes[5]) & 0x10) $at += 10;
    }

    if (strlen($bytes) >= $at + 12 && substr($bytes, $at + 4, 4) === 'ftyp') return 'mp4';
    if (strncmp(substr($bytes, $at, 4), 'OggS', 4) === 0) return 'ogg';
    if (strncmp(substr($bytes, $at, 4), 'RIFF', 4) === 0) return 'wav';
    if (strncmp(substr($bytes, $at, 4), 'fLaC', 4) === 0) return 'flac';

    if (strlen($bytes) >= $at + 2) {
        $b0 = ord($bytes[$at]);
        $b1 = ord($bytes[$at + 1]);

        if ($b0 === 0xFF && ($b1 & 0xE0) === 0xE0) {
            // Nel sync ADTS i bit di "layer" sono a zero; in quello MPEG no.
            return (($b1 >> 1) & 0x03) === 0 ? 'aac' : 'mpeg';
        }
    }

    return 'unknown';
}

function pullspot_container_mime(string $kind): string
{
    return [
        'mpeg'    => 'audio/mpeg',
        'aac'     => 'audio/aac',
        'mp4'     => 'audio/mp4',
        'ogg'     => 'audio/ogg',
        'wav'     => 'audio/wav',
        'flac'    => 'audio/flac',
        'unknown' => 'application/octet-stream',
    ][$kind] ?? 'application/octet-stream';
}

/* ── Taglio dei file MP4/AAC ─────────────────────────────────────────────── */

/** Trova un box MP4 dentro un intervallo. Restituisce [inizio, fine] del contenuto. */
function pullspot_mp4_box(string $bytes, int $start, int $end, string $type): ?array
{
    $at = $start;

    while ($at + 8 <= $end) {
        $size = unpack('N', substr($bytes, $at, 4))[1];
        $name = substr($bytes, $at + 4, 4);
        $header = 8;

        if ($size === 1) {
            if ($at + 16 > $end) return null;
            $high = unpack('N', substr($bytes, $at + 8, 4))[1];
            $low  = unpack('N', substr($bytes, $at + 12, 4))[1];
            $size = $high * 4294967296 + $low;
            $header = 16;
        } elseif ($size === 0) {
            $size = $end - $at;
        }

        if ($size < $header || $at + $size > $end) return null;
        if ($name === $type) return [$at + $header, $at + $size];

        $at += $size;
    }

    return null;
}

/** Tutti i box di un tipo, non solo il primo. */
function pullspot_mp4_boxes(string $bytes, int $start, int $end, string $type): array
{
    $found = [];
    $at = $start;

    while ($at + 8 <= $end) {
        $size = unpack('N', substr($bytes, $at, 4))[1];
        $name = substr($bytes, $at + 4, 4);
        $header = 8;

        if ($size === 1) {
            if ($at + 16 > $end) break;
            $high = unpack('N', substr($bytes, $at + 8, 4))[1];
            $low  = unpack('N', substr($bytes, $at + 12, 4))[1];
            $size = $high * 4294967296 + $low;
            $header = 16;
        } elseif ($size === 0) {
            $size = $end - $at;
        }

        if ($size < $header || $at + $size > $end) break;
        if ($name === $type) $found[] = [$at + $header, $at + $size];

        $at += $size;
    }

    return $found;
}

/** Legge una tabella di interi a 32 bit (o 64 per co64). */
function pullspot_mp4_table(string $bytes, int $start, int $end, int $width, int $columns): array
{
    if ($start + 8 > $end) return [];

    $count = unpack('N', substr($bytes, $start + 4, 4))[1];
    $rows = [];
    $at = $start + 8;
    $step = $width * $columns;

    for ($i = 0; $i < $count; $i++) {
        if ($at + $step > $end) break;
        $row = [];

        for ($c = 0; $c < $columns; $c++) {
            if ($width === 8) {
                $high = unpack('N', substr($bytes, $at, 4))[1];
                $low  = unpack('N', substr($bytes, $at + 4, 4))[1];
                $row[] = $high * 4294967296 + $low;
            } else {
                $row[] = unpack('N', substr($bytes, $at, 4))[1];
            }
            $at += $width;
        }

        $rows[] = $columns === 1 ? $row[0] : $row;
    }

    return $rows;
}

/** Estrae la configurazione audio (AudioSpecificConfig) da un box esds. */
function pullspot_mp4_asc(string $bytes, int $start, int $end): ?array
{
    $at = $start + 4;   // versione e flag

    // I descrittori sono annidati: si scende finché non si trova il 0x05.
    while ($at < $end) {
        $tag = ord($bytes[$at]);
        $at++;

        $length = 0;
        for ($i = 0; $i < 4 && $at < $end; $i++) {
            $byte = ord($bytes[$at]);
            $at++;
            $length = ($length << 7) | ($byte & 0x7F);
            if (!($byte & 0x80)) break;
        }

        if ($tag === 0x03) {
            // ES_Descriptor: si salta l'intestazione e si continua dentro.
            if ($at + 3 > $end) return null;
            $flags = ord($bytes[$at + 2]);
            $at += 3;
            if ($flags & 0x80) $at += 2;
            if ($flags & 0x40) $at += 1 + ord($bytes[$at]);
            if ($flags & 0x20) $at += 2;
            continue;
        }

        if ($tag === 0x04) {
            $at += 13;   // tipo, stream, buffer, bitrate
            continue;
        }

        if ($tag === 0x05) {
            if ($at + 2 > $end) return null;

            $b0 = ord($bytes[$at]);
            $b1 = ord($bytes[$at + 1]);

            $objectType = ($b0 >> 3) & 0x1F;
            $rateIndex  = (($b0 & 0x07) << 1) | (($b1 >> 7) & 0x01);
            $channels   = ($b1 >> 3) & 0x0F;

            // ADTS sa dire solo i quattro profili base, e la frequenza deve
            // stare in tabella: fuori da lì si preferisce non inventare.
            if ($objectType < 1 || $objectType > 4) return null;
            if ($rateIndex > 12 || $channels < 1 || $channels > 7) return null;

            return ['object' => $objectType, 'rate' => $rateIndex, 'channels' => $channels];
        }

        $at += $length;
    }

    return null;
}

/** Intestazione ADTS di 7 byte davanti a un frame AAC grezzo. */
function pullspot_adts_header(array $config, int $frameLength): string
{
    $total = $frameLength + 7;

    return chr(0xFF)
        . chr(0xF1)                                                   // MPEG-4, niente CRC
        . chr((($config['object'] - 1) << 6) | ($config['rate'] << 2) | (($config['channels'] >> 2) & 0x01))
        . chr((($config['channels'] & 0x03) << 6) | (($total >> 11) & 0x03))
        . chr(($total >> 3) & 0xFF)
        . chr((($total & 0x07) << 5) | 0x1F)
        . chr(0xFC);
}

/**
 * I primi $seconds secondi di un MP4/AAC, riconfezionati in ADTS.
 *
 * Riscrivere un MP4 accorciato vorrebbe dire rifare tutte le tabelle degli
 * indici; l'AAC invece si può servire nudo, un frame dietro l'altro con la sua
 * intestazione, e i browser lo suonano come se fosse un file a sé.
 *
 * Restituisce null se il file non si lascia leggere: chi chiama servirà la
 * traccia intera piuttosto che qualcosa di rotto.
 */
function pullspot_mp4_clip(string $bytes, float $seconds): ?string
{
    $total = strlen($bytes);

    $moov = pullspot_mp4_box($bytes, 0, $total, 'moov');
    if ($moov === null) return null;

    foreach (pullspot_mp4_boxes($bytes, $moov[0], $moov[1], 'trak') as $trak) {
        $mdia = pullspot_mp4_box($bytes, $trak[0], $trak[1], 'mdia');
        if ($mdia === null) continue;

        $mdhd = pullspot_mp4_box($bytes, $mdia[0], $mdia[1], 'mdhd');
        $minf = pullspot_mp4_box($bytes, $mdia[0], $mdia[1], 'minf');
        if ($mdhd === null || $minf === null) continue;

        $stbl = pullspot_mp4_box($bytes, $minf[0], $minf[1], 'stbl');
        if ($stbl === null) continue;

        $stsd = pullspot_mp4_box($bytes, $stbl[0], $stbl[1], 'stsd');
        if ($stsd === null) continue;

        $mp4a = pullspot_mp4_box($bytes, $stsd[0] + 8, $stsd[1], 'mp4a');
        if ($mp4a === null) continue;   // non è una traccia audio AAC

        $esds = pullspot_mp4_box($bytes, $mp4a[0] + 28, $mp4a[1], 'esds');
        if ($esds === null) continue;

        $config = pullspot_mp4_asc($bytes, $esds[0], $esds[1]);
        if ($config === null) return null;

        $version = ord($bytes[$mdhd[0]]);
        $timescale = $version === 1
            ? unpack('N', substr($bytes, $mdhd[0] + 20, 4))[1]
            : unpack('N', substr($bytes, $mdhd[0] + 12, 4))[1];
        if ($timescale <= 0) return null;

        $stts = pullspot_mp4_box($bytes, $stbl[0], $stbl[1], 'stts');
        $stsc = pullspot_mp4_box($bytes, $stbl[0], $stbl[1], 'stsc');
        $stsz = pullspot_mp4_box($bytes, $stbl[0], $stbl[1], 'stsz');
        $stco = pullspot_mp4_box($bytes, $stbl[0], $stbl[1], 'stco');
        $co64 = $stco === null ? pullspot_mp4_box($bytes, $stbl[0], $stbl[1], 'co64') : null;

        if ($stts === null || $stsc === null || $stsz === null || ($stco === null && $co64 === null)) {
            return null;
        }

        $times  = pullspot_mp4_table($bytes, $stts[0], $stts[1], 4, 2);
        $chunks = pullspot_mp4_table($bytes, $stsc[0], $stsc[1], 4, 3);
        $offsets = $stco !== null
            ? pullspot_mp4_table($bytes, $stco[0], $stco[1], 4, 1)
            : pullspot_mp4_table($bytes, $co64[0], $co64[1], 8, 1);

        if (!$times || !$chunks || !$offsets) return null;

        // stsz: se la misura fissa è zero, le misure stanno in tabella.
        $fixed = unpack('N', substr($bytes, $stsz[0] + 4, 4))[1];
        $count = unpack('N', substr($bytes, $stsz[0] + 8, 4))[1];
        $sizes = [];

        if ($fixed === 0) {
            $at = $stsz[0] + 12;
            for ($i = 0; $i < $count && $at + 4 <= $stsz[1]; $i++) {
                $sizes[] = unpack('N', substr($bytes, $at, 4))[1];
                $at += 4;
            }
        }

        $sampleCount = $fixed === 0 ? count($sizes) : $count;
        if ($sampleCount === 0) return null;

        $out = '';
        $elapsed = 0.0;
        $sample = 0;
        $timeRow = 0;
        $timeLeft = $times[0][0];
        $stscRow = 0;

        foreach ($offsets as $chunkIndex => $offset) {
            while (isset($chunks[$stscRow + 1]) && $chunks[$stscRow + 1][0] <= $chunkIndex + 1) {
                $stscRow++;
            }
            $perChunk = max(1, (int)$chunks[$stscRow][1]);

            for ($i = 0; $i < $perChunk; $i++) {
                if ($sample >= $sampleCount) break 2;

                $size = $fixed === 0 ? $sizes[$sample] : $fixed;
                if ($size <= 0 || $offset + $size > $total) break 2;

                $out .= pullspot_adts_header($config, $size) . substr($bytes, $offset, $size);
                $offset += $size;

                while ($timeLeft <= 0 && isset($times[$timeRow + 1])) {
                    $timeRow++;
                    $timeLeft = $times[$timeRow][0];
                }
                $elapsed += $times[$timeRow][1] / $timescale;
                $timeLeft--;

                $sample++;
                if ($elapsed >= $seconds - 1e-9) break 2;
            }
        }

        return $out === '' ? null : $out;
    }

    return null;
}

/**
 * I byte da mandare al browser e il loro tipo.
 *
 * Il formato lo decide il contenuto del file, non il nome: sul sito ci sono
 * tracce MP4/AAC chiamate .mp3, e trattarle da MP3 significava servire
 * spezzoni che nessun browser riusciva a suonare.
 *
 * Se il taglio non riesce si manda la traccia intera: meglio un frammento
 * lungo che uno rotto. In quel caso a limitare l'ascolto resta il client.
 */
function pullspot_clip_bytes(string $path, ?float $seconds): ?array
{
    $bytes = @file_get_contents($path);
    if ($bytes === false) return null;

    $kind = pullspot_sniff($bytes);

    if ($seconds === null) {
        return ['bytes' => $bytes, 'mime' => pullspot_container_mime($kind), 'exact' => true];
    }

    if ($kind === 'mpeg') {
        $clip = pullspot_mp3_clip($bytes, $seconds);
        if ($clip !== null) {
            return ['bytes' => $clip, 'mime' => 'audio/mpeg', 'exact' => true];
        }
    }

    if ($kind === 'mp4') {
        $clip = pullspot_mp4_clip($bytes, $seconds);
        if ($clip !== null) {
            return ['bytes' => $clip, 'mime' => 'audio/aac', 'exact' => true];
        }
    }

    return ['bytes' => $bytes, 'mime' => pullspot_container_mime($kind), 'exact' => false];
}
