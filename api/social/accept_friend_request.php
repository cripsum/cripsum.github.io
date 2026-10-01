<?php
require_once __DIR__ . '/bootstrap.php';

social_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $senderId = (int)($input['sender_id'] ?? 0);
    if ($senderId <= 0) {
        throw new SocialError(rt_t('Richiesta non valida.', 'Invalid request.'), 'INVALID_INPUT');
    }

    sc_request_accept($mysqli, $userId, $senderId);
    send_api_success(['is_friend' => true], rt_t('Richiesta accettata. Ora siete amici!', 'Request accepted. You are now friends!'));
});
