<?php
/** Dettagli di un gruppo: dati, permessi, la propria appartenenza e i membri. */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    send_success(cg_details($mysqli, $userId, (int)($_GET['chat_id'] ?? 0)));
});
