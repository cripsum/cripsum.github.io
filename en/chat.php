<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Security: User must be logged in to access the chat
if (!isLoggedIn()) {
    header("Location: login");
    exit();
}

$myUserId = (int)$_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Private Chat</title>
    <link rel="stylesheet" href="/assets/chat/chat.css?v=1.0">
</head>

<body class="chat-page-body" data-user-id="<?php echo $myUserId; ?>">
    <?php include '../includes/navbar.php'; ?>

    <!-- Background Orbs -->
    <div class="chat-bg-orbs" aria-hidden="true">
        <span class="chat-orb chat-orb--1"></span>
        <span class="chat-orb chat-orb--2"></span>
    </div>

    <div class="chat-shell">
        <!-- Sidebar (Conversations) -->
        <aside class="chat-sidebar">
            <div class="chat-sidebar__header">
                <div class="chat-sidebar__title">
                    <span>Messages</span>
                    <i class="fa-regular fa-comment-dots text-muted"></i>
                </div>
                <div class="chat-sidebar__search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="chatSearchInput" placeholder="Search chats or users...">
                </div>
            </div>

            <div class="chat-sidebar__tabs">
                <button class="chat-tab-btn is-active" id="tab-active" type="button">Active</button>
                <button class="chat-tab-btn" id="tab-archived" type="button">Archived</button>
            </div>

            <div class="chat-list">
                <!-- Loaded dynamically via JS -->
            </div>
        </aside>

        <!-- Main Chat Area -->
        <main class="chat-area">
            <!-- Chat Header -->
            <div class="chat-area__header">
                <div class="chat-area__user" id="chatHeaderUser">
                    <button class="chat-action-btn d-md-none me-2" id="chatBackBtn" type="button" aria-label="Back">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                    <img class="chat-area__user-avatar" id="chatHeaderAvatar" src="" alt="">
                    <div>
                        <div class="chat-area__user-name" id="chatHeaderName">Select a conversation</div>
                        <div class="chat-area__user-status" id="chatHeaderStatus"></div>
                    </div>
                </div>

                <div class="chat-area__actions">
                    <button class="chat-action-btn" id="chatInfoBtn" type="button" title="Chat info">
                        <i class="fa-solid fa-circle-info"></i>
                    </button>
                </div>
            </div>

            <!-- Messages Area -->
            <div class="chat-messages">
                <div class="text-center py-5 text-muted my-auto">
                    <i class="fa-regular fa-paper-plane fs-1 mb-3"></i>
                    <br>
                    Choose a user from the sidebar to start chatting.
                </div>
            </div>

            <!-- Input Footer -->
            <div class="chat-input-area">
                <!-- Reply Bar (hidden by default) -->
                <div class="chat-input-reply-bar" id="chatReplyBar" style="display: none;">
                    <div>
                        <i class="fa-solid fa-reply text-muted me-2"></i>
                        Replying to <strong class="chat-reply-user">User</strong>: 
                        <span class="chat-reply-text text-muted">Text...</span>
                    </div>
                    <i class="fa-solid fa-xmark chat-input-reply-cancel" id="cancelReplyBtn"></i>
                </div>

                <div class="chat-input-row">
                    <button class="chat-action-btn" id="chatAttachBtn" type="button" title="Attach file">
                        <i class="fa-solid fa-paperclip"></i>
                    </button>
                    <input type="file" id="chatFileInput" style="display: none;">

                    <div class="chat-input-wrapper">
                        <textarea class="chat-input-textarea" id="chatTextarea" rows="1" placeholder="Type a message..."></textarea>
                    </div>

                    <button class="chat-action-btn" id="chatSendBtn" type="button" title="Send message" style="background: var(--chat-accent); color: white;">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </main>

        <!-- Details Panel (Right, collapsible) -->
        <aside class="chat-details is-hidden">
            <div class="chat-details__header">
                <h5 class="m-0 font-weight-bold">Details</h5>
                <button class="chat-action-btn" id="chatDetailsCloseBtn" type="button">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="chat-details__scroll">
                <!-- Loaded dynamically via JS -->
            </div>
        </aside>
    </div>

    <!-- Custom Context Menu -->
    <div class="chat-context-menu" id="chatContextMenu"></div>

    <!-- Toast Notifications -->
    <div class="admin-toast" id="chatToast" style="position: fixed; bottom: 20px; right: 20px; z-index: 10000; transition: opacity 0.3s; pointer-events: none;"></div>

    <script src="/assets/chat/chat.js?v=1.0" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
