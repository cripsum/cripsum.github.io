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
checkBan($mysqli);


if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Per accedere alla chat globale devi essere loggato";

    header('Location: accedi');
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$userRole = $_SESSION['ruolo'] ?? 'utente';
$profilePic = "/includes/get_pfp.php?id=$userId";


if (!isset($_SESSION['lineeGuidaChat'])) {
    $stmt = $mysqli->prepare("SELECT lineeGuidaChat FROM utenti WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($lineeGuida);
    $stmt->fetch();
    $stmt->close();
    $_SESSION['lineeGuidaChat'] = $lineeGuida;
}
$lineeGuidaChat = $_SESSION['lineeGuidaChat'];

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
    <?php if (!isset($_SESSION['lineeGuidaChat']) || $_SESSION['lineeGuidaChat'] == 0): ?>
        <div class="container mt-4 fadeup testobianco" style="padding-top: 7rem; margin: auto;">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card card-gay">
                        <div class="card-header">
                            <h5 class="mb-0 testobianco text-center">Accetta le Linee Guida della Chat</h5>
                        </div>
                        <div class="card-body testobianco text-center">
                            <p>Prima di accedere alla chat globale, devi leggere e accettare le nostre linee guida.</p>
                            <div class="d-grid gap-2">
                                <a href="chat-policy" class="btn btn-secondary bottone" style="margin: auto; max-width: 200px;">Leggi le Linee Guida</a>
                                <form method="POST" action="/includes/accept_chat_terms.php" style="text-align: center; margin: auto; max-width: 400px;">
                                    <button type="submit" class="btn btn-secondary bottone ">Ho letto e accetto le Linee Guida</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['lineeGuidaChat']) && $_SESSION['lineeGuidaChat'] == 1): ?>
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
    <?php endif; ?>

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

            function getCookie(name) {
                const cookies = document.cookie.split("; ");
                for (let cookie of cookies) {
                    let [key, value] = cookie.split("=");
                    if (key === name) return JSON.parse(value);
                }
                return null;
            }

            function setCookie(name, value) {
                document.cookie = `${name}=${JSON.stringify(value)}; path=/; expires=Fri, 31 Dec 9999 23:59:59 GMT`;
            }
    </script>
    <script src="../js/chat.js?v=508"></script>
    <script src="../js/chat-utils.js?v=508"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>