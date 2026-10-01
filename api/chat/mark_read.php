<?php
/**
 * Segna come letta una chat fino a un certo messaggio: un gruppo
 * (`chat_id`) o una conversazione privata (`conversation_id`).
 *
 * Lo chiama la pagina quando la conversazione è davvero sullo schermo, non
 * al semplice arrivo dei messaggi: una scheda lasciata in secondo piano non
 * deve far risultare letto quello che nessuno ha visto.
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $upTo = (int)($input['message_id'] ?? 0);

    if (!empty($input['conversation_id'])) {
        $pair = cc_pm_require($mysqli, (int)$input['conversation_id'], $userId);
        send_success(['last_read_id' => cc_pm_mark_read($mysqli, $pair, $upTo)]);
    }

    $member = cg_require($mysqli, (int)($input['chat_id'] ?? 0), $userId);
    send_success(['last_read_id' => cg_mark_read($mysqli, $member, $upTo)]);
});
