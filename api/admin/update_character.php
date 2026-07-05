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

    $description = trim((string)($input['descrizione'] ?? ''));
    $description_en = trim((string)($input['descrizione_en'] ?? ''));
    $features = trim((string)($input['caratteristiche'] ?? ''));
    $features_en = trim((string)($input['caratteristiche_en'] ?? ''));
    $rarity = trim((string)($input['rarita'] ?? $input['rarità'] ?? ''));
    $rarity_en = trim((string)($input['rarita_en'] ?? $input['rarità_en'] ?? ''));
    $category = trim((string)($input['categoria'] ?? ''));
    $pool_evento = isset($input['pool_evento']) ? (int)$input['pool_evento'] : 0;
    $in_pool_standard = isset($input['in_pool_standard']) ? (int)$input['in_pool_standard'] : 0;
    $ruolo = trim((string)($input['ruolo'] ?? ''));

    $imageValue = admin_normalize_media_file(
        $input['img_url'] ?? '',
        ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'Immagine'
    );

    $audioValue = admin_normalize_media_file(
        $input['audio_url'] ?? '',
        ['mp3', 'wav', 'ogg', 'm4a', 'aac'],
        'Audio'
    );

    $videoValue = admin_normalize_media_file(
        $input['video_url'] ?? '',
        ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'],
        'Video'
    );

    $values = [
        'description' => $description,
        'description_en' => $description_en,
        'features' => $features,
        'features_en' => $features_en,
        'image' => $imageValue !== '' ? $imageValue : null,
        'rarity' => $rarity,
        'rarity_en' => $rarity_en,
        'audio' => $audioValue !== '' ? $audioValue : null,
        'category' => $category !== '' ? $category : null,
        'video_url' => $videoValue !== '' ? $videoValue : null,
        'pool_evento' => $pool_evento,
        'in_pool_standard' => $in_pool_standard,
        'ruolo' => $ruolo !== '' ? $ruolo : null,
    ];

    foreach ($values as $key => $value) {
        if (!empty($cols[$key])) {
            $sets[] = admin_qcol($cols[$key]) . ' = ?';
            if ($key === 'pool_evento' || $key === 'in_pool_standard') {
                $types .= 'i';
            } else {
                $types .= 's';
            }
            $params[] = $value;
        }
    }

    $params[] = $id;
    $types .= 'i';

    $stmt = $mysqli->prepare('UPDATE personaggi SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1');
    if (!$stmt) admin_fail('Query modifica personaggio non valida.', 500);

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Non sono riuscito a modificare il personaggio.', 500);
    $stmt->close();

    admin_log($mysqli, (int)$adminUser['id'], 'update_character', null, ['character_id' => $id, 'name' => $name]);
    admin_ok(['message' => 'Personaggio aggiornato.']);
} catch (Throwable $e) {
    error_log('update_character fatal error: ' . $e->getMessage());
    admin_fail('Errore modifica personaggio.', 500);
}
