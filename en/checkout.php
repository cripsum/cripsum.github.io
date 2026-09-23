<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

// Il vecchio checkout serviva anche al Premium: quei link portano ancora qui.
if (($_GET['type'] ?? '') === 'premium') {
    header('Location: /en/checkout-premium.php');
    exit;
}

$shopLang = 'en';
require __DIR__ . '/../includes/shop/pages/checkout.php';
