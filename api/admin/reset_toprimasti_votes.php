<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $input = admin_input();
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) admin_fail('ID Top Rimasti non valido.');

    $mysqli->begin_transaction();

    if (admin_table_exists($mysqli, 'voti_toprimasti')) {
        $stmt = $mysqli->prepare("DELETE FROM voti_toprimasti WHERE id_post = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    $stmt = $mysqli->prepare("UPDATE toprimasti SET reazioni = 0 WHERE id = ? LIMIT 1");
    if (!$stmt) {
        $mysqli->rollback();
        admin_fail(admin_prepare_error($mysqli, 'Query reset voti non valida.'), 500);
    }
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        $mysqli->rollback();
        admin_fail('Non sono riuscito a resettare i voti.', 500);
    }
    $stmt->close();

    $mysqli->commit();

    admin_log($mysqli, (int)$adminUser['id'], 'reset_toprimasti_votes', null, ['post_id' => $id]);
    admin_ok(['message' => 'Voti resettati.']);
} catch (Throwable $e) {
    if ($mysqli->errno) @$mysqli->rollback();
    admin_fail('Errore reset voti. Dettaglio: ' . $e->getMessage(), 500);
}
