<?php
// api/chat/send_message.php
// Dual endpoint: Sends a message (text or GIF) to a group chat or private chat.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';
require_once __DIR__ . '/../../includes/social_functions.php';

$input = get_json_input();
$chatId = isset($input['chat_id']) ? (int)$input['chat_id'] : 0;
$messageText = isset($input['message']) ? trim((string)$input['message']) : '';
$msgType = isset($input['message_type']) ? trim((string)$input['message_type']) : 'text';
$mediaUrl = isset($input['media_url']) ? trim((string)$input['media_url']) : null;
$mediaTitle = isset($input['media_title']) ? trim((string)$input['media_title']) : null;

// Validate GIF
if ($msgType === 'gif' && !$mediaUrl) {
    send_error("URL della GIF mancante.");
}
if ($msgType === 'gif' && !chat_is_allowed_gif_url($mediaUrl)) {
    send_error("GIF non valida.", 422);
}

// --- GROUP CHAT ROUTING ---
if ($chatId > 0) {
    if ($messageText === '' && $msgType !== 'gif') {
        send_error("Il testo del messaggio non può essere vuoto.");
    }
    if (strlen($messageText) > 2000) {
        send_error("Il messaggio supera il limite di 2000 caratteri.");
    }
    
    if (!canSendMessage($mysqli, $chatId, $userId)) {
        send_error("Non sei autorizzato a inviare messaggi in questo gruppo o non sei un membro attivo.", 403);
    }
    
    // Rate limit check
    $stmtRate = $mysqli->prepare("SELECT COUNT(*) as c FROM chat_messages WHERE sender_id = ? AND created_at > NOW() - INTERVAL 10 SECOND");
    $stmtRate->bind_param("i", $userId);
    $stmtRate->execute();
    $msgCount = $stmtRate->get_result()->fetch_assoc()['c'];
    $stmtRate->close();
    
    if ($msgCount >= 5) {
        send_error("Stai inviando messaggi troppo velocemente. Rallenta!", 429);
    }
    
    $mysqli->begin_transaction();
    
    try {
        $replyTo = isset($input['reply_to_message_id']) ? (int)$input['reply_to_message_id'] : null;
        
        $metaJson = null;
        if ($msgType === 'gif') {
            $metaJson = json_encode([
                'media_url' => $mediaUrl,
                'media_title' => $mediaTitle
            ]);
        } else if (isset($input['metadata_json'])) {
            $metaJson = $input['metadata_json'];
        }
        
        $stmtMsg = $mysqli->prepare("
            INSERT INTO chat_messages (chat_id, sender_id, body, message_type, reply_to_message_id, metadata_json)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        if (!$stmtMsg) throw new Exception("Errore di database.");
        $stmtMsg->bind_param("iissis", $chatId, $userId, $messageText, $msgType, $replyTo, $metaJson);
        $stmtMsg->execute();
        $messageId = $mysqli->insert_id;
        $stmtMsg->close();
        
        // Update chat pointer
        $mysqli->query("UPDATE chats SET last_message_id = $messageId, last_message_at = NOW() WHERE id = $chatId");
        
        // Restore archive state for all participants
        $mysqli->query("UPDATE chat_members SET is_archived = 0 WHERE chat_id = $chatId");
        
        $mysqli->commit();
        
        // Fetch newly created message details to return to client
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
        unset($newMsg['metadata_json']);
        
        // Get sender username and chat name for notifications
        $stmtGroup = $mysqli->prepare("SELECT name FROM chats WHERE id = ? LIMIT 1");
        $stmtGroup->bind_param("i", $chatId);
        $stmtGroup->execute();
        $groupName = $stmtGroup->get_result()->fetch_assoc()['name'] ?? 'Gruppo';
        $stmtGroup->close();
        
        // Send notifications to group members (except sender and muted users)
        $members = getChatMembers($mysqli, $chatId);
        foreach ($members as $m) {
            $memberId = (int)$m['user_id'];
            if ($memberId === $userId) continue;
            
            // Check if member muted this chat
            $stmtMute = $mysqli->prepare("SELECT muted_until, notification_level FROM chat_members WHERE chat_id = ? AND user_id = ? LIMIT 1");
            $isMuted = false;
            $notifLevel = 'all';
            if ($stmtMute) {
                $stmtMute->bind_param("ii", $chatId, $memberId);
                $stmtMute->execute();
                $resMute = $stmtMute->get_result()->fetch_assoc();
                if ($resMute) {
                    $notifLevel = $resMute['notification_level'];
                    if ($resMute['muted_until'] && strtotime($resMute['muted_until']) > time()) {
                        $isMuted = true;
                    }
                }
                $stmtMute->close();
            }
            
            if ($isMuted || $notifLevel === 'muted') continue;
            
            // Send social notification
            $titleIt = "Nuovo messaggio in $groupName";
            $titleEn = "New message in $groupName";
            $notifText = $msgType === 'gif' ? '[GIF]' : $messageText;
            $contentIt = "@{$newMsg['sender_username']}: " . (strlen($notifText) > 40 ? substr($notifText, 0, 40) . '...' : $notifText);
            $contentEn = "@{$newMsg['sender_username']}: " . (strlen($notifText) > 40 ? substr($notifText, 0, 40) . '...' : $notifText);
            sendSocialNotification($mysqli, $memberId, $titleIt, $titleEn, $contentIt, $contentEn);
        }
        
        send_success(['message' => $newMsg]);
        
    } catch (Throwable $e) {
        $mysqli->rollback();
        send_error($e->getMessage());
    }
}

// --- ORIGINAL PRIVATE CHAT ROUTING ---
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
$recipientId = isset($input['recipient_id']) ? (int)$input['recipient_id'] : 0;
$replyToId = isset($input['reply_to_id']) ? (int)$input['reply_to_id'] : null;
$forwardedFromId = isset($input['forwarded_from_id']) ? (int)$input['forwarded_from_id'] : null;
$ephemeralTimer = isset($input['ephemeral_timer']) ? (int)$input['ephemeral_timer'] : 0;

// Validazione input
if ($messageText === '' && !$forwardedFromId && $msgType !== 'gif') {
    send_error("Il testo del messaggio non può essere vuoto.");
}

$mysqli->begin_transaction();

try {
    // 1. Se non c'è una conversazione attiva, proviamo a crearla o trovarla con il destinatario
    if ($conversationId === 0) {
        if ($recipientId === 0 || $recipientId === $userId) {
            throw new Exception("Destinatario non valido.");
        }
        
        // Controlliamo se esiste già una conversazione 1to1 tra i due utenti
        $queryCheck = "
            SELECT p1.conversation_id 
            FROM private_conversation_participants p1
            INNER JOIN private_conversation_participants p2 ON p1.conversation_id = p2.conversation_id
            INNER JOIN private_conversations c ON c.id = p1.conversation_id
            WHERE p1.user_id = ? AND p2.user_id = ? AND c.is_group = 0
            LIMIT 1
        ";
        $stmtCheck = $mysqli->prepare($queryCheck);
        $stmtCheck->bind_param("ii", $userId, $recipientId);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        $existingConv = $resCheck->fetch_assoc();
        $stmtCheck->close();
        
        if ($existingConv) {
            $conversationId = (int)$existingConv['conversation_id'];
        } else {
            // Verifica permessi di privacy del destinatario e blocchi
            $rel = getRelationshipStatus($mysqli, $userId, $recipientId);
            if (!$rel['can_message']) {
                throw new Exception("L'utente ha disattivato la ricezione di messaggi da parte tua o c'è un blocco attivo.", 403);
            }
            
            // Creiamo una nuova conversazione
            $mysqli->query("INSERT INTO private_conversations (is_group) VALUES (0)");
            $conversationId = $mysqli->insert_id;
            
            // Aggiungiamo i due partecipanti
            $stmtPart1 = $mysqli->prepare("INSERT INTO private_conversation_participants (conversation_id, user_id) VALUES (?, ?)");
            $stmtPart1->bind_param("ii", $conversationId, $userId);
            $stmtPart1->execute();
            $stmtPart1->close();
            
            $stmtPart2 = $mysqli->prepare("INSERT INTO private_conversation_participants (conversation_id, user_id) VALUES (?, ?)");
            $stmtPart2->bind_param("ii", $conversationId, $recipientId);
            $stmtPart2->execute();
            $stmtPart2->close();
        }
    } else {
        // Se la conversazione esiste già, verifichiamo che il mittente ne faccia parte
        $stmtCheckPart = $mysqli->prepare("SELECT id FROM private_conversation_participants WHERE conversation_id = ? AND user_id = ? LIMIT 1");
        $stmtCheckPart->bind_param("ii", $conversationId, $userId);
        $stmtCheckPart->execute();
        $isPart = $stmtCheckPart->get_result()->num_rows > 0;
        $stmtCheckPart->close();
        
        if (!$isPart) {
            throw new Exception("Non sei autorizzato a inviare messaggi in questa conversazione.", 403);
        }
        
        // Trova l'altro partecipante per verificare il blocco
        $stmtOther = $mysqli->prepare("SELECT user_id FROM private_conversation_participants WHERE conversation_id = ? AND user_id != ? LIMIT 1");
        $stmtOther->bind_param("ii", $conversationId, $userId);
        $stmtOther->execute();
        $resOther = $stmtOther->get_result();
        if ($rowOther = $resOther->fetch_assoc()) {
            $recipientId = (int)$rowOther['user_id'];
            $rel = getRelationshipStatus($mysqli, $userId, $recipientId);
            if (!$rel['can_message']) {
                throw new Exception("Impossibile inviare il messaggio. Sei stato bloccato, hai bloccato questo utente o non hai i permessi di messaggistica.", 403);
            }
        }
        $stmtOther->close();
    }

    // 2. Protezione antispam (Rate limiting: max 5 messaggi negli ultimi 10 secondi)
    $stmtRate = $mysqli->prepare("SELECT COUNT(*) as c FROM private_messages WHERE sender_id = ? AND created_at > NOW() - INTERVAL 10 SECOND");
    $stmtRate->bind_param("i", $userId);
    $stmtRate->execute();
    $msgCount = $stmtRate->get_result()->fetch_assoc()['c'];
    $stmtRate->close();
    
    if ($msgCount >= 5) {
        throw new Exception("Stai inviando messaggi troppo velocemente. Rallenta!", 429);
    }

    // 3. Inserimento del messaggio
    $stmtMsg = $mysqli->prepare("
        INSERT INTO private_messages (conversation_id, sender_id, message, message_type, media_url, media_title, reply_to_id, forwarded_from_id, ephemeral_timer)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmtMsg) {
        throw new Exception("Errore interno del server durante la preparazione del messaggio.");
    }
    
    $stmtMsg->bind_param("iisssssii", $conversationId, $userId, $messageText, $msgType, $mediaUrl, $mediaTitle, $replyToId, $forwardedFromId, $ephemeralTimer);
    $stmtMsg->execute();
    $messageId = $mysqli->insert_id;
    $stmtMsg->close();
    
    // Aggiorniamo `updated_at` della conversazione per farla risalire in cima alla lista
    $mysqli->query("UPDATE private_conversations SET updated_at = NOW() WHERE id = $conversationId");
    
    // Se la chat era archiviata, la ripristiniamo automaticamente per entrambi i partecipanti
    $mysqli->query("UPDATE private_conversation_participants SET is_archived = 0 WHERE conversation_id = $conversationId");

    $mysqli->commit();
    
    // Recuperiamo i dettagli del messaggio appena inserito per restituirli al client
    $stmtSelect = $mysqli->prepare("
        SELECT m.id, m.conversation_id, m.sender_id, u.username as sender_username, m.message, 
               m.message_type, m.media_url, m.media_title,
               m.reply_to_id, m.forwarded_from_id, m.ephemeral_timer, m.created_at,
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
    
    send_success(['message' => $newMsg]);
    
} catch (Exception $e) {
    $mysqli->rollback();
    send_error($e->getMessage(), $e->getCode() ?: 400);
}
?>
