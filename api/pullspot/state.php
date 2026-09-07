<?php

/**
 * Cripsum™ — API Pullspot: stato della partita
 *
 * Endpoint : GET /api/pullspot/state.php[?mode=daily|practice][&new=1]
 * Auth     : sessione PHP
 * Response : JSON
 *
 * È l'unica chiamata che manda anche l'elenco dei personaggi, perché serve a
 * riempire il campo di ricerca. La risposta del giorno non è mai qui dentro
 * finché la partita non è finita.
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => pullspot_msg('method', $lang)]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
checkBan($mysqli);

$mode    = ($_GET['mode'] ?? 'daily') === 'practice' ? 'practice' : 'daily';
$restart = $mode === 'practice' && !empty($_GET['new']);

$round = pullspot_bootstrap($mysqli, $userId, $mode, $restart);

if ($round === null) {
    http_response_code(503);
    echo json_encode(['error' => pullspot_msg('no_pool', $lang), 'code' => 'NO_POOL']);
    exit;
}

$payload = pullspot_public_state($round['game'], $round['character'], $round['mode'], $round['day_index']);
$payload['ok']         = true;
$payload['characters'] = pullspot_character_list($mysqli);
$payload['stats']      = pullspot_stats($mysqli, $userId);

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
