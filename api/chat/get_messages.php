<?php
/**
 * Messaggi di una conversazione privata.
 *
 * GET conversation_id, più uno fra:
 *   before | before_message_id   pagina precedente
 *   after                        solo i più recenti di un id
 *   ids=1,2,3                    messaggi precisi (dopo una modifica o una reazione)
 *   around                       una finestra intorno a un messaggio (salto da ricerca o risposta)
 *
 * La pagina nuova segna «letto» da sola quando la conversazione è davvero
 * sullo schermo (api/chat/mark_read.php) e chiede `noread=1`. Senza quel
 * parametro si comporta come prima: caricare l'ultima pagina la segna letta.
 */
require_once __DIR__ . '/bootstrap.php';

chat_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $source = $_SERVER['REQUEST_METHOD'] === 'GET' ? $_GET : array_merge($_GET, $input);

    $conversationId = (int)($source['conversation_id'] ?? 0);
    $pair = cc_pm_require($mysqli, $conversationId, $userId);

    $options = ['limit' => (int)($source['limit'] ?? CC_PAGE)];
    $before = (int)($source['before'] ?? $source['before_message_id'] ?? 0);
    if (!empty($source['ids'])) {
        $options['ids'] = is_array($source['ids']) ? $source['ids'] : explode(',', (string)$source['ids']);
    } elseif (!empty($source['around'])) {
        $options['around'] = (int)$source['around'];
    } elseif (!empty($source['after'])) {
        $options['after'] = (int)$source['after'];
    } elseif ($before > 0) {
        $options['before'] = $before;
    }

    $result = cc_pm_fetch($mysqli, $pair, $options);

    $isLatestPage = empty($options['ids']) && empty($options['around']) && empty($options['before']);
    if ($isLatestPage && empty($source['noread']) && $result['messages']) {
        cc_pm_mark_read($mysqli, $pair);
    }

    $receipts = !$pair['other']['is_request'] && cc_pm_receipts_allowed($mysqli, $userId, (int)$pair['other']['user_id']);

    send_success($result + [
        'last_read_id' => $pair['me']['last_read_message_id'],
        'other_last_read_id' => $receipts ? $pair['other']['last_read_message_id'] : null,
        'is_request' => $pair['me']['is_request'],
        'awaiting_accept' => $pair['other']['is_request'],
    ]);
});
