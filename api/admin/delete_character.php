<?php
require_once __DIR__ . '/bootstrap.php';
try {
    $input = admin_input();
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) admin_fail('ID personaggio non valido.');
    if (admin_table_exists($mysqli, 'utenti_personaggi')) {
        $stmt = $mysqli->prepare('DELETE FROM utenti_personaggi WHERE personaggio_id = ?');
        if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
    }
    $stmt = $mysqli->prepare('DELETE FROM personaggi WHERE id = ? LIMIT 1');
    if (!$stmt) admin_fail('Query eliminazione personaggio non valida.', 500);
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) admin_fail('Non sono riuscito a eliminare il personaggio.', 500);
    $stmt->close();
    admin_log($mysqli, (int)$adminUser['id'], 'delete_character', null, ['character_id' => $id]);
    admin_ok(['message' => 'Personaggio eliminato.']);
} catch (Throwable $e) { admin_fail('Errore eliminazione personaggio.', 500); }
