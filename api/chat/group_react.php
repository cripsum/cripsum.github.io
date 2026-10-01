<?php
/** Reazione a un messaggio di gruppo (la mette se manca, la toglie se c'è). */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    send_success(cg_react($mysqli, $userId, (int)($input['message_id'] ?? 0), trim((string)($input['reaction'] ?? ''))));
});
