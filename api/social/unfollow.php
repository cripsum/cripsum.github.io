<?php
require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$followedId = isset($input['followed_id']) ? (int)$input['followed_id'] : 0;

if (!$followedId) {
    send_api_error("ID utente da smettere di seguire mancante.", "INVALID_INPUT");
}

$stmt = $mysqli->prepare("DELETE FROM user_follows WHERE follower_id = ? AND followed_id = ?");
if ($stmt) {
    $stmt->bind_param("ii", $userId, $followedId);
    $ok = $stmt->execute();
    $stmt->close();
    
    if ($ok) {
        send_api_success(['is_following' => false], "Hai smesso di seguire l'Utente.");
    }
}

send_api_error("Errore di database durante l'unfollow.", "DATABASE_ERROR", 500);
?>
