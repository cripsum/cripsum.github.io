<?php
require_once __DIR__ . '/bootstrap.php';

try {
    cv2_check_csrf();
    $user = cv2_require_login($mysqli);

    $input = cv2_input();
    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $id = (int)($input['id'] ?? 0);
    $parentId = (int)($input['parent'] ?? 0);
    $text = trim((string)($input['commento'] ?? ''));

    if ($text === '') {
        cv2_fail(cm_t('Il commento non può essere vuoto.', 'The comment cannot be empty.'));
    }
    if (mb_strlen($text, 'UTF-8') > CM_COMMENT_LENGTH) {
        cv2_fail(cm_t('Commento troppo lungo: massimo ' . CM_COMMENT_LENGTH . ' caratteri.', 'Comment too long: ' . CM_COMMENT_LENGTH . ' characters at most.'));
    }

    $post = cm_require_post($mysqli, $type, $id, $user, true);
    $commentId = cm_comment_add($mysqli, $type, $post, $user, $text, $parentId);

    // Missioni: un commento per post al giorno, non uno a messaggio.
    if (cm_action_once($mysqli, (int)$user['id'], $type, $post['id'], 'comment')) {
        cm_track($mysqli, (int)$user['id'], ['comment_post' => true]);
    }

    cv2_ok([
        'message' => cm_t('Commento inviato.', 'Comment posted.'),
        'comment_id' => $commentId,
        'comments' => cm_comments($mysqli, $type, $post['id'], $user),
    ]);
} catch (Throwable $e) {
    cm_crash('nuovo commento', $e);
}
