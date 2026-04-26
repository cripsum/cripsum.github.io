<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $q = mb_strtolower(trim((string)($_GET['q'] ?? '')), 'UTF-8');
    $source = (string)($_GET['source'] ?? 'all');
    $status = (string)($_GET['status'] ?? 'open');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(80, max(10, (int)($_GET['limit'] ?? 30)));

    $reports = [];

    if (($source === 'all' || $source === 'content') && admin_table_exists($mysqli, 'content_reports')) {
        $sql = "
            SELECT
                cr.id,
                'content' AS report_source,
                cr.content_type,
                cr.post_id,
                cr.user_id AS reporter_id,
                cr.reason,
                cr.status,
                cr.created_at,
                cr.reviewed_at,
                cr.reviewed_by,
                ru.username AS reporter_username,
                COALESCE(sp.titolo, rp.titolo, CONCAT('Post #', cr.post_id)) AS target_title,
                COALESCE(su.username, ru2.username) AS target_username
            FROM content_reports cr
            LEFT JOIN utenti ru ON ru.id = cr.user_id
            LEFT JOIN shitposts sp ON cr.content_type = 'shitpost' AND sp.id = cr.post_id
            LEFT JOIN utenti su ON su.id = sp.id_utente
            LEFT JOIN toprimasti rp ON cr.content_type = 'rimasto' AND rp.id = cr.post_id
            LEFT JOIN utenti ru2 ON ru2.id = rp.id_utente
            ORDER BY cr.created_at DESC
            LIMIT 500
        ";
        $result = $mysqli->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['report_source_label'] = $row['content_type'] === 'rimasto' ? 'Top Rimasti' : 'Shitpost';
                $row['target_url'] = $row['content_type'] === 'rimasto'
                    ? '/it/rimasti?post=' . (int)$row['post_id']
                    : '/it/shitpost?post=' . (int)$row['post_id'];
                $reports[] = $row;
            }
        }
    }

    if (($source === 'all' || $source === 'chat') && admin_table_exists($mysqli, 'chat_reports')) {
        $hasDeletedAt = admin_column_exists($mysqli, 'messages', 'deleted_at');
        $deletedSelect = $hasDeletedAt ? 'm.deleted_at' : 'NULL AS deleted_at';

        $sql = "
            SELECT
                cr.id,
                'chat' AS report_source,
                'chat' AS content_type,
                cr.message_id AS post_id,
                cr.reporter_id,
                cr.reason,
                cr.status,
                cr.created_at,
                NULL AS reviewed_at,
                NULL AS reviewed_by,
                ru.username AS reporter_username,
                LEFT(COALESCE(m.message, CONCAT('Messaggio #', cr.message_id)), 120) AS target_title,
                tu.username AS target_username,
                $deletedSelect
            FROM chat_reports cr
            LEFT JOIN utenti ru ON ru.id = cr.reporter_id
            LEFT JOIN messages m ON m.id = cr.message_id
            LEFT JOIN utenti tu ON tu.id = m.user_id
            ORDER BY cr.created_at DESC
            LIMIT 500
        ";
        $result = $mysqli->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['report_source_label'] = 'Chat';
                $row['target_url'] = '/it/global-chat';
                $reports[] = $row;
            }
        }
    }

    $reports = array_values(array_filter($reports, function ($row) use ($q, $status) {
        if ($status !== 'all' && ($row['status'] ?? '') !== $status) return false;
        if ($q === '') return true;

        $haystack = mb_strtolower(implode(' ', [
            $row['reason'] ?? '',
            $row['reporter_username'] ?? '',
            $row['target_username'] ?? '',
            $row['target_title'] ?? '',
            $row['report_source_label'] ?? '',
        ]), 'UTF-8');

        return str_contains($haystack, $q);
    }));

    usort($reports, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));

    $total = count($reports);
    $offset = ($page - 1) * $limit;
    $reports = array_slice($reports, $offset, $limit);

    admin_ok([
        'reports' => $reports,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => max(1, (int)ceil($total / $limit)),
        ],
    ]);
} catch (Throwable $e) {
    admin_fail('Errore caricamento segnalazioni. Dettaglio: ' . $e->getMessage(), 500);
}
