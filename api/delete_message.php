<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/chat_config.php';

session_start();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non sei autenticato']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$messageId = $input['id'] ?? 0;

if (!$messageId) {
    echo json_encode(['error' => 'ID messaggio non valido']);
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['ruolo'] ?? 'utente';

$result = deleteMessage($mysqli, $messageId, $userId, $userRole);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Messaggio eliminato']);
} else {
    echo json_encode(['error' => 'Impossibile eliminare il messaggio']);
}
?>