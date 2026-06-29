<?php
require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
$recipientId = isset($input['recipient_id']) ? (int)$input['recipient_id'] : 0;
$messageText = isset($input['message']) ? trim((string)$input['message']) : '';
$replyToId = isset($input['reply_to_id']) ? (int)$input['reply_to_id'] : null;
$forwardedFromId = isset($input['forwarded_from_id']) ? (int)$input['forwarded_from_id'] : null;
$ephemeralTimer = isset($input['ephemeral_timer']) ? (int)$input['ephemeral_timer'] : 0;

// Validazione input
if ($messageText === '' && !$forwardedFromId) {
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
            // Verifica permessi di privacy del destinatario
            $stmtPriv = $mysqli->prepare("SELECT privacy_receive_from FROM private_user_settings WHERE user_id = ? LIMIT 1");
            $privacySetting = 'all';
            if ($stmtPriv) {
                $stmtPriv->bind_param("i", $recipientId);
                $stmtPriv->execute();
                $resPriv = $stmtPriv->get_result();
                if ($rowPriv = $resPriv->fetch_assoc()) {
                    $privacySetting = $rowPriv['privacy_receive_from'];
                }
                $stmtPriv->close();
            }
            
            // Se impostato su 'none', nessuno può avviare nuove chat
            if ($privacySetting === 'none') {
                throw new Exception("L'utente ha disattivato la ricezione di nuove chat private.", 403);
            }
            // Se impostato su 'verified', solo utenti speciali o premium
            if ($privacySetting === 'verified') {
                $stmtSender = $mysqli->prepare("SELECT ruolo, is_premium FROM utenti WHERE id = ? LIMIT 1");
                $isVerified = false;
                if ($stmtSender) {
                    $stmtSender->bind_param("i", $userId);
                    $stmtSender->execute();
                    $resSender = $stmtSender->get_result();
                    if ($rowSender = $resSender->fetch_assoc()) {
                        $isVerified = in_array($rowSender['ruolo'], ['admin', 'owner'], true) || (int)$rowSender['is_premium'] === 1;
                    }
                    $stmtSender->close();
                }
                if (!$isVerified) {
                    throw new Exception("Solo gli utenti verificati o premium possono avviare una chat con questo utente.", 403);
                }
            }

            // Controlliamo il blocco prima di creare la chat
            if (is_blocked_with($mysqli, $userId, $recipientId)) {
                throw new Exception("Impossibile avviare la conversazione. Sei stato bloccato o hai bloccato questo utente.", 403);
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
            if (is_blocked_with($mysqli, $userId, $recipientId)) {
                throw new Exception("Impossibile inviare il messaggio. Sei stato bloccato o hai bloccato questo utente.", 403);
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
        INSERT INTO private_messages (conversation_id, sender_id, message, reply_to_id, forwarded_from_id, ephemeral_timer)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmtMsg) {
        throw new Exception("Errore interno del server durante la preparazione del messaggio.");
    }
    
    $stmtMsg->bind_param("iisiii", $conversationId, $userId, $messageText, $replyToId, $forwardedFromId, $ephemeralTimer);
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
