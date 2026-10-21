<?php

/**
 * Duelli 2.0 — l'inizio comune delle pagine con la navbar del sito (lobby,
 * Squadra, fine partita). Si include in cima al modello, fuori da ogni
 * funzione: config/database.php deve lasciare $mysqli globale.
 *
 * Prima: $duelLang ('it' o 'en'). Dopo: $dLang, $dEn, $dUserId, $dReady,
 * $dOpen, $dUrls, $h(), $dT().
 *
 * head-import.php e la navbar sovrascrivono $t e $lang: qui si usa $d.
 */

require_once __DIR__ . '/../../../config/session_init.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../theme.php';
require_once __DIR__ . '/../pages.php';

$dLang = ($duelLang ?? 'it') === 'en' ? 'en' : 'it';
$dEn = $dLang === 'en';
// I testi che il server scrive (ranghi, kit) seguono la lingua della pagina.
$_SERVER['HTTP_X_CRIPSUM_LANG'] = $dLang;

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = $dEn ? 'You need to be logged in to play' : 'Per giocare devi essere loggato';
    header('Location: /' . $dLang . '/accedi');
    exit();
}

$dUserId = (int)$_SESSION['user_id'];
$dReady = duel_db_ready($mysqli);
$dOpen = $dReady && duel_open_for($mysqli);
$dUrls = duel_urls($dLang);
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$dT = static fn(string $it, string $en) => $dEn ? $en : $it;
