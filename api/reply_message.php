<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/chat_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/chat_functions.php';

session_start();

if (!isLoggedIn()) {
    http_response_code(403);
    exit('Access denied. You must be logged in to reply to messages.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $messageId = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    $replyContent = isset($_POST['reply_content']) ? trim($_POST['reply_content']) : '';

    if (strlen($replyContent) > MAX_MESSAGE_LENGTH) {
        http_response_code(400);
        exit('Reply exceeds maximum length of ' . MAX_MESSAGE_LENGTH . ' characters.');
    }

    if (empty($replyContent)) {
        http_response_code(400);
        exit('Reply content cannot be empty.');
    }

    $userId = $_SESSION['user_id'];
    $stmt = $mysqli->prepare("INSERT INTO replies (message_id, user_id, reply_content, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $messageId, $userId, $replyContent);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Reply added successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add reply.']);
    }

    $stmt->close();
} else {
    http_response_code(405);
    exit('Method not allowed. Please use POST to reply to messages.');
}
?>