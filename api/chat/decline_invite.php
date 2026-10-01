<?php
/** Rifiuta un invito a un gruppo. */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    cg_answer_invite($mysqli, $userId, (int)(get_json_input()['chat_id'] ?? 0), false);
    send_success([]);
});
