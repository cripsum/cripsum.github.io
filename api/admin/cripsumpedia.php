<?php
/**
 * Pannello admin: Cripsumpedia (fase 4).
 *
 * La revisione delle proposte si fa dalla pagina /it/cripsumpedia/revisione:
 * qui c'è la coda per arrivarci, le voci (nascondere, rimettere online,
 * cancellare per sempre), le impostazioni delle proposte, chi non può più
 * proporre e l'import delle persone da Chi siamo.
 *
 * GET  action=overview | entries
 * POST action=save_settings | block | unblock | hide | unhide | purge | import_chisiamo
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../cripsumpedia/_bootstrap.php';

$input = $_SERVER['REQUEST_METHOD'] === 'GET' ? $_GET : admin_input();
$action = (string)($input['action'] ?? ($_GET['action'] ?? ''));
$adminId = (int)$adminUser['id'];
$isOwner = admin_is_owner_role($adminUser['ruolo'] ?? '');

if (!cp_schema_ready($mysqli)) {
    admin_fail('Le tabelle della Cripsumpedia non ci sono: esegui migrations/2026_10_17_cripsumpedia_schema.sql.', 503);
}
if (!cp_v2_ready($mysqli)) {
    admin_fail('Manca la migrazione migrations/2026_10_17_cripsumpedia_v2.sql: eseguila in phpMyAdmin.', 503);
}

$count = static function (string $sql) use ($mysqli): int {
    $result = $mysqli->query($sql);
    return $result ? (int)($result->fetch_row()[0] ?? 0) : 0;
};

$entryRow = static function (array $row): array {
    return [
        'id' => (int)$row['id'],
        'title' => (string)$row['title'],
        'type' => (string)$row['entry_type'],
        'type_label' => cp_type_label((string)$row['entry_type'], 'it'),
        'status' => !empty($row['deleted_at']) ? 'hidden' : ((string)$row['status'] === 'published' ? 'published' : 'draft'),
        'updated_at' => (string)$row['updated_at'],
        'image' => cp_entry_image($row),
        'url' => cp_entry_url($row, 'it'),
        'edit_url' => cp_edit_url('it', (int)$row['id']),
        'history_url' => cp_url('history', ['voce' => (int)$row['id']], 'it'),
    ];
};

try {
    // Le voci scritte prima della cronologia: versione di partenza e testo pronto.
    cp_v2_backfill($mysqli);

    if ($action === 'overview') {
        $settings = cp_settings($mysqli);
        $pending = array_map(static fn(array $row): array => [
            'id' => (int)$row['id'],
            'title' => (string)$row['title'] !== '' ? (string)$row['title'] : 'Senza titolo',
            'kind' => (string)$row['kind'],
            'type_label' => $row['entry_type'] ? cp_type_label((string)$row['entry_type'], 'it') : '',
            'summary' => (string)$row['summary'],
            'author' => (string)($row['username'] ?? ''),
            'author_id' => (int)($row['user_id'] ?? 0),
            'created_at' => (string)$row['created_at'],
            'url' => cp_url('review', ['id' => (int)$row['id']], 'it'),
        ], cp_pending_list($mysqli, 50));

        $blocked = [];
        $result = $mysqli->query('SELECT b.user_id, b.reason, b.created_at, u.username, s.username AS blocked_by_name
                                  FROM cripsumpedia_blocked b
                                  LEFT JOIN utenti u ON u.id = b.user_id
                                  LEFT JOIN utenti s ON s.id = b.blocked_by
                                  ORDER BY b.created_at DESC');
        while ($result && ($row = $result->fetch_assoc())) {
            $blocked[] = [
                'user_id' => (int)$row['user_id'],
                'username' => (string)($row['username'] ?? ''),
                'reason' => (string)$row['reason'],
                'created_at' => (string)$row['created_at'],
                'by' => (string)($row['blocked_by_name'] ?? ''),
            ];
        }

        $candidates = array_map(static fn(array $row): array => [
            'name' => (string)$row['nome'],
            'photo' => (string)($row['foto'] ?? ''),
        ], cp_chisiamo_candidates($mysqli));

        admin_ok([
            'settings' => $settings,
            'pending' => $pending,
            'pending_count' => cp_pending_count($mysqli),
            'counts' => [
                'published' => $count("SELECT COUNT(*) FROM cripsumpedia_entries WHERE status = 'published' AND deleted_at IS NULL"),
                'draft' => $count("SELECT COUNT(*) FROM cripsumpedia_entries WHERE status <> 'published' AND deleted_at IS NULL"),
                'hidden' => $count('SELECT COUNT(*) FROM cripsumpedia_entries WHERE deleted_at IS NOT NULL'),
            ],
            'blocked' => $blocked,
            'chisiamo' => $candidates,
            'uploads_ready' => cp_table_exists($mysqli, 'cripsumpedia_uploads'),
            'is_owner' => $isOwner,
            'review_url' => cp_url('review', [], 'it'),
            'write_url' => cp_url('write', [], 'it'),
        ]);
    }

    if ($action === 'entries') {
        $filter = in_array((string)($input['filter'] ?? ''), ['published', 'draft', 'hidden'], true) ? (string)$input['filter'] : 'all';
        $where = match ($filter) {
            'published' => "status = 'published' AND deleted_at IS NULL",
            'draft' => "status <> 'published' AND deleted_at IS NULL",
            'hidden' => 'deleted_at IS NOT NULL',
            default => '1 = 1',
        };
        $q = trim((string)($input['q'] ?? ''));
        $params = [];
        $types = '';
        if ($q !== '') {
            $where .= ' AND (title LIKE ? OR title_en LIKE ? OR slug LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params = [$like, $like, $like];
            $types = 'sss';
        }
        $page = max(1, (int)($input['page'] ?? 1));
        $limit = 30;

        $stmt = $mysqli->prepare("SELECT COUNT(*) FROM cripsumpedia_entries WHERE $where");
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();
        $pages = max(1, (int)ceil($total / $limit));
        $page = min($page, $pages);
        $offset = ($page - 1) * $limit;

        $stmt = $mysqli->prepare("SELECT id, entry_type, slug, title, status, deleted_at, updated_at, image_url, banner_url
                                  FROM cripsumpedia_entries WHERE $where ORDER BY updated_at DESC, id DESC LIMIT ? OFFSET ?");
        $stmt->bind_param($types . 'ii', ...[...$params, $limit, $offset]);
        $stmt->execute();
        $rows = cp_stmt_all($stmt);
        $stmt->close();

        admin_ok([
            'entries' => array_map($entryRow, $rows),
            'pagination' => ['page' => $page, 'pages' => $pages, 'total' => $total],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        admin_fail('Azione non valida.', 400);
    }

    if ($action === 'save_settings') {
        $open = !empty($input['proposals_open']) ? 1 : 0;
        $days = max(0, min(365, (int)($input['min_account_days'] ?? 3)));
        $max = max(1, min(20, (int)($input['max_pending'] ?? 3)));
        $stmt = $mysqli->prepare('INSERT INTO cripsumpedia_settings (id, proposals_open, min_account_days, max_pending, updated_by, updated_at)
                                  VALUES (1, ?, ?, ?, ?, NOW())
                                  ON DUPLICATE KEY UPDATE proposals_open = VALUES(proposals_open), min_account_days = VALUES(min_account_days),
                                                          max_pending = VALUES(max_pending), updated_by = VALUES(updated_by), updated_at = NOW()');
        if (!$stmt) admin_fail('Salvataggio non riuscito.', 500);
        $stmt->bind_param('iiii', $open, $days, $max, $adminId);
        $stmt->execute();
        $stmt->close();
        admin_log($mysqli, $adminId, 'cripsumpedia_settings', null, ['proposals_open' => $open, 'min_account_days' => $days, 'max_pending' => $max]);
        admin_ok(['settings' => ['proposals_open' => $open === 1, 'min_account_days' => $days, 'max_pending' => $max]]);
    }

    if ($action === 'block') {
        $username = ltrim(trim((string)($input['username'] ?? '')), '@');
        $reason = mb_substr(trim((string)($input['reason'] ?? '')), 0, 300, 'UTF-8');
        $stmt = $mysqli->prepare('SELECT id, username, ruolo FROM utenti WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$user) admin_fail('Nessun account con questo username.', 404);
        if (admin_is_admin_role($user['ruolo'] ?? '')) admin_fail('Lo staff non si può bloccare.', 422);
        $userId = (int)$user['id'];
        $stmt = $mysqli->prepare('INSERT INTO cripsumpedia_blocked (user_id, reason, blocked_by, created_at) VALUES (?, ?, ?, NOW())
                                  ON DUPLICATE KEY UPDATE reason = VALUES(reason), blocked_by = VALUES(blocked_by)');
        $stmt->bind_param('isi', $userId, $reason, $adminId);
        $stmt->execute();
        $stmt->close();
        admin_log($mysqli, $adminId, 'cripsumpedia_block', $userId, ['reason' => $reason]);
        admin_ok(['message' => $user['username'] . ' non può più proporre voci.']);
    }

    if ($action === 'unblock') {
        $userId = (int)($input['user_id'] ?? 0);
        $stmt = $mysqli->prepare('DELETE FROM cripsumpedia_blocked WHERE user_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        admin_log($mysqli, $adminId, 'cripsumpedia_unblock', $userId);
        admin_ok();
    }

    if ($action === 'hide' || $action === 'unhide' || $action === 'purge') {
        $entryId = (int)($input['entry_id'] ?? 0);
        $entry = cp_fetch_entry($mysqli, null, (string)$entryId, true, true);
        if (!$entry) admin_fail('Voce non trovata.', 404);
        if ($action === 'purge') {
            if (!$isOwner) admin_fail('Cancellare per sempre una voce può solo l\'owner.', 403);
            if (empty($entry['deleted_at'])) admin_fail('Prima nascondi la voce.', 409);
            if (!cp_purge_entry($mysqli, $entryId)) admin_fail('Cancellazione non riuscita.', 500);
        } else {
            $done = $action === 'hide' ? cp_hide_entry($mysqli, $entryId, $adminId) : cp_unhide_entry($mysqli, $entryId, $adminId);
            if (!$done) admin_fail($action === 'hide' ? 'La voce è già nascosta.' : 'La voce è già online.', 409);
        }
        admin_log($mysqli, $adminId, 'cripsumpedia_' . $action, null, ['entry_id' => $entryId, 'title' => $entry['title']]);
        admin_ok();
    }

    if ($action === 'import_chisiamo') {
        $created = cp_import_chisiamo($mysqli, $adminId);
        admin_log($mysqli, $adminId, 'cripsumpedia_import_chisiamo', null, ['created' => count($created)]);
        admin_ok([
            'created' => array_map(static fn(array $row): array => $row + ['edit_url' => cp_edit_url('it', (int)$row['id'])], $created),
            'message' => $created
                ? (count($created) === 1 ? '1 persona importata come bozza.' : count($created) . ' persone importate come bozze.')
                : 'Nessuna persona nuova da importare.',
        ]);
    }

    admin_fail('Azione non valida.', 400);
} catch (Throwable $e) {
    error_log('[admin cripsumpedia] ' . $e->getMessage());
    admin_fail('Errore del server.', 500);
}
