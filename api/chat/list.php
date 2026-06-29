<?php
// api/chat/list.php
// Returns active conversations (group + private) and pending group invites.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

try {
    // 1. Get Group Chats
    $groups = getUserChats($mysqli, $userId);

    // 2. Get Pending Group Invites
    $invitesQuery = "
        SELECT 
            i.id AS invite_id,
            i.chat_id,
            i.inviter_id,
            i.created_at AS invited_at,
            c.name AS chat_name,
            c.description AS chat_description,
            c.avatar_url AS chat_avatar,
            u.username AS inviter_username,
            u.display_name AS inviter_display_name
        FROM chat_invites i
        INNER JOIN chats c ON c.id = i.chat_id
        INNER JOIN utenti u ON u.id = i.inviter_id
        WHERE i.invitee_id = ? AND i.status = 'pending'
    ";
    $stmtInv = $mysqli->prepare($invitesQuery);
    $invites = [];
    if ($stmtInv) {
        $stmtInv->bind_param("i", $userId);
        $stmtInv->execute();
        $invites = $stmtInv->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtInv->close();
    }
    
    // 3. Get Private Chats (Old Tables)
    // We can query the private conversations here so the client gets a fully unified view
    $privateQuery = "
        SELECT 
            c.id AS conversation_id,
            c.is_group,
            cp.is_muted,
            cp.is_archived,
            cp.nickname,
            cp.theme_color,
            cp.theme_bg,
            cp.favorite_emoji,
            (SELECT COUNT(*) FROM private_messages pm 
             WHERE pm.conversation_id = c.id 
               AND pm.id > COALESCE(cp.last_read_message_id, 0)
               AND pm.sender_id != ?
               AND pm.deleted_at IS NULL
               AND NOT EXISTS (SELECT 1 FROM private_message_deleted pmd WHERE pmd.message_id = pm.id AND pmd.user_id = ?)
            ) AS unread_count,
            EXISTS(SELECT 1 FROM private_conversation_pins pin WHERE pin.user_id = ? AND pin.conversation_id = c.id) AS is_pinned,
            
            other_u.id AS other_user_id,
            other_u.username AS other_username,
            other_u.display_name AS other_display_name,
            other_cp.nickname AS other_nickname,
            
            last_m.id AS last_message_id,
            last_m.sender_id AS last_message_sender_id,
            last_m.message AS last_message_text,
            last_m.message_type AS last_message_type,
            last_m.created_at AS last_message_time,
            last_m.deleted_for_all AS last_message_deleted_for_all
        FROM private_conversation_participants cp
        INNER JOIN private_conversations c ON c.id = cp.conversation_id
        INNER JOIN private_conversation_participants other_cp ON other_cp.conversation_id = c.id AND other_cp.user_id != ?
        INNER JOIN utenti other_u ON other_u.id = other_cp.user_id
        LEFT JOIN (
            SELECT pm1.*
            FROM private_messages pm1
            INNER JOIN (
                SELECT conversation_id, MAX(id) as max_id
                FROM private_messages
                WHERE deleted_at IS NULL
                GROUP BY conversation_id
            ) pm2 ON pm1.id = pm2.max_id
        ) last_m ON last_m.conversation_id = c.id
        WHERE cp.user_id = ? AND cp.is_archived = 0
        ORDER BY is_pinned DESC, COALESCE(last_m.created_at, c.created_at) DESC
    ";
    
    $stmtPriv = $mysqli->prepare($privateQuery);
    $privates = [];
    if ($stmtPriv) {
        $stmtPriv->bind_param("iiiii", $userId, $userId, $userId, $userId, $userId);
        $stmtPriv->execute();
        $privates = $stmtPriv->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtPriv->close();
    }
    
    // Check real-time online status of private chats
    foreach ($privates as &$p) {
        $p['conversation_id'] = (int)$p['conversation_id'];
        $p['other_user_id'] = (int)$p['other_user_id'];
        $p['unread_count'] = (int)$p['unread_count'];
        $p['is_pinned'] = (bool)$p['is_pinned'];
        $p['is_muted'] = (bool)$p['is_muted'];
        
        $p['is_online'] = false;
        $stmtAct = $mysqli->prepare("SELECT ultimo_accesso FROM utenti WHERE id = ? LIMIT 1");
        if ($stmtAct) {
            $stmtAct->bind_param("i", $p['other_user_id']);
            $stmtAct->execute();
            $resAct = $stmtAct->get_result();
            if ($rowAct = $resAct->fetch_assoc()) {
                $lastAct = $rowAct['ultimo_accesso'] ? strtotime($rowAct['ultimo_accesso']) : 0;
                $p['is_online'] = (time() - $lastAct) < 180;
            }
            $stmtAct->close();
        }
    }
    unset($p);

    send_success([
        'groups' => $groups,
        'invites' => $invites,
        'privates' => $privates
    ]);

} catch (Throwable $e) {
    send_error("Impossibile caricare le chat: " . $e->getMessage(), 500);
}
?>
