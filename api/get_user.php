<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_GET['id'])) {
    http_response_code(400);
    exit('ID mancante');
}

$id = intval($_GET['id']);

$stmt = $mysqli->prepare("SELECT id, username, email, ruolo, soldi, data_creazione, clickgoon FROM utenti WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode($row);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Utente non trovato']);
}

$stmt->close();
$mysqli->close();