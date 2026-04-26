<?php
require_once __DIR__ . '/bootstrap.php';

try {
    cv2_check_csrf();

    $user = cv2_require_login($mysqli);
    $input = cv2_input();
    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $id = (int)($input['id'] ?? 0);

    if ($id <= 0) cv2_fail('ID non valido.');

    if ($type === 'rimasto') {
        if (!cv2_table_exists($mysqli, 'voti_toprimasti')) cv2_fail('Tabella voti mancante.', 500);

        $check = $mysqli->prepare("SELECT id FROM voti_toprimasti WHERE id_utente = ? AND id_post = ? LIMIT 1");
        if (!$check) cv2_fail('Query voto non valida.', 500);
        $check->bind_param('ii', $user['id'], $id);
        $check->execute();
        $has = $check->get_result()->num_rows > 0;
        $check->close();

        $mysqli->begin_transaction();

        if ($has) {
            $stmt = $mysqli->prepare("DELETE FROM voti_toprimasti WHERE id_utente = ? AND id_post = ?");
            $stmt->bind_param('ii', $user['id'], $id);
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare("UPDATE toprimasti SET reazioni = GREATEST(0, COALESCE(reazioni, 0) - 1) WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $active = false;
        } else {
            $stmt = $mysqli->prepare("INSERT INTO voti_toprimasti (id_utente, id_post, data_voto) VALUES (?, ?, NOW())");
            $stmt->bind_param('ii', $user['id'], $id);
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare("UPDATE toprimasti SET reazioni = COALESCE(reazioni, 0) + 1 WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $active = true;
        }

        $stmt = $mysqli->prepare("SELECT COALESCE(reazioni, 0) AS score FROM toprimasti WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $score = (int)($stmt->get_result()->fetch_assoc()['score'] ?? 0);
        $stmt->close();

        $mysqli->commit();

        cv2_ok(['active' => $active, 'score' => $score]);
    }

    if (!cv2_table_exists($mysqli, 'shitpost_likes')) cv2_fail('Tabella like mancante. Esegui SQL upgrade.', 500);

    $check = $mysqli->prepare("SELECT id FROM shitpost_likes WHERE id_utente = ? AND id_shitpost = ? LIMIT 1");
    if (!$check) cv2_fail('Query like non valida.', 500);
    $check->bind_param('ii', $user['id'], $id);
    $check->execute();
    $has = $check->get_result()->num_rows > 0;
    $check->close();

    if ($has) {
        $stmt = $mysqli->prepare("DELETE FROM shitpost_likes WHERE id_utente = ? AND id_shitpost = ?");
        $stmt->bind_param('ii', $user['id'], $id);
        $stmt->execute();
        $stmt->close();
        $active = false;
    } else {
        $stmt = $mysqli->prepare("INSERT INTO shitpost_likes (id_utente, id_shitpost, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param('ii', $user['id'], $id);
        $stmt->execute();
        $stmt->close();
        $active = true;
    }

    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM shitpost_likes WHERE id_shitpost = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $score = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    cv2_ok(['active' => $active, 'score' => $score]);
} catch (Throwable $e) {
    @$mysqli->rollback();
    cv2_fail('Errore reazione: ' . $e->getMessage(), 500);
}
