<?php
require_once __DIR__ . '/bootstrap.php';

try {
    if (!admin_table_exists($mysqli, 'toprimasti')) {
        admin_ok(['posts' => [], 'pagination' => ['page' => 1, 'pages' => 1, 'total' => 0]]);
    }

    $q = trim((string)($_GET['q'] ?? ''));
    $status = (string)($_GET['status'] ?? 'all');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(80, max(10, (int)($_GET['limit'] ?? 30)));
    $offset = ($page - 1) * $limit;

    $hasTag = admin_column_exists($mysqli, 'toprimasti', 'tag');
    $hasSpoiler = admin_column_exists($mysqli, 'toprimasti', 'is_spoiler');
    $hasViews = admin_column_exists($mysqli, 'toprimasti', 'views');
    $hasUpdated = admin_column_exists($mysqli, 'toprimasti', 'updated_at');

    $where = [];
    $params = [];
    $types = '';

    if ($q !== '') {
        $parts = ['t.titolo LIKE ?', 't.descrizione LIKE ?', 't.motivazione LIKE ?', 'u.username LIKE ?'];
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= 'ssss';

        if ($hasTag) {
            $parts[] = 't.`tag` LIKE ?';
            $params[] = $like;
            $types .= 's';
        }

        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    if ($status === 'approved') $where[] = 't.approvato = 1';
    if ($status === 'pending') $where[] = 't.approvato = 0';

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM toprimasti t LEFT JOIN utenti u ON u.id = t.id_utente $whereSql");
    if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Query conteggio Top Rimasti non valida.'), 500);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Impossibile contare i Top Rimasti.', 500);
    $total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $votesSql = admin_table_exists($mysqli, 'voti_toprimasti')
        ? "(SELECT COUNT(*) FROM voti_toprimasti v WHERE v.id_post = t.id)"
        : "COALESCE(t.reazioni, 0)";
    $commentsSql = admin_table_exists($mysqli, 'content_comments')
        ? "(SELECT COUNT(*) FROM content_comments c WHERE c.content_type = 'rimasto' AND c.post_id = t.id)"
        : "0";
    $savesSql = admin_table_exists($mysqli, 'content_saves')
        ? "(SELECT COUNT(*) FROM content_saves sv WHERE sv.content_type = 'rimasto' AND sv.post_id = t.id)"
        : "0";
    $reportsSql = admin_table_exists($mysqli, 'content_reports')
        ? "(SELECT COUNT(*) FROM content_reports r WHERE r.content_type = 'rimasto' AND r.post_id = t.id AND r.status = 'open')"
        : "0";

    $tagSql = $hasTag ? "t.`tag`" : "NULL";
    $spoilerSql = $hasSpoiler ? "COALESCE(t.`is_spoiler`, 0)" : "0";
    $viewsSql = $hasViews ? "COALESCE(t.`views`, 0)" : "0";
    $updatedSql = $hasUpdated ? "t.`updated_at`" : "NULL";

    $sql = "
        SELECT
            t.id,
            t.id_utente,
            t.titolo,
            t.descrizione,
            t.motivazione,
            t.tipo_foto_rimasto,
            t.data_creazione,
            t.approvato,
            COALESCE(t.reazioni, 0) AS reazioni,
            $tagSql AS tag,
            $spoilerSql AS is_spoiler,
            $viewsSql AS views,
            $updatedSql AS updated_at,
            u.username,
            CASE WHEN t.foto_rimasto IS NULL THEN 0 ELSE 1 END AS has_media,
            $votesSql AS votes_count,
            $commentsSql AS comments_count,
            $savesSql AS saves_count,
            $reportsSql AS reports_count
        FROM toprimasti t
        LEFT JOIN utenti u ON u.id = t.id_utente
        $whereSql
        ORDER BY t.approvato ASC, COALESCE(t.reazioni, 0) DESC, t.data_creazione DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Impossibile preparare la lista Top Rimasti.'), 500);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) admin_fail('Impossibile caricare i Top Rimasti.', 500);

    $res = $stmt->get_result();
    $posts = [];
    while ($row = $res->fetch_assoc()) {
        $row['media_url'] = ((int)$row['has_media'] === 1) ? '/api/admin/get_toprimasti_media.php?id=' . (int)$row['id'] : null;
        $posts[] = $row;
    }
    $stmt->close();

    admin_ok([
        'posts' => $posts,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => max(1, (int)ceil($total / $limit))]
    ]);
} catch (Throwable $e) {
    admin_fail('Errore caricamento Top Rimasti. Dettaglio: ' . $e->getMessage(), 500);
}
