<?php
require_once __DIR__ . '/bootstrap.php';

try {
    cv2_check_csrf();

    $user = cv2_require_login($mysqli);
    $input = cv2_input();

    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $meta = cv2_meta($type);
    $id = (int)($input['id'] ?? 0);

    if ($id <= 0) cv2_fail('ID non valido.');
    if (!cv2_can_manage_post($mysqli, $user, $meta, $id)) cv2_fail('Non puoi modificare questo post.', 403);

    $title = cv2_validate_title((string)($input['titolo'] ?? ''));
    $description = cv2_validate_text((string)($input['descrizione'] ?? ''), 2000, 'Descrizione');
    $motivation = cv2_validate_text((string)($input['motivazione'] ?? ''), 2000, 'Motivazione');
    $tag = cv2_validate_tag($input['tag'] ?? null);
    $isSpoiler = cv2_bool_int($input['is_spoiler'] ?? 0);

    $table = cv2_qcol($meta['table']);
    $titleCol = cv2_qcol($meta['title']);
    $descCol = cv2_qcol($meta['description']);

    $sets = ["$titleCol = ?", "$descCol = ?"];
    $types = 'ss';
    $params = [$title, $description];

    if ($type === 'rimasto') {
        if ($motivation === '') cv2_fail('La motivazione è obbligatoria.');
        $sets[] = cv2_qcol($meta['extra']) . ' = ?';
        $types .= 's';
        $params[] = $motivation;
    }

    if (cv2_column_exists($mysqli, $meta['table'], 'tag')) {
        $sets[] = '`tag` = ?';
        $types .= 's';
        $params[] = $tag;
    }

    if (cv2_column_exists($mysqli, $meta['table'], 'is_spoiler')) {
        $sets[] = '`is_spoiler` = ?';
        $types .= 'i';
        $params[] = $isSpoiler;
    }

    if (cv2_column_exists($mysqli, $meta['table'], 'updated_at')) {
        $sets[] = '`updated_at` = NOW()';
    }

    $params[] = $id;
    $types .= 'i';

    $sql = "UPDATE $table SET " . implode(', ', $sets) . " WHERE id = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) cv2_fail('Query modifica non valida: ' . $mysqli->error, 500);

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) cv2_fail('Non sono riuscito a modificare il post.', 500);
    $stmt->close();

    cv2_ok(['message' => 'Post aggiornato.']);
} catch (Throwable $e) {
    cv2_fail('Errore modifica post: ' . $e->getMessage(), 500);
}
