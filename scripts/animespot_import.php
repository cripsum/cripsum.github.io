<?php

/**
 * Cripsum™ — Animespot: costruzione del catalogo
 *
 * Riempie `animespot_anime`, `animespot_titoli` e `animespot_tracce` leggendo
 * due fonti pubbliche:
 *
 *   • AnimeThemes (https://api.animethemes.moe) — quali sigle esistono, di che
 *     anime sono, chi le canta e dove sta il file audio;
 *   • Kitsu (https://kitsu.io/api) — quanto è conosciuto un anime e come si
 *     chiama in inglese.
 *
 * La notorietà serve a una cosa sola: dividere le sigle in cinque difficoltà.
 * Senza, il gioco funziona lo stesso ma tutto finisce in fondo alla scala.
 *
 * Kitsu e non MyAnimeList perché a Kitsu si possono chiedere venti anime per
 * volta, per identificativo: sono duecentoquaranta richieste sui nostri
 * cinquemila anime invece di milleduecento pagine di classifica da spulciare,
 * e non è mai capitato che rispondesse 504 come fa Jikan a giorni alterni.
 *
 * Uso:
 *   php scripts/animespot_import.php                  tutto, riusando la cache
 *   php scripts/animespot_import.php --no-popolarita  salta la notorietà
 *   php scripts/animespot_import.php --fresh          ignora la cache su disco
 *   php scripts/animespot_import.php --dry-run        non scrive sul database
 *
 * Le pagine scaricate restano in scratch/animespot/, che Apache non serve:
 * rilanciare lo script dopo un'interruzione riparte da dove era arrivato.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo da riga di comando.\n");
}

require_once __DIR__ . '/../secure/config.php';

const AT_API      = 'https://api.animethemes.moe';
const KITSU_API   = 'https://kitsu.io/api/edge';
const CACHE_DIR   = __DIR__ . '/../scratch/animespot';
const AT_PAUSE    = 250000;  // µs fra due chiamate ad AnimeThemes
const KITSU_PAUSE = 300000;  // µs fra due chiamate a Kitsu
const KITSU_BATCH = 20;      // quanti anime per chiamata (è il massimo utile)

/**
 * Percentili delle cinque difficoltà, sulle sigle ordinate per notorietà.
 *
 * "Facile" è stretto apposta. Allargandolo di due punti ci finivano dentro la
 * diciannovesima opening di One Piece e la nona ending di Naruto: sono di
 * serie famosissime, ma nessuno le riconosce in un decimo di secondo, ed è
 * quello che deve voler dire facile.
 */
const TIERS = [0.03, 0.11, 0.26, 0.55, 1.00];

/* ── Opzioni ────────────────────────────────────────────────────────────── */

$options = getopt('', ['no-popolarita', 'fresh', 'dry-run', 'help']);

if (isset($options['help'])) {
    echo file_get_contents(__FILE__, false, null, 0, 1400), "\n";
    exit(0);
}

$usePopularity = !isset($options['no-popolarita']);
$fresh         = isset($options['fresh']);
$dryRun        = isset($options['dry-run']);

/* ── Utilità ────────────────────────────────────────────────────────────── */

function say(string $line): void
{
    echo '[' . date('H:i:s') . '] ' . $line . "\n";
    flush();
}

function fail(string $line): never
{
    fwrite(STDERR, 'ERRORE: ' . $line . "\n");
    exit(1);
}

/**
 * Una GET con qualche tentativo in più.
 *
 * Le due API sono gratuite e ogni tanto rispondono 429 o niente: rinunciare al
 * primo intoppo vorrebbe dire ricominciare un'ora di download.
 */
