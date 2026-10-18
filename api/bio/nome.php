<?php
declare(strict_types=1);

/**
 * GET ?nome=... — il nome e' libero per bio.cripsum.com? Lo usa il campo
 * dell'ingresso mentre si scrive. Vale anche per gli ospiti: dice solo
 * libero / preso / riservato / non valido, cose che si vedono gia' aprendo
 * bio.cripsum.com/<nome>.
 *
 * Limite: 60 controlli al minuto per sessione (il campo aspetta che si smetta
 * di scrivere, quindi una persona non ci arriva).
 */

require_once __DIR__ . '/_bootstrap.php';

$now = time();
$checks = array_values(array_filter((array)($_SESSION['bio_name_checks'] ?? []), static fn($ts): bool => is_int($ts) && $ts > $now - 60));
if (count($checks) >= 60) {
    bio_api_fail('err_slow', 429);
}
$checks[] = $now;
$_SESSION['bio_name_checks'] = $checks;
cripsum_release_session();

$state = bio_name_state($mysqli, (string)($_GET['nome'] ?? ''), (int)($_SESSION['user_id'] ?? 0));
bio_api_ok($state);
