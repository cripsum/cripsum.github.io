<?php
require_once __DIR__ . '/bootstrap.php';

try {
    if (!admin_table_exists($mysqli, 'achievement')) {
        admin_ok(['achievements' => [], 'pagination' => ['page' => 1, 'pages' => 1, 'total' => 0]]);
    }

    $q = trim((string)($_GET['q'] ?? ''));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(80, max(10, (int)($_GET['limit'] ?? 30)));
    $offset = ($page - 1) * $limit;
    $cols = admin_achievement_columns($mysqli);
    $nameCol = $cols['name'];

    $where = '';
    $params = [];
    $types = '';

    if ($q !== '' && $nameCol) {
        $where = 'WHERE ' . admin_qcol($nameCol) . ' LIKE ?';
        $params[] = '%' . $q . '%';
        $types = 's';
    }

    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM achievement $where");
    if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Query conteggio achievement non valida.'), 500);
    if ($types) $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Impossibile contare gli achievement.', 500);
    $total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $select = 'id';
    $select .= $nameCol ? ', ' . admin_qcol($nameCol) . ' AS nome' : ", CONCAT('Achievement #', id) AS nome";
    $select .= $cols['description'] ? ', ' . admin_qcol($cols['description']) . ' AS descrizione' : ', NULL AS descrizione';
    $select .= $cols['image'] ? ', ' . admin_qcol($cols['image']) . ' AS img_url' : ', NULL AS img_url';
    $select .= $cols['points'] ? ', ' . admin_qcol($cols['points']) . ' AS punti' : ', 0 AS punti';

    $order = $nameCol ? admin_qcol($nameCol) : 'id';
    $stmt = $mysqli->prepare("SELECT $select FROM achievement $where ORDER BY $order ASC LIMIT $limit OFFSET $offset");
    if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Impossibile preparare la lista achievement.'), 500);
    if ($types) $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Impossibile caricare gli achievement.', 500);

    $res = $stmt->get_result();
    $achievements = [];
    while ($row = $res->fetch_assoc()) {
        $row['image_url'] = admin_asset_url($row['img_url'] ?? null);
        $achievements[] = $row;
    }
    $stmt->close();

    admin_ok([
        'achievements' => $achievements,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => max(1, (int)ceil($total / $limit))]
    ]);
} catch (Throwable $e) {
    admin_fail('Errore caricamento achievement. Dettaglio: ' . $e->getMessage(), 500);
}
