<?php

/**
 * Cripsum™ — API Rewind: GET
 *
 * Restituisce il Rewind dell'utente autenticato, generandolo se la copia in
 * cache è scaduta.
 *
 * Endpoint : GET /api/rewind/get.php[?period=r365|2026][&refresh=1]
 * Auth     : sessione PHP
 * Response : JSON
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/rewind_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

// Gli errori di questo endpoint finiscono a schermo cosi come sono:
// vanno risolti nella lingua di chi guarda.
$lang = rewind_request_lang();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => rewind_msg('unauthenticated', $lang), 'code' => 'UNAUTHENTICATED']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => rewind_msg('method', $lang)]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
checkBan($mysqli);

// Stessa porta della pagina: senza questo controllo il payload sarebbe
// raggiungibile lo stesso chiamando l'endpoint a mano.
if (!rewind_user_can_view()) {
    http_response_code(403);
    echo json_encode(['error' => rewind_locked_message($lang), 'code' => 'NOT_RELEASED']);
    exit;
}

if (!rewind_available($mysqli)) {
    http_response_code(503);
    echo json_encode([
        'error' => rewind_msg('schema_missing', $lang),
        'code'  => 'SCHEMA_MISSING',
    ]);
    exit;
}

$periodKey = (string)($_GET['period'] ?? 'all');
if (!preg_match('/^(all|r365|\d{4})$/', $periodKey)) {
    $periodKey = 'all';
}

// Una rigenerazione forzata è cara: la concediamo al massimo ogni cinque
// minuti per sessione, così un pulsante premuto a ripetizione non diventa
// un modo per far lavorare il database a vuoto.
$refresh = !empty($_GET['refresh']);
if ($refresh) {
    $lastRefresh = (int)($_SESSION['rewind_last_refresh'] ?? 0);
    if ((time() - $lastRefresh) < 300) {
        $refresh = false;
    } else {
        $_SESSION['rewind_last_refresh'] = time();
    }
}

// La sessione non serve più da qui in avanti.
cripsum_release_session();

try {
    $payload = rewind_get_or_build($mysqli, $userId, $periodKey, $refresh);
} catch (Throwable $e) {
    error_log('[api/rewind/get] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => rewind_msg('internal', $lang)]);
    exit;
}

if ($payload === null) {
    http_response_code(500);
    echo json_encode(['error' => rewind_msg('build_failed', $lang)]);
    exit;
}

// Occasione buona per far avanzare il lavoro schedulato: la richiesta è già
// asincrona e l'utente non aspetta questa parte.
rewind_maybe_precompute($mysqli);

echo json_encode(['ok' => true, 'rewind' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
