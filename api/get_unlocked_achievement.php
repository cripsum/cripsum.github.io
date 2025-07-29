<?php  
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$user_id = $_SESSION['user_id'] ?? 0;

    $stmt = $mysqli->prepare("SELECT id, nome, descrizione, punti, img_url, data FROM achievement, utenti_achievement WHERE achievement.id = utenti_achievement.achievement_id AND utenti_achievement.utente_id = ?"); 
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $achievements = [];
    while ($row = $result->fetch_assoc()) {
    $achievements[] = $row;
    }
    
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode($achievements);

?>