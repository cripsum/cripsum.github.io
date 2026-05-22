<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';
require_once __DIR__ . '/../includes/profile_v3_helpers.php';

checkBan($mysqli);

if (!isLoggedIn()) {
    profile_json_response(['ok' => false, 'message' => 'Login required.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    profile_json_response(['ok' => false, 'message' => 'Invalid method.'], 405);
}

if (!profile_validate_csrf($_POST['csrf_token'] ?? null)) {
    profile_json_response(['ok' => false, 'message' => 'Session expired. Reload the editor.'], 403);
}

$userId = (int)$_SESSION['user_id'];
if (!profile_v3_rate_limit($mysqli, 'upload_media', 24, 3600, $userId)) {
    profile_json_response(['ok' => false, 'message' => 'Upload limit reached. Try later.'], 429);
}

$file = $_FILES['media'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    profile_json_response(['ok' => false, 'message' => 'No file selected.'], 400);
}

if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    profile_json_response(['ok' => false, 'message' => 'Upload failed.'], 400);
}

$tmp = (string)($file['tmp_name'] ?? '');
if (!is_uploaded_file($tmp)) {
    profile_json_response(['ok' => false, 'message' => 'Invalid uploaded file.'], 422);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($tmp) ?: '';
$name = strtolower((string)($file['name'] ?? 'media'));
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$size = (int)($file['size'] ?? 0);
$imageInfo = @getimagesize($tmp);
if ($imageInfo && !empty($imageInfo['mime'])) $mime = $imageInfo['mime'];

$allowed = [
    'image/jpeg' => ['type' => 'image', 'ext' => 'jpg', 'max' => 5 * 1024 * 1024],
    'image/png' => ['type' => 'image', 'ext' => 'png', 'max' => 5 * 1024 * 1024],
    'image/webp' => ['type' => 'image', 'ext' => 'webp', 'max' => 5 * 1024 * 1024],
    'image/gif' => ['type' => 'image', 'ext' => 'gif', 'max' => 8 * 1024 * 1024],
    'video/mp4' => ['type' => 'video', 'ext' => 'mp4', 'max' => 24 * 1024 * 1024],
    'video/webm' => ['type' => 'video', 'ext' => 'webm', 'max' => 24 * 1024 * 1024],
    'audio/mpeg' => ['type' => 'audio', 'ext' => 'mp3', 'max' => 16 * 1024 * 1024],
];

if (!isset($allowed[$mime])) {
    profile_json_response(['ok' => false, 'message' => 'Unsupported media type.'], 422);
}

$rule = $allowed[$mime];
if ($size <= 0 || $size > $rule['max']) {
    profile_json_response(['ok' => false, 'message' => 'File too large.'], 422);
}

if ($rule['type'] === 'video' && !in_array($ext, ['mp4', 'webm'], true)) {
    profile_json_response(['ok' => false, 'message' => 'Video extension mismatch.'], 422);
}

if ($rule['type'] === 'audio' && $ext !== 'mp3') {
    profile_json_response(['ok' => false, 'message' => 'Only MP3 audio is supported.'], 422);
}

$uploadRoot = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
$targetDir = $uploadRoot . DIRECTORY_SEPARATOR . 'profile_media' . DIRECTORY_SEPARATOR . $userId;
if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
    profile_json_response(['ok' => false, 'message' => 'Cannot create media directory.'], 500);
}

$resolvedDir = realpath($targetDir);
$uploadsBase = realpath(__DIR__ . '/../uploads');
if (!$resolvedDir || !$uploadsBase || !str_starts_with($resolvedDir, $uploadsBase)) {
    profile_json_response(['ok' => false, 'message' => 'Invalid media path.'], 500);
}

$filename = bin2hex(random_bytes(16)) . '.' . $rule['ext'];
$targetPath = $resolvedDir . DIRECTORY_SEPARATOR . $filename;
if (!move_uploaded_file($tmp, $targetPath)) {
    profile_json_response(['ok' => false, 'message' => 'Could not save media.'], 500);
}

@chmod($targetPath, 0644);
$publicUrl = '/uploads/profile_media/' . $userId . '/' . $filename;
$width = $imageInfo[0] ?? null;
$height = $imageInfo[1] ?? null;

if (profile_v3_table_exists($mysqli, 'profile_media')) {
    $stmt = $mysqli->prepare('INSERT INTO profile_media (utente_id, media_type, storage_path, public_url, mime_type, file_size, width, height, alt_text, alt_text_en, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    if ($stmt) {
        $storagePath = 'uploads/profile_media/' . $userId . '/' . $filename;
        $alt = profile_clean_text($_POST['alt_text'] ?? '', 160) ?: null;
        $altEn = profile_clean_text($_POST['alt_text_en'] ?? '', 160) ?: null;
        $stmt->bind_param('issssiiiss', $userId, $rule['type'], $storagePath, $publicUrl, $mime, $size, $width, $height, $alt, $altEn);
        $stmt->execute();
        $mediaId = $mysqli->insert_id;
        $stmt->close();
    }
}

profile_json_response([
    'ok' => true,
    'media' => [
        'id' => isset($mediaId) ? (int)$mediaId : null,
        'url' => $publicUrl,
        'type' => $rule['type'],
        'mime' => $mime,
        'size' => $size,
        'width' => $width,
        'height' => $height,
    ],
]);
