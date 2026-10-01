<?php
/** Riattiva le notifiche di un gruppo. */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    cg_mute($mysqli, $userId, (int)($input['chat_id'] ?? 0), 0);
    send_success(['muted' => false]);
});