function http_get(string $url, int $attempts = 5): ?string
{
    for ($try = 1; $try <= $attempts; $try++) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Cripsum-Animespot/1.0 (+https://cripsum.com)',
            // Kitsu parla JSON:API e rifiuta con un 406 chi chiede solo
            // application/json: elencarli tutti e due costa niente.
            CURLOPT_HTTPHEADER     => ['Accept: application/vnd.api+json, application/json'],
        ]);

        $body   = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($body !== false && $status === 200) return $body;

        // Oltre l'ultima pagina le due API rispondono 404: insistere cinque
        // volte su una pagina che non c'è vuol dire solo aspettare quindici
        // secondi in più alla fine di ogni fase.
        if ($status === 404) return null;

        // 429 è "rallenta", non "non esiste": si aspetta di più a ogni giro.
        $wait = $status === 429 ? 5 * $try : $try;
        say("  … risposta $status, riprovo fra {$wait}s (tentativo $try/$attempts)");
        sleep($wait);
    }

    return null;
}

/** Una pagina JSON, dalla cache se c'è già. */
function fetch_page(string $url, string $cacheKey, int $pause): ?array
{
    global $fresh;

    $file = CACHE_DIR . '/' . $cacheKey . '.json';

    if (!$fresh && is_file($file)) {
        $decoded = json_decode((string)file_get_contents($file), true);
        if (is_array($decoded)) return $decoded;
    }

    usleep($pause);
    $body = http_get($url);
    if ($body === null) return null;

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) return null;

    file_put_contents($file, $body);

    return $decoded;
}

/**
 * La forma su cui si confrontano i titoli: senza maiuscole, senza accenti e
 * senza punteggiatura.
 *
 * "Re:Zero kara Hajimeru Isekai Seikatsu" e "rezero kara hajimeru isekai
 * seikatsu" devono incontrarsi, altrimenti la ricerca punisce chi scrive bene.
 * Il giapponese resta com'è: lì non c'è niente da appiattire.
 */
function normalize_title(string $text): string
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

    // Via tutto ciò che non è lettera, cifra o spazio — gli ideogrammi restano.
    $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

/** L'ordinale di una sigla: OP1 → 1, ED → 1, OP12 → 12. */
function slug_ordinal(string $slug): int
{
    return preg_match('/(\d+)/', $slug, $m) ? max(1, min(255, (int)$m[1])) : 1;
}

/* ── Fase 1: le sigle ───────────────────────────────────────────────────── */

/**
 * Fra più versioni della stessa sigla si tiene quella che si presta meglio a
 * essere giocata e poi mostrata: prima di tutto deve avere un audio, poi
 * conta che il video sia pulito (senza sovraincisioni, senza sottotitoli).
 */
function rank_video(array $video): int
{
    $score = 0;
    if (!empty($video['audio']['basename'])) $score += 10000;
    if (($video['overlap'] ?? 'None') === 'None') $score += 400;
    if (empty($video['lyrics'])) $score += 200;
    if (empty($video['subbed'])) $score += 100;
    if (!empty($video['nc'])) $score += 50;

    return $score + (int)(($video['resolution'] ?? 0) / 10);
}

