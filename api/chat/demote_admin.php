<?php
/** Toglie il ruolo di admin a un membro (solo il proprietario). */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    cg_set_role($mysqli, $userId, (int)($input['chat_id'] ?? 0), (int)($input['member_id'] ?? 0), 'member');
    send_success([]);
});
