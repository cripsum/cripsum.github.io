<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $type = cv2_normalize_type((string)($_GET['type'] ?? 'shitpost'));
    $id = (int)($_GET['id'] ?? 0);

    // I commenti di un post in attesa li vede solo chi vede il post.
    $post = cm_require_post($mysqli, $type, $id, $currentUser, false);
    cripsum_release_session();

    cv2_ok(['comments' => cm_comments($mysqli, $type, $post['id'], $currentUser)]);
} catch (Throwable $e) {
    cm_crash('commenti', $e);
}
