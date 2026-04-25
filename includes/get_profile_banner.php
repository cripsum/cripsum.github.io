<?php
require_once __DIR__ . '/../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(404);
    exit;
}

$stmt = $mysqli->prepare("SELECT profile_banner, profile_banner_type FROM utenti WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($blob, $mime);
$stmt->fetch();
$stmt->close();

if (!$blob || !$mime) {
    http_response_code(404);
    exit;
}

$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(415);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($blob));
header('Cache-Control: public, max-age=86400');
echo $blob;
