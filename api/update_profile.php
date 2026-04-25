<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

checkBan($mysqli);

if (!isLoggedIn()) {
    profile_json_response(['ok' => false, 'message' => 'Devi essere loggato.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    profile_json_response(['ok' => false, 'message' => 'Metodo non valido.'], 405);
}

if (!profile_validate_csrf($_POST['csrf_token'] ?? null)) {
    profile_json_response(['ok' => false, 'message' => 'Sessione scaduta. Ricarica la pagina.'], 403);
}

$currentUserId = (int)$_SESSION['user_id'];
$targetUserId = isset($_POST['target_user_id']) && profile_is_staff() ? (int)$_POST['target_user_id'] : $currentUserId;

if (!profile_can_edit($targetUserId)) {
    profile_json_response(['ok' => false, 'message' => 'Non puoi modificare questo profilo.'], 403);
}

$profile = profile_get_edit_profile($mysqli, $targetUserId);
if (!$profile) {
    profile_json_response(['ok' => false, 'message' => 'Profilo non trovato.'], 404);
}

$username = trim((string)($_POST['username'] ?? ''));
$displayName = profile_clean_text($_POST['display_name'] ?? '', 40);
$displayNameDb = $displayName !== '' ? $displayName : null;

$bio = trim((string)($_POST['bio'] ?? ''));
$bio = mb_substr($bio, 0, 280, 'UTF-8');
$bioDb = $bio !== '' ? $bio : null;
$accentColor = profile_normalize_hex_color($_POST['accent_color'] ?? '#0f5bff');
$theme = profile_allowed_value((string)($_POST['profile_theme'] ?? 'dark'), ['dark', 'light', 'auto'], 'dark');
$layout = profile_allowed_value((string)($_POST['profile_layout'] ?? 'standard'), ['standard', 'compact', 'showcase'], 'standard');
$visibility = profile_allowed_value((string)($_POST['profile_visibility'] ?? 'public'), ['public', 'logged_in', 'private'], 'public');
$discordId = trim((string)($_POST['discord_id'] ?? ''));
$discordIdDb = $discordId !== '' ? $discordId : null;

if (!profile_is_valid_username($username)) {
    profile_json_response(['ok' => false, 'message' => 'Username non valido. Usa 3-20 caratteri, lettere, numeri o underscore.'], 422);
}

if (mb_strlen($bio, 'UTF-8') > 280) {
    profile_json_response(['ok' => false, 'message' => 'Bio troppo lunga.'], 422);
}

if (!profile_is_valid_discord_id($discordId)) {
    profile_json_response(['ok' => false, 'message' => 'ID Discord non valido. Deve contenere solo numeri.'], 422);
}

$stmt = $mysqli->prepare("SELECT id FROM utenti WHERE LOWER(username) = LOWER(?) AND id != ? LIMIT 1");
$stmt->bind_param('si', $username, $targetUserId);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($exists) {
    profile_json_response(['ok' => false, 'message' => 'Username già in uso.'], 409);
}

$avatarUpload = profile_handle_image_upload($_FILES['avatar'] ?? ['error' => UPLOAD_ERR_NO_FILE], 2 * 1024 * 1024);
if (!empty($avatarUpload['error'])) {
    profile_json_response(['ok' => false, 'message' => 'Avatar: ' . $avatarUpload['error']], 422);
}

$bannerUpload = profile_handle_background_upload($_FILES['banner'] ?? ['error' => UPLOAD_ERR_NO_FILE], 12 * 1024 * 1024);
if (!empty($bannerUpload['error'])) {
    profile_json_response(['ok' => false, 'message' => 'Sfondo profilo: ' . $bannerUpload['error']], 422);
}

function profile_decode_rows(string $key): array
{
    $raw = $_POST[$key] ?? '[]';
    $rows = json_decode((string)$raw, true);
    return is_array($rows) ? $rows : [];
}

$socialRows = array_slice(profile_decode_rows('socials_json'), 0, 10);
$linkRows = array_slice(profile_decode_rows('links_json'), 0, 12);
$projectRows = array_slice(profile_decode_rows('projects_json'), 0, 8);
$contentRows = array_slice(profile_decode_rows('contents_json'), 0, 8);
$badgeRows = array_slice(profile_decode_rows('badges_json'), 0, 8);

$allowedPlatforms = ['tiktok','instagram','youtube','twitch','github','discord','telegram','x','twitter','website','steam','other'];
$allowedStatuses = ['active','paused','finished','idea'];
$allowedContentTypes = ['edit','video','game','post','other'];

try {
    $mysqli->begin_transaction();

    $stmt = $mysqli->prepare("
        UPDATE utenti
        SET username = ?, display_name = ?, bio = ?, accent_color = ?, profile_theme = ?, profile_layout = ?, profile_visibility = ?, discord_id = ?, profile_updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param('ssssssssi', $username, $displayNameDb, $bioDb, $accentColor, $theme, $layout, $visibility, $discordIdDb, $targetUserId);
    if (!$stmt->execute()) throw new RuntimeException('Errore salvataggio profilo.');
    $stmt->close();

    if (!empty($avatarUpload['has_file']) && isset($avatarUpload['blob'], $avatarUpload['mime'])) {
        $stmt = $mysqli->prepare("UPDATE utenti SET profile_pic = ?, profile_pic_type = ? WHERE id = ?");
        $null = null;
        $stmt->bind_param('bsi', $null, $avatarUpload['mime'], $targetUserId);
        $stmt->send_long_data(0, $avatarUpload['blob']);
        if (!$stmt->execute()) throw new RuntimeException('Errore salvataggio avatar.');
        $stmt->close();
    }

    if (!empty($bannerUpload['has_file']) && isset($bannerUpload['blob'], $bannerUpload['mime'])) {
        $stmt = $mysqli->prepare("UPDATE utenti SET profile_banner = ?, profile_banner_type = ? WHERE id = ?");
        $null = null;
        $stmt->bind_param('bsi', $null, $bannerUpload['mime'], $targetUserId);
        $stmt->send_long_data(0, $bannerUpload['blob']);
        if (!$stmt->execute()) throw new RuntimeException('Errore salvataggio sfondo profilo.');
        $stmt->close();
    }

    $stmt = $mysqli->prepare("DELETE FROM utenti_social WHERE utente_id = ?");
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $stmt->close();

    $insertSocial = $mysqli->prepare("INSERT INTO utenti_social (utente_id, platform, label, url, sort_order, is_visible) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($socialRows as $i => $row) {
        $platform = strtolower(profile_clean_text($row['platform'] ?? 'other', 32));
        $platform = in_array($platform, $allowedPlatforms, true) ? $platform : 'other';
        $label = profile_clean_text($row['label'] ?? $platform, 40);
        $url = trim((string)($row['url'] ?? ''));
        $visible = !empty($row['is_visible']) ? 1 : 0;
        if ($url === '') continue;
        if (!profile_is_safe_url($url, true)) throw new RuntimeException('URL social non valido: ' . $label);
        $insertSocial->bind_param('isssii', $targetUserId, $platform, $label, $url, $i, $visible);
        if (!$insertSocial->execute()) throw new RuntimeException('Errore salvataggio social.');
    }
    $insertSocial->close();

    $stmt = $mysqli->prepare("DELETE FROM utenti_links WHERE utente_id = ?");
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $stmt->close();

    $insertLink = $mysqli->prepare("INSERT INTO utenti_links (utente_id, title, description, url, icon, is_featured, is_visible, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($linkRows as $i => $row) {
        $title = profile_clean_text($row['title'] ?? '', 60);
        $description = profile_clean_text($row['description'] ?? '', 160);
        $url = trim((string)($row['url'] ?? ''));
        $icon = profile_clean_text($row['icon'] ?? 'fas fa-link', 40);
        $featured = !empty($row['is_featured']) ? 1 : 0;
        $visible = !empty($row['is_visible']) ? 1 : 0;
        if ($title === '' && $url === '') continue;
        if ($title === '') throw new RuntimeException('Un link non ha titolo.');
        if (!profile_is_safe_url($url, true)) throw new RuntimeException('URL link non valido: ' . $title);
        $insertLink->bind_param('issssiii', $targetUserId, $title, $description, $url, $icon, $featured, $visible, $i);
        if (!$insertLink->execute()) throw new RuntimeException('Errore salvataggio link.');
    }
    $insertLink->close();

    $stmt = $mysqli->prepare("DELETE FROM utenti_projects WHERE utente_id = ?");
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $stmt->close();

    $insertProject = $mysqli->prepare("INSERT INTO utenti_projects (utente_id, title, description, url, image_url, tech_stack, status, is_featured, is_visible, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($projectRows as $i => $row) {
        $title = profile_clean_text($row['title'] ?? '', 70);
        $description = profile_clean_text($row['description'] ?? '', 260);
        $url = trim((string)($row['url'] ?? ''));
        $imageUrl = trim((string)($row['image_url'] ?? ''));
        $techStack = profile_clean_text($row['tech_stack'] ?? '', 160);
        $status = profile_allowed_value((string)($row['status'] ?? 'active'), $allowedStatuses, 'active');
        $featured = !empty($row['is_featured']) ? 1 : 0;
        $visible = !empty($row['is_visible']) ? 1 : 0;
        if ($title === '') continue;
        if (!profile_is_safe_url($url, false)) throw new RuntimeException('URL progetto non valido: ' . $title);
        if (!profile_is_safe_url($imageUrl, false)) throw new RuntimeException('Immagine progetto non valida: ' . $title);
        $insertProject->bind_param('issssssiii', $targetUserId, $title, $description, $url, $imageUrl, $techStack, $status, $featured, $visible, $i);
        if (!$insertProject->execute()) throw new RuntimeException('Errore salvataggio progetto.');
    }
    $insertProject->close();

    $stmt = $mysqli->prepare("DELETE FROM utenti_contents WHERE utente_id = ?");
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $stmt->close();

    $insertContent = $mysqli->prepare("INSERT INTO utenti_contents (utente_id, content_type, title, description, url, thumbnail_url, is_featured, is_visible, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($contentRows as $i => $row) {
        $type = profile_allowed_value((string)($row['content_type'] ?? 'other'), $allowedContentTypes, 'other');
        $title = profile_clean_text($row['title'] ?? '', 70);
        $description = profile_clean_text($row['description'] ?? '', 220);
        $url = trim((string)($row['url'] ?? ''));
        $thumb = trim((string)($row['thumbnail_url'] ?? ''));
        $featured = !empty($row['is_featured']) ? 1 : 0;
        $visible = !empty($row['is_visible']) ? 1 : 0;
        if ($title === '') continue;
        if (!profile_is_safe_url($url, false)) throw new RuntimeException('URL contenuto non valido: ' . $title);
        if (!profile_is_safe_url($thumb, false)) throw new RuntimeException('Thumbnail non valida: ' . $title);
        $insertContent->bind_param('isssssiii', $targetUserId, $type, $title, $description, $url, $thumb, $featured, $visible, $i);
        if (!$insertContent->execute()) throw new RuntimeException('Errore salvataggio contenuto.');
    }
    $insertContent->close();

    $stmt = $mysqli->prepare("DELETE FROM utenti_profile_badges WHERE utente_id = ?");
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $stmt->close();

    $insertBadge = $mysqli->prepare("
        INSERT INTO utenti_profile_badges (utente_id, achievement_id, sort_order, is_visible)
        SELECT ?, ua.achievement_id, ?, 1
        FROM utenti_achievement ua
        WHERE ua.utente_id = ? AND ua.achievement_id = ?
        LIMIT 1
    ");
    foreach ($badgeRows as $i => $badgeId) {
        $badgeId = (int)$badgeId;
        if ($badgeId <= 0) continue;
        $insertBadge->bind_param('iiii', $targetUserId, $i, $targetUserId, $badgeId);
        if (!$insertBadge->execute()) throw new RuntimeException('Errore salvataggio badge.');
    }
    $insertBadge->close();

    profile_record_activity($mysqli, $targetUserId, 'profile_update', 'Ha aggiornato il profilo');

    $mysqli->commit();

    if ($targetUserId === $currentUserId) {
        $_SESSION['username'] = $username;
    }

    profile_json_response([
        'ok' => true,
        'message' => 'Profilo salvato.',
        'profile_url' => '/u/' . rawurlencode(strtolower($username)),
    ]);
} catch (Throwable $e) {
    $mysqli->rollback();
    profile_json_response(['ok' => false, 'message' => $e->getMessage()], 422);
}
