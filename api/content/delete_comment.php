<?php
require_once __DIR__ . '/bootstrap.php';

try {
    cv2_check_csrf();
    $user = cv2_require_login($mysqli);
    $input = cv2_input();

    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $id = (int)($input['comment_id'] ?? 0);

    $comment = cm_comment_row($mysqli, $type, $id);
    if (!$comment) {
        cv2_fail(cm_t('Commento non trovato.', 'Comment not found.'), 404);
    }
    if (!cv2_is_admin($user) && $comment['user_id'] !== (int)$user['id']) {
        cv2_fail(cm_t('Non puoi eliminare questo commento.', 'You cannot delete this comment.'), 403);
    }

    $c = cm_comment_meta($type);
    $scope = $type === 'rimasto' ? " AND content_type = 'rimasto'" : '';

    // Con il commento vanno via anche le sue risposte.
    if (cm_schema($mysqli, $type)['replies']) {
        $stmt = $mysqli->prepare("DELETE FROM `{$c['table']}` WHERE parent_id = ?$scope");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $mysqli->prepare("DELETE FROM `{$c['table']}` WHERE id = ?$scope LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    cv2_ok([
        'message' => cm_t('Commento eliminato.', 'Comment deleted.'),
        'comments' => cm_comments($mysqli, $type, $comment['post_id'], $user),
    ]);
} catch (Throwable $e) {
    cm_crash('elimina commento', $e);
}
