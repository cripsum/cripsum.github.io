<?php

/**
 * Cripsum™ — API Animespot: stato della partita
 *
 * Endpoint : GET /api/animespot/state.php[?new=1]
 * Auth     : sessione PHP
 * Response : JSON
 *
 * Manda la partita in corso, le opzioni e le statistiche. La risposta non è
 * mai qui dentro finché la partita non è finita.
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => animespot_msg('method', $lang)]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
checkBan($mysqli);

if (!animespot_catalog_ready($mysqli)) {
    http_response_code(503);
    echo json_encode(['error' => animespot_msg('no_catalog', $lang), 'code' => 'NO_CATALOG']);
    exit;
}

$round = animespot_bootstrap($mysqli, !empty($_GET['new']));

if ($round === null) {
    http_response_code(503);
    echo json_encode(['error' => animespot_msg('no_pool', $lang), 'code' => 'NO_POOL']);
    exit;
}

$options = animespot_options();

$payload = animespot_public_round($round['round'], $round['track'], $lang);
$payload['ok']      = true;
$payload['options'] = $options;
// Due conteggi diversi: quante sigle ha ogni difficoltà dentro l'epoca scelta,
// e quante ne ha ogni epoca in tutto. Servono ai due gruppi di pulsanti, che
// mostrano il numero accanto a ogni voce e spengono quelle vuote.
$payload['pool']    = animespot_counts($mysqli, $options['era']);
$payload['eras']    = animespot_era_counts($mysqli);
$payload['stats']   = animespot_stats($mysqli, $userId);

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
