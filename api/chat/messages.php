<?php
require_once __DIR__ . '/bootstrap.php';
$user = chat_require_login_json($mysqli);
$userId = (int)$user['id'];
chat_touch_user($mysqli, $userId);

$afterId = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;
$beforeId = isset($_GET['before_id']) ? (int)$_GET['before_id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : MESSAGES_PER_PAGE;
$search = trim((string)($_GET['search'] ?? ''));

$messages = chat_fetch_messages($mysqli, $userId, [
    'after_id' => $afterId,
    'before_id' => $beforeId,
    'limit' => $limit,
    'search' => $search,
]);

chat_json([
    'ok' => true,
    'messages' => $messages,
    'online_count' => chat_get_online_count($mysqli),
    'typing' => chat_get_typing_users($mysqli, $userId),
    'server_time' => date(DATE_ATOM),
]);
