<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo non consentito');
}

$id = intval($_POST['id']);
$username = trim($_POST['username']);
$email = trim($_POST['email']);
$ruolo = trim($_POST['ruolo']);

$stmt = $mysqli->prepare("UPDATE utenti SET username = ?, email = ?, ruolo = ? WHERE id = ?");
$stmt->bind_param("sssi", $username, $email, $ruolo, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Errore aggiornamento']);
}

$stmt->close();
$mysqli->close();
