<?php  
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$user_id = $_SESSION['user_id'] ?? 0;
$idPersonaggio = $_GET['id'] ?? '';

    $stmt = $mysqli->prepare("SELECT * FROM personaggi where id = ?"); 
    $stmt->bind_param("i", $idPersonaggio); 
    $stmt->execute();
    $result = $stmt->get_result();

    $character = null;
    if ($row = $result->fetch_assoc()) {
        $character = $row;
    }
    
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode($character);



?>