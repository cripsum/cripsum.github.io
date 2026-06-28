<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: text/plain');

echo "--- COLONNE game_match_actions ---" . PHP_EOL;
$res = $mysqli->query('DESCRIBE game_match_actions');
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . PHP_EOL;
}

echo PHP_EOL . "--- COLONNE game_match_cards ---" . PHP_EOL;
$res2 = $mysqli->query('DESCRIBE game_match_cards');
while ($row = $res2->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . PHP_EOL;
}

echo PHP_EOL . "--- ULTIME 3 AZIONI REGISTRATE ---" . PHP_EOL;
$res3 = $mysqli->query('SELECT * FROM game_match_actions ORDER BY id DESC LIMIT 3');
while ($row = $res3->fetch_assoc()) {
    print_r($row);
}
