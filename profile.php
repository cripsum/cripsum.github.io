<?php
require_once __DIR__ . '/config/session_init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/profile_helpers.php';
require_once __DIR__ . '/includes/cripsum_og.php';
require_once __DIR__ . '/includes/mission_tracker.php';

checkBan($mysqli);

$isLoggedIn = isLoggedIn();
$currentUserId = profile_current_user_id();
$identifier = profile_get_identifier();

if (!$identifier) {
    if ($isLoggedIn && !empty($_SESSION['username'])) {
        header('Location: /u/' . rawurlencode(strtolower($_SESSION['username'])));
        exit;
    }
    $profile = null;
} else {
    $profile = profile_get_public_profile($mysqli, $identifier);
}

$isNotFound = !$profile;
$isOwnProfile = false;
$canEdit = false;
$isPrivateBlocked = false;
$isLoginBlocked = false;
$socials = $links = $projects = $contents = $blocks = $badges = $activity = [];
$isOnline = false;
$lastSeen = null;

if ($profile) {
    $profileId = (int)$profile['id'];
    $isOwnProfile = $currentUserId === $profileId;
    $canEdit = profile_can_edit($profileId);

    if (($profile['profile_visibility'] ?? 'public') === 'private' && !$canEdit) {
        $isPrivateBlocked = true;
    } elseif (($profile['profile_visibility'] ?? 'public') === 'logged_in' && !$isLoggedIn) {
        $isLoginBlocked = true;
    } else {
        profile_increment_views($mysqli, $profileId);

        // ── MISSION TRACKING ─────────────────────────────────────────────
        // Traccia solo se: utente loggato + sta vedendo il profilo di un altro.
        // Non tracciare il proprio profilo (evita farming auto-visita).
        if ($isLoggedIn && !$isOwnProfile && $currentUserId > 0) {
            try {
                trackMissionProgress($mysqli, $currentUserId, 'visit_profile');
            } catch (Throwable $trackErr) {
                error_log('[MissionTracking profile.php] ' . $trackErr->getMessage());
            }
        }
        // ── /MISSION TRACKING ────────────────────────────────────────────
        $socials = profile_list_socials($mysqli, $profileId, true);
        $links = profile_list_links($mysqli, $profileId, true);
        $projects = profile_list_projects($mysqli, $profileId, true);
        $contents = profile_list_contents($mysqli, $profileId, true);
        $blocks = function_exists('profile_list_blocks') ? profile_list_blocks($mysqli, $profileId, true) : [];
        $badges = profile_list_visible_badges($mysqli, $profileId);
        $activity = profile_recent_activity($mysqli, $profileId);
        $characters = function_exists('profile_list_displayed_characters')
            ? profile_list_displayed_characters($mysqli, $profileId)
            : [];

        if (function_exists('isUserOnline')) {
            $isOnline = isUserOnline($mysqli, $profileId);
        }
        $lastSeen = $profile['ultimo_accesso'] ?? null;
    }
}

function profile_flag(array $profile, string $key, bool $default = true): bool
{
    if (!array_key_exists($key, $profile)) return $default;
    return (int)$profile[$key] === 1;
}

function profile_state_page(string $code, string $title, string $text, ?string $buttonText = null, ?string $buttonUrl = null): void
{
?>
    <main class="bio-page bio-state-page profile-smart-page profile-smart-page--single">
        <section class="bio-card bio-state-card js-reveal">
            <span class="bio-pill"><?php echo profile_h($code); ?></span>
            <h1><?php echo profile_h($title); ?></h1>
            <p><?php echo profile_h($text); ?></p>
            <?php if ($buttonText && $buttonUrl): ?>
                <a class="bio-button bio-button--primary" href="<?php echo profile_h($buttonUrl); ?>"><?php echo profile_h($buttonText); ?></a>
            <?php endif; ?>
        </section>
    </main>
<?php
}

function profile_render_background(?array $profile, ?string $backgroundUrl, string $backgroundType): void
{
    $defaultBackgroundVideo = '/vid/nga.mp4';
    $url = $backgroundUrl ?: $defaultBackgroundVideo;
    $type = $backgroundUrl ? $backgroundType : 'video/mp4';
    $isVideo = str_starts_with($type, 'video/');
    $isImage = str_starts_with($type, 'image/');
?>
    <div class="bio-background" aria-hidden="true">
        <?php if ($isVideo): ?>
            <video class="bio-background__media" autoplay muted loop playsinline poster="">
                <source src="<?php echo profile_h($url); ?>" type="<?php echo profile_h($type); ?>">
            </video>
        <?php elseif ($isImage): ?>
            <img class="bio-background__media" src="<?php echo profile_h($url); ?>" alt="" loading="eager">
        <?php else: ?>
            <video class="bio-background__media" autoplay muted loop playsinline poster="">
                <source src="<?php echo profile_h($defaultBackgroundVideo); ?>" type="video/mp4">
            </video>
        <?php endif; ?>
        <div class="bio-background__overlay"></div>
        <div class="bio-orb bio-orb--one"></div>
        <div class="bio-orb bio-orb--two"></div>
        <div class="bio-grid-glow"></div>
    </div>
<?php
}

function profile_render_section_heading(string $icon, string $title, ?string $subtitle = null): void
{
?>
    <div class="bio-section-heading profile-clean-heading">
        <div>
            <span><i class="<?php echo profile_h($icon); ?>"></i><?php echo profile_h($title); ?></span>
            <?php if ($subtitle): ?><p><?php echo profile_h($subtitle); ?></p><?php endif; ?>
        </div>
    </div>
<?php
}

$theme = $profile ? profile_allowed_value((string)($profile['profile_theme'] ?? 'dark'), ['dark', 'light', 'auto'], 'dark') : 'dark';
$accent = $profile ? profile_normalize_hex_color($profile['accent_color'] ?? '#0f5bff') : '#0f5bff';
$secondaryColor = $profile ? profile_normalize_hex_color($profile['profile_secondary_color'] ?? $accent) : $accent;
$cardColor = $profile ? profile_optional_hex_color($profile['profile_card_color'] ?? '') : null;
$textColor = $profile ? profile_optional_hex_color($profile['profile_text_color'] ?? '') : null;
$linkStyle = $profile ? profile_allowed_value((string)($profile['profile_link_style'] ?? 'glass'), ['glass', 'solid', 'outline', 'neon'], 'glass') : 'glass';
$buttonShape = $profile ? profile_allowed_value((string)($profile['profile_button_shape'] ?? 'pill'), ['pill', 'rounded', 'sharp'], 'pill') : 'pill';
$cardColorCss = $cardColor ?: ($theme === 'light' ? '#ffffff' : '#080c18');
$textColorCss = $textColor ?: 'var(--text)';
if ($theme === 'auto') $theme = 'dark';

$layout = $profile ? profile_allowed_value((string)($profile['profile_layout'] ?? 'standard'), ['standard', 'compact', 'showcase', 'clean'], 'standard') : 'standard';
$showEmbeds = $profile ? profile_flag($profile, 'profile_show_embeds', true) : false;
$embeds = $showEmbeds ? profile_list_embeds($mysqli, $profileId, true) : [];
$socialsStyle = $profile ? profile_allowed_value((string)($profile['profile_socials_style'] ?? 'cards'), ['cards', 'icons'], 'cards') : 'cards';

