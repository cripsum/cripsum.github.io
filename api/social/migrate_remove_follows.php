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
echo "=== CRIPSUM™ DATABASE MIGRATION: REMOVE FOLLOWS & OBSOLETE TABLES ===\n\n";

// 1. Rilascio tabella follow
echo "Eliminazione tabella 'user_follows' in corso...\n";
if ($mysqli->query("DROP TABLE IF EXISTS user_follows")) {
    echo "[OK] Tabella 'user_follows' eliminata con successo.\n";
} else {
    echo "[ERRORE] Eliminazione tabella 'user_follows' fallita: " . $mysqli->error . "\n";
}

// 2. Rilascio tabella impostazioni social obsolete
echo "\nEliminazione tabella 'user_social_settings' in corso...\n";
if ($mysqli->query("DROP TABLE IF EXISTS user_social_settings")) {
    echo "[OK] Tabella 'user_social_settings' (obsoleta) eliminata con successo.\n";
} else {
    echo "[ERRORE] Eliminazione tabella 'user_social_settings' fallita: " . $mysqli->error . "\n";
}

echo "\nMigrazione completata con successo!";
?>
