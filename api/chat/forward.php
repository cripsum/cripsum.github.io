<?php
/**
 * Inoltra un messaggio (testo o GIF) verso altre chat, al massimo cinque
 * per volta.
 *
 * POST kind (private|group), message_id, targets: [{ kind, id }]
 *
 * Si inoltra solo ciò che si può leggere, e solo dove si può scrivere: ogni
 * destinazione passa dagli stessi controlli di un messaggio normale. Gli
 * allegati non si inoltrano (il file resterebbe legato a due messaggi, e
 * cancellarne uno lo toglierebbe anche all'altro).
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $kind = (string)($input['kind'] ?? 'private');
    $messageId = (int)($input['message_id'] ?? 0);
    $targets = array_slice((array)($input['targets'] ?? []), 0, 5);
    if ($messageId <= 0 || !$targets) {
        throw new ChatError(rt_t('Scegli almeno una chat.', 'Pick at least one chat.'), 422);
    }

    if ($kind === 'group') {
        $context = cg_message_context($mysqli, $messageId, $userId);
        $source = cg_one($mysqli, $userId, (int)$context['message']['chat_id'], $messageId);
    } else {
        $context = cc_pm_message_context($mysqli, $messageId, $userId);
        $source = cc_pm_one($mysqli, $context['pair'], $messageId);
    }

    if (!$source || $source['is_deleted'] || !in_array($source['message_type'], ['text', 'gif'], true)) {
        throw new ChatError(rt_t('Questo messaggio non si può inoltrare.', 'This message cannot be forwarded.'), 422);
    }

    $payload = [
        'message' => (string)($source['body'] ?? ''),
        'message_type' => $source['message_type'],
        'media_url' => $source['media_url'],
        'media_title' => $source['media_title'],
        'forwarded' => true,
    ];

    $sent = 0;
    $failed = [];
    foreach ($targets as $target) {
        $targetKind = (string)($target['kind'] ?? '');
        $targetId = (int)($target['id'] ?? 0);
        try {
            if ($targetKind === 'group') {
                cg_send($mysqli, $userId, $targetId, $payload);
            } elseif ($targetKind === 'private') {
                cc_pm_send($mysqli, $userId, $payload + ['conversation_id' => $targetId]);
            } elseif ($targetKind === 'user') {
                cc_pm_send($mysqli, $userId, $payload + ['recipient_id' => $targetId]);
            } else {
                continue;
            }
            $sent++;
        } catch (ChatError $e) {
            $failed[] = ['kind' => $targetKind, 'id' => $targetId, 'error' => $e->getMessage()];
        }
    }

    if ($sent === 0) {
        throw new ChatError($failed[0]['error'] ?? rt_t('Inoltro non riuscito.', 'Forward failed.'), 422);
    }
    send_success(['sent' => $sent, 'failed' => $failed]);
});
