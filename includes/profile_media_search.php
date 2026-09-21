<?php

/**
 * Fonti esterne per le sezioni "preferiti" del profilo e per il brano del
 * player. L'endpoint e' api/profile/media_search.php: qui ci sono solo le
 * chiamate e la conversione dei risultati, senza sessione ne' database, cosi'
 * si possono provare da riga di comando quando una fonte cambia idea.
 *
 * Quattro tipi, ognuno con le sue fonti. Tutte pubbliche e senza chiave: una
 * chiave da rinnovare a mano sarebbe una sezione del profilo che un giorno
 * smette di funzionare senza che nessuno se ne accorga.
 *
 *   game   Steam (store), con la copertina verticale del CDN
 *   watch  AniList per gli anime + Cinemeta per film e serie
 *   music  iTunes Search, che porta anche la copertina
 *   read   AniList per manga e light novel + OpenLibrary per i libri
 *
 * Scelte e trappole (provate il 20 settembre 2026):
 * - Jikan risponde 504 a intermittenza, AniList no: gli anime arrivano da li'.
 * - AniList con `type: MANGA` restituisce anche le light novel (`format:
 *   NOVEL`), quindi manga e LN vengono dalla stessa richiesta.
 * - Google Books risponde 429 dagli IP condivisi anche senza chiave: per i
 *   libri si usa OpenLibrary.
 * - La ricerca film/serie di iTunes e' rimasta senza risultati: per quelli si
 *   usa Cinemeta, che restituisce i poster di IMDb.
 * - Una fonte che non risponde non fa fallire la ricerca: si tiene quello che
 *   e' arrivato dalle altre, e resta sempre l'inserimento a mano.
 */

require_once __DIR__ . '/profile_helpers.php';

const PMS_KINDS = ['game', 'watch', 'music', 'read'];
const PMS_CACHE_TTL = 86400;      // un giorno
const PMS_LIMIT = 12;             // risultati per ricerca
const PMS_TIMEOUT = 8;            // secondi per fonte
const PMS_RATE_MAX = 40;          // richieste...
const PMS_RATE_WINDOW = 60;       // ...al minuto, per utente

// ─────────────────────────────────────────────────────────────
//  Richieste in uscita
// ─────────────────────────────────────────────────────────────

/**
 * Una richiesta HTTP che non fa mai saltare la pagina: se la fonte non
 * risponde torna null e il chiamante tiene quello che ha.
 *
 * Lo User-Agent e' obbligatorio: cURL di PHP non ne manda uno e piu' di un
 * CDN dietro Cloudflare risponde 403 a chi si presenta senza.
 */
function pms_fetch(string $url, ?array $jsonBody = null, array $headers = []): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => PMS_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'CripsumProfile/7.0 (+https://cripsum.com)',
    ];
    if ($jsonBody !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($jsonBody, JSON_UNESCAPED_UNICODE);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Accept: application/json';
    }
    if ($headers) {
        $options[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $status < 200 || $status >= 300) {
        // Chi guarda i risultati deve sapere che sono monchi: una risposta
        // parziale non va messa in cache per un giorno intero.
        $GLOBALS['pms_source_failed'] = true;
        return null;
    }
    $decoded = json_decode((string)$body, true);
    if (!is_array($decoded)) {
        $GLOBALS['pms_source_failed'] = true;
        return null;
    }
    return $decoded;
}

/** Vero se durante l'ultima pms_search() una fonte non ha risposto. */
function pms_last_search_incomplete(): bool
{
    return !empty($GLOBALS['pms_source_failed']);
}

/** Un risultato pulito, come lo vuole l'editor. */
function pms_item(string $source, $id, string $title, string $subtitle = '', string $meta = '', string $image = '', string $url = ''): ?array
{
    $title = profile_clean_text($title, 120);
    if ($title === '') {
        return null;
    }
    if ($image !== '' && !profile_is_safe_url($image, true)) {
        $image = '';
    }
    if ($url !== '' && !profile_is_safe_url($url, true)) {
        $url = '';
    }
    return [
        'source' => $source . ':' . $id,
        'title' => $title,
        'subtitle' => profile_clean_text($subtitle, 120),
        'meta' => profile_clean_text($meta, 80),
        'image' => $image,
        'url' => $url,
    ];
}

// ─────────────────────────────────────────────────────────────
//  Le fonti
// ─────────────────────────────────────────────────────────────

/** Steam. La copertina verticale sta su un indirizzo prevedibile dall'id. */
function pms_search_steam(string $query): array
{
    $url = 'https://store.steampowered.com/api/storesearch/?' . http_build_query([
        'term' => $query,
        'l' => 'italian',
        'cc' => 'IT',
    ]);
    $json = pms_fetch($url);
    $items = [];
    foreach ($json['items'] ?? [] as $row) {
        $appId = (int)($row['id'] ?? 0);
        if ($appId <= 0) continue;
        $platforms = [];
        foreach (['windows' => 'Windows', 'mac' => 'Mac', 'linux' => 'Linux'] as $key => $label) {
            if (!empty($row['platforms'][$key])) $platforms[] = $label;
        }
        $item = pms_item(
            'steam',
            $appId,
            (string)($row['name'] ?? ''),
            'Steam',
            implode(' · ', $platforms),
            'https://cdn.cloudflare.steamstatic.com/steam/apps/' . $appId . '/library_600x900.jpg',
            'https://store.steampowered.com/app/' . $appId . '/'
        );
        if ($item) $items[] = $item;
    }
    return $items;
}

