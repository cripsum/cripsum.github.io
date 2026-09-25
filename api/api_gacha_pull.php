<?php

/**
 * api_gacha_pull.php
 * POST /api/api_gacha_pull
 *
 * Pull singola. La logica (rarita', pity, 50/50, costi) sta tutta nel
 * motore in includes/gacha/engine.php; qui restano login, CSRF e anti-spam.
 *
 * Input JSON (o POST form):
 *   banner_id  "standard" oppure l'id numerico del banner
 *   quantity   ignorato: per la multi c'e' api_gacha_multi_pull
 *
 * Output: la stessa risposta di sempre (personaggio, is_new, pity_standard,
 * pity_evento, garantito, vinto_50_50, soldi_rimasti, ...) piu' qualche
 * campo nuovo (pity del gruppo del banner, uso, featured, achievements).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gacha/engine.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function gacha_pull_endpoint_fail(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gacha_pull_endpoint_fail(405, ['status' => 'error', 'message' => 'Method not allowed']);
}

if (!isLoggedIn()) {
    gacha_pull_endpoint_fail(401, ['status' => 'error', 'message' => 'Non autenticato', 'code' => 'NOT_LOGGED_IN']);
}

$userId = (int)$_SESSION['user_id'];

$rawInput = file_get_contents('php://input');
$input = $rawInput ? (json_decode($rawInput, true) ?? []) : [];
if (!$input) {
    $input = $_POST;
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? null);
if (!csrf_validate(is_string($csrf) ? $csrf : null)) {
    gacha_pull_endpoint_fail(419, ['status' => 'error', 'message' => 'Sessione scaduta. Ricarica la pagina.', 'code' => 'CSRF_FAILED']);
}

$bannerId = $input['banner_id'] ?? null;
if ($bannerId === null || $bannerId === '') {
    gacha_pull_endpoint_fail(400, ['status' => 'error', 'message' => 'banner_id mancante', 'code' => 'MISSING_BANNER']);
}
if (!is_scalar($bannerId) || ($bannerId !== 'standard' && !ctype_digit((string)$bannerId))) {
    gacha_pull_endpoint_fail(400, ['status' => 'error', 'message' => 'banner_id non valido', 'code' => 'INVALID_BANNER']);
}

// Anti-spam per sessione.
$now = microtime(true);
if (isset($_SESSION['gacha_last_pull_ts'])) {
    $elapsed = ($now - (float)$_SESSION['gacha_last_pull_ts']) * 1000;
    if ($elapsed < GACHA_PULL_RATE_LIMIT_MS) {
        gacha_pull_endpoint_fail(429, [
            'status' => 'error',
            'message' => 'Aspetta un momento prima di pullare ancora!',
            'code' => 'RATE_LIMIT',
            'wait_ms' => (int)ceil(GACHA_PULL_RATE_LIMIT_MS - $elapsed),
        ]);
    }
}
$_SESSION['gacha_last_pull_ts'] = $now;

// Forzature di prova: solo admin e owner, decise qui e non dal client.
$opts = ['source' => 'web'];
if (in_array($_SESSION['ruolo'] ?? 'utente', ['admin', 'owner'], true)) {
    $opts['force_rarity'] = is_string($input['force_rarity'] ?? null) ? $input['force_rarity'] : null;
    $opts['force_character_id'] = max(0, (int)($input['force_character_id'] ?? 0));
}

try {
    $result = gacha_pull($mysqli, $userId, (string)$bannerId, 1, $opts);
    echo json_encode(gacha_response_single($result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    [$status, $payload] = gacha_response_error($e);
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

gacha_flush_announcements();
