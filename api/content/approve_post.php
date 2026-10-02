<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/content_moderation.php';
require_once __DIR__ . '/../../includes/admin/admin_helpers.php';

/*
 * Approva o nasconde un post dalla pagina (solo staff). Fa esattamente
 * quello che fa il pannello: stessa funzione, stesso log, stesso annuncio.
 */
try {
    cv2_check_csrf();

    $user = cv2_require_login($mysqli);
    if (!cv2_is_admin($user)) {
        cv2_fail(cm_t('Permessi insufficienti.', 'Not enough permissions.'), 403);
    }

    $input = cv2_input();
    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $id = (int)($input['id'] ?? 0);
    $approved = cv2_bool_int($input['approved'] ?? 0) === 1;

    $result = cripsum_set_community_post_approval($mysqli, $type, $id, $approved, ['actor_id' => (int)$user['id']]);
    if (!$result['ok']) {
        cv2_fail(cm_t('Questo post non esiste più.', 'This post no longer exists.'), 404);
    }

    $action = ($approved ? 'approve_' : 'unapprove_') . ($type === 'rimasto' ? 'toprimasti' : 'shitpost');
    admin_log($mysqli, (int)$user['id'], $action, $result['author_id'], ['post_id' => $id, 'from' => 'page']);

    cv2_ok(['message' => $approved ? cm_t('Post approvato.', 'Post approved.') : cm_t('Post nascosto.', 'Post hidden.')]);
} catch (Throwable $e) {
    cm_crash('approvazione', $e);
}