/**
 * Wikipedia, tenendo solo le pagine che parlano di videogiochi.
 *
 * Serve perche' Steam ha solo i giochi che vende: "zelda" li' non esiste. La
 * copertina e' la miniatura della pagina, che per i videogiochi e' quasi
 * sempre la copertina vera.
 */
function pms_search_wikipedia_games(string $query, string $lang = 'it'): array
{
    $lang = $lang === 'en' ? 'en' : 'it';
    $hint = $lang === 'en' ? ' video game' : ' videogioco';
    $url = 'https://' . $lang . '.wikipedia.org/w/api.php?' . http_build_query([
        'action' => 'query',
        'generator' => 'search',
        'gsrsearch' => $query . $hint,
        'gsrlimit' => PMS_LIMIT,
        'prop' => 'pageimages|description',
        'piprop' => 'thumbnail',
        'pithumbsize' => 500,
        'format' => 'json',
        'formatversion' => 2,
    ]);
    $json = pms_fetch($url);

    $pages = $json['query']['pages'] ?? [];
    // `generator=search` restituisce le pagine sparpagliate: l'ordine giusto
    // sta in `index`, non in quello in cui arrivano.
    usort($pages, static fn(array $a, array $b): int => ((int)($a['index'] ?? 99)) <=> ((int)($b['index'] ?? 99)));

    $items = [];
    foreach ($pages as $page) {
        $description = (string)($page['description'] ?? '');
        // Cercando "zelda videogioco" escono anche il personaggio, il film e
        // la saga: si tiene solo cio' che Wikidata descrive come un gioco.
        if (!preg_match('/videogioc|video game/i', $description)) {
            continue;
        }
        // "personaggio dei videogiochi", "serie videoludica" e simili passano
        // il controllo di sopra ma non sono giochi.
        if (preg_match('/personagg|character|serie|series|saga|franchise|azienda|company|sviluppatore|developer/i', $description)) {
            continue;
        }
        $title = (string)($page['title'] ?? '');
        // "Nome (videogioco 2017)" -> "Nome": la precisazione e' per Wikipedia.
        $clean = preg_replace('/\s*\((?:videogioco|video game)[^)]*\)\s*$/i', '', $title);
        $item = pms_item(
            'wikipedia',
            (int)($page['pageid'] ?? 0),
            (string)$clean,
            $lang === 'en' ? 'Video game' : 'Videogioco',
            trim((string)(preg_match('/\b(19|20)\d{2}\b/', $description, $m) ? $m[0] : '')),
            (string)($page['thumbnail']['source'] ?? ''),
            'https://' . $lang . '.wikipedia.org/?curid=' . (int)($page['pageid'] ?? 0)
        );
        if ($item) $items[] = $item;
    }
    return $items;
}

/** AniList: anime con type ANIME, manga e light novel con type MANGA. */
function pms_search_anilist(string $query, string $type): array
{
    $gql = 'query ($q: String, $t: MediaType) {
        Page(perPage: ' . PMS_LIMIT . ') {
            media(search: $q, type: $t, sort: SEARCH_MATCH, isAdult: false) {
                id
                format
                seasonYear
                startDate { year }
                title { romaji english }
                coverImage { large }
                siteUrl
            }
        }
    }';
    $json = pms_fetch('https://graphql.anilist.co', [
        'query' => $gql,
        'variables' => ['q' => $query, 't' => $type],
    ]);

    $formats = [
        'TV' => 'Serie TV', 'TV_SHORT' => 'Serie corta', 'MOVIE' => 'Film', 'SPECIAL' => 'Speciale',
        'OVA' => 'OVA', 'ONA' => 'ONA', 'MUSIC' => 'Musicale',
        'MANGA' => 'Manga', 'NOVEL' => 'Light novel', 'ONE_SHOT' => 'One shot',
    ];

    $items = [];
    foreach ($json['data']['Page']['media'] ?? [] as $row) {
        $title = trim((string)($row['title']['english'] ?? '')) ?: trim((string)($row['title']['romaji'] ?? ''));
        $year = (int)($row['seasonYear'] ?? $row['startDate']['year'] ?? 0);
        $format = $formats[(string)($row['format'] ?? '')] ?? (string)($row['format'] ?? '');
        $item = pms_item(
            'anilist',
            (int)($row['id'] ?? 0),
            $title,
            $format,
            $year > 0 ? (string)$year : '',
            (string)($row['coverImage']['large'] ?? ''),
            (string)($row['siteUrl'] ?? '')
        );
        if ($item) $items[] = $item;
    }
    return $items;
}

