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

$socials = profile_list_socials($mysqli, $targetUserId, false);
$links = profile_list_links($mysqli, $targetUserId, false);
$projects = profile_list_projects($mysqli, $targetUserId, false);
$contents = profile_list_contents($mysqli, $targetUserId, false);
$blocks = function_exists('profile_list_blocks') ? profile_list_blocks($mysqli, $targetUserId, false) : [];
$badges = profile_list_unlocked_badges($mysqli, $targetUserId);
$csrf = profile_csrf_token();
$profileFlashSuccess = $_SESSION['profile_flash_success'] ?? '';
$profileFlashError = $_SESSION['profile_flash_error'] ?? '';
unset($_SESSION['profile_flash_success'], $_SESSION['profile_flash_error']);
$accent = profile_normalize_hex_color($profile['accent_color'] ?? '#0f5bff');
$secondaryColor = profile_normalize_hex_color($profile['profile_secondary_color'] ?? $accent);
$cardColor = profile_optional_hex_color($profile['profile_card_color'] ?? '') ?: '';
$textColor = profile_optional_hex_color($profile['profile_text_color'] ?? '') ?: '';
$linkStyle = profile_allowed_value((string)($profile['profile_link_style'] ?? 'glass'), ['glass', 'solid', 'outline', 'neon'], 'glass');
$buttonShape = profile_allowed_value((string)($profile['profile_button_shape'] ?? 'pill'), ['pill', 'rounded', 'sharp'], 'pill');
$theme = profile_allowed_value((string)($profile['profile_theme'] ?? 'dark'), ['dark', 'light', 'auto'], 'dark');
if ($theme === 'auto') $theme = 'dark';
$displayName = profile_display_name($profile);
$discordConnected = !empty($profile['discord_id']) && !empty($profile['discord_username']);
$discordAvatarUrl = $discordConnected ? profile_discord_avatar_url((string)$profile['discord_id'], $profile['discord_avatar'] ?? null, 128) : null;
$discordDisplayName = trim((string)($profile['discord_global_name'] ?? '')) ?: trim((string)($profile['discord_username'] ?? ''));
$connectDiscordUrl = '../auth/discord_connect.php' . (profile_is_staff() && $targetUserId !== $currentUserId ? '?target_user_id=' . (int)$targetUserId : '');
$backgroundUrl = !empty($profile['profile_banner_type']) ? '../includes/get_profile_banner.php?id=' . (int)$profile['id'] : '../vid/Shorekeeper Wallpaper 4K Loop.mp4';
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
    <link rel="stylesheet" href="/assets/css/profile.css?v=3.0.3">
    <script src="/assets/js/profile.js?v=3.0.3" defer></script>
    <script src="/assets/js/edit-profile-en.js?v=3.0.4" defer></script>
</head>

