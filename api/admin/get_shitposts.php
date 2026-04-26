<?php
require_once __DIR__ . '/bootstrap.php';

try {
    if (!admin_table_exists($mysqli, 'shitposts')) {
        admin_ok(['posts' => [], 'pagination' => ['page' => 1, 'pages' => 1, 'total' => 0]]);
    }

    $q = trim((string)($_GET['q'] ?? ''));
    $status = (string)($_GET['status'] ?? 'all');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(80, max(10, (int)($_GET['limit'] ?? 30)));
    $offset = ($page - 1) * $limit;

    $where = [];
    $params = [];
    $types = '';

    if ($q !== '') {
        $where[] = "(s.titolo LIKE ? OR s.descrizione LIKE ? OR u.username LIKE ?)";
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= 'sss';
    }

    if ($status === 'approved') $where[] = 's.approvato = 1';
    if ($status === 'pending') $where[] = 's.approvato = 0';

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM shitposts s LEFT JOIN utenti u ON u.id = s.id_utente $whereSql");
    if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Query conteggio shitpost non valida.'), 500);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Impossibile contare gli shitpost.', 500);
    $total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $commentsSql = admin_table_exists($mysqli, 'commenti_shitpost')
        ? "(SELECT COUNT(*) FROM commenti_shitpost c WHERE c.id_shitpost = s.id)"
        : "0";

    $sql = "
        SELECT
            s.id,
            s.id_utente,
            s.titolo,
            s.descrizione,
            s.tipo_foto_shitpost,
            s.data_creazione,
            s.approvato,
            u.username,
            CASE WHEN s.foto_shitpost IS NULL THEN 0 ELSE 1 END AS has_media,
            $commentsSql AS comments_count
        FROM shitposts s
        LEFT JOIN utenti u ON u.id = s.id_utente
        $whereSql
        ORDER BY s.data_creazione DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Impossibile preparare la lista shitpost.'), 500);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Impossibile caricare gli shitpost.', 500);

    $res = $stmt->get_result();
    $posts = [];
    while ($row = $res->fetch_assoc()) {
        $row['media_url'] = ((int)$row['has_media'] === 1) ? '/api/admin/get_shitpost_media.php?id=' . (int)$row['id'] : null;
        $posts[] = $row;
    }
    $stmt->close();

    admin_ok([
        'posts' => $posts,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => max(1, (int)ceil($total / $limit))]
    ]);
} catch (Throwable $e) {
    admin_fail('Errore caricamento shitpost. Dettaglio: ' . $e->getMessage(), 500);
}
