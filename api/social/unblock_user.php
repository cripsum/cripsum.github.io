<?php
require_once __DIR__ . '/bootstrap.php';

social_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $blockedId = (int)($input['blocked_id'] ?? 0);
    if ($blockedId <= 0) {
        throw new SocialError(rt_t('Utente non valido.', 'Invalid user.'), 'INVALID_INPUT');
    }

    sc_unblock($mysqli, $userId, $blockedId);
    send_api_success(['is_blocked' => false], rt_t('Utente sbloccato.', 'User unblocked.'));
});
