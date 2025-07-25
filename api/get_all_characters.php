<?php  
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$user_id = $_SESSION['user_id'] ?? 0;

    $stmt = $mysqli->prepare("SELECT * FROM personaggi"); 
    $stmt->execute();
    $result = $stmt->get_result();
    
    $all_characters = [];
    while ($row = $result->fetch_assoc()) {
    $all_characters[] = $row;
    }
    
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode($all_characters);



?>