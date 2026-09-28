<?php

/**
 * Cripsum™ — API: Track Edit View
 * Chiamato via fetch dal frontend quando qualcuno apre un edit.
 *
 * - conta una visualizzazione per edit e per sessione, anche per gli ospiti
 *   (le vede solo il pannello, la pagina le usa per "Piu' visti");
 * - fa avanzare la missione "guarda un edit" a chi ha fatto l'accesso.
 *
 * Solo POST, risposta JSON minimale.
 * Endpoint: POST /api/missions/track_edit_view.php  { "edit_id": 28 }
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/mission_tracker.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit();
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
    exit();
}

$mysqli->set_charset('utf8mb4');

$input = json_decode((string)file_get_contents('php://input'), true);
$editId = (int)(is_array($input) ? ($input['edit_id'] ?? 0) : 0);

// Visualizzazioni: solo edit pubblicati, una volta per sessione.
if ($editId > 0 && auth_table_exists($mysqli, 'edits')) {
    $seen = $_SESSION['edits_viewed'] ?? [];
    if (!is_array($seen)) {
        $seen = [];
    }

    if (!in_array($editId, $seen, true) && count($seen) < 500) {
        try {
            $stmt = $mysqli->prepare("UPDATE edits SET visualizzazioni = visualizzazioni + 1 WHERE id = ? AND stato = 'pubblicato' LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $editId);
                $stmt->execute();
                $counted = $stmt->affected_rows > 0;
                $stmt->close();
                if ($counted) {
                    $seen[] = $editId;
                    $_SESSION['edits_viewed'] = $seen;
                }
            }
        } catch (Throwable $e) {
            error_log('[track_edit_view] ' . $e->getMessage());
        }
    }
}

// La missione: solo per chi ha fatto l'accesso, gli ospiti non ricevono errori.
if (!isLoggedIn()) {
    echo json_encode(['ok' => true, 'reason' => 'guest']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
checkBan($mysqli);

try {
    trackMissionProgress($mysqli, $userId, 'view_edit');
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('[MissionTracking track_edit_view] ' . $e->getMessage());
    echo json_encode(['ok' => false]);
}
