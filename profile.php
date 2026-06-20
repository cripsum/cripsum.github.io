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
$customAlias = $_GET['custom_alias'] ?? null;
if ($customAlias !== null) {
    $customAlias = trim((string)$customAlias);
    if (strtolower($customAlias) === 'profile') {
        $customAlias = null; // Treat /profile as direct profile.php visit
    }
}

if ($customAlias !== null && $customAlias !== '') {
    $profile = profile_get_public_profile_by_alias($mysqli, $customAlias);

    // Redirect SEO: alias visits redirect to the canonical /u/username URL
    if ($profile && !empty($profile['username']) && !isset($_GET['preview_mode'])) {
        header("HTTP/1.1 301 Moved Permanently");
        header('Location: /u/' . rawurlencode(strtolower($profile['username'])));
        exit;
    }

    // Serve standard website 404.html if the custom alias doesn't exist
    if (!$profile) {
        header("HTTP/1.1 404 Not Found");
        include __DIR__ . '/404.html';
        exit;
    }
} else {
    $identifier = profile_get_identifier();
    if (!$identifier) {
        if ($isLoggedIn && !empty($_SESSION['username'])) {
            header('Location: /u/' . rawurlencode(strtolower($_SESSION['username'])));
            exit;
        }
        // Redirect logged out users accessing their own profile to login
        $lang = 'it';
        if (isset($_SESSION['lang']) && $_SESSION['lang'] === 'en') {
            $lang = 'en';
        } elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && strpos(strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']), 'it') === false) {
            $lang = 'en';
        }
        header("Location: /{$lang}/accedi");
        exit;
    } else {
        $profile = profile_get_public_profile($mysqli, $identifier);
    }
}

$isNotFound = !$profile;
$isOwnProfile = false;
$canEdit = false;
$isPremium = false;
$isPrivateBlocked = false;
$isLoginBlocked = false;
$socials = $links = $projects = $contents = $blocks = $badges = $activity = [];
$isOnline = false;
$lastSeen = null;

