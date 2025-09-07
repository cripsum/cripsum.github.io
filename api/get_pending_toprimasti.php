<?php  
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$user_id = $_SESSION['user_id'] ?? 0;

try {
    $stmt = $mysqli->prepare("
        SELECT 
            t.id,
            t.id_utente,
            t.titolo,
            t.descrizione,
            t.motivazione,
            t.foto_rimasto,
            t.tipo_foto_rimasto,
            t.data_creazione,
            t.approvato,
            COALESCE(t.reazioni, 0) as reazioni,
            u.username
        FROM toprimasti t 
        LEFT JOIN utenti u ON t.id_utente = u.id 
        WHERE t.approvato = 0
        ORDER BY COALESCE(t.reazioni, 0) DESC, t.data_creazione DESC
    "); 
    
    if (!$stmt) {
        throw new Exception("Errore nella preparazione della query: " . $mysqli->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $toprimasti = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['foto_rimasto'] && !empty($row['foto_rimasto'])) {
            $row['foto_rimasto'] = base64_encode($row['foto_rimasto']);
        } else {
            $row['foto_rimasto'] = null;
        }
        
        $row['reazioni'] = (int)$row['reazioni'];
        
        $toprimasti[] = $row;
    }
    
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode($toprimasti, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Errore in get_toprimasti.php: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Errore interno del server: ' . $e->getMessage()]);
}
?>