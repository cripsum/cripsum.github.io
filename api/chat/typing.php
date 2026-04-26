<?php
require_once __DIR__ . '/bootstrap.php';
$user = chat_require_login_json($mysqli);
$userId = (int)$user['id'];
$data = chat_read_input();
chat_verify_csrf($data);
chat_touch_user($mysqli, $userId);

$isTyping = !empty($data['typing']);
chat_upsert_typing($mysqli, $userId, $isTyping);
chat_json(['ok' => true, 'typing' => chat_get_typing_users($mysqli, $userId), 'online_count' => chat_get_online_count($mysqli)]);
