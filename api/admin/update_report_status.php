<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/content_moderation.php';

try {
    $input = admin_input();
    $source = (string)($input['source'] ?? '');
    $id = (int)($input['id'] ?? 0);
    $status = (string)($input['status'] ?? 'open');

    // Logica condivisa con i bottoni delle segnalazioni su Discord
    // (api/bot/reports/act.php).
    $result = cripsum_set_report_status($mysqli, $source, $id, $status, (int)$adminUser['id']);

    if (!$result['ok']) {
        admin_fail($result['error'] ?? 'Non sono riuscito ad aggiornare la segnalazione.', 400);
    }

    admin_log($mysqli, (int)$adminUser['id'], 'update_report_status', null, [
        'source' => $source,
        'report_id' => $id,
        'status' => $status,
    ]);

    admin_ok(['message' => 'Segnalazione aggiornata.']);
} catch (Throwable $e) {
    admin_fail('Errore aggiornamento segnalazione. Dettaglio: ' . $e->getMessage(), 500);
}
