<?php
/** Annulla un invito in sospeso (chi l'ha mandato, oppure proprietario e admin). */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    cg_cancel_invite($mysqli, $userId, (int)($input['chat_id'] ?? 0), (int)($input['invitee_id'] ?? 0));
    send_success([]);
});
