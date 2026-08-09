<?php
date_default_timezone_set('Europe/Rome');


require_once __DIR__ . '/../secure/config.php';


$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');
$mysqli->query("SET time_zone = '" . date('P') . "'");

// Valida il token del dispositivo su ogni richiesta autenticata che usa il database.
require_once __DIR__ . '/../includes/security_helpers.php';
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
    auth_sync_current_device_session($mysqli);
}
