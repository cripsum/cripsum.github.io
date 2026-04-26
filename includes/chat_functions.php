<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/chat_config.php';
require_once __DIR__ . '/chat_v2_helpers.php';

function sendMessage($mysqli, $userId, $message, $replyTo = null)
{
    $message = chat_clean_message((string)$message);
    if ($error = chat_message_error($message)) return $error;
    if (chat_has_bad_word($mysqli, $message)) return 'Messaggio bloccato dal filtro';

    $rate = chat_rate_limit_ok($mysqli, (int)$userId);
    if (!$rate['ok']) return 'Aspetta ancora ' . $rate['wait'] . 's';

    if ($replyTo !== null) {
        $replyTo = (int)$replyTo;
        $checkStmt = $mysqli->prepare('SELECT id FROM messages WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        if (!$checkStmt) return 'Errore server';
        $checkStmt->bind_param('i', $replyTo);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if (!$checkResult || $checkResult->num_rows === 0) {
            $checkStmt->close();
            return 'Messaggio di riferimento non trovato';
        }
        $checkStmt->close();

        $stmt = $mysqli->prepare('INSERT INTO messages (user_id, message, reply_to, created_at) VALUES (?, ?, ?, NOW())');
        if (!$stmt) return 'Errore server';
        $stmt->bind_param('isi', $userId, $message, $replyTo);
    } else {
        $stmt = $mysqli->prepare('INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())');
        if (!$stmt) return 'Errore server';
        $stmt->bind_param('is', $userId, $message);
    }

    $ok = $stmt->execute();
    $stmt->close();
    return $ok ? true : 'Errore durante l\'inserimento del messaggio';
}

function getMessageById($mysqli, $messageId)
{
    $stmt = $mysqli->prepare('SELECT m.id, m.user_id, u.username, m.message, m.created_at, m.reply_to FROM messages m INNER JOIN utenti u ON u.id = m.user_id WHERE m.id = ? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('i', $messageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

function checkMessageTimeout($mysqli, $userId)
{
    return chat_rate_limit_ok($mysqli, (int)$userId)['ok'];
}

function getMessages($mysqli, $lastMessageId = 0)
{
    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    return chat_fetch_messages($mysqli, $currentUserId, ['after_id' => (int)$lastMessageId, 'limit' => MESSAGES_PER_PAGE]);
}

function getAllMessages($mysqli)
{
    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    return chat_fetch_messages($mysqli, $currentUserId, ['limit' => MESSAGES_PER_PAGE]);
}

function deleteMessage($mysqli, $messageId, $userId, $userRole)
{
    $messageId = (int)$messageId;
    $userId = (int)$userId;

    $stmt = $mysqli->prepare('SELECT user_id, deleted_at FROM messages WHERE id = ? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('i', $messageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $message = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$message || !empty($message['deleted_at'])) return false;
    if ((int)$message['user_id'] !== $userId && !in_array($userRole, ['admin', 'owner'], true)) return false;

    $deleteStmt = $mysqli->prepare('UPDATE messages SET message = "", deleted_at = NOW(), deleted_by = ? WHERE id = ?');
    if (!$deleteStmt) return false;
    $deleteStmt->bind_param('ii', $userId, $messageId);
    $ok = $deleteStmt->execute();
    $deleteStmt->close();
    return $ok;
}

function replyToMessage($mysqli, $userId, $messageId, $replyMessage)
{
    return sendMessage($mysqli, $userId, $replyMessage, $messageId);
}
?>
