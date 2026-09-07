<?php

/**
 * Cripsum™ — API Pullspot: traccia
 *
 * Endpoint : GET /api/pullspot/audio.php[?mode=daily|practice]
 * Auth     : sessione PHP
 * Response : audio/mpeg
 *
 * Serve solo i secondi che il giocatore ha già sbloccato, tagliati sul confine
 * di un frame MPEG. Il nome del file resta dietro a questo endpoint: se la
 * pagina puntasse a /audio/<personaggio>.mp3 la risposta sarebbe nell'URL.
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/pullspot_helpers.php';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
header('Accept-Ranges: none');

$lang = pullspot_request_lang();

function pullspot_audio_fail(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message]);
    exit;
}

if (!isLoggedIn()) {
    pullspot_audio_fail(401, pullspot_msg('unauthenticated', $lang));
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    pullspot_audio_fail(405, pullspot_msg('method', $lang));
}

$userId = (int)$_SESSION['user_id'];
checkBan($mysqli);

$mode  = ($_GET['mode'] ?? 'daily') === 'practice' ? 'practice' : 'daily';
$round = pullspot_bootstrap($mysqli, $userId, $mode);

if ($round === null || empty($round['character'])) {
    pullspot_audio_fail(503, pullspot_msg('no_pool', $lang));
}

$path = pullspot_audio_path($round['character']['audio_url'] ?? null);
if ($path === null) {
    pullspot_audio_fail(404, pullspot_msg('no_audio', $lang));
}

$bytes = pullspot_clip_bytes($path, pullspot_unlocked_seconds($round['game']));
if ($bytes === null) {
    pullspot_audio_fail(404, pullspot_msg('no_audio', $lang));
}

// La sessione non serve più: liberarla evita di bloccare le richieste che il
// gioco fa in parallelo mentre il browser scarica la traccia.
cripsum_release_session();

header('Content-Type: ' . pullspot_audio_mime($path));
header('Content-Length: ' . strlen($bytes));
header('Content-Disposition: inline; filename="pullspot.' . pathinfo($path, PATHINFO_EXTENSION) . '"');

echo $bytes;
