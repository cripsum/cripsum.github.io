<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/community/media.php';

/*
 * Butta via i file caricati e non pubblicati (tolti dall'elenco, finestra
 * chiusa senza inviare). Tocca solo le bozze di chi chiama.
 */
try {
    cv2_check_csrf();
    $user = cv2_require_login($mysqli);

    $input = cv2_input();
    $ids = is_array($input['ids'] ?? null) ? $input['ids'] : [];

    $removed = 0;
    if (cm_has($mysqli, 'content_media')) {
        foreach (array_slice($ids, 0, 20) as $id) {
            if (cm_draft_delete($mysqli, (int)$user['id'], (int)$id)) {
                $removed++;
            }
        }
    }

    cv2_ok(['removed' => $removed]);
} catch (Throwable $e) {
    cm_crash('scarto media', $e);
}
