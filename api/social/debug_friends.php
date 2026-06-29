<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/social_functions.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DEBUG FRIENDS.PHP ===\n";
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1; // Fallback a 1 per test
$targetId = $userId;

echo "User ID: $userId\n";
echo "Target ID: $targetId\n";

$sql = "
    SELECT 
        u.id, u.username, u.ruolo, u.is_premium, u.last_activity,
        EXISTS(SELECT 1 FROM user_follows WHERE follower_id = ? AND followed_id = u.id) AS is_following,
        EXISTS(SELECT 1 FROM user_follows WHERE follower_id = u.id AND followed_id = ?) AS is_followed_by,
        EXISTS(SELECT 1 FROM friendships WHERE (user_one_id = LEAST(?, u.id) AND user_two_id = GREATEST(?, u.id))) AS is_friend,
        EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = ? AND receiver_id = u.id AND status = 'pending') AS request_sent,
        EXISTS(SELECT 1 FROM friendship_requests WHERE sender_id = u.id AND receiver_id = ? AND status = 'pending') AS request_received
    FROM friendships f
    INNER JOIN utenti u ON u.id = IF(f.user_one_id = ?, f.user_two_id, f.user_one_id)
    WHERE f.user_one_id = ? OR f.user_two_id = ?
    ORDER BY u.username ASC
";

echo "Preparing query...\n";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo "PREPARE FAILED!\n";
    echo "Error: " . $mysqli->error . "\n";
    exit();
}

echo "Binding parameters...\n";
$ok = $stmt->bind_param(
    "iiiiiiiii",
    $userId, $userId, $userId, $userId, $userId, $userId,
    $targetId, $targetId, $targetId
);

if (!$ok) {
    echo "BIND PARAM FAILED!\n";
    echo "Error: " . $stmt->error . "\n";
    exit();
}

echo "Executing query...\n";
$ok = $stmt->execute();
if (!$ok) {
    echo "EXECUTE FAILED!\n";
    echo "Error: " . $stmt->error . "\n";
    exit();
}

echo "Fetching results...\n";
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
echo "Success! Found " . count($rows) . " friends:\n";
print_r($rows);
$stmt->close();
?>
