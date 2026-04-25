<?php
require_once __DIR__ . '/../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(404);
    exit;
}

$stmt = $mysqli->prepare("SELECT profile_music_blob, profile_music_mime FROM utenti WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['profile_music_blob']) || empty($row['profile_music_mime'])) {
    http_response_code(404);
    exit;
}

$audio = $row['profile_music_blob'];
$mime = $row['profile_music_mime'] ?: 'audio/mpeg';
$length = strlen($audio);
$start = 0;
$end = $length - 1;
$status = 200;

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');

if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
    if ($matches[1] !== '') $start = (int)$matches[1];
    if ($matches[2] !== '') $end = (int)$matches[2];
    if ($start > $end || $start >= $length) {
        header('Content-Range: bytes */' . $length, true, 416);
        exit;
    }
    $end = min($end, $length - 1);
    $status = 206;
}

$chunkLength = $end - $start + 1;
http_response_code($status);
if ($status === 206) {
    header("Content-Range: bytes $start-$end/$length");
}
header('Content-Length: ' . $chunkLength);

echo substr($audio, $start, $chunkLength);
