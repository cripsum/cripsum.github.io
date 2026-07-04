<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/discord_oauth.php';
require_once __DIR__ . '/../../includes/redeem_codes.php';

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

$codes = [];
foreach (cripsum_redeem_codes() as $code => $entry) {
    if (!cripsum_redeem_code_is_available($entry)) {
        continue;
    }

    $description = $entry['descrizione'] ?? null;
    if (is_array($description)) {
        $description = $description['en'] ?? $description['it'] ?? null;
    }

    $expiresAt = cripsum_redeem_code_date(isset($entry['expires_at']) ? (string)$entry['expires_at'] : null);
    $codes[] = [
        'code' => (string)$code,
        'reward' => ($entry['tipo'] ?? null) === 'personaggio'
            ? [
                'type' => 'character',
                'name' => (string)($entry['nome'] ?? 'Unknown character'),
            ]
            : [
                'type' => 'godos',
                'amount' => (int)($entry['punti'] ?? 0),
            ],
        'description' => is_string($description) ? $description : null,
        'expires_at' => $expiresAt ? $expiresAt->format(DATE_ATOM) : null,
        'expires_unix' => $expiresAt ? $expiresAt->getTimestamp() : null,
    ];
}

echo json_encode([
    'status' => 'success',
    'count' => count($codes),
    'codes' => $codes,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
