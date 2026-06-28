<?php
// Script per rimuovere l'indice UNIQUE 'uniq_turn_action' che causava crash sui turni extra e start-of-turn passive logs
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

echo "Checking for uniq_turn_action index...\n";
$res = $mysqli->query("SHOW INDEX FROM game_match_actions WHERE Key_name = 'uniq_turn_action'");
if ($res && $res->num_rows > 0) {
    echo "uniq_turn_action index found. Dropping it...\n";
    $ok = $mysqli->query("ALTER TABLE game_match_actions DROP INDEX uniq_turn_action");
    if ($ok) {
        echo "SUCCESS: uniq_turn_action dropped.\n";
    } else {
        echo "ERROR: " . $mysqli->error . "\n";
    }
} else {
    echo "Index uniq_turn_action does not exist or was already dropped.\n";
}
