<?php  
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$user_id = $_SESSION['user_id'] ?? 0;

    $stmt = $mysqli->prepare("SELECT count(id) as total FROM personaggi"); 
    $stmt->execute();
    $result = $stmt->get_result();
    
    $total = [];
    while ($row = $result->fetch_assoc()) {
    $total[] = $row;
    }
    
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode($total);



?>