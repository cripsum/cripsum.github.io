<?php
// api/chat/promote_admin.php
// Promotes a member to admin (owner only).

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';
require_once __DIR__ . '/../../includes/social_functions.php';

$input = get_json_input();
$chatId = isset($input['chat_id']) ? (int)$input['chat_id'] : 0;
$memberId = isset($input['member_id']) ? (int)$input['member_id'] : 0;

if (!$chatId || !$memberId) {
    send_error("ID chat o ID membro da promuovere mancanti.");
}

$myRole = getChatRole($mysqli, $chatId, $userId);

if ($myRole !== 'owner') {
    send_error("Solo il proprietario del gruppo può promuovere membri ad admin.", 403);
}

// Ensure target is a member and not already admin/owner
$targetRole = getChatRole($mysqli, $chatId, $memberId);

if ($targetRole === null) {
    send_error("L'utente specificato non è un partecipante attivo di questo gruppo.", 404);
}

if ($targetRole === 'admin' || $targetRole === 'owner') {
    send_error("L'utente ha già privilegi amministrativi.", 400);
}

$mysqli->begin_transaction();

try {
    // 1. Get usernames for system message / notification
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
    
    $actorName = $names['actor_name'] ?? 'Proprietario';
    $targetName = $names['target_name'] ?? 'Utente';
    $groupName = $names['group_name'] ?? 'Gruppo';
    
    // 2. Update role
    $stmtPromote = $mysqli->prepare("UPDATE chat_members SET role = 'admin' WHERE chat_id = ? AND user_id = ?");
    $stmtPromote->bind_param("ii", $chatId, $memberId);
    $stmtPromote->execute();
    $stmtPromote->close();
    
    // 3. System message
    createSystemMessage($mysqli, $chatId, 'promote', [
        'actor' => $actorName,
        'target' => $targetName
    ]);
    
    // 4. Send notification
    $titleIt = "Promosso ad admin";
    $titleEn = "Promoted to admin";
    $contentIt = "Sei stato promosso ad amministratore nel gruppo \"$groupName\".";
    $contentEn = "You were promoted to admin in the group \"$groupName\".";
    sendSocialNotification($mysqli, $memberId, $titleIt, $titleEn, $contentIt, $contentEn);
    
    $mysqli->commit();
    
    send_success([
        'member_id' => $memberId,
        'role' => 'admin',
        'message' => "Membro promosso ad admin con successo."
    ]);

} catch (Throwable $e) {
    $mysqli->rollback();
    send_error($e->getMessage());
}
?>
