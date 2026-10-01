<?php
/** Eliminazione di un messaggio di gruppo: il proprio, o quello di un altro per proprietario e admin. */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    send_success(cg_delete($mysqli, $userId, (int)($input['message_id'] ?? 0)));
});
