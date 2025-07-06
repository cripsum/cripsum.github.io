<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/chat_config.php';

function sendMessage($mysqli, $userId, $message, $replyTo = null) {
    if (strlen($message) > MAX_MESSAGE_LENGTH) {
        return 'Message exceeds maximum length.';
    }

    $stmt = $mysqli->prepare("INSERT INTO messages (user_id, message, reply_to, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("isi", $userId, $message, $replyTo);
    
    if ($stmt->execute()) {
        return true;
    } else {
        return 'Error sending message.';
    }
}

function getMessages($mysqli) {
    $stmt = $mysqli->prepare("SELECT m.id, m.message, m.created_at, u.username, u.profile_pic FROM messages m JOIN utenti u ON m.user_id = u.id ORDER BY m.created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];

    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }

    return $messages;
}

function deleteMessage($mysqli, $messageId, $userId, $isAdmin) {
    if ($isAdmin) {
        $stmt = $mysqli->prepare("DELETE FROM messages WHERE id = ?");
        $stmt->bind_param("i", $messageId);
    } else {
        $stmt = $mysqli->prepare("DELETE FROM messages WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $messageId, $userId);
    }

    return $stmt->execute();
}

function replyToMessage($mysqli, $userId, $messageId, $replyMessage) {
    return sendMessage($mysqli, $userId, $replyMessage, $messageId);
}
?>