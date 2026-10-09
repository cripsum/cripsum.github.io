<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/maintenance.php';

/**
 * Manutenzione del sito (pannello: Sito > Manutenzione).
 *
 * GET            lo stato di adesso, con i nomi di chi puo' entrare.
 * GET ?preview=1 la pagina di manutenzione come la vedrebbe un visitatore,
 *                col motivo passato nell'indirizzo: non salva niente.
 * POST           salva. Solo l'owner: chiudere il sito a tutti non e' una
 *                cosa da lasciare a ogni admin.
 *
 * Lo stato sta nella tabella site_maintenance (una riga sola); la tabella
 * utenti serve a dare un nome agli id dell'elenco.
 */

$maintMe = (int)($adminUser['id'] ?? 0);
$maintCanEdit = admin_is_owner_role($adminUser['ruolo'] ?? '');

/** Nome e ruolo degli utenti dell'elenco, nell'ordine dell'elenco. Gli id che non esistono piu' spariscono. */
function maintenance_admin_users(mysqli $mysqli, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    if (!$ids) {
        return [];
    }

    $found = [];
    $result = $mysqli->query('SELECT id, username, ruolo FROM utenti WHERE id IN (' . implode(',', $ids) . ')');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $found[(int)$row['id']] = ['id' => (int)$row['id'], 'username' => (string)$row['username'], 'ruolo' => (string)($row['ruolo'] ?? 'utente')];
        }
        $result->free();
    }

    $users = [];
    foreach ($ids as $id) {
        if (isset($found[$id])) {
            $users[] = $found[$id];
        }
    }

    return $users;
}

/** Lo stato come lo vuole il pannello. */
function maintenance_admin_view(mysqli $mysqli, array $state, int $me, bool $canEdit): array
{
    $users = maintenance_admin_users($mysqli, $state['allowed']);
    foreach ($users as &$user) {
        $user['me'] = $user['id'] === $me;
    }
    unset($user);

    $by = $state['updated_by'] > 0 ? maintenance_admin_users($mysqli, [$state['updated_by']]) : [];

    return [
        'state' => [
            'enabled' => $state['enabled'],
            'reason_it' => $state['reason_it'],
            'reason_en' => $state['reason_en'],
            'since' => $state['since'],
            'until' => $state['until'],
            'allowed' => $users,
            'updated_at' => $state['updated_at'],
            'updated_by' => $by ? $by[0]['username'] : null,
        ],
        'can_edit' => $canEdit,
        'me' => $me,
        'limits' => ['reason' => CRIPSUM_MAINTENANCE_REASON_MAX, 'allowed' => CRIPSUM_MAINTENANCE_ALLOWED_MAX],
        // Falso finche' non si applica migrations/2026_10_09_site_maintenance.sql.
        'ready' => cripsum_maintenance_ready($mysqli),
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!empty($_GET['preview'])) {
            require_once __DIR__ . '/../../includes/maintenance_page.php';
            $until = (int)($_GET['until'] ?? 0);
            $draft = cripsum_maintenance_normalize([
                'enabled' => true,
                'reason_it' => (string)($_GET['reason_it'] ?? ''),
                'reason_en' => (string)($_GET['reason_en'] ?? ''),
                'since' => time(),
                'until' => $until > time() ? $until : null,
            ]);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store, private');
            header('X-Robots-Tag: noindex');
            echo cripsum_maintenance_page($draft, ($_GET['lang'] ?? 'it') === 'en' ? 'en' : 'it', ['preview' => true]);
            exit;
        }

        admin_ok(maintenance_admin_view($mysqli, cripsum_maintenance_state($mysqli, true), $maintMe, $maintCanEdit));
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        admin_fail('Metodo non valido.', 405);
    }
    if (!$maintCanEdit) {
        admin_fail('Solo l\'owner può mettere il sito in manutenzione.', 403);
    }

    if (!cripsum_maintenance_ready($mysqli)) {
        admin_fail('Manca la tabella della manutenzione: applica la migrazione 2026_10_09_site_maintenance.sql.', 409);
    }

    $input = admin_input();
    $before = cripsum_maintenance_state($mysqli, true);
    $enabled = !empty($input['enabled']);
    $draft = cripsum_maintenance_normalize([
        'enabled' => $enabled,
        'reason_it' => (string)($input['reason_it'] ?? ''),
        'reason_en' => (string)($input['reason_en'] ?? ''),
        'until' => $input['until'] ?? null,
        'allowed' => is_array($input['allowed'] ?? null) ? $input['allowed'] : [],
    ]);

    if ($enabled && (function_exists('mb_strlen') ? mb_strlen($draft['reason_it']) : strlen($draft['reason_it'])) < 5) {
        admin_fail('Scrivi il motivo della manutenzione: lo legge chi arriva sul sito.', 422, ['field' => 'reason_it']);
    }
    if ($draft['until'] !== null && $draft['until'] <= time()) {
        admin_fail('L\'ora di riapertura prevista è già passata.', 422, ['field' => 'until']);
    }

    // Nell'elenco restano solo utenti che esistono.
    $draft['allowed'] = array_column(maintenance_admin_users($mysqli, $draft['allowed']), 'id');

    $saved = cripsum_maintenance_save($mysqli, $draft, $maintMe);
    if ($saved === null) {
        admin_fail('Non riesco a salvare lo stato della manutenzione.', 500);
    }

    $action = $saved['enabled'] ? ($before['enabled'] ? 'maintenance_update' : 'maintenance_on') : 'maintenance_off';
    if ($saved['enabled'] || $before['enabled']) {
        admin_log($mysqli, $maintMe, $action, null, [
            'reason' => $saved['reason_it'],
            'until' => $saved['until'] ? date('Y-m-d H:i', $saved['until']) : null,
            'allowed' => count($saved['allowed']),
        ]);
    }

    admin_ok(maintenance_admin_view($mysqli, $saved, $maintMe, $maintCanEdit) + ['action' => $action]);
} catch (Throwable $e) {
    error_log('[admin/maintenance] ' . $e->getMessage());
    admin_fail('Errore nella manutenzione.', 500);
}
