<?php
require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$senderId = isset($input['sender_id']) ? (int)$input['sender_id'] : 0;

if (!$senderId) {
    send_api_error("ID mittente della richiesta mancante.", "INVALID_INPUT");
}

// 1. Verifichiamo che esista una richiesta pending da parte dell'utente
$stmtCheck = $mysqli->prepare("
    SELECT id FROM friendship_requests 
    WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'
    LIMIT 1
");
$stmtCheck->bind_param("ii", $senderId, $userId);
$stmtCheck->execute();
$hasRequest = $stmtCheck->get_result()->num_rows > 0;
$stmtCheck->close();

if (!$hasRequest) {
    send_api_error("Nessuna richiesta di amicizia pendente trovata da parte di questo utente.", "REQUEST_NOT_FOUND");
}

$mysqli->begin_transaction();

try {
    $userOne = min($userId, $senderId);
    $userTwo = max($userId, $senderId);
    
    // Crea l'amicizia
    $stmtFriend = $mysqli->prepare("INSERT IGNORE INTO friendships (user_one_id, user_two_id) VALUES (?, ?)");
    $stmtFriend->bind_param("ii", $userOne, $userTwo);
    $stmtFriend->execute();
    $stmtFriend->close();
    
    // Aggiorna lo stato della richiesta
    $stmtUpdate = $mysqli->prepare("
        UPDATE friendship_requests 
        SET status = 'accepted', responded_at = NOW() 
        WHERE sender_id = ? AND receiver_id = ?
    ");
    $stmtUpdate->bind_param("ii", $senderId, $userId);
    $stmtUpdate->execute();
    $stmtUpdate->close();
    
    $mysqli->commit();
    
    // Invia notifica all'altro utente
    $myUsername = $_SESSION['username'] ?? 'Un utente';
    $titleIt = "@$myUsername ha accettato la tua richiesta di amicizia!";
    $titleEn = "@$myUsername accepted your friend request!";
    $contentIt = "Evviva! **@$myUsername** ha accettato la tua richiesta di amicizia. Ora siete amici! Visita il suo profilo [qui](/u/$myUsername).";
    $contentEn = "Hooray! **@$myUsername** accepted your friend request. You are now friends! View their profile [here](/u/$myUsername).";
    
    sendSocialNotification($mysqli, $senderId, $titleIt, $titleEn, $contentIt, $contentEn);
    
    send_api_success(['is_friend' => true], "Richiesta di amicizia accettata. Ora siete amici!");
    
} catch (Exception $e) {
    $mysqli->rollback();
    send_api_error("Errore durante l'accettazione dell'amicizia: " . $e->getMessage(), "DATABASE_ERROR", 500);
}
?>
