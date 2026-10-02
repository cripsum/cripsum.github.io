<?php
/**
 * Contenuto del menu delle notifiche in navbar: quello che aspetta l'utente
 * (richieste, inviti, chat non lette, ticket) e gli ultimi messaggi della
 * posta. Si chiama solo quando il menu viene aperto.
 */
require_once __DIR__ . '/../../config/session_init.php';

if (empty($_SESSION['user_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit();
}

$panelUserId = (int)$_SESSION['user_id'];
$panelRole = (string)($_SESSION['ruolo'] ?? 'utente');
cripsum_release_session();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/chat_config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/chat_v2_helpers.php';
require_once __DIR__ . '/../../includes/notify.php';
require_once __DIR__ . '/../../includes/inbox_core.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

try {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        @$mysqli->set_charset('utf8mb4');
    }

    $counts = ib_counts($mysqli, $panelUserId);

    echo json_encode([
        'ok' => true,
        'counters' => notify_counters($mysqli, $panelUserId, $panelRole),
        'notifications' => notify_panel($mysqli, $panelUserId, $panelRole),
        'mail' => ib_latest($mysqli, $panelUserId, 6),
        'mail_unread' => $counts['unread'],
        'rewards_pending' => $counts['rewards'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('[api/notify/panel] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
