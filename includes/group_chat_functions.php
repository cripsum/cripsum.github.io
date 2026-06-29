<?php
// includes/group_chat_functions.php
// Central library for Cripsum™ Group Chat backend authorization and utilities.

/**
 * Returns group chats where the user is an active participant, plus pending invitations.
 */
function getUserChats($mysqli, $userId, $limit = 50) {
    $userId = (int)$userId;
    $limit = (int)$limit;
    
    $query = "
        SELECT 
            c.id AS chat_id,
            c.type,
            c.name,
            c.description,
            c.avatar_url,
            c.created_by,
            c.created_at,
            c.updated_at,
            c.last_message_id,
            c.last_message_at,
            m.role,
            m.status,
            m.notification_level,
            m.muted_until,
            -- Calculate unread messages
            (SELECT COUNT(*) FROM chat_messages msg 
             WHERE msg.chat_id = c.id 
               AND msg.id > COALESCE(m.last_read_message_id, 0)
               AND msg.deleted_at IS NULL
            ) AS unread_count,
            
            -- Last message details
            lm.body AS last_message_body,
            lm.message_type AS last_message_type,
            lm.created_at AS last_message_time,
            lm.sender_id AS last_message_sender_id,
            u.username AS last_message_sender_username
        FROM chat_members m
        INNER JOIN chats c ON c.id = m.chat_id
        LEFT JOIN chat_messages lm ON lm.id = c.last_message_id
        LEFT JOIN utenti u ON u.id = lm.sender_id
        WHERE m.user_id = ? AND m.status IN ('active', 'invited') AND c.is_archived = 0
        ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
        LIMIT ?
    ";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) return [];
    
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $chats = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Parse formatting and active states
    foreach ($chats as &$chat) {
        $chat['chat_id'] = (int)$chat['chat_id'];
        $chat['created_by'] = (int)$chat['created_by'];
        $chat['last_message_id'] = $chat['last_message_id'] ? (int)$chat['last_message_id'] : null;
        $chat['unread_count'] = (int)$chat['unread_count'];
        $chat['is_muted'] = false;
        if ($chat['muted_until'] && strtotime($chat['muted_until']) > time()) {
            $chat['is_muted'] = true;
        }
    }
    unset($chat);
    
    return $chats;
}

/**
 * Fetch a single chat metadata
 */
