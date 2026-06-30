<?php
require_once __DIR__ . '/../../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? trim((string)$_GET['type']) : 'shitpost';

if ($id <= 0) {
    http_response_code(400);
    exit('Missing or invalid ID.');
}

if ($type === 'rimasto') {
    $stmt = $mysqli->prepare("SELECT foto_rimasto, tipo_foto_rimasto FROM toprimasti WHERE id = ? AND approvato = 1 LIMIT 1");
} else {
    $stmt = $mysqli->prepare("SELECT foto_shitpost, tipo_foto_shitpost FROM shitposts WHERE id = ? AND approvato = 1 LIMIT 1");
}

if (!$stmt) {
    http_response_code(500);
    exit('Database query preparation error.');
}

$stmt->bind_param('i', $id);
if (!$stmt->execute()) {
    http_response_code(500);
    exit('Query execution failed.');
}

$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    exit('Media not found or not approved.');
}

$blob = ($type === 'rimasto') ? $row['foto_rimasto'] : $row['foto_shitpost'];
$mime = ($type === 'rimasto') ? $row['tipo_foto_rimasto'] : $row['tipo_foto_shitpost'];

if (empty($blob)) {
    http_response_code(404);
    exit('No media attachment on this post.');
}

$mime = $mime ?: 'image/jpeg';
header("Content-Type: $mime");
header("Cache-Control: public, max-age=31536000"); // cache for 1 year
header("Content-Length: " . strlen($blob));
echo $blob;
exit;
