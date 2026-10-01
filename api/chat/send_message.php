<?php
/**
 * Invio di un messaggio (testo o GIF) in un gruppo (`chat_id`) oppure in
 * una conversazione privata (`conversation_id`, o `recipient_id` per il
 * primo messaggio a qualcuno).
 *
 * Gli allegati passano da upload_media.php. Qualunque altro campo mandato
 * dal browser (metadati, percorsi di file) viene ignorato: era da lì che un
 * membro di un gruppo poteva far comparire agli altri contenuto arbitrario.
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $chatId = (int)($input['chat_id'] ?? 0);

    if ($chatId > 0) {
        $result = cg_send($mysqli, $userId, $chatId, $input);
        send_success(['message' => $result['message'], 'chat_id' => $chatId]);
    }

    $result = cc_pm_send($mysqli, $userId, $input);
    send_success([
        'message' => $result['message'],
        'conversation_id' => $result['conversation_id'],
        'recipient_id' => $result['recipient_id'],
    ]);
});