function getChatById($mysqli, $chatId) {
    $chatId = (int)$chatId;
    $stmt = $mysqli->prepare("SELECT * FROM chats WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $chat = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $chat;
}

/**
 * Fetch active/invited list of members
 */
function getChatMembers($mysqli, $chatId) {
    $chatId = (int)$chatId;
    $query = "
        SELECT 
            m.user_id,
            m.role,
            m.status,
            m.joined_at,
            u.username,
            u.display_name,
            u.ultimo_accesso
        FROM chat_members m
        INNER JOIN utenti u ON u.id = m.user_id
        WHERE m.chat_id = ? AND m.status IN ('active', 'invited')
        ORDER BY CASE m.role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 ELSE 3 END, u.username ASC
    ";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) return [];
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $res = $stmt->get_result();
    $members = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($members as &$m) {
        $m['user_id'] = (int)$m['user_id'];
        $lastAct = $m['ultimo_accesso'] ? strtotime($m['ultimo_accesso']) : 0;
        $m['is_online'] = (time() - $lastAct) < 180;
        unset($m['ultimo_accesso']);
    }
    unset($m);
    
    return $members;
}

/**
 * Checks if a user is an active member
 */
function isChatMember($mysqli, $chatId, $userId) {
    $chatId = (int)$chatId;
    $userId = (int)$userId;
    $stmt = $mysqli->prepare("SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ? AND status = 'active' LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param("ii", $chatId, $userId);
    $stmt->execute();
    $isMember = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $isMember;
}

/**
 * Returns user role in chat
 */
function getChatRole($mysqli, $chatId, $userId) {
    $chatId = (int)$chatId;
    $userId = (int)$userId;
    $stmt = $mysqli->prepare("SELECT role FROM chat_members WHERE chat_id = ? AND user_id = ? AND status = 'active' LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("ii", $chatId, $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ? $res['role'] : null;
}

/**
 * Checks if user has permission to view chat
 */
function canViewChat($mysqli, $chatId, $userId) {
    $chatId = (int)$chatId;
    $userId = (int)$userId;
    
    // Checks if user is active member or invited
    $stmt = $mysqli->prepare("SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ? AND status IN ('active', 'invited') LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param("ii", $chatId, $userId);
    $stmt->execute();
    $canView = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $canView;
}

/**
 * Validates if the user is a member and group is not in restricted message mode
 */
function canSendMessage($mysqli, $chatId, $userId) {
    $chatId = (int)$chatId;
    $userId = (int)$userId;
    
    // Must be active member
    $stmtMember = $mysqli->prepare("SELECT role FROM chat_members WHERE chat_id = ? AND user_id = ? AND status = 'active' LIMIT 1");
    if (!$stmtMember) return false;
    $stmtMember->bind_param("ii", $chatId, $userId);
    $stmtMember->execute();
    $member = $stmtMember->get_result()->fetch_assoc();
    $stmtMember->close();
    
    if (!$member) return false;
    
    // Check settings restriction
    $stmtSettings = $mysqli->prepare("SELECT message_permission FROM chat_settings WHERE chat_id = ? LIMIT 1");
    if (!$stmtSettings) return true; // default true
    $stmtSettings->bind_param("i", $chatId);
    $stmtSettings->execute();
    $settings = $stmtSettings->get_result()->fetch_assoc();
    $stmtSettings->close();
    
    if ($settings && $settings['message_permission'] === 'admins_only') {
        return in_array($member['role'], ['owner', 'admin'], true);
    }
    
    return true;
}

/**
 * Checks if inviter can invite target user
 */
function canInviteUser($mysqli, $chatId, $inviterId, $inviteeId) {
    $chatId = (int)$chatId;
    $inviterId = (int)$inviterId;
    $inviteeId = (int)$inviteeId;
    
    if ($inviterId === $inviteeId) return false;
    
    // Check block list relations
    require_once __DIR__ . '/social_functions.php';
    $rel = getRelationshipStatus($mysqli, $inviterId, $inviteeId);
    if ($rel['is_blocked_by_viewer'] || $rel['has_blocked_viewer']) {
        return false;
    }
    
    // Validate inviter is active member
    $stmtInviter = $mysqli->prepare("SELECT role FROM chat_members WHERE chat_id = ? AND user_id = ? AND status = 'active' LIMIT 1");
    if (!$stmtInviter) return false;
    $stmtInviter->bind_param("ii", $chatId, $inviterId);
    $stmtInviter->execute();
    $inviter = $stmtInviter->get_result()->fetch_assoc();
    $stmtInviter->close();
    
    if (!$inviter) return false;
    
    // Check invite permissions from settings
    $stmtSettings = $mysqli->prepare("SELECT invite_permission FROM chat_settings WHERE chat_id = ? LIMIT 1");
    if ($stmtSettings) {
        $stmtSettings->bind_param("i", $chatId);
        $stmtSettings->execute();
        $settings = $stmtSettings->get_result()->fetch_assoc();
        $stmtSettings->close();
        
        if ($settings && $settings['invite_permission'] === 'owner_admins') {
            if (!in_array($inviter['role'], ['owner', 'admin'], true)) {
                return false;
            }
        }
    }
    
    // Ensure invitee is not already an active member or invited
    $stmtInvitee = $mysqli->prepare("SELECT status FROM chat_members WHERE chat_id = ? AND user_id = ? LIMIT 1");
    if ($stmtInvitee) {
        $stmtInvitee->bind_param("ii", $chatId, $inviteeId);
        $stmtInvitee->execute();
        $invitee = $stmtInvitee->get_result()->fetch_assoc();
        $stmtInvitee->close();
        
        if ($invitee && in_array($invitee['status'], ['active', 'invited'], true)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Checks if actor can manage group info (name, avatar, description)
 */
function canManageChat($mysqli, $chatId, $userId) {
    $chatId = (int)$chatId;
    $userId = (int)$userId;
    
    $stmtMember = $mysqli->prepare("SELECT role FROM chat_members WHERE chat_id = ? AND user_id = ? AND status = 'active' LIMIT 1");
    if (!$stmtMember) return false;
    $stmtMember->bind_param("ii", $chatId, $userId);
    $stmtMember->execute();
    $member = $stmtMember->get_result()->fetch_assoc();
    $stmtMember->close();
    
    if (!$member) return false;
    if ($member['role'] === 'owner') return true;
    
    $stmtSettings = $mysqli->prepare("SELECT edit_info_permission FROM chat_settings WHERE chat_id = ? LIMIT 1");
    if ($stmtSettings) {
        $stmtSettings->bind_param("i", $chatId);
        $stmtSettings->execute();
        $settings = $stmtSettings->get_result()->fetch_assoc();
        $stmtSettings->close();
        
        if ($settings && $settings['edit_info_permission'] === 'everyone') {
            return true;
        }
    }
    
    return ($member['role'] === 'admin');
}

/**
 * Checks if actor can kick a target member
 */
function canRemoveMember($mysqli, $chatId, $actorId, $targetId) {
    $chatId = (int)$chatId;
    $actorId = (int)$actorId;
    $targetId = (int)$targetId;
    
    if ($actorId === $targetId) return false;
    
    $stmtActor = $mysqli->prepare("SELECT role FROM chat_members WHERE chat_id = ? AND user_id = ? AND status = 'active' LIMIT 1");
    if (!$stmtActor) return false;
    $stmtActor->bind_param("ii", $chatId, $actorId);
    $stmtActor->execute();
    $actor = $stmtActor->get_result()->fetch_assoc();
    $stmtActor->close();
    
    if (!$actor) return false;
    if (!in_array($actor['role'], ['owner', 'admin'], true)) return false;
    
    $stmtTarget = $mysqli->prepare("SELECT role FROM chat_members WHERE chat_id = ? AND user_id = ? AND status = 'active' LIMIT 1");
    if (!$stmtTarget) return false;
    $stmtTarget->bind_param("ii", $chatId, $targetId);
    $stmtTarget->execute();
    $target = $stmtTarget->get_result()->fetch_assoc();
    $stmtTarget->close();
    
    if (!$target) return true; // Kick pending/left is allowed
    
    // Owners can remove anyone, admins can only remove normal members
    if ($actor['role'] === 'owner') return true;
    if ($actor['role'] === 'admin' && $target['role'] === 'member') return true;
    
    return false;
}

/**
 * Checks message edit window (15 mins)
 */
function canEditMessage($mysqli, $messageId, $userId) {
    $messageId = (int)$messageId;
    $userId = (int)$userId;
    
    $stmt = $mysqli->prepare("SELECT sender_id, created_at, message_type FROM chat_messages WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param("i", $messageId);
    $stmt->execute();
    $msg = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$msg) return false;
    if ((int)$msg['sender_id'] !== $userId) return false;
    if ($msg['message_type'] !== 'text') return false; // cannot edit system messages
    
    $created = strtotime($msg['created_at']);
    if (time() - $created > 900) { // 15 mins
        return false;
    }
    
    return true;
}

/**
 * Checks message delete permission
 */
function canDeleteGroupMessage($mysqli, $messageId, $userId, $userRole) {
    $messageId = (int)$messageId;
    $userId = (int)$userId;
    
    $stmt = $mysqli->prepare("SELECT sender_id, chat_id FROM chat_messages WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param("i", $messageId);
    $stmt->execute();
    $msg = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$msg) return false;
    
    // Author can delete
    if ((int)$msg['sender_id'] === $userId) return true;
    
    // Site admins/owners can delete
    if (in_array($userRole, ['admin', 'owner'], true)) return true;
    
    // Group owner/admin can delete
    $groupRole = getChatRole($mysqli, (int)$msg['chat_id'], $userId);
    return in_array($groupRole, ['owner', 'admin'], true);
}

/**
 * Appends system message to the chat
 */
function createSystemMessage($mysqli, $chatId, $type, $metadata = []) {
    $chatId = (int)$chatId;
    $senderId = 0; // System sender ID
    
    $body = "";
    $metaJson = json_encode($metadata);
    
    // Let's create localized templates for system events
    switch ($type) {
        case 'create':
            $body = "Il gruppo è stato creato.";
            break;
        case 'join':
            $user = $metadata['username'] ?? 'Un utente';
            $body = "@$user è entrato nel gruppo.";
            break;
        case 'leave':
            $user = $metadata['username'] ?? 'Un utente';
            $body = "@$user ha lasciato il gruppo.";
            break;
        case 'invite':
            $inviter = $metadata['inviter'] ?? 'Qualcuno';
            $invitee = $metadata['invitee'] ?? 'qualcuno';
            $body = "@$inviter ha invitato @$invitee.";
            break;
        case 'remove':
            $actor = $metadata['actor'] ?? 'Qualcuno';
            $target = $metadata['target'] ?? 'qualcuno';
            $body = "@$actor ha rimosso @$target dal gruppo.";
            break;
        case 'rename':
            $user = $metadata['username'] ?? 'Qualcuno';
            $newName = $metadata['new_name'] ?? 'nuovo nome';
            $body = "@$user ha rinominato il gruppo in \"$newName\".";
            break;
        case 'avatar':
            $user = $metadata['username'] ?? 'Qualcuno';
            $body = "@$user ha cambiato l'immagine del gruppo.";
            break;
        case 'promote':
            $actor = $metadata['actor'] ?? 'Qualcuno';
            $target = $metadata['target'] ?? 'qualcuno';
            $body = "@$actor ha promosso @$target ad admin.";
            break;
        case 'demote':
            $actor = $metadata['actor'] ?? 'Qualcuno';
            $target = $metadata['target'] ?? 'qualcuno';
            $body = "@$actor ha rimosso i privilegi di admin a @$target.";
            break;
    }
    
    $stmt = $mysqli->prepare("
        INSERT INTO chat_messages (chat_id, sender_id, body, message_type, metadata_json) 
        VALUES (?, ?, ?, 'system', ?)
    ");
    if (!$stmt) return false;
    
    $stmt->bind_param("iiss", $chatId, $senderId, $body, $metaJson);
    $ok = $stmt->execute();
    $messageId = $mysqli->insert_id;
    $stmt->close();
    
    if ($ok && $messageId) {
        // Aggiorna l'ultimo messaggio del gruppo
        $mysqli->query("UPDATE chats SET last_message_id = $messageId, last_message_at = NOW() WHERE id = $chatId");
    }
    
    return $messageId;
}

/**
 * Updates unread pointer
 */
function markChatAsRead($mysqli, $chatId, $userId, $messageId = null) {
    $chatId = (int)$chatId;
    $userId = (int)$userId;
    
    if ($messageId === null) {
        $stmtMax = $mysqli->prepare("SELECT MAX(id) FROM chat_messages WHERE chat_id = ? AND deleted_at IS NULL");
        if ($stmtMax) {
            $stmtMax->bind_param("i", $chatId);
            $stmtMax->execute();
            $stmtMax->bind_result($messageId);
            $stmtMax->fetch();
            $stmtMax->close();
        }
    }
    
    if (!$messageId) return false;
    
    $messageId = (int)$messageId;
    $stmt = $mysqli->prepare("UPDATE chat_members SET last_read_message_id = ?, last_read_at = NOW() WHERE chat_id = ? AND user_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("iii", $messageId, $chatId, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Count unreads
 */
function getUnreadCount($mysqli, $chatId, $userId) {
    $chatId = (int)$chatId;
    $userId = (int)$userId;
    
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) FROM chat_messages msg
        INNER JOIN chat_members m ON m.chat_id = msg.chat_id
        WHERE m.chat_id = ? AND m.user_id = ?
          AND msg.id > COALESCE(m.last_read_message_id, 0)
          AND msg.deleted_at IS NULL
    ");
    if (!$stmt) return 0;
    
    $stmt->bind_param("ii", $chatId, $userId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    
    return (int)$count;
}
?>
