<?php
require_once __DIR__ . '/bootstrap.php';
chat_require_login_json($mysqli);

$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 60, 'UTF-8');
$pos = mb_substr(trim((string)($_GET['pos'] ?? '')), 0, 120, 'UTF-8');

if (!defined('TENOR_API_KEY') || TENOR_API_KEY === '') {
    chat_json([
        'ok' => false,
        'error' => 'Tenor non configurato. Inserisci TENOR_API_KEY in /config/tenor_config.php.'
    ], 503);
}

$endpoint = $q === '' ? 'https://tenor.googleapis.com/v2/featured' : 'https://tenor.googleapis.com/v2/search';
$params = [
    'key' => TENOR_API_KEY,
    'client_key' => TENOR_CLIENT_KEY,
    'limit' => (int)TENOR_LIMIT,
    'media_filter' => 'tinygif,gif',
    'contentfilter' => TENOR_CONTENT_FILTER,
    'locale' => TENOR_LOCALE,
    'country' => TENOR_COUNTRY,
];
if ($q !== '') $params['q'] = $q;
if ($pos !== '') $params['pos'] = $pos;

$url = $endpoint . '?' . http_build_query($params);
$body = null;

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 7,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'CripsumGlobalChat/2.1',
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300) $body = null;
} else {
    $ctx = stream_context_create(['http' => ['timeout' => 7, 'header' => "User-Agent: CripsumGlobalChat/2.1\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
}

if (!$body) {
    chat_json(['ok' => false, 'error' => 'Non riesco a caricare le GIF ora.'], 502);
}

$json = json_decode($body, true);
if (!is_array($json)) {
    chat_json(['ok' => false, 'error' => 'Risposta Tenor non valida.'], 502);
}

$items = [];
foreach (($json['results'] ?? []) as $item) {
    $formats = $item['media_formats'] ?? [];
    $tiny = $formats['tinygif']['url'] ?? null;
    $gif = $formats['gif']['url'] ?? $tiny;
    if (!$gif || !$tiny) continue;
    if (!chat_is_allowed_gif_url($gif) || !chat_is_allowed_gif_url($tiny)) continue;

    $items[] = [
        'id' => (string)($item['id'] ?? ''),
        'title' => mb_substr((string)($item['content_description'] ?? 'GIF'), 0, 120, 'UTF-8'),
        'url' => $gif,
        'preview_url' => $tiny,
        'tenor_url' => (string)($item['itemurl'] ?? ''),
    ];
}

chat_json([
    'ok' => true,
    'gifs' => $items,
    'next' => (string)($json['next'] ?? ''),
]);
