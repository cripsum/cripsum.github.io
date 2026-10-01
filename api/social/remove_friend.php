<?php
require_once __DIR__ . '/bootstrap.php';

social_run(static function () use ($mysqli, $userId): void {
    $input = get_json_input();
    $friendId = (int)($input['friend_id'] ?? 0);
    if ($friendId <= 0 || $friendId === $userId) {
        throw new SocialError(rt_t('Utente non valido.', 'Invalid user.'), 'INVALID_INPUT');
    }

    sc_remove_friend($mysqli, $userId, $friendId);
    send_api_success(['is_friend' => false], rt_t('Amico rimosso.', 'Friend removed.'));
});
