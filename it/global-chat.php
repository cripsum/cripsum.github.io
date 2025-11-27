<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 0);
error_reporting(0);

require_once '../config/session_init.php';
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
        <div class="modal fade" id="chatGuidelinesModal" tabindex="-1" aria-labelledby="chatGuidelinesModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content bg-dark text-light border-0 shadow-lg" style="border-radius: 15px;">
                    <div class="modal-header border-bottom border-secondary pb-4">
                        <h5 class="modal-title w-100 text-center text-white fw-bold" id="chatGuidelinesModalLabel" style="font-size: 1.5rem;">
                            <i class="fas fa-comments me-3 text-primary" style="font-size: 1.8rem;"></i>Benvenuto nella Chat Globale
                        </h5>
                    </div>
                    <div class="modal-body text-center py-5">
                        <div class="mb-5">
                            <div class="d-inline-block p-4 rounded-circle bg-primary bg-opacity-10 mb-4">
                                <i class="fas fa-shield-alt text-primary" style="font-size: 3.5rem;"></i>
                            </div>
                            <p class="lead mb-0 text-light opacity-75" style="font-size: 1.1rem; line-height: 1.6;">Prima di iniziare a chattare, è necessario accettare le nostre linee guida per mantenere un ambiente sicuro e rispettoso per tutti.</p>
                        </div>

                        <div class="d-grid gap-4">
                            <a href="chat-policy" class="btn btn-outline-light btn-lg py-3 rounded-pill text-decoration-none" target="_blank" style="border-width: 2px; transition: all 0.3s ease;">
                                <i class="fas fa-book-open me-2"></i>Leggi le Linee Guida
                            </a>

                            <div class="border-top border-secondary pt-4">
                                <form method="POST" action="/includes/accept_chat_terms.php">
                                    <div class="form-check mb-4 d-flex justify-content-center align-items-center">
                                        <input class="form-check-input bg-dark border-light me-3" type="checkbox" id="acceptTerms" required style="transform: scale(1.2);">
                                        <label class="form-check-label text-light opacity-75" for="acceptTerms" style="font-size: 1rem;">
                                            Ho letto e accetto le linee guida della chat
                                        </label>
                                    </div>
                                    <button type="submit" class="btn btn-light btn-lg px-5 py-3 text-dark fw-bold rounded-pill" disabled id="acceptBtn" style="min-width: 250px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);">
                                        <i class="fas fa-check me-2"></i>Accetta e Continua
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modal = new bootstrap.Modal(document.getElementById('chatGuidelinesModal'));
                modal.show();


                const checkbox = document.getElementById('acceptTerms');
                const acceptBtn = document.getElementById('acceptBtn');

                checkbox.addEventListener('change', function() {
                    acceptBtn.disabled = !this.checked;
                });
            });
        </script>
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
        window.userId = <?php echo $userId; ?>;
        window.userRole = '<?php echo $userRole; ?>';
        window.maxMessageLength = <?php echo MAX_MESSAGE_LENGTH; ?>;
        window.messageTimeout = <?php echo MESSAGE_TIMEOUT * 1000; ?>;
        window.AUTO_REFRESH_INTERVAL = <?php echo AUTO_REFRESH_INTERVAL; ?>;

        document.addEventListener('DOMContentLoaded', function() {

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