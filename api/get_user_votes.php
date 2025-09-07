<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    $stmt = $mysqli->prepare("SELECT id_post FROM voti_toprimasti WHERE id_utente = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $votes = [];
    while ($row = $result->fetch_assoc()) {
        $votes[$row['id_post']] = true;
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'votes' => $votes
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
?>