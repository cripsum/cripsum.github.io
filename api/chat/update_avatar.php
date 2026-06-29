<?php
// api/chat/update_avatar.php
// Handles group avatar upload.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

$chatId = isset($_POST['chat_id']) ? (int)$_POST['chat_id'] : 0;

if (!$chatId) {
    send_error("ID chat mancante.");
}

if (!canManageChat($mysqli, $chatId, $userId)) {
    send_error("Non hai i permessi per modificare l'avatar di questo gruppo.", 403);
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    send_error("Nessun file caricato o errore di upload.");
}

$file = $_FILES['avatar'];
$tempPath = $file['tmp_name'];
$originalName = basename($file['name']);
$fileSize = $file['size'];

// Max size 5MB
if ($fileSize > 5 * 1024 * 1024) {
    send_error("L'immagine non può superare i 5MB.");
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $tempPath);
finfo_close($finfo);

if (!str_starts_with($mimeType, 'image/')) {
    send_error("Il file caricato non è un'immagine valida.");
}

$extension = pathinfo($originalName, PATHINFO_EXTENSION);
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
if (!in_array(strtolower($extension), $allowedExtensions, true)) {
    send_error("Estensione dell'immagine non consentita.");
}

$fileName = 'group_' . $chatId . '_' . time() . '_' . uniqid() . '.' . $extension;
$uploadDir = __DIR__ . '/../../uploads/chat_avatars/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$destPath = $uploadDir . $fileName;
$relativePath = '/uploads/chat_avatars/' . $fileName;

if (!move_uploaded_file($tempPath, $destPath)) {
    send_error("Impossibile salvare l'immagine sul server.");
}

$mysqli->begin_transaction();

try {
    // 1. Update DB
    $stmt = $mysqli->prepare("UPDATE chats SET avatar_url = ? WHERE id = ?");
    if (!$stmt) throw new Exception("Errore interno.");
    $stmt->bind_param("si", $relativePath, $chatId);
    $stmt->execute();
    $stmt->close();
    
    // 2. System message
    $stmtUser = $mysqli->prepare("SELECT username FROM utenti WHERE id = ? LIMIT 1");
    if ($stmtUser) {
        $stmtUser->bind_param("i", $userId);
        $stmtUser->execute();
        $username = $stmtUser->get_result()->fetch_assoc()['username'] ?? 'Utente';
        $stmtUser->close();
    } else {
        $username = 'Utente';
    }
    
    createSystemMessage($mysqli, $chatId, 'avatar', [
        'username' => $username
    ]);
    
    $mysqli->commit();
    
    send_success([
        'avatar_url' => $relativePath,
        'message' => "Immagine del gruppo aggiornata."
    ]);

} catch (Throwable $e) {
    $mysqli->rollback();
    @unlink($destPath);
    send_error($e->getMessage());
}
?>
