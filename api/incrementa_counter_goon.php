<?php  
ini_set('display_errors', 1);
ini_set('display_startup_errors', value: 1);
ini_set('log_errors', 1);
error_reporting(1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$user_id = $_SESSION['user_id'] ?? 0;

$stmt = $mysqli->prepare("UPDATE utenti SET clickgoon = clickgoon + 1 WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();
?>