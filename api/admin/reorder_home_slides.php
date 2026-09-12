<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Riordina le slide della homepage.
 *
 * Arriva l'elenco completo degli id nell'ordine voluto e le posizioni vengono
 * riscritte da zero a passi di dieci. Rinumerare tutto invece di scambiare due
 * righe evita che due slide finiscano sulla stessa posizione dopo qualche
 * spostamento, e lascia spazio per infilarne una in mezzo a mano.
 */
try {
    if (!admin_table_exists($mysqli, 'home_slides')) {
        admin_fail('Tabella home_slides mancante: applica la migrazione.', 500);
    }

    $input = admin_input();
    $order = $input['order'] ?? null;

    if (is_string($order)) {
        $order = array_filter(explode(',', $order), static fn($v) => trim($v) !== '');
    }

    if (!is_array($order) || !$order) {
        admin_fail('Ordine mancante.');
    }

    $ids = [];
    foreach ($order as $value) {
        $id = (int)$value;
        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    if (!$ids) {
        admin_fail('Ordine non valido.');
    }

    if (count($ids) > 200) {
        admin_fail('Troppe slide in una volta.');
    }

    $stmt = $mysqli->prepare('UPDATE home_slides SET posizione = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        admin_fail('Query di riordino non valida.', 500);
    }

    $mysqli->begin_transaction();
    $posizione = 10;
    $aggiornate = 0;

    foreach ($ids as $id) {
        $stmt->bind_param('ii', $posizione, $id);

        if (!$stmt->execute()) {
            $stmt->close();
            $mysqli->rollback();
            admin_fail('Non sono riuscito a riordinare le slide.', 500);
        }

        $aggiornate += $stmt->affected_rows > 0 ? 1 : 0;
        $posizione += 10;
    }

    $stmt->close();
    $mysqli->commit();

    admin_log($mysqli, (int)$adminUser['id'], 'reorder_home_slides', null, ['slide' => count($ids)]);
    admin_ok(['message' => 'Ordine aggiornato.', 'aggiornate' => $aggiornate]);
} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        @$mysqli->rollback();
    }
    admin_fail('Errore riordino slide.', 500);
}
