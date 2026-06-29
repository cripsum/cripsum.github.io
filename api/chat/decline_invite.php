<?php
// api/chat/decline_invite.php
// Declines a group chat invite.

require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$chatId = isset($input['chat_id']) ? (int)$input['chat_id'] : 0;

if (!$chatId) {
    send_error("ID chat mancante.");
}

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
    $stmtInvite = $mysqli->prepare("UPDATE chat_invites SET status = 'declined', responded_at = NOW() WHERE chat_id = ? AND invitee_id = ? AND status = 'pending'");
    $stmtInvite->bind_param("ii", $chatId, $userId);
    $stmtInvite->execute();
    $stmtInvite->close();
    
    // 2. Set member status to left (declined)
    $stmtMember = $mysqli->prepare("UPDATE chat_members SET status = 'left', left_at = NOW() WHERE chat_id = ? AND user_id = ?");
    $stmtMember->bind_param("ii", $chatId, $userId);
    $stmtMember->execute();
    $stmtMember->close();
    
    $mysqli->commit();
    
    send_success([
        'message' => "Invito rifiutato con successo."
    ]);

} catch (Throwable $e) {
    $mysqli->rollback();
    send_error($e->getMessage());
}
?>