<body class="bio-v2-body profile-editor-shell" data-theme="<?php echo profile_h($theme); ?>" data-accent="<?php echo profile_h($accent); ?>" data-profile-link-style="<?php echo profile_h($linkStyle); ?>" data-profile-button-shape="<?php echo profile_h($buttonShape); ?>" data-profile-url="https://cripsum.com/u/<?php echo rawurlencode(strtolower($profile['username'])); ?>" style="--profile-ring: <?php echo profile_h(profile_normalize_hex_color($profile['avatar_ring_color'] ?: $accent)); ?>; --accent-2: <?php echo profile_h($secondaryColor); ?>; --profile-card-color: <?php echo profile_h($cardColor ?: 'var(--card)'); ?>; --profile-text-color: <?php echo profile_h($textColor ?: 'var(--text)'); ?>;">
    <?php
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
                <source src="/vid/Shorekeeper Wallpaper 4K Loop.mp4" type="video/mp4">
            </video>
        <?php endif; ?>
        <div class="bio-background__overlay"></div>
        <div class="bio-orb bio-orb--one"></div>
        <div class="bio-orb bio-orb--two"></div>
        <div class="bio-grid-glow"></div>
    </div>

    <main class="profile-edit-layout">
        <?php if ($profileFlashSuccess || $profileFlashError): ?>
            <div class="bio-card profile-flash <?php echo $profileFlashError ? 'is-error' : 'is-success'; ?>">
                <i class="<?php echo $profileFlashError ? 'fas fa-triangle-exclamation' : 'fas fa-check'; ?>"></i>
                <span><?php echo profile_h($profileFlashError ?: $profileFlashSuccess); ?></span>
            </div>
        <?php endif; ?>

        <header class="bio-card profile-edit-hero js-reveal">
            <div>
                <span class="bio-pill">Profile editor</span>
                <h1>Edit profile</h1>
            </div>
            <div class="profile-edit-hero-actions">
                <a class="bio-button" href="/u/<?php echo rawurlencode(strtolower($profile['username'])); ?>"><i class="fas fa-eye"></i>View profile</a>
                <button class="bio-icon-button js-theme-toggle" type="button" aria-label="Change theme"><i class="fas fa-moon"></i></button>
            </div>
        </header>

        <form id="profileEditForm" class="profile-edit-grid" method="post" enctype="multipart/form-data" action="../api/update_profile.php">
            <input type="hidden" name="csrf_token" value="<?php echo profile_h($csrf); ?>">
            <input type="hidden" name="target_user_id" value="<?php echo (int)$targetUserId; ?>">
            <input type="hidden" name="socials_json" id="socialsJson">
            <input type="hidden" name="links_json" id="linksJson">
            <input type="hidden" name="projects_json" id="projectsJson">
            <input type="hidden" name="contents_json" id="contentsJson">
            <input type="hidden" name="blocks_json" id="blocksJson">
            <input type="hidden" name="badges_json" id="badgesJson">

            <section class="bio-card profile-edit-panel js-reveal">
                <div class="profile-editor-tabs" role="tablist">
                    <button type="button" class="is-active" data-edit-tab="identity">Identity</button>
                    <button type="button" data-edit-tab="links">Links</button>
                    <button type="button" data-edit-tab="projects">Projects</button>
                    <button type="button" data-edit-tab="content">Contents</button>
                    <button type="button" data-edit-tab="custom">Custom</button>
                    <button type="button" data-edit-tab="effects">Effects</button>
                    <button type="button" data-edit-tab="badges">Badges</button>
                    <button type="button" data-edit-tab="visibility">Visibility</button>
                </div>

                <div class="profile-edit-section is-active" data-edit-section="identity">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-id-card"></i> Identity</span>
                        </div>
                    </div>

                    <div class="profile-field-grid two">
                        <label class="profile-field"><span>Display name</span><input type="text" name="display_name" id="displayNameInput" maxlength="40" value="<?php echo profile_h($profile['display_name'] ?? ''); ?>" placeholder="E.g. Godo"></label>
                        <label class="profile-field"><span>Username</span><input type="text" name="username" id="usernameInput" maxlength="20" required value="<?php echo profile_h($profile['username']); ?>" placeholder="username"><small>3-20 characters. Letters, numbers and underscores.</small></label>
                    </div>

                    <label class="profile-field"><span>Bio</span><textarea name="bio" id="bioInput" maxlength="280" rows="5" placeholder="Write something about yourself..."><?php echo profile_h($profile['bio'] ?? ''); ?></textarea><small><span id="bioCounter">0</span>/280</small></label>

                    <div class="profile-field-grid two">
                        <label class="profile-field"><span>Short status</span><input type="text" name="profile_status" id="statusInput" maxlength="60" value="<?php echo profile_h($profile['profile_status'] ?? ''); ?>" placeholder="editing, gaming, busy..."><small>Appears next to your name when you are not online.</small></label>
                        <label class="profile-field"><span>Discord user ID</span><input type="text" name="discord_id" id="discordIdInput" maxlength="25" value="<?php echo profile_h($profile['discord_id'] ?? ''); ?>" placeholder="E.g. 8239582304530540"><small>Only needed for Lanyard/Rich Presence.</small></label>
                    </div>

                    <div class="profile-discord-connect-card">
                        <div class="profile-discord-connect-main">
                            <?php if ($discordConnected): ?>
                                <?php if ($discordAvatarUrl): ?><img src="<?php echo profile_h($discordAvatarUrl); ?>" alt="" loading="lazy"><?php else: ?><span class="profile-discord-avatar-fallback"><i class="fab fa-discord"></i></span><?php endif; ?>
                                <div>
                                    <strong><?php echo profile_h($discordDisplayName ?: $profile['discord_username']); ?></strong>
                                    <small>@<?php echo profile_h($profile['discord_username']); ?> · ID <?php echo profile_h($profile['discord_id']); ?></small>
                                </div>
                            <?php else: ?>
                                <span class="profile-discord-avatar-fallback"><i class="fab fa-discord"></i></span>
                                <div>
                                    <strong>Discord not connected</strong>
                                    <small>Connect Discord to save your ID, username and avatar.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="profile-discord-actions">
                            <a class="bio-button bio-button--primary" href="<?php echo profile_h($connectDiscordUrl); ?>"><i class="fab fa-discord"></i><?php echo $discordConnected ? 'Reconnect' : 'Connect Discord'; ?></a>
                            <?php if ($discordConnected): ?>
                                <button class="bio-button profile-discord-disconnect" type="submit" form="disconnectDiscordForm"><i class="fas fa-link-slash"></i>Disconnect</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="profile-discord-note">
                        <i class="fas fa-circle-info"></i>
                        <span>Discord login only saves your ID, username and avatar. To enable Rich Presence, just join the <a href="https://discord.com/invite/lanyard" target="_blank" rel="noopener noreferrer">Lanyard Discord server</a>.</span>
                    </div>

                    <div class="profile-field-grid two" style="margin-top: 2%;">
                        <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="discord_use_display_name" value="0"><input type="checkbox" name="discord_use_display_name" id="discordUseNameInput" value="1" <?php echo (int)($profile['discord_use_display_name'] ?? 0) === 1 ? 'checked' : ''; ?> <?php echo !$discordConnected ? 'disabled' : ''; ?>><span><i class="fab fa-discord"></i>Use Discord name</span></label>
                        <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="discord_use_avatar" value="0"><input type="checkbox" name="discord_use_avatar" id="discordUseAvatarInput" value="1" <?php echo (int)($profile['discord_use_avatar'] ?? 0) === 1 ? 'checked' : ''; ?> <?php echo !$discordConnected ? 'disabled' : ''; ?>><span><i class="fab fa-discord"></i>Use Discord avatar</span></label>
                    </div>

                    <div class="profile-field-grid two">
                        <label class="profile-field"><span>Avatar</span><input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp,image/gif"><small>Max 2MB. JPG, PNG, WEBP or GIF.</small></label>
                        <label class="profile-field"><span>Profile background</span><input type="file" name="banner" id="bannerInput" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm"><small>Max 12MB. Photo, GIF or video. Changes the page background.</small></label>
                    </div>

                    <div class="profile-field-grid three">
                        <label class="profile-field"><span>Primary accent</span><input type="color" name="accent_color" id="accentInput" value="<?php echo profile_h($accent); ?>"></label>
                        <label class="profile-field"><span>Secondary accent</span><input type="color" name="profile_secondary_color" id="secondaryColorInput" value="<?php echo profile_h($secondaryColor); ?>"></label>
                        <label class="profile-field"><span>Theme</span><select name="profile_theme" id="themeInput"><?php foreach (['dark' => 'Dark', 'light' => 'Light', 'auto' => 'Auto'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_theme'] ?? 'dark') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                        <label class="profile-field"><span>Layout</span><select name="profile_layout" id="layoutInput"><?php foreach (['standard' => 'Standard', 'compact' => 'Compact', 'showcase' => 'Showcase'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_layout'] ?? 'standard') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                        <label class="profile-field"><span>Card color</span><input type="color" name="profile_card_color" id="cardColorInput" value="<?php echo profile_h($cardColor ?: '#080c18'); ?>"><small>Leave default for classic glass style.</small></label>
                        <label class="profile-field"><span>Text color</span><input type="color" name="profile_text_color" id="textColorInput" value="<?php echo profile_h($textColor ?: ($theme === 'light' ? '#111827' : '#f7f8ff')); ?>"></label>
                        <label class="profile-field"><span>Link style</span><select name="profile_link_style" id="linkStyleInput"><?php foreach (['glass' => 'Glass', 'solid' => 'Solid', 'outline' => 'Outline', 'neon' => 'Neon'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo $linkStyle === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                        <label class="profile-field"><span>Button shape</span><select name="profile_button_shape" id="buttonShapeInput"><?php foreach (['pill' => 'Pill', 'rounded' => 'Rounded', 'sharp' => 'Sharp'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo $buttonShape === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
                    </div>

                    <label class="profile-field"><span>Profile privacy</span><select name="profile_visibility" id="visibilityInput"><?php foreach (['public' => 'Public', 'logged_in' => 'Logged-in users only', 'private' => 'Private'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_visibility'] ?? 'public') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>

                    <div class="bio-section-heading profile-mt">
                        <div><span><i class="fas fa-music"></i> Profile audio</span>
                            <p>Upload an MP3 or use a public audio URL.</p>
                        </div>
                    </div>
                    <div class="profile-field-grid two">
                        <label class="profile-field"><span>Upload MP3</span><input type="file" name="profile_music_file" id="musicFileInput" accept="audio/mpeg,audio/mp3,.mp3"><small>Max 12MB. if uploading an MP3, it will replace the URL.</small></label>
                        <label class="profile-field"><span>Song URL</span><input type="url" name="profile_music_url" id="musicUrlInput" maxlength="255" value="<?php echo profile_h($profile['profile_music_url'] ?? ''); ?>" placeholder="https://.../audio.mp3"><small>Use only if you are not uploading a file.</small></label>
                        <label class="profile-field"><span>Song title</span><input type="text" name="profile_music_title" id="musicTitleInput" maxlength="80" value="<?php echo profile_h($profile['profile_music_title'] ?? ''); ?>" placeholder="Song name"></label>
                        <label class="profile-field"><span>Artist / note</span><input type="text" name="profile_music_artist" id="musicArtistInput" maxlength="80" value="<?php echo profile_h($profile['profile_music_artist'] ?? ''); ?>" placeholder="Artist or source"></label>
                        <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="profile_show_audio_player" value="0"><input type="checkbox" name="profile_show_audio_player" value="1" <?php echo (int)($profile['profile_show_audio_player'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-sliders"></i>Show player</span></label>
                        <?php if (!empty($profile['profile_music_mime'])): ?>
                            <label class="profile-toggle-card profile-inline-toggle"><input type="checkbox" name="remove_profile_music_upload" value="1"><span><i class="fas fa-trash"></i>Remove uploaded MP3</span></label>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-edit-section" data-edit-section="links">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-link"></i> Social</span>
                            <p>Quick icons below the profile.</p>
                        </div><button type="button" class="bio-button" data-add-row="socials">+ Social</button>
                    </div>
                    <div class="profile-repeater" id="socialsRepeater"></div>

                    <div class="bio-section-heading profile-mt">
                        <div><span><i class="fas fa-star"></i> Custom links</span>
                            <p>Large featured cards.</p>
                        </div><button type="button" class="bio-button" data-add-row="links">+ Link</button>
                    </div>
                    <div class="profile-repeater" id="linksRepeater"></div>
                </div>

                <div class="profile-edit-section" data-edit-section="projects">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-cubes"></i> Projects</span>
                        </div><button type="button" class="bio-button" data-add-row="projects">+ Project</button>
                    </div>
                    <div class="profile-repeater" id="projectsRepeater"></div>
                </div>

                <div class="profile-edit-section" data-edit-section="content">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-play-circle"></i> Contents</span>
                            <p>Edits, videos, pages and showcase.</p>
                        </div><button type="button" class="bio-button" data-add-row="contents">+ Content</button>
                    </div>
                    <div class="profile-repeater" id="contentsRepeater"></div>
                </div>

                <div class="profile-edit-section" data-edit-section="custom">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-wand-magic-sparkles"></i> Custom section</span>
                            <p>Texts, images, GIFs or videos.</p>
                        </div><button type="button" class="bio-button" data-add-row="blocks">+ Section</button>
                    </div>
                    <div class="profile-repeater" id="blocksRepeater"></div>
                </div>

                <div class="profile-edit-section" data-edit-section="effects">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-sparkles"></i> Effects</span>
                            <p>Subtle effects on the page, mouse and profile picture.</p>
                        </div>
                    </div>
                    <div class="profile-field-grid three">
                        <label class="profile-field"><span>Page effect</span><select name="profile_effect" id="profileEffectInput">
                                <?php foreach (
                                    [
                                        'none' => 'None',
                                        'cursor_glow' => 'Mouse glow',
                                        'soft_particles' => 'Soft particles',
                                        'scanlines' => 'Scanlines soft',
                                        'ambient' => 'Ambient glow',
                                        'aurora' => 'Aurora',
                                        'gradient_waves' => 'Gradient waves',
                                        'stars' => 'Soft stars',
                                        'spotlight' => 'Spotlight mouse',
                                        'digital_noise' => 'Digital noise',
                                        'glass_rain' => 'Glass rain'
                                    ] as $value => $label
                                ): ?><option value="<?php echo $value; ?>" <?php echo ($profile['profile_effect'] ?? 'none') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?>
                            </select></label>
                        <label class="profile-field"><span>PFP ring effect</span><select name="avatar_ring_style" id="ringStyleInput">
                                <?php foreach (
                                    [
                                        'spin' => 'Rotation',
                                        'pulse' => 'Pulse',
                                        'orbit' => 'Orbit',
                                        'glow' => 'Glow',
                                        'dual' => 'Dual spin',
                                        'rainbow' => 'Rainbow',
                                        'halo' => 'Halo soft',
                                        'neon' => 'Neon',
                                        'spark' => 'Spark',
                                        'glitch' => 'Light glitch',
                                        'none' => 'None'
                                    ] as $value => $label
                                ): ?><option value="<?php echo $value; ?>" <?php echo ($profile['avatar_ring_style'] ?? 'spin') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?>
                            </select></label>
                        <label class="profile-field"><span>PFP ring color</span><input type="color" name="avatar_ring_color" id="ringColorInput" value="<?php echo profile_h(profile_normalize_hex_color($profile['avatar_ring_color'] ?: $accent)); ?>"></label>
                    </div>
                    <div class="profile-effect-hint">
                        <span><i class="fas fa-wand-magic-sparkles"></i> Effects are purely aesthetic and lightweight.</span>
                        <span><i class="fas fa-circle"></i> The ring color is now applied in the public profile too.</span>
                    </div>
                    <label class="profile-toggle-card profile-inline-toggle"><input type="hidden" name="avatar_ring_enabled" value="0"><input type="checkbox" name="avatar_ring_enabled" id="ringEnabledInput" value="1" <?php echo (int)($profile['avatar_ring_enabled'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-circle-notch"></i>Show ring around profile picture</span></label>
                </div>

                <div class="profile-edit-section" data-edit-section="badges">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-trophy"></i> Visible badges</span>
                            <p>You can display up to 8.</p>
                        </div>
                    </div>
                    <?php if ($badges): ?>
                        <div class="profile-badge-picker" id="badgePicker">
                            <?php foreach ($badges as $badge): ?>
                                <label class="profile-badge-choice">
                                    <input type="checkbox" value="<?php echo (int)$badge['id']; ?>" <?php echo (int)$badge['selected'] === 1 ? 'checked' : ''; ?>>
                                    <?php if (!empty($badge['img_url'])): ?><img src="/img/<?php echo profile_h($badge['img_url']); ?>" alt=""><?php else: ?><span>✦</span><?php endif; ?>
                                    <strong><?php echo profile_h($badge['nome']); ?></strong>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="bio-empty-state"><i class="fas fa-medal"></i><strong>No badges unlocked</strong>
                            <p>Once you unlock achievements, you can display them here.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="profile-edit-section" data-edit-section="visibility">
                    <div class="bio-section-heading">
                        <div><span><i class="fas fa-eye"></i> Public sections</span>
                            <p>Turn off what you don't want to show. Empty sections stay hidden regardless.</p>
                        </div>
                    </div>
                    <div class="profile-toggle-grid">
                        <label class="profile-toggle-card"><input type="hidden" name="profile_show_socials" value="0"><input type="checkbox" name="profile_show_socials" value="1" <?php echo (int)($profile['profile_show_socials'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fab fa-instagram"></i>Social</span></label>
                        <label class="profile-toggle-card"><input type="hidden" name="profile_show_links" value="0"><input type="checkbox" name="profile_show_links" value="1" <?php echo (int)($profile['profile_show_links'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-link"></i>Link</span></label>
                        <label class="profile-toggle-card"><input type="hidden" name="profile_show_projects" value="0"><input type="checkbox" name="profile_show_projects" value="1" <?php echo (int)($profile['profile_show_projects'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-cubes"></i>Projects</span></label>
                        <label class="profile-toggle-card"><input type="hidden" name="profile_show_contents" value="0"><input type="checkbox" name="profile_show_contents" value="1" <?php echo (int)($profile['profile_show_contents'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-play"></i>Edits & contents</span></label>
                        <label class="profile-toggle-card"><input type="hidden" name="profile_show_badges" value="0"><input type="checkbox" name="profile_show_badges" value="1" <?php echo (int)($profile['profile_show_badges'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-trophy"></i>Badge</span></label>
                        <label class="profile-toggle-card"><input type="hidden" name="profile_show_stats" value="0"><input type="checkbox" name="profile_show_stats" value="1" <?php echo (int)($profile['profile_show_stats'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-chart-simple"></i>Statistics</span></label>
                        <label class="profile-toggle-card"><input type="hidden" name="profile_show_activity" value="0"><input type="checkbox" name="profile_show_activity" value="1" <?php echo (int)($profile['profile_show_activity'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fas fa-clock"></i>Activity</span></label>
                        <label class="profile-toggle-card"><input type="hidden" name="profile_show_discord" value="0"><input type="checkbox" name="profile_show_discord" value="1" <?php echo (int)($profile['profile_show_discord'] ?? 1) === 1 ? 'checked' : ''; ?>><span><i class="fab fa-discord"></i>Discord</span></label>
                    </div>
                </div>
                <div class="profile-editor-footer">
                    <button type="submit" class="bio-button bio-button--primary" id="saveProfileButton"><i class="fas fa-save"></i>Save profile</button>
                    <a class="bio-button" href="/u/<?php echo rawurlencode(strtolower($profile['username'])); ?>">Cancel</a>
                </div>
            </section>

            <aside class="bio-hero bio-card profile-preview-card js-tilt-card js-reveal">
                <span class="bio-pill mb-2">Profile preview</span>
                <!-- <div class="profile-background-note"><i class="fas fa-image"></i><span>Lo sfondo scelto appare dietro tutta la pagina, non sopra la foto profilo.</span></div> -->
                <div class="bio-avatar-wrap profile-preview-avatar-ring" id="previewAvatarWrap">
                    <div class="bio-avatar-ring" id="previewAvatarRing"></div><img class="bio-avatar" id="previewAvatar" src="<?php echo profile_h(profile_avatar_url($profile, 256)); ?>" alt="">
                </div>
                <div class="bio-name-block">
                    <h1 id="previewName"><?php echo profile_h($displayName); ?></h1>
                    <p class="bio-username" id="previewUsername">@<?php echo profile_h($profile['username']); ?></p>
                </div>
                <p class="bio-tagline" id="previewBio"><?php echo profile_h($profile['bio'] ?: 'Your bio will appear here.'); ?></p>
                <div class="bio-badges"><span class="bio-badge" id="previewStatusBadge"><i class="fas fa-signal"></i>Status</span><span class="bio-badge"><i class="fas fa-link"></i>Link</span><span class="bio-badge"><i class="fas fa-trophy"></i>Badge</span></div>
                <!-- <p class="bio-description" id="previewExtra">Audio, effetti e blocchi custom appaiono solo se li compili.</p> -->
            </aside>
        </form>
    </main>

    <div class="bio-toast" id="bioToast" role="status" aria-live="polite"></div>

    <?php profile_json_script('initialSocialsData', $socials); ?>
    <?php profile_json_script('initialLinksData', $links); ?>
    <?php profile_json_script('initialProjectsData', $projects); ?>
    <?php profile_json_script('initialContentsData', $contents); ?>
    <?php profile_json_script('initialBlocksData', $blocks); ?>

    <?php if (file_exists(__DIR__ . '/../includes/footer-en.php')) include __DIR__ . '/../includes/footer-en.php'; ?>
</body>

</html>