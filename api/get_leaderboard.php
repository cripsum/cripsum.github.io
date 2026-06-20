<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();

$type = $_GET['type'] ?? 'casse_aperte';

try {
    if ($type === 'casse_aperte') {
        $stmt = $mysqli->prepare("
            SELECT 
                u.username,
                COALESCE(u.is_premium, 0) AS is_premium,
                SUM(up.quantità) as total_casse
            FROM utenti u
            JOIN utenti_personaggi up ON u.id = up.utente_id
            GROUP BY u.id, u.username, u.is_premium
            ORDER BY total_casse DESC
            LIMIT 10
        ");
        
    } else if ($type === 'personaggi_sbloccati') {
        $stmt = $mysqli->prepare("
            SELECT 
                u.username,
                COALESCE(u.is_premium, 0) AS is_premium,
                COUNT(DISTINCT up.personaggio_id) as personaggi_unici
            FROM utenti u
            JOIN utenti_personaggi up ON u.id = up.utente_id
            GROUP BY u.id, u.username, u.is_premium
            ORDER BY personaggi_unici DESC
            LIMIT 10
        ");
        
    } else {
        throw new Exception('Invalid leaderboard type');
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $leaderboard = [];
    $position = 1;
    
    while ($row = $result->fetch_assoc()) {
        $leaderboard[] = [
            'position' => $position++,
            'username' => htmlspecialchars($row['username']),
            'is_premium' => (int)($row['is_premium'] ?? 0) === 1,
            'value' => (int)($row['total_casse'] ?? $row['personaggi_unici'] ?? 0)
        ];
    }
    
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'type' => $type,
        'data' => $leaderboard
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>