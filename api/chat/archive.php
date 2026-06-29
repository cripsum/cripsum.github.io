<?php
// api/chat/archive.php
// Handles group chat archiving / unarchiving per user.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

$input = get_json_input();
$chatId = isset($input['chat_id']) ? (int)$input['chat_id'] : 0;
$action = isset($input['action']) ? trim((string)$input['action']) : 'archive';

if (!$chatId) {
    send_error("ID chat mancante.");
}

if (!isChatMember($mysqli, $chatId, $userId)) {
    send_error("Non sei un partecipante di questo gruppo.", 403);
}

try {
    // Add is_archived column if it doesn't exist yet to be safe
    $mysqli->query("ALTER TABLE `chat_members` ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notification_level` LIMIT 1");
} catch (Throwable $e) {
    // Column might already exist, ignore error
}

try {
    $isArchived = ($action === 'archive') ? 1 : 0;
    
    $stmt = $mysqli->prepare("UPDATE chat_members SET is_archived = ? WHERE chat_id = ? AND user_id = ?");
    if (!$stmt) throw new Exception("Errore di database.");
    $stmt->bind_param("iii", $isArchived, $chatId, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    
    if ($ok) {
        send_success([
            'archived' => (bool)$isArchived,
            'message' => $isArchived ? "Gruppo archiviato." : "Gruppo ripristinato."
        ]);
    } else {
        send_error("Impossibile aggiornare lo stato di archiviazione.");
    }

} catch (Throwable $e) {
    send_error($e->getMessage());
}
?>
