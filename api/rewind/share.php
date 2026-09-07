<?php

/**
 * Cripsum™ — API Rewind: condivisione
 *
 * Attiva o revoca il link pubblico del proprio Rewind.
 *
 * Endpoint : POST /api/rewind/share.php
 * Body     : {"action": "enable"|"disable", "period": "all"}
 * Auth     : sessione PHP + token CSRF
 *
 * Pubblicare un Rewind è un'azione visibile all'esterno, quindi passa da una
 * richiesta POST con CSRF e non da un GET: nessun link ostile deve poter
 * rendere pubblico il riepilogo di qualcun altro.
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/rewind_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

// Serve sia per i messaggi di errore sia per il prefisso del link
// pubblico: chi condivide dall'inglese ottiene un link inglese.
$lang = rewind_request_lang();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => rewind_msg('unauthenticated', $lang)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => rewind_msg('method', $lang)]);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true);
$input = is_array($input) ? $input : $_POST;

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? null);
if (!csrf_validate(is_string($csrf) ? $csrf : null)) {
    http_response_code(419);
    echo json_encode(['error' => rewind_msg('csrf', $lang)]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
checkBan($mysqli);

$periodKey = (string)($input['period'] ?? 'all');
if (!preg_match('/^(all|r365|\d{4})$/', $periodKey)) {
    $periodKey = 'all';
}

$action = (string)($input['action'] ?? '');

if ($action === 'enable') {
    $token = rewind_enable_share($mysqli, $userId, $periodKey);
    if ($token === null) {
        http_response_code(409);
        echo json_encode(['error' => rewind_msg('no_rewind', $lang)]);
        exit;
    }

    echo json_encode([
        'ok'        => true,
        'is_public' => true,
        'token'     => $token,
        'url'       => rewind_share_url($token, $lang),
    ]);
    exit;
}

if ($action === 'disable') {
    $ok = rewind_disable_share($mysqli, $userId, $periodKey);
    echo json_encode(['ok' => $ok, 'is_public' => false, 'token' => null]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => rewind_msg('bad_action', $lang)]);
