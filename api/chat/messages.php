<?php
// api/chat/messages.php
// Dual endpoint: Loads group chat messages if 'chat_id' is provided, otherwise falls back to global chat.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

// Route: Group Chat Messages
if (isset($_GET['chat_id'])) {
    $chatId = (int)$_GET['chat_id'];
    $beforeId = isset($_GET['before_message_id']) ? (int)$_GET['before_message_id'] : 0;
    $afterId = isset($_GET['after_message_id']) ? (int)$_GET['after_message_id'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 30;
    
    if (!$chatId) {
        send_error("ID chat non valido.");
    }
    
    if (!canViewChat($mysqli, $chatId, $userId)) {
        send_error("Accesso negato o non partecipi a questa chat.", 403);
    }
    
    try {
        // Build query clauses
        $whereSql = "m.chat_id = ? AND m.deleted_at IS NULL";
        $params = [$chatId];
        $types = "i";
        
        if ($beforeId > 0) {
            $whereSql .= " AND m.id < ?";
            $params[] = $beforeId;
            $types .= "i";
        }
        
        if ($afterId > 0) {
            $whereSql .= " AND m.id > ?";
            $params[] = $afterId;
            $types .= "i";
        }
        
        $query = "
            SELECT 
                m.id,
                m.chat_id,
                m.sender_id,
                u.username AS sender_username,
                u.display_name AS sender_display_name,
                u.ruolo AS sender_role,
                m.body,
                m.message_type,
                m.reply_to_message_id,
                m.metadata_json,
                m.edited_at,
                m.created_at
            FROM chat_messages m
            LEFT JOIN utenti u ON u.id = m.sender_id
            WHERE $whereSql
            ORDER BY m.id DESC
            LIMIT ?
        ";
        
        $params[] = $limit;
        $types .= "i";
        
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            send_error("Errore di database: " . $mysqli->error, 500);
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $messages = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Reverse to chronological order for the client
        $messages = array_reverse($messages);
        
        foreach ($messages as &$msg) {
            $msg['id'] = (int)$msg['id'];
            $msg['chat_id'] = (int)$msg['chat_id'];
            $msg['sender_id'] = (int)$msg['sender_id'];
            $msg['reply_to_message_id'] = $msg['reply_to_message_id'] ? (int)$msg['reply_to_message_id'] : null;
            $msg['metadata'] = $msg['metadata_json'] ? json_decode($msg['metadata_json'], true) : null;
            unset($msg['metadata_json']);
            
            // Populate attachments from metadata if present
            if (isset($msg['metadata']['attachments'])) {
                $msg['attachments'] = $msg['metadata']['attachments'];
            } else {
                $msg['attachments'] = [];
            }
        }
        unset($msg);
        
        // Automatically mark as read if we loaded messages
        if (count($messages) > 0 && $afterId === 0) {
            $lastMsg = end($messages);
            markChatAsRead($mysqli, $chatId, $userId, $lastMsg['id']);
        }
        
        send_success([
            'messages' => $messages,
            'has_more' => count($messages) >= $limit
        ]);
        
    } catch (Throwable $e) {
        send_error("Impossibile caricare i messaggi: " . $e->getMessage(), 500);
    }
}

// Fallback: Original Global Chat Logic
// We use the function defined in their chat environment
$user = chat_require_login_json($mysqli);
$userId = (int)$user['id'];
chat_touch_user($mysqli, $userId);

$afterId = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;
$beforeId = isset($_GET['before_id']) ? (int)$_GET['before_id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : MESSAGES_PER_PAGE;
$search = trim((string)($_GET['search'] ?? ''));

$messages = chat_fetch_messages($mysqli, $userId, [
    'after_id' => $afterId,
    'before_id' => $beforeId,
    'limit' => $limit,
    'search' => $search,
]);

chat_json([
    'ok' => true,
    'messages' => $messages,
    'online_count' => chat_get_online_count($mysqli),
    'typing' => chat_get_typing_users($mysqli, $userId),
    'server_time' => date(DATE_ATOM),
]);
?>
