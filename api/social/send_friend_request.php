<?php
require_once __DIR__ . '/bootstrap.php';

social_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $status = sc_request_send($mysqli, $userId, (int)($input['receiver_id'] ?? 0));

    send_api_success(['status' => $status], $status === 'accepted'
        ? rt_t('Ora siete amici!', 'You are now friends!')
        : rt_t('Richiesta di amicizia inviata.', 'Friend request sent.'));
});
