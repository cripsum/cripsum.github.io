<?php
require_once __DIR__ . '/bootstrap.php';

try {
    cv2_check_csrf();

    $user = cv2_require_login($mysqli);
    cv2_rate_limit('cv2_last_post', 45, 'Stai pubblicando troppo velocemente.');

    $type = cv2_normalize_type((string)($_POST['type'] ?? 'shitpost'));
    $meta = cv2_meta($type);

    if (!cv2_table_exists($mysqli, $meta['table'])) {
        cv2_fail('Tabella contenuti mancante.', 500);
    }

    $title = cv2_validate_title((string)($_POST['titolo'] ?? ''));
    $description = cv2_validate_text((string)($_POST['descrizione'] ?? ''), 2000, 'Descrizione');
    $motivation = cv2_validate_text((string)($_POST['motivazione'] ?? ''), 2000, 'Motivazione');
    $tag = cv2_validate_tag($_POST['tag'] ?? null);
    $isSpoiler = cv2_bool_int($_POST['is_spoiler'] ?? 0);
    $file = cv2_upload_file('media');

    if ($type === 'rimasto' && $motivation === '') {
        cv2_fail('La motivazione è obbligatoria.');
    }

    $approved = cv2_is_admin($user) ? 1 : 0;
    $blobData = $file['blob'];
    $blobParam = null;

    if ($type === 'rimasto') {
        $stmt = $mysqli->prepare("
            INSERT INTO toprimasti
                (id_utente, titolo, descrizione, motivazione, foto_rimasto, tipo_foto_rimasto, data_creazione, approvato, reazioni)
            VALUES
                (?, ?, ?, ?, ?, ?, NOW(), ?, 0)
        ");
        if (!$stmt) cv2_fail('Query creazione non valida: ' . $mysqli->error, 500);

        $stmt->bind_param('isssbsi', $user['id'], $title, $description, $motivation, $blobParam, $file['mime'], $approved);
        $stmt->send_long_data(4, $blobData);
    } else {
        $stmt = $mysqli->prepare("
            INSERT INTO shitposts
                (id_utente, titolo, descrizione, foto_shitpost, tipo_foto_shitpost, data_creazione, approvato)
            VALUES
                (?, ?, ?, ?, ?, NOW(), ?)
        ");
        if (!$stmt) cv2_fail('Query creazione non valida: ' . $mysqli->error, 500);

        $stmt->bind_param('issbsi', $user['id'], $title, $description, $blobParam, $file['mime'], $approved);
        $stmt->send_long_data(3, $blobData);
    }

    if (!$stmt->execute()) cv2_fail('Non sono riuscito a creare il post.', 500);
    $postId = (int)$stmt->insert_id;
    $stmt->close();

    $table = $meta['table'];
    $sets = [];
    $types = '';
    $params = [];

    if ($tag !== null && cv2_column_exists($mysqli, $table, 'tag')) {
        $sets[] = '`tag` = ?';
        $types .= 's';
        $params[] = $tag;
    }

    if (cv2_column_exists($mysqli, $table, 'is_spoiler')) {
        $sets[] = '`is_spoiler` = ?';
        $types .= 'i';
        $params[] = $isSpoiler;
    }

    if ($sets) {
        $params[] = $postId;
        $types .= 'i';

        $sql = 'UPDATE ' . cv2_qcol($table) . ' SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1';
        $up = $mysqli->prepare($sql);
        if ($up) {
            $up->bind_param($types, ...$params);
            $up->execute();
            $up->close();
        }
    }

    cv2_ok([
        'message' => $approved ? 'Post pubblicato.' : 'Post inviato. Sarà visibile dopo approvazione.',
        'post_id' => $postId,
        'approved' => $approved,
    ]);
} catch (Throwable $e) {
    cv2_fail('Errore creazione post: ' . $e->getMessage(), 500);
}
