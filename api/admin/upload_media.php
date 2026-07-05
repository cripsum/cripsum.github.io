<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_fail('Metodo non consentito.', 405);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    admin_fail('Errore durante il caricamento del file (Codice: ' . $errCode . ').');
}

$file = $_FILES['file'];
$type = trim((string)($_POST['type'] ?? ''));

if ($type !== 'image' && $type !== 'audio' && $type !== 'video') {
    admin_fail('Tipo di media non specificato o non valido.');
}

if ($type === 'image') {
    $maxSize = 10 * 1024 * 1024;
} elseif ($type === 'audio') {
    $maxSize = 35 * 1024 * 1024;
} else {
    $maxSize = 100 * 1024 * 1024;
}

if ($file['size'] <= 0 || $file['size'] > $maxSize) {
    admin_fail('Il file supera la dimensione massima consentita.');
}

$allowedExtensions = [];
$targetDir = '';

if ($type === 'image') {
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $targetDir = __DIR__ . '/../../img/';
} elseif ($type === 'audio') {
    $allowedExtensions = ['mp3', 'wav', 'ogg', 'm4a', 'aac'];
    $targetDir = __DIR__ . '/../../audio/';
} else {
    $allowedExtensions = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'];
    $targetDir = __DIR__ . '/../../vid/';
}

$origName = basename($file['name']);
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExtensions, true)) {
    admin_fail('Estensione file non consentita.');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if ($type === 'image') {
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mimeType, $allowedMimes, true)) {
        admin_fail('Tipo MIME dell\'immagine non valido.');
    }
} elseif ($type === 'audio') {
    $isAudioMime = str_starts_with($mimeType, 'audio/') || $mimeType === 'application/octet-stream' || $mimeType === 'application/x-zip-compressed';
    if (!$isAudioMime) {
        admin_fail('Tipo MIME audio non valido.');
    }
} else {
    $isVideoMime = str_starts_with($mimeType, 'video/') || $mimeType === 'application/octet-stream';
    if (!$isVideoMime) {
        admin_fail('Tipo MIME video non valido.');
    }
}

$sanitizedName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', pathinfo($origName, PATHINFO_FILENAME));
$sanitizedName = substr($sanitizedName, 0, 100);
if ($sanitizedName === '') {
    $sanitizedName = 'media_' . time();
}

$finalFilename = time() . '_' . $sanitizedName . '.' . $ext;
$targetPath = $targetDir . $finalFilename;

if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0755, true)) {
        admin_fail('Impossibile creare la cartella di destinazione sul server.', 500);
    }
}

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    admin_fail('Errore nel salvare il file sul server.', 500);
}

admin_log($mysqli, (int)$adminUser['id'], 'upload_media', null, [
    'filename' => $finalFilename,
    'type' => $type
]);

admin_ok([
    'message' => 'File caricato con successo.',
    'filename' => $finalFilename
]);
