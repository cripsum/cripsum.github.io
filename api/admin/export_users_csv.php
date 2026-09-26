<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Metodo non consentito';
    exit;
}

$columns = ['id', 'username', 'email', 'ruolo', 'isBannato', 'data_creazione'];
foreach (['display_name', 'is_premium', 'email_verificata', 'ultimo_accesso', 'soldi', 'godoshards_balance', 'frammenti', 'banned_until', 'motivo_ban'] as $column) {
    if (admin_column_exists($mysqli, 'utenti', $column)) $columns[] = $column;
}

// Un valore che comincia con = + - @ viene preso per una formula da Excel e
// LibreOffice: l'apostrofo davanti lo lascia testo.
$cell = static function ($value) {
    $value = (string)($value ?? '');
    return preg_match('/^[=+\-@\t\r]/', $value) && !is_numeric($value) ? "'" . $value : $value;
};

header('Cache-Control: no-store, private');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="cripsum-users-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel altrimenti legge gli accenti come Latin-1
fputcsv($out, $columns);
$stmt = $mysqli->prepare('SELECT ' . implode(', ', array_map('admin_qcol', $columns)) . ' FROM utenti ORDER BY id ASC');
if ($stmt && $stmt->execute()) {
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) fputcsv($out, array_map($cell, $row));
    $stmt->close();
}
fclose($out);
admin_log($mysqli, (int)$adminUser['id'], 'export_users_csv', null, ['colonne' => count($columns)]);
exit;
