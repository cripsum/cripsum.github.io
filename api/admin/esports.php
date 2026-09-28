<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/admin/admin_shop_helpers.php';
require_once __DIR__ . '/../../includes/esports/esports.php';

/**
 * Team esports dal pannello (Esports > Team OHPY).
 *
 * GET  ?action=list          team, player e liste che servono ai form
 * GET  ?action=music_kits    catalogo dei music kit di CS2 (cache di 7 giorni)
 * POST action = save_team | save_player | set_state | reorder | delete_player | import_cover
 *
 * I testi a righe del pannello ("Etichetta: valore", palmares con le
 * barre) diventano JSON qui, e tornano testo nella lista: il pannello non
 * vede mai il JSON.
 */

/** Catalogo pubblico dei music kit (nome, artista, immagine ufficiale). */
const ESPORTS_KITS_URL = 'https://raw.githubusercontent.com/ByMykel/CSGO-API/main/public/api/en/music_kits.json';
const ESPORTS_KITS_CACHE = __DIR__ . '/../../scratch/esports/music_kits.json';
const ESPORTS_KITS_TTL = 7 * 86400;

$action = admin_shop_action();
$adminId = (int)$adminUser['id'];

if (!admin_table_exists($mysqli, 'esports_team') || !admin_table_exists($mysqli, 'esports_giocatori')) {
    if ($action === 'list') {
        admin_ok(['ready' => false, 'message' => 'Tabelle del team mancanti: applica migrations/2026_09_27_esports_ohpy.sql e ricarica.']);
    }
    admin_fail('Tabelle del team mancanti: applica la migrazione 2026_09_27_esports_ohpy.sql.', 409);
}

/* ── Conversioni testo <-> JSON ─────────────────────────────────────── */

/**
 * Un numero intero facoltativo. Accetta anche "18.452" o "18,452", come si
 * scrive il Premier rating.
 */
function esports_admin_optional_int(array $input, string $key, string $label, int $min, int $max): ?int
{
    $raw = trim(str_replace(['.', ',', ' ', "'"], '', (string)($input[$key] ?? '')));

    if ($raw === '') {
        return null;
    }

    if (!preg_match('/^\d{1,9}$/', $raw)) {
        admin_fail($label . ': serve un numero intero.');
    }

    $value = (int)$raw;
    if ($value < $min || $value > $max) {
        admin_fail($label . ': deve stare tra ' . number_format($min, 0, ',', '.') . ' e ' . number_format($max, 0, ',', '.') . '.');
    }

    return $value;
}

/**
 * I social: un campo per rete (social_twitch, social_steam...). Un link
 * scritto senza https:// lo prende da solo.
 */
function esports_admin_socials(array $input, array $keys): ?string
{
    $networks = esports_social_networks();
    $links = [];

    foreach ($keys as $key) {
        $value = trim((string)($input['social_' . $key] ?? ''));
        if ($value === '') {
            continue;
        }

        if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }

        if (mb_strlen($value) > 255 || !preg_match('~^https://[^\s]+$~i', $value) || !filter_var($value, FILTER_VALIDATE_URL)) {
            admin_fail($networks[$key]['label'] . ': serve un link https completo.');
        }

        $links[$key] = $value;
    }

    return $links ? json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
}

function esports_admin_socials_fields(?string $json): array
{
    $fields = [];
    foreach (esports_json($json) as $key => $url) {
        if (is_string($key) && is_string($url)) {
            $fields['social_' . $key] = $url;
        }
    }

    return $fields;
}

/**
 * Curiosita': "Etichetta: valore" diventa una scheda, "- frase" (o una frase
 * senza due punti) un punto dell'elenco. I due punti di un link (https://)
 * non contano.
 */
function esports_admin_facts(?string $text, string $label): array
{
    $facts = [];

    foreach (preg_split('/\R/', (string)$text) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        if (preg_match('/^[-*•]\s*(.+)$/u', $line, $m)) {
            $facts[] = mb_substr(trim($m[1]), 0, 200);
            continue;
        }

        if (preg_match('/^([^:]{1,40}):(?!\/\/)\s*(.+)$/u', $line, $m) && trim($m[1]) !== '' && trim($m[2]) !== '') {
            $facts[] = [trim($m[1]), mb_substr(trim($m[2]), 0, 200)];
            continue;
        }

        $facts[] = mb_substr($line, 0, 200);
    }

    if (count($facts) > 20) {
        admin_fail($label . ': al massimo 20 righe.');
    }

    return $facts;
}