function collect_themes(): array
{
    $fields = [
        'fields[anime]'            => 'id,name,slug,season,year,media_format',
        'fields[song]'             => 'id,title',
        'fields[artist]'           => 'id,name',
        'fields[animethemeentry]'  => 'id,version,nsfw,spoiler',
        'fields[video]'            => 'id,basename,lyrics,nc,overlap,source,resolution,subbed',
        'fields[audio]'            => 'id,basename,size',
        'fields[image]'            => 'id,facet,link',
        'include'                  => 'anime.images,song.artists,animethemeentries.videos.audio',
        'page[size]'               => '100',
    ];

    $themes = [];
    $anime  = [];
    $page   = 1;

    while (true) {
        $url = AT_API . '/animetheme?' . http_build_query($fields + ['page[number]' => (string)$page]);
        $data = fetch_page($url, 'themes-' . $page, AT_PAUSE);

        if ($data === null) fail("AnimeThemes non risponde alla pagina $page.");

        $rows = $data['animethemes'] ?? [];
        if (!$rows) break;

        foreach ($rows as $theme) {
            $animeRow = $theme['anime'] ?? null;
            if (!is_array($animeRow) || empty($animeRow['id'])) continue;

            // Il migliore fra tutti i video di tutte le versioni: cercare solo
            // nella prima versione perde le sigle il cui video buono sta nella
            // seconda, che non sono poche.
            $best = null;
            foreach ($theme['animethemeentries'] ?? [] as $entry) {
                if (!empty($entry['nsfw'])) continue;

                foreach ($entry['videos'] ?? [] as $video) {
                    if (empty($video['audio']['basename'])) continue;
                    if ($best === null || rank_video($video) > rank_video($best)) $best = $video;
                }
            }

            if ($best === null) continue;

            $animeId = (int)$animeRow['id'];

            if (!isset($anime[$animeId])) {
                $cover = '';
                foreach ($animeRow['images'] ?? [] as $image) {
                    $facet = (string)($image['facet'] ?? '');
                    if ($facet === 'Large Cover') { $cover = (string)($image['link'] ?? ''); break; }
                    if ($facet === 'Small Cover' && $cover === '') $cover = (string)($image['link'] ?? '');
                }

                $anime[$animeId] = [
                    'id'       => $animeId,
                    'nome'     => (string)($animeRow['name'] ?? ''),
                    'slug'     => (string)($animeRow['slug'] ?? ''),
                    'anno'     => $animeRow['year'] !== null ? (int)$animeRow['year'] : null,
                    'stagione' => (string)($animeRow['season'] ?? ''),
                    'formato'  => (string)($animeRow['media_format'] ?? ''),
                    'cover'    => $cover,
                ];
            }

            $artists = [];
            foreach ($theme['song']['artists'] ?? [] as $artist) {
                $name = trim((string)($artist['name'] ?? ''));
                if ($name !== '') $artists[] = $name;
            }

            $slug = (string)($theme['slug'] ?? 'OP1');

            $themes[] = [
                'theme_id'   => (int)$theme['id'],
                'anime_id'   => $animeId,
                'tipo'       => ($theme['type'] ?? 'OP') === 'ED' ? 'ED' : 'OP',
                'slug'       => $slug !== '' ? $slug : (string)($theme['type'] ?? 'OP'),
                'ordinale'   => slug_ordinal($slug),
                'canzone_id' => isset($theme['song']['id']) ? (int)$theme['song']['id'] : null,
                'canzone'    => trim((string)($theme['song']['title'] ?? '')),
                'artisti'    => implode(', ', array_slice($artists, 0, 6)),
                'audio'      => (string)$best['audio']['basename'],
                'bytes'      => (int)($best['audio']['size'] ?? 0),
                'video'      => (string)($best['basename'] ?? ''),
            ];
        }

        say('  sigle: ' . count($themes) . ' (pagina ' . $page . ')');

        if (empty($data['links']['next'])) break;
        $page++;
    }

    return [$themes, $anime];
}

/* ── Fase 2: identificativi e titoli alternativi ────────────────────────── */

/**
 * Un solo passaggio su /anime per due cose che servono entrambe: il numero con
 * cui MyAnimeList conosce la serie e i titoli con cui la conoscono gli altri.
 *
 * Le due informazioni starebbero anche su /resource e /synonym, ma quegli
 * elenchi non dicono a quale anime appartiene ogni riga — e senza quel legame
 * sono due liste di parole senza padrone.
 */
function collect_anime_extras(): array
{
    $malIds   = [];
    $kitsuIds = [];
    $synonyms = [];
    $page     = 1;

    while (true) {
        $url = AT_API . '/anime?' . http_build_query([
            'include'          => 'animesynonyms,resources',
            'fields[anime]'    => 'id',
            'fields[synonym]'  => 'id,text',
            'fields[resource]' => 'id,external_id,site',
            'page[size]'       => '100',
            'page[number]'     => (string)$page,
        ]);

        $data = fetch_page($url, 'extra-' . $page, AT_PAUSE);
        if ($data === null) break;

        $rows = $data['anime'] ?? [];
        if (!$rows) break;

        foreach ($rows as $row) {
            $animeId = (int)($row['id'] ?? 0);
            if ($animeId <= 0) continue;

            foreach ($row['resources'] ?? [] as $resource) {
                $external = (int)($resource['external_id'] ?? 0);
                if ($external <= 0) continue;

                // MyAnimeList serve solo per il collegamento nella scheda
                // finale; Kitsu è quello da cui arriva la notorietà.
                if (($resource['site'] ?? '') === 'MyAnimeList') $malIds[$animeId]   = $external;
                if (($resource['site'] ?? '') === 'Kitsu')       $kitsuIds[$animeId] = $external;
            }

            foreach ($row['animesynonyms'] ?? [] as $synonym) {
                $text = trim((string)($synonym['text'] ?? ''));
                if ($text !== '') $synonyms[$animeId][] = $text;
            }
        }

        if (empty($data['links']['next'])) break;
        $page++;
    }

    say('  identificativi: ' . count($malIds) . ' MyAnimeList, ' . count($kitsuIds) . ' Kitsu;'
        . ' titoli alternativi per ' . count($synonyms) . ' anime');

    return [$malIds, $kitsuIds, $synonyms];
}

