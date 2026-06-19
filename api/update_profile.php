<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';
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
$isPremium = (int)($profile['is_premium'] ?? 0) === 1;

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
$font = profile_allowed_value((string)($_POST['profile_font'] ?? 'Poppins'), [
    'Poppins', 'Inter', 'Roboto', 'Outfit', 'Playfair Display', 
    'Space Grotesk', 'Syne', 'Montserrat', 'Fira Code', 'PT Mono', 
    'Cinzel', 'Rubik', 'Bebas Neue', 'Minecraft', 'Gang of Three',
    'Press Start 2P', 'Bungee', 'Permanent Marker', 'Creepster', 'Shojumaru'
], 'Poppins');
$allowedFreeFonts = ['Poppins', 'Inter', 'Roboto', 'Outfit', 'Montserrat'];
if (!$isPremium && !in_array($font, $allowedFreeFonts, true)) {
    $font = 'Poppins';
}
$borderRadius = (int)($_POST['profile_border_radius'] ?? 30);
if ($borderRadius < 0 || $borderRadius > 40) $borderRadius = 30;
$cardOpacity = (int)($_POST['profile_card_opacity'] ?? 68);
if ($cardOpacity < 0 || $cardOpacity > 100) $cardOpacity = 68;
$cardBlur = (int)($_POST['profile_card_blur'] ?? 20);
if ($cardBlur < 0 || $cardBlur > 40) $cardBlur = 20;
$borderColorDb = profile_optional_hex_color($_POST['profile_border_color'] ?? '');
$borderWidth = (int)($_POST['profile_border_width'] ?? 1);
if ($borderWidth < 0 || $borderWidth > 5) $borderWidth = 1;
$borderOpacity = (int)($_POST['profile_border_opacity'] ?? 100);
if ($borderOpacity < 0 || $borderOpacity > 100) $borderOpacity = 100;

$uiShape = profile_allowed_value((string)($_POST['profile_ui_shape'] ?? 'circle'), ['circle', 'rounded', 'soft', 'square-rounded', 'square', 'pill'], 'circle');
$avatarShape = profile_allowed_value((string)($_POST['profile_avatar_shape'] ?? 'circle'), ['circle', 'squircle', 'square', 'hexagon', 'octagon', 'badge'], 'circle');
$socialSize = (int)($_POST['profile_social_size'] ?? 42);
if ($socialSize < 32 || $socialSize > 72) $socialSize = 42;
$iconSpacing = (int)($_POST['profile_icon_spacing'] ?? 8);
if ($iconSpacing < 0 || $iconSpacing > 24) $iconSpacing = 8;
$badgeSize = (int)($_POST['profile_badge_size'] ?? 24);
if ($badgeSize < 16 || $badgeSize > 60) $badgeSize = 24;
$buttonSize = (int)($_POST['profile_button_size'] ?? 48);
if ($buttonSize < 32 || $buttonSize > 80) $buttonSize = 48;

$theme = profile_allowed_value((string)($_POST['profile_theme'] ?? 'dark'), ['dark', 'light', 'auto'], 'dark');
$rawLayout = (string)($_POST['profile_layout'] ?? 'standard');
$layoutAliases = [
    'left-tabs' => 'standard',
    'right-tabs' => 'showcase',
    'stacked' => 'clean',
    'center-split' => 'compact',
];
$rawLayout = $layoutAliases[$rawLayout] ?? $rawLayout;
$layout = profile_allowed_value($rawLayout, ['standard', 'compact', 'showcase', 'clean'], 'standard');
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
$profileEffect = profile_allowed_value((string)($_POST['profile_effect'] ?? 'none'), ['none', 'cursor_glow', 'soft_particles', 'scanlines', 'ambient', 'aurora', 'gradient_waves', 'stars', 'spotlight', 'digital_noise', 'glass_rain', 'sakura_falling', 'cyber_grid'], 'none');
$allowedFreeEffects = ['none', 'cursor_glow', 'stars'];
if (!$isPremium && !in_array($profileEffect, $allowedFreeEffects, true)) {
    $profileEffect = 'none';
}
$avatarRingEnabled = profile_bool_from_post('avatar_ring_enabled', true);
$avatarRingStyle = profile_allowed_value((string)($_POST['avatar_ring_style'] ?? 'spin'), ['spin', 'pulse', 'orbit', 'glow', 'dual', 'rainbow', 'halo', 'neon', 'spark', 'glitch', 'none'], 'spin');
$avatarRingColor = profile_normalize_hex_color($_POST['avatar_ring_color'] ?? $accentColor);
$avatarBorder = profile_bool_from_post('profile_avatar_border', true);

