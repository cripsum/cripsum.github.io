<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $type = cv2_normalize_type((string)($_GET['type'] ?? 'shitpost'));
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) cv2_fail('ID non valido.');

    if ($type === 'shitpost') {
        if (!cv2_table_exists($mysqli, 'commenti_shitpost')) cv2_ok(['comments' => []]);

        $stmt = $mysqli->prepare("
            SELECT c.id, c.id_utente, c.commento, c.data_commento AS created_at, u.username
            FROM commenti_shitpost c
            LEFT JOIN utenti u ON u.id = c.id_utente
            WHERE c.id_shitpost = ?
            ORDER BY c.data_commento DESC
            LIMIT 120
        ");
        if (!$stmt) cv2_fail('Query commenti non valida.', 500);
        $stmt->bind_param('i', $id);
    } else {
        if (!cv2_table_exists($mysqli, 'content_comments')) cv2_ok(['comments' => []]);

        $stmt = $mysqli->prepare("
            SELECT c.id, c.user_id AS id_utente, c.comment AS commento, c.created_at, u.username
            FROM content_comments c
            LEFT JOIN utenti u ON u.id = c.user_id
            WHERE c.content_type = 'rimasto' AND c.post_id = ?
            ORDER BY c.created_at DESC
            LIMIT 120
        ");
        if (!$stmt) cv2_fail('Query commenti non valida.', 500);
        $stmt->bind_param('i', $id);
    }

    if (!$stmt->execute()) cv2_fail('Errore caricamento commenti.', 500);
    $res = $stmt->get_result();
    $comments = [];
    while ($row = $res->fetch_assoc()) $comments[] = $row;
    $stmt->close();

    cv2_ok(['comments' => $comments]);
} catch (Throwable $e) {
    cv2_fail('Errore commenti: ' . $e->getMessage(), 500);
}
