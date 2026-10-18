<?php
declare(strict_types=1);

/**
 * POST — «Vuoi anche bio.cripsum.com/<nome>?» su «La tua pagina».
 *
 * Il nome e' quello scelto da ospite sull'ingresso prima di entrare (resta
 * nella sessione di bio, $_SESSION['bio_claim']); diventa l'alias di chi non
 * ne ha ancora uno. Solo quel nome: per cambiare alias c'e' l'editor.
 */

require_once __DIR__ . '/_bootstrap.php';

bio_api_require_post_csrf();
$userId = bio_api_require_user($mysqli);

$claim = (string)($_SESSION['bio_claim'] ?? '');
$name = trim((string)(bio_api_input()['name'] ?? ''));
if ($claim === '' || strcasecmp($claim, $name) !== 0) {
    bio_api_fail('err_generic');
}

unset($_SESSION['bio_claim']);
cripsum_release_session();

if (bio_name_state($mysqli, $name, $userId)['state'] !== 'free') {
    bio_api_fail('err_taken', 409);
}

// Solo a chi non ha gia' un alias: non si cambia il link di nessuno da qui.
$stmt = $mysqli->prepare("UPDATE utenti SET custom_alias = ? WHERE id = ? AND (custom_alias IS NULL OR custom_alias = '')");
$stmt->bind_param('si', $name, $userId);
$stmt->execute();
$changed = $stmt->affected_rows === 1;
$stmt->close();

if (!$changed) {
    bio_api_fail('err_generic', 409);
}

bio_api_ok(['name' => $name, 'message' => sprintf(bio_t('claim_done', $bioLang), $name)]);
