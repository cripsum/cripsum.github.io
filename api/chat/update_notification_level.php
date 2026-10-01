<?php
/** Livello di notifica di un gruppo: «all» oppure «muted». */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $level = (string)($input['notification_level'] ?? 'all');
    cg_mute($mysqli, $userId, (int)($input['chat_id'] ?? 0), $level === 'muted' ? -1 : 0);
    send_success(['notification_level' => $level === 'muted' ? 'muted' : 'all']);
});
