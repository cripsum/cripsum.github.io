<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/achievements.php';
try {
    $input = admin_input();
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) admin_fail('ID achievement non valido.');

    $v3 = admin_column_exists($mysqli, 'achievement', 'metrica');

    // Campi controllati dal motore: categorie, livelli e metriche escono
    // da elenchi chiusi, quindi i nomi di colonna qui sotto sono fissi.
    $fields = ach_admin_fields($input, $v3);
    if (is_string($fields)) admin_fail($fields);

    $sets = [];
    $types = '';
    $params = [];
    foreach ($fields as $column => $value) {
        $sets[] = admin_qcol($column) . ' = ?';
        $types .= is_int($value) ? 'i' : 's';
        $params[] = $value;
    }
    $params[] = $id;
    $types .= 'i';

    $stmt = $mysqli->prepare('UPDATE achievement SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1');
    if (!$stmt) admin_fail('Query modifica achievement non valida.', 500);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Non sono riuscito a modificare l’achievement.', 500);
    $stmt->close();

    admin_log($mysqli, (int)$adminUser['id'], 'update_achievement', null, ['achievement_id' => $id, 'name' => $fields['nome']]);
    admin_ok(['message' => 'Achievement aggiornato.']);
} catch (Throwable $e) { admin_fail('Errore modifica achievement.', 500); }
