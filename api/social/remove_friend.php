<?php
require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$friendId = isset($input['friend_id']) ? (int)$input['friend_id'] : 0;

if (!$friendId) {
    send_api_error("ID amico da rimuovere mancante.", "INVALID_INPUT");
}

$userOne = min($userId, $friendId);
$userTwo = max($userId, $friendId);

$mysqli->begin_transaction();

try {
    // 1. Rimuove l'amicizia
    $stmtFriend = $mysqli->prepare("DELETE FROM friendships WHERE user_one_id = ? AND user_two_id = ?");
    $stmtFriend->bind_param("ii", $userOne, $userTwo);
    $stmtFriend->execute();
    $stmtFriend->close();
    
    // 2. Cancella la cronologia delle richieste collegate per permettere future aggiunte
    $stmtRequest = $mysqli->prepare("
        DELETE FROM friendship_requests 
        WHERE (sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?)
    ");
    $stmtRequest->bind_param("iiii", $userId, $friendId, $friendId, $userId);
    $stmtRequest->execute();
    $stmtRequest->close();
    
    $mysqli->commit();
    
    send_api_success(['is_friend' => false], "Amico rimosso con successo.");
    
} catch (Exception $e) {
    $mysqli->rollback();
    send_api_error("Errore durante la rimozione dell'amico: " . $e->getMessage(), "DATABASE_ERROR", 500);
}
?>
