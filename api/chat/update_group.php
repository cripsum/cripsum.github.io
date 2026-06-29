<?php
// api/chat/update_group.php
// Updates group metadata and permissions.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

$input = get_json_input();
$chatId = isset($input['chat_id']) ? (int)$input['chat_id'] : 0;

if (!$chatId) {
    send_error("ID chat mancante o non valido.");
}

if (!canManageChat($mysqli, $chatId, $userId)) {
    send_error("Non hai i permessi per modificare le impostazioni di questo gruppo.", 403);
}

$chat = getChatById($mysqli, $chatId);
if (!$chat) {
    send_error("Gruppo non trovato.", 404);
}

$mysqli->begin_transaction();

try {
    // 1. Update Name/Description
    $name = isset($input['name']) ? trim((string)$input['name']) : '';
    $description = isset($input['description']) ? trim((string)$input['description']) : null;
    
    if ($name !== '' && $name !== $chat['name']) {
        if (strlen($name) > 100) {
            throw new Exception("Il nome del gruppo non può superare i 100 caratteri.");
        }
        
        $stmtUpdate = $mysqli->prepare("UPDATE chats SET name = ?, description = ? WHERE id = ?");
        $stmtUpdate->bind_param("ssi", $name, $description, $chatId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
        
        // System message for rename
        $stmtUser = $mysqli->prepare("SELECT username FROM utenti WHERE id = ? LIMIT 1");
        if ($stmtUser) {
            $stmtUser->bind_param("i", $userId);
            $stmtUser->execute();
            $username = $stmtUser->get_result()->fetch_assoc()['username'] ?? 'Utente';
            $stmtUser->close();
        } else {
            $username = 'Utente';
        }
        
        createSystemMessage($mysqli, $chatId, 'rename', [
            'username' => $username,
            'new_name' => $name
        ]);
    } else {
        // Just description
        $stmtUpdate = $mysqli->prepare("UPDATE chats SET description = ? WHERE id = ?");
        $stmtUpdate->bind_param("si", $description, $chatId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }
    
    // 2. Update Settings
    $invitePerm = isset($input['invite_permission']) ? trim((string)$input['invite_permission']) : null;
    $editInfoPerm = isset($input['edit_info_permission']) ? trim((string)$input['edit_info_permission']) : null;
    $msgPerm = isset($input['message_permission']) ? trim((string)$input['message_permission']) : null;
    $approvalReq = isset($input['approval_required']) ? (int)$input['approval_required'] : null;
    
    // Validate settings parameters
    if ($invitePerm && !in_array($invitePerm, ['everyone', 'owner_admins'], true)) $invitePerm = null;
    if ($editInfoPerm && !in_array($editInfoPerm, ['everyone', 'owner_admins'], true)) $editInfoPerm = null;
    if ($msgPerm && !in_array($msgPerm, ['members', 'admins_only'], true)) $msgPerm = null;
    if ($approvalReq !== null) $approvalReq = ($approvalReq ? 1 : 0);
    
    $updateFields = [];
    $types = "";
    $params = [];
    
    if ($invitePerm) {
        $updateFields[] = "invite_permission = ?";
        $types .= "s";
        $params[] = $invitePerm;
    }
    if ($editInfoPerm) {
        $updateFields[] = "edit_info_permission = ?";
        $types .= "s";
        $params[] = $editInfoPerm;
    }
    if ($msgPerm) {
        $updateFields[] = "message_permission = ?";
        $types .= "s";
        $params[] = $msgPerm;
    }
    if ($approvalReq !== null) {
        $updateFields[] = "approval_required = ?";
        $types .= "i";
        $params[] = $approvalReq;
    }
    
    if (count($updateFields) > 0) {
        $sql = "UPDATE chat_settings SET " . implode(", ", $updateFields) . " WHERE chat_id = ?";
        $types .= "i";
        $params[] = $chatId;
        
        $stmtSet = $mysqli->prepare($sql);
        if (!$stmtSet) throw new Exception("Errore di database.");
        $stmtSet->bind_param($types, ...$params);
        $stmtSet->execute();
        $stmtSet->close();
    }
    
    $mysqli->commit();
    
    send_success([
        'message' => "Gruppo aggiornato con successo."
    ]);

} catch (Throwable $e) {
    $mysqli->rollback();
    send_error($e->getMessage());
}
?>
