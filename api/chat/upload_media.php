<?php
/**
 * Invio di allegati in un gruppo o in una conversazione privata.
 *
 * Fino a sei file per messaggio (`files[]`, o `file` per il vecchio client),
 * con un testo facoltativo (`message`). Valgono gli stessi controlli di un
 * messaggio normale: prima un utente bloccato poteva continuare a mandare
 * file, perché questo endpoint guardava solo l'appartenenza alla chat.
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new ChatError(rt_t('Metodo non consentito.', 'Method not allowed.'), 405);
    }

    $chatId = (int)($_POST['chat_id'] ?? 0);
    $conversationId = (int)($_POST['conversation_id'] ?? 0);
    $recipientId = (int)($_POST['recipient_id'] ?? 0);

    $files = cc_uploaded_files($_FILES);
    if (!$files) {
        throw new ChatError(rt_t('Nessun file ricevuto. Forse è troppo grande.', 'No file received. It may be too large.'), 422);
    }
    if (count($files) > CC_MAX_FILES) {
        throw new ChatError(rt_t('Troppi file in un solo messaggio (massimo ' . CC_MAX_FILES . ').', 'Too many files in one message (max ' . CC_MAX_FILES . ').'), 422);
    }

    // I permessi si controllano prima di salvare qualsiasi cosa su disco.
    $table = $chatId > 0 ? 'chat_messages' : 'private_messages';
    if ($chatId > 0) {
        $member = cg_require($mysqli, $chatId, $userId);
        if (cg_settings($mysqli, $chatId)['message_permission'] === 'admins_only' && !$member['is_staff']) {
            throw new ChatError(rt_t('In questo gruppo possono scrivere solo gli amministratori.', 'Only admins can write in this group.'), 403);
        }
    } else {
        if ($conversationId > 0) {
            $pair = cc_pm_require($mysqli, $conversationId, $userId);
            $recipientId = (int)$pair['other']['user_id'];
        }
        $allowed = sc_can_message($mysqli, $userId, $recipientId);
        if (!$allowed['ok']) {
            throw new ChatError($allowed['message'], 403);
        }
    }

    // Al massimo dieci messaggi con allegati al minuto.
    $stmt = $mysqli->prepare("SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) FROM `$table` WHERE sender_id = ? AND message_type = 'media' ORDER BY id DESC LIMIT 1 OFFSET 9");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $tenth = $stmt->get_result()->fetch_row();
    $stmt->close();
    if ($tenth && $tenth[0] !== null && (int)$tenth[0] < 60) {
        throw new ChatError(rt_t('Stai caricando troppi file. Aspetta un attimo.', 'You are uploading too many files. Wait a moment.'), 429);
    }

    $stored = [];
    try {
        foreach ($files as $file) {
            $stored[] = cc_store_upload($file);
        }

        $input = [
            'message' => (string)($_POST['message'] ?? ''),
            'reply_to_id' => (int)($_POST['reply_to_id'] ?? 0),
            'reply_to_message_id' => (int)($_POST['reply_to_id'] ?? 0),
            'conversation_id' => $conversationId,
            'recipient_id' => $recipientId,
        ];

        $result = $chatId > 0
            ? cg_send($mysqli, $userId, $chatId, $input, $stored)
            : cc_pm_send($mysqli, $userId, $input, $stored);
    } catch (Throwable $e) {
        // Messaggio non salvato: i file già scritti non devono restare orfani.
        foreach ($stored as $file) {
            cc_delete_upload($file['file_path']);
        }
        throw $e;
    }

    send_success([
        'message' => $result['message'],
        'conversation_id' => $result['conversation_id'] ?? null,
        'chat_id' => $chatId ?: null,
    ]);
});
