<?php
// api/chat/cancel_invite.php
// Cancels a pending group chat invite.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

$input = get_json_input();
$chatId = isset($input['chat_id']) ? (int)$input['chat_id'] : 0;
$inviteeId = isset($input['invitee_id']) ? (int)$input['invitee_id'] : 0;

if (!$chatId || !$inviteeId) {
    send_error("ID chat o ID invitato mancanti.");
}

// Verify that the viewer is owner, admin, or the original inviter
$stmtCheck = $mysqli->prepare("SELECT inviter_id FROM chat_invites WHERE chat_id = ? AND invitee_id = ? AND status = 'pending' LIMIT 1");
if (!$stmtCheck) {
    send_error("Errore interno.");
}
$stmtCheck->bind_param("ii", $chatId, $inviteeId);
$stmtCheck->execute();
$inviteRow = $stmtCheck->get_result()->fetch_assoc();
$stmtCheck->close();

if (!$inviteRow) {
    send_error("Invito pendente non trovato.", 404);
}

$inviterId = (int)$inviteRow['inviter_id'];
$myRole = getChatRole($mysqli, $chatId, $userId);

if ($userId !== $inviterId && !in_array($myRole, ['owner', 'admin'], true)) {
    send_error("Non sei autorizzato ad annullare questo invito.", 403);
}

$mysqli->begin_transaction();

try {
    // 1. Cancel the invite
    $stmtInvite = $mysqli->prepare("UPDATE chat_invites SET status = 'cancelled', responded_at = NOW() WHERE chat_id = ? AND invitee_id = ? AND status = 'pending'");
    $stmtInvite->bind_param("ii", $chatId, $inviteeId);
    $stmtInvite->execute();
    $stmtInvite->close();
    
    // 2. Clear member row status
    $stmtMember = $mysqli->prepare("UPDATE chat_members SET status = 'left', left_at = NOW() WHERE chat_id = ? AND user_id = ?");
    $stmtMember->bind_param("ii", $chatId, $inviteeId);
    $stmtMember->execute();
    $stmtMember->close();
    
    $mysqli->commit();
    
    send_success([
        'message' => "Invito annullato con successo."
    ]);

} catch (Throwable $e) {
    $mysqli->rollback();
    send_error($e->getMessage());
}
?>
