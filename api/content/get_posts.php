<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $type = cv2_normalize_type((string)($_GET['type'] ?? 'shitpost'));
    $meta = cv2_meta($type);

    if (!cv2_table_exists($mysqli, $meta['table'])) {
        cv2_ok(['posts' => [], 'stats' => [], 'pagination' => ['page' => 1, 'pages' => 1, 'total' => 0]]);
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(24, max(6, (int)($_GET['limit'] ?? 12)));
    $offset = ($page - 1) * $limit;
    $q = trim((string)($_GET['q'] ?? ''));
    $sort = (string)($_GET['sort'] ?? 'recent');
    $status = (string)($_GET['status'] ?? 'approved');
    $savedOnly = (string)($_GET['saved'] ?? '0') === '1';

    $isAdmin = cv2_is_admin($currentUser);
    $userId = (int)($currentUser['id'] ?? 0);

    $table = cv2_qcol($meta['table']);
    $title = cv2_qcol($meta['title']);
    $desc = cv2_qcol($meta['description']);
    $created = cv2_qcol($meta['created']);
    $approved = cv2_qcol($meta['approved']);
    $userCol = cv2_qcol($meta['user']);
    $mimeCol = cv2_qcol($meta['mime']);
    $blobCol = cv2_qcol($meta['blob']);
    $scoreCol = $meta['score'] ? cv2_qcol($meta['score']) : null;
    $extraCol = $meta['extra'] ? cv2_qcol($meta['extra']) : null;

    $hasViews = cv2_column_exists($mysqli, $meta['table'], 'views');
    $hasTag = cv2_column_exists($mysqli, $meta['table'], 'tag');
    $hasSpoiler = cv2_column_exists($mysqli, $meta['table'], 'is_spoiler');

    $where = [];
    $params = [];
    $types = '';

    if ($isAdmin) {
        if ($status === 'pending') $where[] = "p.$approved = 0";
        elseif ($status === 'all') {}
        else $where[] = "p.$approved = 1";
    } else {
        $where[] = "p.$approved = 1";
    }

    if ($q !== '') {
        $parts = ["p.$title LIKE ?", "p.$desc LIKE ?", "u.username LIKE ?"];
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= 'sss';

        if ($extraCol) {
            $parts[] = "p.$extraCol LIKE ?";
            $params[] = $like;
            $types .= 's';
        }

        if ($hasTag) {
            $parts[] = "p.`tag` LIKE ?";
            $params[] = $like;
            $types .= 's';
        }

        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    if ($savedOnly) {
        if (!$currentUser) cv2_fail('Devi essere loggato per vedere i salvati.', 401);
        if (!cv2_table_exists($mysqli, 'content_saves')) cv2_fail('Tabella salvati mancante.', 500);

        $where[] = "EXISTS (
            SELECT 1 FROM content_saves cs
            WHERE cs.content_type = ? AND cs.post_id = p.id AND cs.user_id = ?
        )";
        $params[] = $type;
        $params[] = $userId;
        $types .= 'si';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $scoreSql = "0";
    $userLikedSql = "0";
    if ($type === 'rimasto') {
        $scoreSql = $scoreCol ? "COALESCE(p.$scoreCol, 0)" : "0";
        if ($currentUser && cv2_table_exists($mysqli, 'voti_toprimasti')) {
            $userLikedSql = "EXISTS(SELECT 1 FROM voti_toprimasti v WHERE v.id_post = p.id AND v.id_utente = $userId)";
        }
    } else {
        if (cv2_table_exists($mysqli, 'shitpost_likes')) {
            $scoreSql = "(SELECT COUNT(*) FROM shitpost_likes sl WHERE sl.id_shitpost = p.id)";
            if ($currentUser) {
                $userLikedSql = "EXISTS(SELECT 1 FROM shitpost_likes sl2 WHERE sl2.id_shitpost = p.id AND sl2.id_utente = $userId)";
            }
        }
    }

    $commentsCountSql = "0";
    if ($type === 'shitpost' && cv2_table_exists($mysqli, 'commenti_shitpost')) {
        $commentsCountSql = "(SELECT COUNT(*) FROM commenti_shitpost c WHERE c.id_shitpost = p.id)";
    } elseif ($type === 'rimasto' && cv2_table_exists($mysqli, 'content_comments')) {
        $commentsCountSql = "(SELECT COUNT(*) FROM content_comments c WHERE c.content_type = 'rimasto' AND c.post_id = p.id)";
    }

    $userSavedSql = "0";
    if ($currentUser && cv2_table_exists($mysqli, 'content_saves')) {
        $userSavedSql = "EXISTS(SELECT 1 FROM content_saves s WHERE s.content_type = '$type' AND s.post_id = p.id AND s.user_id = $userId)";
    }

    $viewsSql = $hasViews ? "COALESCE(p.`views`, 0)" : "0";
    $tagSql = $hasTag ? "p.`tag`" : "NULL";
    $spoilerSql = $hasSpoiler ? "COALESCE(p.`is_spoiler`, 0)" : "0";
    $extraSql = $extraCol ? "p.$extraCol" : "NULL";

    $orderSql = "p.$created DESC";
    if ($sort === 'top') $orderSql = "score DESC, p.$created DESC";
    if ($sort === 'comments') $orderSql = "comments_count DESC, p.$created DESC";
    if ($sort === 'views') $orderSql = "views DESC, p.$created DESC";

    $countSql = "SELECT COUNT(*) AS total FROM $table p LEFT JOIN utenti u ON u.id = p.$userCol $whereSql";
    $stmt = $mysqli->prepare($countSql);
    if (!$stmt) cv2_fail('Query conteggio non valida: ' . $mysqli->error, 500);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) cv2_fail('Errore conteggio post.', 500);
    $total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $sql = "
        SELECT
            p.id,
            p.$userCol AS id_utente,
            p.$title AS titolo,
            p.$desc AS descrizione,
            $extraSql AS extra_text,
            p.$created AS data_creazione,
            p.$approved AS approvato,
            p.$mimeCol AS media_mime,
            CASE WHEN p.$blobCol IS NULL THEN 0 ELSE 1 END AS has_media,
            $tagSql AS tag,
            $spoilerSql AS is_spoiler,
            $viewsSql AS views,
            $scoreSql AS score,
            $commentsCountSql AS comments_count,
            $userLikedSql AS user_liked,
            $userSavedSql AS user_saved,
            u.username,
            COALESCE(u.ruolo, 'utente') AS ruolo
        FROM $table p
        LEFT JOIN utenti u ON u.id = p.$userCol
        $whereSql
        ORDER BY $orderSql
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) cv2_fail('Query post non valida: ' . $mysqli->error, 500);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) cv2_fail('Errore caricamento post.', 500);

    $res = $stmt->get_result();
    $posts = [];

    while ($row = $res->fetch_assoc()) {
        $id = (int)$row['id'];
        $row['media_url'] = ((int)$row['has_media'] === 1) ? $meta['media_endpoint'] . $id : null;
        $row['is_video'] = cv2_is_video($row['media_mime'] ?? null) ? 1 : 0;
        $row['public_url'] = cv2_post_url($type, $id);
        $row['score'] = (int)($row['score'] ?? 0);
        $row['comments_count'] = (int)($row['comments_count'] ?? 0);
        $row['views'] = (int)($row['views'] ?? 0);
        $row['user_liked'] = (int)($row['user_liked'] ?? 0);
        $row['user_saved'] = (int)($row['user_saved'] ?? 0);
        $posts[] = $row;
    }
    $stmt->close();

    $stats = [
        'total' => $total,
        'reactions' => array_sum(array_map(fn($p) => (int)$p['score'], $posts)),
        'comments' => array_sum(array_map(fn($p) => (int)$p['comments_count'], $posts)),
        'views' => array_sum(array_map(fn($p) => (int)$p['views'], $posts)),
    ];

    cv2_ok([
        'posts' => $posts,
        'stats' => $stats,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => max(1, (int)ceil($total / $limit)),
        ],
    ]);
} catch (Throwable $e) {
    cv2_fail('Errore feed: ' . $e->getMessage(), 500);
}
