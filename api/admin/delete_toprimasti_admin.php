<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/content_moderation.php';

try {
    $input = admin_input();
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) admin_fail('ID Top Rimasti non valido.');

    // Logica condivisa con i bottoni delle segnalazioni su Discord
    // (api/bot/reports/act.php).
    $result = cripsum_delete_community_post($mysqli, 'rimasto', $id);

    if (!$result['ok']) {
        admin_fail('Errore eliminazione Top Rimasti. Dettaglio: ' . ($result['error'] ?? 'sconosciuto'), 500);
    }

    admin_log($mysqli, (int)$adminUser['id'], 'delete_toprimasti', null, ['post_id' => $id]);
    admin_ok(['message' => $result['deleted'] ? 'Post eliminato.' : 'Post non trovato.']);
} catch (Throwable $e) {
    admin_fail('Errore eliminazione Top Rimasti. Dettaglio: ' . $e->getMessage(), 500);
}
