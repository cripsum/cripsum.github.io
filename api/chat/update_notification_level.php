<?php
// api/chat/update_notification_level.php
// Updates notification level for a member in a group chat.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

$input = get_json_input();
$chatId = isset($input['chat_id']) ? (int)$input['chat_id'] : 0;
$level = isset($input['notification_level']) ? trim((string)$input['notification_level']) : 'all';

if (!$chatId) {
    send_error("ID chat non valido.");
}

if (!in_array($level, ['all', 'mentions', 'muted'], true)) {
    send_error("Livello notifica non valido.");
}

if (!isChatMember($mysqli, $chatId, $userId)) {
    send_error("Non partecipi a questo gruppo.", 403);
}

try {
    $stmt = $mysqli->prepare("UPDATE chat_members SET notification_level = ? WHERE chat_id = ? AND user_id = ?");
    if (!$stmt) {
        send_error("Errore interno del server.", 500);
    }
    $stmt->bind_param("sii", $level, $chatId, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    
    if ($ok) {
        send_success([
            'notification_level' => $level,
            'message' => "Preferenze notifiche aggiornate con successo."
        ]);
    } else {
        send_error("Impossibile aggiornare le preferenze notifiche.");
    }
} catch (Throwable $e) {
    send_error($e->getMessage(), 500);
}
?>
