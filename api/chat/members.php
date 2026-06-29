<?php
// api/chat/members.php
// Returns the list of members of a group chat.

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
    $members = getChatMembers($mysqli, $chatId);
    send_success([
        'members' => $members
    ]);
} catch (Throwable $e) {
    send_error("Errore nel recupero dei membri: " . $e->getMessage(), 500);
}
?>
