<?php

/**
 * Cripsum™ — API Animespot: tentativo
 *
 * Endpoint : POST /api/animespot/guess.php
 * Body     : {"action":"guess|skip", "anime_id":123}
 * Auth     : sessione PHP + token CSRF
 *
 * Il confronto con la risposta avviene solo qui: il client non sa di quale
 * anime sia la sigla finché non ha finito i tentativi.
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/animespot_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

$lang = animespot_request_lang();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => animespot_msg('unauthenticated', $lang), 'code' => 'UNAUTHENTICATED']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => animespot_msg('method', $lang)]);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true);
$input = is_array($input) ? $input : $_POST;

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? null);
if (!csrf_validate(is_string($csrf) ? $csrf : null)) {
    http_response_code(419);
    echo json_encode(['error' => animespot_msg('csrf', $lang)]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
checkBan($mysqli);

$current = animespot_bootstrap($mysqli);
if ($current === null) {
    http_response_code(503);
    echo json_encode(['error' => animespot_msg('no_catalog', $lang), 'code' => 'NO_CATALOG']);
    exit;
}

$round = $current['round'];
$track = $current['track'];

if ($round['status'] !== 'playing') {
    http_response_code(409);
    echo json_encode(['error' => animespot_msg('finished', $lang), 'code' => 'FINISHED']);
    exit;
}

$guess   = null;
$correct = false;

if (($input['action'] ?? 'guess') !== 'skip') {
    $guess = animespot_anime($mysqli, (int)($input['anime_id'] ?? 0));
    if ($guess === null) {
        http_response_code(422);
        echo json_encode(['error' => animespot_msg('bad_guess', $lang), 'code' => 'BAD_GUESS']);
        exit;
    }

    if (animespot_already_guessed($round, (int)$guess['id'])) {
        http_response_code(409);
        echo json_encode(['error' => animespot_msg('repeat', $lang), 'code' => 'REPEAT']);
        exit;
    }

    $correct = animespot_is_correct($mysqli, (int)$guess['id'], $track);
}

$round = animespot_apply_guess($round, $guess, $correct);
$round = animespot_record($mysqli, $userId, $round);
animespot_round_save($round);

$payload = animespot_public_round($round, $track, $lang);
$payload['ok']      = true;
$payload['options'] = animespot_options();
$payload['stats']   = animespot_stats($mysqli, $userId);

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
