<?php
require_once __DIR__ . '../secure/config.php';

$mysqli = new mysqli($host, $username, $password, $database);

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

$mysqli->set_charset('utf8');
?>