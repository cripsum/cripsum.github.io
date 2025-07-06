<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/chat_config.php';

function sendMessage($mysqli, $userId, $message, $replyTo = null) {
    // Validazione base
    $message = trim($message);
    if (empty($message)) {
        return 'Il messaggio non può essere vuoto';
    }
    
    if (strlen($message) > (MAX_MESSAGE_LENGTH ?? 300)) {
        return 'Il messaggio è troppo lungo';
    }
    
    // Se c'è una reply, verifica che il messaggio esista
    if ($replyTo !== null) {
        $replyTo = intval($replyTo);
        $checkStmt = $mysqli->prepare("SELECT id FROM messages WHERE id = ?");
        $checkStmt->bind_param("i", $replyTo);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            $checkStmt->close();
            return 'Messaggio di riferimento non trovato';
        }
        $checkStmt->close();
    }
    
    try {
        if ($replyTo !== null) {
            // Inserisci messaggio con reply
            $stmt = $mysqli->prepare("INSERT INTO messages (user_id, message, reply_to, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("isi", $userId, $message, $replyTo);
        } else {
            // Inserisci messaggio normale
            $stmt = $mysqli->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("is", $userId, $message);
        }
        
        $success = $stmt->execute();
        $stmt->close();
        
        return $success ? true : 'Errore durante l\'inserimento del messaggio';
        
    } catch (Exception $e) {
        error_log("Errore invio messaggio: " . $e->getMessage());
        return 'Errore durante l\'invio del messaggio';
    }
}

function getMessageById($mysqli, $messageId) {
    $stmt = $mysqli->prepare("SELECT id, user_id, username, message, created_at, reply_to FROM messages WHERE id = ?");
    $stmt->bind_param("i", $messageId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function checkMessageTimeout($mysqli, $userId) {
    $stmt = $mysqli->prepare("SELECT created_at FROM messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $lastMessageTime = strtotime($row['created_at']);
        $currentTime = time();
        return ($currentTime - $lastMessageTime) >= MESSAGE_TIMEOUT;
    }
    
    return true; // Primo messaggio
}

function getMessages($mysqli, $lastMessageId = 0) {
    $stmt = $mysqli->prepare("
        SELECT 
            m.id, 
            m.message, 
            m.created_at, 
            m.reply_to,
            u.id as user_id,
            u.username, 
            u.ruolo,
            rm.message as reply_message,
            ru.username as reply_username
        FROM messages m 
        JOIN utenti u ON m.user_id = u.id 
        LEFT JOIN messages rm ON m.reply_to = rm.id
        LEFT JOIN utenti ru ON rm.user_id = ru.id
        WHERE m.id > ?
        ORDER BY m.created_at ASC 
        LIMIT ?
    ");
    $limit = defined('MESSAGES_PER_PAGE') ? MESSAGES_PER_PAGE : 50;
    $stmt->bind_param("ii", $lastMessageId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];

    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }

    return $messages;
}

function getAllMessages($mysqli) {
    $stmt = $mysqli->prepare("
        SELECT 
            m.id, 
            m.message, 
            m.created_at, 
            m.reply_to,
            u.id as user_id,
            u.username, 
            u.ruolo,
            rm.message as reply_message,
            ru.username as reply_username
        FROM messages m 
        JOIN utenti u ON m.user_id = u.id 
        LEFT JOIN messages rm ON m.reply_to = rm.id
        LEFT JOIN utenti ru ON rm.user_id = ru.id
        ORDER BY m.created_at DESC 
        LIMIT ?
    ");
    $limit = defined('MESSAGES_PER_PAGE') ? MESSAGES_PER_PAGE : 50;
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];

    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }

    return array_reverse($messages); // Mostra dal più vecchio al più nuovo
}

function deleteMessage($mysqli, $messageId, $userId, $userRole) {
    try {
        // Verifica se il messaggio esiste
        $stmt = $mysqli->prepare("SELECT user_id FROM chat_messages WHERE id = ?");
        $stmt->bind_param("i", $messageId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false; // Messaggio non trovato
        }
        
        $message = $result->fetch_assoc();
        $messageUserId = $message['user_id'];
        
        // Verifica permessi
        if ($messageUserId != $userId && $userRole !== 'admin') {
            return false; // Non autorizzato
        }
        
        // Elimina il messaggio
        $deleteStmt = $mysqli->prepare("DELETE FROM chat_messages WHERE id = ?");
        $deleteStmt->bind_param("i", $messageId);
        
        return $deleteStmt->execute();
        
    } catch (Exception $e) {
        return false;
    }
}

function replyToMessage($mysqli, $userId, $messageId, $replyMessage) {
    return sendMessage($mysqli, $userId, $replyMessage, $messageId);
}
?>