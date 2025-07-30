<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo non consentito');
}

if (!isset($_POST['id'])) {
    http_response_code(400);
    exit('ID mancante');
}

$id = intval($_POST['id']);

$stmt = $mysqli->prepare("DELETE FROM utenti WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Errore eliminazione']);
}

$stmt->close();
$mysqli->close();
