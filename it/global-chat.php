<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once '../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/chat_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/chat_v2_helpers.php';

if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->set_charset('utf8mb4');
}

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = 'Per accedere alla chat globale devi essere loggato';
    header('Location: accedi');
    exit();
}

$userId = (int)$_SESSION['user_id'];
$username = (string)($_SESSION['username'] ?? 'utente');
$userRole = (string)($_SESSION['ruolo'] ?? 'utente');
$csrf = chat_csrf_token();

if (!isset($_SESSION['lineeGuidaChat'])) {
    $stmt = $mysqli->prepare('SELECT lineeGuidaChat FROM utenti WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($lineeGuida);
    $stmt->fetch();
    $stmt->close();
    $_SESSION['lineeGuidaChat'] = (int)$lineeGuida;
}

$lineeGuidaChat = (int)($_SESSION['lineeGuidaChat'] ?? 0);
$onlineCount = $lineeGuidaChat === 1 ? chat_get_online_count($mysqli) : 0;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/css/chat-v2.css?v=2.4-mobile-hardfix">
    <script src="/js/chat-v2.js?v=2.4-mobile-hardfix" defer></script>
    <title>Chat Globale - Cripsum</title>
</head>
<body class="chat-v2-body" data-user-id="<?php echo $userId; ?>" data-user-role="<?php echo htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8'); ?>" data-csrf="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
    <?php include '../includes/navbar.php'; ?>

    <div class="chat-bg" aria-hidden="true">
        <span class="chat-orb chat-orb--one"></span>
        <span class="chat-orb chat-orb--two"></span>
        <span class="chat-grid"></span>
    </div>

    <?php if ($lineeGuidaChat !== 1): ?>
        <div class="modal fade chat-guidelines-modal" id="chatGuidelinesModal" tabindex="-1" aria-labelledby="chatGuidelinesModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="chatGuidelinesModalLabel"><i class="fas fa-shield-halved"></i> Prima di entrare</h5>
                    </div>
                    <div class="modal-body">
                        <p>Rispetta gli altri, evita spam e non inviare contenuti pericolosi.</p>
                        <a href="chat-policy" target="_blank" class="chat-policy-link"><i class="fas fa-book-open"></i> Linee guida</a>
                        <form method="POST" action="/includes/accept_chat_terms.php" class="chat-guidelines-form">
                            <label class="chat-check">
                                <input type="checkbox" id="acceptTerms" required>
                                <span>Ho letto e accetto</span>
                            </label>
                            <button type="submit" id="acceptBtn" disabled>Continua</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modalElement = document.getElementById('chatGuidelinesModal');
                if (window.bootstrap && modalElement) new bootstrap.Modal(modalElement).show();
                const checkbox = document.getElementById('acceptTerms');
                const button = document.getElementById('acceptBtn');
                checkbox?.addEventListener('change', () => button.disabled = !checkbox.checked);
            });
        </script>
    <?php else: ?>
        <main class="chat-shell" id="chatApp">
            <section class="chat-panel" aria-label="Chat Globale">
                <header class="chat-topbar">
                    <div class="chat-title-block">
                        <span class="chat-kicker"><i class="fas fa-globe"></i> Globale</span>
                        <h1>Chat</h1>
                    </div>

                    <div class="chat-meta-actions">
                        <span class="chat-online" title="Utenti attivi">
                            <span class="chat-online-dot"></span>
                            <strong id="chatOnlineCount"><?php echo (int)$onlineCount; ?></strong>
                        </span>
                        <span class="chat-sync-state" id="chatSyncState" title="Stato chat"><i class="fas fa-circle"></i><span>Live</span></span>
                        <button type="button" class="chat-icon-button js-toggle-search" aria-label="Cerca messaggi"><i class="fas fa-search"></i></button>
                        <button type="button" class="chat-icon-button js-toggle-sound" aria-label="Notifiche audio"><i class="fas fa-volume-xmark"></i></button>
                    </div>
                </header>

                <div class="chat-search" id="chatSearch" hidden>
                    <i class="fas fa-search"></i>
                    <input type="search" id="chatSearchInput" placeholder="Cerca nella chat..." maxlength="80" autocomplete="off">
                    <button type="button" class="js-clear-search" aria-label="Cancella ricerca"><i class="fas fa-xmark"></i></button>
                </div>

                <div class="chat-loadbar">
                    <button type="button" class="chat-load-old js-load-older" hidden>Carica messaggi vecchi</button>
                </div>

                <div class="chat-messages" id="chatMessages" aria-live="polite">
                    <div class="chat-loading">
                        <span></span><span></span><span></span>
                    </div>
                </div>

                <button type="button" class="chat-new-messages js-new-messages" hidden>Nuovi messaggi</button>
                <button type="button" class="chat-scroll-bottom js-scroll-bottom" hidden aria-label="Torna in basso"><i class="fas fa-arrow-down"></i></button>

                <div class="chat-typing" id="chatTyping" hidden></div>

                <footer class="chat-composer">
                    <div class="chat-reply-preview" id="replyPreview" hidden>
                        <div>
                            <small>Risposta a <strong id="replyUsername"></strong></small>
                            <span id="replyText"></span>
                        </div>
                        <button type="button" class="js-cancel-reply" aria-label="Annulla risposta"><i class="fas fa-xmark"></i></button>
                    </div>

                    <div class="chat-edit-preview" id="editPreview" hidden>
                        <div>
                            <small>Modifica messaggio</small>
                            <span>Invia per salvare. Esc per annullare.</span>
                        </div>
                        <button type="button" class="js-cancel-edit" aria-label="Annulla modifica"><i class="fas fa-xmark"></i></button>
                    </div>

                    <div class="chat-gif-panel" id="chatGifPanel" hidden>
                        <div class="chat-gif-head">
                            <div class="chat-gif-search">
                                <i class="fas fa-magnifying-glass"></i>
                                <input type="search" id="chatGifSearch" placeholder="Cerca GIF su GIPHY..." maxlength="60" autocomplete="off">
                            </div>
                            <button type="button" class="chat-icon-button js-close-gifs" aria-label="Chiudi GIF"><i class="fas fa-xmark"></i></button>
                        </div>
                        <div class="chat-gif-grid" id="chatGifGrid"></div>
                        <button type="button" class="chat-gif-more js-more-gifs" hidden>Carica altre GIF</button>
                        <small class="chat-giphy-credit">Powered by GIPHY</small>
                    </div>

                    <div class="chat-emoji-strip" id="chatEmojiStrip" hidden>
                        <button type="button" data-emoji="😭">😭</button>
                        <button type="button" data-emoji="🙏">🙏</button>
                        <button type="button" data-emoji="🔥">🔥</button>
                        <button type="button" data-emoji="💀">💀</button>
                        <button type="button" data-emoji="💯">💯</button>
                        <button type="button" data-emoji="🗣️">🗣️</button>
                    </div>

                    <form id="chatForm" class="chat-form" autocomplete="off">
                        <img class="chat-my-avatar" src="/includes/get_pfp.php?id=<?php echo $userId; ?>" alt="">
                        <button type="button" class="chat-tool-button js-toggle-gifs" aria-label="Apri GIF"><i class="fas fa-image"></i></button>
                        <button type="button" class="chat-tool-button js-toggle-emojis" aria-label="Emoji rapide"><i class="far fa-face-smile"></i></button>
                        <div class="chat-input-wrap">
                            <textarea id="chatInput" rows="1" maxlength="<?php echo (int)MAX_MESSAGE_LENGTH; ?>" placeholder="Scrivi..." aria-label="Messaggio"></textarea>
                            <div class="chat-input-footer">
                                <span id="chatCounter">0/<?php echo (int)MAX_MESSAGE_LENGTH; ?></span>
                                <span>Enter invia · Shift+Enter va a capo</span>
                            </div>
                        </div>
                        <button type="submit" class="chat-send" id="chatSendButton" aria-label="Invia messaggio"><i class="fas fa-paper-plane"></i></button>
                    </form>
                </footer>
            </section>
        </main>

        <div class="chat-toast" id="chatToast" role="status" aria-live="polite"></div>
        <audio id="notificationSound" src="/audio/notification.mp3" preload="auto"></audio>

        <script>
            window.CripsumChat = {
                userId: <?php echo $userId; ?>,
                username: <?php echo json_encode($username, JSON_UNESCAPED_UNICODE); ?>,
                role: <?php echo json_encode($userRole, JSON_UNESCAPED_UNICODE); ?>,
                maxLength: <?php echo (int)MAX_MESSAGE_LENGTH; ?>,
                refreshInterval: <?php echo (int)AUTO_REFRESH_INTERVAL; ?>,
                csrf: <?php echo json_encode($csrf); ?>,
                endpoints: {
                    messages: '/api/chat/messages.php',
                    send: '/api/chat/send.php',
                    edit: '/api/chat/edit.php',
                    delete: '/api/chat/delete.php',
                    report: '/api/chat/report.php',
                    mute: '/api/chat/mute.php',
                    typing: '/api/chat/typing.php',
                    status: '/api/chat/status.php',
                    gifs: '/api/chat/gifs.php',
                    react: '/api/chat/react.php'
                }
            };
        </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
