<?php
// api/chat/delete_message.php
// Deletes a group chat message.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

$input = get_json_input();
$messageId = isset($input['message_id']) ? (int)$input['message_id'] : 0;

if (!$messageId) {
    send_error("ID messaggio mancante.");
}

if (!canDeleteGroupMessage($mysqli, $messageId, $userId, $userRole)) {
    send_error("Non sei autorizzato ad eliminare questo messaggio.", 403);
}

try {
    $stmt = $mysqli->prepare("UPDATE chat_messages SET deleted_at = NOW(), deleted_by = ? WHERE id = ?");
    if (!$stmt) throw new Exception("Errore di database.");
    
    $stmt->bind_param("ii", $userId, $messageId);
    $ok = $stmt->execute();
    $stmt->close();
    
    if ($ok) {
        send_success([
            'message_id' => $messageId,
            'message' => "Messaggio eliminato con successo."
        ]);
    } else {
        send_error("Impossibile eliminare il messaggio.");
    }

} catch (Throwable $e) {
    send_error($e->getMessage(), 500);
}
?>
