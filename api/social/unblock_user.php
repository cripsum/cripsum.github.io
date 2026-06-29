<?php
require_once __DIR__ . '/bootstrap.php';

$input = get_json_input();
$blockedId = isset($input['blocked_id']) ? (int)$input['blocked_id'] : 0;

if (!$blockedId) {
    send_api_error("ID utente da sbloccare mancante.", "INVALID_INPUT");
}

$stmt = $mysqli->prepare("DELETE FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?");
if ($stmt) {
    $stmt->bind_param("ii", $userId, $blockedId);
    $ok = $stmt->execute();
    $stmt->close();
    
    if ($ok) {
        send_api_success(['is_blocked' => false], "Utente sbloccato con successo.");
    }
}

send_api_error("Errore di database durante lo sblocco dell'utente.", "DATABASE_ERROR", 500);
?>
