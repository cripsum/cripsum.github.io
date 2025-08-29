<?php  
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$user_id = $_SESSION['user_id'] ?? 0;
$nomePersonaggio = $_GET['nomePersonaggio'] ?? '';

    $stmt = $mysqli->prepare("SELECT * FROM personaggi where nome = ?"); 
    $stmt->bind_param("s", $nomePersonaggio); 
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