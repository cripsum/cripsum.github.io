<?php
/**
 * bio.cripsum.com/auth/callback: il ritorno da cripsum.com (includes/bio_sso.php).
 *
 *   ?t=<biglietto>      si usa una volta sola e apre la sessione di bio
 *   ?none=1             accesso automatico a vuoto: su cripsum.com non c'e' nessuno
 *   ?error=1            cripsum.com non ha potuto dare un biglietto
 */

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bio_sso.php';

cripsum_bio_only('auth/callback');
header('Cache-Control: no-store');

[$state, $auto] = cripsum_sso_take_state();
$return = cripsum_safe_return_path((string)($_GET['return'] ?? '/'));

// Accesso automatico, ma su cripsum.com non c'e' nessuno: l'indizio era
// vecchio. Si spegne e si resta ospiti, senza riprovare per un po'.
if (isset($_GET['none'])) {
    cripsum_sso_hint_cookie(false);
    cripsum_bio_noauto();
    header('Location: ' . $return);
    exit;
}

$data = !isset($_GET['error']) && $state !== '' ? cripsum_sso_redeem($mysqli, (string)($_GET['t'] ?? ''), $state) : null;
$user = $data ? auth_get_user_by_id($mysqli, (int)$data['user_id']) : null;

// La riga di cripsum.com a cui legare questa sessione, se il registro dei
// dispositivi c'e'. Se nel frattempo e' stata revocata, niente accesso.
$parentId = $data && $data['user_session_id'] !== null ? (int)$data['user_session_id'] : 0;
$parent = $parentId > 0 && $user ? auth_session_row($mysqli, (int)$user['id'], $parentId) : null;

if (!$data || !$user || ($parentId > 0 && !$parent)) {
    if ($auto) {
        // Un accesso automatico non mostra mai errori: si resta ospiti.
        cripsum_bio_noauto();
        header('Location: ' . $return);
        exit;
    }

    require_once __DIR__ . '/../includes/avviso_page.php';
    $lang = cripsum_preferred_lang();
    $tt = static fn(string $it, string $en): string => $lang === 'it' ? $it : $en;
    cripsum_avviso_page([
        'status' => 400,
        'lang' => $lang,
        'title' => $tt('Accesso non riuscito', 'Sign-in failed'),
        'icon' => 'fa-key',
        'headline' => $tt('Accesso non riuscito', 'Sign-in failed'),
        'text' => $tt('Il collegamento è scaduto o è già stato usato. Riprova: ci vuole un attimo.', 'The link has expired or was already used. Try again, it only takes a moment.'),
        'actions' => [
            ['href' => '/accedi?' . http_build_query(['return' => $return]), 'icon' => 'fa-rotate-right', 'label' => $tt('Riprova', 'Try again'), 'primary' => true],
            ['href' => '/', 'icon' => 'fa-house', 'label' => $tt('Torna a bio', 'Back to bio')],
        ],
    ]);
    exit;
}

auth_establish_session($user);
$_SESSION['auth_linked'] = true;
$_SESSION['auth_parent_session_id'] = $parentId;
$_SESSION['auth_parent_token_hash'] = $parent ? (string)$parent['token_hash'] : '';
$_SESSION['auth_device_last_sync'] = time();

header('Location: ' . cripsum_safe_return_path((string)$data['return_path']));
exit;