/* ── Fase 3: notorietà e titoli inglesi da Kitsu ────────────────────────── */

/**
 * Quanti utenti hanno l'anime in libreria, e come lo chiamano.
 *
 * Venti anime per chiamata, chiesti per identificativo: si scarica solo quello
 * che serve, e in quattro minuti c'è tutto. Il numero di utenti non è
 * "quanto è bello" ma "quanti l'hanno visto", ed è esattamente la domanda a
 * cui deve rispondere una difficoltà.
 */
function collect_popularity(array $kitsuIds): array
{
    $ids   = array_values(array_unique(array_map('intval', $kitsuIds)));
    $found = [];
    $batch = 0;
    $total = (int)ceil(count($ids) / KITSU_BATCH);

    foreach (array_chunk($ids, KITSU_BATCH) as $chunk) {
        $batch++;

        $url = KITSU_API . '/anime?' . http_build_query([
            'filter[id]'    => implode(',', $chunk),
            'page[limit]'   => (string)KITSU_BATCH,
            'fields[anime]' => 'canonicalTitle,titles,userCount,averageRating',
        ]);

        $data = fetch_page($url, 'kitsu-' . $batch, KITSU_PAUSE);
        if ($data === null) {
            say("  Kitsu non ha risposto al blocco $batch: proseguo senza quei venti.");
            continue;
        }

        foreach ($data['data'] ?? [] as $row) {
            $kitsuId = (int)($row['id'] ?? 0);
            $attrs   = $row['attributes'] ?? [];
            if ($kitsuId <= 0 || !is_array($attrs)) continue;

            // I titoli arrivano come mappa di lingue: en e en_us sono la
            // versione inglese, ja_jp quella in giapponese vero. en_jp è la
            // traslitterazione, che di solito è già il titolo principale.
            $titles = [];
            foreach ((array)($attrs['titles'] ?? []) as $language => $text) {
                $text = trim((string)$text);
                if ($text === '') continue;

                $titles[] = [
                    'testo' => $text,
                    'tipo'  => match (true) {
                        str_starts_with((string)$language, 'ja') => 'giapponese',
                        str_starts_with((string)$language, 'en') => 'inglese',
                        default                                  => 'sinonimo',
                    },
                ];
            }

            $canonical = trim((string)($attrs['canonicalTitle'] ?? ''));
            if ($canonical !== '') $titles[] = ['testo' => $canonical, 'tipo' => 'inglese'];

            $rating = $attrs['averageRating'] ?? null;

            $found[$kitsuId] = [
                'utenti' => (int)($attrs['userCount'] ?? 0),
                // Kitsu dà un voto su cento: qui si tiene su dieci, con due
                // decimali, come lo scrivono tutti gli altri siti.
                'voto'   => $rating !== null ? (int)round(((float)$rating) * 10) : null,
                'titoli' => $titles,
            ];
        }

        if ($batch % 25 === 0 || $batch === $total) {
            say('  Kitsu: blocco ' . $batch . '/' . $total . ', ' . count($found) . ' anime riconosciuti');
        }
    }

    return $found;
}

/* ── Fase 4: la difficoltà ──────────────────────────────────────────────── */

