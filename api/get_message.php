<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/chat_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/chat_functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non sei autenticato']);
    exit();
}

$lastMessageId = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
$currentUserId = $_SESSION['user_id'];
$userRole = $_SESSION['ruolo'] ?? 'utente';

if ($lastMessageId === 0) {
    $messages = getAllMessages($mysqli);
} else {
    $messages = getMessages($mysqli, $lastMessageId);
}

foreach ($messages as $message) {
    $profilePicUrl = "/includes/get_pfp.php?id=" . $message['user_id'];
    $time = date('H:i', strtotime($message['created_at']));
    $canDelete = ($userRole === 'admin' || $message['user_id'] == $currentUserId);
    
    echo '<div class="message" data-message-id="' . $message['id'] . '">';
    echo '<div class="message-header">';
    echo '<img src="' . $profilePicUrl . '" alt="' . htmlspecialchars($message['username']) . '" class="profile-pic">';
    echo '<span class="username' . ($message['ruolo'] === 'admin' ? ' admin' : '') . '">' . htmlspecialchars($message['username']) . '</span>';
    echo '<span class="timestamp">' . $time . '</span>';
    echo '<div class="message-actions">';
    echo '<button class="reply-btn" onclick="startReply(' . $message['id'] . ', \'' . htmlspecialchars($message['username']) . '\', \'' . htmlspecialchars($message['message']) . '\')" title="Rispondi">↩️</button>';
    if ($canDelete) {
        echo '<button class="delete-btn" onclick="deleteMessage(' . $message['id'] . ')" title="Elimina">🗑️</button>';
    }
    echo '</div>';
    echo '</div>';
    
    if ($message['reply_to'] && $message['reply_message']) {
        echo '<div class="reply-preview">';
        echo '<span class="reply-author">@' . htmlspecialchars($message['reply_username']) . '</span>';
        echo '<span class="reply-text">' . htmlspecialchars($message['reply_message']) . '</span>';
        echo '</div>';
    }
    
    echo '<div class="message-content">' . htmlspecialchars($message['message']) . '</div>';
    echo '</div>';
}

?>