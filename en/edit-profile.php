<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = 'You must be logged in to edit your profile';
    header('Location: accedi');
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$targetUserId = isset($_GET['user_id']) && profile_is_staff() ? (int)$_GET['user_id'] : $currentUserId;

if (!profile_can_edit($targetUserId)) {
    http_response_code(403);
    exit('Access denied.');
}

$profile = profile_get_edit_profile($mysqli, $targetUserId);
if (!$profile) {
    http_response_code(404);
    exit('Profile not found.');
}
$isPremium = (int)($profile['is_premium'] ?? 0) === 1;
$nameStyle = [];
if ($profile && !empty($profile['profile_name_style'])) {
    $nameStyle = json_decode($profile['profile_name_style'], true);
}
if (!is_array($nameStyle)) {
    $nameStyle = [];
}
$nameType = $nameStyle['type'] ?? 'default';
$nameAnim = $nameStyle['animation'] ?? 'none';
$nameSolidColor = $nameStyle['solid_color'] ?? '#ffffff';
$nameGradColor1 = $nameStyle['grad_color1'] ?? '#ffffff';
$nameGradColor2 = $nameStyle['grad_color2'] ?? '#8b5cf6';
$nameGradAngle = $nameStyle['grad_angle'] ?? 90;
$nameGlowColor = $nameStyle['glow_color'] ?? '#8b5cf6';

$socials = profile_list_socials($mysqli, $targetUserId, false);
$links = profile_list_links($mysqli, $targetUserId, false);
$projects = profile_list_projects($mysqli, $targetUserId, false);
$contents = profile_list_contents($mysqli, $targetUserId, false);
$blocks = function_exists('profile_list_blocks') ? profile_list_blocks($mysqli, $targetUserId, false) : [];
$embeds = function_exists('profile_list_embeds') ? profile_list_embeds($mysqli, $targetUserId, false) : [];
$availableBadges = profile_list_all_user_badges($mysqli, $targetUserId);
$inventoryCharacters = profile_list_inventory_characters($mysqli, $targetUserId);
$csrf = profile_csrf_token();
$profileFlashSuccess = $_SESSION['profile_flash_success'] ?? '';
$profileFlashError = $_SESSION['profile_flash_error'] ?? '';
unset($_SESSION['profile_flash_success'], $_SESSION['profile_flash_error']);
$accent = profile_normalize_hex_color($profile['accent_color'] ?? '#0f5bff');
$secColorRaw = trim((string)($profile['profile_secondary_color'] ?? ''));
$secondaryColor = (preg_match('/^#[0-9a-fA-F]{6}$/', $secColorRaw)) ? strtolower($secColorRaw) : $accent;

$accentHex = ltrim($accent, '#');
if (strlen($accentHex) == 3) {
    $r = hexdec(substr($accentHex, 0, 1) . substr($accentHex, 0, 1));
    $g = hexdec(substr($accentHex, 1, 1) . substr($accentHex, 1, 1));
    $b = hexdec(substr($accentHex, 2, 1) . substr($accentHex, 2, 1));
} else {
    $r = hexdec(substr($accentHex, 0, 2));
    $g = hexdec(substr($accentHex, 2, 2));
    $b = hexdec(substr($accentHex, 4, 2));
}
$accentRgbComma = "$r, $g, $b";
$cardColor = profile_optional_hex_color($profile['profile_card_color'] ?? '') ?: '';
$textColor = profile_optional_hex_color($profile['profile_text_color'] ?? '') ?: '';
$linkStyle = profile_allowed_value((string)($profile['profile_link_style'] ?? 'glass'), ['glass', 'solid', 'outline', 'neon'], 'glass');
$buttonShape = profile_allowed_value((string)($profile['profile_button_shape'] ?? 'pill'), ['pill', 'rounded', 'sharp'], 'pill');
$theme = profile_allowed_value((string)($profile['profile_theme'] ?? 'dark'), ['dark', 'light', 'auto'], 'dark');
if ($theme === 'auto') $theme = 'dark';
$avatarShape = profile_allowed_value((string)($profile['profile_avatar_shape'] ?? 'circle'), ['circle', 'squircle', 'square', 'hexagon', 'octagon', 'badge'], 'circle');
$avatarBorder = (int)($profile['profile_avatar_border'] ?? 1);
$cardColorCss = $cardColor ?: ($theme === 'light' ? '#ffffff' : '#080c18');
$textColorCss = $textColor ?: ($theme === 'light' ? '#111827' : '#f7f8ff');
$displayName = profile_display_name($profile);
$discordConnected = !empty($profile['discord_id']) && !empty($profile['discord_username']);
$discordAvatarUrl = $discordConnected ? profile_discord_avatar_url((string)$profile['discord_id'], $profile['discord_avatar'] ?? null, 128) : null;
$discordDisplayName = trim((string)($profile['discord_global_name'] ?? '')) ?: trim((string)($profile['discord_username'] ?? ''));
$connectDiscordUrl = '../auth/discord_connect.php' . (profile_is_staff() && $targetUserId !== $currentUserId ? '?target_user_id=' . (int)$targetUserId : '');
$stamp = !empty($profile['profile_updated_at']) ? (int)strtotime((string)$profile['profile_updated_at']) : time();
$backgroundUrl = !empty($profile['profile_banner_type']) ? '../includes/get_profile_banner.php?id=' . (int)$profile['id'] . '&t=' . $stamp : '../vid/nga.mp4';
$backgroundType = !empty($profile['profile_banner_type']) ? (string)$profile['profile_banner_type'] : 'video/mp4';
$backgroundIsVideo = str_starts_with($backgroundType, 'video/');
$backgroundIsImage = str_starts_with($backgroundType, 'image/');

