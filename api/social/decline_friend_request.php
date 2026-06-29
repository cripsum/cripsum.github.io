<?php
require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$senderId = isset($input['sender_id']) ? (int)$input['sender_id'] : 0;

if (!$senderId) {
    send_api_error("ID mittente della richiesta mancante.", "INVALID_INPUT");
}

$stmt = $mysqli->prepare("
    UPDATE friendship_requests 
    SET status = 'declined', responded_at = NOW() 
    WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'
");

if ($stmt) {
    $stmt->bind_param("ii", $senderId, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    
    if ($ok) {
        send_api_success(['status' => 'declined'], "Richiesta di amicizia rifiutata.");
    }
}

send_api_error("Errore di database durante il rifiuto della richiesta.", "DATABASE_ERROR", 500);
?>
