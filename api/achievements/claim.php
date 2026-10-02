<?php
/**
 * Achievement: incassa il premio in Godos.
 *
 * Il browser dice solo quale achievement (o «tutti»): quanto vale lo legge il
 * server dal catalogo, e solo per gli achievement che risultano sbloccati.
 * Doppio clic e richieste in parallelo sono coperti da ach_claim(), che
 * blocca la riga dell'utente prima di toccare qualsiasi cosa.
 *
 * Endpoint : POST /api/achievements/claim.php
 * Auth     : sessione PHP + token CSRF (header X-CSRF-Token)
 * Body     : JSON { "achievement_id": int }  oppure  { "all": true }
 */
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/achievements.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'code' => 'METHOD_NOT_ALLOWED']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'code' => 'UNAUTHENTICATED']);
    exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($payload['csrf_token'] ?? null);
if (!csrf_validate(is_string($csrf) ? $csrf : null)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'code' => 'CSRF_FAILED']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
@$mysqli->set_charset('utf8mb4');
checkBan($mysqli);

$all = !empty($payload['all']);
$achievementId = (int)($payload['achievement_id'] ?? 0);
if (!$all && $achievementId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'code' => 'INVALID_ID']);
    exit;
}

// Da qui la sessione non serve più.
cripsum_release_session();

$result = ach_claim($mysqli, $userId, $all ? null : $achievementId);

if (!$result['ok']) {
    $status = [
        'NOTHING_TO_CLAIM' => 409,
        'UNAVAILABLE'      => 503,
        'NO_USER'          => 401,
    ][$result['code']] ?? 500;
    http_response_code($status);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
