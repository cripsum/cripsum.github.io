<?php
declare(strict_types=1);

/**
 * Banner attivi e pity di un utente collegato, per /banners e per
 * l'autocompletamento di /gacha. Stessa risposta di api_gacha_banners.php,
 * che fa il lavoro; qui si trova solo l'utente dal suo id Discord.
 */

// Il bot non ha cookie: niente sessione (vedi config/session_init.php).
if (!defined('CRIPSUM_STATELESS_REQUEST')) {
    define('CRIPSUM_STATELESS_REQUEST', true);
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/discord_oauth.php';

header('Content-Type: application/json; charset=utf-8');

$apiKey = (string)($_SERVER['HTTP_X_CRIPSUM_BOT_KEY'] ?? '');
if ($apiKey === '' || !defined('CRIPSUM_BOT_API_KEY') || !hash_equals((string)CRIPSUM_BOT_API_KEY, $apiKey)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Invalid or missing X-Cripsum-Bot-Key.']);
    exit;
}

$discordId = trim((string)($_GET['discord_id'] ?? ''));
if ($discordId === '') {
    $input = json_decode((string)file_get_contents('php://input'), true);
    $discordId = is_array($input) ? trim((string)($input['discord_id'] ?? '')) : '';
}

if ($discordId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing discord_id.']);
    exit;
}

$stmt = $mysqli->prepare('SELECT id FROM utenti WHERE discord_id = ? LIMIT 1');
$stmt->bind_param('s', $discordId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['status' => 'error', 'linked' => false, 'message' => 'Account Discord non collegato.']);
    exit;
}

$GLOBALS['gacha_bot_user_id'] = (int)$user['id'];
$_SERVER['REQUEST_METHOD'] = 'GET';

require __DIR__ . '/../api_gacha_banners.php';
exit;
