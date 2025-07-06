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

// Imposta header JSON prima di qualsiasi output
header('Content-Type: application/json');

// Pulisci il buffer di output
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
$messageId = $input['id'] ?? 0;

if (!$messageId || !is_numeric($messageId)) {
    echo json_encode(['error' => 'ID messaggio non valido']);
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['ruolo'] ?? 'utente';

try {
    // Verifica se l'utente può eliminare il messaggio
    $stmt = $mysqli->prepare("SELECT user_id FROM messages WHERE id = ?");
    $stmt->bind_param("i", $messageId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Messaggio non trovato']);
        exit();
    }
    
    $message = $result->fetch_assoc();
    $messageUserId = $message['user_id'];
    
    // Verifica permessi: l'utente può eliminare solo i propri messaggi o essere admin
    if ($messageUserId != $userId && $userRole !== 'admin') {
        echo json_encode(['error' => 'Non hai i permessi per eliminare questo messaggio']);
        exit();
    }
    
    // Elimina il messaggio
    $deleteStmt = $mysqli->prepare("DELETE FROM messages WHERE id = ?");
    $deleteStmt->bind_param("i", $messageId);
    
    if ($deleteStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Messaggio eliminato con successo']);
    } else {
        echo json_encode(['error' => 'Errore durante l\'eliminazione del messaggio']);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Errore interno del server']);
}

exit();
?>