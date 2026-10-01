<?php
/**
 * Endpoint storico per blocco, silenzia, archivia e impostazioni di privacy.
 * Le stesse azioni ora vivono in api/social/ (blocco, privacy) e in
 * api/chat/conversation.php: qui restano i nomi vecchi, che richiamano le
 * stesse funzioni, per le schede aperte con la versione precedente.
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $action = (string)($input['action'] ?? '');

    $setMine = static function (int $conversationId, string $column, int $value) use ($mysqli, $userId): void {
        cc_pm_require($mysqli, $conversationId, $userId);
        $stmt = $mysqli->prepare("UPDATE private_conversation_participants SET $column = ? WHERE conversation_id = ? AND user_id = ?");
        $stmt->bind_param('iii', $value, $conversationId, $userId);
        $stmt->execute();
        $stmt->close();
        rt_push_user($userId, ['t' => 'ls']);
    };

    switch ($action) {
        case 'block':
            sc_block($mysqli, $userId, (int)($input['blocked_user_id'] ?? 0));
            send_success(['blocked' => true]);
            // no break
        case 'unblock':
            sc_unblock($mysqli, $userId, (int)($input['blocked_user_id'] ?? 0));
            send_success(['blocked' => false]);
            // no break
        case 'mute':
        case 'unmute':
            $setMine((int)($input['conversation_id'] ?? 0), 'is_muted', $action === 'mute' ? 1 : 0);
            send_success(['muted' => $action === 'mute']);
            // no break
        case 'archive':
        case 'unarchive':
            $setMine((int)($input['conversation_id'] ?? 0), 'is_archived', $action === 'archive' ? 1 : 0);
            send_success(['archived' => $action === 'archive']);
            // no break
        case 'get_user_settings':
            $settings = sc_settings($mysqli, $userId);
            send_success(['settings' => $settings + [
                'privacy_receive_from' => $settings['dm_from'],
                'disable_read_receipts' => !$settings['read_receipts'],
                'disable_typing_status' => !$settings['typing'],
            ]]);
            // no break
        case 'update_user_settings':
            $map = [];
            if (isset($input['privacy_receive_from'])) {
                $map['dm_from'] = in_array($input['privacy_receive_from'], ['friends', 'none'], true) ? 'friends' : 'all';
            }
            if (isset($input['disable_read_receipts'])) {
                $map['read_receipts'] = empty($input['disable_read_receipts']);
            }
            if (isset($input['disable_typing_status'])) {
                $map['typing'] = empty($input['disable_typing_status']);
            }
            send_success(['settings' => sc_save_settings($mysqli, $userId, $map + $input)]);
            // no break
        default:
            throw new ChatError(rt_t('Azione non supportata.', 'Unsupported action.'), 422);
    }
});
