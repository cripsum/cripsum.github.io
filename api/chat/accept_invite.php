<?php
/** Accetta un invito a un gruppo. */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $chatId = (int)(get_json_input()['chat_id'] ?? 0);
    cg_answer_invite($mysqli, $userId, $chatId, true);
    send_success(['chat_id' => $chatId]);
});
