<?php
/**
 * Azioni su una conversazione privata, valide solo per chi le chiede
 * (l'altra persona non vede né sa nulla di queste scelte).
 *
 * POST conversation_id, action =
 *   mute      { seconds }   -1 finché non la riattivi, 0 riattiva, N secondi
 *   archive   { archived }
 *   pin       { pinned }    la tiene in cima alla lista
 *   unread                  la segna come non letta
 *   clear                   svuota la cronologia dal proprio lato
 *   accept                  accetta una richiesta di messaggio
 *   nickname  { nickname }  soprannome che do io all'altra persona
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $conversationId = (int)($input['conversation_id'] ?? 0);
    $action = (string)($input['action'] ?? '');
    $pair = cc_pm_require($mysqli, $conversationId, $userId);
    $otherId = (int)$pair['other']['user_id'];
    $result = [];

    $setMine = static function (string $sql, string $types, array $params) use ($mysqli, $conversationId, $userId): void {
        $stmt = $mysqli->prepare("UPDATE private_conversation_participants SET $sql WHERE conversation_id = ? AND user_id = ?");
        $all = array_merge($params, [$conversationId, $userId]);
        $stmt->bind_param($types . 'ii', ...$all);
        $stmt->execute();
        $stmt->close();
    };

    switch ($action) {
        case 'mute':
            $seconds = (int)($input['seconds'] ?? -1);
            $timed = rt_has_col($mysqli, 'private_conversation_participants', 'muted_until');
            if ($seconds > 0 && $timed) {
                $until = date('Y-m-d H:i:s', time() + min($seconds, 365 * 86400));
                $setMine('is_muted = 0, muted_until = ?', 's', [$until]);
                $result = ['muted' => true, 'muted_until_ts' => strtotime($until)];
            } else {
                $flag = $seconds === 0 ? 0 : 1;
                $setMine($timed ? 'is_muted = ?, muted_until = NULL' : 'is_muted = ?', 'i', [$flag]);
                $result = ['muted' => $flag === 1, 'muted_until_ts' => null];
            }
            break;

        case 'archive':
            $flag = !empty($input['archived']) ? 1 : 0;
            $setMine('is_archived = ?', 'i', [$flag]);
            $result = ['archived' => $flag === 1];
            break;

        case 'pin':
            if (!rt_has_table($mysqli, 'private_conversation_pins')) {
                throw new ChatError(rt_t('Funzione non disponibile.', 'Feature not available.'), 503);
            }
            if (!empty($input['pinned'])) {
                $stmt = $mysqli->prepare('SELECT COUNT(*) FROM private_conversation_pins WHERE user_id = ?');
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $count = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
                $stmt->close();
                if ($count >= 10) {
                    throw new ChatError(rt_t('Puoi fissare al massimo 10 chat.', 'You can pin up to 10 chats.'), 422);
                }
                $stmt = $mysqli->prepare('INSERT IGNORE INTO private_conversation_pins (user_id, conversation_id) VALUES (?, ?)');
            } else {
                $stmt = $mysqli->prepare('DELETE FROM private_conversation_pins WHERE user_id = ? AND conversation_id = ?');
            }
            $stmt->bind_param('ii', $userId, $conversationId);
            $stmt->execute();
            $stmt->close();
            $result = ['pinned' => !empty($input['pinned'])];
            break;

        case 'unread':
            // Il segno di lettura torna a prima dell'ultimo messaggio ricevuto.
            $stmt = $mysqli->prepare('SELECT MAX(id) FROM private_messages WHERE conversation_id = ? AND sender_id = ? AND deleted_at IS NULL');
            $stmt->bind_param('ii', $conversationId, $otherId);
            $stmt->execute();
            $lastReceived = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
            if ($lastReceived > 0) {
                $setMine('last_read_message_id = ?', 'i', [$lastReceived - 1]);
            }
            $result = ['unread' => $lastReceived > 0];
            break;

        case 'clear':
            if (!rt_has_col($mysqli, 'private_conversation_participants', 'cleared_before_id')) {
                throw new ChatError(rt_t('Funzione non ancora attiva su questo server.', 'Feature not available on this server yet.'), 503);
            }
            $stmt = $mysqli->prepare('SELECT COALESCE(MAX(id), 0) FROM private_messages WHERE conversation_id = ?');
            $stmt->bind_param('i', $conversationId);
            $stmt->execute();
            $max = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
            $setMine('cleared_before_id = ?, last_read_message_id = ?, is_archived = 0', 'ii', [$max, $max]);
            if (rt_has_table($mysqli, 'private_conversation_pins')) {
                $stmt = $mysqli->prepare('DELETE FROM private_conversation_pins WHERE user_id = ? AND conversation_id = ?');
                $stmt->bind_param('ii', $userId, $conversationId);
                $stmt->execute();
                $stmt->close();
            }
            $result = ['cleared' => true];
            break;

        case 'accept':
            if ($pair['me']['is_request']) {
                $setMine('is_request = 0', '', []);
                rt_push_user($otherId, ['t' => 'ls']);
            }
            $result = ['accepted' => true];
            break;

        case 'nickname':
            if (!rt_has_col($mysqli, 'private_conversation_participants', 'nickname')) {
                throw new ChatError(rt_t('Funzione non disponibile.', 'Feature not available.'), 503);
            }
            $nickname = cc_clean_text(str_replace("\n", ' ', (string)($input['nickname'] ?? '')));
            if (mb_strlen($nickname, 'UTF-8') > CC_NICK_MAX) {
                throw new ChatError(rt_t('Soprannome troppo lungo.', 'Nickname too long.'), 422);
            }
            $value = $nickname === '' ? null : $nickname;
            // Il soprannome che do all'altro sta sulla sua riga della conversazione.
            $stmt = $mysqli->prepare('UPDATE private_conversation_participants SET nickname = ? WHERE conversation_id = ? AND user_id = ?');
            $stmt->bind_param('sii', $value, $conversationId, $otherId);
            $stmt->execute();
            $stmt->close();
            $result = ['nickname' => $value];
            break;

        default:
            throw new ChatError(rt_t('Azione non supportata.', 'Unsupported action.'), 422);
    }

    rt_push_user($userId, ['t' => 'ls']);
    send_success($result);
});
