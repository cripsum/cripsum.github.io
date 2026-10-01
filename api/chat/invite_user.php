<?php
/** Invita un amico in un gruppo. */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    cg_invite($mysqli, $userId, (int)($input['chat_id'] ?? 0), (int)($input['invitee_id'] ?? 0));
    send_success(['message' => rt_t('Invito inviato.', 'Invitation sent.')]);
});
