<?php
require_once __DIR__ . '/bootstrap.php';

try {
    cv2_check_csrf();
    $user = cv2_require_login($mysqli);
    cm_throttle('save', 40, 60);

    $input = cv2_input();
    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));

    if (!cm_schema($mysqli, $type)['saves']) {
        cv2_fail(cm_t('I salvati non sono disponibili.', 'Saved posts are not available.'), 503);
    }

    $post = cm_require_post($mysqli, $type, (int)($input['id'] ?? 0), $user, true);
    $userId = (int)$user['id'];

    $stmt = $mysqli->prepare('DELETE FROM content_saves WHERE content_type = ? AND post_id = ? AND user_id = ?');
    $stmt->bind_param('sii', $type, $post['id'], $userId);
    $stmt->execute();
    $removed = $stmt->affected_rows > 0;
    $stmt->close();

    if ($removed) {
        cv2_ok(['active' => false]);
    }

    $stmt = $mysqli->prepare('INSERT IGNORE INTO content_saves (content_type, post_id, user_id, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->bind_param('sii', $type, $post['id'], $userId);
    $stmt->execute();
    $stmt->close();

    cv2_ok(['active' => true]);
} catch (Throwable $e) {
    cm_crash('salvataggio', $e);
}
