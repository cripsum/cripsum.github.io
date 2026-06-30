<?php
// api/chat/upload_media.php
// Dual endpoint: Handles media/file uploads for both private conversations and group chats.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

$conversationId = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : 0;
$chatId = isset($_POST['chat_id']) ? (int)$_POST['chat_id'] : 0;
$replyToId = isset($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;
$ephemeralTimer = isset($_POST['ephemeral_timer']) ? (int)$_POST['ephemeral_timer'] : 0;

if (!$conversationId && !$chatId) {
    send_error("ID conversazione o ID chat mancante.");
}

// 1. Verifica permessi
if ($chatId > 0) {
    if (!canSendMessage($mysqli, $chatId, $userId)) {
        send_error("Non sei autorizzato ad allegare file in questo gruppo.", 403);
    }
} else {
    $stmtCheck = $mysqli->prepare("SELECT id FROM private_conversation_participants WHERE conversation_id = ? AND user_id = ? LIMIT 1");
    $stmtCheck->bind_param("ii", $conversationId, $userId);
    $stmtCheck->execute();
    $isPart = $stmtCheck->get_result()->num_rows > 0;
    $stmtCheck->close();
    
    if (!$isPart) {
        send_error("Non sei autorizzato ad allegare file in questa conversazione.", 403);
    }
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    send_error("Nessun file caricato o si è verificato un errore durante l'upload.");
}

$file = $_FILES['file'];
$tempPath = $file['tmp_name'];
$originalName = basename($file['name']);
$fileSize = $file['size'];

// Validazione dimensione file
$maxImageSize = 20 * 1024 * 1024; // 20MB per immagini, sticker e audio
$maxFileSize = 50 * 1024 * 1024;  // 50MB per video e documenti generici

// Controllo reale del tipo MIME tramite finfo
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $tempPath);
finfo_close($finfo);

// Mappatura tipo MIME a file_type ENUM
$fileType = 'file';
if (str_starts_with($mimeType, 'image/')) {
    $fileType = 'image';
    if (isset($_POST['is_sticker']) && (int)$_POST['is_sticker'] === 1) {
        $fileType = 'sticker';
    }
} elseif (str_starts_with($mimeType, 'video/')) {
    $fileType = 'video';
} elseif (str_starts_with($mimeType, 'audio/')) {
    $fileType = 'audio';
}

// Applica limiti di dimensione in base al tipo
if (($fileType === 'image' || $fileType === 'audio' || $fileType === 'sticker') && $fileSize > $maxImageSize) {
    send_error("Il file supera la dimensione massima consentita per questa tipologia (20MB).");
} elseif ($fileSize > $maxFileSize) {
    send_error("Il file supera la dimensione massima consentita (50MB).");
}

// Sanitizzazione del nome del file
$extension = pathinfo($originalName, PATHINFO_EXTENSION);
$dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'jar'];
if (in_array(strtolower($extension), $dangerousExtensions, true)) {
    send_error("Estensione del file non consentita per motivi di sicurezza.");
}

// Generiamo un nome unico ed evitiamo collisioni
$safeName = preg_replace("/[^a-zA-Z0-9_\.-]/", "", pathinfo($originalName, PATHINFO_FILENAME));
$fileName = time() . '_' . uniqid() . '_' . $safeName . '.' . $extension;

// Creazione directory di upload
$uploadDir = __DIR__ . '/../../uploads/chat/' . date('Y/m/');
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$destPath = $uploadDir . $fileName;
$relativePath = '/uploads/chat/' . date('Y/m/') . $fileName;

// Spostamento del file caricato
if (!move_uploaded_file($tempPath, $destPath)) {
    send_error("Impossibile salvare il file caricato sul server.");
}

$mysqli->begin_transaction();

