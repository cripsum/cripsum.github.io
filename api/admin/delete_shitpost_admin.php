<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $input = admin_input();
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) admin_fail('ID shitpost non valido.');

    $mysqli->begin_transaction();

    if (admin_table_exists($mysqli, 'commenti_shitpost')) {
        $stmt = $mysqli->prepare("DELETE FROM commenti_shitpost WHERE id_shitpost = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    $stmt = $mysqli->prepare("DELETE FROM shitposts WHERE id = ? LIMIT 1");
    if (!$stmt) {
        $mysqli->rollback();
        admin_fail(admin_prepare_error($mysqli, 'Query eliminazione shitpost non valida.'), 500);
    }
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        $mysqli->rollback();
        admin_fail('Non sono riuscito a eliminare lo shitpost.', 500);
    }
    $deleted = $stmt->affected_rows;
    $stmt->close();

    $mysqli->commit();

    admin_log($mysqli, (int)$adminUser['id'], 'delete_shitpost', null, ['post_id' => $id]);
    admin_ok(['message' => $deleted > 0 ? 'Shitpost eliminato.' : 'Shitpost non trovato.']);
} catch (Throwable $e) {
    if ($mysqli->errno) @$mysqli->rollback();
    admin_fail('Errore eliminazione shitpost. Dettaglio: ' . $e->getMessage(), 500);
}
