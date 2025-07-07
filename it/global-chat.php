<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 0);
error_reporting(0);

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
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-T0CTM2SBJJ"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() {
                dataLayer.push(arguments);
            }
            gtag("js", new Date());

            gtag("config", "G-T0CTM2SBJJ");
        </script>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
            rel="stylesheet"
            integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
            crossorigin="anonymous"
        />
        <link rel="icon" href="../img/Susremaster.png" type="image/png" />
        <link rel="shortcut icon" href="../img/Susremaster.png" type="image/png" />
        <link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet" />
        <link rel="stylesheet" href="../css/style.css" />
        <link rel="stylesheet" href="../css/style-dark.css" />
        <link rel="stylesheet" href="../css/animations.css" />
        <link rel="stylesheet" href="../css/achievement-style.css" />
        <link rel="stylesheet" href="../css/chat.css?v=504">
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
        <script src="../js/animations.js"></script>

        <script src="../js/controlloLingua-it.js"></script>
        <script src="../js/controlloTema.js"></script>
        <script src="../js/unlockAchievement-it.js"></script>
        <script src="../js/achievements-globali.js"></script>
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
    <script src="../js/chat.js?v=504"></script>
    <script src="../js/chat-utils.js?v=504"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>