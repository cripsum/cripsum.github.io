<?php  
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$id = intval($_GET['id']);

    $stmt = $mysqli->prepare("SELECT id, nome, rarità, categoria, img_url, audio_url, data, quantità FROM personaggi, utenti_personaggi WHERE personaggi.id = utenti_personaggi.personaggio_id AND utenti_personaggi.utente_id = ?"); 
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $characters = [];
    while ($row = $result->fetch_assoc()) {
    $characters[] = $row;
    }
    
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode($characters);

?>