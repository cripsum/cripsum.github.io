<?php  
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();

    $stmt = $mysqli->prepare("SELECT * FROM achievement"); 
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