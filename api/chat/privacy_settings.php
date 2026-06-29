<?php
require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$action = isset($input['action']) ? trim((string)$input['action']) : '';

if (!$action) {
    send_error("Azione mancante.");
}

switch ($action) {
    case 'block':
    case 'unblock':
        $blockedUserId = isset($input['blocked_user_id']) ? (int)$input['blocked_user_id'] : 0;
        if (!$blockedUserId || $blockedUserId === $userId) {
            send_error("ID utente da bloccare/sbloccare non valido.");
        }
        
        if ($action === 'block') {
            $stmt = $mysqli->prepare("INSERT IGNORE INTO private_user_blocks (user_id, blocked_user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $blockedUserId);
            $stmt->execute();
            $stmt->close();
            
            // Se c'è una chat attiva, la archiviamo automaticamente
            $mysqli->query("
                UPDATE private_conversation_participants cp
                INNER JOIN private_conversations c ON c.id = cp.conversation_id
                SET cp.is_archived = 1
                WHERE cp.user_id = $userId 
                  AND c.is_group = 0 
                  AND EXISTS (SELECT 1 FROM private_conversation_participants cp2 WHERE cp2.conversation_id = c.id AND cp2.user_id = $blockedUserId)
            ");
            
            send_success(['blocked' => true]);
        } else {
            $stmt = $mysqli->prepare("DELETE FROM private_user_blocks WHERE user_id = ? AND blocked_user_id = ?");
            $stmt->bind_param("ii", $userId, $blockedUserId);
            $stmt->execute();
            $stmt->close();
            send_success(['blocked' => false]);
        }
        break;
        
    case 'mute':
    case 'unmute':
        $conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
        if (!$conversationId) {
            send_error("ID conversazione mancante o non valido.");
        }
        
        $isMuted = ($action === 'mute') ? 1 : 0;
        $stmt = $mysqli->prepare("UPDATE private_conversation_participants SET is_muted = ? WHERE conversation_id = ? AND user_id = ?");
        $stmt->bind_param("iii", $isMuted, $conversationId, $userId);
        if ($stmt->execute()) {
            $stmt->close();
            send_success(['muted' => (bool)$isMuted]);
        } else {
            $stmt->close();
            send_error("Impossibile aggiornare lo stato di silenziamento.");
        }
        break;
        
    case 'archive':
    case 'unarchive':
        $conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
        if (!$conversationId) {
            send_error("ID conversazione mancante o non valido.");
        }
        
        $isArchived = ($action === 'archive') ? 1 : 0;
        $stmt = $mysqli->prepare("UPDATE private_conversation_participants SET is_archived = ? WHERE conversation_id = ? AND user_id = ?");
        $stmt->bind_param("iii", $isArchived, $conversationId, $userId);
        if ($stmt->execute()) {
            $stmt->close();
            send_success(['archived' => (bool)$isArchived]);
        } else {
            $stmt->close();
            send_error("Impossibile aggiornare lo stato di archiviazione.");
        }
        break;
        
    case 'update_user_settings':
        $receiveFrom = isset($input['privacy_receive_from']) ? trim((string)$input['privacy_receive_from']) : 'all';
        $disableRead = isset($input['disable_read_receipts']) ? (int)$input['disable_read_receipts'] : 0;
        $disableTyping = isset($input['disable_typing_status']) ? (int)$input['disable_typing_status'] : 0;
        $statusText = isset($input['custom_status_text']) ? trim((string)$input['custom_status_text']) : null;
        $statusEmoji = isset($input['custom_status_emoji']) ? trim((string)$input['custom_status_emoji']) : null;
        
        if (!in_array($receiveFrom, ['all', 'verified', 'none'], true)) {
            $receiveFrom = 'all';
        }
        
        $disableRead = $disableRead ? 1 : 0;
        $disableTyping = $disableTyping ? 1 : 0;
        
        if ($statusText === '') $statusText = null;
        if ($statusEmoji === '') $statusEmoji = null;
        
        $stmt = $mysqli->prepare("
            INSERT INTO private_user_settings (user_id, privacy_receive_from, disable_read_receipts, disable_typing_status, custom_status_text, custom_status_emoji)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                privacy_receive_from = VALUES(privacy_receive_from),
                disable_read_receipts = VALUES(disable_read_receipts),
                disable_typing_status = VALUES(disable_typing_status),
                custom_status_text = VALUES(custom_status_text),
                custom_status_emoji = VALUES(custom_status_emoji)
        ");
        
        if ($stmt) {
            $stmt->bind_param("isiiiss", $userId, $receiveFrom, $disableRead, $disableTyping, $statusText, $statusEmoji, $statusText, $statusEmoji);
            if ($stmt->execute()) {
                $stmt->close();
                
                // Aggiorniamo anche lo stato personalizzato nella tabella utenti se esiste
                // (per coerenza con il resto del sito se mostrato altrove)
                $stmtUser = $mysqli->prepare("UPDATE utenti SET custom_status = ? WHERE id = ?");
                if ($stmtUser) {
                    $formattedStatus = ($statusEmoji ? $statusEmoji . ' ' : '') . $statusText;
                    if ($statusText === null && $statusEmoji === null) {
                        $formattedStatus = null;
                    }
                    $stmtUser->bind_param("si", $formattedStatus, $userId);
                    $stmtUser->execute();
                    $stmtUser->close();
                }
                
                send_success([
                    'privacy_receive_from' => $receiveFrom,
                    'disable_read_receipts' => (bool)$disableRead,
                    'disable_typing_status' => (bool)$disableTyping,
                    'custom_status_text' => $statusText,
                    'custom_status_emoji' => $statusEmoji
                ]);
            } else {
                $stmt->close();
                send_error("Errore durante l'aggiornamento delle impostazioni.");
            }
        } else {
            send_error("Errore di database durante la preparazione delle impostazioni.");
        }
        break;
        
    case 'get_user_settings':
        $stmt = $mysqli->prepare("SELECT privacy_receive_from, disable_read_receipts, disable_typing_status, custom_status_text, custom_status_emoji FROM private_user_settings WHERE user_id = ? LIMIT 1");
        $settings = [
            'privacy_receive_from' => 'all',
            'disable_read_receipts' => false,
            'disable_typing_status' => false,
            'custom_status_text' => null,
            'custom_status_emoji' => null
        ];
        
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $settings = [
                    'privacy_receive_from' => $row['privacy_receive_from'],
                    'disable_read_receipts' => (bool)$row['disable_read_receipts'],
                    'disable_typing_status' => (bool)$row['disable_typing_status'],
                    'custom_status_text' => $row['custom_status_text'],
                    'custom_status_emoji' => $row['custom_status_emoji']
                ];
            }
            $stmt->close();
        }
        send_success(['settings' => $settings]);
        break;
        
    default:
        send_error("Azione non supportata.");
        break;
}
?>
