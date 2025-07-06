<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/chat_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/chat_functions.php';

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
$message = $input['message'] ?? '';
$replyTo = $input['reply_to'] ?? null;

if (empty(trim($message))) {
    echo json_encode(['error' => 'Il messaggio non può essere vuoto']);
    exit();
}

$userId = $_SESSION['user_id'];
$result = sendMessage($mysqli, $userId, $message, $replyTo);

if ($result === true) {
    echo json_encode(['success' => true, 'message' => 'Messaggio inviato con successo']);
} else {
    echo json_encode(['error' => $result]);
}
?>