<?php
require_once __DIR__ . '/bootstrap.php';

try {
    cv2_check_csrf();
    $user = cv2_require_login($mysqli);
    cv2_rate_limit('cv2_last_report', 20, 'Stai segnalando troppo velocemente.');

    if (!cv2_table_exists($mysqli, 'content_reports')) cv2_fail('Tabella segnalazioni mancante. Esegui SQL upgrade.', 500);

    $input = cv2_input();
    $type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
    $id = (int)($input['id'] ?? 0);
    $reason = cv2_validate_text((string)($input['reason'] ?? ''), 500, 'Motivo');

    if ($id <= 0) cv2_fail('ID non valido.');
    if ($reason === '') cv2_fail('Inserisci un motivo.');

    $stmt = $mysqli->prepare("
        INSERT INTO content_reports (content_type, post_id, user_id, reason, status, created_at)
        VALUES (?, ?, ?, ?, 'open', NOW())
        ON DUPLICATE KEY UPDATE reason = VALUES(reason), status = 'open', created_at = NOW()
    ");
    if (!$stmt) cv2_fail('Query segnalazione non valida.', 500);

    $stmt->bind_param('siis', $type, $id, $user['id'], $reason);
    $stmt->execute();
    $stmt->close();

    cv2_ok(['message' => 'Segnalazione inviata.']);
} catch (Throwable $e) {
    cv2_fail('Errore segnalazione: ' . $e->getMessage(), 500);
}
