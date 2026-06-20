<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'message' => 'Devi essere loggato per caricare file.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Get user profile to check premium status
$profile = profile_get_edit_profile($mysqli, $userId);
if (!$profile || (int)($profile['is_premium'] ?? 0) !== 1) {
    echo json_encode(['ok' => false, 'message' => 'Questa funzionalità richiede un account Premium.']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['ok' => false, 'message' => 'Nessun file ricevuto o errore di caricamento (Codice: ' . $errCode . ').']);
    exit;
}

$file = $_FILES['file'];
$maxBytes = 25 * 1024 * 1024; // 25 MB

if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
    echo json_encode(['ok' => false, 'message' => 'Il file è troppo pesante. Il limite massimo è 25MB.']);
    exit;
}

$tmpPath = $file['tmp_name'];
if (!is_uploaded_file($tmpPath)) {
    echo json_encode(['ok' => false, 'message' => 'File non valido.']);
    exit;
}

// Check mime type / extension
$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/jpg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'image/svg+xml' => 'svg'
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $tmpPath);
finfo_close($finfo);

// SVG fallback check (sometimes mime comes out as text/plain or text/xml for SVGs depending on OS configuration)
$ext = '';
if ($mimeType === 'text/plain' || $mimeType === 'text/xml' || $mimeType === 'image/svg') {
    $origName = strtolower($file['name']);
    if (str_ends_with($origName, '.svg')) {
        $mimeType = 'image/svg+xml';
    }
}

if (!array_key_exists($mimeType, $allowedMimes)) {
    echo json_encode(['ok' => false, 'message' => 'Formato file non supportato. Formati validi: JPG, PNG, WEBP, GIF, SVG. Mime rilevato: ' . $mimeType]);
    exit;
}

$ext = $allowedMimes[$mimeType];

// Create target directory
$uploadDir = __DIR__ . '/../uploads/profile_media/user_' . $userId;
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        echo json_encode(['ok' => false, 'message' => 'Impossibile creare la cartella di destinazione. Contattare l\'amministratore.']);
        exit;
    }
}

// Generate randomized secure filename
$randomHash = bin2hex(random_bytes(16));
$fileName = 'media_' . $randomHash . '.' . $ext;
$targetPath = $uploadDir . '/' . $fileName;

if (move_uploaded_file($tmpPath, $targetPath)) {
    // Return relative URL that starts with /uploads/profile_media/
    $relativeUrl = '/uploads/profile_media/user_' . $userId . '/' . $fileName;
    echo json_encode(['ok' => true, 'url' => $relativeUrl]);
} else {
    echo json_encode(['ok' => false, 'message' => 'Impossibile salvare il file sul server.']);
}
