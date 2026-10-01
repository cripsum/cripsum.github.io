<?php
/** Membri di un gruppo (attivi e invitati). Solo per chi ne fa parte. */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $chatId = (int)($_GET['chat_id'] ?? 0);
    cg_require($mysqli, $chatId, $userId);
    send_success(['members' => cg_members($mysqli, $chatId)]);
});
