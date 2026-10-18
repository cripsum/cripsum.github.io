<?php
/**
 * Il lato cripsum.com dell'accesso su bio.cripsum.com (vedi includes/bio_sso.php).
 *
 * Chi e' gia' dentro riceve un biglietto e torna su bio. Chi non lo e' passa
 * prima dal login di sempre (password, Google, 2FA), che alla fine torna qui.
 * Con auto=1 (l'accesso automatico) non si chiede mai il login: se non c'e'
 * nessuno dentro si torna su bio senza biglietto.
 */

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bio_sso.php';

header('Cache-Control: no-store');

if (cripsum_site() !== 'main') {
    include __DIR__ . '/../404.php';
    exit;
}

$stateHash = strtolower((string)($_GET['state'] ?? ''));
$return = cripsum_safe_return_path((string)($_GET['return'] ?? '/'));
$auto = ($_GET['auto'] ?? '') === '1';
// L'host di bio che ha chiesto l'accesso (bio o bio-prova): solo quelli
// dell'elenco, altrimenti bio.cripsum.com.
$bioHost = (string)($_GET['host'] ?? 'bio.cripsum.com');

$backToBio = static function (array $query) use ($bioHost): void {
    header('Location: ' . cripsum_bio_url_on($bioHost, 'auth/callback?' . http_build_query($query)));
    exit;
};

if (!preg_match('/^[a-f0-9]{64}$/', $stateHash) || !cripsum_sso_available($mysqli)) {
    $backToBio([$auto ? 'none' : 'error' => 1, 'return' => $return]);
}

checkBan($mysqli);

if (!isLoggedIn()) {
    if ($auto) {
        $backToBio(['none' => 1, 'return' => $return]);
    }

    // Il login di sempre; finito, si torna qui con gli stessi parametri.
    $_SESSION['redirect_after_login'] = '/auth/sso.php?' . http_build_query(['state' => $stateHash, 'return' => $return, 'host' => $bioHost]);
    header('Location: /' . cripsum_preferred_lang() . '/accedi');
    exit;
}

$deviceSession = auth_current_device_session($mysqli);
$ticket = cripsum_sso_issue(
    $mysqli,
    (int)$_SESSION['user_id'],
    $deviceSession ? (int)$deviceSession['id'] : null,
    $stateHash,
    $return
);

if ($ticket === null) {
    $backToBio([$auto ? 'none' : 'error' => 1, 'return' => $return]);
}

// Il ritorno vero e' quello scritto col biglietto; questo serve solo a
// «Riprova» se il biglietto non vale piu'.
$backToBio(['t' => $ticket, 'return' => $return]);
