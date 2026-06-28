<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== ALL INDEXES OF game_match_actions ===\n";
$res = $mysqli->query("SHOW INDEX FROM game_match_actions");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "Table: " . $row['Table'] . " | Key_name: " . $row['Key_name'] . " | Column_name: " . $row['Column_name'] . " | Non_unique: " . $row['Non_unique'] . "\n";
    }
} else {
    echo "Error: " . $mysqli->error . "\n";
}
