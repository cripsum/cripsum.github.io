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
    $_SESSION['login_message'] = 'Per giocare devi essere loggato';
    header('Location: accedi');
    exit();
}

$ogDescription = 'Gioca a Subway Surfers su Cripsum: 25 città World Tour, No-Coin Challenge con classifica, modalità allenamento e controlli configurabili.';
$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/it/subway'), '#');

// La pagina e' una sola per le due lingue: includes/subway/page.php.
$subwayLang = 'it';
require __DIR__ . '/../includes/subway/page.php';
