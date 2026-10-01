<?php
/**
 * Contatori della navbar e avvisi da mostrare.
 *
 * GET since=<numero>   avvisi per gli eventi personali successivi a quel
 *                      numero (quello che api/rt/poll.php ha appena segnalato)
 *     (senza since)    solo i contatori
 *
 * Questa richiesta passa dal database, quindi il browser la fa solo quando
 * il controllo leggero dice che è arrivato qualcosa che merita un avviso, e
 * ogni tanto per riallineare i numeri.
 */
require_once __DIR__ . '/../../config/session_init.php';

if (empty($_SESSION['user_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit();
}

$notifyUserId = (int)$_SESSION['user_id'];
$notifyRole = (string)($_SESSION['ruolo'] ?? 'utente');
cripsum_release_session();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/chat_config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/chat_v2_helpers.php';
require_once __DIR__ . '/../../includes/notify.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

try {
    $items = [];
    if (isset($_GET['since'])) {
        $since = (int)$_GET['since'];
        $stamp = rt_read('u:' . $notifyUserId);
        $events = array_values(array_filter(
            isset($stamp['events']) && is_array($stamp['events']) ? $stamp['events'] : [],
            static fn($event) => (int)($event['s'] ?? 0) > $since
        ));
        $items = notify_items($mysqli, $notifyUserId, $events);
    }

    echo json_encode([
        'ok' => true,
        'counters' => notify_counters($mysqli, $notifyUserId, $notifyRole),
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('[api/notify] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
