<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/content_moderation.php';

try {
    $input = admin_input();
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) admin_fail('ID shitpost non valido.');

    // Logica condivisa con i bottoni delle segnalazioni su Discord
    // (api/bot/reports/act.php).
    $result = cripsum_delete_community_post($mysqli, 'shitpost', $id);

    if (!$result['ok']) {
        admin_fail('Errore eliminazione shitpost. Dettaglio: ' . ($result['error'] ?? 'sconosciuto'), 500);
    }

    admin_log($mysqli, (int)$adminUser['id'], 'delete_shitpost', null, ['post_id' => $id]);
    admin_ok(['message' => $result['deleted'] ? 'Shitpost eliminato.' : 'Shitpost non trovato.']);
} catch (Throwable $e) {
    admin_fail('Errore eliminazione shitpost. Dettaglio: ' . $e->getMessage(), 500);
}
