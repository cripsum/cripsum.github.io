<?php
require_once __DIR__ . '/bootstrap.php';
try {
    $input = admin_input();
    $cols = admin_achievement_columns($mysqli);
    $nameCol = $cols['name'] ?: admin_fail('Campo nome achievement mancante.', 500);
    $name = trim((string)($input['nome'] ?? ''));
    if ($name === '' || mb_strlen($name) > 90) admin_fail('Nome achievement non valido.');

    $fields = [admin_qcol($nameCol)];
    $placeholders = ['?'];
    $types = 's';
    $params = [$name];
    $values = [
        'description' => trim((string)($input['descrizione'] ?? '')) ?: null,
        'image' => trim((string)($input['img_url'] ?? '')) ?: null,
        'points' => max(0, (int)($input['punti'] ?? 0)),
    ];
    foreach ($values as $key => $value) {
        if ($cols[$key]) {
            $fields[] = admin_qcol($cols[$key]); $placeholders[] = '?';
            if ($key === 'points') { $types .= 'i'; $params[] = $value; }
            else { $types .= 's'; $params[] = $value; }
        }
    }
    $stmt = $mysqli->prepare('INSERT INTO achievement (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')');
    if (!$stmt) admin_fail('Query creazione achievement non valida.', 500);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Non sono riuscito a creare l’achievement.', 500);
    $id = $stmt->insert_id;
    $stmt->close();
    admin_log($mysqli, (int)$adminUser['id'], 'create_achievement', null, ['achievement_id' => $id, 'name' => $name]);
    admin_ok(['message' => 'Achievement creato.', 'id' => $id]);
} catch (Throwable $e) { admin_fail('Errore creazione achievement.', 500); }
