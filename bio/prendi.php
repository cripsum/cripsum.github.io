<?php
declare(strict_types=1);

/**
 * bio.cripsum.com/prendi?nome=... — il pulsante «Prendilo» dell'ingresso.
 *
 * Se il nome e' libero resta nella sessione di bio e si va a registrarsi su
 * cripsum.com con quello username gia' scritto; dopo il primo accesso si torna
 * su bio. Chi invece ha gia' un account ed entra, su «La tua pagina» se lo
 * vede offrire come link breve (api/bio/alias.php). Se non e' libero si torna
 * all'ingresso, che dice perche'.
 */

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bio_pages.php';

cripsum_bio_only('prendi');
header('Cache-Control: no-store');

$lang = bio_lang();
$name = trim((string)($_GET['nome'] ?? ''));
$userId = (int)($_SESSION['user_id'] ?? 0);
$state = bio_name_state($mysqli, $name, $userId)['state'];

if ($state !== 'free') {
    header('Location: /' . ($userId > 0 ? '' : '?' . http_build_query(['nome' => $name])));
    exit;
}

$_SESSION['bio_claim'] = $name;

if ($userId > 0) {
    header('Location: /');
    exit;
}

header('Location: ' . cripsum_main_url($lang . '/registrati?' . http_build_query(['username' => $name, 'da' => 'bio'])));
exit;
