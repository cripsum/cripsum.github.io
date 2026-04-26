<?php
require_once __DIR__ . '/bootstrap.php';

if (!admin_validate_csrf($_GET['csrf_token'] ?? '')) {
    http_response_code(419);
    echo 'Sessione scaduta';
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="cripsum-users-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['id', 'username', 'email', 'ruolo', 'isBannato', 'data_creazione']);
$stmt = $mysqli->prepare("SELECT id, username, email, ruolo, isBannato, data_creazione FROM utenti ORDER BY id ASC");
if ($stmt && $stmt->execute()) {
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) fputcsv($out, $row);
    $stmt->close();
}
fclose($out);
exit;
