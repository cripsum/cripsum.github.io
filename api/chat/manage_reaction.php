<?php
/**
 * Reazione a un messaggio privato. Senza `action` (o con «toggle») la mette
 * se manca e la toglie se c'è; «add» e «remove» restano per il client vecchio.
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $messageId = (int)($input['message_id'] ?? 0);
    $reaction = trim((string)($input['reaction'] ?? ''));
    $action = (string)($input['action'] ?? 'toggle');

    if ($messageId <= 0) {
        throw new ChatError(rt_t('Messaggio non valido.', 'Invalid message.'), 422);
    }

    if (in_array($action, ['add', 'remove'], true) && rt_has_table($mysqli, 'private_message_reactions')) {
        // Il client vecchio dichiara cosa vuole ottenere: se è già così, non si tocca nulla.
        cc_pm_message_context($mysqli, $messageId, $userId);
        $stmt = $mysqli->prepare('SELECT 1 FROM private_message_reactions WHERE message_id = ? AND user_id = ? AND reaction = ? LIMIT 1');
        $stmt->bind_param('iis', $messageId, $userId, $reaction);
        $stmt->execute();
        $has = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if (($action === 'add') === $has) {
            send_success([
                'message_id' => $messageId,
                'reactions' => cc_reactions($mysqli, 'private_message_reactions', $userId, [$messageId])[$messageId] ?? [],
            ]);
        }
    }

    send_success(cc_pm_react($mysqli, $userId, $messageId, $reaction));
});
