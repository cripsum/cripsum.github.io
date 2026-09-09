<?php

/**
 * Cripsum™ — API Animespot: ricerca degli anime
 *
 * Endpoint : GET /api/animespot/search.php?q=...
 * Auth     : sessione PHP
 * Response : JSON
 *
 * L'elenco delle risposte possibili non si può mandare tutto al client: sono
 * quasi cinquemila serie con tutti i loro titoli alternativi. La ricerca sta
 * qui, e restituisce solo le dieci righe che servono a scegliere.
 *
 * Non rivela niente: l'indice è lo stesso per tutti e non sa quale sia la
 * sigla in corso.
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

checkBan($mysqli);

$query = trim((string)($_GET['q'] ?? ''));
$limit = (int)($_GET['limit'] ?? 10);

if (mb_strlen($query, 'UTF-8') > 120) {
    $query = mb_substr($query, 0, 120, 'UTF-8');
}

$options = animespot_options();
$results = $query === '' ? [] : animespot_search($mysqli, $query, $limit, $options['ricerca']);

// La ricerca non tocca la sessione: liberarla lascia passare le altre
// richieste che il gioco fa mentre si scrive.
cripsum_release_session();

echo json_encode(
    ['ok' => true, 'q' => $query, 'results' => $results],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
