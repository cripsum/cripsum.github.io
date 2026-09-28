<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$cLang = 'en';
require __DIR__ . '/../includes/chisiamo/page.php';
