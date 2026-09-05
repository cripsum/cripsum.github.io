<?php
// Streams the data export belonging to the CURRENT session user.
//
// Security model: the archive to serve is resolved from $_SESSION['user_id']
// alone. No user id, no file name and no path is ever accepted from the
// request, so there is nothing to tamper with — an attacker with a valid
// session can only ever download their own data.
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/account_data_helpers.php';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    http_response_code(405);
    exit('Metodo non consentito.');
}

if (!isLoggedIn() || empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Devi essere loggato.');
}

$userId = (int)$_SESSION['user_id'];
if ($userId <= 0) {
    http_response_code(401);
    exit('Sessione non valida.');
}

if (!account_ensure_schema($mysqli)) {
    http_response_code(503);
    exit('Funzionalità non disponibile.');
}

$export = account_latest_export($mysqli, $userId);
if (!$export) {
    http_response_code(404);
    exit('Nessuna esportazione disponibile. Richiedine una dalle impostazioni.');
}

// basename() strips any directory component that could have ended up stored,
// and the realpath check below guarantees the result really is inside the
// export folder even if the row were somehow tampered with.
$dir = realpath(account_export_dir());
$path = $dir !== false ? realpath($dir . '/' . basename((string)$export['file_name'])) : false;

if ($dir === false || $path === false || !is_file($path)) {
    http_response_code(404);
    exit('Archivio non più disponibile.');
}

$normalizedDir = rtrim(str_replace('\\', '/', $dir), '/') . '/';
if (!str_starts_with(str_replace('\\', '/', $path), $normalizedDir)) {
    http_response_code(403);
    exit('Accesso negato.');
}

$stmt = $mysqli->prepare("UPDATE `user_data_exports` SET download_count = download_count + 1 WHERE id = ? AND utente_id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('ii', $export['id'], $userId);
    $stmt->execute();
    $stmt->close();
}

// The visible name is built here, never taken from storage.
$downloadName = 'cripsum-dati-' . date('Y-m-d', strtotime((string)$export['requested_at'])) . '.zip';

cripsum_release_session();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($path));

if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}

readfile($path);
