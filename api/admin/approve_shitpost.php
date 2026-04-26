<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $input = admin_input();
    $id = (int)($input['id'] ?? 0);
    $approved = (int)($input['approved'] ?? 0) === 1 ? 1 : 0;

    if ($id <= 0) admin_fail('ID shitpost non valido.');

    $stmt = $mysqli->prepare("UPDATE shitposts SET approvato = ? WHERE id = ? LIMIT 1");
    if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Query approvazione shitpost non valida.'), 500);
    $stmt->bind_param('ii', $approved, $id);
    if (!$stmt->execute()) admin_fail('Non sono riuscito ad aggiornare lo stato.', 500);
    $stmt->close();

    admin_log($mysqli, (int)$adminUser['id'], $approved ? 'approve_shitpost' : 'unapprove_shitpost', null, ['post_id' => $id]);
    admin_ok(['message' => $approved ? 'Shitpost approvato.' : 'Shitpost rimesso in attesa.']);
} catch (Throwable $e) {
    admin_fail('Errore approvazione shitpost. Dettaglio: ' . $e->getMessage(), 500);
}
