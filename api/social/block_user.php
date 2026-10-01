<?php
require_once __DIR__ . '/bootstrap.php';

social_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    sc_block($mysqli, $userId, (int)($input['blocked_id'] ?? 0));
    send_api_success(['is_blocked' => true], rt_t('Utente bloccato.', 'User blocked.'));
});
