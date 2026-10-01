<?php
/**
 * Eliminazione di un messaggio della chat globale: il proprio, oppure (staff)
 * quello di un altro, con un motivo facoltativo che arriva all'autore e
 * resta nel registro dello staff.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/chat_global.php';

chat_run(static function () use ($mysqli, $chatUser): void {
    $input = get_json_input();
    $messageId = (int)($input['id'] ?? 0);
    $message = gc_delete($mysqli, $chatUser, $messageId, (string)($input['reason'] ?? ''));
    send_success([
        'id' => $messageId,
        'message' => $message ? gc_view($message, $chatUser['id'], $chatUser['ruolo'], $chatUser['username']) : null,
    ]);
});
