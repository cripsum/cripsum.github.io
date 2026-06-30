<?php
require_once __DIR__ . '/../../config/database.php';

// Add last_discord_activity column to utenti table
$query = "ALTER TABLE utenti ADD COLUMN last_discord_activity TIMESTAMP NULL DEFAULT NULL";
if ($mysqli->query($query)) {
    echo "Column 'last_discord_activity' added successfully to 'utenti' table.\n";
} else {
    echo "Error or column already exists: " . $mysqli->error . "\n";
}
exit;
