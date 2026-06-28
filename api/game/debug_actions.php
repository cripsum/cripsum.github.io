<?php
// Diagnostic: dump last 5 actions from the database as JSON
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$res = $mysqli->query('SELECT id, match_id, user_id, turn_number, action_type, actor_card_id, target_card_id, damage, LEFT(message, 120) as msg FROM game_match_actions ORDER BY id DESC LIMIT 5');
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
echo json_encode(['actions' => $rows, 'ok' => true], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
