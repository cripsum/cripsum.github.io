<?php
require_once __DIR__ . '/bootstrap.php';
chat_require_login_json($mysqli);

$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 60, 'UTF-8');
$limit = max(8, min(50, (int)KLIPY_LIMIT));
$page = max(1, min(250, (int)($_GET['page'] ?? $_GET['pos'] ?? $_GET['offset'] ?? 1)));

if (!defined('KLIPY_API_KEY') || KLIPY_API_KEY === '') {
    chat_json([
        'ok' => false,
        'error' => 'KLIPY non configurato. Inserisci KLIPY_API_KEY in /config/klipy_config.php o nelle variabili ambiente.'
    ], 503);
}

$apiKey = rawurlencode((string)KLIPY_API_KEY);
$endpoint = $q === '' ? "https://api.klipy.com/api/v1/{$apiKey}/gifs/trending" : "https://api.klipy.com/api/v1/{$apiKey}/gifs/search";
$params = [
    'per_page' => $limit,
    'page' => $page,
    'rating' => KLIPY_RATING,
    'locale' => KLIPY_LOCALE,
];
if ($q !== '') $params['q'] = $q;

$url = $endpoint . '?' . http_build_query($params);
$body = null;
$status = 0;

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 7,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'CripsumChat/3.0-KLIPY',
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300) $body = null;
} else {
    $ctx = stream_context_create(['http' => ['timeout' => 7, 'header' => "User-Agent: CripsumChat/3.0-KLIPY\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
}

if (!$body) {
    chat_json(['ok' => false, 'error' => 'Non riesco a caricare le GIF ora.'], 502);
}

$json = json_decode($body, true);
if (!is_array($json)) {
    chat_json(['ok' => false, 'error' => 'Risposta KLIPY non valida.'], 502);
}

function chat_klipy_array_is_list(array $value): bool
{
    return $value === [] || array_keys($value) === range(0, count($value) - 1);
}

function chat_klipy_response_items(array $json): array
{
    if (isset($json['data']['data']) && is_array($json['data']['data'])) return $json['data']['data'];
    if (isset($json['data']) && is_array($json['data']) && chat_klipy_array_is_list($json['data'])) return $json['data'];
    if (isset($json['results']) && is_array($json['results'])) return $json['results'];
    return [];
}

function chat_klipy_collect_urls(mixed $value, string $path = ''): array
{
    $urls = [];
    if (is_string($value)) {
        $candidate = trim($value);
        if (preg_match('#^https://#i', $candidate)) {
            $urls[] = ['url' => $candidate, 'path' => strtolower($path)];
        }
        return $urls;
    }

    if (!is_array($value)) return $urls;

    foreach ($value as $key => $child) {
        $childPath = $path === '' ? (string)$key : $path . '.' . (string)$key;
        $urls = array_merge($urls, chat_klipy_collect_urls($child, $childPath));
    }

    return $urls;
}

function chat_klipy_pick_media_url(array $item, bool $preview = false): ?string
{
    $root = isset($item['files']) && is_array($item['files']) ? $item['files'] : $item;
    $candidates = chat_klipy_collect_urls($root);
    $ranked = [];

    foreach ($candidates as $candidate) {
        $url = $candidate['url'];
        $path = $candidate['path'];
        if (!chat_is_allowed_gif_url($url)) continue;

        $score = 0;
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
        $hasImageExtension = (bool)preg_match('/\.(gif|webp)(?:[?#]|$)/i', $url);
        if ($host === 'klipy.com' && !$hasImageExtension) continue;

        if (preg_match('/\.(gif)(?:[?#]|$)/i', $url)) $score += 70;
        if (preg_match('/\.(webp)(?:[?#]|$)/i', $url)) $score += 65;
        if (preg_match('/\.(mp4|webm)(?:[?#]|$)/i', $url)) $score -= 100;

        if ($preview) {
            if (preg_match('/(preview|thumb|small|tiny|low|webp|md|100|200)/i', $path)) $score += 25;
            if (preg_match('/(original|large|hd)/i', $path)) $score -= 10;
        } else {
            if (preg_match('/(original|gif|large|hd|source)/i', $path)) $score += 25;
            if (preg_match('/(preview|thumb|tiny)/i', $path)) $score -= 10;
        }

        $ranked[] = ['score' => $score, 'url' => $url];
    }

    if (!$ranked) return null;
    usort($ranked, fn($a, $b) => $b['score'] <=> $a['score']);
    return $ranked[0]['url'];
}

$items = [];
foreach (chat_klipy_response_items($json) as $item) {
    if (!is_array($item)) continue;

    $gif = chat_klipy_pick_media_url($item, false);
    $preview = chat_klipy_pick_media_url($item, true) ?: $gif;
    if (!$gif || !$preview) continue;

    $items[] = [
        'id' => (string)($item['id'] ?? $item['slug'] ?? ''),
        'title' => mb_substr((string)($item['title'] ?? $item['name'] ?? 'GIF'), 0, 120, 'UTF-8'),
        'url' => $gif,
        'preview_url' => $preview,
        'klipy_url' => (string)($item['url'] ?? $item['share_url'] ?? ''),
    ];
}

$pagination = (isset($json['data']) && is_array($json['data'])) ? $json['data'] : [];
$currentPage = (int)($pagination['current_page'] ?? $page);
$hasNext = array_key_exists('has_next', $pagination)
    ? (bool)$pagination['has_next']
    : count($items) >= $limit;
$next = $hasNext ? (string)($currentPage + 1) : '';

chat_json([
    'ok' => true,
    'gifs' => $items,
    'next' => $next,
]);
