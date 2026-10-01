<?php
/** Reazione a un messaggio della chat globale (la mette se manca, la toglie se c'è). */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/chat_global.php';

chat_run(static function () use ($mysqli, $chatUser): void {
    $input = get_json_input();
    $message = gc_react($mysqli, $chatUser, (int)($input['id'] ?? 0), trim((string)($input['emoji'] ?? '')));
    send_success(['message' => gc_view($message, $chatUser['id'], $chatUser['ruolo'], $chatUser['username'])]);
});
