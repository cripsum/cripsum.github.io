<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$eLang = 'en';
require __DIR__ . '/../includes/edits/page.php';
