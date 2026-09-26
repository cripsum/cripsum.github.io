<?php
require_once __DIR__ . '/bootstrap.php';
try {
    $input = admin_input();
    $userId = (int)($input['user_id'] ?? 0);
    $achievementId = (int)($input['achievement_id'] ?? 0);
    if ($userId <= 0 || $achievementId <= 0) admin_fail('Dati non validi.');
    $target = admin_fetch_user($mysqli, $userId);
    if (!$target) admin_fail('Utente non trovato.', 404);
    if (!admin_can_manage_user($adminUser, $target, true)) admin_fail('Non puoi modificare questi achievement.', 403);

    $stmt = $mysqli->prepare('DELETE FROM utenti_achievement WHERE utente_id = ? AND achievement_id = ?');
    if (!$stmt) admin_fail('Query rimozione non valida.', 500);
    $stmt->bind_param('ii', $userId, $achievementId);
    if (!$stmt->execute()) admin_fail('Non sono riuscito a rimuovere l’achievement.', 500);
    $stmt->close();
    $nameCol = admin_achievement_columns($mysqli)['name'];
    $name = null;
    if ($nameCol && ($stmt = $mysqli->prepare('SELECT ' . admin_qcol($nameCol) . ' AS nome FROM achievement WHERE id = ? LIMIT 1'))) {
        $stmt->bind_param('i', $achievementId);
        $stmt->execute();
        $name = $stmt->get_result()->fetch_assoc()['nome'] ?? null;
        $stmt->close();
    }
    admin_log($mysqli, (int)$adminUser['id'], 'remove_achievement_from_user', $userId, array_filter(['achievement' => $name, 'achievement_id' => $achievementId], static fn($v) => $v !== null));
    admin_ok(['message' => 'Achievement rimosso.']);
} catch (Throwable $e) { admin_fail('Errore rimozione achievement.', 500); }
