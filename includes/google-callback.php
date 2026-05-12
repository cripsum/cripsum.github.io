<?php
/**
 * Google OAuth 2.0 – Callback
 * Percorso: /it/google-callback   (o come lo hai mappato nel router)
 *
 * Flusso:
 *   1. Valida state (anti-CSRF)
 *   2. Scambia il codice con un access token
 *   3. Recupera le info dell'utente Google
 *   4. Login o registrazione automatica
 *   5. Redirect a home (o alla pagina di provenienza)
 */

require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/google_auth.php';   // ← il file che hai appena creato

// Se già loggato, niente da fare
if (isLoggedIn()) {
    header('Location: home');
    exit();
}

$error = '';

do {
    // ── 1. Errore esplicito da Google ──────────────────────────
    if (!empty($_GET['error'])) {
        $error = 'Accesso con Google annullato.';
        break;
    }

    // ── 2. Parametri obbligatori ───────────────────────────────
    $code  = $_GET['code']  ?? '';
    $state = $_GET['state'] ?? '';

    if ($code === '' || $state === '') {
        $error = 'Risposta di Google non valida.';
        break;
    }

    // ── 3. Validazione state (anti-CSRF) ───────────────────────
    $expectedState = $_SESSION['google_oauth_state'] ?? '';
    unset($_SESSION['google_oauth_state']);

    if (!hash_equals($expectedState, $state)) {
        $error = 'Sessione non valida. Riprova.';
        break;
    }

    // ── 4. Scambio codice → token ──────────────────────────────
    $tokens = google_exchange_code($code);
    if (!$tokens) {
        $error = 'Impossibile completare l\'autenticazione con Google.';
        break;
    }

    // ── 5. Info utente ─────────────────────────────────────────
    $gUser = google_get_userinfo($tokens['access_token']);
    if (!$gUser) {
        $error = 'Impossibile recuperare le informazioni dell\'account Google.';
        break;
    }

    // ── 6. Login / registrazione ───────────────────────────────
    $result = google_handle_login($mysqli, $gUser);

    if (!empty($result['ok'])) {
        $redirect = $_SESSION['redirect_after_login'] ?? 'home';
        unset($_SESSION['redirect_after_login']);
        header('Location: ' . $redirect);
        exit();
    }

    $error = $result['error'] ?? 'Accesso non riuscito. Riprova.';

} while (false);

// ── In caso di errore: redirect ad accedi con messaggio ────────
$_SESSION['login_message_error'] = $error;
header('Location: accedi');
exit();