if ($profile) {
    $profileId = (int)$profile['id'];
    $isOwnProfile = $currentUserId === $profileId;
    $canEdit = profile_can_edit($profileId);
    $isPremium = (int)($profile['is_premium'] ?? 0) === 1;

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
        if (isset($_GET['preview_mode']) && isset($_SESSION['profile_draft'][$profileId])) {
            $draft = $_SESSION['profile_draft'][$profileId];

            // Override profile values
            foreach ($draft as $key => $val) {
                if (!in_array($key, ['socials_json', 'links_json', 'projects_json', 'contents_json', 'blocks_json', 'badges_json', 'characters_json', 'embeds_json', 'profile_tags_json'])) {
                    $profile[$key] = $val;
                }
            }

            // Re-map booleans
            $booleans = [
                'tilt_enabled',
                'avatar_ring_enabled',
                'profile_avatar_border',
                'profile_show_stats',
                'profile_show_socials',
                'profile_show_links',
                'profile_show_projects',
                'profile_show_contents',
                'profile_show_blocks',
                'profile_show_badges',
                'profile_show_activity',
                'profile_show_discord',
                'profile_show_audio_player',
                'profile_click_to_enter',
                'profile_show_embeds',
                'profile_show_characters',
                'profile_hide_meta',
                'profile_show_audio_btn'
            ];
            foreach ($booleans as $boolCol) {
                $profile[$boolCol] = isset($draft[$boolCol]) ? (int)$draft[$boolCol] : 0;
            }

            // Re-map list JSON variables
            $socials = isset($draft['socials_json']) ? json_decode($draft['socials_json'], true) : [];
            $links = isset($draft['links_json']) ? json_decode($draft['links_json'], true) : [];
            $projects = isset($draft['projects_json']) ? json_decode($draft['projects_json'], true) : [];
            $contents = isset($draft['contents_json']) ? json_decode($draft['contents_json'], true) : [];
            $blocks = isset($draft['blocks_json']) ? json_decode($draft['blocks_json'], true) : [];
            $embeds = isset($draft['embeds_json']) ? json_decode($draft['embeds_json'], true) : [];
            if (isset($draft['profile_tags_json'])) {
                $profile['profile_tags_json'] = $draft['profile_tags_json'];
            }

            // Resolve badges
            $badges = [];
            if (isset($draft['badges_json'])) {
                $badgeCompoundIds = json_decode($draft['badges_json'], true) ?: [];
                foreach ($badgeCompoundIds as $i => $compoundId) {
                    if (str_starts_with($compoundId, 'custom_')) {
                        $badgeId = (int)substr($compoundId, 7);
                        $res = $mysqli->query("SELECT 'custom' AS badge_source, cb.id, cb.name AS nome, cb.name_en AS nome_en, cb.descrizione, cb.descrizione_en, cb.image_url AS img_url, 0 AS punti, $i AS sort_order, cb.color, cb.glow, cb.animation, cb.badge_type, cb.icon FROM custom_badges cb WHERE cb.id = " . $badgeId);
                        if ($row = $res->fetch_assoc()) {
                            $badges[] = $row;
                        }
                    } else {
                        $badgeId = $compoundId;
                        if (str_starts_with($compoundId, 'achievement_')) {
                            $badgeId = (int)substr($compoundId, 12);
                        }
                        $res = $mysqli->query("SELECT 'achievement' AS badge_source, a.id, a.nome, a.nome_en, a.descrizione, a.descrizione_en, a.img_url, a.punti, $i AS sort_order, NULL AS color, 0 AS glow, 'none' AS animation, 'custom' AS badge_type, NULL AS icon FROM achievement a WHERE a.id = " . $badgeId);
                        if ($row = $res->fetch_assoc()) {
                            $badges[] = $row;
                        }
                    }
                }
            }

            // Resolve characters
            $characters = [];
            if (isset($draft['characters_json'])) {
                $charIds = json_decode($draft['characters_json'], true) ?: [];
                foreach ($charIds as $i => $charId) {
                    $charId = (int)$charId;
                    if ($charId <= 0) continue;
                    $res = $mysqli->query("
                        SELECT p.id, p.nome, COALESCE(p.img_url, '') AS img_url, COALESCE(p.rarità, '') AS rarità, COALESCE(up.quantità, 1) as quantità
                        FROM personaggi p
                        LEFT JOIN utenti_personaggi up ON up.personaggio_id = p.id AND up.utente_id = " . $profileId . "
                        WHERE p.id = " . $charId . "
                        LIMIT 1
                    ");
                    if ($row = $res->fetch_assoc()) {
                        $characters[] = $row;
                    }
                }
            }

            $activity = profile_recent_activity($mysqli, $profileId);
        } else {
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
        }

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
    </div>
<?php
}

function profile_get_section_title(string $sectionKey, string $defaultTitle): string
{
    global $profile;
    $isPremium = (int)($profile['is_premium'] ?? 0) === 1;
    if ($isPremium && !empty($profile['profile_sections_config'])) {
        $config = json_decode($profile['profile_sections_config'], true);
        if (is_array($config) && isset($config[$sectionKey])) {
            $secConf = $config[$sectionKey];
            if (isset($secConf['title']) && trim($secConf['title']) !== '') {
                return trim($secConf['title']);
            }
        }
    }
    return $defaultTitle;
}

function profile_render_section_heading(string $icon, string $title, ?string $subtitle = null, ?string $sectionKey = null): void
{
    global $profile;

    $isPremium = (int)($profile['is_premium'] ?? 0) === 1;
    $customTitle = $title;
    $customIcon = $icon;
    $isHidden = false;

    if ($isPremium && $sectionKey !== null && !empty($profile['profile_sections_config'])) {
        $config = json_decode($profile['profile_sections_config'], true);
        if (is_array($config) && isset($config[$sectionKey])) {
            $secConf = $config[$sectionKey];
            if (!empty($secConf['hidden'])) {
                $isHidden = true;
            }
            if (isset($secConf['title']) && trim($secConf['title']) !== '') {
                $customTitle = trim($secConf['title']);
            }
            if (isset($secConf['icon']) && trim($secConf['icon']) !== '') {
                $customIcon = trim($secConf['icon']);
            }
        }
    }

    // If it's the blocks section and not customized, we don't render anything by default
    if ($sectionKey === 'blocks' && (!$isPremium || !isset($config['blocks']))) {
        return;
    }

    if ($isHidden) {
        return;
    }

    if (trim($customTitle) === '' && trim($customIcon) === '') {
        return;
    }
?>
    <div class="bio-section-heading profile-clean-heading">
        <div>
            <span>
                <?php if (trim($customIcon) !== ''): ?>
                    <?php echo profile_render_icon($customIcon, ''); ?>
                <?php endif; ?>
                <?php echo profile_h($customTitle); ?>
            </span>
            <?php if ($subtitle): ?><p><?php echo profile_h($subtitle); ?></p><?php endif; ?>
        </div>
    </div>
<?php
}

$theme = $profile ? profile_allowed_value((string)($profile['profile_theme'] ?? 'dark'), ['dark', 'light', 'auto'], 'dark') : 'dark';
$accent = $profile ? profile_normalize_hex_color($profile['accent_color'] ?? '#0f5bff') : '#0f5bff';
$secColorRaw = $profile ? trim((string)($profile['profile_secondary_color'] ?? '')) : '';
$secondaryColor = (preg_match('/^#[0-9a-fA-F]{6}$/', $secColorRaw)) ? strtolower($secColorRaw) : $accent;
$cardColor = $profile ? profile_optional_hex_color($profile['profile_card_color'] ?? '') : null;
$textColor = $profile ? profile_optional_hex_color($profile['profile_text_color'] ?? '') : null;
$linkStyle = $profile ? profile_allowed_value((string)($profile['profile_link_style'] ?? 'glass'), ['glass', 'solid', 'outline', 'neon'], 'glass') : 'glass';
$buttonShape = $profile ? profile_allowed_value((string)($profile['profile_button_shape'] ?? 'pill'), ['pill', 'rounded', 'sharp'], 'pill') : 'pill';
$cardColorCss = $cardColor ?: ($theme === 'light' ? '#ffffff' : '#080c18');
$textColorCss = $textColor ?: 'var(--text)';
if ($theme === 'auto') $theme = 'dark';

$rawLayout = $profile ? (string)($profile['profile_layout'] ?? 'standard') : 'standard';
$layoutAliases = [
    'left-tabs' => 'standard',
    'right-tabs' => 'showcase',
    'stacked' => 'clean',
    'center-split' => 'compact',
];
$rawLayout = $layoutAliases[$rawLayout] ?? $rawLayout;
$layout = profile_allowed_value($rawLayout, ['standard', 'compact', 'showcase', 'clean'], 'standard');
$layoutCss = [
    'standard' => 'standard',
    'compact' => 'center-split',
    'showcase' => 'right-tabs',
    'clean' => 'stacked',
][$layout] ?? 'standard';
$showEmbeds = $profile ? profile_flag($profile, 'profile_show_embeds', true) : false;
$embeds = $showEmbeds ? profile_list_embeds($mysqli, $profileId, true) : [];
$socialsStyle = $profile ? profile_allowed_value((string)($profile['profile_socials_style'] ?? 'cards'), ['cards', 'icons'], 'cards') : 'cards';

$displayName = $profile ? profile_display_name($profile) : 'Profilo';
$profileUrl = $profile ? 'https://cripsum.com/u/' . rawurlencode(strtolower($profile['username'])) : 'https://cripsum.com/profile.php';
$discordId = $profile ? trim((string)($profile['discord_id'] ?? '')) : '';
$customStatus = $profile ? trim((string)($profile['profile_status'] ?? '')) : '';
$musicExternalUrl = $profile ? trim((string)($profile['profile_music_url'] ?? '')) : '';
$musicMime = $profile ? trim((string)($profile['profile_music_mime'] ?? '')) : '';
$hasUploadedMusic = $profile && $musicMime !== '' && empty($profile['remove_profile_music_upload']);
$stamp = ($profile && !empty($profile['profile_updated_at'])) ? (int)strtotime((string)$profile['profile_updated_at']) : time();
$musicUrl = $hasUploadedMusic ? '/includes/get_profile_music.php?id=' . (int)$profile['id'] . '&t=' . $stamp : $musicExternalUrl;
$musicTitle = $profile ? trim((string)($profile['profile_music_title'] ?? '')) : '';
$musicArtist = $profile ? trim((string)($profile['profile_music_artist'] ?? '')) : '';
$showAudioPlayer = $profile ? ((int)($profile['profile_show_audio_player'] ?? 1) === 1) : false;
$hasMusic = $hasUploadedMusic || ($musicExternalUrl !== '' && profile_is_safe_url($musicExternalUrl, true));
$profileEffect = $profile ? profile_allowed_value((string)($profile['profile_effect'] ?? 'none'), ['none', 'cursor_glow', 'soft_particles', 'scanlines', 'ambient', 'aurora', 'gradient_waves', 'stars', 'spotlight', 'digital_noise', 'glass_rain', 'sakura_falling', 'cyber_grid', 'bg_grain'], 'none') : 'none';
$avatarRingEnabled = $profile ? ((int)($profile['avatar_ring_enabled'] ?? 1) === 1) : true;
$avatarRingStyle = $profile ? profile_allowed_value((string)($profile['avatar_ring_style'] ?? 'spin'), ['spin', 'pulse', 'orbit', 'glow', 'dual', 'rainbow', 'halo', 'neon', 'spark', 'glitch', 'none'], 'spin') : 'spin';
$avatarRingColor = $profile ? profile_normalize_hex_color($profile['avatar_ring_color'] ?: $accent) : $accent;
$backgroundUrl = $profile && !empty($profile['profile_banner_type']) ? '/includes/get_profile_banner.php?id=' . (int)$profile['id'] . '&t=' . $stamp : null;
$backgroundType = $profile && !empty($profile['profile_banner_type']) ? (string)$profile['profile_banner_type'] : 'video/mp4';

$showStats = $profile ? profile_flag($profile, 'profile_show_stats', true) : false;
$showSocials = $profile ? profile_flag($profile, 'profile_show_socials', true) : false;
$showLinks = $profile ? profile_flag($profile, 'profile_show_links', true) : false;
$showProjects = $profile ? profile_flag($profile, 'profile_show_projects', true) : false;
$showContents = $profile ? profile_flag($profile, 'profile_show_contents', true) : false;
$showBlocks = $profile ? profile_flag($profile, 'profile_show_blocks', true) : false;
$showBadges = $profile ? profile_flag($profile, 'profile_show_badges', true) : false;
$showActivity = $profile ? profile_flag($profile, 'profile_show_activity', true) : false;
$showDiscord = $profile ? profile_flag($profile, 'profile_show_discord', true) : false;
$showCharacters = $profile ? profile_flag($profile, 'profile_show_characters', true) : false;

$profileFont = $profile ? ($profile['profile_font'] ?? 'Poppins') : 'Poppins';
$hideMeta = $isPremium && $profile ? profile_flag($profile, 'profile_hide_meta', false) : false;
$showAudioBtn = $profile ? profile_flag($profile, 'profile_show_audio_btn', true) : true;
$audioBtnPosition = ($profile && !empty($profile['profile_audio_btn_position'])) ? $profile['profile_audio_btn_position'] : 'bottom-right';
$audioDefaultVolume = ($profile && isset($profile['profile_audio_default_volume']) && $profile['profile_audio_default_volume'] !== '') ? (float)$profile['profile_audio_default_volume'] : 0.18;
$borderRadius = $profile ? (int)($profile['profile_border_radius'] ?? 30) : 30;
$cardOpacity = $profile ? (int)($profile['profile_card_opacity'] ?? 68) : 68;
$cardBlur = $profile ? (int)($profile['profile_card_blur'] ?? 20) : 20;
$borderOpacity = $profile ? (int)($profile['profile_border_opacity'] ?? 100) : 100;
$borderColor = $profile ? ($profile['profile_border_color'] ?? null) : null;
$borderWidth = $profile ? (int)($profile['profile_border_width'] ?? 1) : 1;
$avatarBorder = $profile ? (int)($profile['profile_avatar_border'] ?? 1) : 1;

$uiShape = $profile ? ($profile['profile_ui_shape'] ?? 'circle') : 'circle';
$avatarShape = $profile ? ($profile['profile_avatar_shape'] ?? 'circle') : 'circle';
$socialSize = $profile ? (int)($profile['profile_social_size'] ?? 42) : 42;
$iconSpacing = $profile ? (int)($profile['profile_icon_spacing'] ?? 8) : 8;
$badgeSize = $profile ? (int)($profile['profile_badge_size'] ?? 24) : 24;
$buttonSize = $profile ? (int)($profile['profile_button_size'] ?? 48) : 48;

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
$visibleBlocks = $showBlocks ? $blocks : [];
$badgesDisplay = $profile ? ($profile['profile_badges_display'] ?? 'both') : 'both';
$badgesPosition = $profile ? ($profile['profile_badges_position'] ?? 'below_bio') : 'below_bio';

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

$discordServerInvite = $profile ? ($profile['discord_server_invite'] ?? '') : '';
$discordServerCache = $profile ? ($profile['discord_server_cache'] ?? '') : '';
$discordServerCacheTime = $profile ? (int)($profile['discord_server_cache_time'] ?? 0) : 0;

$widgetData = null;
if (!empty($discordServerInvite)) {
    if (!empty($discordServerCache) && (time() - $discordServerCacheTime < 300)) {
        $widgetData = json_decode($discordServerCache, true);
    } else {
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
            $updStmt = $mysqli->prepare("UPDATE utenti SET discord_server_cache = ?, discord_server_cache_time = ? WHERE id = ?");
            if ($updStmt) {
                $now = time();
                $updStmt->bind_param('sii', $jsonStr, $now, $profile['id']);
                $updStmt->execute();
                $updStmt->close();
            }
        } elseif (!empty($discordServerCache)) {
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
    $spotlight = ['type' => 'Contenuto', 'icon' => 'fa-solid fa-play', 'title' => $featuredContents[0]['title'], 'description' => $featuredContents[0]['description'] ?: '', 'url' => $featuredContents[0]['url'] ?: '', 'meta' => $featuredContents[0]['content_type'] ?? 'contenuto'];
} elseif ($featuredProjects) {
    $spotlight = ['type' => 'Progetto', 'icon' => 'fa-solid fa-layer-group', 'title' => $featuredProjects[0]['title'], 'description' => $featuredProjects[0]['description'] ?: '', 'url' => $featuredProjects[0]['url'] ?: '', 'meta' => $featuredProjects[0]['tech_stack'] ?: profile_status_label($featuredProjects[0]['status'] ?? 'active')];
} elseif ($featuredLinks) {
    $spotlight = ['type' => 'Link', 'icon' => $featuredLinks[0]['icon'] ?: 'fa-solid fa-link', 'title' => $featuredLinks[0]['title'], 'description' => $featuredLinks[0]['description'] ?: profile_short_url_label($featuredLinks[0]['url']), 'url' => $featuredLinks[0]['url'], 'meta' => 'in evidenza'];
}

$stats = [];
if ($profile) {
    if ((int)$profile['profile_views'] > 0) $stats[] = ['icon' => 'fa-solid fa-eye', 'value' => profile_compact_number($profile['profile_views']), 'label' => 'Views'];
    if ((int)$profile['num_achievement'] > 0) $stats[] = ['icon' => 'fa-solid fa-trophy', 'value' => profile_compact_number($profile['num_achievement']), 'label' => 'Badges'];
    if ((int)$profile['num_personaggi'] > 0) $stats[] = ['icon' => 'fa-solid fa-user-astronaut', 'value' => profile_compact_number($profile['num_personaggi']), 'label' => 'Characters'];
    if ((int)$profile['total_personaggi'] > 0) $stats[] = ['icon' => 'fa-solid fa-dice-d20', 'value' => profile_compact_number($profile['total_personaggi']), 'label' => 'Pulls'];
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
<html lang="en" <?php echo ($profile && profile_flag($profile, 'profile_click_to_enter', false)) ? 'class="click-to-enter-active"' : ''; ?>>

<head>
    <?php include __DIR__ . '/includes/head-import.php'; ?>
    <?php
    $pageTitle = 'Cripsum™ - ' . ($profile ? ($profile['display_name'] ?? $profile['username'] ?? 'Profilo') : 'Profilo');
    if ($profile && !empty($profile['profile_tab_title'])) {
        $pageTitle = $profile['profile_tab_title'];
    }
    ?>
    <title><?php echo profile_h($pageTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php cripsum_og_print($ogMeta); ?>
    <link rel="stylesheet" href="/assets/css/profile.css?v=5.8.4">
    <style>
        .profile-dropdown-item--gift,
        .profile-dropdown-item--gift * {
            cursor: pointer !important;
        }
        .profile-dropdown-item--gift {
            color: #eab308 !important;
            font-weight: 700 !important;
            background: rgba(234, 179, 8, 0.08) !important;
            border: 1px dashed rgba(234, 179, 8, 0.4) !important;
            margin: 6px 0 !important;
            box-shadow: 0 0 12px rgba(234, 179, 8, 0.15);
            animation: giftPulse 2s infinite ease-in-out;
        }
        .profile-dropdown-item--gift i {
            color: #eab308 !important;
            filter: drop-shadow(0 0 3px rgba(234, 179, 8, 0.5));
        }
        .profile-dropdown-item--gift:hover {
            background: rgba(234, 179, 8, 0.16) !important;
            color: #fff !important;
            box-shadow: 0 0 16px rgba(234, 179, 8, 0.3) !important;
        }
        .profile-dropdown-item--gift:hover i {
            color: #fff !important;
        }
        @keyframes giftPulse {
            0%, 100% {
                border-color: rgba(234, 179, 8, 0.4);
                box-shadow: 0 0 12px rgba(234, 179, 8, 0.15);
            }
            50% {
                border-color: rgba(234, 179, 8, 0.8);
                box-shadow: 0 0 18px rgba(234, 179, 8, 0.35);
            }
        }
    </style>
    <script src="/assets/js/profile.js?v=5.8.4" defer></script>
    <?php if (isset($_GET['preview_mode'])): ?>
        <style>
            .profile-smart-page {
                padding-top: 1.5rem !important;
            }

            body {
                overflow-y: auto !important;
            }
        </style>
    <?php endif; ?>
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
    <?php
    $cornerStyle = $profile['profile_corner_style'] ?? 'circle';
    $cornerStyleCustom = (int)($profile['profile_corner_style_custom'] ?? 8);
    $profileCornerRadius = '100px';
    if ($cornerStyle === 'rounded') {
        $profileCornerRadius = '12px';
    } elseif ($cornerStyle === 'soft') {
        $profileCornerRadius = '6px';
    } elseif ($cornerStyle === 'square') {
        $profileCornerRadius = '0px';
    } elseif ($cornerStyle === 'custom') {
        $profileCornerRadius = $cornerStyleCustom . 'px';
    }
    ?>
    <style>
        .bio-v2-body {
            --radius-lg: <?php echo $borderRadius; ?>px !important;
            --radius-md: <?php echo round($borderRadius * 0.73); ?>px !important;
            --radius-sm: <?php echo round($borderRadius * 0.47); ?>px !important;
            --profile-corner-radius: <?php echo $profileCornerRadius; ?> !important;

            --profile-card-opacity: <?php echo $cardOpacity / 100; ?> !important;
            --profile-card-blur: <?php echo $cardBlur; ?>px !important;
            --profile-border-opacity: <?php echo $borderOpacity / 100; ?> !important;
            --profile-border-opacity-percent: <?php echo $borderOpacity; ?>% !important;
            --profile-border-glow-alpha: <?php echo round(($borderOpacity / 100) * 0.34, 3); ?> !important;
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

        /* Scroll Snap Layout
           body.snap-active is added by JS only when snap is on AND there are 2+ slides.
           This prevents the single-card case from breaking the layout. */
        @media (min-width: 768px) {
            body.snap-active {
                overflow: hidden !important;
            }

            body.snap-active #bioPage {
                position: relative !important;
                height: 100vh !important;
                max-height: 100vh !important;
                width: 100% !important;
                max-width: 100% !important;
                overflow-y: scroll !important;
                overflow-x: hidden !important;
                display: block !important;
                padding: 0 !important;
                margin: 0 !important;
                scroll-behavior: auto !important;
                grid-template-columns: none !important;
                /* Hide scrollbar visually */
                scrollbar-width: none !important;
                -ms-overflow-style: none !important;
            }

            body.snap-active #bioPage::-webkit-scrollbar {
                display: none !important;
            }

            body.snap-active .profile-snap-slide-wrapper {
                min-height: 100vh !important;
                height: auto !important;
                width: 100% !important;
                margin: 0 !important;
                box-sizing: border-box !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                padding: 2rem 1rem !important;
                position: relative !important;
            }

            body.snap-active.public-profile-body #bioPage .profile-smart-hero-wrapper {
                top: 0 !important;
                position: relative !important;
                align-self: auto !important;
                margin: 0 !important;
            }

            body.snap-active .profile-split-column,
            body.snap-active .profile-smart-content {
                display: contents !important;
            }

            /* Constrain child cards/sections inside snap wrappers – matches stacked layout width */
            body.snap-active .profile-snap-slide-wrapper>.bio-card,
            body.snap-active .profile-snap-slide-wrapper>section.bio-card,
            body.snap-active .profile-snap-slide-wrapper>.bio-stats-grid,
            body.snap-active .profile-snap-slide-wrapper>.profile-split-item,
            body.snap-active .profile-snap-slide-wrapper>section,
            body.snap-active .profile-snap-slide-wrapper>div {
                width: min(660px, calc(100% - 32px)) !important;
                max-width: 660px !important;
                margin: 0 auto !important;
                box-sizing: border-box !important;
            }

            body.snap-active .profile-snap-slide-wrapper>.bio-card,
            body.snap-active .profile-snap-slide-wrapper>section.bio-card,
            body.snap-active .profile-snap-slide-wrapper>section {
                max-height: none !important;
                overflow-y: visible !important;
            }

            /* Ensure embeds inside snap cards render at full width */
            body.snap-active .profile-embeds-grid {
                width: 100% !important;
            }

            body.snap-active .profile-embed-wrapper {
                width: 100% !important;
            }

            body.snap-active .profile-embed-wrapper iframe {
                width: 100% !important;
            }

            body.snap-active .bio-stats-grid {
                width: min(660px, calc(100% - 32px)) !important;
                max-width: 660px !important;
                margin: 0 auto !important;
                display: grid !important;
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 1rem !important;
                justify-content: center !important;
                align-content: center !important;
            }
        }

        /* Scroll Snap Pagination Dots */
        .profile-snap-dots {
            position: fixed !important;
            right: 24px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            z-index: 99999 !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 16px !important;
            padding: 14px 10px !important;
            border-radius: 100px !important;
            background: rgba(8, 12, 24, 0.3) !important;
            backdrop-filter: blur(16px) !important;
            -webkit-backdrop-filter: blur(16px) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3) !important;
            transition: opacity 0.4s ease, transform 0.4s ease !important;
            pointer-events: auto !important;
        }

        .profile-snap-dot {
            width: 8px !important;
            height: 8px !important;
            border-radius: 50% !important;
            background: rgba(255, 255, 255, 0.3) !important;
            border: none !important;
            padding: 0 !important;
            cursor: pointer !important;
            position: relative !important;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
            opacity: 0.6 !important;
        }

        .profile-snap-dot:hover {
            background: rgba(255, 255, 255, 0.8) !important;
            opacity: 1 !important;
            transform: scale(1.2) !important;
        }

        .profile-snap-dot.is-active {
            background: var(--accent, #0f5bff) !important;
            height: 22px !important;
            border-radius: 100px !important;
            opacity: 1 !important;
            transform: scale(1) !important;
            box-shadow: 0 0 12px var(--accent, #0f5bff) !important;
        }

        /* Tooltips */
        .profile-snap-dot::after {
            content: attr(data-label) !important;
            position: absolute !important;
            right: 24px !important;
            top: 50% !important;
            transform: translateY(-50%) translateX(10px) !important;
            background: rgba(8, 12, 24, 0.85) !important;
            color: #fff !important;
            padding: 4px 10px !important;
            border-radius: 6px !important;
            font-size: 0.7rem !important;
            font-weight: 600 !important;
            white-space: nowrap !important;
            opacity: 0 !important;
            pointer-events: none !important;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2) !important;
        }

        .profile-snap-dot:hover::after {
            opacity: 1 !important;
            transform: translateY(-50%) translateX(0) !important;
        }

        @media (max-width: 767px) {
            .profile-snap-dots {
                display: none !important;
            }
        }

        /* Background Grain Effect */
        body[data-bg-grain="1"]::after {
            content: "";
            position: fixed;
            top: -50%;
            left: -50%;
            right: -50%;
            bottom: -50%;
            width: 200%;
            height: 200%;
            background: transparent url('data:image/svg+xml,%3Csvg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"%3E%3Cfilter id="noiseFilter"%3E%3CfeTurbulence type="fractalNoise" baseFrequency="0.85" numOctaves="3" stitchTiles="stitch"/%3E%3C/filter%3E%3Crect width="100%25" height="100%25" filter="url(%23noiseFilter)" opacity="0.06"/%3E%3C/svg%3E') repeat;
            opacity: 0.18;
            pointer-events: none;
            z-index: 99999;
            animation: grain-animation 8s steps(10) infinite;
        }

        @keyframes grain-animation {

            0%,
            100% {
                transform: translate(0, 0);
            }

            10% {
                transform: translate(-5%, -10%);
            }

            20% {
                transform: translate(-15%, 5%);
            }

            30% {
                transform: translate(7%, -25%);
            }

            40% {
                transform: translate(-5%, 25%);
            }

            50% {
                transform: translate(-15%, 10%);
            }

            60% {
                transform: translate(15%, 0%);
            }

            70% {
                transform: translate(0%, 15%);
            }

            80% {
                transform: translate(3%, 35%);
            }

            90% {
                transform: translate(-10%, 10%);
            }
        }

        /* Custom Cursor */
        body[data-cursor-custom-url],
        body[data-cursor-custom-url] a,
        body[data-cursor-custom-url] button,
        body[data-cursor-custom-url] select,
        body[data-cursor-custom-url] input,
        body[data-cursor-custom-url] textarea,
        body[data-cursor-custom-url] [role="button"] {
            cursor: var(--cursor-custom-url) !important;
        }

        /* Card Tags */
        .profile-card-tag {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 0.15rem 0.45rem;
            border-radius: 4px;
            margin-left: 0.5rem;
            vertical-align: middle;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            line-height: 1;
        }

        /* Music Player Styles */
        /* 1. COMPACT ROW STYLE (retro) */
        body[data-music-theme="retro"] .bio-audio {
            display: grid !important;
            grid-template-columns: auto 1fr auto !important;
            align-items: center !important;
            gap: 0.6rem 1rem !important;
            padding: 0.75rem 1rem !important;
        }

        body[data-music-theme="retro"] .bio-audio__header {
            display: contents !important;
        }

        body[data-music-theme="retro"] .bio-audio__header>button.js-profile-audio-toggle {
            grid-column: 1 !important;
            grid-row: 1 !important;
            margin: 0 !important;
        }

        body[data-music-theme="retro"] .bio-audio__header>div {
            grid-column: 2 !important;
            grid-row: 1 !important;
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            gap: 0.25rem 0.5rem !important;
        }

        body[data-music-theme="retro"] .bio-audio__header>div small {
            display: none !important;
        }

        body[data-music-theme="retro"] .bio-audio__header>div strong {
            font-size: 0.85rem !important;
            margin: 0 !important;
        }

        body[data-music-theme="retro"] .bio-audio__header>div span.profile-artist-span {
            font-size: 0.76rem !important;
            color: var(--muted) !important;
            margin: 0 !important;
            display: inline-block !important;
        }

        body[data-music-theme="retro"] .bio-audio__header>div span.profile-artist-span::before {
            content: "• " !important;
            margin-right: 0.25rem !important;
            opacity: 0.6 !important;
        }

        body[data-music-theme="retro"] .bio-audio__progress {
            grid-column: 1 / -1 !important;
            grid-row: 2 !important;
            margin: 0 !important;
            width: 100% !important;
        }

        body[data-music-theme="retro"] .bio-audio__bottom {
            grid-column: 3 !important;
            grid-row: 1 !important;
            margin: 0 !important;
            display: flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
        }

        body[data-music-theme="retro"] .bio-audio__bottom input[type="range"] {
            width: 60px !important;
        }

        /* 2. CENTERED PILL STYLE (cyberpunk) */
        body[data-music-theme="cyberpunk"] .bio-audio {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            text-align: center !important;
            padding: 1.5rem !important;
            border-radius: 28px !important;
        }

        body[data-music-theme="cyberpunk"] .bio-audio__header {
            flex-direction: column !important;
            align-items: center !important;
            gap: 0.75rem !important;
            margin-bottom: 1rem !important;
            width: 100% !important;
        }

        body[data-music-theme="cyberpunk"] .bio-audio__header>div {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            text-align: center !important;
        }

        body[data-music-theme="cyberpunk"] .bio-audio__header>div small {
            margin-bottom: 0.35rem !important;
            letter-spacing: 0.12em !important;
        }

        body[data-music-theme="cyberpunk"] .bio-audio__header>div strong {
            font-size: 1.05rem !important;
            justify-content: center !important;
        }

        body[data-music-theme="cyberpunk"] .bio-audio__header>button.js-profile-audio-toggle {
            width: 52px !important;
            height: 52px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            background: rgba(var(--accent-rgb), 0.1) !important;
            border: 1px solid rgba(var(--accent-rgb), 0.25) !important;
            order: -1 !important;
            margin-bottom: 0.5rem !important;
            transition: all 0.3s ease !important;
        }

        body[data-music-theme="cyberpunk"] .bio-audio__header>button.js-profile-audio-toggle:hover {
            background: rgba(var(--accent-rgb), 0.2) !important;
            transform: scale(1.05) !important;
        }

        body[data-music-theme="cyberpunk"] .bio-audio__header>button.js-profile-audio-toggle i {
            font-size: 1.15rem !important;
            margin-left: 2px !important;
        }

        body[data-music-theme="cyberpunk"] .bio-audio__header>button.js-profile-audio-toggle:has(.fa-pause) i {
            margin-left: 0 !important;
        }

        body[data-music-theme="cyberpunk"] .bio-audio__progress {
            width: 100% !important;
            margin-bottom: 0.85rem !important;
            justify-content: center !important;
        }

        body[data-music-theme="cyberpunk"] .bio-audio__bottom {
            width: 100% !important;
            justify-content: center !important;
            gap: 0.5rem !important;
        }

        body[data-music-theme="cyberpunk"] .bio-audio__bottom input[type="range"] {
            max-width: 110px !important;
        }

        /* 3. VINYL PLAYER STYLE (synthwave) */
        body[data-music-theme="synthwave"] .bio-audio {
            display: grid !important;
            grid-template-columns: auto 1fr !important;
            align-items: center !important;
            gap: 1rem 1.25rem !important;
            padding: 1.25rem !important;
        }

        body[data-music-theme="synthwave"] .bio-audio::before {
            content: "" !important;
            display: block !important;
            width: 80px !important;
            height: 80px !important;
            border-radius: 50% !important;
            background: radial-gradient(circle,
                    var(--accent) 6%,
                    #0b0c10 7%,
                    #0b0c10 22%,
                    #1f2833 23%,
                    #0b0c10 38%,
                    #1f2833 40%,
                    #0b0c10 56%,
                    rgba(var(--accent-rgb), 0.25) 57%,
                    #0b0c10 70%,
                    rgba(255, 255, 255, 0.05) 71%) !important;
            border: 2px solid rgba(255, 255, 255, 0.08) !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5), 0 0 0 4px rgba(var(--accent-rgb), 0.05) !important;
            grid-column: 1 !important;
            grid-row: 1 / span 3 !important;
            animation: spin-vinyl 4s linear infinite !important;
            animation-play-state: paused !important;
        }

        body[data-music-theme="synthwave"] .bio-audio.audio-playing::before {
            animation-play-state: running !important;
        }

        @keyframes spin-vinyl {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        body[data-music-theme="synthwave"] .bio-audio__header {
            grid-column: 2 !important;
            grid-row: 1 !important;
            margin-bottom: 0 !important;
            align-items: center !important;
        }

        body[data-music-theme="synthwave"] .bio-audio__progress {
            grid-column: 2 !important;
            grid-row: 2 !important;
            margin-bottom: 0 !important;
            width: 100% !important;
        }

        body[data-music-theme="synthwave"] .bio-audio__bottom {
            grid-column: 2 !important;
            grid-row: 3 !important;
            margin-bottom: 0 !important;
            width: 100% !important;
        }

        body[data-music-theme="synthwave"] .bio-audio__bottom input[type="range"] {
            width: 100% !important;
            max-width: 100px !important;
        }

        @media (max-width: 480px) {
            body[data-music-theme="synthwave"] .bio-audio {
                grid-template-columns: 1fr !important;
                justify-items: center !important;
                text-align: center !important;
            }

            body[data-music-theme="synthwave"] .bio-audio::before {
                grid-column: 1 !important;
                grid-row: 1 !important;
            }

            body[data-music-theme="synthwave"] .bio-audio__header {
                grid-column: 1 !important;
                grid-row: 2 !important;
                width: 100% !important;
            }

            body[data-music-theme="synthwave"] .bio-audio__progress {
                grid-column: 1 !important;
                grid-row: 3 !important;
                width: 100% !important;
            }

            body[data-music-theme="synthwave"] .bio-audio__bottom {
                grid-column: 1 !important;
                grid-row: 4 !important;
                width: 100% !important;
                justify-content: center !important;
            }
        }
    </style>
</head>

<body
    class="bio-v2-body public-profile-body profile-border-style-<?php echo profile_h($profile['profile_border_style'] ?? 'thin'); ?><?php echo ($profile && profile_flag($profile, 'profile_click_to_enter', false)) ? ' click-to-enter-active' : ''; ?>"
    data-theme="<?php echo profile_h($theme); ?>"
    data-accent="<?php echo profile_h($accent); ?>"
    data-profile-url="<?php echo profile_h($profileUrl); ?>"
    data-discord-id="<?php echo profile_h($showDiscord ? $discordId : ''); ?>"
    data-profile-effect="<?php echo profile_h($profileEffect); ?>"
    data-profile-link-style="<?php echo profile_h($linkStyle); ?>"
    data-profile-button-shape="<?php echo profile_h($buttonShape); ?>"
    data-profile-socials-style="<?php echo profile_h($socialsStyle); ?>"
    data-profile-layout="<?php echo profile_h($layoutCss); ?>"
    data-avatar-shape="<?php echo profile_h($avatarShape); ?>"
    data-avatar-border="<?php echo $avatarBorder; ?>"
    data-tab-title="<?php echo profile_h($pageTitle); ?>"
    data-tab-animation="<?php echo profile_h($profile['profile_tab_animation'] ?? 'static'); ?>"
    data-tab-animation-speed="<?php echo (int)($profile['profile_tab_animation_speed'] ?? 1000); ?>"
    data-tab-animation-text="<?php echo profile_h($profile['profile_tab_animation_text'] ?? ''); ?>"
    data-cursor-effect="<?php echo (int)($profile['is_premium'] ?? 0) === 1 ? profile_h($profile['profile_cursor_effect'] ?? 'none') : 'none'; ?>"
    data-layout-snap="<?php echo (int)($profile['is_premium'] ?? 0) === 1 && (int)($profile['profile_layout_snap'] ?? 0) === 1 ? '1' : '0'; ?>"
    data-bg-grain="<?php echo (int)($profile['is_premium'] ?? 0) === 1 && ((int)($profile['profile_bg_grain'] ?? 0) === 1 || $profileEffect === 'bg_grain') ? '1' : '0'; ?>"
    data-music-theme="<?php echo (int)($profile['is_premium'] ?? 0) === 1 ? profile_h($profile['profile_music_theme'] ?? 'default') : 'default'; ?>"
    data-cursor-custom-url="<?php echo (int)($profile['is_premium'] ?? 0) === 1 && !empty($profile['profile_cursor_custom_url']) ? profile_h($profile['profile_cursor_custom_url']) : ''; ?>"
    style="--profile-ring: <?php echo profile_h($avatarRingColor); ?>; --accent-2: <?php echo profile_h($secondaryColor); ?>; --profile-card-color: <?php echo profile_h($cardColorCss); ?>; --profile-text-color: <?php echo profile_h($textColorCss); ?>; <?php if ((int)($profile['is_premium'] ?? 0) === 1 && !empty($profile['profile_cursor_custom_url'])): ?>--cursor-custom-url: url('<?php echo profile_h($profile['profile_cursor_custom_url']); ?>'), auto !important;<?php endif; ?>">

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
    if (!isset($_GET['preview_mode'])) {
        if (file_exists(__DIR__ . '/includes/navbar-bio.php')) include __DIR__ . '/includes/navbar-bio.php';
        else include __DIR__ . '/includes/navbar.php';
        if (file_exists(__DIR__ . '/includes/impostazioni.php')) include __DIR__ . '/includes/impostazioni.php';
    }
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
        <?php
        $tiltAttrs = 'data-tilt-enabled="' . (int)($profile['tilt_enabled'] ?? 1) . '" ' .
            'data-tilt-max="' . (int)($profile['tilt_max'] ?? 15) . '" ' .
            'data-tilt-glare="' . (float)($profile['tilt_glare'] ?? 0.0) . '" ' .
            'data-tilt-zoom="' . (float)($profile['tilt_zoom'] ?? 1.05) . '" ' .
            'data-tilt-speed="' . (int)($profile['tilt_speed'] ?? 400) . '"';
        ?>
        <main class="bio-page profile-smart-page <?php echo (!$hasRightContent) ? 'profile-smart-page--single' : ''; ?> layout-<?php echo profile_h($layoutCss); ?>" id="bioPage">
            <div class="profile-smart-hero-wrapper">
                <section class="bio-hero bio-card profile-smart-hero js-tilt-card js-reveal" aria-label="Public Profile" <?php echo $tiltAttrs; ?>>
                    <div class="profile-hero-actions-top">
                        <?php if ($showStats): ?>
                            <?php if ($isOnline): ?>
                                <span class="bio-pill bio-pill--live"><span class="bio-dot"></span>online</span>
                            <?php elseif ($customStatus): ?>
                                <span class="bio-pill"><i class="fa-solid fa-signal"></i><?php echo profile_h($customStatus); ?></span>
                            <?php else: ?>
                                <div></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="bio-pill"><i class="fa-solid fa-eye"></i><?php echo profile_compact_number($profile['profile_views'] ?? 0); ?> <?php echo ($lang === 'it') ? 'visite' : 'views'; ?></span>
                        <?php endif; ?>

                        <?php if (!isset($_GET['preview_mode'])): ?>
                            <div class="profile-dropdown-wrap">
                                <button class="bio-small-button js-profile-dropdown-trigger" type="button" aria-label="Menu" aria-expanded="false">
                                    <i class="fa-solid fa-ellipsis-h"></i>
                                </button>
                                <div class="profile-dropdown-menu">
                                    <?php if ($canEdit): ?>
                                        <a class="profile-dropdown-item" href="/it/edit-profile<?php echo profile_is_staff() && !$isOwnProfile ? '?user_id=' . (int)$profile['id'] : ''; ?>">
                                            <i class="fa-solid fa-pen"></i>
                                            <span>Edit profile</span>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!$isPremium && !$isOwnProfile): ?>
                                        <a class="profile-dropdown-item profile-dropdown-item--gift" href="/<?php echo $lang; ?>/checkout-premium.php?gift_to=<?php echo urlencode($profile['username']); ?>">
                                            <i class="fa-solid fa-gift"></i>
                                            <span>Gift Premium</span>
                                        </a>
                                    <?php endif; ?>
                                    <a class="profile-dropdown-item" href="/<?php echo $lang; ?>/home">
                                        <i class="fa-solid fa-home"></i>
                                        <span>Home Page</span>
                                    </a>
                                    <button class="profile-dropdown-item js-open-search" type="button">
                                        <i class="fa-solid fa-search"></i>
                                        <span>Search users</span>
                                    </button>
                                    <button class="profile-dropdown-item js-open-navigation" type="button">
                                        <i class="fa-solid fa-compass"></i>
                                        <span>Open Navigation</span>
                                    </button>
                                    <button class="profile-dropdown-item js-copy-profile" type="button">
                                        <i class="fa-solid fa-link"></i>
                                        <span>Copy link</span>
                                    </button>
                                    <button class="profile-dropdown-item js-share-profile" type="button">
                                        <i class="fa-solid fa-share-nodes"></i>
                                        <span>Share Profile</span>
                                    </button>
                                    <button class="profile-dropdown-item js-open-report" type="button">
                                        <i class="fa-solid fa-flag"></i>
                                        <span>Report Profile</span>
                                    </button>
                                    <button class="profile-dropdown-item js-open-qr" type="button">
                                        <i class="fa-solid fa-qrcode"></i>
                                        <span>QR Code</span>
                                    </button>
                                    <button class="profile-dropdown-item js-theme-toggle" type="button">
                                        <i class="fa-solid fa-moon"></i>
                                        <span class="theme-label-text">Dark Mode</span>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
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
                                        <i class="fa-solid fa-medal"></i>
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

                        <?php
                        $profileTags = json_decode($profile['profile_tags_json'] ?? '[]', true) ?: [];
                        if (!empty($profileTags)):
                        ?>
                            <div class="profile-tags-container" style="display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center; margin-top: 0.75rem;">
                                <?php foreach ($profileTags as $tag):
                                    $tagText = $tag['text'] ?? '';
                                    if (trim($tagText) === '') continue;
                                    $tagIcon = $tag['icon'] ?? '';
                                    $tagColor = $tag['color'] ?? '';
                                    $tagGradient = $tag['gradient'] ?? '';

                                    $tagStyle = '';
                                    if (!empty($tagColor)) {
                                        if (!empty($tagGradient)) {
                                            $tagStyle = 'background: linear-gradient(135deg, ' . $tagColor . ', ' . $tagGradient . ') !important; border-color: transparent !important; color: #fff !important;';
                                        } else {
                                            $tagStyle = 'background: ' . $tagColor . ' !important; border-color: transparent !important; color: #fff !important;';
                                        }
                                    }
                                ?>
                                    <span class="profile-tag-pill" style="<?php echo $tagStyle; ?>">
                                        <?php if (!empty($tagIcon)): ?>
                                            <i class="<?php echo profile_h($tagIcon); ?>" style="margin-right: 4px;"></i>
                                        <?php endif; ?>
                                        <?php echo profile_h($tagText); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($badgesPosition === 'below_bio' || empty($badgesPosition)) echo $renderMiniBadgesHtml; ?>

                    <?php if ($visibleSocials): ?>
                        <?php if ($socialsStyle === 'icons'): ?>
                            <div class="bio-social-icons-row" aria-label="Social">
                                <?php foreach ($visibleSocials as $social): ?>
                                    <?php
                                    $socialIcon = ((int)($profile['is_premium'] ?? 0) === 1 && !empty($social['icon'])) ? $social['icon'] : profile_social_icon_class($social['platform']);
                                    ?>
                                    <a class="bio-social-icon bio-social-icon--<?php echo profile_h($social['platform']); ?>" href="<?php echo profile_h($social['url']); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo profile_h($social['label'] ?: ucfirst($social['platform'])); ?>">
                                        <?php echo profile_render_icon($socialIcon, 'fa-solid fa-link'); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="bio-social-grid profile-social-compact" aria-label="Social">
                                <?php foreach ($visibleSocials as $social): ?>
                                    <?php
                                    $socialIcon = ((int)($profile['is_premium'] ?? 0) === 1 && !empty($social['icon'])) ? $social['icon'] : profile_social_icon_class($social['platform']);
                                    ?>
                                    <a class="bio-social" href="<?php echo profile_h($social['url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <span class="bio-social__icon"><?php echo profile_render_icon($socialIcon, 'fa-solid fa-link'); ?></span>
                                        <span>
                                            <strong><?php echo profile_h($social['label'] ?: ucfirst($social['platform'])); ?></strong>
                                            <small><?php echo profile_h($social['display_username'] ?: profile_short_url_label($social['url'])); ?></small>
                                        </span>
                                        <i class="fa-solid fa-arrow-up-right-from-square bio-social__arrow"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?> <?php if ($showDiscord && $discordId): ?>
                        <div class="profile-discord-left js-reveal" aria-label="Attività Discord">
                            <div class="profile-discord-left__title">
                                <span><i class="fa-brands fa-discord"></i>Discord</span>
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
                                <span><i class="fa-brands fa-discord"></i><?php echo (isset($lang) && $lang === 'en') ? 'Discord Server' : 'Server Discord'; ?></span>
                            </div>
                            <div class="ds-card profile-discord-server-section" style="padding: 1.25rem;">
                                <div class="profile-discord-server-card">
                                    <div class="profile-discord-server-left">
                                        <?php if ($discordIconUrl): ?>
                                            <img class="profile-discord-server-icon" src="<?php echo htmlspecialchars($discordIconUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($discordServerName, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                                        <?php else: ?>
                                            <div class="profile-discord-server-icon-fallback">
                                                <i class="fa-brands fa-discord"></i>
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
                                        <i class="fa-brands fa-discord"></i>
                                        <span><?php echo (isset($lang) && $lang === 'en') ? 'Join' : 'Entra'; ?></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!$hasAnyPublicContent && $isOwnProfile && !isset($_GET['preview_mode'])): ?>
                        <div class="profile-owner-nudge">
                            <i class="fa-solid fa-plus"></i>
                            <span>Add links, badges, or content to fill out the bio.</span>
                            <a href="/en/edit-profile">Edit</a>
                        </div>
                    <?php endif; ?>

                    <?php
                    $isPreview = isset($_GET['preview_mode']);
                    $hasClickToEnter = $profile && profile_flag($profile, 'profile_click_to_enter', false);
                    ?>
                    <?php if ($hasMusic && $showAudioPlayer): ?>
                        <div class="bio-audio profile-audio-player" data-audio-player>
                            <audio id="profileAudio" preload="metadata" data-default-volume="<?php echo $audioDefaultVolume; ?>" src="<?php echo profile_h($musicUrl); ?>"></audio>
                            <div class="bio-audio__header">
                                <div>
                                    <small>Audio</small>
                                    <strong><i class="fa-solid fa-music"></i><?php echo profile_h($musicTitle ?: 'Profile Song'); ?></strong>
                                    <span class="profile-artist-span" style="<?php echo $musicArtist ? '' : 'display: none;'; ?>"><?php echo profile_h($musicArtist); ?></span>
                                </div>
                                <button class="bio-small-button js-profile-audio-toggle" type="button" aria-label="Play pause"><i id="profileAudioIcon" class="fa-solid fa-play"></i></button>
                            </div>
                            <div class="bio-audio__progress">
                                <span id="profileAudioCurrent">0:00</span>
                                <input id="profileAudioProgress" type="range" min="0" max="100" step="0.1" value="0" aria-label="Audio progress">
                                <span id="profileAudioTotal">0:00</span>
                            </div>
                            <div class="bio-audio__bottom">
                                <button class="bio-small-button js-profile-volume-toggle" type="button" aria-label="Mute"><i id="profileVolumeIcon" class="fa-solid fa-volume-low"></i></button>
                                <input id="profileVolumeSlider" type="range" min="0" max="1" step="0.01" value="0.18" aria-label="Volume">
                            </div>
                        </div>
                    <?php elseif ($hasMusic && !$showAudioPlayer): ?>
                        <audio
                            id="profileAudio"
                            class="profile-hidden-audio"
                            preload="auto"
                            <?php if (!$hasClickToEnter): ?>autoplay<?php endif; ?>
                            loop
                            data-autoplay="1"
                            data-default-volume="<?php echo $audioDefaultVolume; ?>"
                            src="<?php echo profile_h($musicUrl); ?>"></audio>
                        <?php if ($isPreview): ?>
                            <div class="bio-audio profile-audio-player" data-audio-player style="display: none;">
                                <div class="bio-audio__header">
                                    <div>
                                        <small>Audio</small>
                                        <strong><i class="fa-solid fa-music"></i><?php echo profile_h($musicTitle ?: 'Profile Song'); ?></strong>
                                        <span class="profile-artist-span" style="<?php echo $musicArtist ? '' : 'display: none;'; ?>"><?php echo profile_h($musicArtist); ?></span>
                                    </div>
                                    <button class="bio-small-button js-profile-audio-toggle" type="button" aria-label="Play pause"><i class="fa-solid fa-play"></i></button>
                                </div>
                                <div class="bio-audio__progress">
                                    <span>0:00</span>
                                    <input type="range" min="0" max="100" step="0.1" value="0" aria-label="Audio progress">
                                    <span>0:00</span>
                                </div>
                                <div class="bio-audio__bottom">
                                    <button class="bio-small-button js-profile-volume-toggle" type="button" aria-label="Mute"><i class="fa-solid fa-volume-low"></i></button>
                                    <input type="range" min="0" max="1" step="0.01" value="0.18" aria-label="Volume">
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($isPreview): ?>
                        <audio id="profileAudio" preload="metadata"></audio>
                        <div class="bio-audio profile-audio-player" data-audio-player style="display: none;">
                            <div class="bio-audio__header">
                                <div>
                                    <small>Audio</small>
                                    <strong><i class="fa-solid fa-music"></i>Profile Song</strong>
                                    <span class="profile-artist-span" style="display: none;"></span>
                                </div>
                                <button class="bio-small-button js-profile-audio-toggle" type="button" aria-label="Play pause"><i class="fa-solid fa-play"></i></button>
                            </div>
                            <div class="bio-audio__progress">
                                <span>0:00</span>
                                <input type="range" min="0" max="100" step="0.1" value="0" aria-label="Audio progress">
                                <span>0:00</span>
                            </div>
                            <div class="bio-audio__bottom">
                                <button class="bio-small-button js-profile-volume-toggle" type="button" aria-label="Mute"><i class="fa-solid fa-volume-low"></i></button>
                                <input type="range" min="0" max="1" step="0.01" value="0.18" aria-label="Volume">
                            </div>
                    <?php endif; ?>

                    <script>
                    (() => {
                        const audio = document.getElementById('profileAudio');
                        if (audio) {
                            const profileUrl = document.body.dataset.profileUrl || window.location.pathname || 'global';
                            const volumeKey = 'cripsum.profile.audioVolume.' + profileUrl;
                            const defaultVolume = <?php echo $audioDefaultVolume; ?>;
                            const savedVolume = localStorage.getItem(volumeKey) !== null
                                ? Number(localStorage.getItem(volumeKey))
                                : defaultVolume;
                            audio.volume = Math.min(Math.max(savedVolume, 0), 1);
                        }
                    })();
                    </script>

                    <?php if (!$hideMeta): ?>
                    <div class="profile-small-meta">
                        <?php if (!$showStats): ?>
                            <?php if ($isOnline): ?>
                                <span class="bio-pill bio-pill--live" style="margin-right: 0.4rem; padding: 0.2rem 0.5rem;"><span class="bio-dot"></span>online</span>
                            <?php elseif ($customStatus): ?>
                                <span class="bio-pill" style="margin-right: 0.4rem; padding: 0.2rem 0.5rem;"><i class="fa-solid fa-signal"></i><?php echo profile_h($customStatus); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <span><i class="fa-solid fa-calendar"></i><?php echo date('d/m/Y', strtotime($profile['data_creazione'])); ?></span>
                        <?php if (!$isOnline && $lastSeen): ?><span><i class="fa-solid fa-clock"></i><?php echo profile_h(profile_time_ago($lastSeen)); ?></span><?php endif; ?>
                        <?php if ($showDiscord && $discordId): ?><span><i class="fa-brands fa-discord"></i>Discord</span><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </section>
            </div>

            <?php if ($hasRightContent): ?>
                <section class="bio-content profile-smart-content <?php echo $layoutCss === 'center-split' ? 'profile-smart-content--split' : ''; ?>" aria-label="Contenuti profilo">
                    <?php
                    $spotlightHtml = '';
                    if ($spotlight) {
                        ob_start();
                    ?>
                        <section class="bio-card bio-featured profile-spotlight js-reveal js-tilt-card" <?php echo $tiltAttrs; ?> data-section-type="featured" data-section-title="<?php echo profile_h($spotlight['title'] ?: 'Featured'); ?>">
                            <a class="profile-spotlight-link" href="<?php echo profile_h($spotlight['url'] ?: '#'); ?>" <?php echo $spotlight['url'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                <span class="profile-spotlight-icon"><?php echo profile_render_icon($spotlight['icon'], 'fa-solid fa-star'); ?></span>
                                <span class="profile-spotlight-content">
                                    <small><?php echo profile_h($spotlight['type']); ?> Featured</small>
                                    <strong><?php echo profile_h($spotlight['title']); ?></strong>
                                    <?php if ($spotlight['description']): ?><em><?php echo profile_h($spotlight['description']); ?></em><?php endif; ?>
                                    <?php if ($spotlight['meta']): ?><span><?php echo profile_h($spotlight['meta']); ?></span><?php endif; ?>
                                </span>
                                <?php if ($spotlight['url']): ?><i class="fa-solid fa-arrow-up-right-from-square"></i><?php endif; ?>
                            </a>
                        </section>
                    <?php
                        $spotlightHtml = ob_get_clean();
                    }
                    ?>

                    <?php
                    $sectionsHtml = [];

                    // 1. Links
                    ob_start();
                    if ($featuredLinks || $normalLinks): ?>
                        <section class="bio-card bio-featured js-reveal js-tilt-card" <?php echo $tiltAttrs; ?> data-section-type="links" data-section-title="<?php echo profile_h(profile_get_section_title('links', 'Link')); ?>">
                            <?php profile_render_section_heading('fa-solid fa-link', 'Link', null, 'links'); ?>
                            <div class="bio-featured-grid profile-link-grid profile-link-count-<?php echo count(array_merge($featuredLinks, $normalLinks)); ?>">
                                <?php foreach (array_merge($featuredLinks, $normalLinks) as $item): ?>
                                    <?php
                                    $buttonStyle = profile_allowed_value((string)($item['button_style'] ?? 'card'), ['card', 'compact', 'icon'], 'card');
                                    $linkTitle = (string)($item['title'] ?? 'Link');
                                    ?>
                                    <a class="bio-featured-link profile-link-button button-style-<?php echo profile_h($buttonStyle); ?> <?php echo !empty($item['is_featured']) ? 'is-pinned' : ''; ?>" href="<?php echo profile_h($item['url']); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo profile_h($linkTitle); ?>">
                                        <span class="bio-featured-link__icon"><?php echo profile_render_icon($item['icon'] ?? '', 'fa-solid fa-link'); ?></span>
                                        <?php if ($buttonStyle === 'icon'): ?>
                                            <span class="profile-link-icon-label"><?php echo profile_h($linkTitle); ?></span>
                                        <?php else: ?>
                                            <span class="bio-featured-link__content">
                                                <?php if (!empty($item['is_featured'])): ?><small>Pin</small><?php endif; ?>
                                                <strong>
                                                    <?php echo profile_h($linkTitle); ?>
                                                    <?php if ((int)($profile['is_premium'] ?? 0) === 1 && !empty($item['card_tag_text'])): ?>
                                                        <span class="profile-card-tag" style="background-color: <?php echo profile_h($item['card_tag_bg'] ?: 'rgba(255,255,255,0.1)'); ?>; color: <?php echo profile_h($item['card_tag_color'] ?: '#ffffff'); ?>;">
                                                            <?php echo profile_h($item['card_tag_text']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </strong>
                                                <?php if (!empty($item['description'])): ?><em><?php echo profile_h($item['description']); ?></em><?php else: ?><em><?php echo profile_h(profile_short_url_label($item['url'])); ?></em><?php endif; ?>
                                            </span>
                                            <i class="fa-solid fa-chevron-right"></i>
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
                        <section class="bio-card profile-embeds-section js-reveal js-tilt-card" <?php echo $tiltAttrs; ?> data-section-type="embeds" data-section-title="<?php echo profile_h(profile_get_section_title('embeds', 'Embed')); ?>">
                            <?php profile_render_section_heading('fa-solid fa-share-from-square', 'Embed', null, 'embeds'); ?>
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
                                                <span><i class="<?php echo $embedType === 'spotify' ? 'fa-brands fa-spotify' : ($embedType === 'youtube' ? 'fa-brands fa-youtube' : 'fa-solid fa-code'); ?>"></i><?php echo profile_h($embedTitle); ?></span>
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
                        <div class="bio-stats-grid profile-stats-compact js-reveal" data-section-type="stats" data-section-title="<?php echo profile_h(profile_get_section_title('stats', 'Stats')); ?>">
                            <?php foreach (array_slice($stats, 0, 4) as $stat): ?>
                                <article class="bio-stat-card"><i class="<?php echo profile_h($stat['icon']); ?>"></i><strong><?php echo profile_h($stat['value']); ?></strong><span><?php echo profile_h($stat['label']); ?></span></article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif;
                    $sectionsHtml['stats'] = ob_get_clean();

                    // 4. Projects
                    ob_start();
                    if ($visibleProjects): ?>
                        <section class="bio-card bio-details profile-clean-section js-reveal js-tilt-card" <?php echo $tiltAttrs; ?> data-section-type="projects" data-section-title="<?php echo profile_h(profile_get_section_title('projects', 'Projects')); ?>">
                            <?php profile_render_section_heading('fa-solid fa-cubes', 'Projects', null, 'projects'); ?>
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
                                                <span class="profile-card-media__fallback"><i class="fa-solid fa-image"></i></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="bio-project-card__icon"><i class="fa-solid fa-layer-group"></i></span>
                                        <?php endif; ?>
                                        <strong>
                                            <?php echo profile_h($project['title']); ?>
                                            <?php if ((int)($profile['is_premium'] ?? 0) === 1 && !empty($project['card_tag_text'])): ?>
                                                <span class="profile-card-tag" style="background-color: <?php echo profile_h($project['card_tag_bg'] ?: 'rgba(255,255,255,0.1)'); ?>; color: <?php echo profile_h($project['card_tag_color'] ?: '#ffffff'); ?>;">
                                                    <?php echo profile_h($project['card_tag_text']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </strong>
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
                        <section class="bio-card bio-details profile-clean-section js-reveal js-tilt-card" <?php echo $tiltAttrs; ?> data-section-type="blocks" data-section-title="<?php echo profile_h(profile_get_section_title('blocks', '')); ?>">
                            <?php profile_render_section_heading('', '', null, 'blocks'); ?>
                            <div class="profile-block-grid">
                                <?php foreach ($visibleBlocks as $block): ?>
                                    <?php
                                    $allowedTypes = ['text', 'image', 'gif', 'video', 'markdown', 'html'];
                                    $blockType = profile_allowed_value((string)($block['block_type'] ?? 'text'), $allowedTypes, 'text');
                                    $mediaUrl = trim((string)($block['media_url'] ?? ''));
                                    $mediaType = trim((string)($block['media_type'] ?? 'image'));
                                    $isPinned = !empty($block['is_featured']);
                                    $noCardStyleClass = (!empty($block['no_card_style']) && (int)($profile['is_premium'] ?? 0) === 1) ? 'no-card-style' : '';
                                    $blockMediaPos = ($block['media_position'] ?? 'top');
                                    $blockTextAlign = ($block['text_align'] ?? 'left');
                                    $blockMediaAlign = ($block['media_align'] ?? 'center');
                                    $blockMediaFit = ($block['media_fit'] ?? 'cover');
                                    $blockMediaFitClass = 'block-media-fit-' . profile_h($blockMediaFit);
                                    $blockMediaAlignClass = 'block-media-align-' . profile_h($blockMediaAlign);
                                    $blockTextAlignStyle = $blockTextAlign !== 'left' ? ' style="text-align: ' . profile_h($blockTextAlign) . ';"' : '';
                                    ?>
                                    <article class="profile-block-card profile-block-<?php echo profile_h($blockType); ?> <?php echo $isPinned ? 'is-pinned' : ''; ?> <?php echo $noCardStyleClass; ?> <?php echo $blockMediaFitClass; ?> <?php echo $blockMediaAlignClass; ?>">
                                        <?php
                                        // Build media HTML
                                        $mediaHtml = '';
                                        if ($mediaUrl) {
                                            if ($mediaType === 'video' || $blockType === 'video') {
                                                $mediaHtml = '<video src="' . profile_h($mediaUrl) . '" controls playsinline preload="metadata"></video>';
                                            } else {
                                                $mediaHtml = '<img src="' . profile_h($mediaUrl) . '" alt="" loading="lazy">';
                                            }
                                        }
                                        // Build copy HTML
                                        $copyHtml = '';
                                        if (!empty($block['title']) || !empty($block['body']) || $isPinned || (!empty($block['card_tag_text']) && (int)($profile['is_premium'] ?? 0) === 1)) {
                                            ob_start();
                                        ?>
                                            <div class="profile-block-copy" <?php echo $blockTextAlignStyle; ?>>
                                                <?php if (!empty($block['title'])): ?>
                                                    <strong>
                                                        <?php echo profile_h($block['title']); ?>
                                                        <?php if ((int)($profile['is_premium'] ?? 0) === 1 && !empty($block['card_tag_text'])): ?>
                                                            <span class="profile-card-tag" style="background-color: <?php echo profile_h($block['card_tag_bg'] ?: 'rgba(255,255,255,0.1)'); ?>; color: <?php echo profile_h($block['card_tag_color'] ?: '#ffffff'); ?>;">
                                                                <?php echo profile_h($block['card_tag_text']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </strong>
                                                <?php elseif ((int)($profile['is_premium'] ?? 0) === 1 && !empty($block['card_tag_text'])): ?>
                                                    <div style="margin-bottom: 0.4rem;">
                                                        <span class="profile-card-tag" style="margin-left: 0; background-color: <?php echo profile_h($block['card_tag_bg'] ?: 'rgba(255,255,255,0.1)'); ?>; color: <?php echo profile_h($block['card_tag_color'] ?: '#ffffff'); ?>;">
                                                            <?php echo profile_h($block['card_tag_text']); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($block['body'])): ?>
                                                    <div class="profile-block-custom-content">
                                                        <?php if ($blockType === 'html' && (int)($profile['is_premium'] ?? 0) === 1): ?>
                                                            <?php echo $block['body']; ?>
                                                        <?php elseif ($blockType === 'markdown' && (int)($profile['is_premium'] ?? 0) === 1): ?>
                                                            <?php echo profile_markdown_to_html($block['body']); ?>
                                                        <?php else: ?>
                                                            <p><?php echo nl2br(profile_h($block['body'])); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($isPinned): ?><small>Pin</small><?php endif; ?>
                                            </div>
                                        <?php
                                            $copyHtml = ob_get_clean();
                                        }
                                        // Render based on media_position
                                        if ($blockMediaPos === 'bottom') {
                                            echo $copyHtml;
                                            echo $mediaHtml;
                                        } else {
                                            echo $mediaHtml;
                                            echo $copyHtml;
                                        }
                                        ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif;
                    $sectionsHtml['blocks'] = ob_get_clean();

                    // 6. Contents
                    ob_start();
                    if ($visibleContents): ?>
                        <section class="bio-card bio-details profile-clean-section js-reveal js-tilt-card" <?php echo $tiltAttrs; ?> data-section-type="contents" data-section-title="<?php echo profile_h(profile_get_section_title('contents', 'Content')); ?>">
                            <?php profile_render_section_heading('fa-solid fa-circle-play', 'Content', null, 'contents'); ?>
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
                                                <span class="profile-card-media__fallback"><i class="fa-solid fa-play"></i></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="bio-preview-card__icon"><i class="fa-solid fa-play"></i></span>
                                        <?php endif; ?>
                                        <span class="bio-preview-card__label"><?php echo profile_h($content['content_type']); ?></span>
                                        <strong>
                                            <?php echo profile_h($content['title']); ?>
                                            <?php if ((int)($profile['is_premium'] ?? 0) === 1 && !empty($content['card_tag_text'])): ?>
                                                <span class="profile-card-tag" style="background-color: <?php echo profile_h($content['card_tag_bg'] ?: 'rgba(255,255,255,0.1)'); ?>; color: <?php echo profile_h($content['card_tag_color'] ?: '#ffffff'); ?>;">
                                                    <?php echo profile_h($content['card_tag_text']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </strong>
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
                        <section class="bio-card profile-characters-section profile-clean-section js-reveal js-tilt-card" <?php echo $tiltAttrs; ?> data-section-type="characters" data-section-title="<?php echo profile_h(profile_get_section_title('characters', 'Characters')); ?>">
                            <?php profile_render_section_heading('fa-solid fa-user-astronaut', 'Characters', null, 'characters'); ?>
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
                                                    <i class="fa-solid fa-user-astronaut"></i>
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
                        <section class="bio-card bio-details profile-clean-section js-reveal js-tilt-card" <?php echo $tiltAttrs; ?> data-section-type="badges" data-section-title="<?php echo profile_h(profile_get_section_title('badges', 'Badge')); ?>">
                            <?php profile_render_section_heading('fa-solid fa-trophy', 'Badge', null, 'badges'); ?>
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
                                                <i class="fa-solid fa-medal"></i>
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
                        <section class="bio-card bio-about js-reveal js-tilt-card" <?php echo $tiltAttrs; ?> data-section-type="activity" data-section-title="<?php echo profile_h(profile_get_section_title('activity', 'Activity')); ?>">
                            <?php profile_render_section_heading('fa-solid fa-clock', 'Activity', null, 'activity'); ?>
                            <div class="profile-activity-strip">
                                <?php foreach (array_slice($visibleActivity, 0, 5) as $item): ?>
                                    <a class="profile-activity-pill" href="<?php echo !empty($item['url']) ? profile_h($item['url']) : '#'; ?>" <?php echo !empty($item['url']) ? 'target="_blank" rel="noopener noreferrer"' : 'aria-disabled="true"'; ?>>
                                        <i class="<?php echo profile_h(function_exists('profile_activity_icon') ? profile_activity_icon($item['activity_type'] ?? '') : 'fa-solid fa-clock'); ?>"></i>
                                        <span><?php echo profile_h($item['label']); ?></span>
                                        <small><?php echo profile_h(profile_time_ago($item['created_at'] ?? null)); ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif;
                    $sectionsHtml['activity'] = ob_get_clean();

                    $sectionsOrderRaw = $profile['profile_sections_order'] ?? 'links,embeds,stats,projects,blocks,contents,characters,badges,activity';
                    $sectionsOrder = explode(',', $sectionsOrderRaw);
                    $allowedSectionsList = ['links', 'embeds', 'stats', 'projects', 'blocks', 'contents', 'characters', 'badges', 'activity'];

                    $orderedSectionsHtml = [];
                    if (trim($spotlightHtml) !== '') {
                        $orderedSectionsHtml[] = $spotlightHtml;
                    }

                    foreach ($sectionsOrder as $secKey) {
                        $secKey = trim($secKey);
                        if (isset($sectionsHtml[$secKey])) {
                            if (trim($sectionsHtml[$secKey]) !== '') {
                                $orderedSectionsHtml[] = $sectionsHtml[$secKey];
                            }
                            unset($sectionsHtml[$secKey]);
                        }
                    }

                    foreach ($allowedSectionsList as $secKey) {
                        if (isset($sectionsHtml[$secKey]) && trim($sectionsHtml[$secKey]) !== '') {
                            $orderedSectionsHtml[] = $sectionsHtml[$secKey];
                        }
                    }

                    if ($layoutCss === 'center-split') {
                        echo '<div class="profile-split-column profile-split-column--left">';
                        foreach ($orderedSectionsHtml as $sectionIndex => $sectionHtml) {
                            if ($sectionIndex % 2 !== 0) {
                                continue;
                            }
                            echo '<div class="profile-split-item" style="--profile-split-order: ' . (int)$sectionIndex . ';">' . $sectionHtml . '</div>';
                        }
                        echo '</div>';
                        echo '<div class="profile-split-column profile-split-column--right">';
                        foreach ($orderedSectionsHtml as $sectionIndex => $sectionHtml) {
                            if ($sectionIndex % 2 === 0) {
                                continue;
                            }
                            echo '<div class="profile-split-item" style="--profile-split-order: ' . (int)$sectionIndex . ';">' . $sectionHtml . '</div>';
                        }
                        echo '</div>';
                    } else {
                        foreach ($orderedSectionsHtml as $sectionHtml) {
                            echo $sectionHtml;
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
            <button class="bio-small-button js-close-qr" type="button" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            <strong>QR Profile</strong>
            <img class="profile-qr-image" alt="QR code of the profile" src="/api/profile_qr.php?url=<?php echo rawurlencode($profileUrl); ?>" data-qr-src="/api/profile_qr.php?url=<?php echo rawurlencode($profileUrl); ?>">
            <button class="bio-button bio-button--primary js-copy-profile" type="button"><i class="fa-solid fa-link"></i>Copy link</button>
        </section>
    </div>

    <div class="bio-toast" id="bioToast" role="status" aria-live="polite"></div>

    <?php if ($hasMusic): ?>
        <div class="profile-floating-audio-btn-container position-<?php echo profile_h($audioBtnPosition); ?>"
             style="position: fixed !important; z-index: 999999 !important; display: <?php echo (!$showAudioPlayer && $showAudioBtn) ? 'flex' : 'none'; ?> !important; align-items: center !important; flex-direction: <?php echo (strpos($audioBtnPosition, 'left') !== false) ? 'row' : 'row-reverse'; ?> !important; <?php
                 if ($audioBtnPosition === 'top-left') echo 'top: 24px !important; left: 24px !important;';
                 elseif ($audioBtnPosition === 'top-right') echo 'top: 24px !important; right: 24px !important;';
                 elseif ($audioBtnPosition === 'bottom-left') echo 'bottom: 24px !important; left: 24px !important;';
                 else echo 'bottom: 24px !important; right: 24px !important;'; // bottom-right
             ?>"
             data-floating-audio
             data-default-volume="<?php echo $audioDefaultVolume; ?>">
            <button class="profile-floating-audio-btn" type="button" aria-label="Mute/Unmute">
                <i class="fa-solid fa-volume-high"></i>
            </button>
            <div class="profile-floating-audio-slider-wrap">
                <input type="range" class="profile-floating-audio-slider" min="0" max="1" step="0.01" value="<?php echo $audioDefaultVolume; ?>" aria-label="Volume">
            </div>
        </div>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <?php if (isset($_GET['preview_mode'])): ?>
        <script>
            window.addEventListener('message', function(event) {
                if (!event.data) return;
                const data = event.data;
                if (data.type === 'update-css-variables') {
                    const body = document.querySelector('.bio-v2-body');
                    if (body) {
                        for (const [key, value] of Object.entries(data.variables)) {
                            body.style.setProperty(key, value, 'important');
                        }
                    }
                } else if (data.type === 'update-attributes') {
                    const body = document.querySelector('.bio-v2-body');
                    if (body) {
                        for (const [key, value] of Object.entries(data.attributes)) {
                            if (key.startsWith('data-') && !key.startsWith('data-tilt-')) {
                                body.setAttribute(key, value);
                                if (key === 'data-cursor-custom-url') {
                                    if (value) {
                                        body.style.setProperty('--cursor-custom-url', `url('${value}'), auto`);
                                    } else {
                                        body.style.removeProperty('--cursor-custom-url');
                                    }
                                } else if (key === 'data-layout-snap') {
                                    if (window.initScrollSnapPagination) {
                                        window.initScrollSnapPagination();
                                    }
                                }
                            } else if (key === 'style') {
                                for (const [styleKey, styleVal] of Object.entries(value)) {
                                    body.style.setProperty(styleKey, styleVal);
                                }
                            }
                        }
                        
                        // Real-time floating audio button updates in preview
                        const showBtn = data.attributes['data-show-audio-btn'];
                        const btnPos = data.attributes['data-audio-btn-position'];
                        const container = document.querySelector('[data-floating-audio]');
                        if (container) {
                            if (showBtn === '0') {
                                container.style.setProperty('display', 'none', 'important');
                            } else if (showBtn === '1') {
                                const mainPlayer = document.querySelector('[data-audio-player]');
                                const isMainPlayerVisible = mainPlayer && mainPlayer.style.display !== 'none';
                                container.style.setProperty('display', isMainPlayerVisible ? 'none' : 'flex', 'important');
                            }
                            
                            if (btnPos) {
                                container.className = 'profile-floating-audio-btn-container position-' + btnPos;
                                container.style.setProperty('position', 'fixed', 'important');
                                container.style.setProperty('z-index', '999999', 'important');
                                container.style.setProperty('transform', 'none', 'important');
                                container.style.setProperty('flex-direction', btnPos.includes('left') ? 'row' : 'row-reverse', 'important');
                                
                                container.style.setProperty('top', btnPos.startsWith('top') ? '24px' : 'auto', 'important');
                                container.style.setProperty('bottom', btnPos.startsWith('bottom') ? '24px' : 'auto', 'important');
                                container.style.setProperty('left', btnPos.includes('left') ? '24px' : 'auto', 'important');
                                container.style.setProperty('right', btnPos.includes('right') ? '24px' : 'auto', 'important');
                            }
                        }
                        if (window.initCursorEffects) {
                            window.initCursorEffects();
                        }
                        if (data.attributes['data-profile-border-style']) {
                            body.classList.forEach((className) => {
                                if (className.startsWith('profile-border-style-')) {
                                    body.classList.remove(className);
                                }
                            });
                            body.classList.add('profile-border-style-' + data.attributes['data-profile-border-style']);
                        }
                    }
                    if (data.attributes['data-profile-layout']) {
                        const page = document.getElementById('bioPage');
                        if (page) {
                            page.classList.forEach((className) => {
                                if (className.startsWith('layout-')) {
                                    page.classList.remove(className);
                                }
                            });
                            const layoutMap = {
                                compact: 'center-split',
                                showcase: 'right-tabs',
                                clean: 'stacked',
                                'left-tabs': 'standard',
                                'right-tabs': 'right-tabs',
                                stacked: 'stacked',
                                'center-split': 'center-split',
                                standard: 'standard'
                            };
                            const nextLayout = layoutMap[data.attributes['data-profile-layout']] || 'standard';
                            page.classList.add('layout-' + nextLayout);
                            page.classList.toggle('profile-smart-page--single', !document.querySelector('.profile-smart-content'));
                        }
                    }
                    const cards = document.querySelectorAll('.js-tilt-card');
                    cards.forEach(card => {
                        for (const [key, value] of Object.entries(data.attributes)) {
                            if (key.startsWith('data-tilt-')) {
                                card.setAttribute(key, value);
                            }
                        }
                        if (card.getAttribute('data-tilt-enabled') === '0') {
                            card.style.transform = 'none';
                            const glare = card.querySelector('.js-tilt-glare');
                            if (glare) glare.style.display = 'none';
                        }
                    });
                } else if (data.type === 'update-text') {
                    for (const [selector, text] of Object.entries(data.texts)) {
                        const el = document.querySelector(selector);
                        if (el) {
                            if (selector === '.profile-display-name') {
                                el.setAttribute('data-text', text);
                                el.textContent = text;
                            } else if (selector === '.bio-tagline') {
                                el.innerHTML = text.replace(/\n/g, '<br>');
                            } else if (selector.includes('profile-audio-player strong')) {
                                el.innerHTML = `<i class="fa-solid fa-music"></i>` + text;
                            } else {
                                el.textContent = text;
                            }
                        } else if (selector === '.bio-tagline' && text.trim() !== '') {
                            const nameBlock = document.querySelector('.bio-name-block');
                            if (nameBlock) {
                                const newBio = document.createElement('p');
                                newBio.className = 'bio-tagline';
                                newBio.innerHTML = text.replace(/\n/g, '<br>');
                                nameBlock.appendChild(newBio);
                            }
                        }
                    }
                } else if (data.type === 'update-avatar-src') {
                    const avatar = document.querySelector('.bio-avatar');
                    if (avatar) {
                        avatar.src = data.src;
                    }
                } else if (data.type === 'update-background-media') {
                    const background = document.querySelector('.bio-background');
                    if (background) {
                        background.querySelectorAll('.bio-background__media, video').forEach((node) => node.remove());
                        let media;
                        if (data.fileType.startsWith('video/')) {
                            media = document.createElement('video');
                            media.className = 'bio-background__media';
                            media.autoplay = true;
                            media.muted = true;
                            media.loop = true;
                            media.playsInline = true;
                            const source = document.createElement('source');
                            source.src = data.url;
                            source.type = data.fileType;
                            media.appendChild(source);
                        } else if (data.fileType.startsWith('image/')) {
                            media = document.createElement('img');
                            media.className = 'bio-background__media';
                            media.src = data.url;
                            media.alt = '';
                        }
                        if (media) {
                            background.prepend(media);
                        }
                    }
                } else if (data.type === 'update-music-player') {
                    const player = document.querySelector('[data-audio-player]');
                    const audio = document.getElementById('profileAudio');
                    if (player) {
                        if (data.hasMusic && data.showPlayer) {
                            player.style.removeProperty('display');
                        } else {
                            player.style.display = 'none';
                        }
                    }
                    if (audio && data.src) {
                        const newSrc = data.src || '';
                        if (audio.getAttribute('src') !== newSrc) {
                            audio.src = newSrc;
                            audio.load();
                        }
                    }
                    if (audio && typeof data.defaultVolume !== 'undefined') {
                        audio.volume = data.defaultVolume;
                        const mainSlider = document.getElementById('profileVolumeSlider');
                        if (mainSlider) mainSlider.value = String(data.defaultVolume);
                        const floatSlider = document.querySelector('.profile-floating-audio-slider');
                        if (floatSlider) floatSlider.value = String(data.defaultVolume);
                    }
                    const floatBtn = document.querySelector('[data-floating-audio]');
                    if (floatBtn) {
                        const showBtn = document.body.getAttribute('data-show-audio-btn') !== '0';
                        if (data.hasMusic && !data.showPlayer && showBtn) {
                            floatBtn.style.setProperty('display', 'flex', 'important');
                        } else {
                            floatBtn.style.setProperty('display', 'none', 'important');
                        }
                    }
                    const titleEl = document.querySelector('.profile-audio-player strong');
                    if (titleEl) {
                        titleEl.innerHTML = `<i class="fa-solid fa-music"></i>` + (data.title || 'Profile Song');
                    }
                    const artistEl = document.querySelector('.profile-artist-span');
                    if (artistEl) {
                        if (data.artist) {
                            artistEl.textContent = data.artist;
                            artistEl.style.removeProperty('display');
                        } else {
                            artistEl.style.display = 'none';
                        }
                    }
                } else if (data.type === 'reload') {
                    window.location.reload();
                }
            });
        </script>
    <?php endif; ?>
</body>

</html>