<?php  
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$user_id = $_SESSION['user_id'] ?? 0;

$stmt = $mysqli->prepare("DELETE FROM utenti_personaggi WHERE utente_id = ?");
$stmt->bind_param("i", $user_id,);

if ($stmt->execute()) {
    $response = [
        'status' => 'success',
        'message' => 'Inventario cancellato con successo.'
    ];
} else {
    $response = [
        'status' => 'error',
        'message' => 'Errore durante la cancellazione dell\'inventario.'
    ];
}

$stmt->close();
header('Content-Type: application/json');  
echo json_encode($response);
?>

