<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$esLang = 'it';
require __DIR__ . '/../includes/esports/pages/team.php';
