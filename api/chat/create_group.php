<?php
/** Crea un gruppo e invita i primi partecipanti (solo amici). */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $result = cg_create($mysqli, $userId, get_json_input());
    send_success($result + ['message' => rt_t('Gruppo creato.', 'Group created.')]);
});
