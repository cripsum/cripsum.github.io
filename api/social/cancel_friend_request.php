<?php
require_once __DIR__ . '/bootstrap.php';

social_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $receiverId = (int)($input['receiver_id'] ?? 0);
    if ($receiverId <= 0) {
        throw new SocialError(rt_t('Richiesta non valida.', 'Invalid request.'), 'INVALID_INPUT');
    }

    sc_request_close($mysqli, $userId, $receiverId, 'cancelled');
    send_api_success(['status' => 'cancelled'], rt_t('Richiesta annullata.', 'Request cancelled.'));
});
