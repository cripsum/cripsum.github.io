<?php
declare(strict_types=1);

/**
 * POST — la vetrina di bio.cripsum.com. Solo staff (admin, owner): dalla
 * stellina sulle schede di Esplora o dalla sezione «Vetrina» di «La tua pagina».
 *
 *   user_id + on   mette (1) o toglie (0) una pagina
 *   name           mette in vetrina una pagina per username, alias o link
 *   order          gli id della vetrina nel nuovo ordine (tutti)
 *   hero           la pagina nel telefono dell'ingresso, fra quelle in vetrina
 *                  (0 = di nuovo la prima)
 */

require_once __DIR__ . '/_bootstrap.php';

bio_api_require_post_csrf();
$staffId = bio_api_require_user($mysqli);
if (!profile_is_staff()) {
    bio_api_fail('err_login', 403);
}
cripsum_release_session();

if (!bio_explore_ready($mysqli)) {
    bio_api_fail('err_generic', 409);
}

$input = bio_api_input();
$ordered = bio_showcase_ready($mysqli);
$current = static fn(): array => array_map(static fn(array $row): int => (int)$row['id'], bio_showcase($mysqli)['picks']);

if (array_key_exists('order', $input)) {
    if (!$ordered) {
        bio_api_fail('sv_install', 409);
    }
    $order = array_values(array_unique(array_map('intval', is_array($input['order']) ? $input['order'] : [])));
    $now = $current();
    if (count($order) !== count($now) || array_diff($now, $order)) {
        bio_api_fail('sv_order_stale', 409);
    }

    $stmt = $mysqli->prepare('UPDATE utenti SET profile_featured_pos = ? WHERE id = ? AND profile_featured_at IS NOT NULL');
    foreach ($order as $index => $id) {
        $pos = $index + 1;
        $stmt->bind_param('ii', $pos, $id);
        $stmt->execute();
    }
    $stmt->close();

    bio_api_ok(['order' => $order, 'message' => bio_t('sv_order_saved', $bioLang)]);
}

if (array_key_exists('hero', $input)) {
    if (!$ordered) {
        bio_api_fail('sv_install', 409);
    }
    $heroId = max(0, (int)$input['hero']);
    if ($heroId > 0 && !in_array($heroId, $current(), true)) {
        bio_api_fail('sv_not_featured', 422);
    }

    $value = $heroId > 0 ? $heroId : null;
    $stmt = $mysqli->prepare('INSERT INTO bio_settings (id, hero_user_id, updated_by) VALUES (1, ?, ?)
        ON DUPLICATE KEY UPDATE hero_user_id = VALUES(hero_user_id), updated_by = VALUES(updated_by)');
    $stmt->bind_param('ii', $value, $staffId);
    $stmt->execute();
    $stmt->close();

    bio_api_ok(['hero' => $heroId, 'message' => bio_t('sv_phone_set', $bioLang)]);
}

if (trim((string)($input['name'] ?? '')) !== '') {
    [$row, $error] = bio_showcase_lookup($mysqli, (string)$input['name']);
    if (!$row) {
        bio_api_fail($error, 422);
    }
    $targetId = (int)$row['id'];
    $on = true;
} else {
    $targetId = (int)($input['user_id'] ?? 0);
    $on = !empty($input['on']);
}

// Senza posto una pagina sta in cima (bio_explore_cards): chi arriva, anche
// rimessa in vetrina, parte da li'; chi esce perde il suo posto.
$resetPos = $ordered ? ', profile_featured_pos = NULL' : '';
$stmt = $on
    ? $mysqli->prepare("UPDATE utenti SET profile_featured_at = NOW(){$resetPos} WHERE id = ?")
    : $mysqli->prepare("UPDATE utenti SET profile_featured_at = NULL{$resetPos} WHERE id = ?");
$stmt->bind_param('i', $targetId);
$stmt->execute();
$stmt->close();

bio_api_ok(['user_id' => $targetId, 'featured' => $on, 'message' => bio_t($on ? 'sv_added' : 'sv_removed', $bioLang)]);
