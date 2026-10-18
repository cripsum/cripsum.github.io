<?php
declare(strict_types=1);

/**
 * POST — mette o toglie una pagina dalla vetrina di bio.cripsum.com. Solo
 * staff (admin, owner): lo fa dalla stellina sulle schede di Esplora.
 *
 *   user_id   la pagina
 *   on        1 in vetrina, 0 fuori
 */

require_once __DIR__ . '/_bootstrap.php';

bio_api_require_post_csrf();
bio_api_require_user($mysqli);
if (!profile_is_staff()) {
    bio_api_fail('err_login', 403);
}
cripsum_release_session();

if (!bio_explore_ready($mysqli)) {
    bio_api_fail('err_generic', 409);
}

$input = bio_api_input();
$targetId = (int)($input['user_id'] ?? 0);
$on = !empty($input['on']);

$stmt = $on
    ? $mysqli->prepare('UPDATE utenti SET profile_featured_at = NOW() WHERE id = ?')
    : $mysqli->prepare('UPDATE utenti SET profile_featured_at = NULL WHERE id = ?');
$stmt->bind_param('i', $targetId);
$stmt->execute();
$stmt->close();

bio_api_ok(['user_id' => $targetId, 'featured' => $on]);
