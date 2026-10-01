<?php
/**
 * Endpoint storico: lo interroga ogni tre secondi la versione precedente
 * della pagina delle chat, finché chi la tiene aperta non ricarica.
 *
 * Resta in piedi per quelle schede, ma alleggerito e senza i difetti di
 * prima: non scrive più `ultimo_accesso` a ogni giro (lo fa già il battito
 * del sito) e i messaggi passano dagli stessi controlli di tutti gli altri.
 * La pagina nuova usa api/rt/poll.php.
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $conversationId = (int)($input['conversation_id'] ?? 0);
    $lastId = (int)($input['last_message_id'] ?? 0);

    $response = ['other_online' => false, 'other_last_seen' => null, 'other_typing' => null, 'new_messages' => []];

    if ($conversationId > 0) {
        $pair = cc_pm_require($mysqli, $conversationId, $userId);
        $otherId = (int)$pair['other']['user_id'];

        $stmt = $mysqli->prepare('SELECT ultimo_accesso, TIMESTAMPDIFF(SECOND, ultimo_accesso, NOW()) AS idle FROM utenti WHERE id = ?');
        $stmt->bind_param('i', $otherId);
        $stmt->execute();
        $other = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($other && !sc_is_blocked($mysqli, $userId, $otherId)) {
            if ($other['idle'] !== null && (int)$other['idle'] < SC_ONLINE_WINDOW) {
                $response['other_online'] = true;
            } else {
                $response['other_last_seen'] = $other['ultimo_accesso'];
            }
        }

        if ($lastId > 0) {
            $fresh = cc_pm_fetch($mysqli, $pair, ['after' => $lastId, 'limit' => 60])['messages'];
            $response['new_messages'] = array_values(array_filter($fresh, static fn($m) => (int)$m['sender_id'] !== $userId));
            if ($response['new_messages']) {
                cc_pm_mark_read($mysqli, $pair);
            }
        }
    }

    $response['unread_chats_count'] = cc_unread_summary($mysqli, $userId)['chats'];
    send_success($response);
});
