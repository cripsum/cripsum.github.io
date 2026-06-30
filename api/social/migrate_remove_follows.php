<?php
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Sicurezza: Solo admin o owner possono migrare il database
if (!isLoggedIn() || !in_array($_SESSION['ruolo'] ?? '', ['admin', 'owner'], true)) {
    http_response_code(403);
    die("Accesso negato. Solo gli amministratori possono eseguire le migrazioni.");
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== CRIPSUM™ DATABASE MIGRATION: REMOVE FOLLOWS ===\n\n";

// 1. Migrazione dei valori ENUM prima di restringere le colonne
echo "Migrazione valori delle impostazioni in corso...\n";
if ($mysqli->query("UPDATE user_social_settings SET friend_request_permission = 'everyone' WHERE friend_request_permission IN ('followers', 'following', 'mutual_followers')")) {
    echo "[OK] Convertite le preferenze di amicizia basate su follow in 'everyone'.\n";
} else {
    echo "[ERRORE] Migrazione friend_request_permission fallita: " . $mysqli->error . "\n";
}

if ($mysqli->query("UPDATE user_social_settings SET message_permission = 'friends' WHERE message_permission IN ('followers', 'following')")) {
    echo "[OK] Convertite le preferenze di messaggio basate su follow in 'friends'.\n";
} else {
    echo "[ERRORE] Migrazione message_permission fallita: " . $mysqli->error . "\n";
}

// 2. Rilascio tabelle follow
echo "\nEliminazione tabella 'user_follows' in corso...\n";
if ($mysqli->query("DROP TABLE IF EXISTS user_follows")) {
    echo "[OK] Tabella 'user_follows' eliminata con successo.\n";
} else {
    echo "[ERRORE] Eliminazione tabella 'user_follows' fallita: " . $mysqli->error . "\n";
}

// 3. Rimozione colonne obsolete in user_social_settings
echo "\nRimozione colonne dei follow da 'user_social_settings'...\n";
$columnsToDrop = ['follow_permission', 'show_followers_count', 'show_following_count'];
foreach ($columnsToDrop as $col) {
    // Verifichiamo prima se la colonna esiste
    $checkCol = $mysqli->query("SHOW COLUMNS FROM `user_social_settings` LIKE '$col'");
    if ($checkCol && $checkCol->num_rows > 0) {
        if ($mysqli->query("ALTER TABLE `user_social_settings` DROP COLUMN `$col`")) {
            echo "[OK] Colonna '$col' rimossa.\n";
        } else {
            echo "[ERRORE] Rimozione colonna '$col' fallita: " . $mysqli->error . "\n";
        }
    } else {
        echo "[INFO] La colonna '$col' non esiste o è già stata rimossa.\n";
    }
}

// 4. Modifica degli ENUM delle colonne rimanenti
echo "\nAggiornamento vincoli ENUM delle impostazioni di privacy...\n";
if ($mysqli->query("ALTER TABLE `user_social_settings` MODIFY COLUMN `friend_request_permission` ENUM('everyone', 'nobody') DEFAULT 'everyone'")) {
    echo "[OK] ENUM 'friend_request_permission' ristretto a ('everyone', 'nobody').\n";
} else {
    echo "[ERRORE] Modifica 'friend_request_permission' fallita: " . $mysqli->error . "\n";
}

if ($mysqli->query("ALTER TABLE `user_social_settings` MODIFY COLUMN `message_permission` ENUM('everyone', 'friends', 'nobody') DEFAULT 'everyone'")) {
    echo "[OK] ENUM 'message_permission' ristretto a ('everyone', 'friends', 'nobody').\n";
} else {
    echo "[ERRORE] Modifica 'message_permission' fallita: " . $mysqli->error . "\n";
}

echo "\nMigrazione completata con successo!";
?>
