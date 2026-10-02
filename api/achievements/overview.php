<?php
/**
 * Achievement: tutto quello che serve alla pagina, in una richiesta.
 *
 * Catalogo, sbloccati, progressi contati dal server, rarità e premi da
 * riscuotere. La pagina riceve gli stessi dati già dentro l'HTML: questo
 * endpoint serve a riallinearla dopo uno sblocco o un premio incassato.
 *
 * Endpoint : GET /api/achievements/overview.php?lang=it|en
 * Auth     : sessione PHP
 */
require_once __DIR__ . '/../../config/session_init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'code' => 'UNAUTHENTICATED']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : 'it';

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/achievements.php';

// I conti non toccano la sessione: si libera subito, così le altre
// richieste della stessa scheda non restano in coda dietro a questa.
cripsum_release_session();

try {
    @$mysqli->set_charset('utf8mb4');
    echo json_encode(ach_overview($mysqli, $userId, $lang), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('[API achievements/overview] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'code' => 'INTERNAL_ERROR']);
}
