<?php
// api/chat/remove_member.php
// Removes/Kicks a member from the group chat (owner/admin only).

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';
require_once __DIR__ . '/../../includes/social_functions.php';

$input = get_json_input();
$chatId = isset($input['chat_id']) ? (int)$input['chat_id'] : 0;
$memberId = isset($input['member_id']) ? (int)$input['member_id'] : 0;

if (!$chatId || !$memberId) {
    send_error("ID chat o ID membro da rimuovere mancanti.");
}

if (!canRemoveMember($mysqli, $chatId, $userId, $memberId)) {
    send_error("Non sei autorizzato a rimuovere questo partecipante dal gruppo.", 403);
}

$mysqli->begin_transaction();

try {
    // 1. Get usernames for the system message
    $stmtNames = $mysqli->prepare("
        SELECT 
            (SELECT username FROM utenti WHERE id = ?) AS actor_name,
            (SELECT username FROM utenti WHERE id = ?) AS target_name,
            (SELECT name FROM chats WHERE id = ?) AS group_name
    ");
    $stmtNames->bind_param("iii", $userId, $memberId, $chatId);
    $stmtNames->execute();
    $names = $stmtNames->get_result()->fetch_assoc();
    $stmtNames->close();
    
    $actorName = $names['actor_name'] ?? 'Utente';
    $targetName = $names['target_name'] ?? 'Utente';
    $groupName = $names['group_name'] ?? 'Gruppo';
    
    // 2. Update member status to removed
    $stmtRemove = $mysqli->prepare("UPDATE chat_members SET status = 'removed', left_at = NOW() WHERE chat_id = ? AND user_id = ?");
    $stmtRemove->bind_param("ii", $chatId, $memberId);
    $stmtRemove->execute();
    $stmtRemove->close();
    
    // 3. Create system message
    createSystemMessage($mysqli, $chatId, 'remove', [
        'actor' => $actorName,
        'target' => $targetName
    ]);
    
    // 4. Send notification
    $titleIt = "Rimosso dal gruppo";
    $titleEn = "Removed from group";
    $contentIt = "Sei stato rimosso dal gruppo \"$groupName\" da @$actorName.";
    $contentEn = "You were removed from the group \"$groupName\" by @$actorName.";
    sendSocialNotification($mysqli, $memberId, $titleIt, $titleEn, $contentIt, $contentEn);
    
    $mysqli->commit();
    
    send_success([
        'member_id' => $memberId,
        'message' => "Partecipante rimosso con successo."
    ]);

} catch (Throwable $e) {
    $mysqli->rollback();
    send_error($e->getMessage());
}
?>
