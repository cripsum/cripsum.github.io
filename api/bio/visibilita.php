<?php
declare(strict_types=1);

/**
 * POST — «Chi può vederla» e «Mostrami in Esplora» da «La tua pagina».
 *
 *   visibility     public | logged_in | friends | private (le stesse dell'editor)
 *   discoverable   0 | 1 (solo con la migration 2026_10_18_bio_esplora.sql)
 *
 * Si puo' mandare uno dei due o tutti e due.
 */

require_once __DIR__ . '/_bootstrap.php';

bio_api_require_post_csrf();
$userId = bio_api_require_user($mysqli);
cripsum_release_session();

$input = bio_api_input();
$saved = [];

if (array_key_exists('visibility', $input)) {
    $visibility = (string)$input['visibility'];
    if (!in_array($visibility, ['public', 'logged_in', 'friends', 'private'], true)) {
        bio_api_fail('err_generic');
    }
    $stmt = $mysqli->prepare('UPDATE utenti SET profile_visibility = ? WHERE id = ?');
    $stmt->bind_param('si', $visibility, $userId);
    $stmt->execute();
    $stmt->close();
    $saved['visibility'] = $visibility;
}

if (array_key_exists('discoverable', $input)) {
    if (!bio_explore_ready($mysqli)) {
        bio_api_fail('err_generic', 409);
    }
    $discoverable = !empty($input['discoverable']) ? 1 : 0;
    $stmt = $mysqli->prepare('UPDATE utenti SET profile_discoverable = ? WHERE id = ?');
    $stmt->bind_param('ii', $discoverable, $userId);
    $stmt->execute();
    $stmt->close();
    $saved['discoverable'] = (bool)$discoverable;
}

bio_api_ok($saved);
