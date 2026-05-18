<?php

/**
 * Cripsum™ — API: Track Edit View
 * Chiamato via fetch dal frontend quando l'utente apre/riproduce un edit.
 * Solo POST, solo utenti loggati, risposta JSON minimale.
 *
 * Endpoint: POST /api/missions/track_edit_view.php
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/mission_tracker.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit();
}

// Solo loggati — se non loggato ignora silenziosamente (no errore visibile)
if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'reason' => 'guest']);
    exit();
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
    exit();
}

$mysqli->set_charset('utf8mb4');
$userId = (int)$_SESSION['user_id'];
checkBan($mysqli);

try {
    trackMissionProgress($mysqli, $userId, 'view_edit');
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('[MissionTracking track_edit_view] ' . $e->getMessage());
    echo json_encode(['ok' => false]);
}
