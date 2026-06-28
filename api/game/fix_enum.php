<?php
// Fix ENUM sulla colonna action_type per includere 'ultimate'
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

echo "Checking current ENUM...\n";
$res = $mysqli->query("SHOW COLUMNS FROM game_match_actions LIKE 'action_type'");
$row = $res->fetch_assoc();
echo "Current: " . $row['Type'] . "\n\n";

echo "Altering ENUM to include 'ultimate'...\n";
$ok = $mysqli->query("ALTER TABLE game_match_actions MODIFY COLUMN action_type ENUM('basic_attack','special_attack','ultimate','defend','charge','switch','system','forfeit') NOT NULL DEFAULT 'basic_attack'");

if ($ok) {
    echo "SUCCESS! ENUM updated.\n\n";

    // Fix existing rows that have empty action_type but are actually ultimates
    $fixed = $mysqli->query("UPDATE game_match_actions SET action_type='ultimate' WHERE action_type='' AND message LIKE '%ULTIMATE%'");
    echo "Fixed " . $mysqli->affected_rows . " existing ultimate rows with empty action_type.\n";
} else {
    echo "ERROR: " . $mysqli->error . "\n";
}

echo "\nVerifying...\n";
$res2 = $mysqli->query("SHOW COLUMNS FROM game_match_actions LIKE 'action_type'");
$row2 = $res2->fetch_assoc();
echo "New: " . $row2['Type'] . "\n";
