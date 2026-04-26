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

    // Compatibilità con vecchia API che provava a usare votes_toprimasti.post_id.
    if (admin_table_exists($mysqli, 'votes_toprimasti') && admin_column_exists($mysqli, 'votes_toprimasti', 'post_id')) {
        $stmt = $mysqli->prepare("DELETE FROM votes_toprimasti WHERE post_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    $stmt = $mysqli->prepare("DELETE FROM toprimasti WHERE id = ? LIMIT 1");
    if (!$stmt) {
        $mysqli->rollback();
        admin_fail(admin_prepare_error($mysqli, 'Query eliminazione Top Rimasti non valida.'), 500);
    }
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        $mysqli->rollback();
        admin_fail('Non sono riuscito a eliminare il post.', 500);
    }
    $deleted = $stmt->affected_rows;
    $stmt->close();

    $mysqli->commit();

    admin_log($mysqli, (int)$adminUser['id'], 'delete_toprimasti', null, ['post_id' => $id]);
    admin_ok(['message' => $deleted > 0 ? 'Post eliminato.' : 'Post non trovato.']);
} catch (Throwable $e) {
    if ($mysqli->errno) @$mysqli->rollback();
    admin_fail('Errore eliminazione Top Rimasti. Dettaglio: ' . $e->getMessage(), 500);
}
