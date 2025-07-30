<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();

// Controlla se admin (modifica come serve)
if (!isLoggedIn() || (!isAdmin() && !isOwner())) {
    http_response_code(403);
    exit('Non autorizzato, scemo');
}

// Prendi dati POST
$nome = trim($_POST['nome'] ?? '');
$descrizione = trim($_POST['descrizione'] ?? '');
$icona = trim($_POST['icona'] ?? '');
$punti = intval($_POST['punti'] ?? 0);

if ($nome === '') {
    echo json_encode(['status' => 'error', 'message' => 'Nome obbligatorio']);
    exit;
}

// Query inserimento
$stmt = $mysqli->prepare("INSERT INTO achievement (nome, descrizione, img_url, punti) VALUES (?, ?, ?, ?)");
$stmt->bind_param('sssi', $nome, $descrizione, $icona, $punti);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Achievement aggiunto']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Errore inserimento']);
}

$stmt->close();
?>