function esports_admin_facts_text(?string $json, string $lang): string
{
    $lines = [];
    foreach ((esports_json($json)[$lang] ?? []) as $item) {
        if (is_string($item)) {
            $lines[] = '- ' . $item;
        } elseif (is_array($item) && count($item) >= 2) {
            $item = array_values($item);
            $lines[] = $item[0] . ': ' . $item[1];
        }
    }

    return implode("\n", $lines);
}

/**
 * Palmares, un risultato per riga:
 *   2026-05-12 | Torneo X | 1° | https://...
 * Data e link sono facoltativi; la data si puo' scrivere anche 12/05/2026,
 * 05/2026 o solo l'anno.
 */
function esports_admin_palmares(?string $text): ?string
{
    $entries = [];

    foreach (preg_split('/\R/', (string)$text) as $number => $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $parts = array_map('trim', explode('|', $line));
        $row = 'Palmarès, riga ' . ($number + 1);

        $date = '';
        $first = $parts[0] ?? '';
        if (preg_match('/^(\d{4})(-\d{2}){0,2}$/', $first)) {
            $date = $first;
            array_shift($parts);
        } elseif (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})$~', $first, $m)) {
            $date = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            array_shift($parts);
        } elseif (preg_match('~^(\d{1,2})/(\d{4})$~', $first, $m)) {
            $date = sprintf('%04d-%02d', $m[2], $m[1]);
            array_shift($parts);
        }

        if ($date !== '' && strlen($date) === 10 && !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))) {
            admin_fail($row . ': la data ' . $date . ' non esiste.');
        }

        $tournament = $parts[0] ?? '';
        if ($tournament === '') {
            admin_fail($row . ': manca il nome del torneo. Scrivi «data | torneo | piazzamento | link».');
        }

        $placement = '';
        $link = '';
        foreach (array_slice($parts, 1) as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('~^https?://~i', $part)) {
                if (!preg_match('~^https://~i', $part) || !filter_var($part, FILTER_VALIDATE_URL) || strlen($part) > 255) {
                    admin_fail($row . ': il link deve essere un indirizzo https completo.');
                }
                $link = $part;
            } elseif ($placement === '') {
                $placement = mb_substr($part, 0, 40);
            }
        }

        $entries[] = [
            'data' => $date,
            'torneo' => mb_substr($tournament, 0, 120),
            'piazzamento' => $placement,
            'link' => $link,
        ];
    }

    if (count($entries) > 40) {
        admin_fail('Palmarès: al massimo 40 risultati.');
    }

    return $entries ? json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
}

function esports_admin_palmares_text(?string $json): string
{
    $lines = [];
    foreach (esports_json($json) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $parts = array_filter([
            (string)($item['data'] ?? ''),
            (string)($item['torneo'] ?? ''),
            (string)($item['piazzamento'] ?? ''),
            (string)($item['link'] ?? ''),
        ], static fn(string $part): bool => $part !== '');
        $lines[] = implode(' | ', $parts);
    }

    return implode("\n", $lines);
}

/**
 * Il brano: un file caricato (dentro /audio/) che esista davvero, oppure un
 * link https.
 */
function esports_admin_audio(array $input): ?string
{
    $value = trim((string)($input['musica_audio'] ?? ''));

    if ($value === '') {
        return null;
    }

    if (mb_strlen($value) > 255) {
        admin_fail('File audio: percorso troppo lungo.');
    }

    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $value)) {
        if (!preg_match('~^https://~i', $value) || !filter_var($value, FILTER_VALIDATE_URL)) {
            admin_fail('File audio: un link deve essere https.');
        }
        return $value;
    }

    $path = '/' . ltrim(rawurldecode($value), '/');
    if (!str_starts_with($path, '/audio/') || str_contains($path, '..') || str_contains($path, "\0")) {
        admin_fail('File audio: caricalo dal pannello oppure usa un percorso /audio/... o un link https.');
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, ESPORTS_AUDIO_EXTENSIONS, true)) {
        admin_fail('File audio: formato non supportato (' . implode(', ', ESPORTS_AUDIO_EXTENSIONS) . ').');
    }

    $root = realpath(__DIR__ . '/../../audio');
    $real = $root !== false ? realpath(__DIR__ . '/../..' . $path) : false;
    $rootNorm = $root !== false ? rtrim(str_replace('\\', '/', $root), '/') . '/' : '';
    if ($real === false || !is_file($real) || !str_starts_with(str_replace('\\', '/', $real), $rootNorm)) {
        admin_fail('File audio: ' . $path . ' non esiste sul sito.');
    }

    return $path;
}