/** Cinemeta (Stremio): film e serie con i poster di IMDb, senza chiave. */
function pms_search_cinemeta(string $query, string $type): array
{
    $url = 'https://v3-cinemeta.strem.io/catalog/' . $type . '/top/search=' . rawurlencode($query) . '.json';
    $json = pms_fetch($url);
    $label = $type === 'movie' ? 'Film' : 'Serie TV';

    $items = [];
    foreach (array_slice($json['metas'] ?? [], 0, PMS_LIMIT) as $row) {
        $imdb = preg_replace('/[^a-z0-9]/i', '', (string)($row['imdb_id'] ?? $row['id'] ?? ''));
        if ($imdb === '') continue;
        $item = pms_item(
            'imdb',
            $imdb,
            (string)($row['name'] ?? ''),
            $label,
            (string)($row['releaseInfo'] ?? ''),
            (string)($row['poster'] ?? ''),
            'https://www.imdb.com/title/' . $imdb . '/'
        );
        if ($item) $items[] = $item;
    }
    return $items;
}

/** iTunes: brani con copertina. La miniatura si chiede piu' grande. */
function pms_search_itunes(string $query): array
{
    $url = 'https://itunes.apple.com/search?' . http_build_query([
        'term' => $query,
        'entity' => 'song',
        'limit' => PMS_LIMIT,
        'country' => 'IT',
    ]);
    $json = pms_fetch($url);

    $items = [];
    foreach ($json['results'] ?? [] as $row) {
        // L'immagine arriva a 100x100: l'indirizzo porta la misura, e
        // chiedendone una piu' grande il CDN la serve davvero.
        $art = str_replace('100x100bb', '600x600bb', (string)($row['artworkUrl100'] ?? ''));
        $year = substr((string)($row['releaseDate'] ?? ''), 0, 4);
        $item = pms_item(
            'itunes',
            (int)($row['trackId'] ?? 0),
            (string)($row['trackName'] ?? ''),
            (string)($row['artistName'] ?? ''),
            trim(implode(' · ', array_filter([(string)($row['collectionName'] ?? ''), $year]))),
            $art,
            (string)($row['trackViewUrl'] ?? '')
        );
        if ($item) $items[] = $item;
    }
    return $items;
}

/** OpenLibrary: libri veri, con copertina dall'id `cover_i`. */
function pms_search_openlibrary(string $query): array
{
    $url = 'https://openlibrary.org/search.json?' . http_build_query([
        'q' => $query,
        'limit' => PMS_LIMIT,
        'fields' => 'key,title,author_name,first_publish_year,cover_i',
    ]);
    $json = pms_fetch($url);

    $items = [];
    foreach ($json['docs'] ?? [] as $row) {
        $key = trim((string)($row['key'] ?? ''), '/');
        if ($key === '') continue;
        $coverId = (int)($row['cover_i'] ?? 0);
        $year = (int)($row['first_publish_year'] ?? 0);
        $item = pms_item(
            'openlibrary',
            str_replace('/', '_', $key),
            (string)($row['title'] ?? ''),
            (string)(($row['author_name'][0] ?? '')),
            $year > 0 ? (string)$year : '',
            $coverId > 0 ? 'https://covers.openlibrary.org/b/id/' . $coverId . '-L.jpg' : '',
            'https://openlibrary.org/' . $key
        );
        if ($item) $items[] = $item;
    }
    return $items;
}

/** Tutte le fonti di un tipo, unite, senza doppioni. */
function pms_search(string $kind, string $query, string $lang = 'it'): array
{
    $GLOBALS['pms_source_failed'] = false;
    $results = [];
    try {
        switch ($kind) {
            case 'game':
                // Steam ha solo cio' che vende: senza Wikipedia, "zelda" e
                // mezzo Nintendo non si trovavano proprio.
                $results = array_merge(
                    pms_search_steam($query),
                    pms_search_wikipedia_games($query, $lang)
                );
                break;

            case 'watch':
                // Prima gli anime, poi film e serie: chi cerca qui di solito
                // cerca un anime, e i titoli occidentali restano subito sotto.
                $results = array_merge(
                    pms_search_anilist($query, 'ANIME'),
                    pms_search_cinemeta($query, 'movie'),
                    pms_search_cinemeta($query, 'series')
                );
                break;

            case 'music':
                $results = pms_search_itunes($query);
                break;

            case 'read':
                $results = array_merge(
                    pms_search_anilist($query, 'MANGA'),
                    pms_search_openlibrary($query)
                );
                break;
        }
    } catch (Throwable $e) {
        error_log('[media_search] ' . $e->getMessage());
        $results = [];
    }

    // Lo stesso titolo puo' arrivare da due fonti: tenere il primo basta.
    $seen = [];
    $results = array_values(array_filter($results, static function (array $item) use (&$seen): bool {
        $key = mb_strtolower($item['title'] . '|' . $item['subtitle'], 'UTF-8');
        if (isset($seen[$key])) {
            return false;
        }
        $seen[$key] = true;
        return true;
    }));

    return array_slice($results, 0, 24);
}
