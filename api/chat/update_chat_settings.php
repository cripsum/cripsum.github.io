<?php
/**
 * Endpoint storico: soprannome dato all'altra persona in una chat privata.
 *
 * La versione precedente, salvando il soprannome, azzerava anche colore e
 * sfondo della conversazione. Adesso tocca solo il soprannome; la pagina
 * nuova usa api/chat/conversation.php (action=nickname).
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $conversationId = (int)($input['conversation_id'] ?? 0);
    $pair = cc_pm_require($mysqli, $conversationId, $userId);

    if (!array_key_exists('nickname', $input) || !rt_has_col($mysqli, 'private_conversation_participants', 'nickname')) {
        send_success([]);
    }

    $nickname = cc_clean_text(str_replace("\n", ' ', (string)$input['nickname']));
    if (mb_strlen($nickname, 'UTF-8') > CC_NICK_MAX) {
        throw new ChatError(rt_t('Soprannome troppo lungo.', 'Nickname too long.'), 422);
    }
    $value = $nickname === '' ? null : $nickname;
    $otherId = (int)$pair['other']['user_id'];

    $stmt = $mysqli->prepare('UPDATE private_conversation_participants SET nickname = ? WHERE conversation_id = ? AND user_id = ?');
    $stmt->bind_param('sii', $value, $conversationId, $otherId);
    $stmt->execute();
    $stmt->close();

    rt_push_user($userId, ['t' => 'ls']);
    send_success(['nickname' => $value]);
});
