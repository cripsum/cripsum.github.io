<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../includes/stats_tracker.php';
require_once __DIR__ . '/../../includes/mission_tracker.php';
require_once __DIR__ . '/../../includes/subway_helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    subway_fail(405, 'bad_method', 'Metodo non consentito.', 'save_score');
}

if (!isLoggedIn()) {
    subway_fail(401, 'not_logged_in', 'Devi essere loggato per salvare il punteggio.', 'save_score');
}

if (function_exists('checkBan')) {
    checkBan($mysqli);
}

$user_id = (int)$_SESSION['user_id'];

// Check database ban status
$checkUser = $mysqli->prepare("SELECT isBannato FROM utenti WHERE id = ? LIMIT 1");
$checkUser->bind_param("i", $user_id);
$checkUser->execute();
$userRow = $checkUser->get_result()->fetch_assoc();
$checkUser->close();

if (!$userRow || !empty($userRow['isBannato'])) {
    subway_fail(403, 'banned', 'Account non autorizzato o sospeso.', 'save_score', ['user' => $user_id]);
}

// Il payload arriva come JSON sia da fetch sia da sendBeacon.
$json = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($json)) {
    $json = $_POST;
}

$time_ms = isset($json['time_ms']) ? (int)$json['time_ms'] : 0;
$run_id = strtolower(trim((string)($json['run_id'] ?? '')));
// Perche' il client ha chiuso la run (fine, moneta, uscita...): solo per i log.
$reason = substr(preg_replace('/[^a-z0-9_]/', '', strtolower((string)($json['reason'] ?? ''))), 0, 32);
$logContext = ['user' => $user_id, 'run' => substr($run_id, 0, 8), 'time_ms' => $time_ms, 'reason' => $reason];

if (!subway_valid_run_id($run_id)) {
    subway_fail(400, 'bad_request', 'Run non valida.', 'save_score', $logContext);
}

// Reinvio di una run gia' salvata (keepalive + beacon, coda dopo un reload):
// stessa risposta, senza ricontare statistiche e missioni.
$done = subway_done_run($run_id);
if ($done !== null) {
    cripsum_release_session();
    echo json_encode(array_merge(['status' => 'success', 'code' => 'duplicate', 'duplicate' => true], $done));
    exit();
}

$run = subway_open_run($run_id);
if ($run === null) {
    subway_fail(409, 'unknown_run', 'Run non trovata nella sessione.', 'save_score', $logContext);
}
$logContext['map'] = $run['map'];

// Validate time_ms: must be positive and realistic (< 30 days)
if ($time_ms <= 0 || $time_ms > 2592000000) {
    subway_close_run($run_id, null);
    subway_fail(400, 'bad_time', 'Valore del tempo non valido.', 'save_score', $logContext);
}

// Le run di allenamento non entrano in classifica.
if (($run['mode'] ?? 'original') !== 'original') {
    subway_close_run($run_id, ['is_new_best' => false, 'ignored' => true]);
    cripsum_release_session();
    echo json_encode([
        'status' => 'ignored',
        'code' => 'training',
        'message' => 'Le run di allenamento non entrano in classifica.'
    ]);
    exit();
}

// Anti-cheat: il tempo del client non puo' superare quello passato davvero
// sul server da start_run. Il margine copre la latenza e la deriva tra i due
// orologi nelle run lunghe.
$server_elapsed_ms = (microtime(true) - (float)$run['start']) * 1000;
$allowed_ms = $server_elapsed_ms + 5000 + $server_elapsed_ms * 0.005;
if ($time_ms > $allowed_ms) {
    subway_close_run($run_id, null);
    $logContext['server_ms'] = (int)$server_elapsed_ms;
    subway_fail(400, 'time_mismatch', 'Rilevata discrepanza temporale nella sessione di gioco.', 'save_score', $logContext);
}

try {
    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        throw new Exception('Connessione al database non disponibile.');
    }

    [$is_new_best, $best_time_ms, $final_map] = subway_store_best($mysqli, $user_id, $time_ms, $run['map']);

    // Get current user's global rank
    $rankStmt = $mysqli->prepare("
        SELECT COUNT(*) + 1 AS user_rank
        FROM subway_leaderboard
        WHERE best_time_ms > ?
    ");
    $rankStmt->bind_param("i", $best_time_ms);
    $rankStmt->execute();
    $rankRes = $rankStmt->get_result();
    $userRank = (int)($rankRes->fetch_assoc()['user_rank'] ?? 1);
    $rankStmt->close();

    $result = [
        'is_new_best' => $is_new_best,
        'best_time_ms' => $best_time_ms,
        'current_time_ms' => $time_ms,
        'map_slug' => $final_map,
        'rank' => $userRank
    ];

    // Da qui la run risulta salvata: un reinvio riceve la stessa risposta.
    subway_close_run($run_id, $result);
    cripsum_release_session();
} catch (Throwable $e) {
    // La run resta aperta, cosi' il nuovo tentativo del client puo' riuscire.
    error_log('[Subway save_score] errore db ' . $e->getMessage() . ' user=' . $user_id . ' run=' . substr($run_id, 0, 8));
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'code' => 'db_error',
        'message' => 'Errore durante il salvataggio del record.'
    ]);
    exit();
}

// Statistiche Rewind. `best_time_ms` è un tempo di sopravvivenza, quindi
// più alto è meglio: la metrica va tenuta al massimo, non sommata.
try {
    stats_track_many($mysqli, $user_id, [
        'subway_runs'    => 1,
        'subway_best_ms' => $time_ms,
    ]);

    trackMissionProgress($mysqli, $user_id, 'play_subway');
} catch (Throwable $trackErr) {
    error_log('[Stats subway save_score] ' . $trackErr->getMessage());
}

echo json_encode(array_merge(['status' => 'success', 'code' => 'ok'], $result));
