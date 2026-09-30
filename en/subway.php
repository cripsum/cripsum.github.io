<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

if (function_exists('checkBan')) {
    checkBan($mysqli);
}

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = 'You must be logged in to play';
    header('Location: accedi');
    exit();
}

$ogDescription = 'Play Subway Surfers on Cripsum: 25 World Tour cities, a ranked No-Coin Challenge, training mode and configurable controls.';
$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/en/subway'), '#');

// La pagina e' una sola per le due lingue: includes/subway/page.php.
$subwayLang = 'en';
require __DIR__ . '/../includes/subway/page.php';
