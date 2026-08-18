<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/session_init.php';

header('Content-Type: application/json; charset=utf-8');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Metodo non consentito.'
    ]);
    exit();
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Devi essere loggato per salvare il punteggio.'
    ]);
    exit();
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
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Account non autorizzato o sospeso.'
    ]);
    exit();
}

// Parse request payload
$rawData = file_get_contents('php://input');
$json = json_decode($rawData, true);

$time_ms = 0;
$map_slug = '';
$run_token = '';

if (is_array($json)) {
    $time_ms = isset($json['time_ms']) ? (int)$json['time_ms'] : 0;
    $map_slug = isset($json['map_slug']) ? trim((string)$json['map_slug']) : '';
    $run_token = isset($json['run_token']) ? trim((string)$json['run_token']) : '';
} else {
    $time_ms = isset($_POST['time_ms']) ? (int)$_POST['time_ms'] : 0;
    $map_slug = isset($_POST['map_slug']) ? trim((string)$_POST['map_slug']) : '';
    $run_token = isset($_POST['run_token']) ? trim((string)$_POST['run_token']) : '';
}

// Whitelist of valid map slugs
$valid_maps = [
    'london', 'zurich', 'beijing', 'berlin', 'havana', 'houston',
    'iceland', 'mexico', 'miami', 'monaco', 'neworleans', 'sanfrancisco',
    'saintpetersburg', 'winterholiday', 'tokyo', 'cairo', 'paris',
    'bangkok', 'buenosaires', 'moscow', 'newyork'
];

$map_slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', $map_slug));
if (!in_array($map_slug, $valid_maps, true)) {
    $map_slug = 'london';
}

// Validate time_ms: must be positive and realistic (< 30 days)
if ($time_ms <= 0 || $time_ms > 2592000000) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Valore del tempo non valido.'
    ]);
    exit();
}

// Anti-Cheat: Validate run token and elapsed server time
if (!empty($_SESSION['subway_run_token']) && !empty($_SESSION['subway_run_start'])) {
    if (empty($run_token) || !hash_equals($_SESSION['subway_run_token'], $run_token)) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Token di sessione run non valido.'
        ]);
        exit();
    }

    $server_now = microtime(true);
    $server_elapsed_ms = ($server_now - (float)$_SESSION['subway_run_start']) * 1000;

    // Reject if client time exceeds real wall-clock time by more than 5 seconds buffer
    if ($time_ms > ($server_elapsed_ms + 5000)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Rilevata discrepanza temporale nella sessione di gioco.'
        ]);
        exit();
    }

    // Invalidate the token to prevent replay
    unset($_SESSION['subway_run_token']);
    unset($_SESSION['subway_run_start']);
    unset($_SESSION['subway_run_map']);
}

try {
    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        throw new Exception('Connessione al database non disponibile.');
    }

    // Check existing record for this user
    $stmt = $mysqli->prepare("SELECT best_time_ms, map_slug FROM subway_leaderboard WHERE utente_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = $res->fetch_assoc();
    $stmt->close();

    $is_new_best = false;
    $best_time_ms = $time_ms;
    $final_map = $map_slug;

    if (!$existing) {
        $ins = $mysqli->prepare("
            INSERT INTO subway_leaderboard (utente_id, best_time_ms, map_slug, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $ins->bind_param("iis", $user_id, $time_ms, $map_slug);
        $ins->execute();
        $ins->close();
        $is_new_best = true;
    } else {
        $existing_best = (int)$existing['best_time_ms'];
        if ($time_ms > $existing_best) {
            $upd = $mysqli->prepare("
                UPDATE subway_leaderboard
                SET best_time_ms = ?, map_slug = ?, updated_at = NOW()
                WHERE utente_id = ?
            ");
            $upd->bind_param("isi", $time_ms, $map_slug, $user_id);
            $upd->execute();
            $upd->close();
            $is_new_best = true;
        } else {
            $best_time_ms = $existing_best;
            $final_map = $existing['map_slug'] ?? $map_slug;
        }
    }

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

    echo json_encode([
        'status' => 'success',
        'is_new_best' => $is_new_best,
        'best_time_ms' => $best_time_ms,
        'current_time_ms' => $time_ms,
        'map_slug' => $final_map,
        'rank' => $userRank
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Errore durante il salvataggio del record.'
    ]);
}