$nameStyleConfig = [
    'type' => profile_allowed_value((string)($_POST['profile_name_color_type'] ?? 'default'), ['default', 'solid', 'gradient'], 'default'),
    'solid_color' => profile_normalize_hex_color($_POST['profile_name_solid_color'] ?? '#ffffff'),
    'grad_color1' => profile_normalize_hex_color($_POST['profile_name_grad_color1'] ?? '#ffffff'),
    'grad_color2' => profile_normalize_hex_color($_POST['profile_name_grad_color2'] ?? '#8b5cf6'),
    'grad_angle' => min(max((int)($_POST['profile_name_grad_angle'] ?? 90), 0), 360),
    'animation' => profile_allowed_value((string)($_POST['profile_name_animation'] ?? 'none'), ['none', 'rainbow', 'glow', 'sparkles', 'fire', 'water', 'glitch', 'neon', 'bounce'], 'none'),
    'glow_color' => profile_normalize_hex_color($_POST['profile_name_glow_color'] ?? '#8b5cf6')
];
$profileNameStyleJson = json_encode($nameStyleConfig);
$showStats = profile_bool_from_post('profile_show_stats', true);
$showSocials = profile_bool_from_post('profile_show_socials', true);
$showLinks = profile_bool_from_post('profile_show_links', true);
$showProjects = profile_bool_from_post('profile_show_projects', true);
$showContents = profile_bool_from_post('profile_show_contents', true);
$showBlocks = profile_bool_from_post('profile_show_blocks', true);
$showBadges = profile_bool_from_post('profile_show_badges', true);
$showActivity = profile_bool_from_post('profile_show_activity', true);
$showDiscord = profile_bool_from_post('profile_show_discord', true);
$showCharacters = profile_bool_from_post('profile_show_characters', true);
$clickToEnter = profile_bool_from_post('profile_click_to_enter', false);
$enterText = profile_clean_text($_POST['profile_enter_text'] ?? '', 80);
$enterTextDb = $enterText !== '' ? $enterText : null;
$socialsStyle = profile_allowed_value((string)($_POST['profile_socials_style'] ?? 'cards'), ['cards', 'icons'], 'cards');
$showEmbeds = profile_bool_from_post('profile_show_embeds', true);
$badgesDisplay = profile_allowed_value((string)($_POST['profile_badges_display'] ?? 'both'), ['both', 'card_only', 'tab_only', 'none'], 'both');
$badgesPosition = profile_allowed_value((string)($_POST['profile_badges_position'] ?? 'below_bio'), ['below_bio', 'below_username', 'right_of_name'], 'below_bio');
$discordServerInvite = trim((string)($_POST['discord_server_invite'] ?? ''));

// Premium Features 3.X Parameters Extraction & Validation
$customAlias = isset($_POST['custom_alias']) ? trim((string)$_POST['custom_alias']) : '';
$customAliasDb = $customAlias !== '' ? $customAlias : null;
if ($customAliasDb !== null) {
    if (!preg_match('/^[a-zA-Z0-9_-]{3,30}$/', $customAliasDb)) {
        profile_json_response(['ok' => false, 'message' => 'L\'alias URL personalizzato non è valido.'], 422);
    }
    $blacklist = ['api', 'assets', 'audio', 'auth', 'config', 'css', 'data', 'en', 'img', 'includes', 'it', 'js', 'mc', 'user', 'vid', 'u', 'admin', 'logout', 'profile', 'bio', 'gaming', 'game', 'negozio', 'shop', 'privacy', 'tos', 'terms', 'about', 'chisiamo', 'merch', 'checkout', 'lootbox', 'shitpost', 'missions', 'index', '404', 'aura', 'discord', 'register', 'registrati', 'login', 'accedi', 'settings', 'impostazioni', 'dashboard', 'help', 'support', 'uwu', 'db', 'search'];
    if (in_array(strtolower($customAliasDb), $blacklist, true)) {
        profile_json_response(['ok' => false, 'message' => 'Questo alias è riservato.'], 422);
    }
    $stmt = $mysqli->prepare("SELECT id FROM utenti WHERE LOWER(username) = LOWER(?) AND id != ? LIMIT 1");
    $stmt->bind_param('si', $customAliasDb, $targetUserId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        profile_json_response(['ok' => false, 'message' => 'L\'alias coincide con lo username di un altro utente.'], 422);
    }
    $stmt->close();
    $stmt = $mysqli->prepare("SELECT id FROM utenti WHERE LOWER(custom_alias) = LOWER(?) AND id != ? LIMIT 1");
    $stmt->bind_param('si', $customAliasDb, $targetUserId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        profile_json_response(['ok' => false, 'message' => 'Alias già in uso da un altro utente.'], 409);
    }
    $stmt->close();
}

