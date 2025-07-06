<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/chat_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/chat_functions.php';

session_start();

if (!isLoggedIn()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Access denied. User not logged in.']));
}

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    http_response_code(500);
    exit(json_encode(['error' => 'Database connection failed.']));
}

$query = "SELECT m.id, m.user_id, m.message, m.timestamp, r.reply_to, u.username 
          FROM messages m 
          LEFT JOIN replies r ON m.id = r.message_id 
          JOIN utenti u ON m.user_id = u.id 
          ORDER BY m.timestamp DESC";

$result = $mysqli->query($query);

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => $row['id'],
        'user_id' => $row['user_id'],
        'message' => $row['message'],
        'timestamp' => $row['timestamp'],
        'reply_to' => $row['reply_to'],
        'username' => $row['username']
    ];
}

$mysqli->close();

header('Content-Type: application/json');
echo json_encode($messages);
?>