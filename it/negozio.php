<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$shopLang = 'it';
require __DIR__ . '/../includes/shop/pages/negozio.php';
