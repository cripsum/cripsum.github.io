<?php
require_once __DIR__ . '/bootstrap.php';

try {
    cv2_check_csrf();
    $user = cv2_require_login($mysqli);
    cv2_rate_limit('cv2_last_comment', 6, 'Stai commentando troppo velocemente.');

    $input = cv2_input();
    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $id = (int)($input['id'] ?? 0);
    $comment = cv2_validate_text((string)($input['commento'] ?? ''), 500, 'Commento');

    if ($id <= 0) cv2_fail('ID non valido.');
    if ($comment === '') cv2_fail('Il commento non può essere vuoto.');

    if ($type === 'shitpost') {
        if (!cv2_table_exists($mysqli, 'commenti_shitpost')) cv2_fail('Tabella commenti mancante.', 500);

        $stmt = $mysqli->prepare("INSERT INTO commenti_shitpost (id_shitpost, id_utente, commento, data_commento) VALUES (?, ?, ?, NOW())");
        if (!$stmt) cv2_fail('Query commento non valida.', 500);
        $stmt->bind_param('iis', $id, $user['id'], $comment);
        if (!$stmt->execute()) cv2_fail('Non sono riuscito a commentare.', 500);
        $stmt->close();

        cv2_ok(['message' => 'Commento inviato.']);
    }

    if (!cv2_table_exists($mysqli, 'content_comments')) cv2_fail('Tabella commenti mancante. Esegui SQL upgrade.', 500);

    $stmt = $mysqli->prepare("INSERT INTO content_comments (content_type, post_id, user_id, comment, created_at) VALUES ('rimasto', ?, ?, ?, NOW())");
    if (!$stmt) cv2_fail('Query commento non valida.', 500);
    $stmt->bind_param('iis', $id, $user['id'], $comment);
    if (!$stmt->execute()) cv2_fail('Non sono riuscito a commentare.', 500);
    $stmt->close();

    cv2_ok(['message' => 'Commento inviato.']);
} catch (Throwable $e) {
    cv2_fail('Errore commento: ' . $e->getMessage(), 500);
}
