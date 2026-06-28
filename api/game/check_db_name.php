<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

echo "Current Database: ";
$res = $mysqli->query("SELECT DATABASE()");
$row = $res->fetch_row();
echo $row[0] . "\n\n";

echo "=== Tables in Database ===\n";
$res2 = $mysqli->query("SHOW TABLES");
while ($row2 = $res2->fetch_row()) {
    echo $row2[0] . "\n";
}

echo "\n=== CREATE TABLE game_match_actions ===\n";
$res3 = $mysqli->query("SHOW CREATE TABLE game_match_actions");
if ($res3) {
    $row3 = $res3->fetch_assoc();
    echo $row3['Create Table'] . "\n";
} else {
    echo "Error: " . $mysqli->error . "\n";
}
