<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    profile_json_response(['ok' => false, 'message' => 'Metodo non consentito.'], 405);
}

if (!isLoggedIn()) {
    profile_json_response(['ok' => false, 'message' => 'Devi essere loggato.'], 401);
}

if (!profile_validate_csrf($_POST['csrf_token'] ?? null)) {
    profile_json_response(['ok' => false, 'message' => 'Sessione scaduta. Ricarica la pagina.'], 419);
}

$currentUserId = profile_current_user_id();
if ($currentUserId <= 0) {
    profile_json_response(['ok' => false, 'message' => 'Utente non trovato.'], 401);
}

$requestTargetUserId = isset($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : 0;
$targetUserId = ($requestTargetUserId > 0 && profile_is_staff()) ? $requestTargetUserId : $currentUserId;

if (!profile_can_edit($targetUserId)) {
    profile_json_response(['ok' => false, 'message' => 'Non puoi modificare questo profilo.'], 403);
}

// Never persist request credentials or authorization-routing fields in a draft.
$draft = $_POST;
unset($draft['csrf_token'], $draft['target_user_id']);

// Prevent oversized payloads from exhausting the PHP session store.
if (strlen(serialize($draft)) > 1024 * 1024) {
    profile_json_response(['ok' => false, 'message' => 'La bozza supera il limite consentito.'], 413);
}

$_SESSION['profile_draft'][$targetUserId] = $draft;

profile_json_response(['ok' => true, 'message' => 'Bozza salvata.']);
