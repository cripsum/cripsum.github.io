<?php  
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$user_id = $_SESSION['user_id'] ?? 0;


$stmt = $mysqli->prepare("INSERT INTO utenti_personaggi (utente_id, personaggio_id, data, quantità) VALUES (?, ?, NOW(), 1) ON DUPLICATE KEY UPDATE quantità = quantità + 1");
$stmt->bind_param("ii", $user_id, $_GET['character_id']);

if ($stmt->execute()) {
    $response = [
        'status' => 'success',
        'message' => 'Personaggio aggiunto all\'inventario con successo.'
    ];
} else {
    $response = [
        'status' => 'error',
        'message' => 'Errore durante l\'aggiunta del personaggio all\'inventario.'
    ];
}

$stmt->close();
header('Content-Type: application/json');
echo json_encode($response);

?>