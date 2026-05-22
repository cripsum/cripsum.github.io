<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';
require_once __DIR__ . '/../includes/profile_v3_helpers.php';

$slug = trim((string)($_GET['slug'] ?? $_GET['s'] ?? ''));
$slug = profile_v3_slug($slug);
if ($slug === '') {
    http_response_code(404);
    exit('Link not found.');
}

if (!profile_v3_table_exists($mysqli, 'profile_short_links')) {
    http_response_code(503);
    exit('Short links are not installed.');
}

$stmt = $mysqli->prepare('SELECT id, utente_id, link_id, target_url FROM profile_short_links WHERE slug = ? AND is_active = 1 LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    exit('Database unavailable.');
}
$stmt->bind_param('s', $slug);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !profile_is_safe_url($row['target_url'] ?? '', true)) {
    http_response_code(404);
    exit('Link not found.');
}

$shortId = (int)$row['id'];
$profileId = (int)$row['utente_id'];
$linkId = isset($row['link_id']) ? (int)$row['link_id'] : null;

$stmt = $mysqli->prepare('UPDATE profile_short_links SET clicks = clicks + 1 WHERE id = ?');
if ($stmt) {
    $stmt->bind_param('i', $shortId);
    $stmt->execute();
    $stmt->close();
}

if ($linkId && profile_v3_column_exists($mysqli, 'utenti_links', 'click_count')) {
    $stmt = $mysqli->prepare('UPDATE utenti_links SET click_count = click_count + 1 WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $linkId);
        $stmt->execute();
        $stmt->close();
    }
}

profile_v3_track_event($mysqli, $profileId, 'click', $linkId, json_encode(['short_slug' => $slug], JSON_UNESCAPED_SLASHES));

header('Location: ' . $row['target_url'], true, 302);
exit;
