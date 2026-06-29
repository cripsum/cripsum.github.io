<?php
require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$receiverId = isset($input['receiver_id']) ? (int)$input['receiver_id'] : 0;

if (!$receiverId) {
    send_api_error("ID destinatario della richiesta mancante.", "INVALID_INPUT");
}

$stmt = $mysqli->prepare("
    UPDATE friendship_requests 
    SET status = 'cancelled' 
    WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'
");

if ($stmt) {
    $stmt->bind_param("ii", $userId, $receiverId);
    $ok = $stmt->execute();
    $stmt->close();
    
    if ($ok) {
        send_api_success(['status' => 'cancelled'], "Richiesta di amicizia annullata.");
    }
}

send_api_error("Errore di database durante l'annullamento della richiesta.", "DATABASE_ERROR", 500);
?>
