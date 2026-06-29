<?php
require_once __DIR__ . '/bootstrap.php';

$conversationId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;

if (!$conversationId) {
    send_error("ID conversazione mancante o non valido.");
}

// 1. Verifica che l'utente loggato sia parte della conversazione
$stmtCheck = $mysqli->prepare("
    SELECT cp.nickname, cp.theme_color, cp.theme_bg, cp.favorite_emoji, cp.is_archived, c.is_group
    FROM private_conversation_participants cp
    INNER JOIN private_conversations c ON c.id = cp.conversation_id
    WHERE cp.conversation_id = ? AND cp.user_id = ?
    LIMIT 1
");
$stmtCheck->bind_param("ii", $conversationId, $userId);
$stmtCheck->execute();
$participantSettings = $stmtCheck->get_result()->fetch_assoc();
$stmtCheck->close();

if (!$participantSettings) {
    send_error("Accesso negato. Non sei parte di questa conversazione.", 403);
}

// 2. Recupera i partecipanti (escluso l'utente loggato per chat 1to1)
$queryPart = "
    SELECT u.id, u.username, u.ruolo, u.is_premium, cp.nickname
    FROM private_conversation_participants cp
    INNER JOIN utenti u ON u.id = cp.user_id
    WHERE cp.conversation_id = ?
";
$stmtPart = $mysqli->prepare($queryPart);
$stmtPart->bind_param("i", $conversationId);
$stmtPart->execute();
$participants = $stmtPart->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPart->close();

// 3. Recupera i messaggi fissati
$queryPinned = "
    SELECT ppm.message_id, pm.message, pm.created_at, u.username as sender_username, ppm.created_at AS pinned_at
    FROM private_pinned_messages ppm
    INNER JOIN private_messages pm ON pm.id = ppm.message_id
    INNER JOIN utenti u ON u.id = pm.sender_id
    WHERE ppm.conversation_id = ? AND pm.deleted_at IS NULL AND pm.deleted_for_all = 0
    ORDER BY ppm.id DESC
";
$stmtPinned = $mysqli->prepare($queryPinned);
$stmtPinned->bind_param("i", $conversationId);
$stmtPinned->execute();
$pinnedMessages = $stmtPinned->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPinned->close();

// 4. Recupera la galleria dei media condivisi (Immagini & Video)
$queryMedia = "
    SELECT pma.id, pma.message_id, pma.file_name, pma.file_path, pma.file_mime, pma.file_type, pm.created_at
    FROM private_message_attachments pma
    INNER JOIN private_messages pm ON pm.id = pma.message_id
    WHERE pm.conversation_id = ? AND pm.deleted_at IS NULL AND pma.file_type IN ('image', 'video')
      AND NOT EXISTS (SELECT 1 FROM private_message_deleted pmd WHERE pmd.message_id = pm.id AND pmd.user_id = ?)
    ORDER BY pma.id DESC
";
$stmtMedia = $mysqli->prepare($queryMedia);
$stmtMedia->bind_param("ii", $conversationId, $userId);
$stmtMedia->execute();
$sharedMedia = $stmtMedia->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMedia->close();

// 5. Recupera i documenti/file condivisi
$queryFiles = "
    SELECT pma.id, pma.message_id, pma.file_name, pma.file_path, pma.file_size, pma.file_mime, pm.created_at
    FROM private_message_attachments pma
    INNER JOIN private_messages pm ON pm.id = pma.message_id
    WHERE pm.conversation_id = ? AND pm.deleted_at IS NULL AND pma.file_type NOT IN ('image', 'video', 'sticker')
      AND NOT EXISTS (SELECT 1 FROM private_message_deleted pmd WHERE pmd.message_id = pm.id AND pmd.user_id = ?)
    ORDER BY pma.id DESC
";
$stmtFiles = $mysqli->prepare($queryFiles);
$stmtFiles->bind_param("ii", $conversationId, $userId);
$stmtFiles->execute();
$sharedFiles = $stmtFiles->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtFiles->close();

// 6. Recupera i link condivisi (cerca messaggi con http:// o https://)
$queryLinks = "
    SELECT pm.id, pm.message, pm.created_at, u.username as sender_username
    FROM private_messages pm
    INNER JOIN utenti u ON u.id = pm.sender_id
    WHERE pm.conversation_id = ? AND pm.deleted_at IS NULL AND pm.deleted_for_all = 0
      AND (pm.message LIKE '%http://%' OR pm.message LIKE '%https://%')
      AND NOT EXISTS (SELECT 1 FROM private_message_deleted pmd WHERE pmd.message_id = pm.id AND pmd.user_id = ?)
    ORDER BY pm.id DESC
";
$stmtLinks = $mysqli->prepare($queryLinks);
$stmtLinks->bind_param("ii", $conversationId, $userId);
$stmtLinks->execute();
$linkMessages = $stmtLinks->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtLinks->close();

// Estraiamo i link reali dal testo dei messaggi per presentarli in modo pulito
$sharedLinks = [];
foreach ($linkMessages as $linkMsg) {
    preg_match_all('/https?:\/\/[^\s]+/', $linkMsg['message'], $matches);
    if (!empty($matches[0])) {
        foreach ($matches[0] as $url) {
            $sharedLinks[] = [
                'message_id' => $linkMsg['id'],
                'sender_username' => $linkMsg['sender_username'],
                'url' => $url,
                'created_at' => $linkMsg['created_at']
            ];
        }
    }
}

send_success([
    'settings' => $participantSettings,
    'participants' => $participants,
    'pinned_messages' => $pinnedMessages,
    'gallery' => [
        'media' => $sharedMedia,
        'files' => $sharedFiles,
        'links' => $sharedLinks
    ]
]);
?>
