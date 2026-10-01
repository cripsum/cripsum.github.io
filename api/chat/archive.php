<?php
/** Archivia o ripristina un gruppo, solo per chi lo chiede. */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $archived = ($input['action'] ?? 'archive') !== 'unarchive';
    cg_archive($mysqli, $userId, (int)($input['chat_id'] ?? 0), $archived);
    send_success(['archived' => $archived]);
});
