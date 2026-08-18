<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/session_init.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Devi essere autenticato per avviare una run.'
    ]);
    exit();
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
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Account non autorizzato o sospeso.'
    ]);
    exit();
}

$rawData = file_get_contents('php://input');
$json = json_decode($rawData, true);

$map_slug = is_array($json) ? ($json['map_slug'] ?? '') : ($_POST['map_slug'] ?? '');
$map_slug = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$map_slug), 0, 50);
if (empty($map_slug)) {
    $map_slug = 'london';
}

// Generate cryptographic run session token and record server start timestamp
$run_token = bin2hex(random_bytes(24));
$_SESSION['subway_run_token'] = $run_token;
$_SESSION['subway_run_start'] = microtime(true);
$_SESSION['subway_run_map'] = $map_slug;

echo json_encode([
    'status' => 'success',
    'run_token' => $run_token,
    'started_at' => (int)($_SESSION['subway_run_start'] * 1000)
]);
