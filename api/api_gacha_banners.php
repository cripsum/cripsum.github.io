<?php
/**
 * api_gacha_banners.php
 * GET /api/api_gacha_banners
 *
 * Banner attivi e pity dell'utente loggato, nella forma di sempre
 * ({standard, eventi[]}), che legge anche il bot tramite bot/get_banners.
 * Con ?v=2 restituisce lo stato completo della lootbox (tutti i banner,
 * gruppi di pity, limiti, destino).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gacha/public.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// Il bot passa da bot/get_banners.php, che imposta l'utente senza sessione.
$userId = (int)($GLOBALS['gacha_bot_user_id'] ?? 0);
if ($userId <= 0) {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Non autenticato', 'code' => 'NOT_LOGGED_IN']);
        exit();
    }
    $userId = (int)$_SESSION['user_id'];
}

try {
    if (($_GET['v'] ?? '') === '2') {
        $lang = ($_GET['lang'] ?? 'it') === 'en' ? 'en' : 'it';
        echo json_encode(['status' => 'success'] + gacha_lootbox_state($mysqli, $userId, $lang), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        echo json_encode(gacha_banners_legacy_payload($mysqli, $userId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
} catch (Throwable $e) {
    error_log('[api_gacha_banners] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Errore DB banner', 'code' => 'DB_PREPARE_BANNER']);
}
