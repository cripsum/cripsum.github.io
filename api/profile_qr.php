<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

$url = trim((string)($_GET['url'] ?? ''));
$host = parse_url($url, PHP_URL_HOST);

if (!profile_is_safe_url($url, true) || !in_array($host, ['cripsum.com', 'www.cripsum.com'], true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'URL non valido';
    exit;
}

$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=12&data=' . rawurlencode($url);
header('Location: ' . $qrUrl, true, 302);
exit;
