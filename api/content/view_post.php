<?php
require_once __DIR__ . '/bootstrap.php';

/*
 * Conta le visualizzazioni dei post.
 *
 * La pagina manda `ids` con tutti i post visti negli ultimi istanti, cosi' una
 * sola richiesta (e una sola connessione al database) copre tutto lo
 * scorrimento. `id` singolo resta accettato: lo mandano ancora le pagine
 * rimaste aperte con il vecchio script.
 */
try {
    $input = cv2_input();
    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $meta = cv2_meta($type);

    $ids = [];
    if (is_array($input['ids'] ?? null)) {
        foreach ($input['ids'] as $value) {
            $ids[] = (int)$value;
        }
    }
    $ids[] = (int)($input['id'] ?? 0);
    $ids = array_slice(array_values(array_unique(array_filter($ids, static fn($id) => $id > 0))), 0, 50);

    if (!$ids) cv2_ok();

    $hasViewsTable = cv2_table_exists($mysqli, 'content_views');
    $hasViewsColumn = cv2_column_exists($mysqli, $meta['table'], 'views');
    $userId = (int)($currentUser['id'] ?? 0) ?: null;
    $ip = cv2_client_ip();

    $insert = $hasViewsTable
        ? $mysqli->prepare("INSERT IGNORE INTO content_views (content_type, post_id, user_id, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())")
        : null;
    $update = $hasViewsColumn
        ? $mysqli->prepare('UPDATE ' . cv2_qcol($meta['table']) . ' SET `views` = COALESCE(`views`, 0) + 1 WHERE id = ? LIMIT 1')
        : null;

    foreach ($ids as $id) {
        if ($insert) {
            $insert->bind_param('siis', $type, $id, $userId, $ip);
            $insert->execute();

            // Gia' contata per questo utente/indirizzo: il contatore non sale.
            if ($insert->affected_rows <= 0) continue;
        }

        if ($update) {
            $update->bind_param('i', $id);
            $update->execute();
        }
    }

    if ($insert) $insert->close();
    if ($update) $update->close();

    cv2_ok();
} catch (Throwable $e) {
    cv2_ok();
}
