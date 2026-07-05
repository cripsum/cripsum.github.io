<?php
require_once __DIR__ . '/bootstrap.php';

try {
    if (!admin_table_exists($mysqli, 'personaggi')) {
        admin_ok(['characters' => [], 'pagination' => ['page' => 1, 'pages' => 1, 'total' => 0]]);
    }

    $q = trim((string)($_GET['q'] ?? ''));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(80, max(10, (int)($_GET['limit'] ?? 30)));
    $offset = ($page - 1) * $limit;
    $cols = admin_character_columns($mysqli);
    $nameCol = $cols['name'];

    $where = '';
    $params = [];
    $types = '';

    if ($q !== '' && $nameCol) {
        $where = 'WHERE ' . admin_qcol($nameCol) . ' LIKE ?';
        $params[] = '%' . $q . '%';
        $types = 's';
    }

    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM personaggi $where");
    if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Query conteggio personaggi non valida.'), 500);
    if ($types) $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Impossibile contare i personaggi.', 500);
    $total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $select = 'id';
    $select .= $nameCol ? ', ' . admin_qcol($nameCol) . ' AS nome' : ", CONCAT('Personaggio #', id) AS nome";
    $select .= $cols['description'] ? ', ' . admin_qcol($cols['description']) . ' AS descrizione' : ', NULL AS descrizione';
    $select .= $cols['description_en'] ? ', ' . admin_qcol($cols['description_en']) . ' AS descrizione_en' : ', NULL AS descrizione_en';
    $select .= $cols['features'] ? ', ' . admin_qcol($cols['features']) . ' AS caratteristiche' : ', NULL AS caratteristiche';
    $select .= $cols['features_en'] ? ', ' . admin_qcol($cols['features_en']) . ' AS caratteristiche_en' : ', NULL AS caratteristiche_en';
    $select .= $cols['image'] ? ', ' . admin_qcol($cols['image']) . ' AS img_url' : ', NULL AS img_url';
    $select .= $cols['rarity'] ? ', ' . admin_qcol($cols['rarity']) . ' AS rarita' : ', NULL AS rarita';
    $select .= $cols['rarity_en'] ? ', ' . admin_qcol($cols['rarity_en']) . ' AS rarita_en' : ', NULL AS rarita_en';
    $select .= $cols['audio'] ? ', ' . admin_qcol($cols['audio']) . ' AS audio_url' : ', NULL AS audio_url';
    $select .= $cols['category'] ? ', ' . admin_qcol($cols['category']) . ' AS categoria' : ', NULL AS categoria';
    $select .= $cols['video_url'] ? ', ' . admin_qcol($cols['video_url']) . ' AS video_url' : ', NULL AS video_url';
    $select .= isset($cols['pool_evento']) && $cols['pool_evento'] ? ', ' . admin_qcol($cols['pool_evento']) . ' AS pool_evento' : ', 0 AS pool_evento';
    $select .= isset($cols['in_pool_standard']) && $cols['in_pool_standard'] ? ', ' . admin_qcol($cols['in_pool_standard']) . ' AS in_pool_standard' : ', 0 AS in_pool_standard';
    $select .= $cols['ruolo'] ? ', ' . admin_qcol($cols['ruolo']) . ' AS ruolo' : ', NULL AS ruolo';

    $order = $nameCol ? admin_qcol($nameCol) : 'id';
    $stmt = $mysqli->prepare("SELECT $select FROM personaggi $where ORDER BY $order ASC LIMIT $limit OFFSET $offset");
    if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Impossibile preparare la lista personaggi.'), 500);
    if ($types) $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Impossibile caricare i personaggi.', 500);

    $res = $stmt->get_result();
    $characters = [];
    while ($row = $res->fetch_assoc()) {
        $row['image_url'] = admin_asset_url($row['img_url'] ?? null);
        $characters[] = $row;
    }
    $stmt->close();

    admin_ok([
        'characters' => $characters,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => max(1, (int)ceil($total / $limit))]
    ]);
} catch (Throwable $e) {
    admin_fail('Errore caricamento personaggi. Dettaglio: ' . $e->getMessage(), 500);
}
