<?php
/**
 * Cripsum™ — API Missioni: GET
 * Ritorna le missioni daily e weekly dell'utente autenticato.
 * Le genera automaticamente se non esistono per il periodo corrente.
 *
 * Endpoint : GET /api/missions/get.php
 * Auth     : sessione PHP (isLoggedIn())
 * Response : JSON
 */

require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/mission_generator.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Auth ──────────────────────────────────────────────────────
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato', 'code' => 'UNAUTHENTICATED']);
    exit();
}

// ── Solo GET ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    exit();
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database non disponibile']);
    exit();
}

$mysqli->set_charset('utf8mb4');
$userId = (int)$_SESSION['user_id'];
checkBan($mysqli);

// ── Lingua ───────────────────────────────────────────────────
$lang = 'it';
$uri  = $_SERVER['REQUEST_URI'] ?? '';
if (str_contains($uri, '/en/') || ($_GET['lang'] ?? '') === 'en') {
    $lang = 'en';
}

// ── Genera / recupera missioni ───────────────────────────────
try {
    $data = getMissionsPageData($mysqli, $userId, $lang);
} catch (Exception $e) {
    error_log('[API missions/get] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno']);
    exit();
}

// ── Risposta ─────────────────────────────────────────────────
echo json_encode([
    'success' => true,
    'data'    => $data,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
