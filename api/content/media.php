<?php
require_once __DIR__ . '/bootstrap.php';

$type = cv2_normalize_type((string)($_GET['type'] ?? 'shitpost'));
$id = (int)($_GET['id'] ?? 0);
$meta = cv2_meta($type);

if ($id <= 0 || !cv2_table_exists($mysqli, $meta['table'])) {
    http_response_code(404);
    exit;
}

$table = cv2_qcol($meta['table']);
$blob = cv2_qcol($meta['blob']);
$mime = cv2_qcol($meta['mime']);
$approved = cv2_qcol($meta['approved']);
$userCol = cv2_qcol($meta['user']);

$stmt = $mysqli->prepare("SELECT $blob AS media_blob, $mime AS media_mime, $approved AS approvato, $userCol AS id_utente FROM $table WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(404);
    exit;
}

$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['media_blob'])) {
    http_response_code(404);
    exit;
}

$canSee = (int)$row['approvato'] === 1;
if (!$canSee && $currentUser) {
    $canSee = cv2_is_admin($currentUser) || (int)$row['id_utente'] === (int)$currentUser['id'];
}

if (!$canSee) {
    http_response_code(403);
    exit;
}

$mimeValue = (string)($row['media_mime'] ?: 'application/octet-stream');
if (!preg_match('/^(image\/(jpeg|png|gif|webp)|video\/(mp4|webm))$/', $mimeValue)) {
    $mimeValue = 'application/octet-stream';
}

header('Content-Type: ' . $mimeValue);
header('Cache-Control: private, max-age=3600');
echo $row['media_blob'];
exit;
