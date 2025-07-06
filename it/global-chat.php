<?php
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Chat - Cripsum</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/chat.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="chat-container">
            <div class="messages" id="messages">
                <div class="text-center text-muted">
                    <p>Caricamento messaggi...</p>
                </div>
            </div>
            <div class="message-input">
                <input type="text" 
                       id="message" 
                       maxlength="<?php echo MAX_MESSAGE_LENGTH; ?>" 
                       placeholder="Scrivi un messaggio... (max <?php echo MAX_MESSAGE_LENGTH; ?> caratteri)">
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
    </script>
    <script src="../js/chat.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>