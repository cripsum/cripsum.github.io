<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || (!isAdmin() && !isOwner())) {
    http_response_code(403);
    exit('Non autorizzato, scemo');
}

$id_da_sbannare = $_POST['id'] ?? 0;
$id_da_sbannare = intval($id_da_sbannare);

if ($id_da_sbannare <= 0) {
    http_response_code(400);
    exit('ID non valido');
}

$stmt = $mysqli->prepare("UPDATE utenti SET isBannato = 0 WHERE id = ?");
$stmt->bind_param("i", $id_da_sbannare);
$stmt->execute();
$stmt->close();

echo 'Utente sbannato';
?>