<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$uid = $_SESSION['user_id'] ?? 0;
if ($uid <= 0 && php_sapi_name() !== 'cli') {
    die("Errore: Devi essere loggato per eseguire la migrazione.");
}

echo "Avvio migrazione database per progressione livelli...\n";

// 1. Aggiunta colonna livello a utenti_personaggi
$res = $mysqli->query("SHOW COLUMNS FROM utenti_personaggi LIKE 'livello'");
if ($res && $res->num_rows > 0) {
    echo "La colonna 'livello' esiste già in utenti_personaggi.\n";
} else {
    if ($mysqli->query("ALTER TABLE utenti_personaggi ADD COLUMN livello INT NOT NULL DEFAULT 1")) {
        echo "Aggiunta colonna 'livello' a utenti_personaggi con successo!\n";
    } else {
        echo "Errore durante l'aggiunta di 'livello' a utenti_personaggi: " . $mysqli->error . "\n";
    }
}

// 2. Aggiunta colonna livello a game_match_cards
$res = $mysqli->query("SHOW COLUMNS FROM game_match_cards LIKE 'livello'");
if ($res && $res->num_rows > 0) {
    echo "La colonna 'livello' esiste già in game_match_cards.\n";
} else {
    if ($mysqli->query("ALTER TABLE game_match_cards ADD COLUMN livello INT NOT NULL DEFAULT 1")) {
        echo "Aggiunta colonna 'livello' a game_match_cards con successo!\n";
    } else {
        echo "Errore durante l'aggiunta di 'livello' a game_match_cards: " . $mysqli->error . "\n";
    }
}

echo "Migrazione completata.\n";
