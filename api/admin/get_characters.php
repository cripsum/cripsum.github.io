<?php
require_once __DIR__ . '/bootstrap.php';
try {
    if (!admin_table_exists($mysqli, 'personaggi')) admin_ok(['characters' => [], 'pagination' => ['page' => 1, 'pages' => 1, 'total' => 0]]);
    $q = trim((string)($_GET['q'] ?? ''));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(80, max(10, (int)($_GET['limit'] ?? 30)));
    $offset = ($page - 1) * $limit;
    $cols = admin_character_columns($mysqli);
    $nameCol = $cols['name'] ?: 'nome';
    $where = '';
    $params = [];
    $types = '';
    if ($q !== '') { $where = 'WHERE ' . admin_qcol($nameCol) . ' LIKE ?'; $params[] = '%' . $q . '%'; $types = 's'; }

    $countSql = "SELECT COUNT(*) AS total FROM personaggi $where";
    $stmt = $mysqli->prepare($countSql);
    if (!$stmt) admin_fail('Query personaggi non valida.', 500);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $select = 'id, ' . admin_qcol($nameCol) . ' AS nome';
    $select .= $cols['image'] ? ', ' . admin_qcol($cols['image']) . ' AS img_url' : ', NULL AS img_url';
    $select .= $cols['rarity'] ? ', ' . admin_qcol($cols['rarity']) . ' AS rarita' : ', NULL AS rarita';
    $select .= $cols['audio'] ? ', ' . admin_qcol($cols['audio']) . ' AS audio_url' : ', NULL AS audio_url';
    $select .= $cols['category'] ? ', ' . admin_qcol($cols['category']) . ' AS categoria' : ', NULL AS categoria';
    $params2 = $params; $params2[] = $limit; $params2[] = $offset;
    $types2 = $types . 'ii';
    $stmt = $mysqli->prepare("SELECT $select FROM personaggi $where ORDER BY " . admin_qcol($nameCol) . " ASC LIMIT ? OFFSET ?");
    if (!$stmt) admin_fail('Impossibile caricare i personaggi.', 500);
    $stmt->bind_param($types2, ...$params2);
    $stmt->execute();
    $res = $stmt->get_result();
    $characters = [];
    while ($row = $res->fetch_assoc()) $characters[] = $row;
    $stmt->close();
    admin_ok(['characters' => $characters, 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => max(1, (int)ceil($total / $limit))]]);
} catch (Throwable $e) { admin_fail('Errore caricamento personaggi.', 500); }
