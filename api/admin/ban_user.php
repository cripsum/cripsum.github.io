<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $input = admin_input();
    $userId = (int)($input['id'] ?? 0);
    $reason = trim((string)($input['reason'] ?? ''));
    if ($userId <= 0) admin_fail('ID utente non valido.');
    if ($reason !== '' && mb_strlen($reason) > 255) admin_fail('Motivo troppo lungo.');

    $target = admin_fetch_user($mysqli, $userId);
    if (!$target) admin_fail('Utente non trovato.', 404);
    if ((int)$adminUser['id'] === $userId) admin_fail('Non puoi bannare te stesso.', 403);
    if (!admin_can_manage_user($adminUser, $target, false)) admin_fail('Non puoi bannare questo utente.', 403);

    $sets = ['isBannato = 1'];
    $types = '';
    $params = [];
    if (admin_column_exists($mysqli, 'utenti', 'motivo_ban')) { $sets[] = 'motivo_ban = ?'; $params[] = $reason ?: null; $types .= 's'; }
    if (admin_column_exists($mysqli, 'utenti', 'banned_at')) $sets[] = 'banned_at = NOW()';
    if (admin_column_exists($mysqli, 'utenti', 'banned_by')) { $sets[] = 'banned_by = ?'; $params[] = (int)$adminUser['id']; $types .= 'i'; }
    if (admin_column_exists($mysqli, 'utenti', 'updated_at')) $sets[] = 'updated_at = NOW()';

    $sql = 'UPDATE utenti SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1';
    $params[] = $userId; $types .= 'i';
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) admin_fail('Query ban non valida.', 500);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Non sono riuscito a bannare l’utente.', 500);
    $stmt->close();

    admin_log($mysqli, (int)$adminUser['id'], 'ban_user', $userId, ['reason' => $reason]);
    admin_ok(['message' => 'Utente bannato.']);
} catch (Throwable $e) {
    admin_fail('Errore ban utente.', 500);
}
