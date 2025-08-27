<?php
session_start();
if(!isset($_SESSION['user_id'])) exit;
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$user_id = $_SESSION['user_id'];
$stmt = $mysqli->prepare("UPDATE utenti SET ultimo_accesso = NOW() WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();
$mysqli->close();
?>
