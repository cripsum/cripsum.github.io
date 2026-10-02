<?php
/**
 * Pannello admin: moderazione di Shitpost e Top Rimasti.
 *
 * Un solo endpoint per le due sezioni (parametro `type`). Approva, nascondi
 * ed elimina passano dalle funzioni condivise con la pagina e con il bot
 * (includes/content_moderation.php): stesso annuncio, stessi avvisi
 * all'autore, stessa pulizia.
 *
 * GET  action=counts | list | post | settings
 * POST action=approve | hide | delete | update | delete_comment
 *             | reset_votes | dismiss_reports | save_settings
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/community/community.php';
require_once __DIR__ . '/../../includes/content_moderation.php';

cm_lang('it');

$input = $_SERVER['REQUEST_METHOD'] === 'GET' ? $_GET : admin_input();
$action = (string)($input['action'] ?? ($_GET['action'] ?? ''));
$type = cv2_normalize_type((string)($input['type'] ?? 'shitpost'));
$adminId = (int)$adminUser['id'];
$meta = cv2_meta($type);
$schema = cm_schema($mysqli, $type);
$logName = $type === 'rimasto' ? 'toprimasti' : 'shitpost';

/** Gli id di un'azione su più post: `ids` oppure un solo `id`. */
$postIds = static function () use ($input): array {
    $ids = is_array($input['ids'] ?? null) ? $input['ids'] : [$input['id'] ?? 0];
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
    if (!$ids) {
        admin_fail('Nessun post selezionato.');
    }
    return array_slice($ids, 0, 50);
};

$counts = static function () use ($mysqli): array {
    $out = [];
    foreach (['shitpost' => 'shitposts', 'rimasto' => 'toprimasti'] as $key => $table) {
        $out[$key] = [
            'pending' => cm_has($mysqli, $table) ? cm_count($mysqli, "SELECT COUNT(*) FROM `$table` WHERE approvato = 0") : 0,
            'reported' => cm_has($mysqli, 'content_reports')
                ? cm_count($mysqli, "SELECT COUNT(DISTINCT post_id) FROM content_reports WHERE content_type = ? AND status = 'open'", 's', [$key])
                : 0,
        ];
    }
    return $out;
};