$displayName = $profile ? profile_display_name($profile) : 'Profilo';
$profileUrl = $profile ? 'https://cripsum.com/u/' . rawurlencode(strtolower($profile['username'])) : 'https://cripsum.com/profile.php';
$discordId = $profile ? trim((string)($profile['discord_id'] ?? '')) : '';
$customStatus = $profile ? trim((string)($profile['profile_status'] ?? '')) : '';
$musicExternalUrl = $profile ? trim((string)($profile['profile_music_url'] ?? '')) : '';
$musicMime = $profile ? trim((string)($profile['profile_music_mime'] ?? '')) : '';
$hasUploadedMusic = $profile && $musicMime !== '';
$musicUrl = $hasUploadedMusic ? '/includes/get_profile_music.php?id=' . (int)$profile['id'] : $musicExternalUrl;
$musicTitle = $profile ? trim((string)($profile['profile_music_title'] ?? '')) : '';
$musicArtist = $profile ? trim((string)($profile['profile_music_artist'] ?? '')) : '';
$showAudioPlayer = $profile ? ((int)($profile['profile_show_audio_player'] ?? 1) === 1) : false;
$hasMusic = $hasUploadedMusic || ($musicExternalUrl !== '' && profile_is_safe_url($musicExternalUrl, true));
$profileEffect = $profile ? profile_allowed_value((string)($profile['profile_effect'] ?? 'none'), ['none', 'cursor_glow', 'soft_particles', 'scanlines', 'ambient', 'aurora', 'gradient_waves', 'stars', 'spotlight', 'digital_noise', 'glass_rain', 'sakura_falling', 'cyber_grid'], 'none') : 'none';
$avatarRingEnabled = $profile ? ((int)($profile['avatar_ring_enabled'] ?? 1) === 1) : true;
$avatarRingStyle = $profile ? profile_allowed_value((string)($profile['avatar_ring_style'] ?? 'spin'), ['spin', 'pulse', 'orbit', 'glow', 'dual', 'rainbow', 'halo', 'neon', 'spark', 'glitch', 'none'], 'spin') : 'spin';
$avatarRingColor = $profile ? profile_normalize_hex_color($profile['avatar_ring_color'] ?: $accent) : $accent;
$backgroundUrl = $profile && !empty($profile['profile_banner_type']) ? '/includes/get_profile_banner.php?id=' . (int)$profile['id'] : null;
$backgroundType = $profile && !empty($profile['profile_banner_type']) ? (string)$profile['profile_banner_type'] : 'video/mp4';

$showStats = $profile ? profile_flag($profile, 'profile_show_stats', true) : false;
$showSocials = $profile ? profile_flag($profile, 'profile_show_socials', true) : false;
$showLinks = $profile ? profile_flag($profile, 'profile_show_links', true) : false;
$showProjects = $profile ? profile_flag($profile, 'profile_show_projects', true) : false;
$showContents = $profile ? profile_flag($profile, 'profile_show_contents', true) : false;
$showBadges = $profile ? profile_flag($profile, 'profile_show_badges', true) : false;
$showActivity = $profile ? profile_flag($profile, 'profile_show_activity', true) : false;
$showDiscord = $profile ? profile_flag($profile, 'profile_show_discord', true) : false;
$showCharacters = profile_flag($profile, 'profile_show_characters', true);

$profileFont = $profile['profile_font'] ?? 'Poppins';
$borderRadius = (int)($profile['profile_border_radius'] ?? 30);
$cardOpacity = (int)($profile['profile_card_opacity'] ?? 68);
$cardBlur = (int)($profile['profile_card_blur'] ?? 20);
$borderOpacity = (int)($profile['profile_border_opacity'] ?? 100);
$borderColor = $profile['profile_border_color'] ?? null;
$borderWidth = (int)($profile['profile_border_width'] ?? 1);
$avatarBorder = (int)($profile['profile_avatar_border'] ?? 1);

$uiShape = $profile['profile_ui_shape'] ?? 'circle';
$avatarShape = $profile['profile_avatar_shape'] ?? 'circle';
$socialSize = (int)($profile['profile_social_size'] ?? 42);
$iconSpacing = (int)($profile['profile_icon_spacing'] ?? 8);
$badgeSize = (int)($profile['profile_badge_size'] ?? 24);
$buttonSize = (int)($profile['profile_button_size'] ?? 48);

// Map UI shape to variables
$uiShapeIcon = '50%';
$uiShapeButton = '999px';
$uiShapeCard = '24px';

switch ($uiShape) {
    case 'circle':
        $uiShapeIcon = '50%';
        $uiShapeButton = '999px';
        $uiShapeCard = '24px';
        break;
    case 'rounded':
        $uiShapeIcon = '24px';
        $uiShapeButton = '24px';
        $uiShapeCard = '24px';
        break;
    case 'soft':
        $uiShapeIcon = '16px';
        $uiShapeButton = '16px';
        $uiShapeCard = '16px';
        break;
    case 'square-rounded':
        $uiShapeIcon = '8px';
        $uiShapeButton = '8px';
        $uiShapeCard = '8px';
        break;
    case 'square':
        $uiShapeIcon = '0px';
        $uiShapeButton = '0px';
        $uiShapeCard = '0px';
        break;
    case 'pill':
        $uiShapeIcon = '999px';
        $uiShapeButton = '999px';
        $uiShapeCard = '999px';
        break;
}

$visibleSocials = $showSocials ? $socials : [];
$visibleLinks = $showLinks ? $links : [];
$visibleProjects = $showProjects ? $projects : [];
$visibleContents = $showContents ? $contents : [];
$visibleBlocks = $showContents ? $blocks : [];
$badgesDisplay = $profile['profile_badges_display'] ?? 'both';
$badgesPosition = $profile['profile_badges_position'] ?? 'below_bio';

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

$showMiniBadges = $showBadges && ($badgesDisplay === 'both' || $badgesDisplay === 'card_only');
$showBadgesSection = $showBadges && ($badgesDisplay === 'both' || $badgesDisplay === 'tab_only');
$visibleBadges = $showBadges ? $badges : [];
$visibleActivity = $showActivity ? $activity : [];
$visibleCharacters = $showCharacters ? $characters : [];

// Discord Server invite processing
$discordServerInvite = $profile['discord_server_invite'] ?? '';
$discordServerCache = $profile['discord_server_cache'] ?? '';
$discordServerCacheTime = (int)($profile['discord_server_cache_time'] ?? 0);

$widgetData = null;
if (!empty($discordServerInvite)) {
    if (!empty($discordServerCache) && (time() - $discordServerCacheTime < 300)) {
        // Cache is valid (5 minutes)
        $widgetData = json_decode($discordServerCache, true);
    } else {
        // Refresh cache
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
            $jsonStr = json_encode($widgetData);
            // Save to DB
            $updStmt = $mysqli->prepare("UPDATE utenti SET discord_server_cache = ?, discord_server_cache_time = ? WHERE id = ?");
            if ($updStmt) {
                $now = time();
                $updStmt->bind_param('sii', $jsonStr, $now, $profile['id']);
                $updStmt->execute();
                $updStmt->close();
            }
        } elseif (!empty($discordServerCache)) {
            // Fallback to old cache
            $widgetData = json_decode($discordServerCache, true);
        }
    }
}

$featuredLinks = array_values(array_filter($visibleLinks, fn($item) => (int)($item['is_featured'] ?? 0) === 1));
$normalLinks = array_values(array_filter($visibleLinks, fn($item) => (int)($item['is_featured'] ?? 0) !== 1));
$featuredProjects = array_values(array_filter($visibleProjects, fn($item) => (int)($item['is_featured'] ?? 0) === 1));
$normalProjects = array_values(array_filter($visibleProjects, fn($item) => (int)($item['is_featured'] ?? 0) !== 1));
$featuredContents = array_values(array_filter($visibleContents, fn($item) => (int)($item['is_featured'] ?? 0) === 1));
$normalContents = array_values(array_filter($visibleContents, fn($item) => (int)($item['is_featured'] ?? 0) !== 1));

