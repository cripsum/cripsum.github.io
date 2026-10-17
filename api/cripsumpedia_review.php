<?php
declare(strict_types=1);

/**
 * Cripsumpedia — quello che fa lo staff dalle pagine della Cripsumpedia
 * (fase 4). Solo owner e admin, tutto in POST con il token CSRF.
 *
 *   approve    approva una proposta (con «fields»: sistemata dallo staff)
 *   reject     la rifiuta, con un motivo che arriva nella Posta di chi l'ha scritta
 *   restore    rimette una voce com'era in una versione della cronologia
 *   hide       nasconde una voce (resta, con la sua cronologia)
 *   unhide     la rimette online
 */

require_once __DIR__ . '/../cripsumpedia/_bootstrap.php';
require_once __DIR__ . '/../includes/admin/admin_helpers.php';

$lang = cp_detect_lang();
$input = cp_read_input();
$action = (string)($input['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') cp_json(['ok' => false, 'message' => 'Metodo non consentito.'], 405);
cp_require_admin(true);
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
if (!cp_validate_csrf(is_string($csrf) ? $csrf : '')) cp_json(['ok' => false, 'message' => 'Sessione scaduta. Ricarica la pagina.'], 419);
if (!cp_v2_ready($mysqli)) cp_json(['ok' => false, 'message' => cp_cannot_write_text('migration', $lang, $mysqli)], 503);

$staffId = (int)($_SESSION['user_id'] ?? 0);
$log = static function (string $what, ?int $userId, array $details) use ($mysqli, $staffId): void {
    admin_log($mysqli, $staffId, 'cripsumpedia_' . $what, $userId, $details);
};

if ($action === 'approve') {
    $id = (int)($input['id'] ?? 0);
    $revision = cp_revision_find($mysqli, $id);
    if (!$revision || $revision['status'] !== 'pending') cp_json(['ok' => false, 'message' => 'Questa proposta è già stata gestita.'], 409);

    $edited = null;
    if (is_array($input['fields'] ?? null)) {
        ['data' => $edited, 'errors' => $errors] = cp_data_from_input($mysqli, $input['fields'], $revision['data_array'], true, 'it');
        if ($errors) cp_json(['ok' => false, 'errors' => $errors, 'message' => reset($errors)], 422);
    }

    $result = cp_approve($mysqli, $id, $staffId, $edited);
    if (!$result['ok']) cp_json(['ok' => false, 'message' => $result['error']], 409);
    $log('approve', (int)$revision['user_id'] ?: null, ['revision_id' => $id, 'entry_id' => $result['entry_id'], 'title' => $revision['title'], 'edited' => $edited !== null]);
    $entry = cp_fetch_entry($mysqli, null, (string)$result['entry_id'], true);
    cp_json([
        'ok' => true,
        'url' => $entry ? cp_entry_url($entry, $lang) : cp_url('home', [], $lang),
        'conflicts' => $result['conflicts'],
        'next' => cp_pending_list($mysqli, 1)[0]['id'] ?? null,
    ]);
}

if ($action === 'reject') {
    $id = (int)($input['id'] ?? 0);
    $revision = cp_revision_find($mysqli, $id);
    $result = cp_reject($mysqli, $id, $staffId, (string)($input['reason'] ?? ''), (string)($input['note'] ?? ''));
    if (!$result['ok']) cp_json(['ok' => false, 'message' => $result['error']], 422);
    $log('reject', (int)($revision['user_id'] ?? 0) ?: null, ['revision_id' => $id, 'title' => $revision['title'] ?? '', 'reason' => (string)$input['reason']]);
    cp_json(['ok' => true, 'next' => cp_pending_list($mysqli, 1)[0]['id'] ?? null]);
}

if ($action === 'restore') {
    $id = (int)($input['revision_id'] ?? 0);
    $result = cp_restore($mysqli, $id, $staffId);
    if (!$result['ok']) cp_json(['ok' => false, 'message' => $result['error']], 409);
    $log('restore', null, ['revision_id' => $id, 'entry_id' => $result['entry_id']]);
    $entry = cp_fetch_entry($mysqli, null, (string)$result['entry_id'], true);
    cp_json(['ok' => true, 'url' => $entry ? cp_entry_url($entry, $lang) : cp_url('home', [], $lang)]);
}

if ($action === 'hide' || $action === 'unhide') {
    $id = (int)($input['entry_id'] ?? 0);
    $entry = cp_fetch_entry($mysqli, null, (string)$id, true, true);
    if (!$entry) cp_json(['ok' => false, 'message' => 'Voce non trovata.'], 404);
    $done = $action === 'hide' ? cp_hide_entry($mysqli, $id, $staffId) : cp_unhide_entry($mysqli, $id, $staffId);
    if (!$done) cp_json(['ok' => false, 'message' => $action === 'hide' ? 'La voce è già nascosta.' : 'La voce è già online.'], 409);
    $log($action, null, ['entry_id' => $id, 'title' => $entry['title']]);
    cp_json(['ok' => true]);
}

cp_json(['ok' => false, 'message' => 'Azione non valida.'], 400);
