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
$stmt->bind_result($bannerValue, $mime);
$stmt->fetch();
$stmt->close();

if (!$bannerValue || !$mime) {
    http_response_code(404);
    exit;
}

$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(415);
    exit;
}

if (str_starts_with($bannerValue, '/uploads/')) {
    $filePath = __DIR__ . '/..' . $bannerValue;
    if (file_exists($filePath)) {
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: public, max-age=86400');
        readfile($filePath);
        exit;
    } else {
        http_response_code(404);
        exit;
    }
} else {
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($bannerValue));
    header('Cache-Control: public, max-age=86400');
    echo $bannerValue;
}
