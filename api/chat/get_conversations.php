<?php
/**
 * Endpoint storico, senza chiamanti noti nel sito: resta per compatibilità
 * e restituisce le conversazioni private con le stesse regole di list.php.
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    send_success(['conversations' => cc_pm_list($mysqli, $userId)]);
});
