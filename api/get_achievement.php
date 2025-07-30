<?php  
header("Access-Control-Allow-Origin: https://cripsum.com"); 
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$achievement_id = $_GET['achievement_id'] ?? 0;

    $stmt = $mysqli->prepare("SELECT * FROM achievement WHERE achievement.id = ?"); 
    $stmt->bind_param("i", $achievement_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $achievement = [];
    while ($row = $result->fetch_assoc()) {
    $achievement[] = $row;
    }
    
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode($achievement);

?>