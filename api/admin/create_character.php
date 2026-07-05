<?php
require_once __DIR__ . '/bootstrap.php';

function admin_bind_stmt_params(mysqli_stmt $stmt, string $types, array &$params): void
{
    $bindParams = [$types];

    foreach ($params as $key => &$value) {
        $bindParams[] = &$value;
    }

    if (!call_user_func_array([$stmt, 'bind_param'], $bindParams)) {
        admin_fail('Parametri query non validi.', 500);
    }
}

try {
    $input = admin_input();
    $cols = admin_character_columns($mysqli);

    $nameCol = $cols['name'] ?: admin_fail('Campo nome personaggio mancante.', 500);

    $name = trim((string)($input['nome'] ?? ''));

    if ($name === '' || mb_strlen($name) > 80) {
        admin_fail('Nome personaggio non valido.');
    }

    $description = trim((string)($input['descrizione'] ?? ''));
    $description_en = trim((string)($input['descrizione_en'] ?? ''));
    $features = trim((string)($input['caratteristiche'] ?? ''));
    $features_en = trim((string)($input['caratteristiche_en'] ?? ''));

    $rarity = trim((string)($input['rarità'] ?? $input['rarita'] ?? $input['rarity'] ?? ''));
    $rarity_en = trim((string)($input['rarita_en'] ?? $input['rarità_en'] ?? $input['rarity_en'] ?? ''));
    $category = trim((string)($input['categoria'] ?? $input['category'] ?? ''));
    $video_url = trim((string)($input['video_url'] ?? ''));
    $pool_evento = isset($input['pool_evento']) ? (int)$input['pool_evento'] : 0;
    $in_pool_standard = isset($input['in_pool_standard']) ? (int)$input['in_pool_standard'] : 0;
    $ruolo = trim((string)($input['ruolo'] ?? ''));

    $imageValue = admin_normalize_media_file(
        $input['img_url'] ?? $input['image'] ?? '',
        ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'Immagine'
    );

    $audioValue = admin_normalize_media_file(
        $input['audio_url'] ?? $input['audio'] ?? '',
        ['mp3', 'wav', 'ogg', 'm4a', 'aac'],
        'Audio'
    );

    $fields = [admin_qcol($nameCol)];
    $placeholders = ['?'];
    $types = 's';
    $params = [$name];

    $map = [
        'description' => $description,
        'description_en' => $description_en,
        'features' => $features,
        'features_en' => $features_en,
        'image' => $imageValue,
        'rarity' => $rarity,
        'rarity_en' => $rarity_en,
        'audio' => $audioValue,
        'category' => $category,
        'video_url' => $video_url,
        'pool_evento' => $pool_evento,
        'in_pool_standard' => $in_pool_standard,
        'ruolo' => $ruolo,
    ];

    foreach ($map as $key => $value) {
        if (!empty($cols[$key])) {
            $fields[] = admin_qcol($cols[$key]);
            $placeholders[] = '?';
            if ($key === 'pool_evento' || $key === 'in_pool_standard') {
                $types .= 'i';
            } else {
                $types .= 's';
            }
            $params[] = $value;
        }
    }

    $sql = 'INSERT INTO personaggi (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        error_log('create_character prepare error: ' . $mysqli->error . ' | SQL: ' . $sql);
        admin_fail('Query creazione personaggio non valida.', 500);
    }

    admin_bind_stmt_params($stmt, $types, $params);

    if (!$stmt->execute()) {
        error_log('create_character execute error: ' . $stmt->error . ' | SQL: ' . $sql);
        admin_fail('Non sono riuscito a creare il personaggio.', 500);
    }

    $id = $stmt->insert_id;
    $stmt->close();

    admin_log($mysqli, (int)$adminUser['id'], 'create_character', null, [
        'character_id' => $id,
        'name' => $name,
    ]);

    admin_ok([
        'message' => 'Personaggio creato.',
        'id' => $id,
    ]);
} catch (Throwable $e) {
    error_log('create_character fatal error: ' . $e->getMessage());
    admin_fail('Errore creazione personaggio.', 500);
}
