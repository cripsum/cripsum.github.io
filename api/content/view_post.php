<?php
require_once __DIR__ . '/bootstrap.php';

/*
 * Conta le visualizzazioni dei post.
 *
 * La pagina manda `ids` con tutti i post visti negli ultimi istanti, cosi' una
 * sola richiesta (e una sola connessione al database) copre tutto lo
 * scorrimento. `id` singolo resta accettato: lo mandano ancora le pagine
 * rimaste aperte con il vecchio script.
 *
 * Un errore qui non deve mai farsi vedere: la risposta e' sempre «ok».
 */
try {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals($_SESSION['content_v2_csrf'] ?? '', $token)) {
        cv2_ok();
    }

    $input = cv2_input();
    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));

    $ids = is_array($input['ids'] ?? null) ? $input['ids'] : [];
    $ids[] = (int)($input['id'] ?? 0);

    cm_count_views($mysqli, $type, $ids, $currentUser);
    cv2_ok();
} catch (Throwable $e) {
    error_log('[community] visite: ' . $e->getMessage());
    cv2_ok();
}
