<?php
require_once __DIR__ . '/bootstrap.php';
try {
    if (!admin_table_exists($mysqli, 'admin_logs')) admin_ok(['logs' => []]);
    $stmt = $mysqli->prepare("\n        SELECT l.id, l.admin_id, l.target_user_id, l.action, l.details, l.ip_address, l.created_at,\n               a.username AS admin_username, t.username AS target_username\n        FROM admin_logs l\n        LEFT JOIN utenti a ON a.id = l.admin_id\n        LEFT JOIN utenti t ON t.id = l.target_user_id\n        ORDER BY l.created_at DESC\n        LIMIT 80\n    ");
    if (!$stmt) admin_fail('Query log non valida.', 500);
    $stmt->execute();
    $res = $stmt->get_result();
    $logs = [];
    while ($row = $res->fetch_assoc()) {
        $row['details_parsed'] = $row['details'] ? json_decode($row['details'], true) : null;
        $logs[] = $row;
    }
    $stmt->close();
    admin_ok(['logs' => $logs]);
} catch (Throwable $e) { admin_fail('Errore caricamento log.', 500); }
