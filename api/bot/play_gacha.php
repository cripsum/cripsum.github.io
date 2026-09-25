<?php
declare(strict_types=1);

/**
 * Pull dal comando /gacha del bot.
 *
 * Prima simulava una sessione e includeva api_gacha_pull.php, che pero'
 * chiede il token CSRF: il bot non ce l'ha, e la pull finiva in errore.
 * Adesso chiama direttamente il motore, con la stessa risposta di sempre.
 */

if (!defined('CRIPSUM_STATELESS_REQUEST')) {
    define('CRIPSUM_STATELESS_REQUEST', true);
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/discord_oauth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/gacha/engine.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$apiKey = (string)($_SERVER['HTTP_X_CRIPSUM_BOT_KEY'] ?? '');
if ($apiKey === '' || !defined('CRIPSUM_BOT_API_KEY') || !hash_equals((string)CRIPSUM_BOT_API_KEY, $apiKey)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Invalid or missing X-Cripsum-Bot-Key.']);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true);
$input = is_array($input) ? $input : [];
$discordId = trim((string)($input['discord_id'] ?? ''));
$bannerId = trim((string)($input['banner_id'] ?? 'standard'));

if ($discordId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing discord_id in request body.']);
    exit;
}
if ($bannerId !== 'standard' && !ctype_digit($bannerId)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'banner_id non valido', 'code' => 'INVALID_BANNER']);
    exit;
}

$stmt = $mysqli->prepare('SELECT id FROM utenti WHERE discord_id = ? LIMIT 1');
$stmt->bind_param('s', $discordId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['status' => 'error', 'linked' => false, 'message' => 'User not linked on Cripsum.com.']);
    exit;
}

try {
    $result = gacha_pull($mysqli, (int)$user['id'], $bannerId, 1, ['source' => 'bot']);
    echo json_encode(gacha_response_single($result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    [$status, $payload] = gacha_response_error($e);
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

gacha_flush_announcements();
