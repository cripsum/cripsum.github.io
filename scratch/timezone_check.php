<?php
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: text/plain');

echo "PHP Timezone: " . date_default_timezone_get() . "\n";
echo "PHP Time: " . date('Y-m-d H:i:s') . "\n";

$res = $mysqli->query("SELECT NOW() as mysql_now, @@system_time_zone as sys_tz, @@time_zone as tz");
$row = $res->fetch_assoc();
echo "MySQL NOW(): " . $row['mysql_now'] . "\n";
echo "MySQL System Timezone: " . $row['sys_tz'] . "\n";
echo "MySQL Timezone: " . $row['tz'] . "\n";
?>