$tiltEnabled = profile_bool_from_post('tilt_enabled', true) ? 1 : 0;
$tiltMax = min(max((int)($_POST['tilt_max'] ?? 15), 0), 45);
$tiltGlare = min(max((float)($_POST['tilt_glare'] ?? 0.0), 0.0), 1.0);
$tiltZoom = min(max((float)($_POST['tilt_zoom'] ?? 1.05), 1.0), 1.3);
$tiltSpeed = min(max((int)($_POST['tilt_speed'] ?? 400), 100), 2000);

$tagsRaw = $_POST['profile_tags_json'] ?? '[]';
$tagsDecoded = json_decode($tagsRaw, true);
$tagsArray = [];
if (is_array($tagsDecoded)) {
    foreach (array_slice($tagsDecoded, 0, 10) as $tag) {
        $tagText = profile_clean_text($tag['text'] ?? '', 40);
        if ($tagText === '') continue;
        $tagsArray[] = [
            'text' => $tagText,
            'icon' => profile_clean_text($tag['icon'] ?? '', 40),
            'color' => profile_optional_hex_color($tag['color'] ?? ''),
            'gradient' => profile_optional_hex_color($tag['gradient'] ?? '')
        ];
    }
}
$profileTagsJsonDb = json_encode($tagsArray);

$profileTabTitle = profile_clean_text($_POST['profile_tab_title'] ?? '', 80);
$profileTabTitleDb = $profileTabTitle !== '' ? $profileTabTitle : null;
$profileTabAnimation = profile_allowed_value((string)($_POST['profile_tab_animation'] ?? 'static'), ['static', 'marquee', 'bounce', 'pulse'], 'static');
$profileTabAnimationSpeed = min(max((int)($_POST['profile_tab_animation_speed'] ?? 1000), 200), 5000);
$profileTabAnimationText = profile_clean_text($_POST['profile_tab_animation_text'] ?? '', 120);
$profileTabAnimationTextDb = $profileTabAnimationText !== '' ? $profileTabAnimationText : null;

$profileCornerStyle = profile_allowed_value((string)($_POST['profile_corner_style'] ?? 'circle'), ['circle', 'rounded', 'soft', 'square', 'custom'], 'circle');
$profileCornerStyleCustom = min(max((int)($_POST['profile_corner_style_custom'] ?? 8), 0), 100);
$profileBorderStyle = profile_allowed_value((string)($_POST['profile_border_style'] ?? 'thin'), ['none', 'thin', 'glow', 'gradient'], 'thin');

$oldInvite = $profile['discord_server_invite'] ?? '';
$discordServerCache = $profile['discord_server_cache'] ?? null;
$discordServerCacheTime = (int)($profile['discord_server_cache_time'] ?? 0);

if ($discordServerInvite !== $oldInvite) {
    if ($discordServerInvite !== '') {
        $inviteCode = trim($discordServerInvite);
        $urlParts = parse_url($inviteCode);
        if ($urlParts && isset($urlParts['path'])) {
            $path = trim($urlParts['path'], '/');
            if ($path !== '') {
                $parts = explode('/', $path);
                $inviteCode = end($parts);
            }
        }
        $widgetData = profile_fetch_discord_server_data($inviteCode);
        if ($widgetData) {
            $discordServerCache = json_encode($widgetData);
            $discordServerCacheTime = time();
        } else {
            $discordServerCache = null;
            $discordServerCacheTime = 0;
        }
    } else {
        $discordServerCache = null;
        $discordServerCacheTime = 0;
    }
}

