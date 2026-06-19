<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'message' => 'Devi essere loggato.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

$stmt = $mysqli->prepare("UPDATE utenti SET is_premium = 1 WHERE id = ?");
$stmt->bind_param('i', $userId);

if ($stmt->execute()) {
    // Also record activity if the function exists
    if (function_exists('profile_record_activity')) {
        profile_record_activity($mysqli, $userId, 'premium_upgrade', 'Upgraded to Premium tier');
    }
    echo json_encode(['ok' => true, 'message' => 'Premium attivato con successo!']);
} else {
    echo json_encode(['ok' => false, 'message' => 'Errore durante l\'attivazione: ' . $mysqli->error]);
}
$stmt->close();
