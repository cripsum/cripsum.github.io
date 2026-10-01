<?php
/** Nome, descrizione e permessi di un gruppo. */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    cg_update($mysqli, $userId, (int)($input['chat_id'] ?? 0), $input);
    send_success([]);
});
