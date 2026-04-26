<?php
require_once __DIR__ . '/bootstrap.php';
chat_require_login_json($mysqli);

$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 60, 'UTF-8');
$offset = max(0, min(4999, (int)($_GET['pos'] ?? 0)));

if (!defined('GIPHY_API_KEY') || GIPHY_API_KEY === '') {
    chat_json([
        'ok' => false,
        'error' => 'GIPHY non configurato. Inserisci GIPHY_API_KEY in /config/giphy_config.php.'
    ], 503);
}

$endpoint = $q === '' ? 'https://api.giphy.com/v1/gifs/trending' : 'https://api.giphy.com/v1/gifs/search';
$params = [
    'api_key' => GIPHY_API_KEY,
    'limit' => (int)GIPHY_LIMIT,
    'offset' => $offset,
    'rating' => GIPHY_RATING,
    'lang' => GIPHY_LANG,
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
        CURLOPT_USERAGENT => 'CripsumGlobalChat/2.2-GIPHY',
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300) $body = null;
} else {
    $ctx = stream_context_create(['http' => ['timeout' => 7, 'header' => "User-Agent: CripsumGlobalChat/2.2-GIPHY\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
}

if (!$body) {
    chat_json(['ok' => false, 'error' => 'Non riesco a caricare le GIF ora.'], 502);
}

$json = json_decode($body, true);
if (!is_array($json)) {
    chat_json(['ok' => false, 'error' => 'Risposta GIPHY non valida.'], 502);
}

$items = [];
foreach (($json['data'] ?? []) as $item) {
    $images = $item['images'] ?? [];

    $gif = $images['downsized']['url']
        ?? $images['fixed_height']['url']
        ?? $images['fixed_width']['url']
        ?? $images['original']['url']
        ?? null;

    $preview = $images['fixed_width_small']['url']
        ?? $images['fixed_height_small']['url']
        ?? $images['preview_gif']['url']
        ?? $gif;

    if (!$gif || !$preview) continue;
    if (!chat_is_allowed_gif_url($gif) || !chat_is_allowed_gif_url($preview)) continue;

    $items[] = [
        'id' => (string)($item['id'] ?? ''),
        'title' => mb_substr((string)($item['title'] ?? 'GIF'), 0, 120, 'UTF-8'),
        'url' => $gif,
        'preview_url' => $preview,
        'giphy_url' => (string)($item['url'] ?? ''),
    ];
}

$pagination = $json['pagination'] ?? [];
$count = (int)($pagination['count'] ?? count($items));
$total = (int)($pagination['total_count'] ?? 0);
$currentOffset = (int)($pagination['offset'] ?? $offset);
$nextOffset = $currentOffset + max(0, $count);
$next = ($count > 0 && ($total === 0 || $nextOffset < $total)) ? (string)$nextOffset : '';

chat_json([
    'ok' => true,
    'gifs' => $items,
    'next' => $next,
]);