try {
    // ── Letture ─────────────────────────────────────────────────────────────
    if ($action === 'counts') {
        admin_ok(['counts' => $counts()]);
    }

    if ($action === 'list') {
        $tab = in_array((string)($input['tab'] ?? ''), ['pending', 'approved', 'reported'], true) ? (string)$input['tab'] : 'pending';
        $sort = (string)($input['sort'] ?? '');
        if (!in_array($sort, ['recent', 'oldest', 'top', 'comments'], true)) {
            // In coda si parte da chi aspetta da più tempo.
            $sort = $tab === 'pending' ? 'oldest' : 'recent';
        }

        $feed = cm_feed($mysqli, $type, [
            'q' => (string)($input['q'] ?? ''),
            'sort' => $sort,
            'status' => $tab === 'pending' ? 'pending' : ($tab === 'approved' ? 'approved' : 'all'),
            'reported' => $tab === 'reported',
            'page' => (int)($input['page'] ?? 1),
            'limit' => 30,
        ], $adminUser);

        // Quello che serve solo allo staff: segnalazioni aperte, salvataggi,
        // peso del file e quanti post ha mandato oggi l'autore.
        $posts = $feed['posts'];
        if ($posts) {
            $ids = implode(',', array_map(static fn($post) => (int)$post['id'], $posts));
            $extra = [];

            $result = $mysqli->query("SELECT id, LENGTH(`{$meta['blob']}`) AS bytes, `{$meta['mime']}` AS mime FROM `{$meta['table']}` WHERE id IN ($ids)");
            while ($result && ($row = $result->fetch_assoc())) {
                $extra[(int)$row['id']] = ['bytes' => (int)$row['bytes'], 'mime' => (string)$row['mime'], 'reports' => 0, 'saves' => 0];
            }
            if ($schema['reports']) {
                $result = $mysqli->query("SELECT post_id, COUNT(*) AS n FROM content_reports WHERE content_type = '$type' AND status = 'open' AND post_id IN ($ids) GROUP BY post_id");
                while ($result && ($row = $result->fetch_assoc())) {
                    $extra[(int)$row['post_id']]['reports'] = (int)$row['n'];
                }
            }
            if ($schema['saves']) {
                $result = $mysqli->query("SELECT post_id, COUNT(*) AS n FROM content_saves WHERE content_type = '$type' AND post_id IN ($ids) GROUP BY post_id");
                while ($result && ($row = $result->fetch_assoc())) {
                    $extra[(int)$row['post_id']]['saves'] = (int)$row['n'];
                }
            }

            $today = [];
            $authors = implode(',', array_unique(array_map(static fn($post) => (int)$post['author']['id'], $posts)));
            foreach (['shitposts', 'toprimasti'] as $table) {
                if (!cm_has($mysqli, $table)) {
                    continue;
                }
                $result = $mysqli->query("SELECT id_utente, COUNT(*) AS n FROM `$table` WHERE id_utente IN ($authors) AND data_creazione >= CURDATE() GROUP BY id_utente");
                while ($result && ($row = $result->fetch_assoc())) {
                    $today[(int)$row['id_utente']] = ($today[(int)$row['id_utente']] ?? 0) + (int)$row['n'];
                }
            }

            foreach ($posts as &$post) {
                $post['admin'] = ($extra[$post['id']] ?? ['bytes' => 0, 'mime' => '', 'reports' => 0, 'saves' => 0])
                    + ['author_today' => $today[$post['author']['id']] ?? 0];
            }
            unset($post);
        }

        admin_ok([
            'posts' => $posts,
            'pagination' => ['page' => $feed['page'], 'pages' => $feed['pages'], 'total' => $feed['total']],
            'counts' => $counts(),
        ]);
    }

    if ($action === 'post') {
        $id = (int)($input['id'] ?? 0);
        $post = cm_feed($mysqli, $type, ['post' => $id], $adminUser)['posts'][0] ?? null;
        if (!$post) {
            admin_fail('Post non trovato.', 404);
        }

        $authorId = (int)$post['author']['id'];
        $author = ['approved' => cm_approved_count($mysqli, $authorId), 'pending' => 0, 'reports' => 0, 'since' => null, 'banned' => false];

        foreach (['shitpost' => 'shitposts', 'rimasto' => 'toprimasti'] as $key => $table) {
            if (!cm_has($mysqli, $table)) {
                continue;
            }
            $author['pending'] += cm_count($mysqli, "SELECT COUNT(*) FROM `$table` WHERE id_utente = ? AND approvato = 0", 'i', [$authorId]);
            if ($schema['reports']) {
                $author['reports'] += cm_count(
                    $mysqli,
                    "SELECT COUNT(*) FROM content_reports r INNER JOIN `$table` p ON p.id = r.post_id WHERE r.content_type = ? AND p.id_utente = ?",
                    'si',
                    [$key, $authorId]
                );
            }
        }

        $stmt = $mysqli->prepare('SELECT data_creazione, isBannato FROM utenti WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $authorId);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) {
            $author['since'] = $row['data_creazione'];
            $author['banned'] = (int)$row['isBannato'] === 1;
        }
        $stmt->close();

        $reports = [];
        if ($schema['reports']) {
            $stmt = $mysqli->prepare("
                SELECT r.id, r.reason, r.status, r.created_at, u.username
                FROM content_reports r
                LEFT JOIN utenti u ON u.id = r.user_id
                WHERE r.content_type = ? AND r.post_id = ?
                ORDER BY (r.status = 'open') DESC, r.created_at DESC
                LIMIT 50
            ");
            $stmt->bind_param('si', $type, $id);
            $stmt->execute();
            $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        admin_ok([
            'post' => $post,
            'author' => $author,
            'reports' => $reports,
            'comments' => cm_comments($mysqli, $type, $id, $adminUser),
        ]);
    }

    if ($action === 'settings') {
        admin_ok([
            'settings' => cm_settings($mysqli),
            'available' => cm_has($mysqli, 'community_impostazioni'),
            'multi_media' => cm_schema($mysqli, 'shitpost')['extra'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        admin_fail('Azione non valida.', 400);
    }

    // ── Azioni ──────────────────────────────────────────────────────────────
    if ($action === 'approve' || $action === 'hide') {
        $approve = $action === 'approve';
        $done = 0;

        foreach ($postIds() as $id) {
            $result = cripsum_set_community_post_approval($mysqli, $type, $id, $approve, ['actor_id' => $adminId]);
            if ($result['ok'] && $result['changed']) {
                $done++;
                admin_log($mysqli, $adminId, ($approve ? 'approve_' : 'unapprove_') . $logName, $result['author_id'], ['post_id' => $id, 'title' => $result['title']]);
            }
        }

        admin_ok([
            'message' => $done === 1
                ? ($approve ? 'Post approvato.' : 'Post rimesso in attesa.')
                : $done . ($approve ? ' post approvati.' : ' post rimessi in attesa.'),
            'done' => $done,
            'counts' => $counts(),
        ]);
    }

    if ($action === 'delete') {
        $reason = trim((string)($input['reason'] ?? ''));
        $done = 0;

        foreach ($postIds() as $id) {
            $result = cripsum_delete_community_post($mysqli, $type, $id, ['reason' => $reason, 'reviewer_id' => $adminId]);
            if ($result['ok'] && $result['deleted']) {
                $done++;
                admin_log($mysqli, $adminId, 'delete_' . $logName, $result['author_id'], ['post_id' => $id, 'title' => $result['title']] + ($reason !== '' ? ['reason' => $reason] : []));
            }
        }

        admin_ok([
            'message' => $done === 1 ? 'Post eliminato.' : $done . ' post eliminati.',
            'done' => $done,
            'counts' => $counts(),
        ]);
    }

    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $current = cm_post_basic($mysqli, $type, $id);
        if (!$current) {
            admin_fail('Post non trovato.', 404);
        }

        $title = trim((string)($input['titolo'] ?? ''));
        $description = trim((string)($input['descrizione'] ?? ''));
        $motivation = trim((string)($input['motivazione'] ?? ''));

        if ($title === '' || mb_strlen($title, 'UTF-8') > 120) {
            admin_fail('Titolo non valido (massimo 120 caratteri).');
        }
        if (mb_strlen($description, 'UTF-8') > 2000 || mb_strlen($motivation, 'UTF-8') > 2000) {
            admin_fail('Testo troppo lungo (massimo 2000 caratteri).');
        }
        if ($type === 'rimasto' && $motivation === '') {
            admin_fail('La motivazione è obbligatoria.');
        }

        $sets = ['titolo = ?', 'descrizione = ?'];
        $types = 'ss';
        $params = [$title, $description];

        if ($type === 'rimasto') {
            $sets[] = 'motivazione = ?';
            $types .= 's';
            $params[] = $motivation;
        }
        if ($schema['tag']) {
            $sets[] = '`tag` = ?';
            $types .= 's';
            $params[] = cm_tags_store(cm_tags_parse($input['tags'] ?? ''), $schema['tag_length']);
        }
        if ($schema['spoiler']) {
            $sets[] = 'is_spoiler = ?';
            $types .= 'i';
            $params[] = (int)($input['is_spoiler'] ?? 0) === 1 ? 1 : 0;
        }
        if ($schema['updated']) {
            $sets[] = 'updated_at = NOW()';
        }
        $params[] = $id;
        $types .= 'i';

        $stmt = $mysqli->prepare("UPDATE `{$meta['table']}` SET " . implode(', ', $sets) . ' WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('modifica: ' . $mysqli->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        admin_log($mysqli, $adminId, 'update_' . $logName, $current['id_utente'], ['post_id' => $id, 'title' => $title]);
        admin_ok(['message' => 'Post aggiornato.']);
    }

    if ($action === 'delete_comment') {
        $commentId = (int)($input['comment_id'] ?? 0);
        $comment = cm_comment_row($mysqli, $type, $commentId);
        if (!$comment) {
            admin_fail('Commento non trovato.', 404);
        }

        $c = cm_comment_meta($type);
        $scope = $type === 'rimasto' ? " AND content_type = 'rimasto'" : '';

        if ($schema['replies']) {
            $stmt = $mysqli->prepare("DELETE FROM `{$c['table']}` WHERE parent_id = ?$scope");
            $stmt->bind_param('i', $commentId);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $mysqli->prepare("DELETE FROM `{$c['table']}` WHERE id = ?$scope LIMIT 1");
        $stmt->bind_param('i', $commentId);
        $stmt->execute();
        $stmt->close();

        admin_log($mysqli, $adminId, 'delete_' . $logName . '_comment', $comment['user_id'], ['comment_id' => $commentId, 'post_id' => $comment['post_id']]);
        admin_ok([
            'message' => 'Commento eliminato.',
            'comments' => cm_comments($mysqli, $type, $comment['post_id'], $adminUser),
        ]);
    }

    if ($action === 'reset_votes') {
        $id = (int)($input['id'] ?? 0);
        $current = cm_post_basic($mysqli, 'rimasto', $id);
        if (!$current) {
            admin_fail('Post non trovato.', 404);
        }

        $mysqli->begin_transaction();
        if (cm_has($mysqli, 'voti_toprimasti')) {
            $stmt = $mysqli->prepare('DELETE FROM voti_toprimasti WHERE id_post = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $mysqli->prepare('UPDATE toprimasti SET reazioni = 0 WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $mysqli->commit();

        admin_log($mysqli, $adminId, 'reset_toprimasti_votes', $current['id_utente'], ['post_id' => $id, 'title' => $current['titolo']]);
        admin_ok(['message' => 'Voti azzerati.']);
    }

    if ($action === 'dismiss_reports') {
        $id = (int)($input['id'] ?? 0);
        $result = cripsum_set_reports_status_for_target($mysqli, 'content', $id, 'dismissed', $adminId, $type);
        if (!$result['ok']) {
            admin_fail($result['error'] ?? 'Non sono riuscito ad aggiornare le segnalazioni.', 500);
        }

        admin_log($mysqli, $adminId, 'update_report_status', null, ['source' => 'content', 'target_id' => $id, 'status' => 'dismissed', 'affected' => $result['affected']]);
        admin_ok(['message' => 'Segnalazioni ignorate.', 'counts' => $counts()]);
    }

    if ($action === 'save_settings') {
        if (!cm_has($mysqli, 'community_impostazioni')) {
            admin_fail('Le regole si attivano dopo aver applicato la migration del 03/10.', 409);
        }

        $limits = [
            'shitpost_aperto' => [0, 1],
            'rimasto_aperto' => [0, 1],
            'auto_approva' => [0, 1],
            'auto_approva_soglia' => [1, 500],
            'max_post_giorno' => [1, 100],
            'max_in_attesa' => [1, 50],
            'max_media' => [1, 10],
        ];
        $before = cm_settings($mysqli);
        $changed = [];

        $stmt = $mysqli->prepare('INSERT INTO community_impostazioni (chiave, valore) VALUES (?, ?) ON DUPLICATE KEY UPDATE valore = VALUES(valore)');
        foreach ($limits as $key => [$min, $max]) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = (string)max($min, min($max, (int)$input[$key]));
            $stmt->bind_param('ss', $key, $value);
            $stmt->execute();
            if ((int)$before[$key] !== (int)$value) {
                $changed[$key] = ['da' => (int)$before[$key], 'a' => (int)$value];
            }
        }
        $stmt->close();

        if ($changed) {
            admin_log($mysqli, $adminId, 'community_settings', null, $changed);
        }
        admin_ok(['message' => 'Regole salvate.']);
    }

    admin_fail('Azione non valida.', 400);
} catch (Throwable $e) {
    if ($mysqli->errno) {
        @$mysqli->rollback();
    }
    error_log('[admin community] ' . $action . ': ' . $e->getMessage());
    admin_fail('Operazione non riuscita. Il dettaglio è nel log del server.', 500);
}
