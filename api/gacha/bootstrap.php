<?php
declare(strict_types=1);

/**
 * Base degli endpoint /api/gacha/*: login obbligatorio, JSON, CSRF sulle
 * richieste che cambiano qualcosa.
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function gacha_api_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function gacha_api_fail(string $message, int $status = 400, array $extra = []): void
{
    gacha_api_json(['ok' => false, 'message' => $message] + $extra, $status);
}

function gacha_api_input(): array
{
    static $input = null;
    if ($input !== null) {
        return $input;
    }
    $raw = file_get_contents('php://input');
    $decoded = $raw ? json_decode($raw, true) : null;
    return $input = is_array($decoded) ? $decoded : $_POST;
}

function gacha_api_lang(): string
{
    $lang = $_GET['lang'] ?? (gacha_api_input()['lang'] ?? 'it');
    return $lang === 'en' ? 'en' : 'it';
}

if (!isLoggedIn()) {
    gacha_api_fail('Devi essere loggato.', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? (gacha_api_input()['csrf_token'] ?? null);
    if (!csrf_validate(is_string($csrf) ? $csrf : null)) {
        gacha_api_fail('Sessione scaduta. Ricarica la pagina.', 419);
    }
}

$gachaUserId = (int)$_SESSION['user_id'];
