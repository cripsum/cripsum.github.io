<?php
require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$receiverId = isset($input['receiver_id']) ? (int)$input['receiver_id'] : 0;

if (!$receiverId) {
    send_api_error("ID destinatario mancante o non valido.", "INVALID_INPUT");
}

if ($receiverId === $userId) {
    send_api_error("Non puoi inviare una richiesta di amicizia a te stesso.", "SELF_REQUEST_FORBIDDEN");
}

$mysqli->begin_transaction();

try {
    // 1. Controlliamo lo stato attuale della relazione
    $rel = getRelationshipStatus($mysqli, $userId, $receiverId);
    
    if ($rel['is_blocked_by_viewer'] || $rel['has_blocked_viewer']) {
        throw new Exception("Impossibile inviare la richiesta di amicizia. C'è un blocco attivo.", 403);
    }
    
    if ($rel['is_friend']) {
        send_api_success(['status' => 'accepted'], "Siete già amici.");
    }
    
    if ($rel['friend_request_sent']) {
        send_api_success(['status' => 'pending'], "Richiesta di amicizia già inviata.");
    }
    
    // 2. COMPORTAMENTO INTELLIGENTE: Se abbiamo ricevuto una richiesta da questo utente, l'azione di inviarne una la ACCETTA automaticamente
    if ($rel['friend_request_received']) {
        $userOne = min($userId, $receiverId);
        $userTwo = max($userId, $receiverId);
        
        // Crea l'amicizia
        $stmtFriend = $mysqli->prepare("INSERT IGNORE INTO friendships (user_one_id, user_two_id) VALUES (?, ?)");
        $stmtFriend->bind_param("ii", $userOne, $userTwo);
        $stmtFriend->execute();
        $stmtFriend->close();
        
        // Aggiorna lo stato della richiesta esistente
        $stmtUpdate = $mysqli->prepare("
            UPDATE friendship_requests 
            SET status = 'accepted', responded_at = NOW() 
            WHERE sender_id = ? AND receiver_id = ?
        ");
        $stmtUpdate->bind_param("ii", $receiverId, $userId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
        
        $mysqli->commit();
        
        // Notifica all'altro utente che abbiamo accettato la richiesta
        $myUsername = $_SESSION['username'] ?? 'Un utente';
        $titleIt = "@$myUsername ha accettato la tua richiesta di amicizia!";
        $titleEn = "@$myUsername accepted your friend request!";
        $contentIt = "Evviva! **@$myUsername** ha accettato la tua richiesta di amicizia. Ora siete amici! Visita il suo profilo [qui](/u/$myUsername).";
        $contentEn = "Hooray! **@$myUsername** accepted your friend request. You are now friends! View their profile [here](/u/$myUsername).";
        
        sendSocialNotification($mysqli, $receiverId, $titleIt, $titleEn, $contentIt, $contentEn);
        
        send_api_success(['status' => 'accepted'], "Richiesta accettata automaticamente! Ora siete amici.");
    }
    
    // 3. Altrimenti verifichiamo se possiamo inviare una nuova richiesta secondo la privacy del destinatario
    if (!$rel['can_send_friend_request']) {
        throw new Exception("Questo utente non accetta richieste di amicizia in base alle sue impostazioni di privacy.", 403);
    }
    
    // Inseriamo o aggiorniamo la richiesta (se precedentemente cancellata o rifiutata)
    $stmtRequest = $mysqli->prepare("
        INSERT INTO friendship_requests (sender_id, receiver_id, status, created_at, responded_at)
        VALUES (?, ?, 'pending', NOW(), NULL)
        ON DUPLICATE KEY UPDATE status = 'pending', created_at = NOW(), responded_at = NULL
    ");
    
    $stmtRequest->bind_param("ii", $userId, $receiverId);
    $stmtRequest->execute();
    $stmtRequest->close();
    
    $mysqli->commit();
    
    // Notifica di nuova richiesta
    $myUsername = $_SESSION['username'] ?? 'Un utente';
    $titleIt = "@$myUsername ti ha inviato una richiesta di amicizia!";
    $titleEn = "@$myUsername sent you a friend request!";
    $contentIt = "**@$myUsername** desidera fare amicizia con te su Cripsum™! Clicca [qui](/it/amici) per gestire le tue richieste.";
    $contentEn = "**@$myUsername** wants to be friends with you on Cripsum™! Click [here](/en/amici) to manage your requests.";
    
    sendSocialNotification($mysqli, $receiverId, $titleIt, $titleEn, $contentIt, $contentEn);
    
    send_api_success(['status' => 'pending'], "Richiesta di amicizia inviata con successo.");
    
} catch (Exception $e) {
    $mysqli->rollback();
    send_api_error($e->getMessage(), "REQUEST_FAILED", $e->getCode() ?: 400);
}
?>
