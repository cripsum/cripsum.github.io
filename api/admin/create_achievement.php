<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/achievements.php';
try {
    $input = admin_input();
    $v3 = admin_column_exists($mysqli, 'achievement', 'metrica');

    // Campi controllati dal motore: categorie, livelli e metriche escono
    // da elenchi chiusi, quindi i nomi di colonna qui sotto sono fissi.
    $fields = ach_admin_fields($input, $v3);
    if (is_string($fields)) admin_fail($fields);

    $columns = array_keys($fields);
    $types = '';
    $params = [];
    foreach ($fields as $column => $value) {
        $types .= is_int($value) ? 'i' : 's';
        $params[] = $value;
    }

    $stmt = $mysqli->prepare(
        'INSERT INTO achievement (' . implode(', ', array_map('admin_qcol', $columns)) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')'
    );
    if (!$stmt) admin_fail('Query creazione achievement non valida.', 500);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Non sono riuscito a creare l’achievement.', 500);
    $id = $stmt->insert_id;
    $stmt->close();

    admin_log($mysqli, (int)$adminUser['id'], 'create_achievement', null, ['achievement_id' => $id, 'name' => $fields['nome']]);
    admin_ok(['message' => 'Achievement creato.', 'id' => $id]);
} catch (Throwable $e) { admin_fail('Errore creazione achievement.', 500); }
