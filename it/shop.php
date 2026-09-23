<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../config/paypal_config.php';

checkBan($mysqli);
requireLogin();

$shopLang = 'it';
require __DIR__ . '/../includes/shop/pages/gacha.php';
