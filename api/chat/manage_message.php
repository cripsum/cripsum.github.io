<?php
/**
 * Azioni su un messaggio privato: modifica, elimina (per me o per tutti),
 * fissa, salva tra i preferiti.
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $action = (string)($input['action'] ?? '');
    $messageId = (int)($input['message_id'] ?? 0);
    if ($messageId <= 0) {
        throw new ChatError(rt_t('Messaggio non valido.', 'Invalid message.'), 422);
    }

    switch ($action) {
        case 'edit':
            $result = cc_pm_edit($mysqli, $userId, $messageId, (string)($input['content'] ?? ''));
            send_success(['message_id' => $messageId, 'content' => $result['message']['body'] ?? '', 'message' => $result['message']]);
            // no break: send_success termina la richiesta
        case 'delete_for_self':
            send_success(cc_pm_delete($mysqli, $userId, $messageId, false));
        case 'delete_for_all':
            send_success(cc_pm_delete($mysqli, $userId, $messageId, true));
        case 'toggle_pin':
            send_success(cc_pm_toggle_pin($mysqli, $userId, $messageId));
        case 'toggle_favorite':
            send_success(cc_pm_toggle_favorite($mysqli, $userId, $messageId));
        default:
            throw new ChatError(rt_t('Azione non supportata.', 'Unsupported action.'), 422);
    }
});
