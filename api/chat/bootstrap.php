<?php
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non autenticato.']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['ruolo'] ?? 'utente';

// Helper per leggere l'input JSON o POST
function get_json_input() {
    return json_decode(file_get_contents('php://input'), true) ?? $_POST;
}

// Helper per inviare risposte di errore
function send_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit();
}

// Helper per inviare risposte di successo
function send_success($data = []) {
    echo json_encode(array_merge(['ok' => true], $data));
    exit();
}

// Helper per verificare se l'utente ha bloccato o è bloccato da un altro utente
function is_blocked_with($mysqli, $userId, $otherUserId) {
    $stmt = $mysqli->prepare("
        SELECT id FROM private_user_blocks 
        WHERE (user_id = ? AND blocked_user_id = ?) 
           OR (user_id = ? AND blocked_user_id = ?)
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("iiii", $userId, $otherUserId, $otherUserId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $blocked = $res->num_rows > 0;
        $stmt->close();
        return $blocked;
    }
    return false;
}
?>
