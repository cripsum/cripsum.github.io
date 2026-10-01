<?php
/** Rimuove un membro (o ritira un invito) da un gruppo. */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $memberId = (int)($input['member_id'] ?? 0);
    cg_remove($mysqli, $userId, (int)($input['chat_id'] ?? 0), $memberId);
    send_success(['member_id' => $memberId]);
});