/**
 * Le clip in game dal form: campi clip1_* e clip2_* (video, copertina,
 * titolo IT/EN, larghezza e altezza lette dal pannello). Una clip senza
 * video non si salva; il video deve essere un file caricato che esiste.
 * Il JSON si scrive con le barre non escapate: la pulizia dei file cerca i
 * percorsi nel testo della colonna.
 */
function esports_admin_clips(array $input): ?string
{
    $clips = [];

    for ($i = 1; $i <= ESPORTS_MAX_CLIPS; $i++) {
        $label = 'Highlight ' . $i;
        $video = trim((string)($input["clip{$i}_video"] ?? ''));
        $hasOther = trim((string)($input["clip{$i}_copertina"] ?? '')) !== ''
            || trim((string)($input["clip{$i}_titolo"] ?? '')) !== '';

        if ($video === '') {
            if ($hasOther) {
                admin_fail($label . ': manca il video (o togli anche titolo e copertina).');
            }
            continue;
        }

        $path = '/' . ltrim(rawurldecode($video), '/');
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!str_starts_with($path, '/vid/') || str_contains($path, '..') || str_contains($path, "\0") || mb_strlen($path) > 255) {
            admin_fail($label . ': carica il video dal pannello.');
        }
        if (!in_array($ext, ESPORTS_VIDEO_EXTENSIONS, true)) {
            admin_fail($label . ': formato non supportato (' . implode(', ', ESPORTS_VIDEO_EXTENSIONS) . ').');
        }

        $root = realpath(__DIR__ . '/../../vid');
        $real = $root !== false ? realpath(__DIR__ . '/../..' . $path) : false;
        $rootNorm = $root !== false ? rtrim(str_replace('\\', '/', $root), '/') . '/' : '';
        if ($real === false || !is_file($real) || !str_starts_with(str_replace('\\', '/', $real), $rootNorm)) {
            admin_fail($label . ': il video ' . $path . ' non esiste sul sito.');
        }

        $clips[] = [
            'video' => $path,
            'copertina' => admin_shop_image($input, "clip{$i}_copertina", $label . ': copertina'),
            'titolo' => admin_shop_text($input, "clip{$i}_titolo", $label . ': titolo (IT)', 80),
            'titolo_en' => admin_shop_text($input, "clip{$i}_titolo_en", $label . ': titolo (EN)', 80),
            'larghezza' => admin_shop_int($input, "clip{$i}_larghezza", $label . ': larghezza', 0, 10000, 0),
            'altezza' => admin_shop_int($input, "clip{$i}_altezza", $label . ': altezza', 0, 10000, 0),
        ];
    }

    return $clips ? json_encode($clips, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
}

/** I file di tutte le clip (video e copertine), per ripulirli dopo un salvataggio. */
function esports_admin_clip_files(?string $json): array
{
    $files = [];
    foreach (esports_json($json) as $clip) {
        if (is_array($clip)) {
            $files[] = $clip['video'] ?? null;
            $files[] = $clip['copertina'] ?? null;
        }
    }

    return array_values(array_filter($files, 'is_string'));
}

/* ── Catalogo dei music kit ─────────────────────────────────────────── */

function esports_admin_http_get(string $url, int $maxBytes): ?string
{
    if (!function_exists('curl_init')) {
        $context = stream_context_create(['http' => ['timeout' => 12, 'user_agent' => 'Cripsum-Esports/1.0 (+https://cripsum.com)']]);
        $raw = @file_get_contents($url, false, $context, 0, $maxBytes + 1);
        return is_string($raw) && $raw !== '' && strlen($raw) <= $maxBytes ? $raw : null;
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => 'Cripsum-Esports/1.0 (+https://cripsum.com)',
        // Si interrompe se il file supera il limite, invece di scaricarlo tutto.
        CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => static fn($handle, $total, $downloaded): int => $downloaded > $maxBytes ? 1 : 0,
    ]);

    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    return is_string($body) && $body !== '' && $status === 200 && strlen($body) <= $maxBytes ? $body : null;
}

