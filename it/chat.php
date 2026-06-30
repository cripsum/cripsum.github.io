<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Sicurezza: L'utente deve essere loggato per accedere alla chat
if (!isLoggedIn()) {
    header("Location: accedi");
    exit();
}

$myUserId = (int)$_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Chat Privata</title>
    <link rel="stylesheet" href="/assets/chat/chat.css?v=2.1">
</head>

<body class="chat-page-body" data-user-id="<?php echo $myUserId; ?>" data-csrf="<?php echo csrf_token(); ?>">
    <?php include '../includes/navbar.php'; ?>

    <!-- Background Orbs -->
    <div class="chat-bg-orbs" aria-hidden="true">
        <span class="chat-orb chat-orb--1"></span>
        <span class="chat-orb chat-orb--2"></span>
    </div>

    <div class="chat-shell">
        <!-- Sidebar (Conversazioni) -->
        <aside class="chat-sidebar">
            <div class="chat-sidebar__header">
                <div class="chat-sidebar__title">
                    <span>Messaggi</span>
                    <i class="fa-regular fa-comment-dots text-muted"></i>
                </div>
                <div class="chat-sidebar__search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="chatSearchInput" placeholder="Cerca chat o utenti...">
                </div>
            </div>

            <div class="chat-sidebar__tabs">
                <button class="chat-tab-btn is-active" id="tab-active" type="button">Attive</button>
                <button class="chat-tab-btn" id="tab-archived" type="button">Archiviate</button>
            </div>

            <div class="chat-list">
                <!-- Caricato dinamicamente via JS -->
            </div>
        </aside>

        <!-- Area Chat Principale -->
        <main class="chat-area">
            <!-- Header Chat -->
            <div class="chat-area__header">
                <div class="chat-area__user" id="chatHeaderUser">
                    <button class="chat-action-btn d-md-none me-2" id="chatBackBtn" type="button" aria-label="Indietro">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                    <img class="chat-area__user-avatar" id="chatHeaderAvatar" src="" alt="" style="display: none;">
                    <div>
                        <div class="chat-area__user-name" id="chatHeaderName">Seleziona una chat</div>
                        <div class="chat-area__user-status" id="chatHeaderStatus"></div>
                    </div>
                </div>

                <div class="chat-area__actions">
                    <button class="chat-action-btn" id="chatInfoBtn" type="button" title="Informazioni chat">
                        <i class="fa-solid fa-circle-info"></i>
                    </button>
                </div>
            </div>

            <!-- Area Messaggi -->
            <div class="chat-messages">
                <div class="text-center py-5 text-muted my-auto">
                    <i class="fa-regular fa-paper-plane fs-1 mb-3"></i>
                    <br>
                    Scegli un utente dalla barra laterale per iniziare a chattare.
                </div>
            </div>

            <!-- Footer Input -->
            <div class="chat-input-area">
                <!-- Barra di risposta (nascosta di default) -->
                <div class="chat-input-reply-bar" id="chatReplyBar" style="display: none;">
                    <div>
                        <i class="fa-solid fa-reply text-muted me-2"></i>
                        Risposta a <strong class="chat-reply-user">Utente</strong>: 
                        <span class="chat-reply-text text-muted">Testo...</span>
                    </div>
                    <i class="fa-solid fa-xmark chat-input-reply-cancel" id="cancelReplyBtn"></i>
                </div>

                <!-- GIF Panel -->
                <div class="chat-gif-panel" id="chatGifPanel" hidden style="background: var(--chat-panel-bg); border-top: 1px solid var(--chat-border); padding: 10px;">
                    <div class="chat-gif-head" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                        <div class="chat-gif-search" style="flex: 1; position: relative;">
                            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 10px; color: var(--chat-text-muted);"></i>
                            <input type="search" id="chatGifSearch" placeholder="Cerca GIF su GIPHY..." maxlength="60" autocomplete="off" style="width: 100%; padding: 8px 12px 8px 30px; background: rgba(0,0,0,0.2); border: 1px solid var(--chat-border); border-radius: 8px; color: white;">
                        </div>
                        <button type="button" class="chat-action-btn js-close-gifs" style="background:none; border:none; color:white;"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div class="chat-gif-grid" id="chatGifGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; max-height: 180px; overflow-y: auto;"></div>
                    <button type="button" class="chat-gif-more js-more-gifs" hidden style="width: 100%; margin-top: 10px; background: rgba(255,255,255,0.05); border: 1px solid var(--chat-border); color: white; padding: 6px; border-radius: 6px;">Carica altre GIF</button>
                    <small class="chat-giphy-credit" style="display: block; text-align: right; font-size: 10px; color: var(--chat-text-muted); margin-top: 6px;">Powered by GIPHY</small>
                </div>

                <!-- Emoji Strip -->
                <div class="chat-emoji-strip" id="chatEmojiStrip" hidden style="display: flex; gap: 8px; padding: 10px; border-top: 1px solid var(--chat-border); overflow-x: auto; background: var(--chat-panel-bg); max-width: 100%;">
                    <button type="button" data-emoji="😭" style="background:none; border:none; font-size: 20px;">😭</button>
                    <button type="button" data-emoji="🙏" style="background:none; border:none; font-size: 20px;">🙏</button>
                    <button type="button" data-emoji="🔥" style="background:none; border:none; font-size: 20px;">🔥</button>
                    <button type="button" data-emoji="💀" style="background:none; border:none; font-size: 20px;">💀</button>
                    <button type="button" data-emoji="💯" style="background:none; border:none; font-size: 20px;">💯</button>
                    <button type="button" data-emoji="😂" style="background:none; border:none; font-size: 20px;">😂</button>
                    <button type="button" data-emoji="❤️" style="background:none; border:none; font-size: 20px;">❤️</button>
                    <button type="button" data-emoji="👍" style="background:none; border:none; font-size: 20px;">👍</button>
                    <button type="button" data-emoji="👀" style="background:none; border:none; font-size: 20px;">👀</button>
                    <button type="button" data-emoji="🗣️" style="background:none; border:none; font-size: 20px;">🗣️</button>
                </div>

                <div class="chat-input-row">
                    <button class="chat-action-btn" id="chatAttachBtn" type="button" title="Allega file">
                        <i class="fa-solid fa-paperclip"></i>
                    </button>
                    <input type="file" id="chatFileInput" style="display: none;">
                    
                    <button class="chat-action-btn js-toggle-gifs" type="button" title="GIF">
                        <i class="fa-solid fa-image"></i>
                    </button>
                    <button class="chat-action-btn js-toggle-emojis" type="button" title="Emoji">
                        <i class="fa-regular fa-face-smile"></i>
                    </button>

                    <div class="chat-input-wrapper">
                        <textarea class="chat-input-textarea" id="chatTextarea" rows="1" placeholder="Scrivi un messaggio..."></textarea>
                    </div>

                    <button class="chat-action-btn" id="chatSendBtn" type="button" title="Invia messaggio" style="background: var(--chat-accent); color: white;">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </main>

        <!-- Pannello Dettagli (Destra, collassabile) -->
        <aside class="chat-details is-hidden">
            <div class="chat-details__header">
                <h5 class="m-0 font-weight-bold">Dettagli</h5>
                <button class="chat-action-btn" id="chatDetailsCloseBtn" type="button">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="chat-details__scroll">
                <!-- Caricato dinamicamente via JS -->
            </div>
        </aside>
    </div>

    <!-- Menu Contestuale Personalizzato -->
    <div class="chat-context-menu" id="chatContextMenu"></div>

    <!-- Toast Notifications -->
    <div class="admin-toast" id="chatToast" style="position: fixed; bottom: 20px; right: 20px; z-index: 10000; transition: opacity 0.3s; pointer-events: none;"></div>

    <script src="/assets/chat/chat-api.js?v=1.3" defer></script>
    <script src="/assets/chat/chat-state.js?v=1.2" defer></script>
    <script src="/assets/chat/chat-ui.js?v=2.2" defer></script>
    <script src="/assets/chat/chat.js?v=2.2" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
