<?php
require_once __DIR__ . '/bootstrap.php';

/*
 * Toglie un personaggio dall'inventario. Con `quantity` toglie solo quelle
 * copie (la riga resta finche' ne rimane almeno una); senza, toglie tutto.
 */

try {
    $input = admin_input();
    $userId = (int)($input['user_id'] ?? 0);
    $characterId = (int)($input['character_id'] ?? 0);
    $quantity = isset($input['quantity']) ? max(1, (int)$input['quantity']) : null;
    if ($userId <= 0 || $characterId <= 0) admin_fail('Dati non validi.');

    $target = admin_fetch_user($mysqli, $userId);
    if (!$target) admin_fail('Utente non trovato.', 404);
    if (!admin_can_manage_user($adminUser, $target, true)) admin_fail('Non puoi modificare questo inventario.', 403);

    $qtyCol = admin_inventory_quantity_column($mysqli);
    $current = 1;
    if ($qtyCol) {
        $stmt = $mysqli->prepare('SELECT COALESCE(' . admin_qcol($qtyCol) . ', 1) AS q FROM utenti_personaggi WHERE utente_id = ? AND personaggio_id = ? LIMIT 1');
        $stmt->bind_param('ii', $userId, $characterId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) admin_fail('Il personaggio non è nell\'inventario.', 404);
        $current = max(1, (int)$row['q']);
    }

    $removeAll = $quantity === null || !$qtyCol || $quantity >= $current;
    if ($removeAll) {
        $stmt = $mysqli->prepare('DELETE FROM utenti_personaggi WHERE utente_id = ? AND personaggio_id = ?');
        if ($stmt) $stmt->bind_param('ii', $userId, $characterId);
    } else {
        $stmt = $mysqli->prepare('UPDATE utenti_personaggi SET ' . admin_qcol($qtyCol) . ' = ' . admin_qcol($qtyCol) . ' - ? WHERE utente_id = ? AND personaggio_id = ?');
        if ($stmt) $stmt->bind_param('iii', $quantity, $userId, $characterId);
    }
    if (!$stmt || !$stmt->execute()) admin_fail('Non sono riuscito a rimuovere il personaggio.', 500);
    $stmt->close();

    $left = $removeAll ? 0 : $current - $quantity;
    $nameCol = admin_character_columns($mysqli)['name'];
    $name = null;
    if ($nameCol) {
        $stmt = $mysqli->prepare('SELECT ' . admin_qcol($nameCol) . ' AS nome FROM personaggi WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $characterId);
            $stmt->execute();
            $name = $stmt->get_result()->fetch_assoc()['nome'] ?? null;
            $stmt->close();
        }
    }
    admin_log($mysqli, (int)$adminUser['id'], 'remove_character_from_user', $userId, [
        'personaggio' => $name,
        'character_id' => $characterId,
        'quantity' => $removeAll ? $current : $quantity,
        'rimaste' => $left,
    ]);
    admin_ok(['message' => $left ? "Copie rimaste: $left." : 'Personaggio rimosso.', 'quantity' => $left]);
} catch (Throwable $e) {
    admin_fail('Errore rimozione personaggio. Dettaglio: ' . $e->getMessage(), 500);
}
