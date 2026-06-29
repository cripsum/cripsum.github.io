<?php
// api/chat/create_group.php
// Creates a new group chat, registers settings, and invites initial members.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';
require_once __DIR__ . '/../../includes/social_functions.php';

$input = get_json_input();
$name = isset($input['name']) ? trim((string)$input['name']) : '';
$description = isset($input['description']) ? trim((string)$input['description']) : null;
$invitedUsers = isset($input['invited_users']) && is_array($input['invited_users']) ? $input['invited_users'] : [];
$privacy = isset($input['privacy']) ? trim((string)$input['privacy']) : 'group_private';

if ($name === '') {
    send_error("Il nome del gruppo è obbligatorio.");
}

if (strlen($name) > 100) {
    send_error("Il nome del gruppo non può superare i 100 caratteri.");
}

// Clean invited user IDs
$inviteeIds = [];
foreach ($invitedUsers as $id) {
    $id = (int)$id;
    if ($id > 0 && $id !== $userId && !in_array($id, $inviteeIds)) {
        $inviteeIds[] = $id;
    }
}

// Check at least 2 members total (creator + at least 1 invitee)
if (count($inviteeIds) === 0) {
    send_error("Seleziona almeno un utente da invitare per creare il gruppo.");
}

$mysqli->begin_transaction();

try {
    // 1. Create the Group entry
    $avatarUrl = null; // Standard default avatar initially
    $stmtChat = $mysqli->prepare("
        INSERT INTO chats (type, name, description, avatar_url, created_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    if (!$stmtChat) throw new Exception("Errore interno del server.");
    $stmtChat->bind_param("ssssi", $privacy, $name, $description, $avatarUrl, $userId);
    $stmtChat->execute();
    $chatId = $mysqli->insert_id;
    $stmtChat->close();
    
    if (!$chatId) throw new Exception("Impossibile creare il gruppo.");
    
    // 2. Insert creator as Active Owner
    $roleOwner = 'owner';
    $statusActive = 'active';
    $stmtOwner = $mysqli->prepare("
        INSERT INTO chat_members (chat_id, user_id, role, status, joined_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    if (!$stmtOwner) throw new Exception("Errore di database.");
    $stmtOwner->bind_param("iiss", $chatId, $userId, $roleOwner, $statusActive);
    $stmtOwner->execute();
    $stmtOwner->close();
    
    // 3. Create settings for permissions
    $invitePerm = 'everyone';
    $editInfoPerm = 'owner_admins';
    $msgPerm = 'members';
    $approvalReq = 0;
    
    $stmtSettings = $mysqli->prepare("
        INSERT INTO chat_settings (chat_id, invite_permission, edit_info_permission, message_permission, approval_required)
        VALUES (?, ?, ?, ?, ?)
    ");
    if (!$stmtSettings) throw new Exception("Errore di database.");
    $stmtSettings->bind_param("isssi", $chatId, $invitePerm, $editInfoPerm, $msgPerm, $approvalReq);
    $stmtSettings->execute();
    $stmtSettings->close();
    
    // Get creator's username for templates
    $stmtUser = $mysqli->prepare("SELECT username FROM utenti WHERE id = ? LIMIT 1");
    $stmtUser->bind_param("i", $userId);
    $stmtUser->execute();
    $creatorUsername = $stmtUser->get_result()->fetch_assoc()['username'] ?? 'Utente';
    $stmtUser->close();
    
    // 4. Invite users
    $roleMember = 'member';
    $statusInvited = 'invited';
    $statusPending = 'pending';
    
    $stmtMember = $mysqli->prepare("
        INSERT INTO chat_members (chat_id, user_id, role, status)
        VALUES (?, ?, ?, ?)
    ");
    
    $stmtInvite = $mysqli->prepare("
        INSERT INTO chat_invites (chat_id, inviter_id, invitee_id, status)
        VALUES (?, ?, ?, ?)
    ");
    
    if (!$stmtMember || !$stmtInvite) throw new Exception("Errore di database.");
    
    foreach ($inviteeIds as $inviteeId) {
        // Validate target privacy and blocks
        $rel = getRelationshipStatus($mysqli, $userId, $inviteeId);
        if ($rel['is_blocked_by_viewer'] || $rel['has_blocked_viewer']) {
            continue; // Skip blocked
        }
        
        // Add member state
        $stmtMember->bind_param("iiss", $chatId, $inviteeId, $roleMember, $statusInvited);
        $stmtMember->execute();
        
        // Add invite tracker
        $stmtInvite->bind_param("iiis", $chatId, $userId, $inviteeId, $statusPending);
        $stmtInvite->execute();
        
        // Send Notification
        $titleIt = "Invito a un gruppo";
        $titleEn = "Group Invitation";
        $contentIt = "@$creatorUsername ti ha invitato ad unirti al gruppo \"$name\".";
        $contentEn = "@$creatorUsername invited you to join the group \"$name\".";
        sendSocialNotification($mysqli, $inviteeId, $titleIt, $titleEn, $contentIt, $contentEn);
    }
    
    $stmtMember->close();
    $stmtInvite->close();
    
    // 5. Create "group created" system message
    createSystemMessage($mysqli, $chatId, 'create');
    
    $mysqli->commit();
    
    send_success([
        'chat_id' => $chatId,
        'message' => "Gruppo creato con successo."
    ]);

} catch (Throwable $e) {
    $mysqli->rollback();
    send_error($e->getMessage());
}
?>
