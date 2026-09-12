<?php
require_once __DIR__ . '/bootstrap.php';

try {
    if (!admin_table_exists($mysqli, 'home_slides')) {
        admin_fail('Tabella home_slides mancante: applica la migrazione.', 500);
    }

    $input = admin_input();
    $id = (int)($input['id'] ?? 0);

    if ($id <= 0) {
        admin_fail('Slide non valida.');
    }

    // Il titolo si legge prima di cancellare: serve nel registro, dove "slide
    // #14 eliminata" non direbbe niente a chi lo rilegge fra sei mesi.
    $stmtRead = $mysqli->prepare('SELECT titolo FROM home_slides WHERE id = ? LIMIT 1');
    $titolo = '';

    if ($stmtRead) {
        $stmtRead->bind_param('i', $id);
        $stmtRead->execute();
        $titolo = (string)($stmtRead->get_result()->fetch_assoc()['titolo'] ?? '');
        $stmtRead->close();
    }

    $stmt = $mysqli->prepare('DELETE FROM home_slides WHERE id = ? LIMIT 1');
    if (!$stmt) {
        admin_fail('Query di eliminazione non valida.', 500);
    }

    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        $stmt->close();
        admin_fail('Non sono riuscito a eliminare la slide.', 500);
    }

    $removed = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$removed) {
        admin_fail('Slide non trovata.', 404);
    }

    admin_log($mysqli, (int)$adminUser['id'], 'delete_home_slide', null, ['slide_id' => $id, 'titolo' => $titolo]);
    admin_ok(['message' => 'Slide eliminata.']);
} catch (Throwable $e) {
    admin_fail('Errore eliminazione slide.', 500);
}