$sectionsOrder = trim((string)($_POST['profile_sections_order'] ?? ''));
if ($sectionsOrder === '') {
    $sectionsOrder = 'links,embeds,stats,projects,blocks,contents,characters,badges,activity,discord_server';
}
$allowedSectionsList = ['links', 'embeds', 'stats', 'projects', 'blocks', 'contents', 'characters', 'badges', 'activity', 'discord_server'];
$sectionsArray = explode(',', $sectionsOrder);
$validSectionsArray = array_values(array_intersect($sectionsArray, $allowedSectionsList));
if (empty($validSectionsArray)) {
    $validSectionsArray = $allowedSectionsList;
}
$sectionsOrderDb = implode(',', $validSectionsArray);


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

$socialRows = profile_decode_rows('socials_json');
$linkRows = profile_decode_rows('links_json');
$projectRows = profile_decode_rows('projects_json');
$contentRows = profile_decode_rows('contents_json');
$blockRows = profile_decode_rows('blocks_json');
$badgeRows = profile_decode_rows('badges_json');
$embedRows = profile_decode_rows('embeds_json');

if (!$isPremium) {
    $socialRows = array_slice($socialRows, 0, 5);
    $linkRows = array_slice($linkRows, 0, 5);
    $projectRows = array_slice($projectRows, 0, 3);
    $contentRows = array_slice($contentRows, 0, 3);
    $blockRows = array_slice($blockRows, 0, 1);
    $embedRows = array_slice($embedRows, 0, 3);
} else {
    $socialRows = array_slice($socialRows, 0, 100);
    $linkRows = array_slice($linkRows, 0, 100);
    $projectRows = array_slice($projectRows, 0, 100);
    $contentRows = array_slice($contentRows, 0, 100);
    $blockRows = array_slice($blockRows, 0, 100);
    $embedRows = array_slice($embedRows, 0, 100);
}

$allowedPlatforms = ['tiktok', 'instagram', 'youtube', 'twitch', 'github', 'discord', 'telegram', 'x', 'twitter', 'spotify', 'soundcloud', 'steam', 'reddit', 'pinterest', 'snapchat', 'facebook', 'linkedin', 'paypal', 'patreon', 'kick', 'bluesky', 'threads', 'behance', 'dribbble', 'website', 'email', 'other'];
$allowedStatuses = ['active', 'paused', 'finished', 'idea'];
$allowedContentTypes = ['edit', 'video', 'game', 'post', 'other'];
$allowedBlockTypes = ['text', 'image', 'gif', 'video'];
if ($isPremium) {
    $allowedBlockTypes[] = 'markdown';
    $allowedBlockTypes[] = 'html';
}

