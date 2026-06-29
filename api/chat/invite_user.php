<?php
// api/chat/invite_user.php
// Invites a user to join an existing group chat.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';
require_once __DIR__ . '/../../includes/social_functions.php';

$input = get_json_input();
$chatId = isset($input['chat_id']) ? (int)$input['chat_id'] : 0;
$inviteeId = isset($input['invitee_id']) ? (int)$input['invitee_id'] : 0;

if (!$chatId || !$inviteeId) {
    send_error("ID chat o ID invitato mancanti.");
}

if (!canInviteUser($mysqli, $chatId, $userId, $inviteeId)) {
    send_error("Impossibile invitare questo utente. Controlla le impostazioni di privacy del gruppo o i blocchi.", 403);
}

$mysqli->begin_transaction();

try {
    // 1. Get usernames for system message / notification templates
    $stmtNames = $mysqli->prepare("
        SELECT 
            (SELECT username FROM utenti WHERE id = ?) AS inviter_name,
            (SELECT username FROM utenti WHERE id = ?) AS invitee_name,
            (SELECT name FROM chats WHERE id = ?) AS group_name
    ");
    $stmtNames->bind_param("iii", $userId, $inviteeId, $chatId);
    $stmtNames->execute();
    $names = $stmtNames->get_result()->fetch_assoc();
    $stmtNames->close();
    
    $inviterName = $names['inviter_name'] ?? 'Utente';
    $inviteeName = $names['invitee_name'] ?? 'Utente';
    $groupName = $names['group_name'] ?? 'Gruppo';
    
    // 2. Add member state (upsert, in case they left earlier and are re-invited)
    $stmtUpsert = $mysqli->prepare("
        INSERT INTO chat_members (chat_id, user_id, role, status)
        VALUES (?, ?, 'member', 'invited')
        ON DUPLICATE KEY UPDATE status = 'invited', role = 'member', joined_at = NULL, left_at = NULL
    ");
    if (!$stmtUpsert) throw new Exception("Errore di database.");
    $stmtUpsert->bind_param("ii", $chatId, $inviteeId);
    $stmtUpsert->execute();
    $stmtUpsert->close();
    
    // 3. Add invite log
    $stmtInvite = $mysqli->prepare("
        INSERT INTO chat_invites (chat_id, inviter_id, invitee_id, status)
        VALUES (?, ?, ?, 'pending')
    ");
    if (!$stmtInvite) throw new Exception("Errore di database.");
    $stmtInvite->bind_param("iii", $chatId, $userId, $inviteeId);
    $stmtInvite->execute();
    $stmtInvite->close();
    
    // 4. Create system message
    createSystemMessage($mysqli, $chatId, 'invite', [
        'inviter' => $inviterName,
        'invitee' => $inviteeName
    ]);
    
    // 5. Send notification
    $titleIt = "Invito a un gruppo";
    $titleEn = "Group Invitation";
    $contentIt = "@$inviterName ti ha invitato ad unirti al gruppo \"$groupName\".";
    $contentEn = "@$inviterName invited you to join the group \"$groupName\".";
    sendSocialNotification($mysqli, $inviteeId, $titleIt, $titleEn, $contentIt, $contentEn);
    
    $mysqli->commit();
    
    send_success(['message' => "Invito inviato con successo."]);

} catch (Throwable $e) {
    $mysqli->rollback();
    send_error($e->getMessage());
}
?>
