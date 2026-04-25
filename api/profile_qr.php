<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

$url = trim((string)($_GET['url'] ?? ''));
$host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));

if (!profile_is_safe_url($url, true) || !in_array($host, ['cripsum.com', 'www.cripsum.com'], true)) {
    http_response_code(400);
    header('Content-Type: image/svg+xml; charset=utf-8');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="280" height="280"><rect width="100%" height="100%" fill="#111827"/><text x="50%" y="50%" text-anchor="middle" fill="#fff" font-size="16">QR non valido</text></svg>';
    exit;
}

$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&margin=12&data=' . rawurlencode($url);
$image = false;

if (function_exists('curl_init')) {
    $ch = curl_init($qrUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'CripsumProfileQR/1.0',
    ]);
    $image = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300 || !$image) {
        $image = false;
    }
}

if ($image === false && ini_get('allow_url_fopen')) {
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'header' => "User-Agent: CripsumProfileQR/1.0\r\n"]]);
    $image = @file_get_contents($qrUrl, false, $ctx);
}

if ($image !== false && strlen($image) > 200) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=3600');
    echo $image;
    exit;
}

// Fallback: mostra un box pulito invece di rompere la UI.
header('Content-Type: image/svg+xml; charset=utf-8');
$safeText = htmlspecialchars('QR non disponibile', ENT_QUOTES, 'UTF-8');
echo '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="320" viewBox="0 0 320 320"><rect width="320" height="320" rx="28" fill="#0b1020"/><rect x="28" y="28" width="264" height="264" rx="18" fill="none" stroke="#ffffff" stroke-opacity=".22" stroke-width="2"/><text x="160" y="148" text-anchor="middle" fill="#ffffff" font-family="Arial" font-size="18" font-weight="700">' . $safeText . '</text><text x="160" y="176" text-anchor="middle" fill="#a8b0c7" font-family="Arial" font-size="12">Copia il link profilo</text></svg>';
