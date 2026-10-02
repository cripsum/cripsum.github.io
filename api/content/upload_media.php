<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/community/media.php';

/*
 * Carica un file per un post che si sta scrivendo.
 *
 * Un file per richiesta: ognuno ha la sua barra di avanzamento e nessuna
 * richiesta supera i limiti di caricamento del server. Il file resta una
 * «bozza» dell'utente finché create_post.php non lo aggancia a un post;
 * dopo due ore le bozze dimenticate si cancellano da sole.
 *
 * POST (multipart) media, type
 *      poster, larghezza, altezza, durata   solo per i video, dal browser
 */
try {
    cv2_check_csrf();

    $user = cv2_require_login($mysqli);
    $type = cv2_normalize_type((string)($_POST['type'] ?? 'shitpost'));

    if (!cm_schema($mysqli, $type)['extra']) {
        cv2_fail(cm_t('Il caricamento a più file non è ancora attivo.', 'Multi-file upload is not active yet.'), 503);
    }
    if (!cv2_is_admin($user) && empty(cm_settings($mysqli)[$type . '_aperto'])) {
        cv2_fail(cm_t('Le pubblicazioni sono chiuse per il momento.', 'Posting is closed for now.'), 403);
    }

    cm_throttle('upload', 40, 600);

    $media = cm_process_upload('media');
    $poster = null;
    $duration = null;

    if ($media['kind'] === 'video') {
        $poster = cm_process_poster('poster');
        $media['width'] = max(0, min(8192, (int)($_POST['larghezza'] ?? 0))) ?: null;
        $media['height'] = max(0, min(8192, (int)($_POST['altezza'] ?? 0))) ?: null;
        $duration = max(0, min(3600, (int)($_POST['durata'] ?? 0))) ?: null;
    }

    $id = cm_draft_create($mysqli, (int)$user['id'], $media, $poster, $duration);

    cv2_ok([
        'id' => $id,
        'kind' => $media['kind'],
        'width' => $media['width'],
        'height' => $media['height'],
        'bytes' => $media['bytes'],
    ]);
} catch (Throwable $e) {
    cm_crash('caricamento media', $e);
}
