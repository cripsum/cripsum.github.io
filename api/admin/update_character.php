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
    $rarity = admin_character_rarity($input);
    $category = trim((string)($input['categoria'] ?? ''));
    $in_pool_standard = isset($input['in_pool_standard']) ? (int)$input['in_pool_standard'] : 0;
    $limitato = !empty($input['limitato']) ? 1 : 0;
    $catalogo = in_array($input['catalogo'] ?? '', ['visibile', 'segreto', 'nascosto'], true) ? $input['catalogo'] : null;
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
        'audio' => $audioValue !== '' ? $audioValue : null,
        'category' => $category !== '' ? $category : null,
        'video_url' => $videoValue !== '' ? $videoValue : null,
        'in_pool_standard' => $in_pool_standard,
        'ruolo' => $ruolo !== '' ? $ruolo : null,
        'limitato' => $limitato,
    ];
    if ($catalogo !== null) {
        $values['catalogo'] = $catalogo;
    }

    foreach ($values as $key => $value) {
        if (!empty($cols[$key])) {
            $sets[] = admin_qcol($cols[$key]) . ' = ?';
            if (in_array($key, ['in_pool_standard', 'limitato'], true)) {
                $types .= 'i';
            } else {
                $types .= 's';
            }
            $params[] = $value;
        }
    }

    $params[] = $id;
    $types .= 'i';

    $oldImage = admin_character_image($mysqli, $id);

    $stmt = $mysqli->prepare('UPDATE personaggi SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1');
    if (!$stmt) admin_fail('Query modifica personaggio non valida.', 500);

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Non sono riuscito a modificare il personaggio.', 500);
    $stmt->close();

    // Immagine sostituita: quella vecchia se ne va, se sta in img/personaggi/.
    admin_media_cleanup($mysqli, [$oldImage], (int)$adminUser['id']);

    admin_log($mysqli, (int)$adminUser['id'], 'update_character', null, ['character_id' => $id, 'name' => $name]);
    admin_ok(['message' => 'Personaggio aggiornato.']);
} catch (Throwable $e) {
    error_log('update_character fatal error: ' . $e->getMessage());
    admin_fail('Errore modifica personaggio.', 500);
}
