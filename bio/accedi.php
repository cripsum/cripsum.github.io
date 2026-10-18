<?php
/**
 * bio.cripsum.com/accedi: parte l'accesso da cripsum.com (includes/bio_sso.php).
 *
 *   ?return=/percorso   dove tornare su bio, sempre un percorso relativo
 *   ?auto=1             accesso automatico: se su cripsum.com non c'e' nessuno, si torna senza chiedere niente
 */

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bio_sso.php';

cripsum_bio_only('accedi');
header('Cache-Control: no-store');

$return = cripsum_safe_return_path((string)($_GET['return'] ?? '/'));
$auto = ($_GET['auto'] ?? '') === '1';

if (isLoggedIn()) {
    header('Location: ' . $return);
    exit;
}

if (!cripsum_sso_available($mysqli)) {
    // Senza la migration: il login di cripsum.com, come prima.
    header('Location: ' . ($auto ? $return : cripsum_main_url(cripsum_preferred_lang() . '/accedi')));
    exit;
}

// host: cripsum.com deve rimandare qui (bio o bio-prova), non a un altro bio.
$query = ['state' => cripsum_sso_start_state($auto), 'return' => $return, 'host' => cripsum_request_host()];
if ($auto) {
    $query['auto'] = 1;
}

header('Location: ' . cripsum_main_url('auth/sso.php?' . http_build_query($query)));
exit;
