<?php

/**
 * api_gacha_multi_pull.php
 * POST /api/api_gacha_multi_pull
 *
 * Dieci pull in un'unica transazione, con lo stesso motore della singola
 * (includes/gacha/engine.php). Anti-spam: una multi ogni 5 secondi.
 *
 * Input JSON: { "banner_id": "standard"|int, "quantity": 10 }
 * Output: { "status": "success", "pulls": [...], "soldi_rimasti": int, ... }
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gacha/engine.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function gacha_multi_endpoint_fail(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gacha_multi_endpoint_fail(405, ['status' => 'error', 'message' => 'Method not allowed']);
}

if (!isLoggedIn()) {
    gacha_multi_endpoint_fail(401, ['status' => 'error', 'message' => 'Non autenticato', 'code' => 'NOT_LOGGED_IN']);
}

$userId = (int)$_SESSION['user_id'];

$rawInput = file_get_contents('php://input');
$input = $rawInput ? (json_decode($rawInput, true) ?? []) : [];
if (!$input) {
    $input = $_POST;
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? null);
if (!csrf_validate(is_string($csrf) ? $csrf : null)) {
    gacha_multi_endpoint_fail(419, ['status' => 'error', 'message' => 'Sessione scaduta. Ricarica la pagina.', 'code' => 'CSRF_FAILED']);
}

$now = time();
if (isset($_SESSION['gacha_multi_last_ts']) && $now - (int)$_SESSION['gacha_multi_last_ts'] < GACHA_MULTI_RATE_LIMIT_S) {
    gacha_multi_endpoint_fail(429, ['status' => 'error', 'message' => 'Aspetta qualche secondo prima di fare un altra multi!', 'code' => 'RATE_LIMIT']);
}
$_SESSION['gacha_multi_last_ts'] = $now;

$bannerId = $input['banner_id'] ?? null;
if ($bannerId === null || $bannerId === '') {
    gacha_multi_endpoint_fail(400, ['status' => 'error', 'message' => 'banner_id mancante', 'code' => 'MISSING_BANNER']);
}
if (!is_scalar($bannerId) || ($bannerId !== 'standard' && !ctype_digit((string)$bannerId))) {
    gacha_multi_endpoint_fail(400, ['status' => 'error', 'message' => 'banner_id non valido', 'code' => 'INVALID_BANNER']);
}

$quantity = min(GACHA_MULTI_SIZE, max(1, (int)($input['quantity'] ?? GACHA_MULTI_SIZE)));

$opts = ['source' => 'web'];
if (in_array($_SESSION['ruolo'] ?? 'utente', ['admin', 'owner'], true)) {
    $opts['force_rarity'] = is_string($input['force_rarity'] ?? null) ? $input['force_rarity'] : null;
    $opts['force_character_id'] = max(0, (int)($input['force_character_id'] ?? 0));
}

try {
    $result = gacha_pull($mysqli, $userId, (string)$bannerId, $quantity, $opts);
    echo json_encode(gacha_response_multi($result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    [$status, $payload] = gacha_response_error($e);
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

gacha_flush_announcements();
