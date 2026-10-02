<?php
// Il primo media di un post APPROVATO, senza sessione: è l'indirizzo che il
// sito passa a Discord per le immagini degli annunci, della coda di
// approvazione e delle segnalazioni.
//
// ?id=<post>&type=shitpost|rimasto [&v=poster]   poster = copertina di un video
require_once __DIR__ . '/../../includes/content_media_cache.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = content_media_cache_type(isset($_GET['type']) ? trim((string)$_GET['type']) : 'shitpost');
$poster = (string)($_GET['v'] ?? '') === 'poster';

if ($id <= 0) {
    http_response_code(400);
    exit('Missing or invalid ID.');
}

// Stessa copia su file della pagina: se c'è, il database non serve.
content_media_cache_serve($type, $id, 0, $poster ? 'poster' : '');

require_once __DIR__ . '/../../config/database.php';

$table = $type === 'rimasto' ? 'toprimasti' : 'shitposts';
$blobColumn = $type === 'rimasto' ? 'foto_rimasto' : 'foto_shitpost';
$mimeColumn = $type === 'rimasto' ? 'tipo_foto_rimasto' : 'tipo_foto_shitpost';

if ($poster) {
    $check = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE 'anteprima'");
    if (!$check || $check->num_rows === 0) {
        http_response_code(404);
        exit('No cover for this post.');
    }
    $blobColumn = 'anteprima';
}

$stmt = $mysqli->prepare("SELECT `$blobColumn` AS media_blob, `$mimeColumn` AS media_mime FROM `$table` WHERE id = ? AND approvato = 1 LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    exit('Database query preparation error.');
}

$stmt->bind_param('i', $id);
if (!$stmt->execute()) {
    http_response_code(500);
    exit('Query execution failed.');
}

$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    exit('Media not found or not approved.');
}

if (empty($row['media_blob'])) {
    http_response_code(404);
    exit('No media attachment on this post.');
}

$mime = $poster ? 'image/jpeg' : (string)$row['media_mime'];
if (!isset(CONTENT_MEDIA_CACHE_EXT[$mime])) {
    http_response_code(404);
    exit('Unsupported media type.');
}

content_media_cache_store($type, $id, $mime, (string)$row['media_blob'], 0, $poster ? 'poster' : '');
content_media_send($mime, null, (string)$row['media_blob']);
