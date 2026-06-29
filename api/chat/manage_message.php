<?php
require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$action = isset($input['action']) ? trim((string)$input['action']) : '';
$messageId = isset($input['message_id']) ? (int)$input['message_id'] : 0;

if (!$messageId || !$action) {
    send_error("ID messaggio o azione mancante.");
}

// 1. Recuperiamo il messaggio e verifichiamo che esista
$stmtMsg = $mysqli->prepare("
    SELECT m.id, m.sender_id, m.conversation_id, m.deleted_for_all, c.is_group
    FROM private_messages m
    INNER JOIN private_conversations c ON c.id = m.conversation_id
    WHERE m.id = ? AND m.deleted_at IS NULL
    LIMIT 1
");
$stmtMsg->bind_param("i", $messageId);
$stmtMsg->execute();
$message = $stmtMsg->get_result()->fetch_assoc();
$stmtMsg->close();

if (!$message) {
    send_error("Messaggio non trovato.");
}

$conversationId = (int)$message['conversation_id'];

// 2. Verifichiamo che l'utente loggato partecipi alla conversazione
$stmtCheckPart = $mysqli->prepare("SELECT id FROM private_conversation_participants WHERE conversation_id = ? AND user_id = ? LIMIT 1");
$stmtCheckPart->bind_param("ii", $conversationId, $userId);
$stmtCheckPart->execute();
$isParticipant = $stmtCheckPart->get_result()->num_rows > 0;
$stmtCheckPart->close();

if (!$isParticipant) {
    send_error("Accesso negato. Non fai parte di questa conversazione.", 403);
}

// 3. Eseguiamo l'azione richiesta
switch ($action) {
    case 'edit':
        // Solo l'autore del messaggio può modificarlo
        if ((int)$message['sender_id'] !== $userId) {
            send_error("Non sei autorizzato a modificare questo messaggio.", 403);
        }
        if ((int)$message['deleted_for_all'] === 1) {
            send_error("Impossibile modificare un messaggio eliminato.");
        }
        
        $newContent = isset($input['content']) ? trim((string)$input['content']) : '';
        if ($newContent === '') {
            send_error("Il messaggio non può essere vuoto.");
        }
        
        $stmtEdit = $mysqli->prepare("UPDATE private_messages SET message = ?, is_edited = 1 WHERE id = ?");
        $stmtEdit->bind_param("si", $newContent, $messageId);
        if ($stmtEdit->execute()) {
            $stmtEdit->close();
            send_success(['message_id' => $messageId, 'content' => $newContent]);
        } else {
            $stmtEdit->close();
            send_error("Impossibile modificare il messaggio.");
        }
        break;
        
    case 'delete_for_self':
        // Chiunque partecipi alla chat può eliminare un messaggio per se stesso
        $stmtDelSelf = $mysqli->prepare("
            INSERT IGNORE INTO private_message_deleted (message_id, user_id)
            VALUES (?, ?)
        ");
        $stmtDelSelf->bind_param("ii", $messageId, $userId);
        if ($stmtDelSelf->execute()) {
            $stmtDelSelf->close();
            send_success(['message_id' => $messageId, 'deleted_for_self' => true]);
        } else {
            $stmtDelSelf->close();
            send_error("Impossibile eliminare il messaggio per te.");
        }
        break;
        
    case 'delete_for_all':
        // Solo l'autore o un amministratore può eliminare per tutti
        $isAdmin = in_array($userRole, ['admin', 'owner'], true);
        if ((int)$message['sender_id'] !== $userId && !$isAdmin) {
            send_error("Non sei autorizzato a eliminare questo messaggio per tutti.", 403);
        }
        
        $stmtDelAll = $mysqli->prepare("UPDATE private_messages SET deleted_for_all = 1, message = NULL WHERE id = ?");
        $stmtDelAll->bind_param("i", $messageId);
        if ($stmtDelAll->execute()) {
            $stmtDelAll->close();
            
            // Eliminiamo anche eventuali allegati collegati fisicamente
            $stmtAtt = $mysqli->prepare("SELECT file_path FROM private_message_attachments WHERE message_id = ?");
            if ($stmtAtt) {
                $stmtAtt->bind_param("i", $messageId);
                $stmtAtt->execute();
                $resAtt = $stmtAtt->get_result();
                while ($att = $resAtt->fetch_assoc()) {
                    $fullPath = __DIR__ . '/../..' . $att['file_path'];
                    if (file_exists($fullPath)) {
                        @unlink($fullPath);
                    }
                }
                $stmtAtt->close();
            }
            
            // Rimuoviamo gli allegati dal DB
            $mysqli->query("DELETE FROM private_message_attachments WHERE message_id = $messageId");
            
            send_success(['message_id' => $messageId, 'deleted_for_all' => true]);
        } else {
            $stmtDelAll->close();
            send_error("Impossibile eliminare il messaggio per tutti.");
        }
        break;
        
    case 'toggle_pin':
        // Controlliamo se è già fissato
        $stmtPinCheck = $mysqli->prepare("SELECT id FROM private_pinned_messages WHERE conversation_id = ? AND message_id = ? LIMIT 1");
        $stmtPinCheck->bind_param("ii", $conversationId, $messageId);
        $stmtPinCheck->execute();
        $pinRow = $stmtPinCheck->get_result()->fetch_assoc();
        $stmtPinCheck->close();
        
        if ($pinRow) {
            // Se è già fissato, lo sfissiamo
            $stmtUnpin = $mysqli->prepare("DELETE FROM private_pinned_messages WHERE conversation_id = ? AND message_id = ?");
            $stmtUnpin->bind_param("ii", $conversationId, $messageId);
            $stmtUnpin->execute();
            $stmtUnpin->close();
            send_success(['message_id' => $messageId, 'pinned' => false]);
        } else {
            // Altrimenti lo fissiamo
            $stmtPin = $mysqli->prepare("INSERT INTO private_pinned_messages (conversation_id, message_id, pinned_by) VALUES (?, ?, ?)");
            $stmtPin->bind_param("iii", $conversationId, $messageId, $userId);
            $stmtPin->execute();
            $stmtPin->close();
            send_success(['message_id' => $messageId, 'pinned' => true]);
        }
        break;
        
    case 'toggle_favorite':
        // Controlliamo se è già nei preferiti
        $stmtFavCheck = $mysqli->prepare("SELECT id FROM private_favorites WHERE user_id = ? AND message_id = ? LIMIT 1");
        $stmtFavCheck->bind_param("ii", $userId, $messageId);
        $stmtFavCheck->execute();
        $favRow = $stmtFavCheck->get_result()->fetch_assoc();
        $stmtFavCheck->close();
        
        if ($favRow) {
            // Rimuovi dai preferiti
            $stmtUnfav = $mysqli->prepare("DELETE FROM private_favorites WHERE user_id = ? AND message_id = ?");
            $stmtUnfav->bind_param("ii", $userId, $messageId);
            $stmtUnfav->execute();
            $stmtUnfav->close();
            send_success(['message_id' => $messageId, 'favorited' => false]);
        } else {
            // Aggiungi ai preferiti
            $stmtFav = $mysqli->prepare("INSERT INTO private_favorites (user_id, message_id) VALUES (?, ?)");
            $stmtFav->bind_param("ii", $userId, $messageId);
            $stmtFav->execute();
            $stmtFav->close();
            send_success(['message_id' => $messageId, 'favorited' => true]);
        }
        break;
        
    default:
        send_error("Azione non supportata.");
        break;
}
?>
