<?php
require_once __DIR__ . '/../config/database.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit('ID non valido');
}

$user_id = intval($_GET['id']);

$stmt = $mysqli->prepare("SELECT profile_pic, profile_pic_type FROM utenti WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 1) {
    $stmt->bind_result($blob, $mime);
    $stmt->fetch();

    if ($blob && $mime) {
        header("Content-Type: $mime");
        echo $blob;
        exit;
    }
}

// Se l'utente non ha immagine: serve immagine predefinita
readfile(__DIR__ . '/../img/abdul.jpg');
exit;
