<?php
date_default_timezone_set('Europe/Rome');
ini_set('session.gc_maxlifetime', 604800);
ini_set('session.cookie_lifetime', 604800);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 1000);

$cookie_domain = '.cripsum.com';
$secure = true;

$host = $_SERVER['HTTP_HOST'] ?? '';
$host = explode(':', $host)[0];

if ($host === 'localhost' || $host === '127.0.0.1' || filter_var($host, FILTER_VALIDATE_IP)) {
    $cookie_domain = '';
    $secure = false;
} else if (strpos($host, 'cripsum.com') === false) {
    $cookie_domain = '.' . $host;
    $secure = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
} else {
    $secure = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

session_set_cookie_params([
    'lifetime' => 604800,
    'path' => '/',
    'domain' => $cookie_domain,
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION["user_id"]) && $_SESSION["user_id"] == 77) {
    header("Location: uwu");
    exit();
}
