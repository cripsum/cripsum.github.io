<?php
// Serves a user's profile background (image or video).
require_once __DIR__ . '/../config/database.php';

// Streaming media must not hold the session lock: it would block every other
// request the same visitor makes in parallel.
cripsum_release_session();

const PROFILE_BANNER_ALLOWED_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'image/avif' => 'avif',
    'video/mp4' => 'mp4',
    'video/webm' => 'webm',
];

const PROFILE_BANNER_EXT_MIMES = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
    'avif' => 'image/avif',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
];

/**
 * Maps a stored `/uploads/...` value to an absolute path, refusing anything
 * that escapes the uploads directory.
 */
function profile_banner_resolve_path(string $relative): ?string
{
    if (!str_starts_with($relative, '/uploads/')) {
        return null;
    }
    if (str_contains($relative, "\0") || str_contains($relative, '..')) {
        return null;
    }

    $uploadsRoot = realpath(__DIR__ . '/../uploads');
    if ($uploadsRoot === false) {
        return null;
    }

    $candidate = realpath(__DIR__ . '/..' . $relative);
    if ($candidate === false || !is_file($candidate)) {
        return null;
    }

    $normalizedRoot = rtrim(str_replace('\\', '/', $uploadsRoot), '/') . '/';
    $normalizedFile = str_replace('\\', '/', $candidate);

    return str_starts_with($normalizedFile, $normalizedRoot) ? $candidate : null;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(404);
    exit;
}

$stmt = $mysqli->prepare("SELECT profile_banner, profile_banner_type FROM utenti WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(404);
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($bannerValue, $mime);
$stmt->fetch();
$stmt->close();

$bannerValue = (string)($bannerValue ?? '');
if ($bannerValue === '') {
    http_response_code(404);
    exit;
}

$mime = strtolower(trim((string)$mime));

if (str_starts_with($bannerValue, '/uploads/')) {
    $filePath = profile_banner_resolve_path($bannerValue);
    if ($filePath === null) {
        http_response_code(404);
        exit;
    }

    // Recover the mime from the extension when the stored value is missing or
    // no longer in the allow list, so an old row does not break the background.
    if (!isset(PROFILE_BANNER_ALLOWED_MIMES[$mime])) {
        $ext = strtolower((string)pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = PROFILE_BANNER_EXT_MIMES[$ext] ?? '';
    }
    if (!isset(PROFILE_BANNER_ALLOWED_MIMES[$mime])) {
        http_response_code(415);
        exit;
    }

    $mtime = @filemtime($filePath) ?: time();
    $etag = '"' . md5($filePath . '|' . $mtime . '|' . filesize($filePath)) . '"';

    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=86400');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('ETag: ' . $etag);

    $ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
        http_response_code(304);
        exit;
    }

    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

// Legacy binary blob stored directly in the column.
if (!isset(PROFILE_BANNER_ALLOWED_MIMES[$mime])) {
    http_response_code(415);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($bannerValue));
header('Cache-Control: public, max-age=86400');
echo $bannerValue;
