<?php
require_once __DIR__ . '/bootstrap.php';
$user = chat_require_login_json($mysqli);
$userId = (int)$user['id'];
chat_touch_user($mysqli, $userId);
chat_json([
    'ok' => true,
    'online_count' => chat_get_online_count($mysqli),
    'typing' => chat_get_typing_users($mysqli, $userId),
    'server_time' => date(DATE_ATOM),
]);
