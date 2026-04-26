<?php
require_once __DIR__ . '/bootstrap.php';

try {
    cv2_check_csrf();
    $user = cv2_require_login($mysqli);
    $input = cv2_input();

    if (!cv2_table_exists($mysqli, 'content_saves')) cv2_fail('Tabella salvati mancante. Esegui SQL upgrade.', 500);

    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) cv2_fail('ID non valido.');

    $check = $mysqli->prepare("SELECT id FROM content_saves WHERE content_type = ? AND post_id = ? AND user_id = ? LIMIT 1");
    if (!$check) cv2_fail('Query salvati non valida.', 500);
    $check->bind_param('sii', $type, $id, $user['id']);
    $check->execute();
    $has = $check->get_result()->num_rows > 0;
    $check->close();

    if ($has) {
        $stmt = $mysqli->prepare("DELETE FROM content_saves WHERE content_type = ? AND post_id = ? AND user_id = ?");
        $stmt->bind_param('sii', $type, $id, $user['id']);
        $stmt->execute();
        $stmt->close();
        cv2_ok(['active' => false]);
    }

    $stmt = $mysqli->prepare("INSERT INTO content_saves (content_type, post_id, user_id, created_at) VALUES (?, ?, ?, NOW())");
    if (!$stmt) cv2_fail('Query salvataggio non valida.', 500);
    $stmt->bind_param('sii', $type, $id, $user['id']);
    $stmt->execute();
    $stmt->close();

    cv2_ok(['active' => true]);
} catch (Throwable $e) {
    cv2_fail('Errore salvataggio: ' . $e->getMessage(), 500);
}
