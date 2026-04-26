<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $input = admin_input();
    $userId = (int)($input['id'] ?? 0);
    if ($userId <= 0) admin_fail('ID utente non valido.');

    $target = admin_fetch_user($mysqli, $userId);
    if (!$target) admin_fail('Utente non trovato.', 404);
    if (!admin_can_manage_user($adminUser, $target, true)) admin_fail('Non puoi sbannare questo utente.', 403);

    $sets = ['isBannato = 0'];
    if (admin_column_exists($mysqli, 'utenti', 'motivo_ban')) $sets[] = 'motivo_ban = NULL';
    if (admin_column_exists($mysqli, 'utenti', 'banned_at')) $sets[] = 'banned_at = NULL';
    if (admin_column_exists($mysqli, 'utenti', 'banned_by')) $sets[] = 'banned_by = NULL';
    if (admin_column_exists($mysqli, 'utenti', 'updated_at')) $sets[] = 'updated_at = NOW()';

    $stmt = $mysqli->prepare('UPDATE utenti SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1');
    if (!$stmt) admin_fail('Query unban non valida.', 500);
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) admin_fail('Non sono riuscito a sbannare l’utente.', 500);
    $stmt->close();

    admin_log($mysqli, (int)$adminUser['id'], 'unban_user', $userId);
    admin_ok(['message' => 'Utente sbannato.']);
} catch (Throwable $e) {
    admin_fail('Errore unban utente.', 500);
}
