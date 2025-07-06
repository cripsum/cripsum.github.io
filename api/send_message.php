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
    exit('Access denied. You must be logged in to send messages.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message']);
    $userId = $_SESSION['user_id'];

    if (strlen($message) > MAX_MESSAGE_LENGTH) {
        http_response_code(400);
        exit('Message exceeds maximum length of ' . MAX_MESSAGE_LENGTH . ' characters.');
    }

    if (time() - ($_SESSION['last_message_time'] ?? 0) < MESSAGE_TIMEOUT) {
        http_response_code(429);
        exit('You must wait before sending another message.');
    }

    $stmt = $mysqli->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $userId, $message);

    if ($stmt->execute()) {
        $_SESSION['last_message_time'] = time();
        echo json_encode(['status' => 'success', 'message' => 'Message sent successfully.']);
    } else {
        http_response_code(500);
        exit('Error sending message. Please try again later.');
    }

    $stmt->close();
} else {
    http_response_code(405);
    exit('Method not allowed. Please use POST to send messages.');
}
?>