<?php
// api/chat/edit_message.php
// Edits a group chat message if the user is the author and within the 15-minute window.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

$input = get_json_input();
$messageId = isset($input['message_id']) ? (int)$input['message_id'] : 0;
$newContent = isset($input['content']) ? trim((string)$input['content']) : '';

if (!$messageId) {
    send_error("ID messaggio mancante.");
}

if ($newContent === '') {
    send_error("Il testo del messaggio non può essere vuoto.");
}

if (strlen($newContent) > 2000) {
    send_error("Il messaggio supera il limite di 2000 caratteri.");
}

if (!canEditMessage($mysqli, $messageId, $userId)) {
    send_error("Non sei autorizzato a modificare questo messaggio o il tempo massimo (15 minuti) è scaduto.", 403);
}

try {
    $stmt = $mysqli->prepare("UPDATE chat_messages SET body = ?, edited_at = NOW() WHERE id = ?");
    if (!$stmt) throw new Exception("Errore di database.");
    
    $stmt->bind_param("si", $newContent, $messageId);
    $ok = $stmt->execute();
    $stmt->close();
    
    if ($ok) {
        send_success([
            'message_id' => $messageId,
            'body' => $newContent
        ]);
    } else {
        send_error("Impossibile modificare il messaggio.");
    }

} catch (Throwable $e) {
    send_error($e->getMessage(), 500);
}
?>