/** Immagini dei kit: solo dal CDN di Steam, in https. */
function esports_admin_kit_image_ok(string $url): bool
{
    $parts = parse_url($url);
    $host = strtolower((string)($parts['host'] ?? ''));

    return ($parts['scheme'] ?? '') === 'https'
        && ($host === 'cdn.steamstatic.com' || str_ends_with($host, '.steamstatic.com') || $host === 'steamcdn-a.akamaihd.net')
        && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function esports_admin_parse_kits(string $raw): array
{
    $items = json_decode($raw, true);
    if (!is_array($items)) {
        return [];
    }

    $kits = [];
    $seen = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        // "Music Kit | Artista, Titolo"; le versioni StatTrak sono doppioni.
        $name = trim((string)($item['name'] ?? ''));
        if ($name === '' || stripos($name, 'StatTrak') !== false) {
            continue;
        }
        $name = preg_replace('/^Music Kit\s*\|\s*/i', '', $name) ?? $name;
        [$artist, $title] = array_pad(explode(', ', $name, 2), 2, '');
        if ($title === '') {
            [$title, $artist] = [$artist, ''];
        }

        $key = strtolower((string)($item['original']['name'] ?? $name));
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $image = (string)($item['image'] ?? '');
        $rarity = (string)($item['rarity']['color'] ?? '');

        $kits[] = [
            'id' => preg_replace('/[^a-z0-9_-]/', '', $key) ?: md5($key),
            'artist' => $artist,
            'title' => $title,
            'image' => esports_admin_kit_image_ok($image) ? $image : '',
            'color' => preg_match('/^#[0-9a-f]{6}$/i', $rarity) ? strtolower($rarity) : '',
        ];
    }

    return $kits;
}