try {
    $mysqli->begin_transaction();

    $stmt = $mysqli->prepare("
        UPDATE utenti
        SET username = ?, display_name = ?, bio = ?, accent_color = ?, profile_secondary_color = ?, profile_card_color = ?, profile_text_color = ?, profile_link_style = ?, profile_button_shape = ?, profile_theme = ?, profile_layout = ?, profile_visibility = ?, discord_id = ?, discord_use_avatar = ?, discord_use_display_name = ?, profile_status = ?,
            profile_music_url = ?, profile_music_title = ?, profile_music_artist = ?, profile_effect = ?, avatar_ring_style = ?, avatar_ring_color = ?,
            profile_show_stats = ?, profile_show_socials = ?, profile_show_links = ?, profile_show_projects = ?, profile_show_contents = ?, profile_show_blocks = ?, profile_show_badges = ?, profile_show_activity = ?, profile_show_discord = ?, profile_show_audio_player = ?, avatar_ring_enabled = ?, profile_show_characters = ?,
            profile_enter_text = ?, profile_click_to_enter = ?, profile_socials_style = ?, profile_show_embeds = ?, profile_sections_order = ?, profile_badges_display = ?,
            profile_badges_position = ?, discord_server_invite = ?, discord_server_cache = ?, discord_server_cache_time = ?,
            profile_font = ?, profile_border_radius = ?, profile_card_opacity = ?, profile_card_blur = ?, profile_border_opacity = ?, profile_border_color = ?, profile_border_width = ?,
            profile_name_style = ?,
            profile_ui_shape = ?, profile_avatar_shape = ?, profile_social_size = ?, profile_icon_spacing = ?, profile_badge_size = ?, profile_button_size = ?, profile_avatar_border = ?,
            custom_alias = ?, tilt_enabled = ?, tilt_max = ?, tilt_glare = ?, tilt_zoom = ?, tilt_speed = ?, profile_tags_json = ?, profile_tab_title = ?, profile_tab_animation = ?, profile_tab_animation_speed = ?, profile_tab_animation_text = ?, profile_corner_style = ?, profile_corner_style_custom = ?, profile_border_style = ?,
            profile_updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param(
        'sssssssssssssiisssssssiiiiiiiiiiiisisisssssisiiiisisssiiiiisiiddisssissisi',
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
        $showBlocks,
        $showBadges,
        $showActivity,
        $showDiscord,
        $showAudioPlayer,
        $avatarRingEnabled,
        $showCharacters,
        $enterTextDb,
        $clickToEnter,
        $socialsStyle,
        $showEmbeds,
        $sectionsOrderDb,
        $badgesDisplay,
        $badgesPosition,
        $discordServerInvite,
        $discordServerCache,
        $discordServerCacheTime,
        $font,
        $borderRadius,
        $cardOpacity,
        $cardBlur,
        $borderOpacity,
        $borderColorDb,
        $borderWidth,
        $profileNameStyleJson,
        $uiShape,
        $avatarShape,
        $socialSize,
        $iconSpacing,
        $badgeSize,
        $buttonSize,
        $avatarBorder,
        $customAliasDb,
        $tiltEnabled,
        $tiltMax,
        $tiltGlare,
        $tiltZoom,
        $tiltSpeed,
        $profileTagsJsonDb,
        $profileTabTitleDb,
        $profileTabAnimation,
        $profileTabAnimationSpeed,
        $profileTabAnimationTextDb,
        $profileCornerStyle,
        $profileCornerStyleCustom,
        $profileBorderStyle,
        $targetUserId
    );
    if (!$stmt->execute()) throw new RuntimeException('Error updating profile.');
    $stmt->close();

    if ($isPremium) {
        $layoutSnap = profile_bool_from_post('profile_layout_snap', false) ? 1 : 0;
        $cursorEffect = profile_allowed_value((string)($_POST['profile_cursor_effect'] ?? 'none'), ['none', 'follower', 'trail'], 'none');
        $cursorCustomUrl = isset($_POST['profile_cursor_custom_url']) ? trim((string)$_POST['profile_cursor_custom_url']) : '';
        if ($cursorCustomUrl !== '' && !profile_is_safe_url($cursorCustomUrl, false)) {
            $cursorCustomUrl = '';
        }
        $cursorCustomUrlDb = $cursorCustomUrl !== '' ? $cursorCustomUrl : null;
        $bgGrain = profile_bool_from_post('profile_bg_grain', false) ? 1 : 0;
        $musicTheme = profile_allowed_value((string)($_POST['profile_music_theme'] ?? 'default'), ['default', 'retro', 'cyberpunk', 'synthwave'], 'default');
        
        $sectionsConfig = isset($_POST['profile_sections_config']) ? trim((string)$_POST['profile_sections_config']) : '';
        if ($sectionsConfig !== '') {
            $decoded = json_decode($sectionsConfig, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $sectionsConfig = null;
            } else {
                $sanitized = [];
                foreach ($decoded as $key => $conf) {
                    $sanitized[$key] = [
                        'hidden' => !empty($conf['hidden']) ? 1 : 0,
                        'title' => isset($conf['title']) ? profile_clean_text($conf['title'], 80) : '',
                        'icon' => isset($conf['icon']) ? profile_clean_text($conf['icon'], 50) : '',
                    ];
                }
                $sectionsConfig = json_encode($sanitized);
            }
        } else {
            $sectionsConfig = null;
        }
    } else {
        $layoutSnap = 0;
        $cursorEffect = 'none';
        $cursorCustomUrlDb = null;
        $bgGrain = 0;
        $musicTheme = 'default';
        $sectionsConfig = null;
    }

    $stmtPremium = $mysqli->prepare("
        UPDATE utenti
        SET profile_layout_snap = ?, profile_cursor_effect = ?, profile_cursor_custom_url = ?, profile_bg_grain = ?, profile_music_theme = ?, profile_sections_config = ?
        WHERE id = ?
    ");
    $stmtPremium->bind_param('ississi', $layoutSnap, $cursorEffect, $cursorCustomUrlDb, $bgGrain, $musicTheme, $sectionsConfig, $targetUserId);
    if (!$stmtPremium->execute()) throw new RuntimeException('Error updating premium settings.');
    $stmtPremium->close();

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

    $insertSocial = $mysqli->prepare("INSERT INTO utenti_social (utente_id, platform, label, display_username, url, sort_order, is_visible, icon) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($socialRows as $i => $row) {
        $platform = strtolower(profile_clean_text($row['platform'] ?? 'other', 32));
        $platform = in_array($platform, $allowedPlatforms, true) ? $platform : 'other';
        $label = profile_clean_text($row['label'] ?? $platform, 40);
        $displayUsername = profile_clean_text($row['display_username'] ?? '', 60);
        $displayUsernameDb = $displayUsername !== '' ? $displayUsername : null;
        $url = trim((string)($row['url'] ?? ''));
        $visible = !empty($row['is_visible']) ? 1 : 0;
        $icon = ($isPremium && isset($row['icon'])) ? profile_clean_text($row['icon'], 255) : null;
        if ($url === '') continue;
        if (!profile_is_safe_url($url, true)) throw new RuntimeException('Invalid social URL: ' . $label);
        $insertSocial->bind_param('issssiis', $targetUserId, $platform, $label, $displayUsernameDb, $url, $i, $visible, $icon);
        if (!$insertSocial->execute()) throw new RuntimeException('Error saving social.');
    }
    $insertSocial->close();

    $stmt = $mysqli->prepare("DELETE FROM utenti_links WHERE utente_id = ?");
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $stmt->close();

    $insertLink = $mysqli->prepare("INSERT INTO utenti_links (utente_id, title, description, url, icon, button_style, is_featured, is_visible, sort_order, card_tag_text, card_tag_bg, card_tag_color) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($linkRows as $i => $row) {
        $title = profile_clean_text($row['title'] ?? '', 60);
        $description = profile_clean_text($row['description'] ?? '', 160);
        $url = trim((string)($row['url'] ?? ''));
        $icon = profile_clean_text($row['icon'] ?? 'fa-solid fa-link', 255);
        if (!$isPremium && (preg_match('/^https?:\/\//i', $icon) || str_starts_with($icon, '/uploads/') || str_contains($icon, '.'))) {
            $icon = 'fa-solid fa-link';
        }
        $buttonStyle = profile_allowed_value((string)($row['button_style'] ?? 'card'), ['card', 'compact', 'icon'], 'card');
        $featured = !empty($row['is_featured']) ? 1 : 0;
        $visible = !empty($row['is_visible']) ? 1 : 0;
        if ($title === '' && $url === '') continue;
        if ($title === '') throw new RuntimeException('A link must have a title.');
        if (!profile_is_safe_url($url, true)) throw new RuntimeException('Invalid link URL: ' . $title);

        $tagText = null;
        $tagBg = null;
        $tagColor = null;
        if ($isPremium) {
            $tagText = profile_clean_text($row['card_tag_text'] ?? '', 50);
            if ($tagText === '') {
                $tagText = null;
            } else {
                $tagBg = profile_optional_hex_color($row['card_tag_bg'] ?? '');
                $tagColor = profile_optional_hex_color($row['card_tag_color'] ?? '');
            }
        }

        $insertLink->bind_param('isssssiiisss', $targetUserId, $title, $description, $url, $icon, $buttonStyle, $featured, $visible, $i, $tagText, $tagBg, $tagColor);
        if (!$insertLink->execute()) throw new RuntimeException('Error saving link.');
    }
    $insertLink->close();

    $stmt = $mysqli->prepare("DELETE FROM utenti_projects WHERE utente_id = ?");
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $stmt->close();

    $insertProject = $mysqli->prepare("INSERT INTO utenti_projects (utente_id, title, description, url, image_url, tech_stack, status, is_featured, is_visible, sort_order, card_tag_text, card_tag_bg, card_tag_color) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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

        $tagText = null;
        $tagBg = null;
        $tagColor = null;
        if ($isPremium) {
            $tagText = profile_clean_text($row['card_tag_text'] ?? '', 50);
            if ($tagText === '') {
                $tagText = null;
            } else {
                $tagBg = profile_optional_hex_color($row['card_tag_bg'] ?? '');
                $tagColor = profile_optional_hex_color($row['card_tag_color'] ?? '');
            }
        }

        $insertProject->bind_param('issssssiiisss', $targetUserId, $title, $description, $url, $imageUrl, $techStack, $status, $featured, $visible, $i, $tagText, $tagBg, $tagColor);
        if (!$insertProject->execute()) throw new RuntimeException('Error saving project.');
    }
    $insertProject->close();

    $stmt = $mysqli->prepare("DELETE FROM utenti_contents WHERE utente_id = ?");
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $stmt->close();

    $insertContent = $mysqli->prepare("INSERT INTO utenti_contents (utente_id, content_type, title, description, url, thumbnail_url, is_featured, is_visible, sort_order, card_tag_text, card_tag_bg, card_tag_color) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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

        $tagText = null;
        $tagBg = null;
        $tagColor = null;
        if ($isPremium) {
            $tagText = profile_clean_text($row['card_tag_text'] ?? '', 50);
            if ($tagText === '') {
                $tagText = null;
            } else {
                $tagBg = profile_optional_hex_color($row['card_tag_bg'] ?? '');
                $tagColor = profile_optional_hex_color($row['card_tag_color'] ?? '');
            }
        }

        $insertContent->bind_param('isssssiiisss', $targetUserId, $type, $title, $description, $url, $thumb, $featured, $visible, $i, $tagText, $tagBg, $tagColor);
        if (!$insertContent->execute()) throw new RuntimeException('Error saving content.');
    }
    $insertContent->close();

    $stmt = $mysqli->prepare("DELETE FROM utenti_profile_blocks WHERE utente_id = ?");
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $stmt->close();

    $insertBlock = $mysqli->prepare("INSERT INTO utenti_profile_blocks (utente_id, block_type, title, body, media_url, media_type, is_featured, is_visible, sort_order, card_tag_text, card_tag_bg, card_tag_color, no_card_style) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($blockRows as $i => $row) {
        $type = profile_allowed_value((string)($row['block_type'] ?? 'text'), $allowedBlockTypes, 'text');
        $title = profile_clean_text($row['title'] ?? '', 80);
        $body = trim((string)($row['body'] ?? ''));
        $maxLen = ($isPremium && in_array($type, ['markdown', 'html'], true)) ? 5000 : 700;
        $body = mb_substr($body, 0, $maxLen, 'UTF-8');
        $mediaUrl = trim((string)($row['media_url'] ?? ''));
        $mediaType = profile_media_type_from_url($mediaUrl, $type === 'gif' ? 'gif' : ($type === 'video' ? 'video' : 'image'));
        $featured = !empty($row['is_featured']) ? 1 : 0;
        $visible = !empty($row['is_visible']) ? 1 : 0;

        if ($title === '' && $body === '' && $mediaUrl === '') continue;
        if (!in_array($type, ['text', 'markdown', 'html'], true) && !profile_is_safe_url($mediaUrl, true)) {
            throw new RuntimeException('Invalid media URL in custom block.');
        }
        if (in_array($type, ['text', 'markdown', 'html'], true)) {
            $mediaUrl = null;
            $mediaType = 'text';
        }

        $tagText = null;
        $tagBg = null;
        $tagColor = null;
        if ($isPremium) {
            $tagText = profile_clean_text($row['card_tag_text'] ?? '', 50);
            if ($tagText === '') {
                $tagText = null;
            } else {
                $tagBg = profile_optional_hex_color($row['card_tag_bg'] ?? '');
                $tagColor = profile_optional_hex_color($row['card_tag_color'] ?? '');
            }
        }
        $noCardStyle = (!empty($row['no_card_style']) && $isPremium) ? 1 : 0;

        $insertBlock->bind_param('isssssiiisssi', $targetUserId, $type, $title, $body, $mediaUrl, $mediaType, $featured, $visible, $i, $tagText, $tagBg, $tagColor, $noCardStyle);
        if (!$insertBlock->execute()) throw new RuntimeException('Error saving custom blocks.');
    }
    $insertBlock->close();

    $stmt = $mysqli->prepare("DELETE FROM utenti_embeds WHERE utente_id = ?");
    $stmt->bind_param('i', $targetUserId);
    if (!$stmt->execute()) throw new RuntimeException('Error cleaning embeds.');
    $stmt->close();

    $insertEmbed = $mysqli->prepare("INSERT INTO utenti_embeds (utente_id, type, title, url, sort_order, is_visible) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($embedRows as $i => $row) {
        $type = profile_allowed_value((string)($row['type'] ?? 'spotify'), ['spotify', 'youtube', 'custom'], 'spotify');
        $title = profile_clean_text($row['title'] ?? '', 100);
        $titleDb = $title !== '' ? $title : null;
        $url = trim((string)($row['url'] ?? ''));
        $visible = !empty($row['is_visible']) ? 1 : 0;
        if ($url === '') continue;
        if (!profile_is_safe_url($url, false)) throw new RuntimeException('Invalid embed URL.');
        
        if ($type === 'spotify') {
            $embedUrl = profile_get_spotify_embed_url($url);
            if ($embedUrl) $url = $embedUrl;
        } elseif ($type === 'youtube') {
            $embedUrl = profile_get_youtube_embed_url($url);
            if ($embedUrl) $url = $embedUrl;
        }

        $insertEmbed->bind_param('isssii', $targetUserId, $type, $titleDb, $url, $i, $visible);
        if (!$insertEmbed->execute()) throw new RuntimeException('Error saving embed.');
    }
    $insertEmbed->close();

    if ($musicUrlDb || !empty($musicUpload['has_file'])) {
        profile_record_activity($mysqli, $targetUserId, 'music', 'Updated profile song', $musicUrlDb ?: null);
    }

    // Clear existing achievements selections
    $stmt = $mysqli->prepare("DELETE FROM utenti_profile_badges WHERE utente_id = ?");
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $stmt->close();

    // Clear visibility and reset order of custom badges for this user
    $stmt = $mysqli->prepare("UPDATE user_custom_badges SET is_visible = 0, sort_order = 999 WHERE utente_id = ?");
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $stmt->close();

    $insertAchievement = $mysqli->prepare("
        INSERT INTO utenti_profile_badges (utente_id, achievement_id, sort_order, is_visible)
        SELECT ?, ua.achievement_id, ?, 1
        FROM utenti_achievement ua
        WHERE ua.utente_id = ? AND ua.achievement_id = ?
        LIMIT 1
    ");

    $updateCustom = $mysqli->prepare("
        UPDATE user_custom_badges
        SET is_visible = 1, sort_order = ?
        WHERE utente_id = ? AND badge_id = ?
    ");

    foreach ($badgeRows as $i => $badgeCompoundId) {
        $badgeCompoundId = trim((string)$badgeCompoundId);
        if ($badgeCompoundId === '') continue;

        if (strpos($badgeCompoundId, 'custom_') === 0) {
            $badgeId = (int)substr($badgeCompoundId, 7);
            if ($badgeId > 0 && $updateCustom) {
                $updateCustom->bind_param('iii', $i, $targetUserId, $badgeId);
                if (!$updateCustom->execute()) throw new RuntimeException('Error saving custom badge.');
            }
        } else {
            // Either starts with "achievement_" or is a plain ID for backward compatibility
            $badgeId = $badgeCompoundId;
            if (strpos($badgeCompoundId, 'achievement_') === 0) {
                $badgeId = substr($badgeCompoundId, 12);
            }
            $badgeId = (int)$badgeId;
            if ($badgeId > 0 && $insertAchievement) {
                $insertAchievement->bind_param('iiii', $targetUserId, $i, $targetUserId, $badgeId);
                if (!$insertAchievement->execute()) throw new RuntimeException('Error saving achievement badge.');
            }
        }
    }
    if ($insertAchievement) $insertAchievement->close();
    if ($updateCustom) $updateCustom->close();


    $characterRows = array_slice(profile_decode_rows('characters_json'), 0, 12);

    $stmt = $mysqli->prepare("DELETE FROM utenti_profile_characters WHERE utente_id = ?");
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $stmt->close();

    $insertCharacter = $mysqli->prepare("
    INSERT INTO utenti_profile_characters (utente_id, personaggio_id, sort_order, is_visible)
    SELECT ?, up.personaggio_id, ?, 1
    FROM utenti_personaggi up
    WHERE up.utente_id = ? AND up.personaggio_id = ?
    LIMIT 1
");
    foreach ($characterRows as $i => $charId) {
        $charId = (int)$charId;
        if ($charId <= 0) continue;
        $insertCharacter->bind_param('iiii', $targetUserId, $i, $targetUserId, $charId);
        if (!$insertCharacter->execute()) throw new RuntimeException('Errore salvataggio personaggio.');
    }
    $insertCharacter->close();

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
    }

    // Clear draft on successful save
    if (isset($_SESSION['profile_draft'][$targetUserId])) {
        unset($_SESSION['profile_draft'][$targetUserId]);
    }

    profile_json_response([
        'ok' => true,
        'message' => 'Profile saved.',
        'profile_url' => '/u/' . rawurlencode(strtolower($username)),
    ]);
} catch (Throwable $e) {
    $mysqli->rollback();
    profile_json_response(['ok' => false, 'message' => $e->getMessage()], 422);
}
