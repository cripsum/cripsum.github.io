<?php
/** Esce da un gruppo. Se esce il proprietario, il gruppo passa a un altro membro. */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    cg_leave($mysqli, $userId, (int)(get_json_input()['chat_id'] ?? 0));
    send_success([]);
});
