<?php
require_once '../secure/config.php';

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

?>