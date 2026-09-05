<?php
// Permanently erases the accounts whose 30 day grace period has expired.
//
// Security model: this endpoint can ONLY erase accounts that already requested
// their own deletion and whose timer has run out. It accepts no account id, so
// it can never be aimed at somebody else's account. Access requires either the
// shared bot key (for the scheduled job) or a signed-in admin/owner going
// through the regular admin guard.
require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/discord_oauth.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo non consentito.']);
    exit;
}

// 1. Scheduled job authenticated with the shared bot key.
$apiKey = (string)($_SERVER['HTTP_X_CRIPSUM_BOT_KEY'] ?? '');
$isCron = $apiKey !== '' && defined('CRIPSUM_BOT_API_KEY') && hash_equals(CRIPSUM_BOT_API_KEY, $apiKey);

if (!$isCron) {
    // 2. Otherwise fall back to the standard admin guard, which enforces the
    //    role check, the CSRF token and the audit logging for us.
    require_once __DIR__ . '/bootstrap.php';
}

require_once __DIR__ . '/../../includes/account_data_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

cripsum_release_session();

$limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 20;
$result = account_purge_due($mysqli, $limit);

echo json_encode([
    'ok' => $result['ok'],
    'purged' => count($result['purged']),
    'message' => $result['message'],
], JSON_UNESCAPED_UNICODE);
