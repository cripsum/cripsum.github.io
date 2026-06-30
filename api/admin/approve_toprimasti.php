<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $input = admin_input();
    $id = (int)($input['id'] ?? 0);
    $approved = (int)($input['approved'] ?? 0) === 1 ? 1 : 0;

    if ($id <= 0) admin_fail('ID Top Rimasti non valido.');

    $stmt = $mysqli->prepare("UPDATE toprimasti SET approvato = ? WHERE id = ? LIMIT 1");
    if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Query approvazione Top Rimasti non valida.'), 500);
    $stmt->bind_param('ii', $approved, $id);
    if (!$stmt->execute()) admin_fail('Non sono riuscito ad aggiornare lo stato.', 500);
    $stmt->close();

    if ($approved) {
        try {
            require_once __DIR__ . '/../../includes/discord_notify.php';
            notifyDiscordNewPost($mysqli, $id, 'rimasto');
        } catch (Throwable $e) {
            error_log('[Discord Webhook Error approve_toprimasti] ' . $e->getMessage());
        }
    }

    admin_log($mysqli, (int)$adminUser['id'], $approved ? 'approve_toprimasti' : 'unapprove_toprimasti', null, ['post_id' => $id]);
    admin_ok(['message' => $approved ? 'Post approvato.' : 'Post rimesso in attesa.']);
} catch (Throwable $e) {
    admin_fail('Errore approvazione Top Rimasti. Dettaglio: ' . $e->getMessage(), 500);
}
