<?php
require_once __DIR__ . '/bootstrap.php';
try {
    $input = admin_input();
    $id = (int)($input['id'] ?? 0);
    $cols = admin_achievement_columns($mysqli);
    $nameCol = $cols['name'] ?: admin_fail('Campo nome achievement mancante.', 500);
    $name = trim((string)($input['nome'] ?? ''));
    if ($id <= 0) admin_fail('ID achievement non valido.');
    if ($name === '' || mb_strlen($name) > 90) admin_fail('Nome achievement non valido.');

    $sets = [admin_qcol($nameCol) . ' = ?'];
    $types = 's';
    $params = [$name];
    $values = [
        'description' => trim((string)($input['descrizione'] ?? '')) ?: null,
        'image' => trim((string)($input['img_url'] ?? '')) ?: null,
        'points' => max(0, (int)($input['punti'] ?? 0)),
    ];
    foreach ($values as $key => $value) {
        if ($cols[$key]) {
            $sets[] = admin_qcol($cols[$key]) . ' = ?';
            if ($key === 'points') { $types .= 'i'; $params[] = $value; }
            else { $types .= 's'; $params[] = $value; }
        }
    }
    $params[] = $id; $types .= 'i';
    $stmt = $mysqli->prepare('UPDATE achievement SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1');
    if (!$stmt) admin_fail('Query modifica achievement non valida.', 500);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Non sono riuscito a modificare l’achievement.', 500);
    $stmt->close();
    admin_log($mysqli, (int)$adminUser['id'], 'update_achievement', null, ['achievement_id' => $id, 'name' => $name]);
    admin_ok(['message' => 'Achievement aggiornato.']);
} catch (Throwable $e) { admin_fail('Errore modifica achievement.', 500); }
