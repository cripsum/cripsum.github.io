<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/chat_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/chat_functions.php';

session_start();

// Imposta header JSON prima di qualsiasi output
header('Content-Type: application/json');

// Pulisci il buffer di output per evitare HTML indesiderato
if (ob_get_level()) {
    ob_clean();
}

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

try {
    $result = sendMessage($mysqli, $userId, $message, $replyTo);
    
    if ($result === true) {
        echo json_encode(['success' => true, 'message' => 'Messaggio inviato con successo']);
    } else {
        echo json_encode(['error' => $result]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Errore interno del server']);
}

exit();
?>