$hasStats = $showStats && $profile && ((int)$profile['profile_views'] > 0 || (int)$profile['num_achievement'] > 0 || (int)$profile['num_personaggi'] > 0 || (int)$profile['total_personaggi'] > 0);
$hasDiscordSection = $showDiscord && (!empty($discordId) || !empty($widgetData));
$hasRightContent = $hasStats || $featuredLinks || $normalLinks || $visibleProjects || $visibleContents || $visibleBlocks || ($visibleBadges && $showBadgesSection) || $visibleActivity || $visibleCharacters || $embeds;
$hasAnyPublicContent = $visibleSocials || $visibleLinks || $visibleProjects || $visibleContents || $visibleBlocks || $visibleBadges || $hasDiscordSection || $hasMusic || $embeds;

$spotlight = null;
if ($featuredContents) {
    $spotlight = ['type' => 'Contenuto', 'icon' => 'fas fa-play', 'title' => $featuredContents[0]['title'], 'description' => $featuredContents[0]['description'] ?: '', 'url' => $featuredContents[0]['url'] ?: '', 'meta' => $featuredContents[0]['content_type'] ?? 'contenuto'];
} elseif ($featuredProjects) {
    $spotlight = ['type' => 'Progetto', 'icon' => 'fas fa-layer-group', 'title' => $featuredProjects[0]['title'], 'description' => $featuredProjects[0]['description'] ?: '', 'url' => $featuredProjects[0]['url'] ?: '', 'meta' => $featuredProjects[0]['tech_stack'] ?: profile_status_label($featuredProjects[0]['status'] ?? 'active')];
} elseif ($featuredLinks) {
    $spotlight = ['type' => 'Link', 'icon' => $featuredLinks[0]['icon'] ?: 'fas fa-link', 'title' => $featuredLinks[0]['title'], 'description' => $featuredLinks[0]['description'] ?: profile_short_url_label($featuredLinks[0]['url']), 'url' => $featuredLinks[0]['url'], 'meta' => 'in evidenza'];
}

$stats = [];
if ($profile) {
    if ((int)$profile['profile_views'] > 0) $stats[] = ['icon' => 'fas fa-eye', 'value' => profile_compact_number($profile['profile_views']), 'label' => 'Views'];
    if ((int)$profile['num_achievement'] > 0) $stats[] = ['icon' => 'fas fa-trophy', 'value' => profile_compact_number($profile['num_achievement']), 'label' => 'Badges'];
    if ((int)$profile['num_personaggi'] > 0) $stats[] = ['icon' => 'fas fa-user-astronaut', 'value' => profile_compact_number($profile['num_personaggi']), 'label' => 'Characters'];
    if ((int)$profile['total_personaggi'] > 0) $stats[] = ['icon' => 'fas fa-dice-d20', 'value' => profile_compact_number($profile['total_personaggi']), 'label' => 'Pulls'];
}
$ogMeta = cripsum_og_profile($mysqli, $profile);

