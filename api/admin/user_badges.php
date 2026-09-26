<?php
require_once __DIR__ . '/bootstrap.php';

/*
 * Badge personalizzati di un utente dalla scheda del pannello: `add` lo
 * assegna (visibile), `remove` lo toglie. Il badge Premium si gestisce anche
 * da qui, ma lo stato Premium resta quello dell'account.
 */

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') admin_fail('Metodo non consentito.', 405);
    if (!admin_table_exists($mysqli, 'custom_badges') || !admin_table_exists($mysqli, 'user_custom_badges')) {
        admin_fail('I badge personalizzati non sono disponibili su questo database.', 500);
    }

    $input = admin_input();
    $action = (string)($input['action'] ?? '');
    $userId = (int)($input['user_id'] ?? 0);
    $badgeId = (int)($input['badge_id'] ?? 0);

    if (!in_array($action, ['add', 'remove'], true)) admin_fail('Azione non valida.');
    if ($userId <= 0 || $badgeId <= 0) admin_fail('Dati non validi.');

    $target = admin_fetch_user($mysqli, $userId);
    if (!$target) admin_fail('Utente non trovato.', 404);
    if (!admin_can_manage_user($adminUser, $target, true)) admin_fail('Non puoi modificare i badge di questo utente.', 403);

    $stmt = $mysqli->prepare('SELECT id, name FROM custom_badges WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $badgeId);
    $stmt->execute();
    $badge = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$badge) admin_fail('Badge non trovato.', 404);

    if ($action === 'add') {
        $hasAssignedBy = admin_column_exists($mysqli, 'user_custom_badges', 'assigned_by');
        $adminId = (int)$adminUser['id'];
        $stmt = $mysqli->prepare(
            'INSERT INTO user_custom_badges (utente_id, badge_id, is_visible' . ($hasAssignedBy ? ', assigned_by' : '') . ')
             SELECT ?, ?, 1' . ($hasAssignedBy ? ', ?' : '') . ' FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM user_custom_badges WHERE utente_id = ? AND badge_id = ?)'
        );
        if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Assegnazione badge non valida.'), 500);
        if ($hasAssignedBy) $stmt->bind_param('iiiii', $userId, $badgeId, $adminId, $userId, $badgeId);
        else $stmt->bind_param('iiii', $userId, $badgeId, $userId, $badgeId);
    } else {
        $stmt = $mysqli->prepare('DELETE FROM user_custom_badges WHERE utente_id = ? AND badge_id = ?');
        if (!$stmt) admin_fail(admin_prepare_error($mysqli, 'Rimozione badge non valida.'), 500);
        $stmt->bind_param('ii', $userId, $badgeId);
    }
    if (!$stmt->execute()) admin_fail('Operazione sul badge non riuscita.', 500);
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        admin_log($mysqli, (int)$adminUser['id'], $action === 'add' ? 'add_badge_to_user' : 'remove_badge_from_user', $userId, [
            'badge_id' => $badgeId,
            'badge' => $badge['name'],
        ]);
    }

    admin_ok([
        'message' => $action === 'add' ? 'Badge «' . $badge['name'] . '» assegnato.' : 'Badge «' . $badge['name'] . '» rimosso.',
        'owned' => $action === 'add',
    ]);
} catch (Throwable $e) {
    admin_fail('Errore badge utente. Dettaglio: ' . $e->getMessage(), 500);
}
