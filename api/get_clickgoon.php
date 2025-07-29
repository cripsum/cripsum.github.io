<?php  
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$user_id = $_SESSION['user_id'] ?? 0;

    $stmt = $mysqli->prepare("SELECT clickgoon FROM utenti WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $total = (int)$row['total'];
    }
    
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode(['total' => $total]);

?>