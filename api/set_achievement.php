<?php  
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$user_id = $_SESSION['user_id'] ?? 0;
$achievement_id = $_GET['achievement_id'] ?? 0;


$stmt = $mysqli->prepare("INSERT INTO utenti_achievement (utente_id, achievement_id, data) VALUES (?, ?, NOW())");
$stmt->bind_param("ii", $user_id, $_GET['achievement_id']);

if ($stmt->execute()) {
    $response = [
        'status' => 'success',
        'message' => 'Achievement Salvato con successo.'
    ];
} else {
    $response = [
        'status' => 'error',
        'message' => "Errore durante il salvataggio dell'achievement."
    ];
}

$stmt->close();
header('Content-Type: application/json');
echo json_encode($response);

?>