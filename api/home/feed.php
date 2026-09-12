<?php
declare(strict_types=1);

/**
 * Contenuti veri per lo slider della homepage.
 *
 * Prima quelle slide erano nove voci scritte a mano dentro home.js: un elenco
 * di funzionalita' con degli screenshot, sempre uguale. Qui arrivano invece le
 * cose che la gente ha appena pubblicato — shitpost, top rimasti, e le
 * estrazioni rare del gacha — cosi' la sezione racconta il sito con il sito
 * stesso.
 *
 * Nessuna autenticazione: escono solo contenuti gia' approvati, cioe' quelli
 * che chiunque vede aprendo le pagine pubbliche. Se le tabelle non ci sono o
 * non c'e' niente di recente, la risposta e' vuota e lo slider torna da solo
 * alle slide fisse: la homepage non deve mai dipendere da questo.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Il contenuto e' pubblico e cambia lentamente: un minuto di cache nel browser
// toglie una raffica di query a ogni visita della homepage.
header('Cache-Control: public, max-age=60');

/** Quante voci al massimo finiscono nello slider. */
const HOME_FEED_LIMIT = 12;

/** Oltre questa eta' un contenuto non e' piu' "novita'". */
const HOME_FEED_MAX_AGE_DAYS = 45;

/** Le rarita' che meritano di finire in prima pagina. */
const HOME_FEED_RARITIES = ['leggendario', 'speciale', 'segreto', 'theone'];

/** Cache condivisa fra le visite: la homepage e' la pagina piu' battuta. */
const HOME_FEED_CACHE_SECONDS = 60;

$lang = ($_GET['lang'] ?? 'it') === 'en' ? 'en' : 'it';

$cacheFile = sys_get_temp_dir() . '/cripsum-home-feed-' . $lang . '.json';

if (is_file($cacheFile) && (time() - (int)filemtime($cacheFile)) < HOME_FEED_CACHE_SECONDS) {
    $cached = file_get_contents($cacheFile);
    if ($cached !== false && $cached !== '') {
        header('X-Cripsum-Cache: hit');
        echo $cached;
        exit;
    }
}

/**
 * Testo accorciato senza tagliare a meta' una parola.
 */
function home_feed_snippet(?string $text, int $max = 140): string
{
    $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
    if ($text === '' || mb_strlen($text, 'UTF-8') <= $max) {
        return $text;
    }

    $cut = mb_substr($text, 0, $max, 'UTF-8');
    $lastSpace = mb_strrpos($cut, ' ', 0, 'UTF-8');

    if ($lastSpace !== false && $lastSpace > $max * 0.6) {
        $cut = mb_substr($cut, 0, $lastSpace, 'UTF-8');
    }

    return rtrim($cut, " \t\n\r\0\x0B.,;:") . '…';
}

/**
 * Nome da mostrare per un autore, con le stesse regole del resto del sito:
 * vince il nome Discord se l'utente ha scelto di usarlo.
 */
function home_feed_author_name(array $row): string
{
    $useDiscord = (int)($row['discord_use_display_name'] ?? 0) === 1;
    $discord = trim((string)($row['discord_global_name'] ?? '')) ?: trim((string)($row['discord_username'] ?? ''));

    if ($useDiscord && $discord !== '') {
        return $discord;
    }

    return trim((string)($row['display_name'] ?? '')) ?: (string)($row['username'] ?? '');
}

$items = [];

/**
 * Le colonne dell'autore servono a tre query identiche: si scrivono una volta.
 * `discord_use_display_name` e compagnia non esistono su tutti gli schemi, per
 * questo si controlla prima invece di far fallire l'intera query.
 */
$authorColumns = ['display_name', 'discord_use_display_name', 'discord_global_name', 'discord_username'];
$authorSelect = 'u.username';

foreach ($authorColumns as $column) {
    if (auth_column_exists($mysqli, 'utenti', $column)) {
        $authorSelect .= ', u.`' . $column . '`';
    }
}

$hasProfileStamp = auth_column_exists($mysqli, 'utenti', 'profile_updated_at');
if ($hasProfileStamp) {
    $authorSelect .= ', u.profile_updated_at';
}

$activeClause = '';
if (auth_column_exists($mysqli, 'utenti', 'deletion_requested_at')) {
    $activeClause = ' AND u.deletion_requested_at IS NULL';
}

/**
 * Post della community: shitpost e top rimasti condividono la stessa forma,
 * cambiano solo i nomi delle colonne.
 */
$postSources = [
    [
        'type' => 'shitpost',
        'table' => 'shitposts',
        'badge' => $lang === 'en' ? 'Shitpost' : 'Shitpost',
        'page' => 'shitpost',
        'score' => null,
        'likes' => 'shitpost_likes',
        'likesPost' => 'id_shitpost',
        'mime' => 'tipo_foto_shitpost',
    ],
    [
        'type' => 'rimasto',
        'table' => 'toprimasti',
        'badge' => $lang === 'en' ? 'Top Rimasti' : 'Top Rimasti',
        'page' => 'rimasti',
        'score' => 'reazioni',
        'likes' => null,
        'likesPost' => null,
        'mime' => 'tipo_foto_rimasto',
    ],
];

