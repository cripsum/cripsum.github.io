<?php
date_default_timezone_set('Europe/Rome');


require_once __DIR__ . '/../secure/config.php';


$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');
$mysqli->query("SET time_zone = '" . date('P') . "'");

