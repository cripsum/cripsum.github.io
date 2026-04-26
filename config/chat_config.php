<?php
if (!defined('MAX_MESSAGE_LENGTH')) define('MAX_MESSAGE_LENGTH', 500);
if (!defined('MESSAGE_TIMEOUT')) define('MESSAGE_TIMEOUT', 4);
if (!defined('MESSAGES_PER_PAGE')) define('MESSAGES_PER_PAGE', 40);
if (!defined('AUTO_REFRESH_INTERVAL')) define('AUTO_REFRESH_INTERVAL', 2500);
if (!defined('CHAT_TYPING_TTL')) define('CHAT_TYPING_TTL', 7);
if (!defined('CHAT_ONLINE_WINDOW')) define('CHAT_ONLINE_WINDOW', 60);
if (!defined('CHAT_MAX_SEARCH_LENGTH')) define('CHAT_MAX_SEARCH_LENGTH', 80);
if (!defined('CHAT_MAX_REPORT_REASON')) define('CHAT_MAX_REPORT_REASON', 300);
if (!defined('CHAT_EDIT_WINDOW_SECONDS')) define('CHAT_EDIT_WINDOW_SECONDS', 900);
if (!defined('CHAT_ALLOWED_MESSAGE_TAGS')) define('CHAT_ALLOWED_MESSAGE_TAGS', '');

// Parole base. Aggiungile anche nella tabella chat_word_filters se vuoi gestirle da DB.
if (!defined('CHAT_BANNED_WORDS')) {
    define('CHAT_BANNED_WORDS', json_encode([
        // 'parola1', 'parola2'
    ], JSON_UNESCAPED_UNICODE));
}
?>
