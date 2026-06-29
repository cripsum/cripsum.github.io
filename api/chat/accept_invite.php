<?php
// api/chat/accept_invite.php
// Accepts a group chat invite and sets user status to active.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

$input = get_json_input();
$chatId = isset($input['chat_id']) ? (int)$input['chat_id'] : 0;

if (!$chatId) {
    send_error("ID chat mancante.");
}

// Check pending invite
$stmtCheck = $mysqli->prepare("SELECT id FROM chat_invites WHERE chat_id = ? AND invitee_id = ? AND status = 'pending' LIMIT 1");
if (!$stmtCheck) {
    send_error("Errore interno.");
}
$stmtCheck->bind_param("ii", $chatId, $userId);
$stmtCheck->execute();
$hasInvite = $stmtCheck->get_result()->num_rows > 0;
$stmtCheck->close();

if (!$hasInvite) {
    send_error("Nessun invito pendente trovato per questo gruppo.", 404);
}

$mysqli->begin_transaction();

try {
    // 1. Update invite status
    $stmtInvite = $mysqli->prepare("UPDATE chat_invites SET status = 'accepted', responded_at = NOW() WHERE chat_id = ? AND invitee_id = ? AND status = 'pending'");
    $stmtInvite->bind_param("ii", $chatId, $userId);
    $stmtInvite->execute();
    $stmtInvite->close();
    
    // 2. Update membership status
    $stmtMember = $mysqli->prepare("UPDATE chat_members SET status = 'active', joined_at = NOW() WHERE chat_id = ? AND user_id = ?");
    $stmtMember->bind_param("ii", $chatId, $userId);
    $stmtMember->execute();
    $stmtMember->close();
    
    // Get username
    $stmtUser = $mysqli->prepare("SELECT username FROM utenti WHERE id = ? LIMIT 1");
    $stmtUser->execute();
    $username = $stmtUser->get_result()->fetch_assoc()['username'] ?? 'Utente';
    $stmtUser->close();
    
    // 3. Create system message
    createSystemMessage($mysqli, $chatId, 'join', [
        'username' => $username
    ]);
    
    $mysqli->commit();
    
    send_success([
        'chat_id' => $chatId,
        'message' => "Sei entrato nel gruppo."
    ]);

} catch (Throwable $e) {
    $mysqli->rollback();
    send_error($e->getMessage());
}
?>
