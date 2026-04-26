<?php
require_once __DIR__ . '/bootstrap.php';

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

    $stmt = $mysqli->prepare('SELECT id FROM personaggi WHERE id = ? LIMIT 1');
    if (!$stmt) admin_fail('Tabella personaggi non disponibile.', 500);
    $stmt->bind_param('i', $characterId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$exists) admin_fail('Personaggio non trovato.', 404);

    $qtyCol = admin_inventory_quantity_column($mysqli);
    if ($qtyCol) {
        $stmt = $mysqli->prepare('SELECT ' . admin_qcol($qtyCol) . ' AS q FROM utenti_personaggi WHERE utente_id = ? AND personaggio_id = ? LIMIT 1');
        $stmt->bind_param('ii', $userId, $characterId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) {
            $stmt = $mysqli->prepare('UPDATE utenti_personaggi SET ' . admin_qcol($qtyCol) . ' = ' . admin_qcol($qtyCol) . ' + ? WHERE utente_id = ? AND personaggio_id = ?');
            $stmt->bind_param('iii', $quantity, $userId, $characterId);
        } else {
            $stmt = $mysqli->prepare('INSERT INTO utenti_personaggi (utente_id, personaggio_id, ' . admin_qcol($qtyCol) . ') VALUES (?, ?, ?)');
            $stmt->bind_param('iii', $userId, $characterId, $quantity);
        }
    } else {
        $stmt = $mysqli->prepare('SELECT 1 FROM utenti_personaggi WHERE utente_id = ? AND personaggio_id = ? LIMIT 1');
        $stmt->bind_param('ii', $userId, $characterId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) admin_ok(['message' => 'Personaggio già presente.']);
        $stmt = $mysqli->prepare('INSERT INTO utenti_personaggi (utente_id, personaggio_id) VALUES (?, ?)');
        $stmt->bind_param('ii', $userId, $characterId);
    }

    if (!$stmt || !$stmt->execute()) admin_fail('Non sono riuscito ad aggiungere il personaggio.', 500);
    $stmt->close();
    admin_log($mysqli, (int)$adminUser['id'], 'add_character_to_user', $userId, ['character_id' => $characterId, 'quantity' => $quantity]);
    admin_ok(['message' => 'Personaggio aggiunto.']);
} catch (Throwable $e) {
    admin_fail('Errore aggiunta personaggio.', 500);
}