function esports_admin_music_kits(): array
{
    $cache = ESPORTS_KITS_CACHE;
    $read = static function () use ($cache): array {
        $data = is_file($cache) ? json_decode((string)@file_get_contents($cache), true) : null;
        return is_array($data) ? $data : [];
    };

    if (is_file($cache) && filemtime($cache) > time() - ESPORTS_KITS_TTL) {
        $kits = $read();
        if ($kits) {
            return $kits;
        }
    }

    $raw = esports_admin_http_get(ESPORTS_KITS_URL, 4 * 1024 * 1024);
    $kits = $raw !== null ? esports_admin_parse_kits($raw) : [];

    if ($kits) {
        if (!is_dir(dirname($cache))) {
            @mkdir(dirname($cache), 0755, true);
        }
        @file_put_contents($cache, json_encode($kits, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $kits;
    }

    // Il catalogo non risponde: meglio la copia vecchia che niente.
    return $read();
}

/* ── Team ───────────────────────────────────────────────────────────── */

$team = admin_shop_row($mysqli, 'SELECT * FROM esports_team WHERE slug = ? LIMIT 1', 's', [ESPORTS_TEAM_SLUG]);
if (!$team) {
    // La migrazione crea il team; se la riga manca (cancellata a mano) la
    // si ricrea vuota invece di lasciare il pannello inutilizzabile.
    admin_shop_exec($mysqli, 'INSERT INTO esports_team (slug, nome) VALUES (?, ?)', 'ss', [ESPORTS_TEAM_SLUG, 'OHPY'], 'Non riesco a creare il team.')->close();
    $team = admin_shop_row($mysqli, 'SELECT * FROM esports_team WHERE slug = ? LIMIT 1', 's', [ESPORTS_TEAM_SLUG]);
}
$teamId = (int)$team['id'];

// Le clip arrivano con migrations/2026_09_28_esports_clip.sql: prima di
// allora il pannello nasconde la sezione e il salvataggio non la tocca.
$clipsReady = admin_column_exists($mysqli, 'esports_giocatori', 'clips');

try {
    if ($action === 'list') {
        $players = admin_shop_rows(
            $mysqli,
            'SELECT g.*, u.username AS utente
             FROM esports_giocatori g
             LEFT JOIN utenti u ON u.id = g.utente_id
             WHERE g.team_id = ?
             ORDER BY g.posizione ASC, g.id ASC',
            'i',
            [$teamId]
        );

        foreach ($players as &$player) {
            $player['statistiche_testo'] = implode("\n", array_map(
                static fn(array $s): string => $s['label'] . ': ' . $s['value'],
                esports_stats($player['statistiche'])
            ));
            $player['setup_it'] = admin_shop_pairs_text($player['setup'], 'it');
            $player['setup_en'] = admin_shop_pairs_text($player['setup'], 'en');
            $player['curiosita_it'] = esports_admin_facts_text($player['curiosita'], 'it');
            $player['curiosita_en'] = esports_admin_facts_text($player['curiosita'], 'en');
            $player['colore_proprio'] = $player['colore_accento'] ? 1 : 0;
            $player += esports_admin_socials_fields($player['social']);
            $player['audio_url'] = esports_audio_url($player['musica_audio']);

            // Le clip tornano campi del form: clip1_video, clip1_titolo...
            $clipList = array_values(array_filter(esports_json($player['clips'] ?? null), 'is_array'));
            $player['clip_count'] = count($clipList);
            for ($i = 1; $i <= ESPORTS_MAX_CLIPS; $i++) {
                $clip = $clipList[$i - 1] ?? [];
                foreach (['video', 'copertina', 'titolo', 'titolo_en', 'larghezza', 'altezza'] as $key) {
                    $player["clip{$i}_{$key}"] = $clip[$key] ?? '';
                }
            }
        }
        unset($player);

        $team['palmares_testo'] = esports_admin_palmares_text($team['palmares']);
        $team += esports_admin_socials_fields($team['social']);

        $networks = esports_social_networks();
        $roles = [];
        foreach (esports_roles() as $key => $role) {
            $roles[] = ['key' => $key, 'label' => $role['it'], 'icon' => $role['icon']];
        }
        $countries = [];
        foreach (esports_countries() as $code => $names) {
            $countries[] = [$code, $names[0]];
        }

        admin_ok([
            'ready' => true,
            'team' => $team,
            'players' => $players,
            'roles' => $roles,
            'countries' => $countries,
            'team_socials' => array_map(static fn(string $k): array => ['key' => $k] + $networks[$k], ESPORTS_TEAM_SOCIALS),
            'player_socials' => array_map(static fn(string $k): array => ['key' => $k] + $networks[$k], ESPORTS_PLAYER_SOCIALS),
            'audio_extensions' => ESPORTS_AUDIO_EXTENSIONS,
            'clips_ready' => $clipsReady,
            'max_clips' => ESPORTS_MAX_CLIPS,
        ]);
    }

    if ($action === 'music_kits') {
        $kits = esports_admin_music_kits();
        if (!$kits) {
            admin_fail('Il catalogo dei music kit non risponde: compila titolo, artista e copertina a mano.', 503);
        }
        admin_ok(['kits' => $kits]);
    }

    admin_shop_require_post();
    $input = admin_input();

    switch ($action) {
        case 'save_team':
            $fields = [
                'nome' => admin_shop_text($input, 'nome', 'Nome del team', 80, true),
                'gioco' => admin_shop_text($input, 'gioco', 'Gioco', 60) ?? 'Counter-Strike 2',
                'frase' => admin_shop_text($input, 'frase', 'Frase (IT)', 200),
                'frase_en' => admin_shop_text($input, 'frase_en', 'Frase (EN)', 200),
                'descrizione' => admin_shop_text($input, 'descrizione', 'Descrizione (IT)', 3000),
                'descrizione_en' => admin_shop_text($input, 'descrizione_en', 'Descrizione (EN)', 3000),
                'logo' => admin_shop_image($input, 'logo', 'Logo'),
                'copertina' => admin_shop_image($input, 'copertina', 'Copertina'),
                'colore_accento' => admin_shop_color($input, 'colore_accento', 'Colore principale', '#f5a524'),
                'colore_sfondo' => admin_shop_color($input, 'colore_sfondo', 'Sfondo 1', '#07080c'),
                'colore_sfondo_2' => admin_shop_color($input, 'colore_sfondo_2', 'Sfondo 2', '#1c1307'),
                'social' => esports_admin_socials($input, ESPORTS_TEAM_SOCIALS),
                'link_testo' => admin_shop_text($input, 'link_testo', 'Testo del bottone (IT)', 60),
                'link_testo_en' => admin_shop_text($input, 'link_testo_en', 'Testo del bottone (EN)', 60),
                'link_url' => admin_shop_link($input, 'link_url', 'Link del bottone'),
                'palmares' => esports_admin_palmares($input['palmares'] ?? ''),
                'mostra_ex' => admin_shop_bool($input, 'mostra_ex'),
            ];

            if (($fields['link_testo'] === null) !== ($fields['link_url'] === null)) {
                admin_fail('Bottone della testata: servono sia il testo sia il link (o nessuno dei due).');
            }

            $set = implode(', ', array_map(static fn(string $c): string => "`$c` = ?", array_keys($fields)));
            $types = implode('', array_map(static fn($v): string => is_int($v) ? 'i' : 's', $fields)) . 'i';
            admin_shop_exec($mysqli, "UPDATE esports_team SET $set WHERE id = ? LIMIT 1", $types, array_merge(array_values($fields), [$teamId]), 'Non sono riuscito a salvare il team.')->close();

            admin_media_cleanup($mysqli, [$team['logo'] ?? null, $team['copertina'] ?? null], $adminId);
            admin_log($mysqli, $adminId, 'esports_update_team', null, ['team' => ESPORTS_TEAM_SLUG]);
            admin_ok(['message' => 'Team salvato.']);

        case 'save_player':
            $id = (int)($input['id'] ?? 0);
            $existing = $id > 0
                ? admin_shop_row($mysqli, 'SELECT * FROM esports_giocatori WHERE id = ? AND team_id = ? LIMIT 1', 'ii', [$id, $teamId])
                : null;
            if ($id > 0 && !$existing) {
                admin_fail('Player non trovato.', 404);
            }

            $nickname = admin_shop_text($input, 'nickname', 'Nickname', 40, true);
            $country = strtoupper(trim((string)($input['nazionalita'] ?? '')));
            if ($country !== '' && !preg_match('/^[A-Z]{2}$/', $country)) {
                admin_fail('Nazionalità: usa il codice di due lettere (IT, FR, DE...).');
            }

            $crosshair = trim((string)($input['crosshair'] ?? ''));
            if ($crosshair !== '') {
                $crosshair = preg_replace('/^csgo-/i', 'CSGO-', $crosshair) ?? $crosshair;
                if (!preg_match(ESPORTS_CROSSHAIR_PATTERN, $crosshair)) {
                    admin_fail('Codice mirino: deve essere come CSGO-xxxxx-xxxxx-xxxxx-xxxxx-xxxxx (lo copi da CS2, Impostazioni > Mirino > Condividi).');
                }
            }

            $utenteId = null;
            $username = ltrim(trim((string)($input['utente'] ?? '')), '@');
            if ($username !== '') {
                if (!admin_validate_username($username)) {
                    admin_fail('Profilo Cripsum: username non valido.');
                }
                $user = admin_shop_row($mysqli, 'SELECT id FROM utenti WHERE LOWER(username) = LOWER(?) LIMIT 1', 's', [$username]);
                if (!$user) {
                    admin_fail('Profilo Cripsum: nessun utente si chiama «' . $username . '».');
                }
                $utenteId = (int)$user['id'];
            }

            $stats = admin_shop_pairs($input['statistiche'] ?? '', 'Statistiche', 16);
            $setup = [
                'it' => admin_shop_pairs($input['setup_it'] ?? '', 'Setup (IT)', 16),
                'en' => admin_shop_pairs($input['setup_en'] ?? '', 'Setup (EN)', 16),
            ];
            $facts = [
                'it' => esports_admin_facts($input['curiosita_it'] ?? '', 'Curiosità (IT)'),
                'en' => esports_admin_facts($input['curiosita_en'] ?? '', 'Curiosità (EN)'),
            ];

            $fields = [
                'slug' => admin_shop_slug($input, 'slug', $nickname, 60),
                'nickname' => $nickname,
                'nome_reale' => admin_shop_text($input, 'nome_reale', 'Nome reale', 80),
                'ruolo' => admin_shop_enum($input, 'ruolo', 'Ruolo', array_keys(esports_roles()), 'rifler'),
                'ruolo_label' => admin_shop_text($input, 'ruolo_label', 'Ruolo personalizzato (IT)', 60),
                'ruolo_label_en' => admin_shop_text($input, 'ruolo_label_en', 'Ruolo personalizzato (EN)', 60),
                'stato' => admin_shop_enum($input, 'stato', 'Stato', ESPORTS_STATES, 'nascosto'),
                'nazionalita' => $country !== '' ? $country : null,
                'foto' => admin_shop_image($input, 'foto', 'Foto'),
                'sfondo' => admin_shop_image($input, 'sfondo', 'Sfondo della pagina'),
                'colore_accento' => admin_shop_bool($input, 'colore_proprio')
                    ? admin_shop_color($input, 'colore_accento', 'Colore del player', '#f5a524')
                    : null,
                'frase' => admin_shop_text($input, 'frase', 'Frase (IT)', 200),
                'frase_en' => admin_shop_text($input, 'frase_en', 'Frase (EN)', 200),
                'bio' => admin_shop_text($input, 'bio', 'Bio (IT)', 3000),
                'bio_en' => admin_shop_text($input, 'bio_en', 'Bio (EN)', 3000),
                'utente_id' => $utenteId,
                'premier_rating' => esports_admin_optional_int($input, 'premier_rating', 'Premier rating', 1, 50000),
                'faceit_livello' => esports_admin_optional_int($input, 'faceit_livello', 'Livello FACEIT', 1, 10),
                'faceit_elo' => esports_admin_optional_int($input, 'faceit_elo', 'ELO FACEIT', 100, 6000),
                'statistiche' => $stats ? json_encode($stats, JSON_UNESCAPED_UNICODE) : null,
                'setup' => ($setup['it'] || $setup['en']) ? json_encode($setup, JSON_UNESCAPED_UNICODE) : null,
                'crosshair' => $crosshair !== '' ? $crosshair : null,
                'curiosita' => ($facts['it'] || $facts['en']) ? json_encode($facts, JSON_UNESCAPED_UNICODE) : null,
                'social' => esports_admin_socials($input, ESPORTS_PLAYER_SOCIALS),
                'musica_titolo' => admin_shop_text($input, 'musica_titolo', 'Titolo del brano', 120),
                'musica_artista' => admin_shop_text($input, 'musica_artista', 'Artista', 120),
                'musica_cover' => admin_shop_image($input, 'musica_cover', 'Copertina del kit'),
                'musica_audio' => esports_admin_audio($input),
                'musica_inizio' => admin_shop_int($input, 'musica_inizio', 'Parti da (secondi)', 0, 3600, 0),
                'musica_volume' => admin_shop_int($input, 'musica_volume', 'Volume', 0, 100, 60),
            ];
            if ($clipsReady) {
                $fields['clips'] = esports_admin_clips($input);
            }

            $columns = array_keys($fields);
            $values = array_values($fields);
            $types = implode('', array_map(static fn($v): string => is_int($v) ? 'i' : 's', $values));
            $media = ['foto', 'sfondo', 'musica_cover', 'musica_audio'];

            if ($existing) {
                $set = implode(', ', array_map(static fn(string $c): string => "`$c` = ?", $columns));
                admin_shop_exec($mysqli, "UPDATE esports_giocatori SET $set WHERE id = ? LIMIT 1", $types . 'i', array_merge($values, [$id]), 'Non sono riuscito a salvare il player.')->close();
                // Foto, brano, video e copertine sostituiti o tolti: via dal
                // disco, se nessun'altra riga li usa.
                admin_media_cleanup($mysqli, array_merge(
                    array_map(static fn(string $c) => $existing[$c] ?? null, $media),
                    $clipsReady ? esports_admin_clip_files($existing['clips'] ?? null) : []
                ), $adminId);
                admin_log($mysqli, $adminId, 'esports_update_player', null, ['player_id' => $id, 'slug' => $fields['slug']]);
                admin_ok(['message' => 'Player salvato.', 'id' => $id, 'slug' => $fields['slug']]);
            }

            $columns[] = 'team_id';
            $values[] = $teamId;
            $columns[] = 'posizione';
            $values[] = admin_shop_next_position($mysqli, 'SELECT COALESCE(MAX(posizione), 0) FROM esports_giocatori WHERE team_id = ?', 'i', [$teamId]);
            $types .= 'ii';

            $stmt = admin_shop_exec(
                $mysqli,
                'INSERT INTO esports_giocatori (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
                $types,
                $values,
                'Non sono riuscito a creare il player.'
            );
            $newId = (int)$stmt->insert_id;
            $stmt->close();

            admin_log($mysqli, $adminId, 'esports_create_player', null, ['player_id' => $newId, 'slug' => $fields['slug']]);
            admin_ok(['message' => 'Player creato.', 'id' => $newId, 'slug' => $fields['slug']]);

        case 'set_state':
            $id = (int)($input['id'] ?? 0);
            $stato = admin_shop_enum($input, 'stato', 'Stato', ESPORTS_STATES, 'nascosto');
            $player = admin_shop_row($mysqli, 'SELECT id FROM esports_giocatori WHERE id = ? AND team_id = ? LIMIT 1', 'ii', [$id, $teamId]);
            if (!$player) {
                admin_fail('Player non trovato.', 404);
            }
            admin_shop_exec($mysqli, 'UPDATE esports_giocatori SET stato = ? WHERE id = ? LIMIT 1', 'si', [$stato, $id], 'Aggiornamento non riuscito.')->close();
            admin_log($mysqli, $adminId, 'esports_state_player', null, ['player_id' => $id, 'stato' => $stato]);
            admin_ok(['message' => 'Stato aggiornato.']);

        case 'reorder':
            $ids = admin_shop_ids($input);
            $own = array_map('intval', array_column(
                admin_shop_rows($mysqli, 'SELECT id FROM esports_giocatori WHERE team_id = ?', 'i', [$teamId]),
                'id'
            ));
            if (array_diff($ids, $own)) {
                admin_fail('Ordine non valido: ricarica la pagina.');
            }
            admin_shop_reorder($mysqli, 'esports_giocatori', 'posizione', $ids);
            admin_log($mysqli, $adminId, 'esports_reorder_players');
            admin_ok(['message' => 'Ordine aggiornato.']);

        case 'delete_player':
            $id = (int)($input['id'] ?? 0);
            $player = admin_shop_row($mysqli, 'SELECT * FROM esports_giocatori WHERE id = ? AND team_id = ? LIMIT 1', 'ii', [$id, $teamId]);
            if (!$player) {
                admin_fail('Player non trovato.', 404);
            }
            admin_shop_exec($mysqli, 'DELETE FROM esports_giocatori WHERE id = ? LIMIT 1', 'i', [$id], 'Eliminazione non riuscita.')->close();
            admin_media_cleanup($mysqli, array_merge(
                [$player['foto'], $player['sfondo'], $player['musica_cover'], $player['musica_audio']],
                esports_admin_clip_files($player['clips'] ?? null)
            ), $adminId);
            admin_log($mysqli, $adminId, 'esports_delete_player', null, ['slug' => $player['slug'], 'nickname' => $player['nickname']]);
            admin_ok(['message' => 'Player eliminato.']);

        case 'import_cover':
            // La copertina del kit scelto dal catalogo si copia sul sito:
            // la pagina non dipende da Steam e la si puo' ripulire come le
            // altre immagini del pannello.
            $url = trim((string)($input['image'] ?? ''));
            if (!esports_admin_kit_image_ok($url)) {
                admin_fail('Copertina: indirizzo non valido.');
            }

            // Il file prende il nome del kit (danielsadowski_01.png): gli
            // indirizzi di Steam sono hash lunghi centinaia di caratteri.
            $kit = strtolower(trim((string)($input['kit'] ?? '')));
            $base = preg_match('/^[a-z0-9_-]{1,60}$/', $kit) ? $kit : 'kit-' . substr(sha1($url), 0, 12);

            $dir = __DIR__ . '/../../img/esports/kits';
            foreach (['png', 'jpg', 'webp'] as $ext) {
                if (is_file($dir . '/' . $base . '.' . $ext)) {
                    admin_ok(['url' => '/img/esports/kits/' . $base . '.' . $ext, 'reused' => true]);
                }
            }

            $raw = esports_admin_http_get($url, 3 * 1024 * 1024);
            $info = $raw !== null ? @getimagesizefromstring($raw) : false;
            $ext = match ($info['mime'] ?? '') {
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                default => null,
            };
            if ($raw === null || $ext === null) {
                admin_fail('Non sono riuscito a scaricare la copertina da Steam: caricala a mano.', 502);
            }

            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                admin_fail('Non riesco a creare la cartella img/esports/kits sul server.', 500);
            }
            if (file_put_contents($dir . '/' . $base . '.' . $ext, $raw, LOCK_EX) === false) {
                admin_fail('Non sono riuscito a salvare la copertina sul server.', 500);
            }

            admin_log($mysqli, $adminId, 'upload_media', null, ['filename' => 'esports/kits/' . $base . '.' . $ext, 'type' => 'image', 'from' => 'music_kit']);
            admin_ok(['url' => '/img/esports/kits/' . $base . '.' . $ext, 'reused' => false]);
    }

    admin_fail('Azione non riconosciuta.', 400);
} catch (Throwable $e) {
    error_log('[admin esports] ' . $e->getMessage());
    admin_fail('Errore del server sul team.', 500);
}
