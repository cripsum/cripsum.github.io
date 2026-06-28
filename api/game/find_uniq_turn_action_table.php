<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

echo "Searching for uniq_turn_action index in all tables...\n";
$res = $mysqli->query("SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS WHERE INDEX_NAME = 'uniq_turn_action' AND TABLE_SCHEMA = DATABASE()");
if ($res) {
    if ($res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            echo "Table: " . $row['TABLE_NAME'] . " | Index: " . $row['INDEX_NAME'] . " | Column: " . $row['COLUMN_NAME'] . "\n";
        }
    } else {
        echo "Index 'uniq_turn_action' not found in any table.\n";
    }
} else {
    echo "Error: " . $mysqli->error . "\n";
}
