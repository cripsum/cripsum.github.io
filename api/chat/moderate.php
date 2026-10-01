<?php
/**
 * Strumenti dello staff per la chat globale. Solo admin e owner.
 *
 * POST action =
 *   pin        { id }                 fissa un messaggio in alto come avviso
 *   unpin                             toglie l'avviso
 *   slow       { seconds }            attesa minima fra due messaggi (0-120)
 *   timeout    { user_id, minutes, reason }   sospende dalla chat (0 = toglie)
 *   word_add   { word }               aggiunge una parola al filtro
 *   word_remove{ word_id }
 * GET          → impostazioni attuali e parole del filtro
 *
 * Ogni azione finisce nel registro dello staff (admin_logs).
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/chat_global.php';

chat_run(static function () use ($mysqli, $chatUser): void {
    gc_require_mod($chatUser);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        send_success([
            'settings' => gc_settings(),
            'words' => gc_words_list($mysqli),
            'timeout_available' => rt_has_col($mysqli, 'utenti', 'chat_timeout_until'),
        ]);
    }

    $input = get_json_input();
    $action = (string)($input['action'] ?? '');
    $adminId = (int)$chatUser['id'];

    switch ($action) {
        case 'pin':
            $message = gc_one($mysqli, (int)($input['id'] ?? 0));
            if (!$message || $message['is_deleted']) {
                throw new ChatError(rt_t('Messaggio non trovato.', 'Message not found.'), 404);
            }
            gc_set_pinned($mysqli, $message);
            gc_log($mysqli, $adminId, 'chat_pin', (int)$message['user_id'], ['message_id' => (int)$message['id']]);
            send_success(['settings' => gc_settings()]);
            // no break
        case 'unpin':
            gc_set_pinned($mysqli, null);
            gc_log($mysqli, $adminId, 'chat_unpin', null, []);
            send_success(['settings' => gc_settings()]);
            // no break
        case 'slow':
            $seconds = gc_set_slow((int)($input['seconds'] ?? 0));
            gc_log($mysqli, $adminId, 'chat_slow_mode', null, ['seconds' => $seconds]);
            send_success(['settings' => gc_settings()]);
            // no break
        case 'timeout':
            $until = gc_timeout($mysqli, $chatUser, (int)($input['user_id'] ?? 0), (int)($input['minutes'] ?? 0), (string)($input['reason'] ?? ''));
            send_success(['until_ts' => $until]);
            // no break
        case 'word_add':
            gc_words_change($mysqli, $chatUser, 'add', (string)($input['word'] ?? ''));
            send_success(['words' => gc_words_list($mysqli)]);
            // no break
        case 'word_remove':
            gc_words_change($mysqli, $chatUser, 'remove', '', (int)($input['word_id'] ?? 0));
            send_success(['words' => gc_words_list($mysqli)]);
            // no break
        default:
            throw new ChatError(rt_t('Azione non supportata.', 'Unsupported action.'), 422);
    }
});
