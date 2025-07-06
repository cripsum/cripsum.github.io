<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/chat_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/chat_functions.php';

session_start();

// Pulisci il buffer di output
if (ob_get_level()) {
    ob_clean();
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non sei autenticato']);
    exit();
}

$lastMessageId = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
$currentUserId = $_SESSION['user_id'];
$userRole = $_SESSION['ruolo'] ?? 'utente';

try {
    if ($lastMessageId === 0) {
        $messages = getAllMessages($mysqli);
    } else {
        $messages = getMessages($mysqli, $lastMessageId);
    }

    foreach ($messages as $message) {
        $profilePic = "/includes/get_pfp.php?id=" . $message['user_id'];
        $canDelete = ($message['user_id'] == $currentUserId) || ($userRole === 'admin');
        
        echo '<div class="message" data-message-id="' . $message['id'] . '">';
        echo '<img src="' . $profilePic . '" alt="Profile" class="profile-pic">';
        echo '<div class="message-content">';
        echo '<div class="message-header">';
        echo '<a href="../user?id=' . $message['user_id'] . '" class="message-username-link"><span class="message-username">' . htmlspecialchars($message['username']) . ' - ' . htmlspecialchars($message['ruolo']) . '</span></a>';
        $date = new DateTime($message['created_at'], new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('Europe/Rome'));
        echo '<span class="message-time">' . $date->format('H:i') . '</span>';
        echo '</div>';

        
        if ($message['reply_to']) {
            echo '<div class="reply-to">Risposta a: ' . htmlspecialchars($message['reply_username']) . ': ' . htmlspecialchars(string: $message['reply_message']) . '</div>';
        }
        
        echo '<p class="message-text">' . htmlspecialchars($message['message']) . '</p>';
        echo '</div>';
        
        if ($canDelete) {
            echo '<div class="message-actions">';
            echo '<button class="btn btn-sm btn-outline-danger" onclick="deleteMessage(' . $message['id'] . ')">Elimina</button>';
            echo '<button class="btn btn-sm btn-outline-primary" onclick="window.startReply(' . $message['id'] . ', \'' . htmlspecialchars($message['username']) . '\', \'' . htmlspecialchars($message['message']) . '\')">Rispondi</button>';
            echo '</div>';
        }
        
        echo '</div>';
    }
} catch (Exception $e) {
    echo '<div class="text-center text-danger"><p>Errore nel caricamento dei messaggi</p></div>';
}

exit();
?>