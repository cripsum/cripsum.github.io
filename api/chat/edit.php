<?php
/** Modifica di un proprio messaggio nella chat globale. */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/chat_global.php';

chat_run(static function () use ($mysqli, $chatUser): void {
    $input = get_json_input();
    $message = gc_edit($mysqli, $chatUser, (int)($input['id'] ?? 0), (string)($input['message'] ?? ''));
    send_success(['message' => gc_view($message, $chatUser['id'], $chatUser['ruolo'], $chatUser['username'])]);
});
