<?php

/**
 * Cripsum™ — API Animespot: classifica
 *
 * Endpoint : GET /api/animespot/board.php?period=settimana|mese|sempre
 * Auth     : sessione PHP
 * Response : JSON
 *
 * Chi ha indovinato più sigle, nei tre periodi. La posizione di chi guarda
 * arriva a parte, perché una classifica in cui non ti trovi non ti dice se
 * stai salendo — e quindi non ti fa giocare.
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

$period = (string)($_GET['period'] ?? 'settimana');
if (!in_array($period, ANIMESPOT_PERIODS, true)) $period = 'settimana';

$board = animespot_board($mysqli, $period, 50);
$me    = animespot_board_me($mysqli, $userId, $period);

// La classifica non tocca la sessione: liberarla lascia passare le richieste
// che il gioco fa mentre il pannello è aperto.
cripsum_release_session();

echo json_encode([
    'ok'        => true,
    'period'    => $period,
    'rows'      => $board,
    'me'        => $me,
    'persisted' => animespot_state_ready($mysqli),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
