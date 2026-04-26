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

    $stmt = $mysqli->prepare('SELECT id FROM achievement WHERE id = ? LIMIT 1');
    if (!$stmt) admin_fail('Tabella achievement non disponibile.', 500);
    $stmt->bind_param('i', $achievementId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$exists) admin_fail('Achievement non trovato.', 404);

    $hasData = admin_column_exists($mysqli, 'utenti_achievement', 'data');
    $stmt = $mysqli->prepare('SELECT 1 FROM utenti_achievement WHERE utente_id = ? AND achievement_id = ? LIMIT 1');
    $stmt->bind_param('ii', $userId, $achievementId);
    $stmt->execute();
    $already = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($already) admin_ok(['message' => 'Achievement già assegnato.']);

    if ($hasData) {
        $stmt = $mysqli->prepare('INSERT INTO utenti_achievement (utente_id, achievement_id, data) VALUES (?, ?, NOW())');
    } else {
        $stmt = $mysqli->prepare('INSERT INTO utenti_achievement (utente_id, achievement_id) VALUES (?, ?)');
    }
    if (!$stmt) admin_fail('Query assegnazione non valida.', 500);
    $stmt->bind_param('ii', $userId, $achievementId);
    if (!$stmt->execute()) admin_fail('Non sono riuscito ad assegnare l’achievement.', 500);
    $stmt->close();
    admin_log($mysqli, (int)$adminUser['id'], 'add_achievement_to_user', $userId, ['achievement_id' => $achievementId]);
    admin_ok(['message' => 'Achievement assegnato.']);
} catch (Throwable $e) { admin_fail('Errore assegnazione achievement.', 500); }
