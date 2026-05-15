<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$user_id = $_SESSION['user_id'] ?? 0;
date_default_timezone_set('Europe/Rome');

header('Content-Type: application/json');

if (!$user_id) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Devi essere loggato.'
    ]);
    exit;
}

$roleStmt = $mysqli->prepare("SELECT ruolo FROM utenti WHERE id = ? LIMIT 1");
$roleStmt->bind_param("i", $user_id);
$roleStmt->execute();
$role = $roleStmt->get_result()->fetch_assoc()['ruolo'] ?? 'utente';
$roleStmt->close();

$character_id = (int)($_GET['character_id'] ?? 0);
if ($character_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Personaggio non valido.'
    ]);
    exit;
}

$stmt = $mysqli->prepare("INSERT INTO utenti_personaggi (utente_id, personaggio_id, data, quantità) VALUES (?, ?, NOW(), 1) ON DUPLICATE KEY UPDATE quantità = quantità + 1");
$stmt->bind_param("ii", $user_id, $character_id);

if ($stmt->execute()) {
    $response = [
        'status' => 'success',
        'message' => 'Personaggio aggiunto all\'inventario con successo.'
    ];
} else {
    $response = [
        'status' => 'error',
        'message' => 'Errore durante l\'aggiunta del personaggio all\'inventario.'
    ];
}

$stmt->close();
echo json_encode($response);
