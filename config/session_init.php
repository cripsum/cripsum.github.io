<?php
date_default_timezone_set('Europe/Rome');
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

const CRIPSUM_SESSION_ABSOLUTE_TIMEOUT = 1209600; // 2 settimane (14 giorni)

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
$sessionExpired = !empty($_SESSION['user_id']) && (
    ($sessionNow - $sessionCreatedAt) > CRIPSUM_SESSION_ABSOLUTE_TIMEOUT
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
    $_SESSION['login_message'] = 'Sessione scaduta dopo 2 settimane. Accedi di nuovo.';
    $sessionCreatedAt = $sessionNow;
}

$_SESSION['session_created_at'] = $sessionCreatedAt;
$_SESSION['session_last_activity'] = $sessionNow;

if (!defined('CRIPSUM_SKIP_SPECIAL_SESSION_REDIRECT') && isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === 77) {
    header('Location: uwu');
    exit();
}

/*
 * Lingua del sito. Le pagine /it/ e /en/ la portano nell'URL, i profili
 * (/u/nome) e le API no: senza ricordarla, dal profilo l'editor si apriva
 * sempre in italiano anche a chi stava navigando in inglese. Il cookie viene
 * riscritto solo quando cambia, cosi' le pagine normali non mandano un
 * Set-Cookie a ogni richiesta.
 */
$cripsumRequestLang = explode('/', trim((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? ''), '/'))[0] ?? '';
if (in_array($cripsumRequestLang, ['it', 'en'], true)
    && ($_COOKIE['cripsum_lang'] ?? '') !== $cripsumRequestLang
    && !headers_sent()
) {
    setcookie('cripsum_lang', $cripsumRequestLang, [
        'expires' => time() + 31536000,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['cripsum_lang'] = $cripsumRequestLang;
}
unset($cripsumRequestLang);

if (!function_exists('cripsum_preferred_lang')) {
    /**
     * La lingua in cui mostrare una pagina che non la dichiara nell'URL:
     * quella dell'ultima pagina visitata, poi quella del browser.
     */
    function cripsum_preferred_lang(): string
    {
        $cookie = (string)($_COOKIE['cripsum_lang'] ?? '');
        if (in_array($cookie, ['it', 'en'], true)) {
            return $cookie;
        }

        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        if ($accept !== '' && !str_contains($accept, 'it')) {
            return 'en';
        }

        return 'it';
    }
}

/**
 * Releases the exclusive lock PHP holds on the session file.
 *
 * While a request keeps the session open, EVERY other request from the same
 * visitor blocks on session_start(). A profile page with 20 avatars, or the
 * editor saving a draft while the preview reloads and a file uploads, then
 * serializes into one long queue and looks like it hangs.
 *
 * Call this as soon as a script no longer needs to WRITE to $_SESSION. Reads
 * still work afterwards ($_SESSION keeps its values), writes just stop being
 * persisted.
 */
if (!function_exists('cripsum_release_session')) {
    function cripsum_release_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
