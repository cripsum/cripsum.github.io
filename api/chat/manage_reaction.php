<?php
require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$messageId = isset($input['message_id']) ? (int)$input['message_id'] : 0;
$reaction = isset($input['reaction']) ? trim((string)$input['reaction']) : '';
$action = isset($input['action']) ? trim((string)$input['action']) : 'add'; // 'add' o 'remove'

if (!$messageId || $reaction === '') {
    send_error("ID messaggio o reazione mancante.");
}

// 1. Recuperiamo il messaggio e la conversazione di appartenenza
$stmtMsg = $mysqli->prepare("SELECT conversation_id FROM private_messages WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$stmtMsg->bind_param("i", $messageId);
$stmtMsg->execute();
$message = $stmtMsg->get_result()->fetch_assoc();
$stmtMsg->close();

if (!$message) {
    send_error("Messaggio non trovato.");
}

$conversationId = (int)$message['conversation_id'];

// 2. Verifichiamo che l'utente loggato sia parte della conversazione
$stmtCheckPart = $mysqli->prepare("SELECT id FROM private_conversation_participants WHERE conversation_id = ? AND user_id = ? LIMIT 1");
$stmtCheckPart->bind_param("ii", $conversationId, $userId);
$stmtCheckPart->execute();
$isParticipant = $stmtCheckPart->get_result()->num_rows > 0;
$stmtCheckPart->close();

if (!$isParticipant) {
    send_error("Accesso negato. Non fai parte di questa conversazione.", 403);
}

// 3. Eseguiamo l'azione
if ($action === 'add') {
    // Aggiungi reazione
    $stmtReact = $mysqli->prepare("
        INSERT INTO private_message_reactions (message_id, user_id, reaction)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE reaction = VALUES(reaction)
    ");
    $stmtReact->bind_param("iis", $messageId, $userId, $reaction);
    $stmtReact->execute();
    $stmtReact->close();
} else {
    // Rimuovi reazione
    $stmtUnreact = $mysqli->prepare("
        DELETE FROM private_message_reactions 
        WHERE message_id = ? AND user_id = ? AND reaction = ?
    ");
    $stmtUnreact->bind_param("iis", $messageId, $userId, $reaction);
    $stmtUnreact->execute();
    $stmtUnreact->close();
}

// 4. Recuperiamo le reazioni aggiornate del messaggio per restituirle al client
$stmtGetReacts = $mysqli->prepare("
    SELECT reaction, GROUP_CONCAT(u.username SEPARATOR ', ') as usernames, COUNT(*) as count,
           MAX(CASE WHEN r.user_id = ? THEN 1 ELSE 0 END) as user_reacted
    FROM private_message_reactions r
    INNER JOIN utenti u ON u.id = r.user_id
    WHERE r.message_id = ?
    GROUP BY r.reaction
");

$updatedReactions = [];
if ($stmtGetReacts) {
    $stmtGetReacts->bind_param("ii", $userId, $messageId);
    $stmtGetReacts->execute();
    $updatedReactions = $stmtGetReacts->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtGetReacts->close();
}

send_success([
    'message_id' => $messageId,
    'reactions' => $updatedReactions
]);
?>
