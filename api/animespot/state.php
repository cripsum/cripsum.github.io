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

// `next` fa passare al posto successivo della serie: è il tasto "Prossima"
// della schermata di risposta. `new` invece ripesca la sigla di questo posto.
$advance = !empty($_GET['next']);

if ($advance) {
    $next = animespot_next_slot(animespot_series(), animespot_slot());
    if ($next > 0) animespot_slot_save($next);
    // Zero vuol dire serie finita: ci pensa il bootstrap, che ne pesca un'altra.
}

$current = animespot_bootstrap($mysqli, !empty($_GET['new']), $advance);

if ($current === null) {
    http_response_code(503);
    echo json_encode(['error' => animespot_msg('no_pool', $lang), 'code' => 'NO_POOL']);
    exit;
}

echo json_encode(
    animespot_full_payload($mysqli, $userId, $lang, $current['round'], $current['track'], $current['slot'], $current['series']),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
