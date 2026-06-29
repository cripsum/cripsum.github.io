<?php
// api/chat/get.php
// Returns details for a single group chat.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

$chatId = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : 0;

if (!$chatId) {
    send_error("ID chat mancante o non valido.");
}

if (!canViewChat($mysqli, $chatId, $userId)) {
    send_error("Accesso negato o non partecipi a questa chat.", 403);
}

try {
    // Get chat metadata
    $chat = getChatById($mysqli, $chatId);
    if (!$chat) {
        send_error("Chat non trovata.", 404);
    }
    
    // Get settings
    $stmtSet = $mysqli->prepare("SELECT * FROM chat_settings WHERE chat_id = ? LIMIT 1");
    $settings = null;
    if ($stmtSet) {
        $stmtSet->bind_param("i", $chatId);
        $stmtSet->execute();
        $settings = $stmtSet->get_result()->fetch_assoc();
        $stmtSet->close();
    }
    
    // Get current user's membership details
    $stmtMem = $mysqli->prepare("SELECT * FROM chat_members WHERE chat_id = ? AND user_id = ? LIMIT 1");
    $myMember = null;
    if ($stmtMem) {
        $stmtMem->bind_param("ii", $chatId, $userId);
        $stmtMem->execute();
        $myMember = $stmtMem->get_result()->fetch_assoc();
        $stmtMem->close();
    }

    send_success([
        'chat' => [
            'id' => (int)$chat['id'],
            'type' => $chat['type'],
            'name' => $chat['name'],
            'description' => $chat['description'],
            'avatar_url' => $chat['avatar_url'],
            'created_by' => (int)$chat['created_by'],
            'created_at' => $chat['created_at'],
            'is_archived' => (bool)$chat['is_archived']
        ],
        'settings' => $settings ? [
            'invite_permission' => $settings['invite_permission'],
            'edit_info_permission' => $settings['edit_info_permission'],
            'message_permission' => $settings['message_permission'],
            'approval_required' => (bool)$settings['approval_required']
        ] : null,
        'my_membership' => $myMember ? [
            'role' => $myMember['role'],
            'status' => $myMember['status'],
            'notification_level' => $myMember['notification_level'],
            'is_muted' => ($myMember['muted_until'] && strtotime($myMember['muted_until']) > time())
        ] : null
    ]);

} catch (Throwable $e) {
    send_error("Errore nel recupero dei dettagli: " . $e->getMessage(), 500);
}
?>
