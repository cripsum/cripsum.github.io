<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../includes/subway_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    subway_fail(405, 'bad_method', 'Metodo non consentito.', 'start_run');
}

if (!isLoggedIn()) {
    subway_fail(401, 'not_logged_in', 'Devi essere autenticato per avviare una run.', 'start_run');
}

if (function_exists('checkBan')) {
    checkBan($mysqli);
}

// Check if user is banned in database
$userId = (int)$_SESSION['user_id'];
$checkUser = $mysqli->prepare("SELECT isBannato FROM utenti WHERE id = ? LIMIT 1");
$checkUser->bind_param("i", $userId);
$checkUser->execute();
$userRow = $checkUser->get_result()->fetch_assoc();
$checkUser->close();

if (!$userRow || !empty($userRow['isBannato'])) {
    subway_fail(403, 'banned', 'Account non autorizzato o sospeso.', 'start_run', ['user' => $userId]);
}

$json = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($json)) {
    $json = $_POST;
}

$runId = strtolower(trim((string)($json['run_id'] ?? '')));
if (!subway_valid_run_id($runId)) {
    subway_fail(400, 'bad_request', 'Id della run non valido.', 'start_run', ['user' => $userId]);
}

$mapSlug = subway_normalize_map($json['map_slug'] ?? '');
if ($mapSlug === '') {
    subway_fail(400, 'bad_map', 'Mappa non valida.', 'start_run', ['user' => $userId, 'map' => substr((string)($json['map_slug'] ?? ''), 0, 50)]);
}

$mode = (string)($json['mode'] ?? 'original');
if (!in_array($mode, SUBWAY_MODES, true)) {
    $mode = 'original';
}

// Il timer del client parte prima che la richiesta arrivi qui: il client
// dice quanti ms sono passati quando l'ha spedita (piu' di zero solo se ha
// dovuto riprovare), e l'inizio della run si retrodata di altrettanto.
$offsetMs = max(0, min(SUBWAY_MAX_START_OFFSET_MS, (int)($json['elapsed_ms'] ?? 0)));
$run = subway_register_run($runId, $mapSlug, $mode, microtime(true) - $offsetMs / 1000);
cripsum_release_session();

echo json_encode([
    'status' => 'success',
    'code' => 'ok',
    'run_id' => $runId,
    'mode' => $run['mode'],
    'started_at' => (int)($run['start'] * 1000)
]);
