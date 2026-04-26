<?php
require_once __DIR__ . '/bootstrap.php';

try {
    cv2_check_csrf();

    $user = cv2_require_login($mysqli);
    if (!cv2_is_admin($user)) cv2_fail('Permessi insufficienti.', 403);

    $input = cv2_input();
    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $meta = cv2_meta($type);
    $id = (int)($input['id'] ?? 0);
    $approved = cv2_bool_int($input['approved'] ?? 0);

    if ($id <= 0) cv2_fail('ID non valido.');

    $table = cv2_qcol($meta['table']);
    $approvedCol = cv2_qcol($meta['approved']);

    $stmt = $mysqli->prepare("UPDATE $table SET $approvedCol = ? WHERE id = ? LIMIT 1");
    if (!$stmt) cv2_fail('Query approvazione non valida.', 500);

    $stmt->bind_param('ii', $approved, $id);
    if (!$stmt->execute()) cv2_fail('Non sono riuscito ad aggiornare lo stato.', 500);
    $stmt->close();

    cv2_ok(['message' => $approved ? 'Post approvato.' : 'Post nascosto.']);
} catch (Throwable $e) {
    cv2_fail('Errore approvazione: ' . $e->getMessage(), 500);
}
