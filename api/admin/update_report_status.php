<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $input = admin_input();
    $source = (string)($input['source'] ?? '');
    $id = (int)($input['id'] ?? 0);
    $status = (string)($input['status'] ?? 'open');

    if ($id <= 0) admin_fail('ID segnalazione non valido.');
    if (!in_array($source, ['content', 'chat'], true)) admin_fail('Tipo segnalazione non valido.');
    if (!in_array($status, ['open', 'reviewed', 'dismissed'], true)) admin_fail('Stato non valido.');

    if ($source === 'content') {
        if (!admin_table_exists($mysqli, 'content_reports')) admin_fail('Tabella content_reports mancante.', 500);

        $sets = ['status = ?'];
        $types = 's';
        $params = [$status];

        if (admin_column_exists($mysqli, 'content_reports', 'reviewed_at')) {
            $sets[] = 'reviewed_at = ' . ($status === 'open' ? 'NULL' : 'NOW()');
        }
        if (admin_column_exists($mysqli, 'content_reports', 'reviewed_by')) {
            $sets[] = 'reviewed_by = ?';
            $types .= 'i';
            $params[] = $status === 'open' ? null : (int)$adminUser['id'];
        }

        $params[] = $id;
        $types .= 'i';

        $stmt = $mysqli->prepare('UPDATE content_reports SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1');
        if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Query aggiornamento segnalazione non valida.'), 500);
        $stmt->bind_param($types, ...$params);
    } else {
        if (!admin_table_exists($mysqli, 'chat_reports')) admin_fail('Tabella chat_reports mancante.', 500);

        $stmt = $mysqli->prepare('UPDATE chat_reports SET status = ? WHERE id = ? LIMIT 1');
        if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Query aggiornamento segnalazione chat non valida.'), 500);
        $stmt->bind_param('si', $status, $id);
    }

    if (!$stmt->execute()) admin_fail('Non sono riuscito ad aggiornare la segnalazione.', 500);
    $stmt->close();

    admin_log($mysqli, (int)$adminUser['id'], 'update_report_status', null, ['source' => $source, 'report_id' => $id, 'status' => $status]);

    admin_ok(['message' => 'Segnalazione aggiornata.']);
} catch (Throwable $e) {
    admin_fail('Errore aggiornamento segnalazione. Dettaglio: ' . $e->getMessage(), 500);
}
