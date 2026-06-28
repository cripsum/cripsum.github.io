<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$uid = $_SESSION['user_id'] ?? 0;
if ($uid <= 0 && php_sapi_name() !== 'cli') {
    die("Errore: Devi essere loggato per eseguire la migrazione.");
}

echo "Avvio migrazione database per abilità Ultimate...\n";

$res = $mysqli->query("SHOW COLUMNS FROM game_match_cards LIKE 'ultimate_used'");
if ($res && $res->num_rows > 0) {
    echo "La colonna 'ultimate_used' esiste già in game_match_cards.\n";
} else {
    if ($mysqli->query("ALTER TABLE game_match_cards ADD COLUMN ultimate_used TINYINT NOT NULL DEFAULT 0")) {
        echo "Aggiunta colonna 'ultimate_used' a game_match_cards con successo!\n";
    } else {
        echo "Errore durante l'aggiunta di 'ultimate_used' a game_match_cards: " . $mysqli->error . "\n";
    }
}

echo "Migrazione completata.\n";
