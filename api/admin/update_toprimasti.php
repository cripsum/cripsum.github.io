<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $input = admin_input();
    $id = (int)($input['id'] ?? 0);
    $title = trim((string)($input['titolo'] ?? ''));
    $description = trim((string)($input['descrizione'] ?? ''));
    $motivation = trim((string)($input['motivazione'] ?? ''));

    if ($id <= 0) admin_fail('ID Top Rimasti non valido.');
    if ($title === '' || mb_strlen($title) > 120) admin_fail('Titolo non valido.');
    if (mb_strlen($description) > 2000) admin_fail('Descrizione troppo lunga.');
    if (mb_strlen($motivation) > 2000) admin_fail('Motivazione troppo lunga.');

    $stmt = $mysqli->prepare("UPDATE toprimasti SET titolo = ?, descrizione = ?, motivazione = ? WHERE id = ? LIMIT 1");
    if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Query modifica Top Rimasti non valida.'), 500);
    $stmt->bind_param('sssi', $title, $description, $motivation, $id);
    if (!$stmt->execute()) admin_fail('Non sono riuscito a modificare il post.', 500);
    $stmt->close();

    admin_log($mysqli, (int)$adminUser['id'], 'update_toprimasti', null, ['post_id' => $id, 'title' => $title]);
    admin_ok(['message' => 'Top Rimasti aggiornato.']);
} catch (Throwable $e) {
    admin_fail('Errore modifica Top Rimasti. Dettaglio: ' . $e->getMessage(), 500);
}
