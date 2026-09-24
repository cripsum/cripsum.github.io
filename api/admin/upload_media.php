<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Le immagini del pannello vanno in una sottocartella di img/ secondo da
 * dove si caricano (POST folder): negozio, merch/{collezione}, download,
 * gacha, personaggi. Senza folder finiscono in img/ come prima.
 *
 * Il primo livello e' una lista chiusa (ADMIN_MEDIA_FOLDERS, in
 * admin_media_helpers.php): img/ ha gia' cartelle con un significato
 * (badges, rewind, cripsumpedia) in cui il pannello non deve scrivere.
 */

function admin_upload_folder(string $folder): string
{
    $folder = trim($folder, " /");
    if ($folder === '') {
        return '';
    }

    $segments = explode('/', $folder);
    if (count($segments) > 2 || !in_array($segments[0], ADMIN_MEDIA_FOLDERS, true)) {
        admin_fail('Cartella di destinazione non valida.');
    }

    foreach ($segments as $segment) {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,58}[a-z0-9])?$/', $segment)) {
            admin_fail('Cartella di destinazione non valida.');
        }
    }

    return implode('/', $segments) . '/';
}

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

$subFolder = '';

if ($type === 'image') {
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $subFolder = admin_upload_folder((string)($_POST['folder'] ?? ''));
    $targetDir = __DIR__ . '/../../img/' . $subFolder;
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
    'filename' => $subFolder . $finalFilename,
    'type' => $type
]);

// filename resta relativo alla cartella del tipo (img/, audio/, vid/), come
// prima: i personaggi lo salvano cosi' e le pagine ci mettono davanti /img/.
$baseUrl = ['image' => '/img/', 'audio' => '/audio/', 'video' => '/vid/'][$type];

admin_ok([
    'message' => 'File caricato con successo.',
    'filename' => $subFolder . $finalFilename,
    'url' => $baseUrl . $subFolder . $finalFilename,
]);
