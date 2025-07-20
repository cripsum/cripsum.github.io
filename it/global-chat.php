<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 0);
error_reporting(0);

ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/chat_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/chat_functions.php';


if (!isLoggedIn()) {
    header('Location: accedi');
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$userRole = $_SESSION['ruolo'] ?? 'utente';
$profilePic = "/includes/get_pfp.php?id=$userId";

?>

<!DOCTYPE html>
<html lang="it">
<head>
        <?php include '../includes/head-import.php'; ?>
        <link rel="stylesheet" href="/css/chat.css?v=508">
    <title>Global Chat - Cripsum</title>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-4" style="padding-top: 7rem">
        <div class="chat-container">
            <div class="messages" id="messages">
                <div class="text-center text-muted">
                    <p>Caricamento messaggi...</p>
                </div>
            </div>
            <div class="input">
                <input type="text" 
                       id="message" 
                       maxlength="<?php echo MAX_MESSAGE_LENGTH; ?>" 
                       placeholder="Scrivi un messaggio...">
                <button id="send-button">Invia</button>
            </div>
        </div>
    </div>

    <audio id="notification-sound" src="../audio/notification.mp3" preload="auto"></audio>

    <script>
        // Variabili globali per JavaScript
        window.userId = <?php echo $userId; ?>;
        window.userRole = '<?php echo $userRole; ?>';
        window.maxMessageLength = <?php echo MAX_MESSAGE_LENGTH; ?>;
        window.messageTimeout = <?php echo MESSAGE_TIMEOUT * 1000; ?>; // Convert to milliseconds
        window.AUTO_REFRESH_INTERVAL = <?php echo AUTO_REFRESH_INTERVAL; ?>;

                document.addEventListener('DOMContentLoaded', function() {
            // Add a small delay to ensure all scripts are loaded
            setTimeout(function() {
                if (typeof initializeChat === 'function') {
                    initializeChat();
                }
            }, 100);
        });
    </script>
    <script src="../js/chat.js?v=508"></script>
    <script src="../js/chat-utils.js?v=508"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>