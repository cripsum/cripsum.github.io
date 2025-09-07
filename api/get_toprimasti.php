<?php  
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$user_id = $_SESSION['user_id'] ?? 0;

try {
    $stmt = $mysqli->prepare("
        SELECT 
            t.*,
            u.username,
            COALESCE(t.reazioni, 0) as reazioni
        FROM toprimasti t 
        LEFT JOIN utenti u ON t.id_utente = u.id 
        WHERE t.approvato = 1 
        ORDER BY COALESCE(t.reazioni, 0) DESC, t.data_creazione DESC
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $toprimasti = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['foto_rimasto']) {
            $row['foto_rimasto'] = base64_encode($row['foto_rimasto']);
        }
        $toprimasti[] = $row;
    }
    
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode($toprimasti);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno del server']);
}
?>