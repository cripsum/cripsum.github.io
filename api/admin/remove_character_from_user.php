<?php
require_once __DIR__ . '/bootstrap.php';
try {
    $input = admin_input();
    $userId = (int)($input['user_id'] ?? 0);
    $characterId = (int)($input['character_id'] ?? 0);
    if ($userId <= 0 || $characterId <= 0) admin_fail('Dati non validi.');
    $target = admin_fetch_user($mysqli, $userId);
    if (!$target) admin_fail('Utente non trovato.', 404);
    if (!admin_can_manage_user($adminUser, $target, true)) admin_fail('Non puoi modificare questo inventario.', 403);

    $stmt = $mysqli->prepare('DELETE FROM utenti_personaggi WHERE utente_id = ? AND personaggio_id = ?');
    if (!$stmt) admin_fail('Query rimozione non valida.', 500);
    $stmt->bind_param('ii', $userId, $characterId);
    if (!$stmt->execute()) admin_fail('Non sono riuscito a rimuovere il personaggio.', 500);
    $stmt->close();
    admin_log($mysqli, (int)$adminUser['id'], 'remove_character_from_user', $userId, ['character_id' => $characterId]);
    admin_ok(['message' => 'Personaggio rimosso.']);
} catch (Throwable $e) { admin_fail('Errore rimozione personaggio.', 500); }
