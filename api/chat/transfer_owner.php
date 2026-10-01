<?php
/** Passa la proprietà di un gruppo a un altro membro (solo il proprietario). */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    cg_set_role($mysqli, $userId, (int)($input['chat_id'] ?? 0), (int)($input['member_id'] ?? 0), 'owner');
    send_success([]);
});
