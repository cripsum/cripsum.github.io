<?php
// api/chat/my_manageable_groups.php
// Returns a list of group chats where the current user can invite the target user.

require_once __DIR__ . '/bootstrap.php';

$targetId = isset($_GET['target_id']) ? (int)$_GET['target_id'] : 0;

if (!$targetId) {
    send_error("ID destinatario mancante.");
}

try {
    // Find all group chats where the current user is active AND can invite members:
    // 1. Current user is owner or admin
    // 2. Or current user is member and settings.invite_permission = 'everyone'
    // AND the target user is NOT already active/invited in the group.
    $query = "
        SELECT 
            c.id AS chat_id,
            c.name
        FROM chat_members m
        INNER JOIN chats c ON c.id = m.chat_id
        INNER JOIN chat_settings s ON s.chat_id = c.id
        WHERE m.user_id = ? 
          AND m.status = 'active'
          AND (m.role IN ('owner', 'admin') OR s.invite_permission = 'everyone')
          AND NOT EXISTS (
              SELECT 1 FROM chat_members m2 
              WHERE m2.chat_id = c.id 
                AND m2.user_id = ? 
                AND m2.status IN ('active', 'invited')
          )
        ORDER BY c.name ASC
    ";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) throw new Exception("Errore di database.");
    
    $stmt->bind_param("ii", $userId, $targetId);
    $stmt->execute();
    $groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    send_success([
        'groups' => $groups
    ]);

} catch (Throwable $e) {
    send_error($e->getMessage(), 500);
}
?>
