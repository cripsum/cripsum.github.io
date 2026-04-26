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
    if (!cv2_can_manage_post($mysqli, $user, $meta, $id)) cv2_fail('Non puoi eliminare questo post.', 403);

    $mysqli->begin_transaction();

    if ($type === 'shitpost') {
        if (cv2_table_exists($mysqli, 'commenti_shitpost')) {
            $stmt = $mysqli->prepare("DELETE FROM commenti_shitpost WHERE id_shitpost = ?");
            if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
        }

        if (cv2_table_exists($mysqli, 'shitpost_likes')) {
            $stmt = $mysqli->prepare("DELETE FROM shitpost_likes WHERE id_shitpost = ?");
            if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
        }
    } else {
        if (cv2_table_exists($mysqli, 'voti_toprimasti')) {
            $stmt = $mysqli->prepare("DELETE FROM voti_toprimasti WHERE id_post = ?");
            if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
        }
    }

    foreach (['content_saves', 'content_reports', 'content_views', 'content_comments'] as $table) {
        if (!cv2_table_exists($mysqli, $table)) continue;
        $stmt = $mysqli->prepare("DELETE FROM `$table` WHERE content_type = ? AND post_id = ?");
        if ($stmt) { $stmt->bind_param('si', $type, $id); $stmt->execute(); $stmt->close(); }
    }

    $table = cv2_qcol($meta['table']);
    $stmt = $mysqli->prepare("DELETE FROM $table WHERE id = ? LIMIT 1");
    if (!$stmt) {
        $mysqli->rollback();
        cv2_fail('Query eliminazione non valida.', 500);
    }

    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        $mysqli->rollback();
        cv2_fail('Non sono riuscito a eliminare il post.', 500);
    }
    $stmt->close();

    $mysqli->commit();

    cv2_ok(['message' => 'Post eliminato.']);
} catch (Throwable $e) {
    @$mysqli->rollback();
    cv2_fail('Errore eliminazione: ' . $e->getMessage(), 500);
}
