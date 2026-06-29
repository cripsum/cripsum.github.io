<?php
// api/chat/unmute.php
// Unmutes a group chat.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

$input = get_json_input();
$chatId = isset($input['chat_id']) ? (int)$input['chat_id'] : 0;

if (!$chatId) {
    send_error("ID chat non valido.");
}

if (!isChatMember($mysqli, $chatId, $userId)) {
    send_error("Non partecipi a questo gruppo.", 403);
}

try {
    $stmt = $mysqli->prepare("UPDATE chat_members SET muted_until = NULL WHERE chat_id = ? AND user_id = ?");
    if (!$stmt) {
        send_error("Errore interno del server.", 500);
    }
    $stmt->bind_param("ii", $chatId, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    
    if ($ok) {
        send_success([
            'message' => "Notifiche riattivate per questo gruppo."
        ]);
    } else {
        send_error("Impossibile riattivare le notifiche.");
    }
} catch (Throwable $e) {
    send_error($e->getMessage(), 500);
}
?>
