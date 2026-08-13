<?php
require __DIR__ . '/../includes/retired_endpoint.php';
require_once __DIR__ . '/../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Controlla se admin (modifica come serve)
if (!isLoggedIn() || (!isAdmin() && !isOwner())) {
    http_response_code(403);
    exit('Non autorizzato, scemo');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $immagine = trim($_POST['immagine'] ?? '');
    $rarita = trim($_POST['rarita'] ?? '');

    $stmt = $mysqli->prepare("INSERT INTO personaggi (nome, categoria, immagine, rarità) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nome, $categoria, $immagine, $rarita);
    $stmt->execute();
    $stmt->close();

    header('Location: gestione-personaggi.php'); // torna alla pagina lista
    exit();
}
?>
