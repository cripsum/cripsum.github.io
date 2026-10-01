<?php
/** Invio di un messaggio (testo o GIF) nella chat globale. */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/chat_global.php';

chat_run(static function () use ($mysqli, $chatUser): void {
    $message = gc_send($mysqli, $chatUser, get_json_input());
    send_success(['message' => gc_view($message, $chatUser['id'], $chatUser['ruolo'], $chatUser['username'])]);
});
