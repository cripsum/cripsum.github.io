<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $input = admin_input();
    $id = (int)($input['id'] ?? 0);
    $title = trim((string)($input['titolo'] ?? ''));
    $description = trim((string)($input['descrizione'] ?? ''));
    $tag = trim((string)($input['tag'] ?? ''));
    $isSpoiler = (int)($input['is_spoiler'] ?? 0) === 1 ? 1 : 0;

    if ($id <= 0) admin_fail('ID shitpost non valido.');
    if ($title === '' || mb_strlen($title) > 120) admin_fail('Titolo non valido.');
    if (mb_strlen($description) > 2000) admin_fail('Descrizione troppo lunga.');
    if (mb_strlen($tag) > 40) admin_fail('Tag troppo lungo.');

    $sets = ['titolo = ?', 'descrizione = ?'];
    $types = 'ss';
    $params = [$title, $description];

    if (admin_column_exists($mysqli, 'shitposts', 'tag')) {
        $sets[] = '`tag` = ?';
        $types .= 's';
        $params[] = $tag !== '' ? $tag : null;
    }

    if (admin_column_exists($mysqli, 'shitposts', 'is_spoiler')) {
        $sets[] = '`is_spoiler` = ?';
        $types .= 'i';
        $params[] = $isSpoiler;
    }

    if (admin_column_exists($mysqli, 'shitposts', 'updated_at')) {
        $sets[] = '`updated_at` = NOW()';
    }

    $params[] = $id;
    $types .= 'i';

    $stmt = $mysqli->prepare('UPDATE shitposts SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1');
    if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Query modifica shitpost non valida.'), 500);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Non sono riuscito a modificare lo shitpost.', 500);
    $stmt->close();

    admin_log($mysqli, (int)$adminUser['id'], 'update_shitpost', null, ['post_id' => $id, 'title' => $title]);
    admin_ok(['message' => 'Shitpost aggiornato.']);
} catch (Throwable $e) {
    admin_fail('Errore modifica shitpost. Dettaglio: ' . $e->getMessage(), 500);
}
