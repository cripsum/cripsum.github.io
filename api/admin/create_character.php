<?php
require_once __DIR__ . '/bootstrap.php';
try {
    $input = admin_input();
    $cols = admin_character_columns($mysqli);
    $nameCol = $cols['name'] ?: admin_fail('Campo nome personaggio mancante.', 500);
    $name = trim((string)($input['nome'] ?? ''));
    if ($name === '' || mb_strlen($name) > 80) admin_fail('Nome personaggio non valido.');

    $fields = [admin_qcol($nameCol)];
    $placeholders = ['?'];
    $types = 's';
    $params = [$name];

    $map = [
        'image' => ['input' => 'img_url', 'type' => 's', 'value' => admin_validate_url($input['img_url'] ?? null)],
        'rarity' => ['input' => 'rarità', 'type' => 's', 'value' => trim((string)($input['rarità'] ?? '')) ?: null],
        'audio' => ['input' => 'audio_url', 'type' => 's', 'value' => admin_validate_url($input['audio_url'] ?? null)],
        'category' => ['input' => 'categoria', 'type' => 's', 'value' => trim((string)($input['categoria'] ?? '')) ?: null],
    ];
    foreach ($map as $key => $cfg) {
        if ($cols[$key]) { $fields[] = admin_qcol($cols[$key]); $placeholders[] = '?'; $types .= 's'; $params[] = $cfg['value']; }
    }

    $stmt = $mysqli->prepare('INSERT INTO personaggi (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')');
    if (!$stmt) admin_fail('Query creazione personaggio non valida.', 500);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Non sono riuscito a creare il personaggio.', 500);
    $id = $stmt->insert_id;
    $stmt->close();
    admin_log($mysqli, (int)$adminUser['id'], 'create_character', null, ['character_id' => $id, 'name' => $name]);
    admin_ok(['message' => 'Personaggio creato.', 'id' => $id]);
} catch (Throwable $e) { admin_fail('Errore creazione personaggio.', 500); }