$lang = 'it';
if (isset($_SESSION['lang']) && $_SESSION['lang'] === 'en') {
    $lang = 'en';
} elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && strpos(strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']), 'it') === false) {
    $lang = 'en';
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include __DIR__ . '/includes/head-import.php'; ?>
    <title><?php echo $profile ? 'Cripsum™ - ' . profile_h($displayName) : 'Cripsum™ - Profilo'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php cripsum_og_print($ogMeta); ?>
    <link rel="stylesheet" href="/assets/css/profile.css?v=4.4.4">
    <script src="/assets/js/profile.js?v=4.4.4" defer></script>
    <?php
    $googleFonts = [
        'Poppins' => 'Poppins',
        'Inter' => 'Inter:wght@300;400;500;600;700;800&display=swap',
        'Roboto' => 'Roboto:wght@300;400;500;700&display=swap',
        'Outfit' => 'Outfit:wght@300;400;500;600;700;800&display=swap',
        'Playfair Display' => 'Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap',
        'Space Grotesk' => 'Space+Grotesk:wght@300..700&display=swap',
        'Syne' => 'Syne:wght@400..800&display=swap',
        'Montserrat' => 'Montserrat:ital,wght@0,100..900;1,100..900&display=swap',
        'Fira Code' => 'Fira+Code:wght@300..700&display=swap',
        'PT Mono' => 'PT+Mono&display=swap',
        'Cinzel' => 'Cinzel:wght@400..900&display=swap',
        'Rubik' => 'Rubik:ital,wght@0,300..900;1,300..900&display=swap',
        'Bebas Neue' => 'Bebas+Neue&display=swap',
        'Press Start 2P' => 'Press+Start+2P&display=swap',
        'Bungee' => 'Bungee&display=swap',
        'Permanent Marker' => 'Permanent+Marker&display=swap',
        'Creepster' => 'Creepster&display=swap',
        'Shojumaru' => 'Shojumaru&display=swap'
    ];
    if (array_key_exists($profileFont, $googleFonts) && $profileFont !== 'Poppins') {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        echo '<link href="https://fonts.googleapis.com/css2?family=' . $googleFonts[$profileFont] . '" rel="stylesheet">' . "\n";
    }
    ?>
    <style>
        .bio-v2-body {
            --radius-lg: <?php echo $borderRadius; ?>px !important;
            --radius-md: <?php echo round($borderRadius * 0.73); ?>px !important;
            --radius-sm: <?php echo round($borderRadius * 0.47); ?>px !important;

            --profile-card-opacity: <?php echo $cardOpacity / 100; ?> !important;
            --profile-card-blur: <?php echo $cardBlur; ?>px !important;
            --profile-border-opacity: <?php echo $borderOpacity / 100; ?> !important;
            --profile-card-bg: color-mix(in srgb, var(--profile-card-color, <?php echo $theme === 'light' ? '#ffffff' : '#080c18'; ?>) <?php echo $cardOpacity; ?>%, transparent) !important;
            --card: var(--profile-card-bg) !important;
            --card-strong: color-mix(in srgb, <?php echo !empty($profile['profile_card_color']) ? $profile['profile_card_color'] : ($theme === 'light' ? '#ffffff' : '#080c18'); ?> <?php echo min(100, $cardOpacity + 20); ?>%, transparent) !important;

            <?php if ($borderColor): ?>--border: <?php echo profile_h($borderColor); ?> !important;
            --profile-border-color: <?php echo profile_h($borderColor); ?> !important;
            <?php endif; ?>--profile-border-width: <?php echo $borderWidth; ?>px !important;
            --profile-font: '<?php echo profile_h($profileFont); ?>', sans-serif !important;
            font-family: var(--profile-font, "Poppins", sans-serif) !important;

            --ui-shape-icon: <?php echo $uiShapeIcon; ?> !important;
            --ui-shape-button: <?php echo $uiShapeButton; ?> !important;
            --ui-shape-card: <?php echo $uiShapeCard; ?> !important;
            --social-icon-size: <?php echo $socialSize; ?>px !important;
            --social-icon-spacing: <?php echo $iconSpacing; ?>px !important;
            --badge-size: <?php echo $badgeSize; ?>px !important;
            --button-height: <?php echo $buttonSize; ?>px !important;
        }
    </style>
</head>

<body
    class="bio-v2-body public-profile-body"
    data-theme="<?php echo profile_h($theme); ?>"
    data-accent="<?php echo profile_h($accent); ?>"
    data-profile-url="<?php echo profile_h($profileUrl); ?>"
    data-discord-id="<?php echo profile_h($showDiscord ? $discordId : ''); ?>"
    data-profile-effect="<?php echo profile_h($profileEffect); ?>"
    data-profile-link-style="<?php echo profile_h($linkStyle); ?>"
    data-profile-button-shape="<?php echo profile_h($buttonShape); ?>"
    data-profile-socials-style="<?php echo profile_h($socialsStyle); ?>"
    data-profile-layout="<?php echo profile_h($layout); ?>"
    data-avatar-shape="<?php echo profile_h($avatarShape); ?>"
    data-avatar-border="<?php echo $avatarBorder; ?>"
    style="--profile-ring: <?php echo profile_h($avatarRingColor); ?>; --accent-2: <?php echo profile_h($secondaryColor); ?>; --profile-card-color: <?php echo profile_h($cardColorCss); ?>; --profile-text-color: <?php echo profile_h($textColorCss); ?>;">

    <?php if ($profile && profile_flag($profile, 'profile_click_to_enter', false)): ?>
        <div id="clickToEnterOverlay" class="click-to-enter-overlay">
            <div class="click-to-enter-content">
                <button type="button" class="click-to-enter-btn">
                    <?php echo profile_h($profile['profile_enter_text'] ?: 'Click to Enter'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <?php
    $isPublicProfilePage = true;
    if (file_exists(__DIR__ . '/includes/navbar-bio.php')) include __DIR__ . '/includes/navbar-bio.php';
    else include __DIR__ . '/includes/navbar.php';
    if (file_exists(__DIR__ . '/includes/impostazioni.php')) include __DIR__ . '/includes/impostazioni.php';
    ?>

    <?php profile_render_background($profile, $backgroundUrl, $backgroundType); ?>
    <div class="profile-effects-layer" aria-hidden="true"></div>

    <?php if ($isNotFound): ?>
        <?php profile_state_page('404', 'Profile Not Found', 'This user does not exist or has changed their username.', 'Home', '/en/home'); ?>
    <?php elseif ($isPrivateBlocked): ?>
        <?php profile_state_page('Private', 'Private Profile', '@' . $profile['username'] . ' is not showing this profile.', 'Home', '/en/home'); ?>
    <?php elseif ($isLoginBlocked): ?>
        <?php profile_state_page('Login', 'Login Required', 'This profile is only visible to registered users.', 'Log In', '/en/login'); ?>
    <?php else: ?>
        <main class="bio-page profile-smart-page <?php echo (!$hasRightContent || $layout === 'clean') ? 'profile-smart-page--single' : ''; ?> layout-<?php echo profile_h($layout); ?>" id="bioPage">
            <section class="bio-hero bio-card profile-smart-hero js-tilt-card js-reveal" aria-label="Public Profile">
                <div class="profile-hero-actions-top">
                    <?php if ($showStats): ?>
                        <?php if ($isOnline): ?>
                            <span class="bio-pill bio-pill--live"><span class="bio-dot"></span>online</span>
                        <?php elseif ($customStatus): ?>
                            <span class="bio-pill"><i class="fas fa-signal"></i><?php echo profile_h($customStatus); ?></span>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="bio-pill"><i class="fas fa-eye"></i><?php echo profile_compact_number($profile['profile_views'] ?? 0); ?> <?php echo ($lang === 'it') ? 'visite' : 'views'; ?></span>
                    <?php endif; ?>

                    <div class="profile-dropdown-wrap">
                        <button class="bio-small-button js-profile-dropdown-trigger" type="button" aria-label="Menu" aria-expanded="false">
                            <i class="fas fa-ellipsis-h"></i>
                        </button>
                        <div class="profile-dropdown-menu">
                            <?php if ($canEdit): ?>
                                <a class="profile-dropdown-item" href="/it/edit-profile<?php echo profile_is_staff() && !$isOwnProfile ? '?user_id=' . (int)$profile['id'] : ''; ?>">
                                    <i class="fas fa-pen"></i>
                                    <span><?php echo ($lang === 'it') ? 'Modifica profilo' : 'Edit profile'; ?></span>
                                </a>
                            <?php endif; ?>
                            <a class="profile-dropdown-item" href="/<?php echo $lang; ?>/home">
                                <i class="fas fa-home"></i>
                                <span>Home Page</span>
                            </a>
                            <button class="profile-dropdown-item js-open-search" type="button">
                                <i class="fas fa-search"></i>
                                <span><?php echo ($lang === 'it') ? 'Cerca utenti' : 'Search users'; ?></span>
                            </button>
                            <button class="profile-dropdown-item js-open-navigation" type="button">
                                <i class="fas fa-compass"></i>
                                <span><?php echo ($lang === 'it') ? 'Apri Navigazione' : 'Open Navigation'; ?></span>
                            </button>
                            <button class="profile-dropdown-item js-copy-profile" type="button">
                                <i class="fas fa-link"></i>
                                <span><?php echo ($lang === 'it') ? 'Copia link' : 'Copy link'; ?></span>
                            </button>
                            <button class="profile-dropdown-item js-share-profile" type="button">
                                <i class="fas fa-share-nodes"></i>
                                <span><?php echo ($lang === 'it') ? 'Condividi Profilo' : 'Share Profile'; ?></span>
                            </button>
                            <button class="profile-dropdown-item js-open-report" type="button">
                                <i class="fas fa-flag"></i>
                                <span><?php echo ($lang === 'it') ? 'Segnala Profilo' : 'Report Profile'; ?></span>
                            </button>
                            <button class="profile-dropdown-item js-open-qr" type="button">
                                <i class="fas fa-qrcode"></i>
                                <span><?php echo ($lang === 'it') ? 'Codice QR' : 'QR Code'; ?></span>
                            </button>
                            <button class="profile-dropdown-item js-theme-toggle" type="button">
                                <i class="fas fa-moon"></i>
                                <span class="theme-label-text"><?php echo ($lang === 'it') ? 'Tema scuro' : 'Dark Mode'; ?></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bio-avatar-wrap profile-smart-avatar ring-style-<?php echo profile_h($avatarRingStyle); ?> <?php echo (!$avatarRingEnabled || $avatarRingStyle === 'none') ? 'ring-disabled' : ''; ?>" style="--profile-ring: <?php echo profile_h($avatarRingColor); ?>;">
                    <?php if ($avatarRingEnabled && $avatarRingStyle !== 'none'): ?><div class="bio-avatar-ring"></div><?php endif; ?>
                    <img class="bio-avatar" src="<?php echo profile_h(profile_avatar_url($profile, 256)); ?>" alt="Avatar di <?php echo profile_h($profile['username']); ?>" loading="eager" data-richpresence-pfp>
                </div>

                <?php
                $renderMiniBadgesHtml = '';
                if ($visibleBadges && $showMiniBadges) {
                    ob_start();
                ?>
                    <div class="profile-mini-badges badges-pos-<?php echo profile_h($badgesPosition); ?>" aria-label="Badge">
                        <?php foreach (array_slice($visibleBadges, 0, 4) as $badge): ?>
                            <?php
                            $badgeName = ($lang === 'it' && !empty($badge['nome'])) ? $badge['nome'] : (!empty($badge['nome_en']) ? $badge['nome_en'] : $badge['nome']);
                            $badgeImage = !empty($badge['img_url']) ? (preg_match('/^https?:\/\//i', $badge['img_url']) ? $badge['img_url'] : '/img/' . ltrim((string)$badge['img_url'], '/')) : null;

                            $styleAttr = '';
                            $extraClasses = '';
                            if ($badge['badge_source'] === 'custom') {
                                $extraClasses .= ' custom-badge-mini';
                                if (!empty($badge['color'])) {
                                    $rgb = function_exists('profile_hex_to_rgb') ? profile_hex_to_rgb($badge['color']) : null;
                                    if ($rgb) {
                                        $rgbStr = "{$rgb[0]}, {$rgb[1]}, {$rgb[2]}";
                                        $styleAttr = 'style="--badge-color: ' . profile_h($badge['color']) . '; --badge-color-rgb: ' . $rgbStr . '; --badge-color-glow-alpha: rgba(' . $rgbStr . ', 0.15);"';
                                    } else {
                                        $styleAttr = 'style="--badge-color: ' . profile_h($badge['color']) . ';"';
                                    }
                                }
                            }
                            ?>
                            <span class="profile-mini-badge<?php echo $extraClasses; ?>" <?php echo $styleAttr; ?> title="<?php echo profile_h($badgeName); ?>">
                                <?php if ($badgeImage): ?>
                                    <img src="<?php echo profile_h($badgeImage); ?>" alt="" loading="lazy">
                                <?php elseif ($badge['badge_source'] === 'custom' && !empty($badge['icon'])): ?>
                                    <i class="<?php echo profile_h($badge['icon']); ?>"></i>
                                <?php else: ?>
                                    <i class="fas fa-medal"></i>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php
                    $renderMiniBadgesHtml = ob_get_clean();
                }
                ?>

                <div class="bio-name-block profile-smart-name">
                    <div class="profile-name-row">
                        <h1 class="profile-display-name"
                            data-name-type="<?php echo profile_h($nameType); ?>"
                            data-name-anim="<?php echo profile_h($nameAnim); ?>"
                            data-text="<?php echo profile_h($displayName); ?>"
                            style="--name-color1: <?php echo profile_h($nameSolidColor); ?>; --name-color2: <?php echo profile_h($nameGradColor1); ?>; --name-color3: <?php echo profile_h($nameGradColor2); ?>; --name-angle: <?php echo profile_h($nameGradAngle); ?>deg; --name-glow-color: <?php echo profile_h($nameGlowColor); ?>;">
                            <?php echo profile_format_name($displayName, $nameStyle); ?>
                        </h1>
                        <?php if ($badgesPosition === 'right_of_name') echo $renderMiniBadgesHtml; ?>
                    </div>
                    <p class="bio-username">@<?php echo profile_h($profile['username']); ?></p>
                    <?php if ($badgesPosition === 'below_username') echo $renderMiniBadgesHtml; ?>
                    <?php if (!empty($profile['bio'])): ?>
                        <p class="bio-tagline"><?php echo nl2br(profile_h($profile['bio'])); ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($badgesPosition === 'below_bio' || empty($badgesPosition)) echo $renderMiniBadgesHtml; ?>

                <?php if ($visibleSocials): ?>
                    <?php if ($socialsStyle === 'icons'): ?>
                        <div class="bio-social-icons-row" aria-label="Social">
                            <?php foreach ($visibleSocials as $social): ?>
                                <a class="bio-social-icon bio-social-icon--<?php echo profile_h($social['platform']); ?>" href="<?php echo profile_h($social['url']); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo profile_h($social['label'] ?: ucfirst($social['platform'])); ?>">
                                    <i class="<?php echo profile_h(profile_social_icon_class($social['platform'])); ?>"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="bio-social-grid profile-social-compact" aria-label="Social">
                            <?php foreach ($visibleSocials as $social): ?>
                                <a class="bio-social" href="<?php echo profile_h($social['url']); ?>" target="_blank" rel="noopener noreferrer">
                                    <span class="bio-social__icon"><i class="<?php echo profile_h(profile_social_icon_class($social['platform'])); ?>"></i></span>
                                    <span>
                                        <strong><?php echo profile_h($social['label'] ?: ucfirst($social['platform'])); ?></strong>
                                        <small><?php echo profile_h($social['display_username'] ?: profile_short_url_label($social['url'])); ?></small>
                                    </span>
                                    <i class="fas fa-arrow-up-right-from-square bio-social__arrow"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?> <?php if ($showDiscord && $discordId): ?>
                    <div class="profile-discord-left js-reveal" aria-label="Attività Discord">
                        <div class="profile-discord-left__title">
                            <span><i class="fab fa-discord"></i>Discord</span>
                        </div>
                        <div class="discord-box" id="discordBox">
                            <?php $discordProfileId = $discordId;
                                    require __DIR__ . '/includes/discord_status.php'; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($showDiscord && !empty($widgetData)): ?>
                    <?php
                    $discordServerName = $widgetData['server_name'] ?? '';
                    $discordServerIcon = $widgetData['icon_hash'] ?? null;
                    $discordGuildId = $widgetData['guild_id'] ?? '';
                    $discordOnline = (int)($widgetData['online_members'] ?? 0);
                    $discordTotal = (int)($widgetData['total_members'] ?? 0);
                    $discordCode = $widgetData['code'] ?? '';

                    $discordJoinUrl = "https://discord.gg/" . rawurlencode($discordCode);

                    $discordIconUrl = null;
                    if ($discordServerIcon && $discordGuildId) {
                        $format = strpos($discordServerIcon, 'a_') === 0 ? 'gif' : 'png';
                        $discordIconUrl = "https://cdn.discordapp.com/icons/" . rawurlencode($discordGuildId) . "/" . rawurlencode($discordServerIcon) . "." . $format . "?size=128";
                    }
                    ?>
                    <div class="profile-discord-left js-reveal" aria-label="Server Discord" style="margin-top: 1.25rem;">
                        <div class="profile-discord-left__title">
                            <span><i class="fab fa-discord"></i><?php echo (isset($lang) && $lang === 'en') ? 'Discord Server' : 'Server Discord'; ?></span>
                        </div>
                        <div class="ds-card profile-discord-server-section" style="padding: 1.25rem;">
                            <div class="profile-discord-server-card">
                                <div class="profile-discord-server-left">
                                    <?php if ($discordIconUrl): ?>
                                        <img class="profile-discord-server-icon" src="<?php echo htmlspecialchars($discordIconUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($discordServerName, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                                    <?php else: ?>
                                        <div class="profile-discord-server-icon-fallback">
                                            <i class="fab fa-discord"></i>
                                        </div>
                                    <?php endif; ?>

                                    <div class="profile-discord-server-info">
                                        <span class="profile-discord-server-label"><?php echo (isset($lang) && $lang === 'en') ? 'DISCORD SERVER' : 'SERVER DISCORD'; ?></span>
                                        <strong class="profile-discord-server-name"><?php echo htmlspecialchars($discordServerName, ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <div class="profile-discord-server-stats">
                                            <span class="discord-stat-online"><span class="discord-stat-dot online"></span><?php echo number_format($discordOnline); ?> Online</span>
                                            <span class="discord-stat-total"><span class="discord-stat-dot total"></span><?php echo number_format($discordTotal); ?> <?php echo (isset($lang) && $lang === 'en') ? 'Members' : 'Membri'; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <a href="<?php echo htmlspecialchars($discordJoinUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="bio-button bio-button--primary discord-join-button">
                                    <i class="fab fa-discord"></i>
                                    <span><?php echo (isset($lang) && $lang === 'en') ? 'Join' : 'Entra'; ?></span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!$hasAnyPublicContent && $isOwnProfile): ?>
                    <div class="profile-owner-nudge">
                        <i class="fas fa-plus"></i>
                        <span>Add links, badges, or content to fill out the bio.</span>
                        <a href="/en/edit-profile">Edit</a>
                    </div>
                <?php endif; ?>

                <?php if ($hasMusic && $showAudioPlayer): ?>
                    <div class="bio-audio profile-audio-player" data-audio-player>
                        <audio id="profileAudio" preload="metadata" src="<?php echo profile_h($musicUrl); ?>"></audio>
                        <div class="bio-audio__header">
                            <div>
                                <small>Audio</small>
                                <strong><i class="fas fa-music"></i><?php echo profile_h($musicTitle ?: 'Profile Song'); ?></strong>
                                <?php if ($musicArtist): ?><span><?php echo profile_h($musicArtist); ?></span><?php endif; ?>
                            </div>
                            <button class="bio-small-button js-profile-audio-toggle" type="button" aria-label="Play pause"><i id="profileAudioIcon" class="fas fa-play"></i></button>
                        </div>
                        <div class="bio-audio__progress">
                            <span id="profileAudioCurrent">0:00</span>
                            <input id="profileAudioProgress" type="range" min="0" max="100" step="0.1" value="0" aria-label="Audio progress">
                            <span id="profileAudioTotal">0:00</span>
                        </div>
                        <div class="bio-audio__bottom">
                            <button class="bio-small-button js-profile-volume-toggle" type="button" aria-label="Mute"><i id="profileVolumeIcon" class="fas fa-volume-down"></i></button>
                            <input id="profileVolumeSlider" type="range" min="0" max="1" step="0.01" value="0.18" aria-label="Volume">
                        </div>
                    </div>
                <?php elseif ($hasMusic && !$showAudioPlayer): ?>
                    <?php $hasClickToEnter = $profile && profile_flag($profile, 'profile_click_to_enter', false); ?>
                    <audio
                        id="profileAudio"
                        class="profile-hidden-audio"
                        preload="auto"
                        <?php if (!$hasClickToEnter): ?>autoplay<?php endif; ?>
                        loop
                        data-autoplay="1"
                        src="<?php echo profile_h($musicUrl); ?>"></audio>
                <?php endif; ?>

                <div class="profile-small-meta">
                    <?php if (!$showStats): ?>
                        <?php if ($isOnline): ?>
                            <span class="bio-pill bio-pill--live" style="margin-right: 0.4rem; padding: 0.2rem 0.5rem;"><span class="bio-dot"></span>online</span>
                        <?php elseif ($customStatus): ?>
                            <span class="bio-pill" style="margin-right: 0.4rem; padding: 0.2rem 0.5rem;"><i class="fas fa-signal"></i><?php echo profile_h($customStatus); ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <span><i class="fas fa-calendar"></i><?php echo date('d/m/Y', strtotime($profile['data_creazione'])); ?></span>
                    <?php if (!$isOnline && $lastSeen): ?><span><i class="fas fa-clock"></i><?php echo profile_h(profile_time_ago($lastSeen)); ?></span><?php endif; ?>
                    <?php if ($showDiscord && $discordId): ?><span><i class="fab fa-discord"></i>Discord</span><?php endif; ?>
                </div>
            </section>

            <?php if ($hasRightContent): ?>
                <section class="bio-content profile-smart-content" aria-label="Contenuti profilo">
                    <?php if ($spotlight): ?>
                        <section class="bio-card bio-featured profile-spotlight js-reveal">
                            <a class="profile-spotlight-link" href="<?php echo profile_h($spotlight['url'] ?: '#'); ?>" <?php echo $spotlight['url'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                <span class="profile-spotlight-icon"><i class="<?php echo profile_h($spotlight['icon']); ?>"></i></span>
                                <span class="profile-spotlight-content">
                                    <small><?php echo profile_h($spotlight['type']); ?> Featured</small>
                                    <strong><?php echo profile_h($spotlight['title']); ?></strong>
                                    <?php if ($spotlight['description']): ?><em><?php echo profile_h($spotlight['description']); ?></em><?php endif; ?>
                                    <?php if ($spotlight['meta']): ?><span><?php echo profile_h($spotlight['meta']); ?></span><?php endif; ?>
                                </span>
                                <?php if ($spotlight['url']): ?><i class="fas fa-arrow-up-right-from-square"></i><?php endif; ?>
                            </a>
                        </section>
                    <?php endif; ?>

                    <?php
                    $sectionsHtml = [];

                    // 1. Links
                    ob_start();
                    if ($featuredLinks || $normalLinks): ?>
                        <section class="bio-card bio-featured js-reveal">
                            <?php profile_render_section_heading('fas fa-link', 'Link'); ?>
                            <div class="bio-featured-grid profile-link-grid profile-link-count-<?php echo count(array_merge($featuredLinks, $normalLinks)); ?>">
                                <?php foreach (array_merge($featuredLinks, $normalLinks) as $item): ?>
                                    <?php
                                    $buttonStyle = profile_allowed_value((string)($item['button_style'] ?? 'card'), ['card', 'compact', 'icon'], 'card');
                                    $linkTitle = (string)($item['title'] ?? 'Link');
                                    ?>
                                    <a class="bio-featured-link profile-link-button button-style-<?php echo profile_h($buttonStyle); ?> <?php echo !empty($item['is_featured']) ? 'is-pinned' : ''; ?>" href="<?php echo profile_h($item['url']); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo profile_h($linkTitle); ?>">
                                        <span class="bio-featured-link__icon"><i class="<?php echo profile_h($item['icon'] ?: 'fas fa-link'); ?>"></i></span>
                                        <?php if ($buttonStyle === 'icon'): ?>
                                            <span class="profile-link-icon-label"><?php echo profile_h($linkTitle); ?></span>
                                        <?php else: ?>
                                            <span class="bio-featured-link__content">
                                                <?php if (!empty($item['is_featured'])): ?><small>Pin</small><?php endif; ?>
                                                <strong><?php echo profile_h($linkTitle); ?></strong>
                                                <?php if (!empty($item['description'])): ?><em><?php echo profile_h($item['description']); ?></em><?php else: ?><em><?php echo profile_h(profile_short_url_label($item['url'])); ?></em><?php endif; ?>
                                            </span>
                                            <i class="fas fa-chevron-right"></i>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif;
                    $sectionsHtml['links'] = ob_get_clean();

                    // 2. Embeds
                    ob_start();
                    if ($embeds): ?>
                        <section class="bio-card profile-embeds-section js-reveal">
                            <?php profile_render_section_heading('fas fa-share-square', 'Embed'); ?>
                            <div class="profile-embeds-grid">
                                <?php foreach ($embeds as $embed): ?>
                                    <?php
                                    $embedUrl = $embed['url'];
                                    $embedType = $embed['type'];
                                    $embedTitle = $embed['title'] ?: ($embedType === 'spotify' ? 'Spotify Playlist' : 'YouTube Video');
                                    ?>
                                    <div class="profile-embed-wrapper profile-embed-<?php echo profile_h($embedType); ?>">
                                        <?php if ($embed['title']): ?>
                                            <div class="profile-embed-header">
                                                <span><i class="<?php echo $embedType === 'spotify' ? 'fab fa-spotify' : ($embedType === 'youtube' ? 'fab fa-youtube' : 'fas fa-code'); ?>"></i><?php echo profile_h($embedTitle); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <iframe src="<?php echo profile_h($embedUrl); ?>" width="100%" height="<?php echo $embedType === 'spotify' ? '352' : '315'; ?>" frameBorder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif;
                    $sectionsHtml['embeds'] = ob_get_clean();

                    // 3. Stats
                    ob_start();
                    if ($hasStats): ?>
                        <div class="bio-stats-grid profile-stats-compact js-reveal">
                            <?php foreach (array_slice($stats, 0, 4) as $stat): ?>
                                <article class="bio-stat-card"><i class="<?php echo profile_h($stat['icon']); ?>"></i><strong><?php echo profile_h($stat['value']); ?></strong><span><?php echo profile_h($stat['label']); ?></span></article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif;
                    $sectionsHtml['stats'] = ob_get_clean();

                    // 4. Projects
                    ob_start();
                    if ($visibleProjects): ?>
                        <section class="bio-card bio-details profile-clean-section js-reveal">
                            <?php profile_render_section_heading('fas fa-cubes', 'Projects'); ?>
                            <div class="bio-project-grid">
                                <?php foreach ($visibleProjects as $project): ?>
                                    <?php
                                    $projectImageUrl = trim((string)($project['image_url'] ?? ''));
                                    $hasProjectImage = $projectImageUrl !== '' && profile_is_safe_url($projectImageUrl, false);
                                    ?>
                                    <a class="bio-project-card <?php echo !empty($project['is_featured']) ? 'is-pinned' : ''; ?> <?php echo $hasProjectImage ? 'has-media' : ''; ?>" href="<?php echo profile_h($project['url'] ?: '#'); ?>" <?php echo $project['url'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                        <?php if ($hasProjectImage): ?>
                                            <span class="profile-card-media profile-project-media">
                                                <img src="<?php echo profile_h($projectImageUrl); ?>" alt="<?php echo profile_h($project['title']); ?>" loading="lazy" onerror="this.parentElement.classList.add('is-broken'); this.remove();">
                                                <span class="profile-card-media__fallback"><i class="fas fa-image"></i></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="bio-project-card__icon"><i class="fas fa-layer-group"></i></span>
                                        <?php endif; ?>
                                        <strong><?php echo profile_h($project['title']); ?></strong>
                                        <?php if (!empty($project['description'])): ?><p><?php echo profile_h($project['description']); ?></p><?php endif; ?>
                                        <small><?php echo profile_h($project['tech_stack'] ?: profile_status_label($project['status'])); ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif;
                    $sectionsHtml['projects'] = ob_get_clean();

                    // 5. Blocks
                    ob_start();
                    if ($visibleBlocks): ?>
                        <section class="bio-card bio-details profile-clean-section js-reveal">
                            <div class="profile-block-grid">
                                <?php foreach ($visibleBlocks as $block): ?>
                                    <?php
                                    $blockType = profile_allowed_value((string)($block['block_type'] ?? 'text'), ['text', 'image', 'gif', 'video'], 'text');
                                    $mediaUrl = trim((string)($block['media_url'] ?? ''));
                                    $isPinned = !empty($block['is_featured']);
                                    ?>
                                    <article class="profile-block-card profile-block-<?php echo profile_h($blockType); ?> <?php echo $isPinned ? 'is-pinned' : ''; ?>">
                                        <?php if ($mediaUrl && in_array($blockType, ['image', 'gif'], true)): ?>
                                            <img src="<?php echo profile_h($mediaUrl); ?>" alt="" loading="lazy">
                                        <?php elseif ($mediaUrl && $blockType === 'video'): ?>
                                            <video src="<?php echo profile_h($mediaUrl); ?>" controls playsinline preload="metadata"></video>
                                        <?php endif; ?>
                                        <div class="profile-block-copy">
                                            <?php if (!empty($block['title'])): ?><strong><?php echo profile_h($block['title']); ?></strong><?php endif; ?>
                                            <?php if (!empty($block['body'])): ?><p><?php echo nl2br(profile_h($block['body'])); ?></p><?php endif; ?>
                                            <?php if ($isPinned): ?><small>Pin</small><?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif;
                    $sectionsHtml['blocks'] = ob_get_clean();

                    // 6. Contents
                    ob_start();
                    if ($visibleContents): ?>
                        <section class="bio-card bio-details profile-clean-section js-reveal">
                            <?php profile_render_section_heading('fas fa-play-circle', 'Content'); ?>
                            <div class="bio-preview-grid">
                                <?php foreach ($visibleContents as $content): ?>
                                    <?php
                                    $contentThumbUrl = trim((string)($content['thumbnail_url'] ?? ''));
                                    $hasContentThumb = $contentThumbUrl !== '' && profile_is_safe_url($contentThumbUrl, false);
                                    ?>
                                    <a class="bio-preview-card <?php echo !empty($content['is_featured']) ? 'is-pinned' : ''; ?> <?php echo $hasContentThumb ? 'has-media' : ''; ?>" href="<?php echo profile_h($content['url'] ?: '#'); ?>" <?php echo $content['url'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                        <?php if ($hasContentThumb): ?>
                                            <span class="profile-card-media profile-content-media">
                                                <img src="<?php echo profile_h($contentThumbUrl); ?>" alt="<?php echo profile_h($content['title']); ?>" loading="lazy" onerror="this.parentElement.classList.add('is-broken'); this.remove();">
                                                <span class="profile-card-media__fallback"><i class="fas fa-play"></i></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="bio-preview-card__icon"><i class="fas fa-play"></i></span>
                                        <?php endif; ?>
                                        <span class="bio-preview-card__label"><?php echo profile_h($content['content_type']); ?></span>
                                        <strong><?php echo profile_h($content['title']); ?></strong>
                                        <?php if (!empty($content['description'])): ?><p><?php echo profile_h($content['description']); ?></p><?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif;
                    $sectionsHtml['contents'] = ob_get_clean();

                    // 7. Characters
                    ob_start();
                    if ($visibleCharacters): ?>
                        <section class="bio-card profile-characters-section profile-clean-section js-reveal">
                            <?php profile_render_section_heading('fas fa-user-astronaut', 'Characters'); ?>
                            <div class="profile-character-grid">
                                <?php foreach ($visibleCharacters as $char): ?>
                                    <?php
                                    $charImg     = profile_character_img_url($char);
                                    $rarityClass = profile_character_rarity_class((string)($char['rarità'] ?? ''));
                                    $charQty     = (int)($char['quantità'] ?? 0);
                                    $rarityLabel = $char['rarità'] !== '' ? ucfirst((string)$char['rarità']) : null;
                                    ?>
                                    <article class="profile-character-card rarity-<?php echo profile_h($rarityClass); ?>">
                                        <div class="profile-character-img-wrap">
                                            <?php if ($charImg): ?>
                                                <img
                                                    src="<?php echo profile_h($charImg); ?>"
                                                    alt="<?php echo profile_h($char['nome']); ?>"
                                                    loading="lazy">
                                            <?php else: ?>
                                                <span class="profile-character-img-fallback">
                                                    <i class="fas fa-user-astronaut"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="profile-character-info">
                                            <strong><?php echo profile_h($char['nome']); ?></strong>
                                            <div class="profile-character-meta">
                                                <?php if ($rarityLabel): ?>
                                                    <span class="profile-character-rarity"><?php echo profile_h($rarityLabel); ?></span>
                                                <?php endif; ?>
                                                <?php if ($charQty > 1): ?>
                                                    <span class="profile-character-qty">×<?php echo $charQty; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif;
                    $sectionsHtml['characters'] = ob_get_clean();

                    // 8. Badges
                    ob_start();
                    if ($visibleBadges && $showBadgesSection): ?>
                        <section class="bio-card bio-details profile-clean-section js-reveal">
                            <?php profile_render_section_heading('fas fa-trophy', 'Badge'); ?>
                            <div class="profile-badge-grid">
                                <?php foreach ($visibleBadges as $badge): ?>
                                    <?php
                                    $badgeName = ($lang === 'it' && !empty($badge['nome'])) ? $badge['nome'] : (!empty($badge['nome_en']) ? $badge['nome_en'] : $badge['nome']);
                                    $badgeDesc = ($lang === 'it' && !empty($badge['descrizione'])) ? $badge['descrizione'] : (!empty($badge['descrizione_en']) ? $badge['descrizione_en'] : $badge['descrizione']);

                                    $badgeImage = !empty($badge['img_url']) ? (preg_match('/^https?:\/\//i', $badge['img_url']) ? $badge['img_url'] : '/img/' . ltrim((string)$badge['img_url'], '/')) : null;

                                    $isFeaturedBadge = (int)($profile['featured_badge_id'] ?? 0) === (int)$badge['id'] && $badge['badge_source'] === 'achievement';

                                    $styleAttr = '';
                                    $cardClasses = [];

                                    if ($badge['badge_source'] === 'custom') {
                                        $cardClasses[] = 'custom-badge-card';
                                        if (!empty($badge['badge_type'])) {
                                            $cardClasses[] = 'badge-type-' . $badge['badge_type'];
                                        }
                                        if (!empty($badge['animation']) && $badge['animation'] !== 'none') {
                                            $cardClasses[] = 'badge-anim-' . $badge['animation'];
                                        }
                                        if (!empty($badge['glow']) && (int)$badge['glow'] === 1) {
                                            $cardClasses[] = 'badge-glow';
                                        }

                                        if (!empty($badge['color'])) {
                                            $rgb = function_exists('profile_hex_to_rgb') ? profile_hex_to_rgb($badge['color']) : null;
                                            if ($rgb) {
                                                $rgbStr = "{$rgb[0]}, {$rgb[1]}, {$rgb[2]}";
                                                $styleAttr = 'style="--badge-color: ' . profile_h($badge['color']) . '; --badge-color-rgb: ' . $rgbStr . '; --badge-color-alpha: rgba(' . $rgbStr . ', 0.12); --badge-color-bg-alpha: rgba(' . $rgbStr . ', 0.08); --badge-color-border-alpha: rgba(' . $rgbStr . ', 0.25); --badge-color-shadow: rgba(' . $rgbStr . ', 0.2); --badge-color-glow-alpha: rgba(' . $rgbStr . ', 0.15); --badge-color-glow-alpha-hover: rgba(' . $rgbStr . ', 0.3);"';
                                            } else {
                                                $styleAttr = 'style="--badge-color: ' . profile_h($badge['color']) . ';"';
                                            }
                                        }

                                        $badgeTypeLabels = [
                                            'staff' => ($lang === 'it') ? 'Staff' : 'Staff',
                                            'verified' => ($lang === 'it') ? 'Verificato' : 'Verified',
                                            'developer' => ($lang === 'it') ? 'Sviluppatore' : 'Developer',
                                            'artist' => ($lang === 'it') ? 'Artista' : 'Artist',
                                            'rare' => ($lang === 'it') ? 'Raro' : 'Rare',
                                            'custom' => ($lang === 'it') ? 'Speciale' : 'Special',
                                        ];
                                        $subtitle = $badgeTypeLabels[$badge['badge_type'] ?? 'custom'] ?? (($lang === 'it') ? 'Speciale' : 'Special');
                                    } else {
                                        $rarity = function_exists('profile_badge_rarity') ? profile_badge_rarity((int)($badge['punti'] ?? 0)) : ['label' => 'Badge', 'class' => 'common'];
                                        $cardClasses[] = 'rarity-' . $rarity['class'];
                                        if ($isFeaturedBadge) {
                                            $cardClasses[] = 'is-featured';
                                        }
                                        $subtitle = $rarity['label'] . ((int)($badge['punti'] ?? 0) > 0 ? ' · ' . (int)$badge['punti'] . ' punti' : '');
                                    }

                                    $classStr = implode(' ', $cardClasses);
                                    ?>
                                    <article class="profile-badge-card <?php echo profile_h($classStr); ?>" <?php echo $styleAttr; ?> tabindex="0">
                                        <div class="profile-badge-art">
                                            <?php if ($badgeImage): ?>
                                                <img src="<?php echo profile_h($badgeImage); ?>" alt="" loading="lazy">
                                            <?php elseif ($badge['badge_source'] === 'custom' && !empty($badge['icon'])): ?>
                                                <i class="<?php echo profile_h($badge['icon']); ?>"></i>
                                            <?php else: ?>
                                                <i class="fas fa-medal"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="profile-badge-info">
                                            <strong><?php echo profile_h($badgeName); ?></strong>
                                            <?php if (!empty($badgeDesc)): ?>
                                                <p><?php echo profile_h($badgeDesc); ?></p>
                                            <?php endif; ?>
                                            <small><?php echo profile_h($subtitle); ?></small>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif;
                    $sectionsHtml['badges'] = ob_get_clean();

                    // 9. Activity
                    ob_start();
                    if ($visibleActivity): ?>
                        <section class="bio-card bio-about js-reveal">
                            <?php profile_render_section_heading('fas fa-clock', 'Activity'); ?>
                            <div class="profile-activity-strip">
                                <?php foreach (array_slice($visibleActivity, 0, 5) as $item): ?>
                                    <a class="profile-activity-pill" href="<?php echo !empty($item['url']) ? profile_h($item['url']) : '#'; ?>" <?php echo !empty($item['url']) ? 'target="_blank" rel="noopener noreferrer"' : 'aria-disabled="true"'; ?>>
                                        <i class="<?php echo profile_h(function_exists('profile_activity_icon') ? profile_activity_icon($item['activity_type'] ?? '') : 'fas fa-clock'); ?>"></i>
                                        <span><?php echo profile_h($item['label']); ?></span>
                                        <small><?php echo profile_h(profile_time_ago($item['created_at'] ?? null)); ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif;
                    $sectionsHtml['activity'] = ob_get_clean();

                    // Output sections in the custom order
                    $sectionsOrderRaw = $profile['profile_sections_order'] ?? 'links,embeds,stats,projects,blocks,contents,characters,badges,activity';
                    $sectionsOrder = explode(',', $sectionsOrderRaw);
                    $allowedSectionsList = ['links', 'embeds', 'stats', 'projects', 'blocks', 'contents', 'characters', 'badges', 'activity'];

                    foreach ($sectionsOrder as $secKey) {
                        $secKey = trim($secKey);
                        if (isset($sectionsHtml[$secKey])) {
                            echo $sectionsHtml[$secKey];
                            unset($sectionsHtml[$secKey]);
                        }
                    }

                    // Append any active sections not found in the custom order list
                    foreach ($allowedSectionsList as $secKey) {
                        if (isset($sectionsHtml[$secKey])) {
                            echo $sectionsHtml[$secKey];
                        }
                    }
                    ?>
                </section>
            <?php endif; ?>
        </main>
    <?php endif; ?>

    <div class="profile-qr-modal" id="profileQrModal" aria-hidden="true">
        <div class="profile-qr-backdrop js-close-qr"></div>
        <section class="bio-card profile-qr-card" role="dialog" aria-modal="true" aria-label="QR Profile">
            <button class="bio-small-button js-close-qr" type="button" aria-label="Close"><i class="fas fa-xmark"></i></button>
            <strong>QR Profile</strong>
            <img class="profile-qr-image" alt="QR code of the profile" src="/api/profile_qr.php?url=<?php echo rawurlencode($profileUrl); ?>" data-qr-src="/api/profile_qr.php?url=<?php echo rawurlencode($profileUrl); ?>">
            <button class="bio-button bio-button--primary js-copy-profile" type="button"><i class="fas fa-link"></i>Copy link</button>
        </section>
    </div>

    <div class="bio-toast" id="bioToast" role="status" aria-live="polite"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>