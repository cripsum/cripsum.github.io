<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'message' => 'Devi essere loggato.']);
    exit;
}

$currentUserId = profile_current_user_id();
if ($currentUserId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Utente non trovato.']);
    exit;
}

$requestTargetUserId = isset($_REQUEST['target_user_id']) ? (int)$_REQUEST['target_user_id'] : 0;
$targetUserId = ($requestTargetUserId > 0 && profile_is_staff()) ? $requestTargetUserId : $currentUserId;

// Store the POST data in the user's profile draft session
$_SESSION['profile_draft'][$targetUserId] = $_POST;

echo json_encode(['ok' => true, 'message' => 'Bozza salvata.']);
