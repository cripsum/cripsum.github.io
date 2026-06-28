<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/game_config.php';
require_once __DIR__ . '/includes/game_helpers.php';

header('Content-Type: text/plain');

try {
    // Trova l'ultimo match attivo
    $res = $mysqli->query("SELECT * FROM game_matches WHERE status = 'active' ORDER BY id DESC LIMIT 1");
    $match = $res->fetch_assoc();
    if (!$match) {
        die("Nessun match attivo trovato per il test.");
    }
    
    echo "Match ID: " . $match['id'] . "\n";
    echo "Turno di: " . $match['current_turn_user_id'] . "\n";
    
    $uid = (int)$match['current_turn_user_id'];
    
    // Proviamo a simulare l'azione 'charge'
    echo "Eseguo 'charge' per l'utente {$uid}...\n";
    $result = gd_apply_battle_action($mysqli, $match, $uid, 'charge', 0);
    echo "Risultato: " . print_r($result, true) . "\n";
    echo "Successo!\n";
    
} catch (Throwable $e) {
    echo "ERRORE RISCONTRATO:\n";
    echo $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
