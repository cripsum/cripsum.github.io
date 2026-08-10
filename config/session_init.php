<?php
date_default_timezone_set('Europe/Rome');
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

const CRIPSUM_SESSION_IDLE_TIMEOUT = 7200;      
const CRIPSUM_SESSION_ABSOLUTE_TIMEOUT = 604800; 
const CRIPSUM_SESSION_ROTATION_INTERVAL = 900; 

ini_set('session.gc_maxlifetime', (string)CRIPSUM_SESSION_ABSOLUTE_TIMEOUT);
ini_set('session.cookie_lifetime', (string)CRIPSUM_SESSION_ABSOLUTE_TIMEOUT);
ini_set('session.gc_probability', '1');
ini_set('session.gc_divisor', '1000');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');

$hostHeader = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
$host = trim((string)preg_replace('/:\d+$/', '', $hostHeader), '[]');
$isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
$isHttps = !$isLocalHost && (
    (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
);

$sessionName = $isHttps ? '__Host-cripsum_session' : 'cripsum_session_dev';
session_name($sessionName);
session_set_cookie_params([
    'lifetime' => CRIPSUM_SESSION_ABSOLUTE_TIMEOUT,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if ($isHttps && isset($_COOKIE['cripsum_session'])) {
    foreach (['', '.cripsum.com'] as $legacyDomain) {
        setcookie('cripsum_session', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => $legacyDomain,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    unset($_COOKIE['cripsum_session']);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sessionNow = time();
$sessionCreatedAt = (int)($_SESSION['session_created_at'] ?? $sessionNow);
$sessionLastActivity = (int)($_SESSION['session_last_activity'] ?? $sessionNow);
$sessionExpired = !empty($_SESSION['user_id']) && (
    ($sessionNow - $sessionLastActivity) > CRIPSUM_SESSION_IDLE_TIMEOUT
    || ($sessionNow - $sessionCreatedAt) > CRIPSUM_SESSION_ABSOLUTE_TIMEOUT
);

if ($sessionExpired) {
    $_SESSION = [];
    setcookie($sessionName, '', [
        'expires' => $sessionNow - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_destroy();
    unset($_COOKIE[$sessionName]);
    session_id('');
    session_start();
    $_SESSION['login_message'] = 'Sessione scaduta per sicurezza. Accedi di nuovo.';
    $sessionCreatedAt = $sessionNow;
}

$_SESSION['session_created_at'] = $sessionCreatedAt;
$_SESSION['session_last_activity'] = $sessionNow;

$lastRotation = (int)($_SESSION['session_last_rotation'] ?? 0);
if ($lastRotation === 0 || ($sessionNow - $lastRotation) >= CRIPSUM_SESSION_ROTATION_INTERVAL) {
    session_regenerate_id(true);
    $_SESSION['session_last_rotation'] = $sessionNow;
}

if (!defined('CRIPSUM_SKIP_SPECIAL_SESSION_REDIRECT') && isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === 77) {
    header('Location: uwu');
    exit();
}
