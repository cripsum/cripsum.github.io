<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $input = admin_input();
    $userId = (int)($input['id'] ?? 0);
    $username = trim((string)($input['username'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $role = admin_normalize_role((string)($input['ruolo'] ?? 'utente'));

    if ($userId <= 0) admin_fail('ID utente non valido.');
    if (!admin_validate_username($username)) admin_fail('Username non valido. Usa 3-20 caratteri, lettere, numeri o underscore.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) admin_fail('Email non valida.');

    $target = admin_fetch_user($mysqli, $userId);
    if (!$target) admin_fail('Utente non trovato.', 404);

    $allowSelf = (int)$adminUser['id'] === $userId;
    if (!admin_can_manage_user($adminUser, $target, $allowSelf)) admin_fail('Non puoi modificare questo utente.', 403);
    if (!admin_can_set_role($adminUser, $target, $role)) admin_fail('Non puoi assegnare questo ruolo.', 403);

    $stmt = $mysqli->prepare("SELECT id FROM utenti WHERE username = ? AND id <> ? LIMIT 1");
    $stmt->bind_param('si', $username, $userId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($exists) admin_fail('Username già in uso.');

    $stmt = $mysqli->prepare("SELECT id FROM utenti WHERE email = ? AND id <> ? LIMIT 1");
    $stmt->bind_param('si', $email, $userId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($exists) admin_fail('Email già in uso.');

    $extra = admin_update_user_timestamp_sql($mysqli);
    $stmt = $mysqli->prepare("UPDATE utenti SET username = ?, email = ?, ruolo = ? $extra WHERE id = ? LIMIT 1");
    if (!$stmt) admin_fail('Query aggiornamento non valida.', 500);
    $stmt->bind_param('sssi', $username, $email, $role, $userId);
    if (!$stmt->execute()) admin_fail('Non sono riuscito ad aggiornare l’utente.', 500);
    $stmt->close();

    admin_log($mysqli, (int)$adminUser['id'], 'update_user', $userId, ['username' => $username, 'email' => $email, 'role' => $role]);
    admin_ok(['message' => 'Utente aggiornato.']);
} catch (Throwable $e) {
    admin_fail('Errore aggiornamento utente.', 500);
}
