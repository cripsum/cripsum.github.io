<?php
// api/chat/leave_chat.php
// Allows a participant to leave the group chat.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

$input = get_json_input();
$chatId = isset($input['chat_id']) ? (int)$input['chat_id'] : 0;

if (!$chatId) {
    send_error("ID chat mancante.");
}

if (!isChatMember($mysqli, $chatId, $userId)) {
    send_error("Non sei un partecipante attivo di questo gruppo.", 403);
}

$myRole = getChatRole($mysqli, $chatId, $userId);

// Owner restrictions
if ($myRole === 'owner') {
    // Count active members in the group
    $stmtCount = $mysqli->prepare("SELECT COUNT(*) as c FROM chat_members WHERE chat_id = ? AND status = 'active'");
    $stmtCount->bind_param("i", $chatId);
    $stmtCount->execute();
    $activeCount = $stmtCount->get_result()->fetch_assoc()['c'];
    $stmtCount->close();
    
    if ($activeCount > 1) {
        send_error("Sei il proprietario del gruppo. Devi trasferire l'ownership ad un altro utente prima di poter uscire.", 400);
    }
}

$mysqli->begin_transaction();

try {
    // 1. Get username for system message
    $stmtUser = $mysqli->prepare("SELECT username FROM utenti WHERE id = ? LIMIT 1");
    $stmtUser->execute();
    $username = $stmtUser->get_result()->fetch_assoc()['username'] ?? 'Utente';
    $stmtUser->close();
    
    // 2. Set status to left
    $stmtLeave = $mysqli->prepare("UPDATE chat_members SET status = 'left', left_at = NOW() WHERE chat_id = ? AND user_id = ?");
    $stmtLeave->bind_param("ii", $chatId, $userId);
    $stmtLeave->execute();
    $stmtLeave->close();
    
    // 3. Create system message
    createSystemMessage($mysqli, $chatId, 'leave', [
        'username' => $username
    ]);
    
    // If no one is left active in the group, archive it
    $stmtCountLeft = $mysqli->prepare("SELECT COUNT(*) as c FROM chat_members WHERE chat_id = ? AND status = 'active'");
    $stmtCountLeft->bind_param("i", $chatId);
    $stmtCountLeft->execute();
    $leftCount = $stmtCountLeft->get_result()->fetch_assoc()['c'];
    $stmtCountLeft->close();
    
    if ($leftCount === 0) {
        $mysqli->query("UPDATE chats SET is_archived = 1 WHERE id = $chatId");
    }
    
    $mysqli->commit();
    
    send_success([
        'message' => "Sei uscito dal gruppo."
    ]);

} catch (Throwable $e) {
    $mysqli->rollback();
    send_error($e->getMessage());
}
?>
