<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || (!isAdmin() && !isOwner())) {
    http_response_code(403);
    exit('Non autorizzato, scemo');
}

$id_da_bannare = $_POST['id'] ?? 0;
$id_da_bannare = intval($id_da_bannare);

if ($id_da_bannare <= 0) {
    http_response_code(400);
    exit('ID non valido');
}

$stmt = $mysqli->prepare("UPDATE utenti SET isBannato = 1 WHERE id = ?");
$stmt->bind_param("i", $id_da_bannare);
$stmt->execute();
$stmt->close();

echo 'Utente bannato';
header('Location: https://cripsum.com/it/admin');

?>