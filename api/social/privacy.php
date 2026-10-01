<?php
// Impostazioni di privacy: chi può scrivermi, chi può mandarmi richieste,
// conferme di lettura e «sta scrivendo». GET le legge, POST le salva.
require_once __DIR__ . '/bootstrap.php';

social_run(static function () use ($mysqli, $userId): void {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $settings = sc_settings($mysqli, $userId);
        $settings['available'] = rt_has_table($mysqli, 'private_user_settings');
        $settings['requests_setting_available'] = rt_has_col($mysqli, 'private_user_settings', 'friend_requests_from');
        send_api_success(['settings' => $settings]);
    }

    $saved = sc_save_settings($mysqli, $userId, get_json_input());
    $saved['available'] = true;
    send_api_success(['settings' => $saved], rt_t('Impostazioni salvate.', 'Settings saved.'));
});