foreach ($postSources as $source) {
    if (!auth_table_exists($mysqli, $source['table'])) {
        continue;
    }

    $scoreSelect = $source['score'] && auth_column_exists($mysqli, $source['table'], $source['score'])
        ? 'COALESCE(p.`' . $source['score'] . '`, 0)'
        : '0';

    // I like degli shitpost stanno in una tabella a parte: si contano con una
    // sottoquery invece di un JOIN, che moltiplicherebbe le righe del post.
    if ($source['likes'] && auth_table_exists($mysqli, $source['likes'])) {
        $scoreSelect = '(SELECT COUNT(*) FROM `' . $source['likes'] . '` l WHERE l.`'
            . $source['likesPost'] . '` = p.id)';
    }

    // Un post puo' contenere un video: senza sapere il tipo lo slider proverebbe
    // a metterlo dentro un <img> e mostrerebbe un riquadro rotto.
    $mimeSelect = auth_column_exists($mysqli, $source['table'], $source['mime'])
        ? 'p.`' . $source['mime'] . '`'
        : "''";

    $sql = "SELECT p.id, p.titolo, p.descrizione, p.data_creazione,
                   $scoreSelect AS punteggio,
                   $mimeSelect AS media_mime,
                   p.id_utente, $authorSelect
            FROM `{$source['table']}` p
            INNER JOIN utenti u ON u.id = p.id_utente
            WHERE p.approvato = 1
              AND p.data_creazione >= DATE_SUB(NOW(), INTERVAL ? DAY)
              $activeClause
            ORDER BY p.data_creazione DESC
            LIMIT ?";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        continue;
    }

    $days = HOME_FEED_MAX_AGE_DAYS;
    $limit = HOME_FEED_LIMIT;
    $stmt->bind_param('ii', $days, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $stamp = $hasProfileStamp && !empty($row['profile_updated_at'])
            ? strtotime((string)$row['profile_updated_at'])
            : 0;

        $items[] = [
            'kind' => $source['type'],
            'badge' => $source['badge'],
            'title' => (string)$row['titolo'],
            'description' => home_feed_snippet($row['descrizione'] ?? ''),
            'media' => '/api/content/media.php?type=' . $source['type'] . '&id=' . (int)$row['id'],
            'media_video' => str_starts_with((string)($row['media_mime'] ?? ''), 'video/'),
            'url' => '/' . $lang . '/' . $source['page'] . '?post=' . (int)$row['id'],
            'author' => home_feed_author_name($row),
            'author_url' => '/u/' . rawurlencode(strtolower((string)$row['username'])),
            'author_avatar' => '/includes/get_pfp.php?id=' . (int)$row['id_utente'] . '&t=' . $stamp . '&size=64',
            'score' => (int)$row['punteggio'],
            'at' => (string)$row['data_creazione'],
        ];
    }

    $stmt->close();
}

/**
 * Estrazioni rare del gacha: sono il momento piu' "guarda cosa e' uscito" che
 * il sito produca, e a differenza dei post non richiedono moderazione.
 */
if (auth_table_exists($mysqli, 'gacha_pull_history') && auth_table_exists($mysqli, 'personaggi')) {
    $placeholders = implode(',', array_fill(0, count(HOME_FEED_RARITIES), '?'));

    $sql = "SELECT g.id, g.created_at, g.utente_id, g.`rarità` AS rarita,
                   c.nome, c.img_url, $authorSelect
            FROM gacha_pull_history g
            INNER JOIN personaggi c ON c.id = g.personaggio_id
            INNER JOIN utenti u ON u.id = g.utente_id
            WHERE LOWER(TRIM(g.`rarità`)) IN ($placeholders)
              AND g.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              $activeClause
            ORDER BY g.id DESC
            LIMIT ?";

    $stmt = $mysqli->prepare($sql);

    if ($stmt) {
        $days = HOME_FEED_MAX_AGE_DAYS;
        $limit = HOME_FEED_LIMIT;
        $params = array_merge(HOME_FEED_RARITIES, [$days, $limit]);
        $stmt->bind_param(str_repeat('s', count(HOME_FEED_RARITIES)) . 'ii', ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $author = home_feed_author_name($row);
            $rarita = strtolower(trim((string)$row['rarita']));

            $items[] = [
                'kind' => 'gacha',
                'badge' => ucfirst($rarita),
                'title' => (string)$row['nome'],
                'description' => $lang === 'en'
                    ? $author . ' just pulled it from the lootbox.'
                    : $author . ' l\'ha appena estratto dalla lootbox.',
                'media' => (string)$row['img_url'],
                'media_video' => false,
                'url' => '/' . $lang . '/lootbox',
                'author' => $author,
                'author_url' => '/u/' . rawurlencode(strtolower((string)$row['username'])),
                'author_avatar' => '/includes/get_pfp.php?id=' . (int)$row['utente_id'] . '&size=64',
                'score' => 0,
                'at' => (string)$row['created_at'],
                'rarity' => $rarita,
            ];
        }

        $stmt->close();
    }
}

// Il piu' recente per primo, poi si taglia: cosi' le tre fonti si mescolano da
// sole invece di uscire a blocchi ordinati per tipo.
usort($items, static fn(array $a, array $b): int => strcmp((string)$b['at'], (string)$a['at']));
$items = array_slice($items, 0, HOME_FEED_LIMIT);

$payload = json_encode([
    'ok' => true,
    'items' => $items,
    'generated_at' => date(DATE_ATOM),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($payload === false) {
    http_response_code(500);
    echo '{"ok":false,"items":[]}';
    exit;
}

// La scrittura passa da un file temporaneo: due visite simultanee non devono
// poter far leggere a una terza un JSON scritto a meta'.
$tmp = $cacheFile . '.' . getmypid() . '.tmp';
if (@file_put_contents($tmp, $payload, LOCK_EX) !== false) {
    @rename($tmp, $cacheFile);
} else {
    @unlink($tmp);
}

header('X-Cripsum-Cache: miss');
echo $payload;