try {
    if ($chatId > 0) {
        // --- LOGICA GRUPPO ---
        $attachmentData = [
            'attachments' => [[
                'file_name' => $originalName,
                'file_path' => $relativePath,
                'file_size' => $fileSize,
                'file_mime' => $mimeType,
                'file_type' => $fileType
            ]]
        ];
        $metaJson = json_encode($attachmentData);
        $messageType = 'media';
        
        $stmtMsg = $mysqli->prepare("
            INSERT INTO chat_messages (chat_id, sender_id, body, message_type, reply_to_message_id, metadata_json)
            VALUES (?, ?, NULL, ?, ?, ?)
        ");
        $stmtMsg->bind_param("iisis", $chatId, $userId, $messageType, $replyToId, $metaJson);
        $stmtMsg->execute();
        $messageId = $mysqli->insert_id;
        $stmtMsg->close();
        
        // Aggiorniamo la conversazione
        $mysqli->query("UPDATE chats SET last_message_id = $messageId, last_message_at = NOW() WHERE id = $chatId");
        $mysqli->query("UPDATE chat_members SET is_archived = 0 WHERE chat_id = $chatId");
        
        $mysqli->commit();
        
        // Seleziona il messaggio appena creato
        $stmtSelect = $mysqli->prepare("
            SELECT m.id, m.chat_id, m.sender_id, u.username as sender_username, u.display_name as sender_display_name,
                   m.body, m.message_type, m.reply_to_message_id, m.metadata_json, m.created_at
            FROM chat_messages m
            INNER JOIN utenti u ON u.id = m.sender_id
            WHERE m.id = ? LIMIT 1
        ");
        $stmtSelect->bind_param("i", $messageId);
        $stmtSelect->execute();
        $newMsg = $stmtSelect->get_result()->fetch_assoc();
        $stmtSelect->close();
        
        $newMsg['id'] = (int)$newMsg['id'];
        $newMsg['chat_id'] = (int)$newMsg['chat_id'];
        $newMsg['sender_id'] = (int)$newMsg['sender_id'];
        $newMsg['reply_to_message_id'] = $newMsg['reply_to_message_id'] ? (int)$newMsg['reply_to_message_id'] : null;
        $newMsg['metadata'] = $newMsg['metadata_json'] ? json_decode($newMsg['metadata_json'], true) : null;
        $newMsg['attachments'] = $newMsg['metadata']['attachments'] ?? [];
        unset($newMsg['metadata_json']);
        
        send_success(['message' => $newMsg]);
        
    } else {
        // --- LOGICA PRIVATA ---
        $messageType = 'media';
        $stmtMsg = $mysqli->prepare("
            INSERT INTO private_messages (conversation_id, sender_id, message, message_type, reply_to_id, ephemeral_timer)
            VALUES (?, ?, NULL, ?, ?, ?)
        ");
        $stmtMsg->bind_param("iisii", $conversationId, $userId, $messageType, $replyToId, $ephemeralTimer);
        $stmtMsg->execute();
        $messageId = $mysqli->insert_id;
        $stmtMsg->close();
        
        // Inseriamo l'allegato
        $stmtAtt = $mysqli->prepare("
            INSERT INTO private_message_attachments (message_id, file_name, file_path, file_size, file_mime, file_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmtAtt->bind_param("ississ", $messageId, $originalName, $relativePath, $fileSize, $mimeType, $fileType);
        $stmtAtt->execute();
        $stmtAtt->close();
        
        // Aggiorniamo la conversazione
        $mysqli->query("UPDATE private_conversations SET updated_at = NOW() WHERE id = $conversationId");
        $mysqli->query("UPDATE private_conversation_participants SET is_archived = 0 WHERE conversation_id = $conversationId");
        
        $mysqli->commit();
        
        // Seleziona il messaggio appena creato
        $stmtSelect = $mysqli->prepare("
            SELECT m.id, m.conversation_id, m.sender_id, u.username as sender_username, m.message, 
                   m.reply_to_id, m.ephemeral_timer, m.created_at,
                   reply_m.message AS reply_message_text, reply_u.username AS reply_username
            FROM private_messages m
            INNER JOIN utenti u ON u.id = m.sender_id
            LEFT JOIN private_messages reply_m ON reply_m.id = m.reply_to_id
            LEFT JOIN utenti reply_u ON reply_u.id = reply_m.sender_id
            WHERE m.id = ? LIMIT 1
        ");
        $stmtSelect->bind_param("i", $messageId);
        $stmtSelect->execute();
        $newMsg = $stmtSelect->get_result()->fetch_assoc();
        $stmtSelect->close();
        
        $newMsg['attachments'] = [[
            'file_name' => $originalName,
            'file_path' => $relativePath,
            'file_size' => $fileSize,
            'file_mime' => $mimeType,
            'file_type' => $fileType
        ]];
        $newMsg['reactions'] = [];
        
        send_success(['message' => $newMsg]);
    }

} catch (Exception $e) {
    $mysqli->rollback();
    if (file_exists($destPath)) {
        unlink($destPath);
    }
    send_error("Impossibile salvare l'allegato: " . $e->getMessage(), 500);
}
?>
