<?php

/**
 * Cripsum™ — API Pullspot: tentativo
 *
 * Endpoint : POST /api/pullspot/guess.php
 * Body     : {"mode":"daily|practice", "action":"guess|skip", "character_id":123}
 * Auth     : sessione PHP + token CSRF
 *
 * Il confronto con la risposta avviene solo qui: il client non sa chi sia il
 * personaggio del giorno finché non ha finito i tentativi.
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/pullspot_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

$lang = pullspot_request_lang();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => pullspot_msg('unauthenticated', $lang), 'code' => 'UNAUTHENTICATED']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => pullspot_msg('method', $lang)]);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true);
$input = is_array($input) ? $input : $_POST;

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? null);
if (!csrf_validate(is_string($csrf) ? $csrf : null)) {
    http_response_code(419);
    echo json_encode(['error' => pullspot_msg('csrf', $lang)]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
checkBan($mysqli);

$mode = ($input['mode'] ?? 'daily') === 'practice' ? 'practice' : 'daily';

$round = pullspot_bootstrap($mysqli, $userId, $mode);
if ($round === null) {
    http_response_code(503);
    echo json_encode(['error' => pullspot_msg('no_pool', $lang), 'code' => 'NO_POOL']);
    exit;
}

$game      = $round['game'];
$character = $round['character'];

if ($game['status'] !== 'playing') {
    http_response_code(409);
    echo json_encode(['error' => pullspot_msg('finished', $lang), 'code' => 'FINISHED']);
    exit;
}

$guess = null;
if (($input['action'] ?? 'guess') !== 'skip') {
    $guess = pullspot_character_by_id($mysqli, (int)($input['character_id'] ?? 0));
    if ($guess === null) {
        http_response_code(422);
        echo json_encode(['error' => pullspot_msg('bad_guess', $lang), 'code' => 'BAD_GUESS']);
        exit;
    }
}

$game = pullspot_apply_guess($game, $guess, $character);
pullspot_save_game($mysqli, $userId, $mode, $game);

$payload = pullspot_public_state($game, $character, $mode, $round['day_index']);
$payload['ok']    = true;
$payload['stats'] = pullspot_stats($mysqli, $userId);

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
