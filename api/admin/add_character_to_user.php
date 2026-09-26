<?php
require_once __DIR__ . '/bootstrap.php';

/*
 * Copie di un personaggio date dal pannello. La riga nuova si scrive come
 * quelle delle pull (data della prima copia, ultima copia, badge NEW
 * nell'inventario) quando lo schema ha quelle colonne.
 */

try {
    $input = admin_input();
    $userId = (int)($input['user_id'] ?? 0);
    $characterId = (int)($input['character_id'] ?? 0);
    $quantity = max(1, min(9999, (int)($input['quantity'] ?? 1)));

    if ($userId <= 0 || $characterId <= 0) admin_fail('Dati non validi.');
    $target = admin_fetch_user($mysqli, $userId);
    if (!$target) admin_fail('Utente non trovato.', 404);
    if (!admin_can_manage_user($adminUser, $target, true)) admin_fail('Non puoi modificare questo inventario.', 403);
    if (!admin_table_exists($mysqli, 'utenti_personaggi')) admin_fail('Tabella utenti_personaggi mancante.', 500);

    $nameCol = admin_character_columns($mysqli)['name'];
    $stmt = $mysqli->prepare('SELECT id' . ($nameCol ? ', ' . admin_qcol($nameCol) . ' AS nome' : ', NULL AS nome') . ' FROM personaggi WHERE id = ? LIMIT 1');
    if (!$stmt) admin_fail('Tabella personaggi non disponibile.', 500);
    $stmt->bind_param('i', $characterId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$exists) admin_fail('Personaggio non trovato.', 404);

    $qtyCol = admin_inventory_quantity_column($mysqli);
    $stmt = $mysqli->prepare('SELECT ' . ($qtyCol ? admin_qcol($qtyCol) : '1') . ' AS q FROM utenti_personaggi WHERE utente_id = ? AND personaggio_id = ? LIMIT 1');
    $stmt->bind_param('ii', $userId, $characterId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing && !$qtyCol) admin_ok(['message' => 'Personaggio già presente.', 'quantity' => 1]);

    $hasLast = admin_column_exists($mysqli, 'utenti_personaggi', 'ultima_copia_il');
    if ($existing) {
        $sql = 'UPDATE utenti_personaggi SET ' . admin_qcol($qtyCol) . ' = ' . admin_qcol($qtyCol) . ' + ?'
            . ($hasLast ? ', ultima_copia_il = NOW()' : '')
            . ' WHERE utente_id = ? AND personaggio_id = ?';
        $stmt = $mysqli->prepare($sql);
        if ($stmt) $stmt->bind_param('iii', $quantity, $userId, $characterId);
    } else {
        $fields = ['utente_id', 'personaggio_id'];
        $values = ['?', '?'];
        if ($qtyCol) { $fields[] = $qtyCol; $values[] = (string)$quantity; }
        if (admin_column_exists($mysqli, 'utenti_personaggi', 'data')) { $fields[] = 'data'; $values[] = 'NOW()'; }
        if ($hasLast) { $fields[] = 'ultima_copia_il'; $values[] = 'NOW()'; }
        if (admin_column_exists($mysqli, 'utenti_personaggi', 'visto')) { $fields[] = 'visto'; $values[] = '0'; }
        $stmt = $mysqli->prepare('INSERT INTO utenti_personaggi (' . implode(', ', array_map('admin_qcol', $fields)) . ') VALUES (' . implode(', ', $values) . ')');
        if ($stmt) $stmt->bind_param('ii', $userId, $characterId);
    }

    if (!$stmt || !$stmt->execute()) admin_fail('Non sono riuscito ad aggiungere il personaggio.', 500);
    $stmt->close();

    $newQuantity = $qtyCol ? (int)($existing['q'] ?? 0) + $quantity : 1;
    admin_log($mysqli, (int)$adminUser['id'], 'add_character_to_user', $userId, array_filter(['personaggio' => $exists['nome'] ?? null, 'character_id' => $characterId, 'quantity' => $quantity], static fn($v) => $v !== null));
    admin_ok(['message' => $existing ? "Aggiunte $quantity copie." : 'Personaggio aggiunto.', 'quantity' => $newQuantity]);
} catch (Throwable $e) {
    admin_fail('Errore aggiunta personaggio. Dettaglio: ' . $e->getMessage(), 500);
}
