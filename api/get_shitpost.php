<?php  
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$user_id = $_SESSION['user_id'] ?? 0;

try {
    $stmt = $mysqli->prepare("
        SELECT 
            s.id,
            s.id_utente,
            s.titolo,
            s.descrizione,
            s.foto_shitpost,
            s.tipo_foto_shitpost,
            s.data_creazione,
            s.approvato,
            u.username
        FROM shitposts s 
        LEFT JOIN utenti u ON s.id_utente = u.id 
        WHERE s.approvato = 1 
        ORDER BY s.data_creazione DESC
    "); 
    
    if (!$stmt) {
        throw new Exception("Errore nella preparazione della query: " . $mysqli->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $shitposts = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['foto_shitpost'] && !empty($row['foto_shitpost'])) {
            $row['foto_shitpost'] = base64_encode($row['foto_shitpost']);
        } else {
            $row['foto_shitpost'] = null;
        }
        
        $shitposts[] = $row;
    }
    
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode($shitposts, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Errore in get_shitposts.php: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Errore interno del server: ' . $e->getMessage()]);
}
?>