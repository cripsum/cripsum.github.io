<?php
require_once __DIR__ . '/bootstrap.php';

try {
    cv2_check_csrf();
    $user = cv2_require_login($mysqli);
    $input = cv2_input();

    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $id = (int)($input['comment_id'] ?? 0);

    if ($id <= 0) cv2_fail('ID commento non valido.');

    if ($type === 'shitpost') {
        if (!cv2_table_exists($mysqli, 'commenti_shitpost')) cv2_fail('Tabella commenti mancante.', 500);

        $stmt = $mysqli->prepare("SELECT id_utente FROM commenti_shitpost WHERE id = ? LIMIT 1");
        if (!$stmt) cv2_fail('Query commento non valida.', 500);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) cv2_fail('Commento non trovato.', 404);
        if (!cv2_is_admin($user) && (int)$row['id_utente'] !== (int)$user['id']) cv2_fail('Non puoi eliminare questo commento.', 403);

        $stmt = $mysqli->prepare("DELETE FROM commenti_shitpost WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        cv2_ok(['message' => 'Commento eliminato.']);
    }

    if (!cv2_table_exists($mysqli, 'content_comments')) cv2_fail('Tabella commenti mancante.', 500);

    $stmt = $mysqli->prepare("SELECT user_id FROM content_comments WHERE id = ? LIMIT 1");
    if (!$stmt) cv2_fail('Query commento non valida.', 500);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) cv2_fail('Commento non trovato.', 404);
    if (!cv2_is_admin($user) && (int)$row['user_id'] !== (int)$user['id']) cv2_fail('Non puoi eliminare questo commento.', 403);

    $stmt = $mysqli->prepare("DELETE FROM content_comments WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    cv2_ok(['message' => 'Commento eliminato.']);
} catch (Throwable $e) {
    cv2_fail('Errore eliminazione commento: ' . $e->getMessage(), 500);
}
