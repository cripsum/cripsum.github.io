<?php  
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/game_config.php';
require_once __DIR__ . '/../includes/game_helpers.php';

session_start();
$user_id = $_SESSION['user_id'] ?? 0;

    $stmt = $mysqli->prepare("SELECT id, nome, descrizione, rarità, categoria, img_url, audio_url, caratteristiche, data, quantità, livello, descrizione_en, caratteristiche_en FROM personaggi, utenti_personaggi WHERE personaggi.id = utenti_personaggi.personaggio_id AND utenti_personaggi.utente_id = ?"); 
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $characters = [];
    while ($row = $result->fetch_assoc()) {
        $lvl = (int)($row['livello'] ?? 1);
        $row['livello'] = $lvl;
        $row['stats'] = gd_stats($mysqli, (int)$row['id'], $lvl);
        $row['stats_next'] = ($lvl < 6) ? gd_stats($mysqli, (int)$row['id'], $lvl + 1) : null;
        $row['required_next'] = ($lvl < 6) ? gd_get_upgrade_requirement($row['rarità'] ?? 'comune', $lvl) : 0;
        $characters[] = $row;
    }
    
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode($characters);

?>