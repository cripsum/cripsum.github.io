<?php
declare(strict_types=1);

/**
 * Banner attivi, senza dati di un utente: per gli annunci e i comandi
 * pubblici del bot. Stessa forma di sempre ({standard, eventi[]}).
 */

if (!defined('CRIPSUM_STATELESS_REQUEST')) {
    define('CRIPSUM_STATELESS_REQUEST', true);
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/discord_oauth.php';
require_once __DIR__ . '/../../includes/gacha/public.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$apiKey = $_SERVER['HTTP_X_CRIPSUM_BOT_KEY'] ?? '';
if (empty($apiKey) || !hash_equals((string)CRIPSUM_BOT_API_KEY, (string)$apiKey)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

try {
    $payload = gacha_banners_legacy_payload($mysqli, 0);
} catch (Throwable $e) {
    error_log('[bot public_banners] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Banner schema is not ready.']);
    exit;
}

// Senza utente i campi personali non hanno senso.
$strip = static function (?array $banner): ?array {
    if ($banner === null) {
        return null;
    }
    unset($banner['pity_utente'], $banner['pity_condiviso'], $banner['garantito_attivo']);
    return $banner;
};

echo json_encode([
    'status' => 'success',
    'standard' => $strip($payload['standard']),
    'eventi' => array_map($strip, $payload['eventi']),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
