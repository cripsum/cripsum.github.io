<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/social_functions.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DEBUG PROFILE.PHP RELATIONSHIP ===\n";

$isLoggedIn = isset($_SESSION['user_id']);
$currentUserId = $isLoggedIn ? (int)$_SESSION['user_id'] : 1; // Fallback a 1 per test

echo "Is Logged In: " . ($isLoggedIn ? "Yes" : "No") . "\n";
echo "Current User ID: $currentUserId\n";

// Trova un altro utente nel database per fare il test
$otherUser = null;
$res = $mysqli->query("SELECT id, username FROM utenti WHERE id != $currentUserId LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $otherUser = $row;
}

if (!$otherUser) {
    echo "No other user found in database to test with!\n";
    exit();
}

$targetId = (int)$otherUser['id'];
echo "Testing relationship status with Target User: {$otherUser['username']} (ID: $targetId)\n";

try {
    echo "Calling getRelationshipStatus($currentUserId, $targetId)...\n";
    $rel = getRelationshipStatus($mysqli, $currentUserId, $targetId);
    echo "Success! Result:\n";
    print_r($rel);
} catch (Throwable $e) {
    echo "FATAL ERROR CAUGHT:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
?>
