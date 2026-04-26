<?php
require_once __DIR__ . '/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit;
}

$stmt = $mysqli->prepare("SELECT foto_rimasto, tipo_foto_rimasto FROM toprimasti WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(404);
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['foto_rimasto'])) {
    http_response_code(404);
    exit;
}

$mime = $row['tipo_foto_rimasto'] ?: 'image/jpeg';
if (!preg_match('/^image\\/(jpeg|png|gif|webp)$/', $mime)) {
    $mime = 'application/octet-stream';
}

header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=3600');
echo $row['foto_rimasto'];
exit;
