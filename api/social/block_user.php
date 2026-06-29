<?php
require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$blockedId = isset($input['blocked_id']) ? (int)$input['blocked_id'] : 0;

if (!$blockedId) {
    send_api_error("ID utente da bloccare mancante.", "INVALID_INPUT");
}

if ($blockedId === $userId) {
    send_api_error("Non puoi bloccare te stesso.", "SELF_BLOCK_FORBIDDEN");
}

$mysqli->begin_transaction();

try {
    // 1. Inserisce il blocco
    $stmtBlock = $mysqli->prepare("INSERT IGNORE INTO blocked_users (blocker_id, blocked_id) VALUES (?, ?)");
    $stmtBlock->bind_param("ii", $userId, $blockedId);
    $stmtBlock->execute();
    $stmtBlock->close();
    
    // 2. Rimuove eventuali follow in entrambe le direzioni
    $stmtUnfollow = $mysqli->prepare("
        DELETE FROM user_follows 
        WHERE (follower_id = ? AND followed_id = ?) 
           OR (follower_id = ? AND followed_id = ?)
    ");
    $stmtUnfollow->bind_param("iiii", $userId, $blockedId, $blockedId, $userId);
    $stmtUnfollow->execute();
    $stmtUnfollow->close();
    
    // 3. Rimuove l'amicizia se esisteva
    $userOne = min($userId, $blockedId);
    $userTwo = max($userId, $blockedId);
    $stmtUnfriend = $mysqli->prepare("DELETE FROM friendships WHERE user_one_id = ? AND user_two_id = ?");
    $stmtUnfriend->bind_param("ii", $userOne, $userTwo);
    $stmtUnfriend->execute();
    $stmtUnfriend->close();
    
    // 4. Elimina eventuali richieste di amicizia
    $stmtUnrequest = $mysqli->prepare("
        DELETE FROM friendship_requests 
        WHERE (sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?)
    ");
    $stmtUnrequest->bind_param("iiii", $userId, $blockedId, $blockedId, $userId);
    $stmtUnrequest->execute();
    $stmtUnrequest->close();
    
    // 5. Archiviazione automatica della chat privata (se esistente)
    $mysqli->query("
        UPDATE private_conversation_participants cp
        INNER JOIN private_conversations c ON c.id = cp.conversation_id
        SET cp.is_archived = 1
        WHERE cp.user_id = $userId 
          AND c.is_group = 0 
          AND EXISTS (SELECT 1 FROM private_conversation_participants cp2 WHERE cp2.conversation_id = c.id AND cp2.user_id = $blockedId)
    ");

    $mysqli->commit();
    
    send_api_success(['is_blocked' => true], "Utente bloccato con successo. Tutte le relazioni sono state rimosse.");

} catch (Exception $e) {
    $mysqli->rollback();
    send_api_error("Errore durante il blocco dell'utente: " . $e->getMessage(), "DATABASE_ERROR", 500);
}
?>
