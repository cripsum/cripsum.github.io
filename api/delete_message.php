<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/chat_config.php';

session_start();

if (!isLoggedIn()) {
    http_response_code(403);
    exit('Access denied. You must be logged in to delete messages.');
}

if (!isset($_POST['message_id']) || !is_numeric($_POST['message_id'])) {
    http_response_code(400);
    exit('Invalid message ID.');
}

$message_id = intval($_POST['message_id']);
$user_id = $_SESSION['user_id'];
$isAdmin = $_SESSION['ruolo'] === 'admin';

$stmt = $mysqli->prepare("SELECT user_id FROM messages WHERE id = ?");
$stmt->bind_param("i", $message_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    http_response_code(404);
    exit('Message not found.');
}

$stmt->bind_result($owner_id);
$stmt->fetch();
$stmt->close();

if ($owner_id !== $user_id && !$isAdmin) {
    http_response_code(403);
    exit('You do not have permission to delete this message.');
}

$deleteStmt = $mysqli->prepare("DELETE FROM messages WHERE id = ?");
$deleteStmt->bind_param("i", $message_id);

if ($deleteStmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Message deleted successfully.']);
} else {
    http_response_code(500);
    exit('Error deleting message.');
}

$deleteStmt->close();
?>