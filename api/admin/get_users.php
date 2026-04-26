<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $q = trim((string)($_GET['q'] ?? ''));
    $status = (string)($_GET['status'] ?? 'all');
    $role = (string)($_GET['role'] ?? 'all');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(60, max(10, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $sort = (string)($_GET['sort'] ?? 'data_creazione');
    $dir = strtoupper((string)($_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

    $allowedSort = ['id' => 'u.id', 'username' => 'u.username', 'email' => 'u.email', 'data_creazione' => 'u.data_creazione', 'ruolo' => 'u.ruolo', 'isBannato' => 'u.isBannato'];
    $orderBy = $allowedSort[$sort] ?? 'u.data_creazione';

    $where = [];
    $params = [];
    $types = '';

    if ($q !== '') {
        $where[] = '(u.username LIKE ? OR u.email LIKE ? OR CAST(u.id AS CHAR) = ?)';
        $like = '%' . $q . '%';
        $params[] = $like; $params[] = $like; $params[] = $q;
        $types .= 'sss';
    }
    if ($status === 'active') $where[] = 'u.isBannato = 0';
    if ($status === 'banned') $where[] = 'u.isBannato = 1';
    if (in_array($role, ['utente', 'admin', 'owner'], true)) {
        $where[] = 'u.ruolo = ?';
        $params[] = $role;
        $types .= 's';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countSql = "SELECT COUNT(*) AS total FROM utenti u $whereSql";
    $stmt = $mysqli->prepare($countSql);
    if (!$stmt) admin_fail('Query utenti non valida.', 500);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $selectExtra = '';
    foreach (['motivo_ban', 'banned_at', 'banned_by', 'updated_at', 'email_verificata'] as $column) {
        if (admin_column_exists($mysqli, 'utenti', $column)) $selectExtra .= ", u.$column";
    }

    $characterCountSql = admin_table_exists($mysqli, 'utenti_personaggi') ? "(SELECT COUNT(DISTINCT up.personaggio_id) FROM utenti_personaggi up WHERE up.utente_id = u.id)" : "0";
    $qtyCol = admin_inventory_quantity_column($mysqli);
    $pullCountSql = admin_table_exists($mysqli, 'utenti_personaggi') && $qtyCol ? "(SELECT COALESCE(SUM(up." . admin_qcol($qtyCol) . "), 0) FROM utenti_personaggi up WHERE up.utente_id = u.id)" : $characterCountSql;
    $achievementCountSql = admin_table_exists($mysqli, 'utenti_achievement') ? "(SELECT COUNT(DISTINCT ua.achievement_id) FROM utenti_achievement ua WHERE ua.utente_id = u.id)" : "0";

    $sql = "
        SELECT u.id, u.username, u.email, u.data_creazione, u.ruolo, u.isBannato $selectExtra,
               $characterCountSql AS character_count,
               $pullCountSql AS pull_count,
               $achievementCountSql AS achievement_count
        FROM utenti u
        $whereSql
        ORDER BY $orderBy $dir
        LIMIT ? OFFSET ?
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) admin_fail('Impossibile caricare gli utenti.', 500);
    $params2 = $params;
    $params2[] = $limit; $params2[] = $offset;
    $types2 = $types . 'ii';
    $stmt->bind_param($types2, ...$params2);
    $stmt->execute();
    $res = $stmt->get_result();
    $users = [];
    while ($row = $res->fetch_assoc()) {
        $row['avatar_url'] = admin_avatar_url((int)$row['id']);
        $users[] = $row;
    }
    $stmt->close();

    admin_ok(['users' => $users, 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => max(1, (int)ceil($total / $limit))]]);
} catch (Throwable $e) {
    admin_fail('Errore caricamento utenti.', 500);
}
