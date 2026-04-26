<?php
require_once __DIR__ . '/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit;
}

$stmt = $mysqli->prepare("SELECT foto_shitpost, tipo_foto_shitpost FROM shitposts WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(404);
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['foto_shitpost'])) {
    http_response_code(404);
    exit;
}

$mime = $row['tipo_foto_shitpost'] ?: 'image/jpeg';
if (!preg_match('/^image\\/(jpeg|png|gif|webp)$/', $mime)) {
    $mime = 'application/octet-stream';
}

header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=3600');
echo $row['foto_shitpost'];
exit;