/**
 * La notorietà della singola sigla, non dell'anime.
 *
 * La quarta ending di una serie famosa non è famosa quanto la sua prima
 * opening: chi guarda salta le ending e ricorda l'apertura. Senza questa
 * correzione la difficoltà "facile" si riempirebbe di sigle che nessuno ha mai
 * ascoltato fino in fondo — la prova era One Piece, che di opening ne ha
 * ventiquattro e ne conoscono tutti una.
 */
function track_weight(int $popularity, string $tipo, int $ordinale): int
{
    if ($popularity <= 0) return 0;

    $weight = $popularity / (1 + 0.45 * max(0, $ordinale - 1));
    if ($tipo === 'ED') $weight *= 0.70;

    return (int)round($weight);
}

/* ── Esecuzione ─────────────────────────────────────────────────────────── */

if (!is_dir(CACHE_DIR) && !mkdir(CACHE_DIR, 0775, true) && !is_dir(CACHE_DIR)) {
    fail('Non riesco a creare ' . CACHE_DIR);
}

say('Leggo le sigle da AnimeThemes…');
[$themes, $anime] = collect_themes();
say('Trovate ' . count($themes) . ' sigle giocabili su ' . count($anime) . ' anime.');

if (!$themes) fail('Nessuna sigla: qualcosa è andato storto nel download.');

say('Leggo identificativi e titoli alternativi…');
[$malIds, $kitsuIds, $synonyms] = collect_anime_extras();

$popularity = [];
if ($usePopularity) {
    $blocks = (int)ceil(count($kitsuIds) / KITSU_BATCH);
    say('Leggo la notorietà da Kitsu (' . $blocks . ' blocchi, circa ' . max(1, (int)ceil($blocks * 0.9 / 60)) . ' minuti)…');
    $popularity = collect_popularity($kitsuIds);
    say('Notorietà nota per ' . count($popularity) . ' anime su ' . count($kitsuIds) . '.');
} else {
    say('Salto la notorietà: tutte le sigle finiranno nelle difficoltà alte.');
}

/* Anime completi ------------------------------------------------------- */

foreach ($anime as $id => &$row) {
    $kitsuId = $kitsuIds[$id] ?? null;
    $kitsu   = $kitsuId !== null ? ($popularity[$kitsuId] ?? null) : null;

    $row['mal_id']     = $malIds[$id] ?? null;
    $row['popolarita'] = $kitsu['utenti'] ?? 0;
    $row['voto']       = $kitsu['voto'] ?? null;
    $row['titoli']     = $kitsu['titoli'] ?? [];
}
unset($row);

/* Difficoltà ----------------------------------------------------------- */

foreach ($themes as &$theme) {
    $theme['peso'] = track_weight(
        $anime[$theme['anime_id']]['popolarita'] ?? 0,
        $theme['tipo'],
        $theme['ordinale']
    );
}
unset($theme);

$weights = array_column($themes, 'peso');
rsort($weights);

$total = count($weights);
$cuts  = [];
foreach (TIERS as $tier => $share) {
    $index  = min($total - 1, max(0, (int)floor($total * $share) - 1));
    $cuts[] = $weights[$index];
}

/**
 * I tagli sono soglie di peso, non posizioni: due sigle con la stessa
 * notorietà devono finire nella stessa difficoltà anche se il percentile
 * cadrebbe fra loro.
 */
$counts = array_fill(1, 5, 0);
foreach ($themes as &$theme) {
    $level = 5;

    // Peso zero vuol dire che di quell'anime non sappiamo niente: è il caso
    // dell'ultima difficoltà per definizione, e va detto prima dei percentili.
    // Se gli sconosciuti fossero tanti da spingere una soglia a zero,
    // finirebbero in "esperto" e la difficoltà più alta resterebbe vuota.
    if ($theme['peso'] > 0) {
        foreach ($cuts as $tier => $threshold) {
            if ($threshold > 0 && $theme['peso'] >= $threshold) { $level = $tier + 1; break; }
        }
    }

    $theme['difficolta'] = $level;
    $counts[$level]++;
}
unset($theme);

