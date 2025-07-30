<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo non consentito');
}

if (!isset($_POST['user_id'], $_POST['character_id'])) {
    http_response_code(400);
    exit('Dati mancanti');
}

$user_id = intval($_POST['user_id']);
$character_id = intval($_POST['character_id']);

$stmt = $mysqli->prepare("INSERT INTO utenti_personaggi (utente_id, personaggio_id, data, quantità) VALUES (?, ?, NOW(), 1) ON DUPLICATE KEY UPDATE quantità = quantità + 1");
$stmt->bind_param("ii", $user_id, $character_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Errore aggiunta personaggio']);
}

$stmt->close();
$mysqli->close();
