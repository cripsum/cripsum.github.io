<?php
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

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'reason' => 'guest']);
    exit();
}

$mysqli->set_charset('utf8mb4');
$userId = (int)$_SESSION['user_id'];
checkBan($mysqli);

try {
    trackMissionProgress($mysqli, $userId, 'download_content');
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('[MissionTracking track_download] ' . $e->getMessage());
    echo json_encode(['ok' => false]);
}
