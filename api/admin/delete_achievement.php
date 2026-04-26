<?php
require_once __DIR__ . '/bootstrap.php';
try {
    $input = admin_input();
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) admin_fail('ID achievement non valido.');
    if (admin_table_exists($mysqli, 'utenti_achievement')) {
        $stmt = $mysqli->prepare('DELETE FROM utenti_achievement WHERE achievement_id = ?');
        if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
    }
    if (admin_table_exists($mysqli, 'utenti_profile_badges')) {
        $stmt = $mysqli->prepare('DELETE FROM utenti_profile_badges WHERE achievement_id = ?');
        if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
    }
    $stmt = $mysqli->prepare('DELETE FROM achievement WHERE id = ? LIMIT 1');
    if (!$stmt) admin_fail('Query eliminazione achievement non valida.', 500);
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) admin_fail('Non sono riuscito a eliminare l’achievement.', 500);
    $stmt->close();
    admin_log($mysqli, (int)$adminUser['id'], 'delete_achievement', null, ['achievement_id' => $id]);
    admin_ok(['message' => 'Achievement eliminato.']);
} catch (Throwable $e) { admin_fail('Errore eliminazione achievement.', 500); }
