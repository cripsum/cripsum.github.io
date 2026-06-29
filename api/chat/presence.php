<?php
require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
$typingStatus = isset($input['typing_status']) ? trim((string)$input['typing_status']) : '';
$lastMessageId = isset($input['last_message_id']) ? (int)$input['last_message_id'] : 0;

// 1. Aggiorna l'attività dell'utente corrente (Stato Online)
$mysqli->query("UPDATE utenti SET ultimo_accesso = NOW() WHERE id = $userId");

// 2. Se l'utente sta scrivendo/registrando/caricando, aggiorna il suo stato nella conversazione
if ($conversationId > 0) {
    $status = null;
    if (in_array($typingStatus, ['typing', 'recording', 'uploading'], true)) {
        $status = $typingStatus;
    }
    
    $stmtType = $mysqli->prepare("
        UPDATE private_conversation_participants 
        SET typing_status = ?, last_typing_at = IF(? IS NULL, NULL, NOW()) 
        WHERE conversation_id = ? AND user_id = ?
    ");
    if ($stmtType) {
        $stmtType->bind_param("ssii", $status, $status, $conversationId, $userId);
        $stmtType->execute();
        $stmtType->close();
    }
}

// Risposta di default
$response = [
    'other_online' => false,
    'other_last_seen' => null,
    'other_typing' => null,
    'new_messages' => []
];

// 3. Se stiamo monitorando una conversazione specifica, recuperiamo lo stato dell'altro utente e i nuovi messaggi
if ($conversationId > 0) {
    // Trova l'altro partecipante
    $queryOther = "
        SELECT 
            u.id, u.ultimo_accesso,
            cp.typing_status, cp.last_typing_at
        FROM private_conversation_participants cp
        INNER JOIN utenti u ON u.id = cp.user_id
        WHERE cp.conversation_id = ? AND cp.user_id != ?
        LIMIT 1
    ";
    
    $stmtOther = $mysqli->prepare($queryOther);
    if ($stmtOther) {
        $stmtOther->bind_param("ii", $conversationId, $userId);
        $stmtOther->execute();
        $resOther = $stmtOther->get_result();
        if ($rowOther = $resOther->fetch_assoc()) {
            // Verifica online (attività negli ultimi 3 minuti)
            $lastAct = $rowOther['ultimo_accesso'] ? strtotime($rowOther['ultimo_accesso']) : 0;
            if ((time() - $lastAct) < 180) {
                $response['other_online'] = true;
            } else {
                $response['other_last_seen'] = $rowOther['ultimo_accesso'];
            }
            
            // Verifica se sta scrivendo (attività negli ultimi 5 secondi)
            $lastType = $rowOther['last_typing_at'] ? strtotime($rowOther['last_typing_at']) : 0;
            if ((time() - $lastType) < 5 && $rowOther['typing_status']) {
                $response['other_typing'] = $rowOther['typing_status'];
            }
        }
        $stmtOther->close();
    }
    
    // 4. Recuperiamo eventuali nuovi messaggi arrivati (per inviarli in tempo reale senza rinfrescare)
    if ($lastMessageId > 0) {
        $queryNew = "
            SELECT 
                m.id, m.conversation_id, m.sender_id, u.username as sender_username, m.message, 
                m.reply_to_id, m.forwarded_from_id, m.is_edited, m.ephemeral_timer, m.created_at,
                reply_m.message AS reply_message_text, reply_u.username AS reply_username
            FROM private_messages m
            INNER JOIN utenti u ON u.id = m.sender_id
            LEFT JOIN private_messages reply_m ON reply_m.id = m.reply_to_id
            LEFT JOIN utenti reply_u ON reply_u.id = reply_m.sender_id
            WHERE m.conversation_id = ? AND m.id > ? AND m.sender_id != ? AND m.deleted_at IS NULL
              AND NOT EXISTS (SELECT 1 FROM private_message_deleted pmd WHERE pmd.message_id = m.id AND pmd.user_id = ?)
            ORDER BY m.id ASC
        ";
        
        $stmtNew = $mysqli->prepare($queryNew);
        if ($stmtNew) {
            $stmtNew->bind_param("iiii", $conversationId, $lastMessageId, $userId, $userId);
            $stmtNew->execute();
            $newMsgs = $stmtNew->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtNew->close();
            
            // Per ogni nuovo messaggio, recuperiamo gli allegati
            foreach ($newMsgs as &$msg) {
                $msgId = (int)$msg['id'];
                $msg['attachments'] = [];
                $msg['reactions'] = [];
                
                $stmtAtt = $mysqli->prepare("SELECT id, file_name, file_path, file_size, file_mime, file_type FROM private_message_attachments WHERE message_id = ?");
                if ($stmtAtt) {
                    $stmtAtt->bind_param("i", $msgId);
                    $stmtAtt->execute();
                    $msg['attachments'] = $stmtAtt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmtAtt->close();
                }
            }
            unset($msg);
            
            $response['new_messages'] = $newMsgs;
            
            // Poiché abbiamo ricevuto nuovi messaggi tramite polling, aggiorniamo il nostro last_read_message_id
            if (!empty($newMsgs)) {
                $maxNewId = (int)end($newMsgs)['id'];
                $stmtUpdateRead = $mysqli->prepare("
                    UPDATE private_conversation_participants 
                    SET last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), ?) 
                    WHERE conversation_id = ? AND user_id = ?
                ");
                if ($stmtUpdateRead) {
                    $stmtUpdateRead->bind_param("iii", $maxNewId, $conversationId, $userId);
                    $stmtUpdateRead->execute();
                    $stmtUpdateRead->close();
                }
            }
        }
    }
}

// 5. Controlliamo se ci sono notifiche globali non lette in altre chat private
$queryNotify = "
    SELECT COUNT(*) as c 
    FROM private_conversation_participants cp
    INNER JOIN private_conversations c ON c.id = cp.conversation_id
    WHERE cp.user_id = ? AND cp.is_archived = 0 AND cp.is_muted = 0
      AND (
          SELECT COUNT(*) FROM private_messages pm 
          WHERE pm.conversation_id = c.id 
            AND pm.id > COALESCE(cp.last_read_message_id, 0)
            AND pm.sender_id != ?
            AND pm.deleted_at IS NULL
            AND NOT EXISTS (SELECT 1 FROM private_message_deleted pmd WHERE pmd.message_id = pm.id AND pmd.user_id = ?)
      ) > 0
";
$stmtNotify = $mysqli->prepare($queryNotify);
$unreadChatsCount = 0;
if ($stmtNotify) {
    $stmtNotify->bind_param("iii", $userId, $userId, $userId);
    $stmtNotify->execute();
    $unreadChatsCount = (int)$stmtNotify->get_result()->fetch_assoc()['c'];
    $stmtNotify->close();
}

$response['unread_chats_count'] = $unreadChatsCount;

send_success($response);
?>