function profile_json_script(string $id, array $data): void
{
    echo '<script type="application/json" id="' . profile_h($id) . '">';
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    echo '</script>';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include __DIR__ . '/../includes/head-import.php'; ?>
    <title>Cripsum™ - Edit profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link class="profile-css-file" rel="stylesheet" href="/assets/css/profile.css?v=5.9.14">
    <link rel="stylesheet" href="/assets/css/editor-premium.css?v=5.9.14">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins&family=Inter:wght@300..900&family=Roboto:wght@300..900&family=Outfit:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Space+Grotesk:wght@300..700&family=Syne:wght@400..800&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Fira+Code:wght@300..700&family=PT+Mono&family=Cinzel:wght@400..900&family=Rubik:ital,wght@0,300..900;1,300..900&family=Bebas+Neue&family=Press+Start+2P&family=Bungee&family=Permanent+Marker&family=Creepster&family=Shojumaru&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        window.isPremiumUser = <?php echo (int)($profile['is_premium'] ?? 0) === 'true' || (int)($profile['is_premium'] ?? 0) === 1 ? 'true' : 'false'; ?>;
    </script>
    <script src="/assets/js/profile.js?v=5.9.14" defer></script>
    <script src="/assets/js/edit-profile-en.js?v=5.9.14" defer></script>
</head>

<body class="bio-v2-body profile-editor-shell" data-theme="<?php echo profile_h($theme); ?>" data-accent="<?php echo profile_h($accent); ?>" data-profile-link-style="<?php echo profile_h($linkStyle); ?>" data-profile-button-shape="<?php echo profile_h($buttonShape); ?>" data-profile-effect="<?php echo profile_h($profile['profile_effect'] ?? 'none'); ?>" data-profile-url="https://cripsum.com/u/<?php echo rawurlencode(strtolower($profile['username'])); ?>" data-avatar-shape="<?php echo profile_h($avatarShape); ?>" data-avatar-border="<?php echo $avatarBorder; ?>" style="--accent: <?php echo profile_h($accent); ?>; --accent-rgb: <?php echo $accentRgbComma; ?>; --profile-ring: <?php echo profile_h(profile_normalize_hex_color($profile['avatar_ring_color'] ?: $accent)); ?>; --accent-2: <?php echo profile_h($secondaryColor); ?>; --profile-card-color: <?php echo profile_h($cardColorCss); ?>; --profile-text-color: <?php echo profile_h($textColorCss); ?>;">
    <?php if ((int)($profile['is_premium'] ?? 0) !== 1): ?>
        <!-- Onboarding Plan Selection Overlay -->
        <div id="onboardingPlanOverlay" class="onboarding-plan-overlay">
            <div class="onboarding-plan-card">
                <h3 class="onboarding-plan-title">Choose your Cripsum™ Plan</h3>
                <p class="onboarding-plan-subtitle">Unlock the maximum level of customization for your profile.</p>

                <div class="plan-options-grid">
                    <!-- Free Plan -->
                    <div class="plan-option-card">
                        <div>
                            <span class="plan-badge">Base</span>
                            <div class="plan-price">Free <span>/ forever</span></div>
                            <ul class="plan-features">
                                <li><i class="fa-solid fa-check"></i>Up to 5 links/socials</li>
                                <li><i class="fa-solid fa-check"></i>1 Custom Block</li>
                                <li><i class="fa-solid fa-check"></i>Basic effects and fonts</li>
                                <li><i class="fa-solid fa-xmark"></i>No tags/badges on cards</li>
                                <li><i class="fa-solid fa-xmark"></i>No custom cursors</li>
                                <li><i class="fa-solid fa-xmark"></i>No full screen layout</li>
                            </ul>
                        </div>
                        <button type="button" class="plan-select-btn" id="selectFreeBtn">Continue Free</button>
                    </div>

                    <!-- Premium Plan -->
                    <div class="plan-option-card is-premium">
                        <div>
                            <span class="plan-badge">Premium</span>
                            <div class="plan-price">€2.99 <span>/ one-time</span></div>
                            <ul class="plan-features">
                                <li><i class="fa-solid fa-check"></i>Unlimited links and blocks</li>
                                <li><i class="fa-solid fa-check"></i>Direct media uploads up to 25MB (instead of 5MB)</li>
                                <li><i class="fa-solid fa-check"></i>Background upload up to 50MB (instead of 12MB)</li>
                                <li><i class="fa-solid fa-check"></i>Avatar (PFP) upload up to 10MB (instead of 2MB)</li>
                                <li><i class="fa-solid fa-check"></i>Hide info/metadata (Discord, last access, etc.) under the profile</li>
                                <li><i class="fa-solid fa-check"></i>Custom card tags & colors</li>
                                <li><i class="fa-solid fa-check"></i>Custom cursors & trails</li>
                                <li><i class="fa-solid fa-check"></i>Premium full screen layout</li>
                                <li><i class="fa-solid fa-check"></i>Theme presets & Preset Saving</li>
                                <li><i class="fa-solid fa-check"></i>Markdown & raw HTML custom blocks</li>
                                <li><i class="fa-solid fa-check"></i>Custom icons & uploads everywhere</li>
                                <li><i class="fa-solid fa-check"></i>Customizable section headings</li>
                                <li><i class="fa-solid fa-check"></i>Premium effects and fonts</li>
                                <li><i class="fa-solid fa-check"></i>20,000 Godos included instantly</li>
                                <li><i class="fa-solid fa-check"></i>Many other perks inside the site...</li>
                            </ul>
                        </div>
                        <a href="/en/checkout-premium.php" class="plan-select-btn">Upgrade to Premium</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php
    $isPublicProfilePage = false;
    if (file_exists(__DIR__ . '/../includes/navbar-bio.php')) {
        include __DIR__ . '/../includes/navbar-bio.php';
    } else {
        include __DIR__ . '/../includes/navbar.php';
    }
    if (file_exists(__DIR__ . '/../includes/impostazioni.php')) include __DIR__ . '/../includes/impostazioni.php';
    ?>

    <?php if ($discordConnected): ?>
        <form id="disconnectDiscordForm" method="post" action="../auth/discord_disconnect.php" class="profile-hidden-form">
            <input type="hidden" name="csrf_token" value="<?php echo profile_h($csrf); ?>">
            <input type="hidden" name="target_user_id" value="<?php echo (int)$targetUserId; ?>">
        </form>
    <?php endif; ?>

    <div class="bio-background" aria-hidden="true">
        <?php if ($backgroundIsVideo): ?>
            <video class="bio-background__media" autoplay muted loop playsinline poster="">
                <source src="<?php echo profile_h($backgroundUrl); ?>" type="<?php echo profile_h($backgroundType); ?>">
            </video>
        <?php elseif ($backgroundIsImage): ?>
            <img class="bio-background__media" src="<?php echo profile_h($backgroundUrl); ?>" alt="" loading="eager">
        <?php else: ?>
            <video class="bio-background__media" autoplay muted loop playsinline poster="">
                <source src="/vid/nga.mp4" type="video/mp4">
            </video>
        <?php endif; ?>
        <div class="bio-background__overlay"></div>
        <div class="bio-orb bio-orb--one"></div>
        <div class="bio-orb bio-orb--two"></div>
    </div>
    <div class="profile-effects-layer" aria-hidden="true"></div>
    <main class="builder-shell-layout">
        <button type="button" class="btn-floating-preview" id="floatingPreviewBtn" aria-label="Preview">
            <i class="fa-solid fa-eye"></i>
        </button>

        <form id="profileEditForm" class="builder-grid-container" method="post" enctype="multipart/form-data" action="../api/update_profile.php">
            <div class="editor-sidebar">
                <div class="editor-sidebar-header">
                    <div class="editor-sidebar-brand">
                        <div class="editor-brand-title">
                            <h2>Profile Editor</h2>
                        </div>
                        <div class="editor-header-actions">
                            <button type="button" class="editor-btn editor-btn-icon" id="undoBtn" disabled title="Undo (Ctrl+Z)"><i class="fa-solid fa-arrow-rotate-left"></i></button>
                            <button type="button" class="editor-btn editor-btn-icon" id="redoBtn" disabled title="Redo (Ctrl+Y)"><i class="fa-solid fa-arrow-rotate-right"></i></button>
                            <?php if ((int)($profile['is_premium'] ?? 0) !== 1): ?>
                                <button type="button" class="editor-btn editor-btn-premium" id="headerPremiumBtn"><i class="fa-solid fa-crown"></i> Premium</button>
                            <?php endif; ?>
                            <button type="submit" name="salva" class="editor-btn editor-btn-primary" id="saveBtn"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                        </div>
                    </div>

                    <div class="editor-controls-row">
                        <div class="editor-search-wrapper">
                            <i class="fa-solid fa-search editor-search-icon"></i>
                            <input type="text" class="editor-search-input" id="editorSearch" placeholder="Search settings (e.g. avatar, colors...)...">
                            <button type="button" class="editor-search-clear" id="editorSearchClear" style="display: none;"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    </div>

                    <!-- Editor Tabs Navigation -->
                    <div class="editor-tabs-nav">
                        <button type="button" class="editor-tab-btn is-active" data-tab="profile"><i class="fa-solid fa-user"></i> Content</button>
                        <button type="button" class="editor-tab-btn" data-tab="design"><i class="fa-solid fa-palette"></i> Design</button>
                        <button type="button" class="editor-tab-btn" data-tab="advanced"><i class="fa-solid fa-sliders"></i> Advanced</button>
                    </div>
                </div>

                <div class="editor-sidebar-scroll">
                    <?php if ($profileFlashSuccess || $profileFlashError): ?>
                        <div class="bio-card profile-flash <?php echo $profileFlashError ? 'is-error' : 'is-success'; ?>" style="margin-bottom: 1rem; display: flex !important;">
                            <i class="<?php echo $profileFlashError ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-check'; ?>"></i>
                            <span><?php echo profile_h($profileFlashError ?: $profileFlashSuccess); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="profile-edit-section editor-card" data-edit-section="identity" data-editor-category="profile">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fa-solid fa-id-card"></i></span>
                                <div class="editor-card-text">
                                    <h3>Identity</h3>
                                    <p>Display name, bio, avatar, music and tags</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Active</span>
                                <span class="editor-card-chevron"><i class="fa-solid fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="profile-field-grid two">
                                <label class="profile-field"><span>Display name</span><input type="text" name="display_name" id="displayNameInput" maxlength="40" value="<?php echo profile_h($profile['display_name'] ?? ''); ?>" placeholder="E.g. Godo"></label>
                                <label class="profile-field"><span>Username</span><input type="text" name="username" id="usernameInput" maxlength="20" required value="<?php echo profile_h($profile['username']); ?>" placeholder="username"><small>3-20 characters. Letters, numbers and underscore.</small></label>
                            </div>

                            <label class="profile-field"><span>Custom URL Alias (cripsum.com/youralias)</span>
                                <div style="position: relative;">
                                    <input type="text" name="custom_alias" id="customAliasInput" maxlength="30" value="<?php echo profile_h($profile['custom_alias'] ?? ''); ?>" placeholder="customalias" style="padding-right: 40px;">
                                    <span id="aliasValidationIcon" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 1.1rem; pointer-events: none;"></span>
                                </div>
                                <small id="aliasValidationMessage" style="transition: color 0.2s;">Leave empty to disable. Allows accessing your profile via cripsum.com/youralias</small>
                            </label>

                            <label class="profile-field"><span>Bio</span><textarea name="bio" id="bioInput" maxlength="280" rows="5" placeholder="Write something about you..."><?php echo profile_h($profile['bio'] ?? ''); ?></textarea><small><span id="bioCounter">0</span>/280</small></label>

                            <label class="profile-field"><span>Short status</span><input type="text" name="profile_status" id="statusInput" maxlength="60" value="<?php echo profile_h($profile['profile_status'] ?? ''); ?>" placeholder="editing, gaming, busy..."><small>Appears near your name when you are offline.</small></label>

                            <div class="profile-field-grid two">
                                <label class="profile-field"><span>Avatar</span><input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp,image/gif"><small>Max <?php echo $isPremium ? '10MB' : '2MB'; ?>. JPG, PNG, WEBP or GIF.</small></label>
                                <label class="profile-field"><span>Profile banner</span><input type="file" name="banner" id="bannerInput" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm"><small>Max <?php echo $isPremium ? '50MB' : '12MB'; ?>. Photo, GIF or video. Changes the page background.</small></label>
                            </div>

                            <label class="profile-field"><span>Profile privacy</span><select name="profile_visibility" id="visibilityInput"><?php foreach (['public' => 'Public', 'logged_in' => 'Logged in users only', 'friends' => 'Friends only', 'private' => 'Private'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_visibility'] ?? 'public') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>



                            <div class="bio-section-heading profile-mt">
                                <div><span><i class="fa-solid fa-music"></i> Profile audio</span>
                                    <p>Upload an MP3 or use a public audio URL.</p>
                                </div>
                            </div>
                            <div class="profile-field-grid two">
                                <label class="profile-field"><span>Upload MP3</span><input type="file" name="profile_music_file" id="musicFileInput" accept="audio/mpeg,audio/mp3,.mp3"><small>Max 12MB. If you upload an MP3, it overrides the URL.</small></label>
                                <label class="profile-field"><span>Song URL</span><input type="url" name="profile_music_url" id="musicUrlInput" maxlength="255" value="<?php echo profile_h($profile['profile_music_url'] ?? ''); ?>" placeholder="https://.../audio.mp3"><small>Use this only if you don't upload a file.</small></label>
                                <label class="profile-field"><span>Song title</span><input type="text" name="profile_music_title" id="musicTitleInput" maxlength="80" value="<?php echo profile_h($profile['profile_music_title'] ?? ''); ?>" placeholder="Song title"></label>
                                <label class="profile-field"><span>Artist / note</span><input type="text" name="profile_music_artist" id="musicArtistInput" maxlength="80" value="<?php echo profile_h($profile['profile_music_artist'] ?? ''); ?>" placeholder="Artist or source"></label>
                                <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="profile_show_audio_player" value="0"><input type="checkbox" name="profile_show_audio_player" value="1" <?php echo (int)($profile['profile_show_audio_player'] ?? 1) === 1 ? 'checked' : ''; ?> id="showAudioPlayerInput"><span><i class="fa-solid fa-sliders"></i>Show player</span></label>
                                <input type="hidden" id="hasServerMusic" value="<?php echo (!empty($profile['profile_music_mime']) || !empty(trim((string)($profile['profile_music_url'] ?? '')))) ? '1' : '0'; ?>">
                                <?php if (!empty($profile['profile_music_mime'])): ?>
                                    <label class="profile-toggle-card profile-inline-toggle"><input type="checkbox" name="remove_profile_music_upload" value="1"><span><i class="fa-solid fa-trash"></i>Remove uploaded MP3</span></label>
                                <?php endif; ?>

                                <!-- floating button options -->
                                <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="profile_show_audio_btn" value="0"><input type="checkbox" name="profile_show_audio_btn" value="1" <?php echo (int)($profile['profile_show_audio_btn'] ?? 1) === 1 ? 'checked' : ''; ?> id="showAudioBtnInput"><span><i class="fa-solid fa-circle-play"></i>Floating audio button</span></label>
                                <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="profile_bg_use_video_audio" value="0"><input type="checkbox" name="profile_bg_use_video_audio" value="1" <?php echo (int)($profile['profile_bg_use_video_audio'] ?? 0) === 1 ? 'checked' : ''; ?> id="bgUseVideoAudioInput"><span><i class="fa-solid fa-video"></i>Use background video audio</span></label>
                                <label class="profile-field"><span>Floating button position</span><select name="profile_audio_btn_position" id="audioBtnPositionInput">
                                        <option value="bottom-right" <?php echo ($profile['profile_audio_btn_position'] ?? 'bottom-right') === 'bottom-right' ? 'selected' : ''; ?>>Bottom Right</option>
                                        <option value="bottom-left" <?php echo ($profile['profile_audio_btn_position'] ?? 'bottom-right') === 'bottom-left' ? 'selected' : ''; ?>>Bottom Left</option>
                                        <option value="top-right" <?php echo ($profile['profile_audio_btn_position'] ?? 'bottom-right') === 'top-right' ? 'selected' : ''; ?>>Top Right</option>
                                        <option value="top-left" <?php echo ($profile['profile_audio_btn_position'] ?? 'bottom-right') === 'top-left' ? 'selected' : ''; ?>>Top Left</option>
                                    </select></label>
                                <label class="profile-field" style="grid-column: span 2;"><span>Default audio volume (<span id="audioDefaultVolumeVal"><?php echo round((float)($profile['profile_audio_default_volume'] ?? 0.18) * 100); ?></span>%)</span>
                                    <input type="range" name="profile_audio_default_volume" id="audioDefaultVolumeInput" min="0" max="1" step="0.01" value="<?php echo (float)($profile['profile_audio_default_volume'] ?? 0.18); ?>" style="width: 100%;"></label>
                            </div>

                            <div class="bio-section-heading profile-mt">
                                <div><span><i class="fa-solid fa-door-open"></i> Click to Enter (Entry Screen)</span>
                                    <p>Show an entry overlay that the user must click to view the profile. Useful to auto-play audio.</p>
                                </div>
                            </div>
                            <div class="profile-field-grid two">
                                <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="profile_click_to_enter" value="0"><input type="checkbox" name="profile_click_to_enter" value="1" <?php echo (int)($profile['profile_click_to_enter'] ?? 0) === 1 ? 'checked' : ''; ?> id="clickToEnterInput"><span><i class="fa-solid fa-hand-pointer"></i>Enable Click to Enter</span></label>
                                <label class="profile-field"><span>Entry button text</span><input type="text" name="profile_enter_text" id="enterTextInput" maxlength="80" value="<?php echo profile_h($profile['profile_enter_text'] ?? ''); ?>" placeholder="E.g. Click to Enter / Enter"></label>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Tags -->
                    <div class="profile-edit-section editor-card" data-edit-section="tags" data-editor-category="profile">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fa-solid fa-tags"></i></span>
                                <div class="editor-card-text">
                                    <h3>Custom Tag / Pills</h3>
                                    <p>Add colored pills under your biography (max 10)</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Pills</span>
                                <span class="editor-card-chevron"><i class="fa-solid fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fa-solid fa-tags"></i> Tag Management</span>
                                    <p>Configure up to 10 colored pills under your biography.</p>
                                </div>
                                <button type="button" class="bio-button" data-add-row="tags">+ Add Tag</button>
                            </div>
                            <div class="profile-repeater" id="tagsRepeater"></div>
                        </div>
                    </div>

                    <div class="profile-edit-section editor-card" data-edit-section="design" data-editor-category="design">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fa-solid fa-palette"></i></span>
                                <div class="editor-card-text">
                                    <h3>Style and Colors</h3>
                                    <p>Premium themes, palettes, layout, shapes, borders and opacity</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Customized</span>
                                <span class="editor-card-chevron"><i class="fa-solid fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="profile-presets-block" style="margin-bottom: 1.5rem;">
                                <span><i class="fa-solid fa-magic"></i> Premium Themes (One-click to apply) <span class="premium-badge-tag"><i class="fa-solid fa-crown"></i> Premium</span></span>
                                <div class="theme-presets-gallery">
                                    <div class="theme-preset-card" data-theme-preset="cyberpunk">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: #ff007f;"></div>
                                            <div class="theme-preview-color" style="background: #7f00ff;"></div>
                                            <div class="theme-preview-color" style="background: #0a0512;"></div>
                                        </div>
                                        <span class="theme-preset-name">Cyberpunk</span>
                                        <span class="theme-preset-desc">Neon cyberpunk and cybernetic glow</span>
                                    </div>
                                    <div class="theme-preset-card" data-theme-preset="rgb">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: #ff0000;"></div>
                                            <div class="theme-preview-color" style="background: #00ff00;"></div>
                                            <div class="theme-preview-color" style="background: #080808;"></div>
                                        </div>
                                        <span class="theme-preset-name">RGB Gamer</span>
                                        <span class="theme-preset-desc">Dynamic and crazy gaming colors</span>
                                    </div>
                                    <div class="theme-preset-card" data-theme-preset="glass">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: rgba(255,255,255,0.2);"></div>
                                            <div class="theme-preview-color" style="background: rgba(255,255,255,0.15);"></div>
                                            <div class="theme-preview-color" style="background: rgba(255,255,255,0.05);"></div>
                                        </div>
                                        <span class="theme-preset-name">Glassmorphism</span>
                                        <span class="theme-preset-desc">Clear glass effect with blur</span>
                                    </div>
                                    <div class="theme-preset-card" data-theme-preset="sakura">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: #ff758c;"></div>
                                            <div class="theme-preview-color" style="background: #ff7eb3;"></div>
                                            <div class="theme-preview-color" style="background: #1f1015;"></div>
                                        </div>
                                        <span class="theme-preset-name">Sakura</span>
                                        <span class="theme-preset-desc">Cherry blossom and pink tones</span>
                                    </div>
                                    <div class="theme-preset-card" data-theme-preset="anime">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: #ff6b6b;"></div>
                                            <div class="theme-preview-color" style="background: #feca57;"></div>
                                            <div class="theme-preview-color" style="background: #1a0f0f;"></div>
                                        </div>
                                        <span class="theme-preset-name">Anime</span>
                                        <span class="theme-preset-desc">Warm colors and oriental style</span>
                                    </div>
                                    <div class="theme-preset-card" data-theme-preset="neon">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: #00f0ff;"></div>
                                            <div class="theme-preview-color" style="background: #ff007f;"></div>
                                            <div class="theme-preview-color" style="background: #03030d;"></div>
                                        </div>
                                        <span class="theme-preset-name">Neon Glow</span>
                                        <span class="theme-preset-desc">Glow lights and dark contrasts</span>
                                    </div>
                                    <div class="theme-preset-card" data-theme-preset="discord">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: #5865f2;"></div>
                                            <div class="theme-preview-color" style="background: #57f287;"></div>
                                            <div class="theme-preview-color" style="background: #2f3136;"></div>
                                        </div>
                                        <span class="theme-preset-name">Discord Style</span>
                                        <span class="theme-preset-desc">Official Discord client layout</span>
                                    </div>
                                    <div class="theme-preset-card" data-theme-preset="minimal">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: #000000;"></div>
                                            <div class="theme-preview-color" style="background: #888888;"></div>
                                            <div class="theme-preview-color" style="background: #ffffff;"></div>
                                        </div>
                                        <span class="theme-preset-name">Minimal</span>
                                        <span class="theme-preset-desc">Clean, elegant, black and white</span>
                                    </div>
                                    <div class="theme-preset-card" data-theme-preset="dark_premium">
                                        <div class="theme-preview-swatch">
                                            <div class="theme-preview-color" style="background: #c9d9ff;"></div>
                                            <div class="theme-preview-color" style="background: #0f5bff;"></div>
                                            <div class="theme-preview-color" style="background: #030509;"></div>
                                        </div>
                                        <span class="theme-preset-name">Dark Premium</span>
                                        <span class="theme-preset-desc">Ultimate modern dark theme</span>
                                    </div>
                                </div>
                            </div>

                            <div class="profile-presets-block">
                                <span><i class="fa-solid fa-palette"></i> Quick Color Palettes</span>
                                <div class="profile-presets-grid">
                                    <button type="button" class="profile-preset-btn" data-accent="#0f5bff" data-secondary="#8b5cf6" data-card="#080c18" data-text="#f7f8ff" style="--btn-accent: #0f5bff; --btn-secondary: #8b5cf6;" title="Default Cripsum"></button>
                                    <button type="button" class="profile-preset-btn" data-accent="#ff007f" data-secondary="#7f00ff" data-card="#0a0512" data-text="#ffebf5" style="--btn-accent: #ff007f; --btn-secondary: #7f00ff;" title="Cyberpunk"></button>
                                    <button type="button" class="profile-preset-btn" data-accent="#ff6b6b" data-secondary="#feca57" data-card="#1a0f0f" data-text="#fff5f5" style="--btn-accent: #ff6b6b; --btn-secondary: #feca57;" title="Sunset Glow"></button>
                                    <button type="button" class="profile-preset-btn" data-accent="#10b981" data-secondary="#3b82f6" data-card="#040d1a" data-text="#ecfdf5" style="--btn-accent: #10b981; --btn-secondary: #3b82f6;" title="Emerald Ocean"></button>
                                    <button type="button" class="profile-preset-btn" data-accent="#ffffff" data-secondary="#888888" data-card="#121212" data-text="#ffffff" style="--btn-accent: #ffffff; --btn-secondary: #888888;" title="Monochrome Clean"></button>
                                    <button type="button" class="profile-preset-btn" data-accent="#ff758c" data-secondary="#ff7eb3" data-card="#1f1015" data-text="#fff0f5" style="--btn-accent: #ff758c; --btn-secondary: #ff7eb3;" title="Cherry Blossom"></button>
                                    <button type="button" class="profile-preset-btn" data-accent="#a855f7" data-secondary="#ec4899" data-card="#150b24" data-text="#faf5ff" style="--btn-accent: #a855f7; --btn-secondary: #ec4899;" title="Purple Orchid"></button>
                                </div>
                            </div>

                            <div class="profile-presets-block profile-mt">
                                <span><i class="fa-solid fa-shapes"></i> UI Style Presets</span>
                                <div class="profile-presets-grid" style="grid-template-columns: repeat(3, 1fr);">
                                    <button type="button" class="ui-preset-btn btn-secondary" data-preset="modern" style="padding: 8px; font-size: 0.8rem;">Modern</button>
                                    <button type="button" class="ui-preset-btn btn-secondary" data-preset="glass" style="padding: 8px; font-size: 0.8rem;">Glass</button>
                                    <button type="button" class="ui-preset-btn btn-secondary" data-preset="bubble" style="padding: 8px; font-size: 0.8rem;">Bubble</button>
                                    <button type="button" class="ui-preset-btn btn-secondary" data-preset="sharp" style="padding: 8px; font-size: 0.8rem;">Sharp</button>
                                    <button type="button" class="ui-preset-btn btn-secondary" data-preset="cyber" style="padding: 8px; font-size: 0.8rem;">Cyber</button>
                                    <button type="button" class="ui-preset-btn btn-secondary" data-preset="minimal" style="padding: 8px; font-size: 0.8rem;">Minimal</button>
                                </div>
                            </div>

                            <div class="bio-section-heading profile-mt">
                                <div><span><i class="fa-solid fa-sliders"></i> Layout and Colors Options</span></div>
                            </div>
                            <div class="profile-field-grid three">
                                <label class="profile-field"><span>Primary accent</span><input type="color" name="accent_color" id="accentInput" value="<?php echo profile_h($accent); ?>"></label>
                                <label class="profile-field"><span>Secondary accent</span><input type="color" name="profile_secondary_color" id="secondaryColorInput" value="<?php echo profile_h($secondaryColor); ?>"></label>
                                <label class="profile-field"><span>Theme</span><select name="profile_theme" id="themeInput"><?php foreach (['dark' => 'Dark', 'light' => 'Light', 'auto' => 'Auto'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_theme'] ?? 'dark') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                                <label class="profile-field"><span>Layout</span>
                                    <select id="layoutInput">
                                        <?php
                                        $currentLayoutVal = ($profile['profile_layout'] ?? 'standard');
                                        $currentLayoutMapped = ['left-tabs' => 'standard', 'right-tabs' => 'showcase', 'stacked' => 'clean', 'center-split' => 'compact'][$currentLayoutVal] ?? $currentLayoutVal;
                                        if ((int)($profile['profile_layout_snap'] ?? 0) === 1) {
                                            $currentLayoutMapped = 'scrollsnap';
                                        }

                                        $layoutOptions = [
                                            'standard' => 'Standard default',
                                            'compact' => 'Profile center, content on sides',
                                            'showcase' => 'Profile right, tabs left',
                                            'clean' => 'Centered column',
                                            'scrollsnap' => 'Vertical Scroll Snap Layout'
                                        ];
                                        foreach ($layoutOptions as $val => $lbl) {
                                            $selectedStr = ($currentLayoutMapped === $val) ? 'selected' : '';
                                            $premStr = ($val === 'scrollsnap') ? 'data-premium="1"' : '';
                                            echo "<option value=\"$val\" $selectedStr $premStr>$lbl</option>";
                                        }
                                        ?>
                                    </select>
                                    <input type="hidden" name="profile_layout" id="profileLayoutHidden" value="<?php echo profile_h($profile['profile_layout'] ?? 'standard'); ?>">
                                    <input type="hidden" name="profile_layout_snap" id="profileLayoutSnapHidden" value="<?php echo (int)($profile['profile_layout_snap'] ?? 0); ?>">
                                </label>
                                <label class="profile-field"><span>Music Player Style</span><select name="profile_music_theme" id="musicThemeInput">
                                        <option value="default" <?php echo ($profile['profile_music_theme'] ?? 'default') === 'default' ? 'selected' : ''; ?>>Default</option>
                                        <option value="retro" <?php echo ($profile['profile_music_theme'] ?? 'default') === 'retro' ? 'selected' : ''; ?> data-premium="1">Compact Row</option>
                                        <option value="cyberpunk" <?php echo ($profile['profile_music_theme'] ?? 'default') === 'cyberpunk' ? 'selected' : ''; ?> data-premium="1">Centered Pill</option>
                                        <option value="synthwave" <?php echo ($profile['profile_music_theme'] ?? 'default') === 'synthwave' ? 'selected' : ''; ?> data-premium="1">Vinyl Player (Disc)</option>
                                    </select></label>
                                <label class="profile-field"><span>Card color</span><input type="color" name="profile_card_color" id="cardColorInput" value="<?php echo profile_h($cardColor ?: '#080c18'); ?>"><small>Leave default for classic glassmorphism.</small></label>
                                <label class="profile-field"><span>Text color</span><input type="color" name="profile_text_color" id="textColorInput" value="<?php echo profile_h($textColor ?: ($theme === 'light' ? '#111827' : '#f7f8ff')); ?>"></label>
                                <label class="profile-field"><span>Link style</span><select name="profile_link_style" id="linkStyleInput"><?php foreach (['glass' => 'Glass', 'solid' => 'Solid', 'outline' => 'Outline', 'neon' => 'Neon'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo $linkStyle === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                                <label class="profile-field"><span>Button shape</span><select name="profile_button_shape" id="buttonShapeInput"><?php foreach (['pill' => 'Pill', 'rounded' => 'Rounded', 'sharp' => 'Sharp'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo $buttonShape === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                                <label class="profile-field"><span>Socials Display Style</span><select name="profile_socials_style" id="socialsStyleInput"><?php foreach (['cards' => 'Large cards (2x row)', 'icons' => 'Clean icons only'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_socials_style'] ?? 'cards') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                            </div>

                            <div class="bio-section-heading profile-mt">
                                <div><span><i class="fa-solid fa-wand-magic-sparkles"></i> Shapes, Borders and Transparency</span></div>
                            </div>
                            <div class="profile-field-grid two">
                                <label class="profile-field"><span>UI Global Shape</span><select name="profile_ui_shape" id="uiShapeInput">
                                        <?php foreach (['circle' => 'Circle (100%)', 'rounded' => 'Rounded (24px)', 'soft' => 'Soft Rounded (16px)', 'square-rounded' => 'Square Rounded (8px)', 'square' => 'Square (0px)', 'pill' => 'Pill (999px)'] as $val => $lbl): ?>
                                            <option value="<?php echo $val; ?>" <?php echo ($profile['profile_ui_shape'] ?? 'circle') === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                        <?php endforeach; ?>
                                    </select></label>
                                <label class="profile-field"><span>Avatar PFP Shape</span><select name="profile_avatar_shape" id="avatarShapeInput">
                                        <?php foreach (['circle' => 'Circle', 'squircle' => 'Squircle', 'square' => 'Square', 'hexagon' => 'Hexagon', 'octagon' => 'Octagon', 'badge' => 'Gaming Badge (Shield)'] as $val => $lbl): ?>
                                            <option value="<?php echo $val; ?>" <?php echo ($profile['profile_avatar_shape'] ?? 'circle') === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                        <?php endforeach; ?>
                                    </select></label>

                                <label class="profile-field"><span>Secondary Component Corners</span><select name="profile_corner_style" id="cornerStyleInput">
                                        <?php foreach (['circle' => 'Circle (Round 100px)', 'rounded' => 'Classic rounded', 'soft' => 'Soft rounded', 'square' => 'Sharp', 'custom' => 'Custom (px)'] as $val => $lbl): ?>
                                            <option value="<?php echo $val; ?>" <?php echo ($profile['profile_corner_style'] ?? 'circle') === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                        <?php endforeach; ?>
                                    </select></label>
                                <label class="profile-field" id="cornerStyleCustomContainer" style="display: <?php echo ($profile['profile_corner_style'] ?? 'circle') === 'custom' ? 'block' : 'none'; ?>;"><span>Custom Corners (<span id="cornerStyleCustomVal"><?php echo (int)($profile['profile_corner_style_custom'] ?? 8); ?></span>px)</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_corner_style_custom" id="cornerStyleCustomInput" min="0" max="100" value="<?php echo (int)($profile['profile_corner_style_custom'] ?? 8); ?>" style="flex: 1;">
                                    </div>
                                </label>
                                <label class="profile-field"><span>Card & Button Borders Style</span><select name="profile_border_style" id="borderStyleInput">
                                        <?php foreach (['none' => 'No border', 'thin' => 'Thin border', 'glow' => 'Glow border', 'gradient' => 'Gradient border'] as $val => $lbl): ?>
                                            <option value="<?php echo $val; ?>" <?php echo ($profile['profile_border_style'] ?? 'thin') === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                        <?php endforeach; ?>
                                    </select></label>

                                <label class="profile-field"><span>Social Icons Size</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_social_size" id="socialSizeInput" min="32" max="72" value="<?php echo (int)($profile['profile_social_size'] ?? 42); ?>" style="flex: 1;">
                                        <span id="socialSizeVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_social_size'] ?? 42); ?>px</span>
                                    </div>
                                </label>
                                <label class="profile-field"><span>Social Icons Spacing</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_icon_spacing" id="iconSpacingInput" min="0" max="24" value="<?php echo (int)($profile['profile_icon_spacing'] ?? 8); ?>" style="flex: 1;">
                                        <span id="iconSpacingVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_icon_spacing'] ?? 8); ?>px</span>
                                    </div>
                                </label>

                                <label class="profile-field"><span>Badge Size</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_badge_size" id="badgeSizeInput" min="16" max="60" value="<?php echo (int)($profile['profile_badge_size'] ?? 24); ?>" style="flex: 1;">
                                        <span id="badgeSizeVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_badge_size'] ?? 24); ?>px</span>
                                    </div>
                                </label>
                                <label class="profile-field"><span>Buttons Height (Media/Links)</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_button_size" id="buttonSizeInput" min="32" max="80" value="<?php echo (int)($profile['profile_button_size'] ?? 48); ?>" style="flex: 1;">
                                        <span id="buttonSizeVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_button_size'] ?? 48); ?>px</span>
                                    </div>
                                </label>

                                <label class="profile-field"><span>Profile font</span><select name="profile_font" id="fontInput">
                                        <?php
                                        $fonts = [
                                            'Poppins' => 'Poppins (Default)',
                                            'Inter' => 'Inter',
                                            'Roboto' => 'Roboto',
                                            'Outfit' => 'Outfit',
                                            'Playfair Display' => 'Playfair Display',
                                            'Space Grotesk' => 'Space Grotesk',
                                            'Syne' => 'Syne',
                                            'Montserrat' => 'Montserrat',
                                            'Fira Code' => 'Fira Code (Monospace)',
                                            'PT Mono' => 'PT Mono',
                                            'Cinzel' => 'Cinzel (Serif Elegant)',
                                            'Rubik' => 'Rubik',
                                            'Bebas Neue' => 'Bebas Neue',
                                            'Minecraft' => 'Minecraft (Gaming)',
                                            'Gang of Three' => 'Gang of Three (Kung-Fu Brush)',
                                            'Press Start 2P' => 'Press Start 2P',
                                            'Bungee' => 'Bungee (Arcade Heavy)',
                                            'Permanent Marker' => 'Permanent Marker (Graffiti)',
                                            'Creepster' => 'Creepster (Horror)',
                                            'Shojumaru' => 'Shojumaru (Asian Style)'
                                        ];
                                        $allowedFreeFonts = ['Poppins', 'Inter', 'Roboto', 'Outfit', 'Montserrat'];
                                        foreach ($fonts as $fontVal => $fontLabel):
                                            $isPrem = !in_array($fontVal, $allowedFreeFonts, true);
                                        ?>
                                            <option value="<?php echo $fontVal; ?>" <?php echo ($profile['profile_font'] ?? 'Poppins') === $fontVal ? 'selected' : ''; ?> <?php echo $isPrem ? 'data-premium="1"' : ''; ?>><?php echo $fontLabel; ?></option>
                                        <?php endforeach; ?>
                                    </select></label>

                                <label class="profile-field"><span>Card opacity</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_card_opacity" id="cardOpacityInput" min="0" max="100" value="<?php echo (int)($profile['profile_card_opacity'] ?? 68); ?>" style="flex: 1;">
                                        <span id="cardOpacityVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_card_opacity'] ?? 68); ?>%</span>
                                    </div>
                                    <small>0% for a fully transparent card profile.</small>
                                </label>

                                <label class="profile-field"><span>Card blur</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_card_blur" id="cardBlurInput" min="0" max="40" value="<?php echo (int)($profile['profile_card_blur'] ?? 20); ?>" style="flex: 1;">
                                        <span id="cardBlurVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_card_blur'] ?? 20); ?>px</span>
                                    </div>
                                </label>

                                <label class="profile-field"><span>Card border opacity</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_border_opacity" id="borderOpacityInput" min="0" max="100" value="<?php echo (int)($profile['profile_border_opacity'] ?? 100); ?>" style="flex: 1;">
                                        <span id="borderOpacityVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_border_opacity'] ?? 100); ?>%</span>
                                    </div>
                                </label>

                                <label class="profile-field"><span>Card border radius</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_border_radius" id="borderRadiusInput" min="0" max="40" value="<?php echo (int)($profile['profile_border_radius'] ?? 30); ?>" style="flex: 1;">
                                        <span id="borderRadiusVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_border_radius'] ?? 30); ?>px</span>
                                    </div>
                                </label>

                                <label class="profile-field"><span>Card border width</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_border_width" id="borderWidthInput" min="0" max="5" value="<?php echo (int)($profile['profile_border_width'] ?? 1); ?>" style="flex: 1;">
                                        <span id="borderWidthVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_border_width'] ?? 1); ?>px</span>
                                    </div>
                                </label>

                                <label class="profile-field"><span>Card border color</span>
                                    <input type="color" name="profile_border_color" id="borderColorInput" value="<?php echo profile_h($profile['profile_border_color'] ?? '#ffffff'); ?>">
                                    <small>Ignored if border thickness is 0.</small>
                                </label>
                            </div>

                            <div class="bio-section-heading profile-mt">
                                <div><span><i class="fa-solid fa-image"></i> Background Customization</span></div>
                            </div>
                            <div class="profile-field-grid three">
                                <label class="profile-field"><span>Background overlay opacity</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_bg_overlay_opacity" id="bgOverlayOpacityInput" min="0" max="1" step="0.05" value="<?php echo (float)($profile['profile_bg_overlay_opacity'] ?? 1.0); ?>" style="flex: 1;">
                                        <span id="bgOverlayOpacityVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo round((float)($profile['profile_bg_overlay_opacity'] ?? 1.0) * 100); ?>%</span>
                                    </div>
                                    <small>Adjust the transparency of the dark layer overlaying the background.</small>
                                </label>
                                <label class="profile-field"><span>Background blur</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_bg_blur" id="bgBlurInput" min="0" max="40" step="1" value="<?php echo (int)($profile['profile_bg_blur'] ?? 0); ?>" style="flex: 1;">
                                        <span id="bgBlurVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo (int)($profile['profile_bg_blur'] ?? 0); ?>px</span>
                                    </div>
                                    <small>Blur the background image or video.</small>
                                </label>
                                <label class="profile-field"><span>Side glows opacity</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_bg_orbs_opacity" id="bgOrbsOpacityInput" min="0" max="1" step="0.05" value="<?php echo (float)($profile['profile_bg_orbs_opacity'] ?? 0.45); ?>" style="flex: 1;">
                                        <span id="bgOrbsOpacityVal" style="font-weight: 700; min-width: 40px; text-align: right;"><?php echo round((float)($profile['profile_bg_orbs_opacity'] ?? 0.45) * 100); ?>%</span>
                                    </div>
                                    <small>Adjust the opacity of the 2 side color glow circles.</small>
                                </label>
                            </div>

                            <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; border-top: 1px solid rgba(255, 255, 255, 0.08); padding-top: 1.5rem;">
                                <button type="button" id="resetDesignBtn" class="bio-button" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.25); display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 0.9rem;">
                                    <i class="fa-solid fa-arrow-rotate-left"></i> Reset to default values
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="profile-edit-section editor-card" data-edit-section="discord" data-editor-category="profile">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fa-brands fa-discord"></i></span>
                                <div class="editor-card-text">
                                    <h3>Discord</h3>
                                    <p>Discord account connection, Lanyard ID and server Widget</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge <?php echo $discordConnected ? 'is-active' : ''; ?>"><?php echo $discordConnected ? 'Connected' : 'Disconnected'; ?></span>
                                <span class="editor-card-chevron"><i class="fa-solid fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="profile-discord-connect-card">
                                <div class="profile-discord-connect-main">
                                    <?php if ($discordConnected): ?>
                                        <?php if ($discordAvatarUrl): ?><img src="<?php echo profile_h($discordAvatarUrl); ?>" alt="" loading="lazy"><?php else: ?><span class="profile-discord-avatar-fallback"><i class="fa-brands fa-discord"></i></span><?php endif; ?>
                                        <div>
                                            <strong><?php echo profile_h($discordDisplayName ?: $profile['discord_username']); ?></strong>
                                            <small>@<?php echo profile_h($profile['discord_username']); ?> · ID <?php echo profile_h($profile['discord_id']); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <span class="profile-discord-avatar-fallback"><i class="fa-brands fa-discord"></i></span>
                                        <div>
                                            <strong>Discord not connected</strong>
                                            <small>Connect Discord to import ID, username and avatar.</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="profile-discord-actions">
                                    <a class="bio-button bio-button--primary" href="<?php echo profile_h($connectDiscordUrl); ?>"><i class="fa-brands fa-discord"></i><?php echo $discordConnected ? 'Reconnect' : 'Connect Discord'; ?></a>
                                    <?php if ($discordConnected): ?>
                                        <button class="bio-button profile-discord-disconnect" type="submit" form="disconnectDiscordForm"><i class="fa-solid fa-link-slash"></i>Disconnect</button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="profile-field-grid two" style="margin-top: 2%;">
                                <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="discord_use_display_name" value="0"><input type="checkbox" name="discord_use_display_name" id="discordUseNameInput" value="1" <?php echo (int)($profile['discord_use_display_name'] ?? 0) === 1 ? 'checked' : ''; ?> <?php echo !$discordConnected ? 'disabled' : ''; ?>><span><i class="fa-brands fa-discord"></i>Use Discord name</span></label>
                                <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="discord_use_avatar" value="0"><input type="checkbox" name="discord_use_avatar" id="discordUseAvatarInput" value="1" <?php echo (int)($profile['discord_use_avatar'] ?? 0) === 1 ? 'checked' : ''; ?> <?php echo !$discordConnected ? 'disabled' : ''; ?>><span><i class="fa-brands fa-discord"></i>Use Discord avatar</span></label>
                            </div>

                            <div class="bio-section-heading" style="margin-top: 2rem; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 1.5rem;">
                                <div><span><i class="fa-solid fa-signal"></i> Rich Presence</span>
                                    <p>Show your state and activities in real-time (games, Spotify, etc.).</p>
                                </div>
                            </div>

                            <label class="profile-field">
                                <span>Discord user ID</span>
                                <input type="text" name="discord_id" id="discordIdInput" maxlength="25" value="<?php echo profile_h($profile['discord_id'] ?? ''); ?>" placeholder="E.g. 8239582304530540" <?php echo $discordConnected ? 'readonly style="background: rgba(255,255,255,0.03); color: rgba(255,255,255,0.4); cursor: not-allowed;"' : ''; ?>>
                                <small>Necessary to fetch your activity and presence status.</small>
                            </label>

                            <div class="profile-discord-presence-card" style="margin-bottom: 2rem; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 1.5rem; border-radius: 12px; display: flex; flex-direction: column; gap: 1rem;">
                                <div style="display: flex; gap: 12px; align-items: flex-start;">
                                    <i class="fa-solid fa-circle-info" style="color: #5865F2; font-size: 1.2rem; margin-top: 2px;"></i>
                                    <div>
                                        <strong style="display: block; margin-bottom: 4px; color: #fff;">Enable Rich Presence</strong>
                                        <span style="font-size: 0.85rem; opacity: 0.75; line-height: 1.4;">Discord login only imports basic details. To display your real-time activities (games, Spotify, etc.) on your profile, you must join our Discord server where the status bot is located.</span>
                                    </div>
                                </div>
                                <a href="https://discord.gg/XdheJHVURw" target="_blank" rel="noopener noreferrer" class="bio-button bio-button--primary" style="background-color: #5865F2; border: none; align-self: flex-start; padding: 10px 16px; font-weight: 500; font-size: 0.9rem; gap: 8px; border-radius: 8px;">
                                    <i class="fa-brands fa-discord"></i> Join the Discord Server
                                </a>
                            </div>

                            <div class="bio-section-heading" style="margin-top: 2rem; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 1.5rem;">
                                <div><span><i class="fa-brands fa-discord"></i> Discord Server</span>
                                    <p>Display a widget of your Discord server on your public profile.</p>
                                </div>
                            </div>

                            <label class="profile-field">
                                <span>Discord server invite link</span>
                                <input type="text" name="discord_server_invite" id="discordServerInviteInput" value="<?php echo profile_h($profile['discord_server_invite'] ?? ''); ?>" placeholder="https://discord.gg/invite or code">
                                <small>Enter the invite link (e.g. <code>https://discord.gg/yourserver</code>) to display the server widget.</small>
                            </label>
                        </div>
                    </div>

                    <div class="profile-edit-section editor-card" data-edit-section="links" data-editor-category="profile">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fa-solid fa-link"></i></span>
                                <div class="editor-card-text">
                                    <h3>Socials and Links</h3>
                                    <p>Social profiles (icons) and large highlighted custom cards</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Links</span>
                                <span class="editor-card-chevron"><i class="fa-solid fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fa-solid fa-link"></i> Socials</span>
                                    <p>Quick icons under profile details.</p>
                                </div><button type="button" class="bio-button" data-add-row="socials" style="margin: 0;">+ Social</button>
                            </div>
                            <div class="profile-repeater" id="socialsRepeater"></div>

                            <div class="bio-section-heading profile-mt">
                                <div><span><i class="fa-solid fa-star"></i> Custom Links</span>
                                    <p>Large highlighted cards.</p>
                                </div><button type="button" class="bio-button" data-add-row="links">+ Link</button>
                            </div>
                            <div class="profile-repeater" id="linksRepeater"></div>
                        </div>
                    </div>

                    <div class="profile-edit-section editor-card" data-edit-section="embeds" data-editor-category="profile">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fa-solid fa-share-from-square"></i></span>
                                <div class="editor-card-text">
                                    <h3>Embed</h3>
                                    <p>Spotify playlists, YouTube videos or external widgets</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Media</span>
                                <span class="editor-card-chevron"><i class="fa-solid fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fa-solid fa-share-from-square"></i> Embed</span>
                                    <p>Insert Spotify playlist or YouTube video embed code.</p>
                                </div><button type="button" class="bio-button" data-add-row="embeds">+ Embed</button>
                            </div>
                            <div class="profile-repeater" id="embedsRepeater"></div>
                        </div>
                    </div>

                    <div class="profile-edit-section editor-card" data-edit-section="projects" data-editor-category="profile">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fa-solid fa-cubes"></i></span>
                                <div class="editor-card-text">
                                    <h3>Projects</h3>
                                    <p>Showcase of your favorite projects or websites</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Showcase</span>
                                <span class="editor-card-chevron"><i class="fa-solid fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fa-solid fa-cubes"></i> Projects</span></div>
                                <button type="button" class="bio-button" data-add-row="projects">+ Project</button>
                            </div>
                            <div class="profile-repeater" id="projectsRepeater"></div>
                        </div>
                    </div>

                    <div class="profile-edit-section editor-card" data-edit-section="content" data-editor-category="profile">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fa-solid fa-circle-play"></i></span>
                                <div class="editor-card-text">
                                    <h3>Contents</h3>
                                    <p>Edits, videos, and custom showcase layouts</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Video</span>
                                <span class="editor-card-chevron"><i class="fa-solid fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fa-solid fa-circle-play"></i> Contents</span>
                                    <p>Edits, video, custom pages and showcases.</p>
                                </div><button type="button" class="bio-button" data-add-row="contents">+ Content</button>
                            </div>
                            <div class="profile-repeater" id="contentsRepeater"></div>
                        </div>
                    </div>

                    <div class="profile-edit-section editor-card" data-edit-section="custom" data-editor-category="advanced">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fa-solid fa-sliders"></i></span>
                                <div class="editor-card-text">
                                    <h3>Custom Blocks</h3>
                                    <p>Free custom blocks with text, images, GIFs or videos</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">HTML/Text</span>
                                <span class="editor-card-chevron"><i class="fa-solid fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fa-solid fa-wand-magic-sparkles"></i> Custom blocks</span>
                                    <p>Texts, images, GIFs or videos.</p>
                                </div><button type="button" class="bio-button" data-add-row="blocks">+ Block</button>
                            </div>
                            <div class="profile-repeater" id="blocksRepeater"></div>
                        </div>
                    </div>

                    <div class="profile-edit-section editor-card" data-edit-section="effects" data-editor-category="design">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fa-solid fa-magic"></i></span>
                                <div class="editor-card-text">
                                    <h3>Effects and PFP Ring</h3>
                                    <p>Page effects, PFP ring, name colors, tilt card and browser tab</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Effects</span>
                                <span class="editor-card-chevron"><i class="fa-solid fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fa-solid fa-magic"></i> Page Effects</span>
                                    <p>Aesthetic effects for cursor, page and photo profile.</p>
                                </div>
                            </div>
                            <div class="profile-field-grid three">
                                <label class="profile-field"><span>Page effect</span><select name="profile_effect" id="profileEffectInput">
                                        <?php
                                        $effectsList = [
                                            'none' => 'None',
                                            'cursor_glow' => 'Mouse glow',
                                            'soft_particles' => 'Soft particles',
                                            'scanlines' => 'Soft scanlines',
                                            'ambient' => 'Ambient glow',
                                            'aurora' => 'Aurora',
                                            'gradient_waves' => 'Gradient waves',
                                            'stars' => 'Light stars',
                                            'cyber_grid' => 'Cyber grid',
                                            'spotlight' => 'Mouse spotlight',
                                            'digital_noise' => 'Digital noise',
                                            'glass_rain' => 'Glass rain',
                                            'sakura_falling' => 'Sakura petals',
                                            'bg_grain' => 'Background grain'
                                        ];
                                        foreach ($effectsList as $value => $label):
                                            $isPrem = in_array($value, ['spotlight', 'digital_noise', 'glass_rain', 'sakura_falling', 'bg_grain'], true);
                                        ?>
                                            <option value="<?php echo $value; ?>" <?php echo ($profile['profile_effect'] ?? 'none') === $value ? 'selected' : ''; ?> <?php echo $isPrem ? 'data-premium="1"' : ''; ?>><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select><small id="glassRainWarning" class="profile-effect-warning" style="display:<?php echo ($profile['profile_effect'] ?? 'none') === 'glass_rain' ? 'flex' : 'none'; ?>"><i class="fa-solid fa-circle-info"></i> Glass rain only supports static backgrounds (images).</small></label>
                                <label class="profile-field"><span>PFP Ring style</span><select name="avatar_ring_style" id="ringStyleInput">
                                        <?php foreach (
                                            [
                                                'spin' => 'Rotation',
                                                'pulse' => 'Pulse',
                                                'orbit' => 'Orbit',
                                                'glow' => 'Glow',
                                                'dual' => 'Dual spin',
                                                'rainbow' => 'Rainbow',
                                                'halo' => 'Soft halo',
                                                'neon' => 'Neon',
                                                'spark' => 'Spark',
                                                'glitch' => 'Light glitch',
                                                'none' => 'None'
                                            ] as $value => $label
                                        ): ?><option value="<?php echo $value; ?>" <?php echo ($profile['avatar_ring_style'] ?? 'spin') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?>
                                    </select></label>
                                <label class="profile-field"><span>PFP Ring color</span><input type="color" name="avatar_ring_color" id="ringColorInput" value="<?php echo profile_h(profile_normalize_hex_color($profile['avatar_ring_color'] ?: $accent)); ?>"></label>
                            </div>
                            <div class="profile-effect-hint" style="margin: 0.5rem 0 1rem 0;">
                                <span><i class="fa-solid fa-circle-info"></i> The ring color is applied on the public profile.</span>
                            </div>
                            <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="avatar_ring_enabled" value="0"><input type="checkbox" name="avatar_ring_enabled" id="ringEnabledInput" value="1" <?php echo (int)($profile['avatar_ring_enabled'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fa-solid fa-circle-notch"></i>Show ring around profile picture</span></label>
                            <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="profile_avatar_border" value="0"><input type="checkbox" name="profile_avatar_border" id="avatarBorderInput" value="1" <?php echo (int)($profile['profile_avatar_border'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fa-solid fa-border-style"></i>Show border on profile picture</span></label>

                            <div class="bio-section-heading" style="margin-top: 1.8rem; border-top: 1px dashed rgba(255, 255, 255, 0.08); padding-top: 1.5rem;">
                                <div><span><i class="fa-solid fa-mouse-pointer"></i> Cursor Personalization</span>
                                    <p>Settings for pointer and cursor effects on your profile.</p>
                                </div>
                            </div>
                            <div class="profile-field-grid three">
                                <label class="profile-field"><span>Cursor Effect <span class="premium-badge-tag"><i class="fa-solid fa-crown"></i> Premium</span></span><select name="profile_cursor_effect" id="cursorEffectInput">
                                        <option value="none" <?php echo ($profile['profile_cursor_effect'] ?? 'none') === 'none' ? 'selected' : ''; ?>>None</option>
                                        <option value="follower" <?php echo ($profile['profile_cursor_effect'] ?? 'none') === 'follower' ? 'selected' : ''; ?> data-premium="1">Follower dot</option>
                                        <option value="trail" <?php echo ($profile['profile_cursor_effect'] ?? 'none') === 'trail' ? 'selected' : ''; ?> data-premium="1">Particle trail</option>
                                        <option value="trail_stars" <?php echo ($profile['profile_cursor_effect'] ?? 'none') === 'trail_stars' ? 'selected' : ''; ?> data-premium="1">Falling stars trail</option>
                                        <option value="cat_follower" <?php echo ($profile['profile_cursor_effect'] ?? 'none') === 'cat_follower' ? 'selected' : ''; ?> data-premium="1">Cat follower</option>
                                        <option value="trail_hearts" <?php echo ($profile['profile_cursor_effect'] ?? 'none') === 'trail_hearts' ? 'selected' : ''; ?> data-premium="1">Hearts trail</option>
                                    </select></label>

                                <label class="profile-field"><span>Custom Cursor Image <span class="premium-badge-tag"><i class="fa-solid fa-crown"></i> Premium</span></span>
                                    <div class="input-with-upload">
                                        <input type="text" name="profile_cursor_custom_url" id="cursorCustomUrlInput" value="<?php echo profile_h($profile['profile_cursor_custom_url'] ?? ''); ?>" placeholder="/uploads/... or url">
                                        <button type="button" class="btn-page-media-upload" data-upload-target="cursorCustomUrlInput"><i class="fa-solid fa-upload"></i></button>
                                    </div>
                                    <small style="opacity: 0.7; font-size: 0.75rem; margin-top: 4px; display: block;">PNG, JPG, WEBP, GIF, CUR, ANI. Images resized to 64×64. Animated .ani/GIF files keep their animation.</small>
                                </label>

                                <div class="profile-field" style="display: flex; flex-direction: column; justify-content: flex-end; height: 100%;">
                                    <label class="profile-toggle-card profile-inline-toggle" style="margin: 0; width: 100%;">
                                        <input type="hidden" name="profile_cursor_custom_center" value="0">
                                        <input type="checkbox" name="profile_cursor_custom_center" id="cursorCustomCenterInput" value="1" <?php echo (int)($profile['profile_cursor_custom_center'] ?? 0) === 1 ? 'checked' : ''; ?>>
                                        <span><i class="fa-solid fa-crosshairs"></i>Center Cursor</span>
                                    </label>
                                </div>
                            </div>

                            <div class="profile-field-grid three" style="margin-top: 1rem;">
                                <div class="profile-field"></div>
                                <label class="profile-field"><span>Hover Link Custom Cursor <span class="premium-badge-tag"><i class="fa-solid fa-crown"></i> Premium</span></span>
                                    <div class="input-with-upload">
                                        <input type="text" name="profile_cursor_custom_hover_url" id="cursorCustomHoverUrlInput" value="<?php echo profile_h($profile['profile_cursor_custom_hover_url'] ?? ''); ?>" placeholder="/uploads/... or url">
                                        <button type="button" class="btn-page-media-upload" data-upload-target="cursorCustomHoverUrlInput"><i class="fa-solid fa-upload"></i></button>
                                    </div>
                                    <small style="opacity: 0.7; font-size: 0.75rem; margin-top: 4px; display: block;">PNG, JPG, WEBP, GIF, CUR, ANI. Used when hovering over links.</small>
                                </label>

                                <div class="profile-field" style="display: flex; flex-direction: column; justify-content: flex-end; height: 100%;">
                                    <label class="profile-toggle-card profile-inline-toggle" style="margin: 0; width: 100%;">
                                        <input type="hidden" name="profile_cursor_custom_hover_center" value="0">
                                        <input type="checkbox" name="profile_cursor_custom_hover_center" id="cursorCustomHoverCenterInput" value="1" <?php echo (int)($profile['profile_cursor_custom_hover_center'] ?? 0) === 1 ? 'checked' : ''; ?>>
                                        <span><i class="fa-solid fa-crosshairs"></i>Center Hover</span>
                                    </label>
                                </div>
                            </div>

                            <div class="bio-section-heading" style="margin-top: 1.8rem; border-top: 1px dashed rgba(255, 255, 255, 0.08); padding-top: 1.5rem;">
                                <div><span><i class="fa-solid fa-signature"></i> Display Name Customization</span>
                                    <p>Customize the look and animation of your profile display name.</p>
                                </div>
                            </div>
                            <div class="profile-field-grid three">
                                <label class="profile-field"><span>Name color type</span><select name="profile_name_color_type" id="nameColorTypeInput">
                                        <option value="default" <?php echo ($nameStyle['type'] ?? 'default') === 'default' ? 'selected' : ''; ?>>Default white</option>
                                        <option value="solid" <?php echo ($nameStyle['type'] ?? 'default') === 'solid' ? 'selected' : ''; ?>>Single solid color</option>
                                        <option value="gradient" <?php echo ($nameStyle['type'] ?? 'default') === 'gradient' ? 'selected' : ''; ?>>Gradient sfumatura</option>
                                    </select></label>

                                <label class="profile-field field-name-solid"><span>Name color</span><input type="color" name="profile_name_solid_color" id="nameSolidColorInput" value="<?php echo profile_h($nameStyle['solid_color'] ?? '#ffffff'); ?>"></label>

                                <label class="profile-field field-name-gradient"><span>Gradient color 1</span><input type="color" name="profile_name_grad_color1" id="nameGradColor1Input" value="<?php echo profile_h($nameStyle['grad_color1'] ?? '#ffffff'); ?>"></label>
                                <label class="profile-field field-name-gradient"><span>Gradient color 2</span><input type="color" name="profile_name_grad_color2" id="nameGradColor2Input" value="<?php echo profile_h($nameStyle['grad_color2'] ?? '#8b5cf6'); ?>"></label>
                                <label class="profile-field field-name-gradient"><span>Gradient Angle (deg)</span><input type="number" name="profile_name_grad_angle" id="nameGradAngleInput" min="0" max="360" value="<?php echo (int)($nameStyle['grad_angle'] ?? 90); ?>"></label>

                                <label class="profile-field"><span>Name animation</span><select name="profile_name_animation" id="nameAnimationInput">
                                        <option value="none" <?php echo ($nameStyle['animation'] ?? 'none') === 'none' ? 'selected' : ''; ?>>None</option>
                                        <option value="rainbow" <?php echo ($nameStyle['animation'] ?? 'none') === 'rainbow' ? 'selected' : ''; ?>>Rainbow slide</option>
                                        <option value="glow" <?php echo ($nameStyle['animation'] ?? 'none') === 'glow' ? 'selected' : ''; ?>>Pulsing glow</option>
                                        <option value="sparkles" <?php echo ($nameStyle['animation'] ?? 'none') === 'sparkles' ? 'selected' : ''; ?>>Magic sparkles</option>
                                        <option value="fire" <?php echo ($nameStyle['animation'] ?? 'none') === 'fire' ? 'selected' : ''; ?>>Animated fire</option>
                                        <option value="water" <?php echo ($nameStyle['animation'] ?? 'none') === 'water' ? 'selected' : ''; ?>>Fluid water</option>
                                        <option value="glitch" <?php echo ($nameStyle['animation'] ?? 'none') === 'glitch' ? 'selected' : ''; ?>>Cyber glitch</option>
                                        <option value="neon" <?php echo ($nameStyle['animation'] ?? 'none') === 'neon' ? 'selected' : ''; ?>>Flickering neon</option>
                                        <option value="bounce" <?php echo ($nameStyle['animation'] ?? 'none') === 'bounce' ? 'selected' : ''; ?>>Bouncing letters</option>
                                    </select></label>

                                <label class="profile-field field-name-glow"><span>Glow color</span><input type="color" name="profile_name_glow_color" id="nameGlowColorInput" value="<?php echo profile_h($nameStyle['glow_color'] ?? '#8b5cf6'); ?>"></label>
                            </div>

                            <?php
                            $dbTiltEnabled = (int)($profile['tilt_enabled'] ?? 1);
                            $dbTiltMax = (int)($profile['tilt_max'] ?? 15);
                            $dbTiltGlare = (float)($profile['tilt_glare'] ?? 0.0);
                            $dbTiltZoom = (float)($profile['tilt_zoom'] ?? 1.05);
                            $dbTiltSpeed = (int)($profile['tilt_speed'] ?? 400);

                            $selectedPreset = 'custom';
                            if ($dbTiltEnabled === 0) {
                                $selectedPreset = 'off';
                            } else {
                                if ($dbTiltMax === 5 && abs($dbTiltGlare - 0.08) < 0.01 && abs($dbTiltZoom - 1.01) < 0.01 && $dbTiltSpeed === 1000) {
                                    $selectedPreset = 'super_soft';
                                } else if ($dbTiltMax === 10 && abs($dbTiltGlare - 0.15) < 0.01 && abs($dbTiltZoom - 1.02) < 0.01 && $dbTiltSpeed === 800) {
                                    $selectedPreset = 'soft';
                                } else if ($dbTiltMax === 15 && abs($dbTiltGlare - 0.25) < 0.01 && abs($dbTiltZoom - 1.05) < 0.01 && $dbTiltSpeed === 600) {
                                    $selectedPreset = 'medium';
                                } else if ($dbTiltMax === 25 && abs($dbTiltGlare - 0.40) < 0.01 && abs($dbTiltZoom - 1.08) < 0.01 && $dbTiltSpeed === 400) {
                                    $selectedPreset = 'strong';
                                } else if ($dbTiltMax === 35 && abs($dbTiltGlare - 0.60) < 0.01 && abs($dbTiltZoom - 1.12) < 0.01 && $dbTiltSpeed === 200) {
                                    $selectedPreset = 'extreme';
                                } else if ($dbTiltMax === 15 && abs($dbTiltGlare - 0.0) < 0.01 && abs($dbTiltZoom - 1.05) < 0.01 && $dbTiltSpeed === 400) {
                                    $selectedPreset = 'medium'; // Match old default
                                }
                            }
                            ?>
                            <div class="bio-section-heading" style="margin-top: 1.8rem; border-top: 1px dashed rgba(255, 255, 255, 0.08); padding-top: 1.5rem;">
                                <div><span><i class="fa-solid fa-cube"></i> Tilt Card Effect</span>
                                    <p>Customize the 3D tilt effect on your profile card when hovered by the mouse.</p>
                                </div>
                            </div>
                            <div class="profile-field-grid two">
                                <label class="profile-field"><span>Tilt Level</span>
                                    <select name="tilt_preset" id="tiltPresetInput">
                                        <option value="off" <?php echo $selectedPreset === 'off' ? 'selected' : ''; ?>>Off (Disabled)</option>
                                        <option value="super_soft" <?php echo $selectedPreset === 'super_soft' ? 'selected' : ''; ?>>Super Soft</option>
                                        <option value="soft" <?php echo $selectedPreset === 'soft' ? 'selected' : ''; ?>>Soft</option>
                                        <option value="medium" <?php echo $selectedPreset === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="strong" <?php echo $selectedPreset === 'strong' ? 'selected' : ''; ?>>Strong</option>
                                        <option value="extreme" <?php echo $selectedPreset === 'extreme' ? 'selected' : ''; ?>>Extreme</option>
                                        <option value="custom" <?php echo $selectedPreset === 'custom' ? 'selected' : ''; ?>>Custom</option>
                                    </select>
                                </label>
                                <label class="profile-toggle-card profile-inline-toggle" style="margin-top: 1.5rem;">
                                    <input type="hidden" name="tilt_enabled" value="0">
                                    <input type="checkbox" name="tilt_enabled" id="tiltEnabledInput" value="1" <?php echo $dbTiltEnabled === 1 ? 'checked' : ''; ?>>
                                    <span>Enable card tilt</span>
                                </label>
                            </div>
                            <div id="tiltCustomControls" class="profile-field-grid two" style="display: <?php echo $selectedPreset === 'custom' ? 'grid' : 'none'; ?>; margin-top: 1rem;">
                                <label class="profile-field"><span>Max tilt angle (<span id="tiltMaxVal"><?php echo $dbTiltMax; ?></span>°)</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="tilt_max" id="tiltMaxInput" min="0" max="45" value="<?php echo $dbTiltMax; ?>" style="flex: 1;">
                                    </div>
                                </label>
                                <label class="profile-field"><span>Glare intensity (<span id="tiltGlareVal"><?php echo $dbTiltGlare; ?></span>)</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="tilt_glare" id="tiltGlareInput" min="0" max="1" step="0.05" value="<?php echo $dbTiltGlare; ?>" style="flex: 1;">
                                    </div>
                                </label>
                                <label class="profile-field"><span>Hover zoom (<span id="tiltZoomVal"><?php echo $dbTiltZoom; ?></span>x)</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="tilt_zoom" id="tiltZoomInput" min="1.0" max="1.3" step="0.01" value="<?php echo $dbTiltZoom; ?>" style="flex: 1;">
                                    </div>
                                </label>
                                <label class="profile-field"><span>Animation speed (<span id="tiltSpeedVal"><?php echo $dbTiltSpeed; ?></span>ms)</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="tilt_speed" id="tiltSpeedInput" min="100" max="2000" step="50" value="<?php echo $dbTiltSpeed; ?>" style="flex: 1;">
                                    </div>
                                </label>
                            </div>

                        </div>
                    </div>

                    <div class="profile-edit-section editor-card" data-edit-section="badges" data-editor-category="profile">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fa-solid fa-trophy"></i></span>
                                <div class="editor-card-text">
                                    <h3>Badges</h3>
                                    <p>Select which badges to show on profile and sort them</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Badges</span>
                                <span class="editor-card-chevron"><i class="fa-solid fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fa-solid fa-trophy"></i> Visible Badges</span>
                                    <?php if ($isPremium): ?>
                                        <p>Choose which badges to display on your profile (<strong>unlimited</strong> with your Premium plan) and sort them.</p>
                                    <?php else: ?>
                                        <p>Choose which badges to display on your profile (max 8 - <strong>unlimited</strong> with <a href="/en/shop.php" style="color: var(--accent); text-decoration: underline;">Premium</a>) and sort them.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="profile-sortable-list" id="badgeSortList" data-badges="<?php echo profile_h(json_encode($availableBadges)); ?>">
                                <!-- Populated via Javascript -->
                            </div>
                        </div>
                    </div>

                    <div class="profile-edit-section editor-card" data-edit-section="characters" data-editor-category="profile">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fa-solid fa-user-astronaut"></i></span>
                                <div class="editor-card-text">
                                    <h3>Favorite Characters</h3>
                                    <p>Showcase up to 12 inventory characters on your profile</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Inventory</span>
                                <span class="editor-card-chevron"><i class="fa-solid fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <?php if ($inventoryCharacters): ?>
                                <div class="profile-character-search-wrap editor-search-wrapper">
                                    <i class="fa-solid fa-search editor-search-icon"></i>
                                    <input
                                        type="text"
                                        id="characterSearchInput"
                                        class="profile-character-search"
                                        placeholder="Search character..."
                                        autocomplete="off">
                                </div>

                                <div class="profile-character-picker" id="characterPicker">
                                    <?php foreach ($inventoryCharacters as $char): ?>
                                        <?php
                                        $charImg       = profile_character_img_url($char);
                                        $rarityClass   = profile_character_rarity_class((string)($char['rarità'] ?? ''));
                                        $charQty       = (int)($char['quantità'] ?? 0);
                                        $rarityLabel   = ucfirst((string)($char['rarità'] ?? ''));
                                        ?>
                                        <label
                                            class="profile-character-choice rarity-<?php echo profile_h($rarityClass); ?>"
                                            data-char-name="<?php echo profile_h(strtolower($char['nome'])); ?>"
                                            title="<?php echo profile_h($char['nome']); ?>">
                                            <input
                                                type="checkbox"
                                                value="<?php echo (int)$char['id']; ?>"
                                                <?php echo (int)$char['selected'] === 1 ? 'checked' : ''; ?>>

                                            <?php if ($charImg): ?>
                                                <img src="<?php echo profile_h($charImg); ?>" alt="<?php echo profile_h($char['nome']); ?>" loading="lazy">
                                            <?php else: ?>
                                                <span class="profile-character-img-fallback-picker">
                                                    <i class="fa-solid fa-user-astronaut"></i>
                                                </span>
                                            <?php endif; ?>

                                            <div class="profile-character-choice-info">
                                                <strong><?php echo profile_h($char['nome']); ?></strong>
                                                <?php if ($charQty > 1): ?>
                                                    <small>×<?php echo $charQty; ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <p class="profile-character-hint">
                                    <i class="fa-solid fa-circle-info"></i>
                                    <?php
                                    $selectedCount = count(array_filter($inventoryCharacters, fn($c) => (int)$c['selected'] === 1));
                                    echo $selectedCount . '/12 selected.';
                                    ?>
                                </p>

                                <div class="profile-character-sort-section" style="margin-top: 1.5rem;">
                                    <strong style="display: block; margin-bottom: 0.5rem;"><i class="fa-solid fa-sort"></i> Selected Characters Sorting</strong>
                                    <p style="font-size: 0.82rem; color: var(--muted-2); margin-bottom: 0.75rem;">Drag and drop to rearrange order of appearance.</p>
                                    <div id="characterSortList" class="profile-character-sort-list">
                                        <!-- Populated dynamically via JS -->
                                    </div>
                                </div>

                            <?php else: ?>
                                <div class="bio-empty-state">
                                    <i class="fa-solid fa-user-astronaut"></i>
                                    <strong>No characters in inventory</strong>
                                    <p>Acquire characters from lootboxes to display them here.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Section: Browser Tab Title -->
                    <div class="profile-edit-section editor-card" data-edit-section="tab-title" data-editor-category="advanced">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fa-solid fa-window-maximize"></i></span>
                                <div class="editor-card-text">
                                    <h3>Browser Tab Title</h3>
                                    <p>Customize the title of the browser tab and add animated title effects</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Configured</span>
                                <span class="editor-card-chevron"><i class="fa-solid fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fa-solid fa-window-maximize"></i> Browser Tab Title</span>
                                    <p>Customize the title of the browser tab and add animated title effects.</p>
                                </div>
                            </div>
                            <div class="profile-field-grid two">
                                <label class="profile-field"><span>Custom tab title</span>
                                    <input type="text" name="profile_tab_title" id="profileTabTitleInput" maxlength="80" value="<?php echo profile_h($profile['profile_tab_title'] ?? ''); ?>" placeholder="Leave empty to use username">
                                </label>
                                <label class="profile-field"><span>Title animation</span>
                                    <select name="profile_tab_animation" id="profileTabAnimationInput">
                                        <option value="static" <?php echo ($profile['profile_tab_animation'] ?? 'static') === 'static' ? 'selected' : ''; ?>>Static</option>
                                        <option value="marquee" <?php echo ($profile['profile_tab_animation'] ?? 'static') === 'marquee' ? 'selected' : ''; ?>>Scrolling (Marquee)</option>
                                        <option value="bounce" <?php echo ($profile['profile_tab_animation'] ?? 'static') === 'bounce' ? 'selected' : ''; ?>>Bouncing (Bounce)</option>
                                        <option value="pulse" <?php echo ($profile['profile_tab_animation'] ?? 'static') === 'pulse' ? 'selected' : ''; ?>>Pulsing (Pulse)</option>
                                    </select>
                                </label>
                            </div>
                            <div class="profile-field-grid two" style="margin-top: 1rem;">
                                <label class="profile-field"><span>Animated / Alt text</span>
                                    <input type="text" name="profile_tab_animation_text" id="profileTabAnimationTextInput" maxlength="120" value="<?php echo profile_h($profile['profile_tab_animation_text'] ?? ''); ?>" placeholder="E.g. ★ Welcome ★">
                                    <small>Used as alternative text for marquee or pulsing animations.</small>
                                </label>
                                <label class="profile-field"><span>Animation speed (ms) (<span id="profileTabSpeedVal"><?php echo (int)($profile['profile_tab_animation_speed'] ?? 1000); ?></span>ms)</span>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="range" name="profile_tab_animation_speed" id="profileTabAnimationSpeedInput" min="200" max="5000" step="100" value="<?php echo (int)($profile['profile_tab_animation_speed'] ?? 1000); ?>" style="flex: 1;">
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="profile-edit-section editor-card" data-edit-section="visibility" data-editor-category="advanced">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fa-solid fa-eye"></i></span>
                                <div class="editor-card-text">
                                    <h3>Visibility and Sorting</h3>
                                    <p>Toggle visible sections, rearrange profile layout sections</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Options</span>
                                <span class="editor-card-chevron"><i class="fa-solid fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fa-solid fa-eye"></i> Public Sections Visibility</span>
                                    <p>Disable features you don't want to display. Empty sections stay hidden automatically.</p>
                                </div>
                            </div>
                            <div class="profile-toggle-grid">
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_socials" value="0"><input type="checkbox" name="profile_show_socials" value="1" <?php echo (int)($profile['profile_show_socials'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fa-brands fa-instagram"></i>Social</span></label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_links" value="0"><input type="checkbox" name="profile_show_links" value="1" <?php echo (int)($profile['profile_show_links'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fa-solid fa-link"></i>Links</span></label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_embeds" value="0"><input type="checkbox" name="profile_show_embeds" value="1" <?php echo (int)($profile['profile_show_embeds'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fa-solid fa-share-from-square"></i>Embeds</span></label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_projects" value="0"><input type="checkbox" name="profile_show_projects" value="1" <?php echo (int)($profile['profile_show_projects'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fa-solid fa-cubes"></i>Projects</span></label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_contents" value="0"><input type="checkbox" name="profile_show_contents" value="1" <?php echo (int)($profile['profile_show_contents'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fa-solid fa-play"></i>Edits & content</span></label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_blocks" value="0"><input type="checkbox" name="profile_show_blocks" value="1" <?php echo (int)($profile['profile_show_blocks'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fa-solid fa-wand-magic-sparkles"></i>Custom Blocks</span></label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_badges" value="0"><input type="checkbox" name="profile_show_badges" value="1" <?php echo (int)($profile['profile_show_badges'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fa-solid fa-trophy"></i>Badges</span></label>
                                <label class="profile-toggle-card">
                                    <input type="hidden" name="profile_show_characters" value="0">
                                    <input type="checkbox" name="profile_show_characters" value="1"
                                        <?php echo (int)($profile['profile_show_characters'] ?? 1) === 1 ? 'checked' : ''; ?>>
                                    <span><i class="fa-solid fa-user-astronaut"></i>Characters</span>
                                </label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_stats" value="0"><input type="checkbox" name="profile_show_stats" value="1" <?php echo (int)($profile['profile_show_stats'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fa-solid fa-chart-simple"></i>Stats</span></label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_activity" value="0"><input type="checkbox" name="profile_show_activity" value="1" <?php echo (int)($profile['profile_show_activity'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fa-solid fa-clock"></i>Activity</span></label>
                                <label class="profile-toggle-card"><input type="hidden" name="profile_show_discord" value="0"><input type="checkbox" name="profile_show_discord" value="1" <?php echo (int)($profile['profile_show_discord'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fa-brands fa-discord"></i>Discord</span></label>
                                <label class="profile-toggle-card" id="hideMetaContainer">
                                    <input type="hidden" name="profile_hide_meta" value="0">
                                    <input type="checkbox" name="profile_hide_meta" value="1" <?php echo (int)($profile['profile_hide_meta'] ?? 0) === 1 ? 'checked' : ''; ?> id="hideMetaInput">
                                    <span><i class="fa-solid fa-eye-slash"></i>Hide info under profile <i class="fa-solid fa-crown premium-lock-icon" style="margin-left: auto; <?php echo ((int)($profile['is_premium'] ?? 0) === 1) ? 'display: none;' : ''; ?>"></i></span>
                                </label>
                            </div>

                            <div style="margin-top: 1.25rem; width: 100%; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <label class="profile-field" style="display: flex; flex-direction: column; gap: 0.4rem; width: 100%;">
                                    <span style="font-size: 0.82rem; font-weight: 600; color: var(--muted);"><i class="fa-solid fa-trophy"></i> Badges Display Mode</span>
                                    <select name="profile_badges_display" id="badgesDisplayInput" class="profile-select-menu" style="width: 100%; max-width: 100%;">
                                        <option value="both" <?php echo ($profile['profile_badges_display'] ?? 'both') === 'both' ? 'selected' : ''; ?>>Show in both (profile card and badges tab)</option>
                                        <option value="card_only" <?php echo ($profile['profile_badges_display'] ?? 'both') === 'card_only' ? 'selected' : ''; ?>>Show only on profile card (main container)</option>
                                        <option value="tab_only" <?php echo ($profile['profile_badges_display'] ?? 'both') === 'tab_only' ? 'selected' : ''; ?>>Show only inside badges tab</option>
                                        <option value="none" <?php echo ($profile['profile_badges_display'] ?? 'both') === 'none' ? 'selected' : ''; ?>>Hide badges completely</option>
                                    </select>
                                </label>
                                <label class="profile-field" style="display: flex; flex-direction: column; gap: 0.4rem; width: 100%;">
                                    <span style="font-size: 0.82rem; font-weight: 600; color: var(--muted);"><i class="fa-solid fa-location-arrow"></i> Mini-badges Position</span>
                                    <select name="profile_badges_position" id="badgesPositionInput" class="profile-select-menu" style="width: 100%; max-width: 100%;">
                                        <option value="below_bio" <?php echo ($profile['profile_badges_position'] ?? 'below_bio') === 'below_bio' ? 'selected' : ''; ?>>Below bio</option>
                                        <option value="below_username" <?php echo ($profile['profile_badges_position'] ?? 'below_bio') === 'below_username' ? 'selected' : ''; ?>>Below username</option>
                                        <option value="right_of_name" <?php echo ($profile['profile_badges_position'] ?? 'below_bio') === 'right_of_name' ? 'selected' : ''; ?>>Right of name</option>
                                    </select>
                                </label>
                            </div>

                            <div class="bio-section-heading" style="margin-top: 1.8rem;">
                                <div><span><i class="fa-solid fa-sort"></i> Sections Sorting</span>
                                    <p>Drag and drop sections to rearrange their vertical order on your public profile.</p>
                                </div>
                            </div>
                            <input type="hidden" name="profile_sections_order" id="sectionsOrderJson" value="<?php echo profile_h($profile['profile_sections_order'] ?? 'links,embeds,stats,projects,blocks,contents,characters,badges,activity'); ?>">
                            <input type="hidden" name="profile_sections_config" id="sectionsConfigJson" value="<?php echo profile_h($profile['profile_sections_config'] ?? '{}'); ?>">
                            <div id="sectionsSortList" class="profile-sections-sort-list">
                                <!-- Populated via Javascript -->
                            </div>
                        </div>
                    </div>

                    <div class="profile-edit-section editor-card" data-edit-section="presets" data-editor-category="advanced">
                        <div class="editor-card-header">
                            <div class="editor-card-info">
                                <span class="editor-card-icon"><i class="fa-solid fa-magic"></i></span>
                                <div class="editor-card-text">
                                    <h3>Profile Presets</h3>
                                    <p>Save or load complete configurations to swap profile designs instantly</p>
                                </div>
                            </div>
                            <div class="editor-card-actions">
                                <span class="editor-status-badge is-active">Presets</span>
                                <span class="editor-card-chevron"><i class="fa-solid fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="editor-card-body">
                            <div class="bio-section-heading">
                                <div><span><i class="fa-solid fa-magic"></i> Profile Presets</span>
                                    <p>Save and load complete configurations of your profile (max 3 presets).</p>
                                </div>
                                <button type="button" class="bio-button" id="saveNewPresetBtn"><i class="fa-solid fa-plus"></i> Save Current Preset</button>
                            </div>
                            <div class="presets-list-container" id="presetsListContainer">
                                <!-- Presets loaded via AJAX -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resize Handle -->
            <div class="editor-resize-handle" id="editorResizeHandle"></div>

            <div class="editor-preview-pane">
                <div class="preview-toolbar">
                    <span class="preview-status">Real-time Live Preview</span>
                    <div class="viewport-buttons">
                        <button type="button" class="btn-viewport is-active" data-viewport="desktop"><i class="fa-solid fa-desktop"></i> Desktop</button>
                        <button type="button" class="btn-viewport" data-viewport="mobile"><i class="fa-solid fa-mobile-screen-button"></i> Mobile</button>
                    </div>
                    <a href="/u/<?php echo rawurlencode(strtolower($profile['username'])); ?>" target="_blank" class="btn-view-live" title="Open in new tab"><i class="fa-solid fa-up-right-from-square"></i> Go to profile</a>
                </div>
                <div class="preview-canvas">
                    <div class="device-frame desktop" id="previewDeviceFrame">
                        <iframe id="profilePreviewIframe" src="../profile.php?id=<?php echo (int)$profile['id']; ?>&preview_mode=1"></iframe>
                    </div>
                </div>
            </div>

            <input type="hidden" name="csrf_token" value="<?php echo profile_h($csrf); ?>">
            <input type="hidden" name="target_user_id" value="<?php echo (int)$targetUserId; ?>">
            <input type="hidden" name="socials_json" id="socialsJson">
            <input type="hidden" name="links_json" id="linksJson">
            <input type="hidden" name="projects_json" id="projectsJson">
            <input type="hidden" name="contents_json" id="contentsJson">
            <input type="hidden" name="blocks_json" id="blocksJson">
            <input type="hidden" name="badges_json" id="badgesJson">
            <input type="hidden" name="characters_json" id="charactersJson">
            <input type="hidden" name="embeds_json" id="embedsJson">
            <input type="hidden" name="profile_tags_json" id="profileTagsJson">
        </form>
    </main>

    <div class="bio-toast" id="bioToast" role="status" aria-live="polite"></div>

    <?php profile_json_script('initialSocialsData', $socials); ?>
    <?php
    $tags = json_decode($profile['profile_tags_json'] ?? '[]', true) ?: [];
    profile_json_script('initialTagsData', $tags);
    ?>
    <?php profile_json_script('initialLinksData', $links); ?>
    <?php profile_json_script('initialProjectsData', $projects); ?>
    <?php profile_json_script('initialContentsData', $contents); ?>
    <?php profile_json_script('initialBlocksData', $blocks); ?>
    <?php profile_json_script('initialEmbedsData', $embeds); ?>
    <?php
    $displayedChars = profile_list_displayed_characters($mysqli, $targetUserId);
    $displayedCharIds = array_map(fn($c) => (int)$c['id'], $displayedChars);
    profile_json_script('initialCharactersData', $displayedCharIds);
    ?>

    <?php // if (file_exists(__DIR__ . '/../includes/footer-en.php')) include __DIR__ . '/../includes/footer-en.php'; 
    ?>


    <div class="editor-loading-overlay" id="editorLoadingOverlay">
        <div class="editor-loading-spinner"></div>
        <div class="editor-loading-text" id="editorLoadingText">Saving profile...</div>
        <div class="editor-loading-subtext" id="editorLoadingSubtext">Uploading media files, please do not close this page.</div>
    </div>
    <!-- Bootstrap JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
