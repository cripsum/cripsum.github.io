<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || (!isAdmin() && !isOwner())) {
    http_response_code(403);
    exit('Non autorizzato, scemo');
}

$user_id = $_SESSION['user_id'] ?? 0;
$id_da_bannare = $_POST['id'] ?? 0;
$id_da_bannare = intval($id_da_bannare);

if ($id_da_bannare <= 0) {
    http_response_code(400);
    exit('ID non valido');
}

if ($id_da_bannare === $user_id) {
    http_response_code(400);
    exit('Non puoi bannare te stesso');
}

$stmt2 = $mysqli->prepare("SELECT ruolo FROM utenti WHERE id = ?");
$stmt2->bind_param("i", $id_da_bannare);
$stmt2->execute();
$result2 = $stmt2->get_result();
$target_user = $result2->fetch_assoc();
$stmt2->close();

if (!$target_user) {
    http_response_code(404);
    exit('Utente non trovato');
}

// Admin cannot ban owner or other admins
if (isAdmin() && !isOwner()) {
    if ($target_user['ruolo'] === 'owner' || $target_user['ruolo'] === 'admin') {
        http_response_code(403);
        exit('Non puoi bannare un owner o un altro admin');
    }
}

$stmt = $mysqli->prepare("UPDATE utenti SET isBannato = 1 WHERE id = ?");
$stmt->bind_param("i", $id_da_bannare);
$stmt->execute();
$stmt->close();

echo 'Utente bannato';
header('Location: https://cripsum.com/it/admin');

?>