say('Difficoltà: ' . implode(' · ', array_map(
    static fn(int $k, int $v): string => ['', 'facile', 'media', 'difficile', 'esperto', 'impossibile'][$k] . ' ' . $v,
    array_keys($counts),
    $counts
)));

if ($dryRun) {
    say('--dry-run: mi fermo qui senza scrivere niente.');
    exit(0);
}

/* Scrittura ------------------------------------------------------------ */

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) fail('Database: ' . $mysqli->connect_error);
$mysqli->set_charset('utf8mb4');

foreach (['animespot_anime', 'animespot_titoli', 'animespot_tracce'] as $table) {
    if (!$mysqli->query("SHOW TABLES LIKE '$table'")->num_rows) {
        fail("Manca la tabella `$table`: applica prima migrations/2026_09_09_animespot.sql.");
    }
}

say('Scrivo il catalogo…');
$mysqli->begin_transaction();

$mysqli->query('DELETE FROM `animespot_titoli`');
$mysqli->query('DELETE FROM `animespot_tracce`');
$mysqli->query('DELETE FROM `animespot_anime`');

$insertAnime = $mysqli->prepare(
    'INSERT INTO `animespot_anime` (id, nome, slug, anno, stagione, formato, mal_id, popolarita, voto, cover_url)'
    . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

foreach ($anime as $row) {
    $insertAnime->bind_param(
        'isssssiiis',
        $row['id'], $row['nome'], $row['slug'], $row['anno'], $row['stagione'],
        $row['formato'], $row['mal_id'], $row['popolarita'], $row['voto'], $row['cover']
    );
    $insertAnime->execute();
}
$insertAnime->close();

// I titoli: quello principale, i sinonimi delle due fonti e i titoli delle
// canzoni. Un doppione non fa danno alla ricerca ma allunga ogni risposta,
// quindi si scarta subito quello che è già entrato per lo stesso anime.
$insertTitle = $mysqli->prepare(
    'INSERT INTO `animespot_titoli` (anime_id, testo, norm, tipo) VALUES (?, ?, ?, ?)'
);

$titleCount = 0;
$seen = [];

$addTitle = static function (int $animeId, string $text, string $kind) use ($insertTitle, &$seen, &$titleCount): void {
    $text = trim($text);
    if ($text === '' || mb_strlen($text, 'UTF-8') > 255) return;

    $norm = normalize_title($text);
    if ($norm === '') return;

    $key = $animeId . "\0" . $norm;
    if (isset($seen[$key])) return;
    $seen[$key] = true;

    $insertTitle->bind_param('isss', $animeId, $text, $norm, $kind);
    $insertTitle->execute();
    $titleCount++;
};

foreach ($anime as $id => $row) {
    $addTitle($id, $row['nome'], 'principale');

    foreach ($synonyms[$id] ?? [] as $text) {
        $addTitle($id, $text, 'sinonimo');
    }

    foreach ($row['titoli'] as $title) {
        $addTitle($id, $title['testo'], $title['tipo']);
    }
}

foreach ($themes as $theme) {
    if ($theme['canzone'] !== '') $addTitle($theme['anime_id'], $theme['canzone'], 'canzone');
}

$insertTitle->close();

$insertTrack = $mysqli->prepare(
    'INSERT INTO `animespot_tracce`'
    . ' (theme_id, anime_id, tipo, slug, ordinale, canzone_id, canzone, artisti, audio, audio_bytes, video, difficolta, peso)'
    . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

foreach ($themes as $theme) {
    $insertTrack->bind_param(
        'iissiisssisii',
        $theme['theme_id'], $theme['anime_id'], $theme['tipo'], $theme['slug'], $theme['ordinale'],
        $theme['canzone_id'], $theme['canzone'], $theme['artisti'], $theme['audio'], $theme['bytes'],
        $theme['video'], $theme['difficolta'], $theme['peso']
    );
    $insertTrack->execute();
}
$insertTrack->close();

$mysqli->commit();

say('Fatto: ' . count($anime) . ' anime, ' . $titleCount . ' titoli, ' . count($themes) . ' sigle.');
