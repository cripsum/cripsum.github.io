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
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Per accedere alla chat globale devi essere loggato";

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
            <div
                id="popup-overlay"
                style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.85);
                    display: none;
                    align-items: center;
                    justify-content: center;
                    z-index: 9999;
                    opacity: 0;
                    transition: opacity 0.5s ease;
                "
            >
                <div
                    id="collegamentoedits"
                    class="collegamentoedit ombra fadeup"
                    style="
                        backdrop-filter: blur(15px);
                        background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(64, 64, 64, 0.1));
                        box-shadow: 0 0 8px 4px rgba(255, 255, 255, 0.5);
                        padding: 20px;
                        border: 1px solid rgba(255, 255, 255, 0.5);
                        border-radius: 10px;
                        max-width: 80%;
                        text-align: center;
                        position: relative;
                        opacity: 0;
                        transform: translateY(-20px);
                        transition: opacity 0.5s ease, transform 0.5s ease;
                    "
                >
                    <button style="position: absolute; top: 0px; right: 5px; background-color: transparent; border: none; cursor: pointer" onclick="location.href='home'">
                        <span class="close_div tastobianco" style="font-size: 20px; color: rgb(255, 255, 255)"
                            >&times;<span class="linkbianco" style="font-size: small; position: relative; top: -3px; left: 3px">chiudi</span></span
                        >
                    </button>
                    <div id="banner-content"></div>
                </div>
            </div>

            <script>
                function getRandomBanner() {
                    const banners = [
                        `<div class="bannerino">
            <h2 style="color: rgb(255, 255, 255); padding-top: 11px">Hey tu! Aspetta un attimo...</h2>
            <p style="color: rgb(255, 255, 255);">Accedendo alla chat, dichiari di aver letto e accettato le <a href="chat-policy" class="linkbianco">linee guida della chat</a>. Violazioni possono portare a ban temporanei o permanenti.</p>
            <button type="button" class="btn btn-secondary bottone" data-bs-dismiss="modal" onclick="closePopup()">Ho letto e accettato le linee guida</button>

        </div>`,
                    ];
                    return banners[Math.floor(Math.random() * banners.length)];
                }

                function showPopup() {
                    if (getCookie("lineeGuidaAccettate")) return;

                    const overlay = document.getElementById("popup-overlay");
                    const popup = document.getElementById("collegamentoedits");
                    document.getElementById("banner-content").innerHTML = getRandomBanner();
                    overlay.style.display = "flex";
                    document.body.style.overflow = "hidden";
                    setTimeout(() => {
                        overlay.style.opacity = "1";
                        popup.style.opacity = "1";
                        popup.style.transform = "translateY(0)";
                    }, 10);
                }

                function closePopup() {
                    const overlay = document.getElementById("popup-overlay");
                    const popup = document.getElementById("collegamentoedits");
                    popup.style.opacity = "0";
                    popup.style.transform = "translateY(-20px)";
                    overlay.style.opacity = "0";
                    document.body.style.overflow = "auto";
                    setTimeout(() => {
                        overlay.style.display = "none";
                    }, 500);
                    setCookie("lineeGuidaAccettate", true);
                }

                window.onload = function () {
                    setTimeout(showPopup, 700);
                };
            </script>

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