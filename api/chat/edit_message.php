<?php
/** Modifica di un proprio messaggio in un gruppo. */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $messageId = (int)($input['message_id'] ?? 0);
    $result = cg_edit($mysqli, $userId, $messageId, (string)($input['content'] ?? ''));
    send_success(['message_id' => $messageId, 'body' => $result['message']['body'] ?? '', 'message' => $result['message']]);
});
