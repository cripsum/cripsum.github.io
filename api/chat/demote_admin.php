<?php
// api/chat/demote_admin.php
// Demotes an admin back to member (owner only).

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';
require_once __DIR__ . '/../../includes/social_functions.php';

$input = get_json_input();
$chatId = isset($input['chat_id']) ? (int)$input['chat_id'] : 0;
$memberId = isset($input['member_id']) ? (int)$input['member_id'] : 0;

if (!$chatId || !$memberId) {
    send_error("ID chat o ID membro da degradare mancanti.");
}

$myRole = getChatRole($mysqli, $chatId, $userId);

if ($myRole !== 'owner') {
    send_error("Solo il proprietario del gruppo può rimuovere i privilegi di admin.", 403);
}

$targetRole = getChatRole($mysqli, $chatId, $memberId);

if ($targetRole === null) {
    send_error("L'utente specificato non è un partecipante attivo di questo gruppo.", 404);
}

if ($targetRole !== 'admin') {
    send_error("L'utente non è un amministratore.", 400);
}

$mysqli->begin_transaction();

try {
    // 1. Get usernames
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
    
    // 2. Update role to member
    $stmtDemote = $mysqli->prepare("UPDATE chat_members SET role = 'member' WHERE chat_id = ? AND user_id = ?");
    $stmtDemote->bind_param("ii", $chatId, $memberId);
    $stmtDemote->execute();
    $stmtDemote->close();
    
    // 3. System message
    createSystemMessage($mysqli, $chatId, 'demote', [
        'actor' => $actorName,
        'target' => $targetName
    ]);
    
    // 4. Send notification
    $titleIt = "Rimosso da admin";
    $titleEn = "Removed from admin";
    $contentIt = "I tuoi privilegi amministrativi nel gruppo \"$groupName\" sono stati revocati.";
    $contentEn = "Your admin status in group \"$groupName\" has been revoked.";
    sendSocialNotification($mysqli, $memberId, $titleIt, $titleEn, $contentIt, $contentEn);
    
    $mysqli->commit();
    
    send_success([
        'member_id' => $memberId,
        'role' => 'member',
        'message' => "Membro degradato a partecipante con successo."
    ]);

} catch (Throwable $e) {
    $mysqli->rollback();
    send_error($e->getMessage());
}
?>
