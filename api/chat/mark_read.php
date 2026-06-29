<?php
// api/chat/mark_read.php
// Marks all messages in a group chat as read.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

$input = get_json_input();
$chatId = isset($input['chat_id']) ? (int)$input['chat_id'] : 0;
$messageId = isset($input['message_id']) ? (int)$input['message_id'] : null;

if (!$chatId) {
    send_error("ID chat mancante.");
}

if (!isChatMember($mysqli, $chatId, $userId)) {
    send_error("Accesso negato. Non sei un partecipante di questo gruppo.", 403);
}

try {
    $ok = markChatAsRead($mysqli, $chatId, $userId, $messageId);
    if ($ok) {
        send_success(['message' => "Chat segnata come letta."]);
    } else {
        send_error("Impossibile aggiornare i messaggi letti.");
    }
} catch (Throwable $e) {
    send_error($e->getMessage(), 500);
}
?>
