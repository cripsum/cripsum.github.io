<?php
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log'); // salva gli errori in un file chiamato "error.log" nella stessa cartella
error_reporting(E_ALL); // mostra tutti gli errori


session_start();
require_once 'includes/functions.php';

logoutUser();
?>