<?php
require_once __DIR__ . '/bootstrap.php';
try {
    $input = admin_input();
    $id = (int)($input['id'] ?? 0);
    $cols = admin_character_columns($mysqli);
    $nameCol = $cols['name'] ?: admin_fail('Campo nome personaggio mancante.', 500);
    $name = trim((string)($input['nome'] ?? ''));
    if ($id <= 0) admin_fail('ID personaggio non valido.');
    if ($name === '' || mb_strlen($name) > 80) admin_fail('Nome personaggio non valido.');

    $sets = [admin_qcol($nameCol) . ' = ?'];
    $types = 's';
    $params = [$name];
    $values = [
        'image' => admin_validate_url($input['img_url'] ?? null),
        'rarity' => trim((string)($input['rarità'] ?? '')) ?: null,
        'audio' => admin_validate_url($input['audio_url'] ?? null),
        'category' => trim((string)($input['categoria'] ?? '')) ?: null,
    ];
    foreach ($values as $key => $value) {
        if ($cols[$key]) { $sets[] = admin_qcol($cols[$key]) . ' = ?'; $types .= 's'; $params[] = $value; }
    }
    $params[] = $id; $types .= 'i';
    $stmt = $mysqli->prepare('UPDATE personaggi SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1');
    if (!$stmt) admin_fail('Query modifica personaggio non valida.', 500);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Non sono riuscito a modificare il personaggio.', 500);
    $stmt->close();
    admin_log($mysqli, (int)$adminUser['id'], 'update_character', null, ['character_id' => $id, 'name' => $name]);
    admin_ok(['message' => 'Personaggio aggiornato.']);
} catch (Throwable $e) { admin_fail('Errore modifica personaggio.', 500); }
