<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';
require_once __DIR__ . '/../includes/profile_v3_helpers.php';
require_once __DIR__ . '/../includes/mission_tracker.php';

checkBan($mysqli);

if (!isLoggedIn()) {
    profile_json_response(['ok' => false, 'message' => 'You must be logged in.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    profile_json_response(['ok' => false, 'message' => 'Invalid method.'], 405);
}

if (!profile_validate_csrf($_POST['csrf_token'] ?? null)) {
    profile_json_response(['ok' => false, 'message' => 'Session expired. Please reload the page.'], 403);
}

$currentUserId = (int)$_SESSION['user_id'];
$targetUserId = isset($_POST['target_user_id']) && profile_is_staff() ? (int)$_POST['target_user_id'] : $currentUserId;

if (!profile_can_edit($targetUserId)) {
    profile_json_response(['ok' => false, 'message' => 'You cannot edit this profile.'], 403);
}

$profile = profile_get_edit_profile($mysqli, $targetUserId);
if (!$profile) {
    profile_json_response(['ok' => false, 'message' => 'Profile not found.'], 404);
}
$profile = profile_v3_apply_extras($profile, $mysqli);

if (!profile_v3_rate_limit($mysqli, 'update_profile', profile_is_staff() ? 80 : 28, 300, $currentUserId)) {
    profile_json_response(['ok' => false, 'message' => 'Too many profile updates. Wait a moment before saving again.'], 429);
}

$username = trim((string)($_POST['username'] ?? ''));
$displayName = profile_clean_text($_POST['display_name'] ?? '', 40);
$displayNameDb = $displayName !== '' ? $displayName : null;

$bio = trim((string)($_POST['bio'] ?? ''));
$bio = mb_substr($bio, 0, 280, 'UTF-8');
$bioDb = $bio !== '' ? $bio : null;
$accentColor = profile_normalize_hex_color($_POST['accent_color'] ?? '#0f5bff');
$secondaryColor = profile_normalize_hex_color($_POST['profile_secondary_color'] ?? '#8b5cf6');
$cardColorDb = profile_optional_hex_color($_POST['profile_card_color'] ?? '');
$textColorDb = profile_optional_hex_color($_POST['profile_text_color'] ?? '');
$linkStyle = profile_allowed_value((string)($_POST['profile_link_style'] ?? 'glass'), ['glass', 'solid', 'outline', 'neon'], 'glass');
$buttonShape = profile_allowed_value((string)($_POST['profile_button_shape'] ?? 'pill'), ['pill', 'rounded', 'sharp'], 'pill');
$theme = profile_allowed_value((string)($_POST['profile_theme'] ?? 'dark'), ['dark', 'light', 'auto'], 'dark');
$layout = profile_allowed_value((string)($_POST['profile_layout'] ?? 'standard'), ['standard', 'compact', 'showcase'], 'standard');
$visibility = profile_allowed_value((string)($_POST['profile_visibility'] ?? 'public'), ['public', 'logged_in', 'private'], 'public');
$discordId = trim((string)($_POST['discord_id'] ?? ''));
$discordIdDb = $discordId !== '' ? $discordId : null;
$discordUseAvatar = profile_bool_from_post('discord_use_avatar', false);
$discordUseDisplayName = profile_bool_from_post('discord_use_display_name', false);
$profileStatus = profile_clean_text($_POST['profile_status'] ?? '', 60);
$profileStatusDb = $profileStatus !== '' ? $profileStatus : null;
$musicUrl = trim((string)($_POST['profile_music_url'] ?? ''));
$musicUrlDb = $musicUrl !== '' ? $musicUrl : null;
$musicTitle = profile_clean_text($_POST['profile_music_title'] ?? '', 80);
$musicTitleDb = $musicTitle !== '' ? $musicTitle : null;
$musicArtist = profile_clean_text($_POST['profile_music_artist'] ?? '', 80);
$musicArtistDb = $musicArtist !== '' ? $musicArtist : null;
$showAudioPlayer = profile_bool_from_post('profile_show_audio_player', true);
$profileEffect = profile_allowed_value((string)($_POST['profile_effect'] ?? 'none'), ['none', 'cursor_glow', 'soft_particles', 'scanlines', 'ambient', 'aurora', 'gradient_waves', 'stars', 'spotlight', 'digital_noise', 'glass_rain'], 'none');
$avatarRingEnabled = profile_bool_from_post('avatar_ring_enabled', true);
$avatarRingStyle = profile_allowed_value((string)($_POST['avatar_ring_style'] ?? 'spin'), ['spin', 'pulse', 'orbit', 'glow', 'dual', 'rainbow', 'halo', 'neon', 'spark', 'glitch', 'none'], 'spin');
$avatarRingColor = profile_normalize_hex_color($_POST['avatar_ring_color'] ?? $accentColor);
$showStats = profile_bool_from_post('profile_show_stats', true);
$showSocials = profile_bool_from_post('profile_show_socials', true);
$showLinks = profile_bool_from_post('profile_show_links', true);
$showProjects = profile_bool_from_post('profile_show_projects', true);
$showContents = profile_bool_from_post('profile_show_contents', true);
$showBadges = profile_bool_from_post('profile_show_badges', true);
$showActivity = profile_bool_from_post('profile_show_activity', true);
$showDiscord = profile_bool_from_post('profile_show_discord', true);
$displayNameEn = profile_clean_text($_POST['display_name_en'] ?? '', 40);
$bioEn = trim((string)($_POST['bio_en'] ?? ''));
$bioEn = mb_substr($bioEn, 0, 280, 'UTF-8');
$profileStatusEn = profile_clean_text($_POST['profile_status_en'] ?? '', 60);
$profileLocale = profile_allowed_value((string)($_POST['profile_locale'] ?? 'it'), ['it', 'en'], 'it');
$enterEnabled = profile_bool_from_post('profile_enter_enabled', false);
$enterText = profile_clean_text($_POST['profile_enter_text'] ?? '', 80);
$enterTextEn = profile_clean_text($_POST['profile_enter_text_en'] ?? '', 80);
$enterButton = profile_clean_text($_POST['profile_enter_button'] ?? '', 40);
$enterButtonEn = profile_clean_text($_POST['profile_enter_button_en'] ?? '', 40);
$enterRemember = profile_bool_from_post('profile_enter_remember', true);
$backgroundMode = profile_allowed_value((string)($_POST['profile_background_mode'] ?? 'upload'), ['upload', 'image', 'video', 'youtube', 'gradient'], 'upload');
$backgroundConfigJson = profile_v3_normalize_background_config((string)($_POST['profile_background_config'] ?? '{}'));
$youtubeUrl = trim((string)($_POST['profile_youtube_url'] ?? ''));
$fallbackImageUrl = trim((string)($_POST['profile_fallback_image_url'] ?? ''));
$canvasEffect = profile_v3_allowed_canvas((string)($_POST['profile_canvas_effect'] ?? 'none'));
$canvasConfigJson = profile_v3_normalize_canvas_config((string)($_POST['profile_canvas_config'] ?? '{}'));
$avatarEffect = profile_allowed_value((string)($_POST['profile_avatar_effect'] ?? 'pfp-glow'), ['pfp-glow', 'pfp-float', 'pfp-neon-border', 'pfp-glitch', 'pfp-pulse-ring', 'pfp-spin', 'pfp-shake', 'pfp-pixelate', 'pfp-rgb-shift', 'pfp-holographic'], 'pfp-glow');
$avatarShape = profile_allowed_value((string)($_POST['profile_avatar_shape'] ?? 'circle'), ['circle', 'squircle', 'hexagon'], 'circle');
$avatarFrameUrl = trim((string)($_POST['profile_avatar_frame_url'] ?? ''));
$themePreset = profile_v3_allowed_preset((string)($_POST['profile_theme_preset'] ?? 'cyber'));
$fontFamily = profile_v3_allowed_font((string)($_POST['profile_font_family'] ?? 'inter'));
$noiseEnabled = profile_bool_from_post('profile_noise_enabled', true);
$animationsEnabled = profile_bool_from_post('profile_animations_enabled', true);
$builderJson = profile_v3_normalize_builder_input((string)($_POST['profile_builder_json'] ?? '{}'));
$pluginsJson = profile_v3_normalize_plugins((string)($_POST['profile_plugins_json'] ?? '[]'));

if (!profile_is_valid_username($username)) {
    profile_json_response(['ok' => false, 'message' => 'Invalid username. Use 3-20 characters, letters, numbers, or underscores.'], 422);
}

if (mb_strlen($bio, 'UTF-8') > 280) {
    profile_json_response(['ok' => false, 'message' => 'Bio too long.'], 422);
}

if (!profile_is_valid_discord_id($discordId)) {
    profile_json_response(['ok' => false, 'message' => 'Invalid Discord ID. Must contain only numbers.'], 422);
}

$hasConnectedDiscord = !empty($profile['discord_username']) && $discordIdDb !== null && $discordIdDb === (string)($profile['discord_id'] ?? '');
if (!$hasConnectedDiscord) {
    $discordUseAvatar = 0;
    $discordUseDisplayName = 0;
}

if (!profile_is_safe_url($musicUrl, false)) {
    profile_json_response(['ok' => false, 'message' => 'Invalid music URL.'], 422);
}

if (!profile_is_safe_url($youtubeUrl, false) || ($youtubeUrl !== '' && !profile_v3_youtube_id($youtubeUrl))) {
    profile_json_response(['ok' => false, 'message' => 'Invalid YouTube background URL.'], 422);
}

if (!profile_is_safe_url($fallbackImageUrl, false)) {
    profile_json_response(['ok' => false, 'message' => 'Invalid fallback image URL.'], 422);
}

if (!profile_is_safe_url($avatarFrameUrl, false)) {
    profile_json_response(['ok' => false, 'message' => 'Invalid avatar frame URL.'], 422);
}

$stmt = $mysqli->prepare("SELECT id FROM utenti WHERE LOWER(username) = LOWER(?) AND id != ? LIMIT 1");
$stmt->bind_param('si', $username, $targetUserId);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($exists) {
    profile_json_response(['ok' => false, 'message' => 'Username already in use.'], 409);
}

$avatarUpload = profile_handle_image_upload($_FILES['avatar'] ?? ['error' => UPLOAD_ERR_NO_FILE], 2 * 1024 * 1024);
if (!empty($avatarUpload['error'])) {
    profile_json_response(['ok' => false, 'message' => 'Avatar: ' . $avatarUpload['error']], 422);
}

$bannerUpload = profile_handle_background_upload($_FILES['banner'] ?? ['error' => UPLOAD_ERR_NO_FILE], 12 * 1024 * 1024);
if (!empty($bannerUpload['error'])) {
    profile_json_response(['ok' => false, 'message' => 'Profile background: ' . $bannerUpload['error']], 422);
}

$musicUpload = profile_handle_audio_upload($_FILES['profile_music_file'] ?? ['error' => UPLOAD_ERR_NO_FILE], 12 * 1024 * 1024);
if (!empty($musicUpload['error'])) {
    profile_json_response(['ok' => false, 'message' => 'Profile audio: ' . $musicUpload['error']], 422);
}
$removeMusicUpload = !empty($_POST['remove_profile_music_upload']);
$avatarChanged = !empty($avatarUpload['has_file']);
if ($avatarChanged) {
    $discordUseAvatar = 0;
}
if (!empty($musicUpload['has_file'])) {
    $musicUrlDb = null;
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
$blockRows = array_slice(profile_decode_rows('blocks_json'), 0, 10);
$badgeRows = array_slice(profile_decode_rows('badges_json'), 0, 8);

$allowedPlatforms = ['tiktok', 'instagram', 'youtube', 'twitch', 'github', 'discord', 'telegram', 'x', 'twitter', 'spotify', 'soundcloud', 'steam', 'reddit', 'pinterest', 'snapchat', 'facebook', 'linkedin', 'paypal', 'patreon', 'kick', 'bluesky', 'threads', 'behance', 'dribbble', 'website', 'email', 'other'];
$allowedStatuses = ['active', 'paused', 'finished', 'idea'];
$allowedContentTypes = ['edit', 'video', 'game', 'post', 'other'];
$allowedBlockTypes = ['text', 'image', 'gif', 'video'];

try {
    $mysqli->begin_transaction();

    $stmt = $mysqli->prepare("
        UPDATE utenti
        SET username = ?, display_name = ?, bio = ?, accent_color = ?, profile_secondary_color = ?, profile_card_color = ?, profile_text_color = ?, profile_link_style = ?, profile_button_shape = ?, profile_theme = ?, profile_layout = ?, profile_visibility = ?, discord_id = ?, discord_use_avatar = ?, discord_use_display_name = ?, profile_status = ?,
            profile_music_url = ?, profile_music_title = ?, profile_music_artist = ?, profile_effect = ?, avatar_ring_style = ?, avatar_ring_color = ?,
            profile_show_stats = ?, profile_show_socials = ?, profile_show_links = ?, profile_show_projects = ?, profile_show_contents = ?, profile_show_badges = ?, profile_show_activity = ?, profile_show_discord = ?, profile_show_audio_player = ?, avatar_ring_enabled = ?,
            profile_updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param(
        'sssssssssssssiisssssssiiiiiiiiiii',
        $username,
        $displayNameDb,
        $bioDb,
        $accentColor,
        $secondaryColor,
        $cardColorDb,
        $textColorDb,
        $linkStyle,
        $buttonShape,
        $theme,
        $layout,
        $visibility,
        $discordIdDb,
        $discordUseAvatar,
        $discordUseDisplayName,
        $profileStatusDb,
        $musicUrlDb,
        $musicTitleDb,
        $musicArtistDb,
        $profileEffect,
        $avatarRingStyle,
        $avatarRingColor,
        $showStats,
        $showSocials,
        $showLinks,
        $showProjects,
        $showContents,
        $showBadges,
        $showActivity,
        $showDiscord,
        $showAudioPlayer,
        $avatarRingEnabled,
        $targetUserId
    );
    if (!$stmt->execute()) throw new RuntimeException('Error updating profile.');
    $stmt->close();

    profile_v3_update_user_columns($mysqli, $targetUserId, [
        'display_name_en' => ['value' => $displayNameEn !== '' ? $displayNameEn : null, 'type' => 's'],
        'bio_en' => ['value' => $bioEn !== '' ? $bioEn : null, 'type' => 's'],
        'profile_status_en' => ['value' => $profileStatusEn !== '' ? $profileStatusEn : null, 'type' => 's'],
        'profile_locale' => ['value' => $profileLocale, 'type' => 's'],
        'profile_enter_enabled' => ['value' => $enterEnabled, 'type' => 'i'],
        'profile_enter_text' => ['value' => $enterText !== '' ? $enterText : null, 'type' => 's'],
        'profile_enter_text_en' => ['value' => $enterTextEn !== '' ? $enterTextEn : null, 'type' => 's'],
        'profile_enter_button' => ['value' => $enterButton !== '' ? $enterButton : null, 'type' => 's'],
        'profile_enter_button_en' => ['value' => $enterButtonEn !== '' ? $enterButtonEn : null, 'type' => 's'],
        'profile_enter_remember' => ['value' => $enterRemember, 'type' => 'i'],
        'profile_background_mode' => ['value' => $backgroundMode, 'type' => 's'],
        'profile_background_config' => ['value' => $backgroundConfigJson, 'type' => 's'],
        'profile_youtube_url' => ['value' => $youtubeUrl !== '' ? $youtubeUrl : null, 'type' => 's'],
        'profile_fallback_image_url' => ['value' => $fallbackImageUrl !== '' ? $fallbackImageUrl : null, 'type' => 's'],
        'profile_canvas_effect' => ['value' => $canvasEffect, 'type' => 's'],
        'profile_canvas_config' => ['value' => $canvasConfigJson, 'type' => 's'],
        'profile_avatar_effect' => ['value' => $avatarEffect, 'type' => 's'],
        'profile_avatar_shape' => ['value' => $avatarShape, 'type' => 's'],
        'profile_avatar_frame_url' => ['value' => $avatarFrameUrl !== '' ? $avatarFrameUrl : null, 'type' => 's'],
        'profile_theme_preset' => ['value' => $themePreset, 'type' => 's'],
        'profile_font_family' => ['value' => $fontFamily, 'type' => 's'],
        'profile_noise_enabled' => ['value' => $noiseEnabled, 'type' => 'i'],
        'profile_animations_enabled' => ['value' => $animationsEnabled, 'type' => 'i'],
        'profile_builder_json' => ['value' => $builderJson, 'type' => 's'],
        'profile_plugins_json' => ['value' => $pluginsJson, 'type' => 's'],
    ]);

    if (!empty($avatarUpload['has_file']) && isset($avatarUpload['blob'], $avatarUpload['mime'])) {
        $stmt = $mysqli->prepare("UPDATE utenti SET profile_pic = ?, profile_pic_type = ? WHERE id = ?");
        $null = null;
        $stmt->bind_param('bsi', $null, $avatarUpload['mime'], $targetUserId);
        $stmt->send_long_data(0, $avatarUpload['blob']);
        if (!$stmt->execute()) throw new RuntimeException('Error saving avatar.');
        $stmt->close();

        if (function_exists('profile_unlock_achievement')) {
            profile_unlock_achievement($mysqli, $targetUserId, 2);
        }
        profile_record_activity($mysqli, $targetUserId, 'profile_update', 'Updated profile picture');
    }

    if (!empty($bannerUpload['has_file']) && isset($bannerUpload['blob'], $bannerUpload['mime'])) {
        $stmt = $mysqli->prepare("UPDATE utenti SET profile_banner = ?, profile_banner_type = ? WHERE id = ?");
        $null = null;
        $stmt->bind_param('bsi', $null, $bannerUpload['mime'], $targetUserId);
        $stmt->send_long_data(0, $bannerUpload['blob']);
        if (!$stmt->execute()) throw new RuntimeException('Error saving profile background.');
        $stmt->close();
    }

    if (!empty($musicUpload['has_file']) && isset($musicUpload['blob'], $musicUpload['mime'])) {
        $stmt = $mysqli->prepare("UPDATE utenti SET profile_music_blob = ?, profile_music_mime = ?, profile_music_url = NULL WHERE id = ?");
        $null = null;
        $stmt->bind_param('bsi', $null, $musicUpload['mime'], $targetUserId);
        $stmt->send_long_data(0, $musicUpload['blob']);
        if (!$stmt->execute()) throw new RuntimeException('Error saving MP3.');
        $stmt->close();
    } elseif ($removeMusicUpload || $musicUrlDb) {
        $stmt = $mysqli->prepare("UPDATE utenti SET profile_music_blob = NULL, profile_music_mime = NULL WHERE id = ?");
        $stmt->bind_param('i', $targetUserId);
        if (!$stmt->execute()) throw new RuntimeException('Error removing MP3.');
        $stmt->close();
    }

    $stmt = $mysqli->prepare("DELETE FROM utenti_social WHERE utente_id = ?");
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $stmt->close();

    $insertSocial = $mysqli->prepare("INSERT INTO utenti_social (utente_id, platform, label, display_username, url, sort_order, is_visible) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($socialRows as $i => $row) {
        $platform = strtolower(profile_clean_text($row['platform'] ?? 'other', 32));
        $platform = in_array($platform, $allowedPlatforms, true) ? $platform : 'other';
        $label = profile_clean_text($row['label'] ?? $platform, 40);
        $displayUsername = profile_clean_text($row['display_username'] ?? '', 60);
        $displayUsernameDb = $displayUsername !== '' ? $displayUsername : null;
        $url = trim((string)($row['url'] ?? ''));
        $visible = !empty($row['is_visible']) ? 1 : 0;
        if ($url === '') continue;
        if (!profile_is_safe_url($url, true)) throw new RuntimeException('Invalid social URL: ' . $label);
        $insertSocial->bind_param('issssii', $targetUserId, $platform, $label, $displayUsernameDb, $url, $i, $visible);
        if (!$insertSocial->execute()) throw new RuntimeException('Error saving social.');
    }
    $insertSocial->close();

    $stmt = $mysqli->prepare("DELETE FROM utenti_links WHERE utente_id = ?");
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $stmt->close();
    if (profile_v3_table_exists($mysqli, 'profile_short_links')) {
        $stmt = $mysqli->prepare("UPDATE profile_short_links SET is_active = 0, link_id = NULL WHERE utente_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $targetUserId);
            $stmt->execute();
            $stmt->close();
        }
    }

    $insertLink = $mysqli->prepare("INSERT INTO utenti_links (utente_id, title, description, url, icon, button_style, is_featured, is_visible, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($linkRows as $i => $row) {
        $title = profile_clean_text($row['title'] ?? '', 60);
        $description = profile_clean_text($row['description'] ?? '', 160);
        $url = trim((string)($row['url'] ?? ''));
        $icon = profile_clean_text($row['icon'] ?? 'fas fa-link', 40);
        $buttonStyle = profile_allowed_value((string)($row['button_style'] ?? 'card'), ['card', 'compact', 'icon'], 'card');
        $featured = !empty($row['is_featured']) ? 1 : 0;
        $visible = !empty($row['is_visible']) ? 1 : 0;
        if ($title === '' && $url === '') continue;
        if ($title === '') throw new RuntimeException('A link must have a title.');
        if (!profile_is_safe_url($url, true)) throw new RuntimeException('Invalid link URL: ' . $title);
        $insertLink->bind_param('isssssiii', $targetUserId, $title, $description, $url, $icon, $buttonStyle, $featured, $visible, $i);
        if (!$insertLink->execute()) throw new RuntimeException('Error saving link.');
        profile_v3_update_link_extras($mysqli, $targetUserId, (int)$mysqli->insert_id, $row, $url);
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
        if (!profile_is_safe_url($url, false)) throw new RuntimeException('Invalid project URL: ' . $title);
        if (!profile_is_safe_url($imageUrl, false)) throw new RuntimeException('Invalid project image: ' . $title);
        $insertProject->bind_param('issssssiii', $targetUserId, $title, $description, $url, $imageUrl, $techStack, $status, $featured, $visible, $i);
        if (!$insertProject->execute()) throw new RuntimeException('Error saving project.');
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
        if (!profile_is_safe_url($url, false)) throw new RuntimeException('Invalid content URL: ' . $title);
        if (!profile_is_safe_url($thumb, false)) throw new RuntimeException('Invalid thumbnail: ' . $title);
        $insertContent->bind_param('isssssiii', $targetUserId, $type, $title, $description, $url, $thumb, $featured, $visible, $i);
        if (!$insertContent->execute()) throw new RuntimeException('Error saving content.');
    }
    $insertContent->close();

    $stmt = $mysqli->prepare("DELETE FROM utenti_profile_blocks WHERE utente_id = ?");
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $stmt->close();

    $insertBlock = $mysqli->prepare("INSERT INTO utenti_profile_blocks (utente_id, block_type, title, body, media_url, media_type, is_featured, is_visible, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($blockRows as $i => $row) {
        $type = profile_allowed_value((string)($row['block_type'] ?? 'text'), $allowedBlockTypes, 'text');
        $title = profile_clean_text($row['title'] ?? '', 80);
        $body = trim((string)($row['body'] ?? ''));
        $body = mb_substr($body, 0, 700, 'UTF-8');
        $mediaUrl = trim((string)($row['media_url'] ?? ''));
        $mediaType = profile_media_type_from_url($mediaUrl, $type === 'gif' ? 'gif' : ($type === 'video' ? 'video' : 'image'));
        $featured = !empty($row['is_featured']) ? 1 : 0;
        $visible = !empty($row['is_visible']) ? 1 : 0;

        if ($title === '' && $body === '' && $mediaUrl === '') continue;
        if ($type !== 'text' && !profile_is_safe_url($mediaUrl, true)) {
            throw new RuntimeException('Invalid media URL in custom block.');
        }
        if ($type === 'text') {
            $mediaUrl = null;
            $mediaType = 'text';
        }

        $insertBlock->bind_param('isssssiii', $targetUserId, $type, $title, $body, $mediaUrl, $mediaType, $featured, $visible, $i);
        if (!$insertBlock->execute()) throw new RuntimeException('Error saving custom blocks.');
    }
    $insertBlock->close();

    if ($musicUrlDb || !empty($musicUpload['has_file'])) {
        profile_record_activity($mysqli, $targetUserId, 'music', 'Updated profile song', $musicUrlDb ?: null);
    }

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
        if (!$insertBadge->execute()) throw new RuntimeException('Error saving badge.');
    }
    $insertBadge->close();

    profile_record_activity($mysqli, $targetUserId, 'profile_update', 'Updated profile');

    $mysqli->commit();

    // ── MISSION TRACKING ─────────────────────────────────────────────────────
    // Traccia solo quando l'utente modifica il PROPRIO profilo.
    // Lo staff che modifica profili altrui non genera progresso missioni.
    if ($targetUserId === $currentUserId) {
        try {
            trackMissionProgress($mysqli, $currentUserId, 'edit_profile');
        } catch (Throwable $trackErr) {
            error_log('[MissionTracking update_profile] ' . $trackErr->getMessage());
        }
    }
    // ── /MISSION TRACKING ────────────────────────────────────────────────────

    if ($targetUserId === $currentUserId) {
        $_SESSION['username'] = $username;
    } elseif (profile_is_staff()) {
        profile_v3_admin_log($mysqli, $currentUserId, $targetUserId, 'profile_update_v3', 'Updated Cripsum Custom Profile 3.0 settings');
    }

    $nextCsrf = profile_v3_rotate_csrf_token();

    profile_json_response([
        'ok' => true,
        'message' => 'Profile saved.',
        'profile_url' => '/u/' . rawurlencode(strtolower($username)),
        'csrf_token' => $nextCsrf,
    ]);
} catch (Throwable $e) {
    $mysqli->rollback();
    profile_json_response(['ok' => false, 'message' => $e->getMessage()], 422);
}
