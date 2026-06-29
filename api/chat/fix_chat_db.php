<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== FIXING CHAT DATABASE ===\n\n";

// 1. Aggiungiamo le colonne mancanti a private_conversation_participants
$queries = [
    "ALTER TABLE `private_conversation_participants` ADD COLUMN `typing_status` VARCHAR(20) NULL DEFAULT NULL AFTER `favorite_emoji`",
    "ALTER TABLE `private_conversation_participants` ADD COLUMN `last_typing_at` TIMESTAMP NULL DEFAULT NULL AFTER `typing_status`"
];

foreach ($queries as $sql) {
    echo "Executing: $sql\n";
    try {
        if ($mysqli->query($sql)) {
            echo "SUCCESS\n";
        } else {
            echo "FAILED: " . $mysqli->error . "\n";
        }
    } catch (Throwable $e) {
        echo "EXCEPTION: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "Database fix complete. You can now delete this file.\n";
?>
