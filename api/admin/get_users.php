<?php
require_once __DIR__ . '/bootstrap.php';

// Online e ultimo accesso si leggono come nella pagina del profilo: stessa
// colonna `utenti.ultimo_accesso` e stessa soglia, USER_ONLINE_WINDOW_SECONDS
// (includes/functions.php, caricato dal bootstrap).

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
    $onlineOnly = (string)($_GET['online'] ?? '') === '1';
    $premium = (string)($_GET['premium'] ?? 'all');

    $hasEmail = admin_column_exists($mysqli, 'utenti', 'email');
    $hasRole = admin_column_exists($mysqli, 'utenti', 'ruolo');
    $hasBan = admin_column_exists($mysqli, 'utenti', 'isBannato');
    $hasCreated = admin_column_exists($mysqli, 'utenti', 'data_creazione');
    $hasLastSeen = admin_column_exists($mysqli, 'utenti', 'ultimo_accesso');
    $hasPremium = admin_column_exists($mysqli, 'utenti', 'is_premium');
    $hasDisplayName = admin_column_exists($mysqli, 'utenti', 'display_name');
    $currencies = admin_user_currencies($mysqli);

    $allowedSort = ['id' => 'u.id', 'username' => 'u.username'];
    if ($hasEmail) $allowedSort['email'] = 'u.email';
    if ($hasCreated) $allowedSort['data_creazione'] = 'u.data_creazione';
    if ($hasRole) $allowedSort['ruolo'] = 'u.ruolo';
    if ($hasBan) $allowedSort['isBannato'] = 'u.isBannato';
    if ($hasLastSeen) $allowedSort['ultimo_accesso'] = 'u.ultimo_accesso';
    foreach (array_keys($currencies) as $column) $allowedSort[$column] = 'u.' . admin_qcol($column);
    $orderBy = $allowedSort[$sort] ?? ($hasCreated ? 'u.data_creazione' : 'u.id');

    // Il confronto si fa tutto in MySQL: `ultimo_accesso` lo scrive NOW(), e
    // l'orologio di PHP potrebbe avere un altro fuso.
    $onlineSql = 'u.ultimo_accesso > DATE_SUB(NOW(), INTERVAL ' . (int)USER_ONLINE_WINDOW_SECONDS . ' SECOND)';

    $where = [];
    $params = [];
    $types = '';

    if ($q !== '') {
        // "#123" cerca solo per ID; altrimenti username, nome, email o ID esatto.
        if (preg_match('/^#(\d+)$/', $q, $m)) {
            $where[] = 'u.id = ?';
            $params[] = (int)$m[1];
            $types .= 'i';
        } else {
            $like = '%' . $q . '%';
            $searchParts = ['u.username LIKE ?'];
            $params[] = $like;
            $types .= 's';
            if ($hasDisplayName) {
                $searchParts[] = 'u.display_name LIKE ?';
                $params[] = $like;
                $types .= 's';
            }
            if ($hasEmail) {
                $searchParts[] = 'u.email LIKE ?';
                $params[] = $like;
                $types .= 's';
            }
            $searchParts[] = 'CAST(u.id AS CHAR) = ?';
            $params[] = $q;
            $types .= 's';
            $where[] = '(' . implode(' OR ', $searchParts) . ')';
        }
    }

    if ($hasBan && $status === 'active') $where[] = 'u.isBannato = 0';
    if ($hasBan && $status === 'banned') $where[] = 'u.isBannato = 1';

    if ($hasPremium && $premium === 'premium') $where[] = 'u.is_premium = 1';
    if ($hasPremium && $premium === 'free') $where[] = 'u.is_premium = 0';

    if ($hasRole && in_array($role, ['utente', 'admin', 'owner'], true)) {
        $where[] = 'u.ruolo = ?';
        $params[] = $role;
        $types .= 's';
    }

    if ($onlineOnly) {
        $where[] = $hasLastSeen ? $onlineSql : '0 = 1';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Il totale di chi e' online non segue i filtri: e' il numero sul bottone.
    $onlineCount = 0;
    if ($hasLastSeen) {
        $onlineRes = $mysqli->query("SELECT COUNT(*) AS total FROM utenti u WHERE $onlineSql");
        $onlineCount = $onlineRes ? (int)($onlineRes->fetch_assoc()['total'] ?? 0) : 0;
    }

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
    $select .= $hasPremium ? ', u.is_premium' : ', 0 AS is_premium';
    $select .= $hasDisplayName ? ', u.display_name' : ', NULL AS display_name';
    foreach (array_keys($currencies) as $column) $select .= ', COALESCE(u.' . admin_qcol($column) . ', 0) AS ' . admin_qcol($column);
    $select .= $hasLastSeen
        ? ', u.ultimo_accesso, TIMESTAMPDIFF(SECOND, u.ultimo_accesso, NOW()) AS seconds_since_active'
        : ', NULL AS ultimo_accesso, NULL AS seconds_since_active';

    foreach (['motivo_ban', 'banned_until', 'banned_at', 'banned_by', 'updated_at', 'email_verificata'] as $column) {
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
        ORDER BY $orderBy $dir, u.id DESC
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
        $secondsSince = $row['seconds_since_active'];
        $row['seconds_since_active'] = $secondsSince === null ? null : max(0, (int)$secondsSince);
        $row['is_online'] = $secondsSince !== null && (int)$secondsSince < USER_ONLINE_WINDOW_SECONDS;
        $users[] = $row;
    }
    $stmt->close();

    admin_ok([
        'users' => $users,
        'online_count' => $onlineCount,
        'online_available' => $hasLastSeen,
        'online_window' => (int)USER_ONLINE_WINDOW_SECONDS,
        'currencies' => admin_user_currencies_public($mysqli),
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => max(1, (int)ceil($total / $limit))]
    ]);
} catch (Throwable $e) {
    admin_fail('Errore caricamento utenti. Dettaglio: ' . $e->getMessage(), 500);
}
