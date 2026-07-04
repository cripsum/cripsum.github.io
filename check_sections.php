<?php
require_once __DIR__ . '/config/session_init.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=utf-8');

if (!isLoggedIn()) {
    die("Please log in first.");
}

$userId = (int)$_SESSION['user_id'];
echo "Logged in as User ID: $userId\n\n";

echo "--- DATABASE COLUMN DETAIL FOR profile_sections_config ---\n";
$result = $mysqli->query("SHOW COLUMNS FROM utenti LIKE 'profile_sections_config'");
if ($result && $row = $result->fetch_assoc()) {
    print_r($row);
} else {
    echo "Column profile_sections_config not found or query error: " . $mysqli->error . "\n";
}

echo "\n--- CURRENT VALUE FOR LOGGED IN USER ---\n";
$stmt = $mysqli->prepare("SELECT username, is_premium, profile_sections_config FROM utenti WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    print_r($res);
    $stmt->close();
} else {
    echo "Query error: " . $mysqli->error . "\n";
}

$mysqli->close();
