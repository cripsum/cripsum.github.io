<?php
require_once __DIR__ . '/bootstrap.php';

try {
    if (!admin_table_exists($mysqli, 'utenti')) {
        admin_fail('Tabella utenti non trovata.', 500);
    }

    $q = trim((string)($_GET['q'] ?? ''));
    $status = (string)($_GET['status'] ?? 'all');
    $role = (string)($_GET['role'] ?? 'all');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(60, max(10, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $sort = (string)($_GET['sort'] ?? 'data_creazione');
    $dir = strtoupper((string)($_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

    $hasEmail = admin_column_exists($mysqli, 'utenti', 'email');
    $hasRole = admin_column_exists($mysqli, 'utenti', 'ruolo');
    $hasBan = admin_column_exists($mysqli, 'utenti', 'isBannato');
    $hasCreated = admin_column_exists($mysqli, 'utenti', 'data_creazione');

    $allowedSort = ['id' => 'u.id', 'username' => 'u.username'];
    if ($hasEmail) $allowedSort['email'] = 'u.email';
    if ($hasCreated) $allowedSort['data_creazione'] = 'u.data_creazione';
    if ($hasRole) $allowedSort['ruolo'] = 'u.ruolo';
    if ($hasBan) $allowedSort['isBannato'] = 'u.isBannato';
    $orderBy = $allowedSort[$sort] ?? ($hasCreated ? 'u.data_creazione' : 'u.id');

    $where = [];
    $params = [];
    $types = '';

    if ($q !== '') {
        $searchParts = ['u.username LIKE ?', 'CAST(u.id AS CHAR) = ?'];
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $q;
        $types .= 'ss';
        if ($hasEmail) {
            array_splice($searchParts, 1, 0, 'u.email LIKE ?');
            array_splice($params, 1, 0, [$like]);
            $types = 'sss';
        }
        $where[] = '(' . implode(' OR ', $searchParts) . ')';
    }

    if ($hasBan && $status === 'active') $where[] = 'u.isBannato = 0';
    if ($hasBan && $status === 'banned') $where[] = 'u.isBannato = 1';

    if ($hasRole && in_array($role, ['utente', 'admin', 'owner'], true)) {
        $where[] = 'u.ruolo = ?';
        $params[] = $role;
        $types .= 's';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM utenti u $whereSql");
    if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Query conteggio utenti non valida.'), 500);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Impossibile contare gli utenti.', 500);
    $total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $select = 'u.id, u.username';
    $select .= $hasEmail ? ', u.email' : ", '' AS email";
    $select .= $hasCreated ? ', u.data_creazione' : ', NULL AS data_creazione';
    $select .= $hasRole ? ', u.ruolo' : ", 'utente' AS ruolo";
    $select .= $hasBan ? ', u.isBannato' : ', 0 AS isBannato';

    foreach (['motivo_ban', 'banned_at', 'banned_by', 'updated_at', 'email_verificata'] as $column) {
        if (admin_column_exists($mysqli, 'utenti', $column)) {
            $select .= ', u.' . admin_qcol($column);
        }
    }

    $characterCountSql = admin_table_exists($mysqli, 'utenti_personaggi') ? "(SELECT COUNT(DISTINCT up.personaggio_id) FROM utenti_personaggi up WHERE up.utente_id = u.id)" : "0";
    $qtyCol = admin_inventory_quantity_column($mysqli);
    $pullCountSql = admin_table_exists($mysqli, 'utenti_personaggi') && $qtyCol
        ? "(SELECT COALESCE(SUM(up." . admin_qcol($qtyCol) . "), 0) FROM utenti_personaggi up WHERE up.utente_id = u.id)"
        : $characterCountSql;
    $achievementCountSql = admin_table_exists($mysqli, 'utenti_achievement') ? "(SELECT COUNT(DISTINCT ua.achievement_id) FROM utenti_achievement ua WHERE ua.utente_id = u.id)" : "0";

    $sql = "
        SELECT $select,
               $characterCountSql AS character_count,
               $pullCountSql AS pull_count,
               $achievementCountSql AS achievement_count
        FROM utenti u
        $whereSql
        ORDER BY $orderBy $dir
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Impossibile preparare la lista utenti.'), 500);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Impossibile caricare gli utenti.', 500);

    $res = $stmt->get_result();
    $users = [];
    while ($row = $res->fetch_assoc()) {
        $row['avatar_url'] = admin_avatar_url((int)$row['id']);
        $users[] = $row;
    }
    $stmt->close();

    admin_ok([
        'users' => $users,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => max(1, (int)ceil($total / $limit))]
    ]);
} catch (Throwable $e) {
    admin_fail('Errore caricamento utenti. Dettaglio: ' . $e->getMessage(), 500);
}
