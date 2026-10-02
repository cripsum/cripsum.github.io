<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/content_moderation.php';

/*
 * Elimina un post: l'autore il proprio, lo staff qualunque (con un motivo
 * facoltativo che arriva all'autore). La pulizia di commenti, reazioni, voti,
 * salvati e media è quella condivisa con pannello e bot.
 */
try {
    cv2_check_csrf();

    $user = cv2_require_login($mysqli);
    $userId = (int)$user['id'];
    $input = cv2_input();

    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $post = cm_require_post($mysqli, $type, (int)($input['id'] ?? 0), $user, false);
    $own = $post['id_utente'] === $userId;

    if (!$own && !cv2_is_admin($user)) {
        cv2_fail(cm_t('Non puoi eliminare questo post.', 'You cannot delete this post.'), 403);
    }

    $result = cripsum_delete_community_post($mysqli, $type, $post['id'], [
        'notify' => !$own,
        'reason' => $own ? '' : (string)($input['reason'] ?? ''),
        'reviewer_id' => $own ? null : $userId,
    ]);

    if (!$result['ok']) {
        throw new RuntimeException((string)$result['error']);
    }

    if (!$own) {
        require_once __DIR__ . '/../../includes/admin/admin_helpers.php';
        admin_log($mysqli, $userId, $type === 'rimasto' ? 'delete_toprimasti' : 'delete_shitpost', $post['id_utente'], ['post_id' => $post['id'], 'title' => $post['titolo'], 'from' => 'page']);
    }

    cv2_ok(['message' => cm_t('Post eliminato.', 'Post deleted.')]);
} catch (Throwable $e) {
    cm_crash('elimina post', $e);
}
