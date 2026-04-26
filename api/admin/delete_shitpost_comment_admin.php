<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $input = admin_input();
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) admin_fail('ID commento non valido.');

    $stmt = $mysqli->prepare("DELETE FROM commenti_shitpost WHERE id = ? LIMIT 1");
    if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Query eliminazione commento non valida.'), 500);
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) admin_fail('Non sono riuscito a eliminare il commento.', 500);
    $stmt->close();

    admin_log($mysqli, (int)$adminUser['id'], 'delete_shitpost_comment', null, ['comment_id' => $id]);
    admin_ok(['message' => 'Commento eliminato.']);
} catch (Throwable $e) {
    admin_fail('Errore eliminazione commento. Dettaglio: ' . $e->getMessage(), 500);
}
