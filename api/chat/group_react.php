<?php
// api/chat/group_react.php
// Endpoint for adding and removing reactions to/from group chat messages.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/group_chat_functions.php';

$input = get_json_input();
$messageId = isset($input['message_id']) ? (int)$input['message_id'] : 0;
$reaction = isset($input['reaction']) ? trim((string)$input['reaction']) : '';

chat_ensure_reactions_tables($mysqli);
$allowed = chat_get_allowed_reactions($mysqli);

if ($messageId <= 0 || !in_array($reaction, $allowed, true)) {
    send_error("Reazione non valida o non consentita.", 422);
}

// 1. Verify message exists and user is participant in the chat
$stmtMsg = $mysqli->prepare("SELECT chat_id FROM chat_messages WHERE id = ? AND deleted_at IS NULL LIMIT 1");
if (!$stmtMsg) {
    send_error("Errore server.", 500);
}
$stmtMsg->bind_param("i", $messageId);
$stmtMsg->execute();
$msgRow = $stmtMsg->get_result()->fetch_assoc();
$stmtMsg->close();

if (!$msgRow) {
    send_error("Messaggio non trovato.", 404);
}

$chatId = (int)$msgRow['chat_id'];
if (!canViewChat($mysqli, $chatId, $userId)) {
    send_error("Accesso negato o non partecipi a questa chat.", 403);
}

// 2. Check if the user already reacted with this emoji
$stmtCheck = $mysqli->prepare("SELECT id FROM group_chat_reactions WHERE message_id = ? AND user_id = ? AND reaction = ? LIMIT 1");
if (!$stmtCheck) {
    send_error("Errore server.", 500);
}
$stmtCheck->bind_param("iis", $messageId, $userId, $reaction);
$stmtCheck->execute();
$existing = $stmtCheck->get_result()->fetch_assoc();
$stmtCheck->close();

if ($existing) {
    // Remove reaction
    $stmtDel = $mysqli->prepare("DELETE FROM group_chat_reactions WHERE message_id = ? AND user_id = ? AND reaction = ?");
    if ($stmtDel) {
        $stmtDel->bind_param("iis", $messageId, $userId, $reaction);
        $stmtDel->execute();
        $stmtDel->close();
    }
} else {
    // Add reaction
    $stmtIns = $mysqli->prepare("INSERT INTO group_chat_reactions (message_id, user_id, reaction) VALUES (?, ?, ?)");
    if ($stmtIns) {
        $stmtIns->bind_param("iis", $messageId, $userId, $reaction);
        $stmtIns->execute();
        $stmtIns->close();
    }
}

// 3. Return updated reaction counts grouped by emoji
$stmtGet = $mysqli->prepare("
    SELECT r.reaction, GROUP_CONCAT(u.username SEPARATOR ', ') as usernames, COUNT(*) as count,
           MAX(CASE WHEN r.user_id = ? THEN 1 ELSE 0 END) as user_reacted
    FROM group_chat_reactions r
    INNER JOIN utenti u ON u.id = r.user_id
    WHERE r.message_id = ?
    GROUP BY r.reaction
");

$updated = [];
if ($stmtGet) {
    $stmtGet->bind_param("ii", $userId, $messageId);
    $stmtGet->execute();
    $updated = $stmtGet->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtGet->close();
}

send_success(['reactions' => $updated]);
?>
