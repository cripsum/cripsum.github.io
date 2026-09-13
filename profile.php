<?php
require_once __DIR__ . '/config/session_init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/profile_helpers.php';
require_once __DIR__ . '/includes/cripsum_og.php';
require_once __DIR__ . '/includes/mission_tracker.php';
require_once __DIR__ . '/includes/social_functions.php';

if (empty($_SESSION['social_csrf'])) {
    $_SESSION['social_csrf'] = bin2hex(random_bytes(32));
}
$socialCsrfToken = $_SESSION['social_csrf'];

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
        $lang = cripsum_preferred_lang();
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
$isDeactivated = false;
$socials = $links = $projects = $contents = $blocks = $badges = $activity = [];
$isOnline = false;
$lastSeen = null;

if ($profile) {
    $profileId = (int)$profile['id'];
    $isOwnProfile = $currentUserId === $profileId;
    $canEdit = profile_can_edit($profileId);
    $isPremium = (int)($profile['is_premium'] ?? 0) === 1;

    $isFriend = false;
    if ($isLoggedIn && !$canEdit) {
        $userOne = min($currentUserId, $profileId);
        $userTwo = max($currentUserId, $profileId);
        $stmtF = $mysqli->prepare("SELECT 1 FROM friendships WHERE user_one_id = ? AND user_two_id = ? LIMIT 1");
        if ($stmtF) {
            $stmtF->bind_param("ii", $userOne, $userTwo);
            $stmtF->execute();
            $resF = $stmtF->get_result();
            if ($resF->num_rows > 0) {
                $isFriend = true;
            }
            $stmtF->close();
        }
    }

    // An account waiting to be deleted behaves like a deactivated one: it stays
    // reachable for its owner (so they can cancel) and nobody else.
    if (!empty($profile['deletion_requested_at']) && !$isOwnProfile) {
        $isDeactivated = true;
        $isPrivateBlocked = true;
    } elseif (($profile['profile_visibility'] ?? 'public') === 'private' && !$canEdit) {
        $isPrivateBlocked = true;
    } elseif (($profile['profile_visibility'] ?? 'public') === 'friends' && !$canEdit && !$isFriend) {
        $isPrivateBlocked = true;
    } elseif (($profile['profile_visibility'] ?? 'public') === 'logged_in' && !$isLoggedIn) {
        $isLoginBlocked = true;
    } else {
        // A preview reload is not a visit: the editor refreshes this page on
        // every change, which would otherwise inflate the counter.
        if (!isset($_GET['preview_mode'])) {
            profile_increment_views($mysqli, $profileId);
        }

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

            // The editor reloads this page on every change while it is also
            // saving drafts and uploading files. Rendering can take a while
            // (Discord widget lookups included), so hand the session lock back
            // now that the draft has been read; nothing below writes to it.
            cripsum_release_session();

            // Override profile values. Only keys that are real profile columns
            // are copied: the draft is raw POST data, so blindly merging it
            // would let a crafted request fake fields the renderer trusts
            // (`is_premium`, `id`, visibility, ...) inside the preview.
            $draftBlockedKeys = [
                'id', 'is_premium', 'ruolo', 'password', 'email', 'discord_username',
                'discord_global_name', 'discord_connected_at', 'discord_server_cache',
                'discord_server_cache_time', 'profile_updated_at', 'ultimo_accesso',
                'csrf_token', 'target_user_id', 'salva', 'viewport',
                'socials_json', 'links_json', 'projects_json', 'contents_json',
                'blocks_json', 'badges_json', 'characters_json', 'embeds_json',
                'profile_tags_json',
            ];
            // Editor-only flags that are not columns but that the renderer reads.
            $draftExtraKeys = ['remove_profile_music_upload'];
            foreach ($draft as $key => $val) {
                if (!is_string($key) || in_array($key, $draftBlockedKeys, true)) continue;
                // Only columns that already exist on the profile row may change.
                if (!array_key_exists($key, $profile) && !in_array($key, $draftExtraKeys, true)) continue;
                if (!is_scalar($val) && $val !== null) continue;
                $profile[$key] = $val;
            }

            // `profile_name_style` is stored as one JSON column but edited as a
            // set of separate fields, so it has to be rebuilt for the preview.
            $draftNameStyle = profile_name_style_from_input(
                $draft,
                profile_style_hex($draft['profile_text_color'] ?? null),
                (string)($draft['profile_theme'] ?? 'dark')
            );
            if ($draftNameStyle !== null) {
                $profile['profile_name_style'] = json_encode($draftNameStyle);
            }

            // Il layout arriva dall'editor come una scelta unica fra cinque.
            if (isset($draft['profile_layout_choice'])) {
                [$profile['profile_layout'], $profile['profile_layout_snap']] = profile_layout_from_input($draft, $isPremium);
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
                'profile_show_audio_btn',
                'profile_bg_use_video_audio'
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
            <video class="bio-background__media" id="profileBgVideo" autoplay muted loop playsinline poster="">
                <source src="<?php echo profile_h($url); ?>" type="<?php echo profile_h($type); ?>">
            </video>
        <?php elseif ($isImage): ?>
            <img class="bio-background__media" src="<?php echo profile_h($url); ?>" alt="" loading="eager">
        <?php else: ?>
            <video class="bio-background__media" id="profileBgVideo" autoplay muted loop playsinline poster="">
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

// Forme, bordi, riquadri e colori arrivano tutti da includes/profile_style.php.
$style = profile_style_resolve($profile ?: []);
$styleVars = profile_style_css_vars($style);
$theme = $style['theme'];
$accent = $style['accent'];
$secondaryColor = $style['secondary'];
$linkStyle = $style['link_style'];
$avatarShape = $style['avatar_shape'];
$controlShape = $style['control_shape'];
// Con il tema "auto" la pagina parte scura e uno script in testa al body
// passa al chiaro se il sistema di chi visita lo preferisce.
$themeAttr = $theme === 'auto' ? 'dark' : $theme;

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
$socialsStyle = $style['socials_style'];

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

$backgroundUrl = $profile && !empty($profile['profile_banner_type']) ? '/includes/get_profile_banner.php?id=' . (int)$profile['id'] . '&t=' . $stamp : null;
$backgroundType = $profile && !empty($profile['profile_banner_type']) ? (string)$profile['profile_banner_type'] : 'video/mp4';

$bgUseVideoAudio = $profile ? ((int)($profile['profile_bg_use_video_audio'] ?? 0) === 1) : false;
$isBgVideo = str_starts_with($backgroundType, 'video/');
$hasMusic = $hasUploadedMusic || ($musicExternalUrl !== '' && profile_is_safe_url($musicExternalUrl, true)) || ($bgUseVideoAudio && $isBgVideo);

if ($hasMusic && $musicTitle === '') {
    if ($bgUseVideoAudio && $isBgVideo) {
        $musicTitle = 'Background Video';
        $musicArtist = 'Video Audio';
    }
}
$profileEffect = $profile ? profile_allowed_value((string)($profile['profile_effect'] ?? 'none'), ['none', 'cursor_glow', 'soft_particles', 'scanlines', 'ambient', 'aurora', 'gradient_waves', 'stars', 'spotlight', 'digital_noise', 'glass_rain', 'sakura_falling', 'cyber_grid', 'bg_grain'], 'none') : 'none';
$avatarRingEnabled = $profile ? ((int)($profile['avatar_ring_enabled'] ?? 1) === 1) : true;
$avatarRingStyle = $profile ? profile_allowed_value((string)($profile['avatar_ring_style'] ?? 'spin'), ['spin', 'pulse', 'orbit', 'glow', 'dual', 'rainbow', 'halo', 'neon', 'spark', 'glitch', 'none'], 'spin') : 'spin';
$avatarRingColor = $profile ? profile_normalize_hex_color($profile['avatar_ring_color'] ?: $accent) : $accent;

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
// Fonts land inside a CSS custom property, so only a plain family name is kept.
if (!preg_match('/^[A-Za-z0-9 _-]{1,40}$/', (string)$profileFont)) {
    $profileFont = 'Poppins';
}

// Cursor URLs are interpolated into an inline style attribute: percent-encode
// everything that could terminate the CSS string (see profile_css_url_value).
$cursorCustomUrlCss = (int)($profile['is_premium'] ?? 0) === 1
    ? profile_css_url_value($profile['profile_cursor_custom_url'] ?? '')
    : '';
$cursorCustomHoverUrlCss = (int)($profile['is_premium'] ?? 0) === 1
    ? profile_css_url_value($profile['profile_cursor_custom_hover_url'] ?? '')
    : '';
$hideMeta = $isPremium && $profile ? profile_flag($profile, 'profile_hide_meta', false) : false;
$showAudioBtn = $profile ? profile_flag($profile, 'profile_show_audio_btn', true) : true;
$audioBtnPosition = ($profile && !empty($profile['profile_audio_btn_position'])) ? $profile['profile_audio_btn_position'] : 'bottom-right';
$audioDefaultVolume = ($profile && isset($profile['profile_audio_default_volume']) && $profile['profile_audio_default_volume'] !== '') ? (float)$profile['profile_audio_default_volume'] : 0.18;
$avatarBorder = $profile ? (int)($profile['profile_avatar_border'] ?? 1) : 1;

$visibleSocials = $showSocials ? $socials : [];
$visibleLinks = $showLinks ? $links : [];
$visibleProjects = $showProjects ? $projects : [];
$visibleContents = $showContents ? $contents : [];
$visibleBlocks = $showBlocks ? $blocks : [];
$badgesDisplay = $profile ? ($profile['profile_badges_display'] ?? 'both') : 'both';
$badgesPosition = $profile ? ($profile['profile_badges_position'] ?? 'below_bio') : 'below_bio';

$nameStyle = profile_name_style_normalize($profile['profile_name_style'] ?? null, $style['text_color'], $theme);

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

$hasStats = $showStats && $profile && ((int)$profile['profile_views'] > 0 || (int)$profile['num_achievement'] > 0 || (int)$profile['num_personaggi'] > 0 || (int)$profile['total_personaggi'] > 0);
$hasDiscordSection = $showDiscord && (!empty($discordId) || !empty($widgetData));
$hasRightContent = $hasStats || $visibleLinks || $visibleProjects || $visibleContents || $visibleBlocks || ($visibleBadges && $showBadgesSection) || $visibleActivity || $visibleCharacters || $embeds;
$hasAnyPublicContent = $visibleSocials || $visibleLinks || $visibleProjects || $visibleContents || $visibleBlocks || $visibleBadges || $hasDiscordSection || $hasMusic || $embeds;

$stats = [];
if ($profile) {
    if ((int)$profile['profile_views'] > 0) $stats[] = ['icon' => 'fa-solid fa-eye', 'value' => profile_compact_number($profile['profile_views']), 'label' => 'Views'];
    if ((int)$profile['num_achievement'] > 0) $stats[] = ['icon' => 'fa-solid fa-trophy', 'value' => profile_compact_number($profile['num_achievement']), 'label' => 'Badges'];
    if ((int)$profile['num_personaggi'] > 0) $stats[] = ['icon' => 'fa-solid fa-user-astronaut', 'value' => profile_compact_number($profile['num_personaggi']), 'label' => 'Characters'];
    if ((int)$profile['total_personaggi'] > 0) $stats[] = ['icon' => 'fa-solid fa-dice-d20', 'value' => profile_compact_number($profile['total_personaggi']), 'label' => 'Pulls'];
}
$ogMeta = cripsum_og_profile($mysqli, $profile);

// The profile URL carries no language: use the one the visitor was browsing
// the site in. The navbar include below reassigns $lang, so the page keeps
// its own copy.
$lang = cripsum_preferred_lang();
$profileLang = $lang;
$pt = static fn(string $it, string $en): string => $profileLang === 'it' ? $it : $en;
?>
<!DOCTYPE html>
<html lang="<?php echo $profileLang; ?>" <?php echo ($profile && profile_flag($profile, 'profile_click_to_enter', false)) ? 'class="click-to-enter-active"' : ''; ?>>

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
    <link rel="stylesheet" href="/assets/css/profile.css?v=5.13.0">
    <link rel="stylesheet" href="/assets/social/social.css?v=2.0">
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

            0%,
            100% {
                border-color: rgba(234, 179, 8, 0.4);
                box-shadow: 0 0 12px rgba(234, 179, 8, 0.15);
            }

            50% {
                border-color: rgba(234, 179, 8, 0.8);
                box-shadow: 0 0 18px rgba(234, 179, 8, 0.35);
            }
        }
    </style>
    <script src="/assets/js/profile.js?v=5.14.0" defer></script>
    <?php if (isset($_GET['preview_mode'])): ?>
        <script src="/assets/js/profile-style.js?v=6.0.0" defer></script>
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
    <style>
        .bio-v2-body {
            <?php foreach ($styleVars as $varName => $varValue): ?>
            <?php echo $varName; ?>: <?php echo preg_replace('/[^a-zA-Z0-9#%.,()\s-]/', '', (string)$varValue); ?> !important;
            <?php endforeach; ?>
            --profile-font: '<?php echo profile_h($profileFont); ?>', sans-serif !important;
            font-family: var(--profile-font, "Poppins", sans-serif) !important;
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

        /* Custom Hover Cursor for Clickable Elements */
        body[data-cursor-custom-hover-url] a,
        body[data-cursor-custom-hover-url] button,
        body[data-cursor-custom-hover-url] select,
        body[data-cursor-custom-hover-url] [role="button"],
        body[data-cursor-custom-hover-url] input[type="submit"],
        body[data-cursor-custom-hover-url] input[type="button"],
        body[data-cursor-custom-hover-url] input[type="reset"],
        body[data-cursor-custom-hover-url] a *,
        body[data-cursor-custom-hover-url] button *,
        body[data-cursor-custom-hover-url] [role="button"] * {
            cursor: var(--cursor-custom-hover-url) !important;
        }

        /* JS cursor follower for animated cursors (GIF) */
        body.custom-cursor-js-active,
        body.custom-cursor-js-active *,
        body.custom-cursor-js-active a,
        body.custom-cursor-js-active button,
        body.custom-cursor-js-active select,
        body.custom-cursor-js-active input,
        body.custom-cursor-js-active textarea {
            cursor: none !important;
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
    class="bio-v2-body public-profile-body<?php echo ($profile && profile_flag($profile, 'profile_click_to_enter', false)) ? ' click-to-enter-active' : ''; ?>"
    data-logged-in="<?php echo $isLoggedIn ? '1' : '0'; ?>"
    data-user-id="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>"
    data-current-user-id="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>"
    data-csrf="<?php echo $socialCsrfToken; ?>"
    data-theme="<?php echo profile_h($themeAttr); ?>"
    data-owner-theme="<?php echo profile_h($theme); ?>"
    <?php if (isset($_GET['preview_mode']) && $canEdit): ?>data-preview-premium="<?php echo $isPremium ? '1' : '0'; ?>"<?php endif; ?>
    data-accent="<?php echo profile_h($accent); ?>"
    data-profile-url="<?php echo profile_h($profileUrl); ?>"
    data-discord-id="<?php echo profile_h($showDiscord ? $discordId : ''); ?>"
    data-profile-effect="<?php echo profile_h($profileEffect); ?>"
    data-profile-link-style="<?php echo profile_h($linkStyle); ?>"
    data-control-shape="<?php echo profile_h($controlShape); ?>"
    data-profile-socials-style="<?php echo profile_h($socialsStyle); ?>"
    data-profile-layout="<?php echo profile_h($layoutCss); ?>"
    data-avatar-shape="<?php echo profile_h($avatarShape); ?>"
    data-avatar-border="<?php echo $avatarBorder; ?>"
    data-tab-title="<?php echo profile_h($pageTitle); ?>"
    data-tab-animation="<?php echo profile_h($profile['profile_tab_animation'] ?? 'static'); ?>"
    data-tab-animation-speed="<?php echo (int)($profile['profile_tab_animation_speed'] ?? 1000); ?>"
    data-tab-animation-text="<?php echo profile_h($profile['profile_tab_animation_text'] ?? ''); ?>"
    data-bg-use-video-audio="<?php echo (int)($profile['profile_bg_use_video_audio'] ?? 0) === 1 ? '1' : '0'; ?>"
    data-cursor-effect="<?php echo (int)($profile['is_premium'] ?? 0) === 1 ? profile_h($profile['profile_cursor_effect'] ?? 'none') : 'none'; ?>"
    data-layout-snap="<?php echo (int)($profile['is_premium'] ?? 0) === 1 && (int)($profile['profile_layout_snap'] ?? 0) === 1 ? '1' : '0'; ?>"
    data-bg-grain="<?php echo (int)($profile['is_premium'] ?? 0) === 1 && ((int)($profile['profile_bg_grain'] ?? 0) === 1 || $profileEffect === 'bg_grain') ? '1' : '0'; ?>"
    data-music-theme="<?php echo (int)($profile['is_premium'] ?? 0) === 1 ? profile_h($profile['profile_music_theme'] ?? 'default') : 'default'; ?>"
    data-cursor-custom-url="<?php echo profile_h($cursorCustomUrlCss); ?>"
    data-cursor-custom-center="<?php echo (int)($profile['is_premium'] ?? 0) === 1 && (int)($profile['profile_cursor_custom_center'] ?? 0) === 1 ? '1' : '0'; ?>"
    data-cursor-custom-hover-url="<?php echo profile_h($cursorCustomHoverUrlCss); ?>"
    data-cursor-custom-hover-center="<?php echo (int)($profile['is_premium'] ?? 0) === 1 && (int)($profile['profile_cursor_custom_hover_center'] ?? 0) === 1 ? '1' : '0'; ?>"
    style="--profile-ring: <?php echo profile_h($avatarRingColor); ?>; <?php if ($cursorCustomUrlCss !== ''): ?>--cursor-custom-url: url('<?php echo profile_h($cursorCustomUrlCss); ?>')<?php echo (int)($profile['profile_cursor_custom_center'] ?? 0) === 1 ? ' 32 32' : ''; ?>, auto !important;<?php endif; ?> <?php if ($cursorCustomHoverUrlCss !== ''): ?>--cursor-custom-hover-url: url('<?php echo profile_h($cursorCustomHoverUrlCss); ?>')<?php echo (int)($profile['profile_cursor_custom_hover_center'] ?? 0) === 1 ? ' 32 32' : ''; ?>, auto !important;<?php endif; ?>">

    <?php if ($theme === 'auto'): ?>
        <script>
            // Tema "auto": segue il sistema di chi visita, salvo che abbia scelto
            // un tema a mano dal menu del profilo.
            (function () {
                var saved = null;
                try { saved = localStorage.getItem('cripsum.profile.viewerTheme'); } catch (e) {}
                if (!saved && window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
                    document.body.setAttribute('data-theme', 'light');
                }
            })();
        </script>
    <?php endif; ?>

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
    $lang = $profileLang;
    ?>

    <?php profile_render_background($profile, $backgroundUrl, $backgroundType); ?>
    <div class="profile-effects-layer" aria-hidden="true"></div>

    <?php if ($isNotFound): ?>
        <?php profile_state_page('404', $pt('Profilo non trovato', 'Profile Not Found'), $pt('Questo utente non esiste o ha cambiato username.', 'This user does not exist or has changed their username.'), 'Home', '/' . $lang . '/home'); ?>
    <?php elseif ($isDeactivated): ?>
        <?php
        // Deliberately worded like a missing profile: whether an account is
        // mid-deletion is nobody else's business.
        profile_state_page(
            '404',
            ($lang === 'it') ? 'Profilo non disponibile' : 'Profile Unavailable',
            ($lang === 'it')
                ? 'Questo profilo non è al momento disponibile.'
                : 'This profile is currently unavailable.',
            'Home',
            '/' . $lang . '/home'
        );
        ?>
    <?php elseif ($isPrivateBlocked): ?>
        <?php
        $blockTitle = (($profile['profile_visibility'] ?? '') === 'friends')
            ? (($lang === 'it') ? 'Solo Amici' : 'Friends Only')
            : (($lang === 'it') ? 'Profilo Privato' : 'Private Profile');
        $blockText = (($profile['profile_visibility'] ?? '') === 'friends')
            ? (($lang === 'it') ? 'Il profilo di @' . $profile['username'] . ' è visibile solo agli amici.' : '@' . $profile['username'] . '\'s profile is only visible to friends.')
            : (($lang === 'it') ? '@' . $profile['username'] . ' non mostra questo profilo.' : '@' . $profile['username'] . ' is not showing this profile.');
        profile_state_page('Private', $blockTitle, $blockText, 'Home', '/' . $lang . '/home');
        ?>
    <?php elseif ($isLoginBlocked): ?>
        <?php profile_state_page('Login', $pt('Accesso richiesto', 'Login Required'), $pt('Questo profilo è visibile solo agli utenti registrati.', 'This profile is only visible to registered users.'), $pt('Accedi', 'Log In'), '/' . $lang . '/accedi'); ?>
    <?php else: ?>
        <?php
        $tiltAttrs = 'data-tilt-enabled="' . (int)($profile['tilt_enabled'] ?? 1) . '" ' .
            'data-tilt-max="' . (int)($profile['tilt_max'] ?? 15) . '" ' .
            'data-tilt-glare="' . (float)($profile['tilt_glare'] ?? 0.0) . '" ' .
            'data-tilt-zoom="' . (float)($profile['tilt_zoom'] ?? 1.05) . '" ' .
            'data-tilt-speed="' . (int)($profile['tilt_speed'] ?? 400) . '"';
        ?>
        <?php
        $rel = ($isLoggedIn && isset($profile['id'])) ? getRelationshipStatus($mysqli, $currentUserId, $profile['id']) : null;
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
                                        <a class="profile-dropdown-item" href="/<?php echo $lang; ?>/edit-profile<?php echo profile_is_staff() && !$isOwnProfile ? '?user_id=' . (int)$profile['id'] : ''; ?>">
                                            <i class="fa-solid fa-pen"></i>
                                            <span><?php echo $pt('Modifica profilo', 'Edit profile'); ?></span>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!$isPremium && !$isOwnProfile): ?>
                                        <a class="profile-dropdown-item profile-dropdown-item--gift" href="/<?php echo $lang; ?>/checkout-premium.php?gift_to=<?php echo urlencode($profile['username']); ?>">
                                            <i class="fa-solid fa-gift"></i>
                                            <span><?php echo $pt('Regala Premium', 'Gift Premium'); ?></span>
                                        </a>
                                    <?php endif; ?>
                                    <a class="profile-dropdown-item" href="/<?php echo $lang; ?>/home">
                                        <i class="fa-solid fa-home"></i>
                                        <span>Home Page</span>
                                    </a>
                                    <button class="profile-dropdown-item js-open-search" type="button">
                                        <i class="fa-solid fa-search"></i>
                                        <span><?php echo $pt('Cerca utenti', 'Search users'); ?></span>
                                    </button>
                                    <button class="profile-dropdown-item js-open-navigation" type="button">
                                        <i class="fa-solid fa-compass"></i>
                                        <span><?php echo $pt('Apri navigazione', 'Open Navigation'); ?></span>
                                    </button>
                                    <button class="profile-dropdown-item js-copy-profile" type="button">
                                        <i class="fa-solid fa-link"></i>
                                        <span><?php echo $pt('Copia link', 'Copy link'); ?></span>
                                    </button>
                                    <button class="profile-dropdown-item js-share-profile" type="button">
                                        <i class="fa-solid fa-share-nodes"></i>
                                        <span><?php echo $pt('Condividi profilo', 'Share Profile'); ?></span>
                                    </button>
                                    <button class="profile-dropdown-item js-open-report" type="button" data-user-id="<?php echo (int)$profile['id']; ?>" data-username="<?php echo profile_h($profile['username']); ?>">
                                        <i class="fa-solid fa-flag"></i>
                                        <span><?php echo $pt('Segnala profilo', 'Report Profile'); ?></span>
                                    </button>
                                    <button class="profile-dropdown-item js-open-qr" type="button">
                                        <i class="fa-solid fa-qrcode"></i>
                                        <span>QR Code</span>
                                    </button>
                                    <button class="profile-dropdown-item js-theme-toggle" type="button">
                                        <i class="fa-solid fa-moon"></i>
                                        <span class="theme-label-text"><?php echo $pt('Modalità scura', 'Dark Mode'); ?></span>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="bio-avatar-wrap profile-smart-avatar ring-style-<?php echo profile_h($avatarRingStyle); ?> <?php echo (!$avatarRingEnabled || $avatarRingStyle === 'none') ? 'ring-disabled' : ''; ?> <?php echo (!$isOwnProfile) ? 'user-card-trigger' : ''; ?>"
                        <?php echo (!$isOwnProfile) ? 'data-user-id="' . (int)$profile['id'] . '" data-username="' . profile_h($profile['username']) . '" style="cursor: pointer; --profile-ring: ' . profile_h($avatarRingColor) . ';"' : 'style="--profile-ring: ' . profile_h($avatarRingColor) . ';"'; ?>>
                        <?php if ($avatarRingEnabled && $avatarRingStyle !== 'none'): ?><div class="bio-avatar-ring"></div><?php endif; ?>
                        <img class="bio-avatar" src="<?php echo profile_h(profile_avatar_url($profile, 256)); ?>" alt="Avatar di <?php echo profile_h($profile['username']); ?>" loading="eager" data-richpresence-pfp<?php echo (int)($profile['discord_use_avatar'] ?? 0) === 1 && !empty($profile['discord_id']) ? ' data-live-discord-avatar data-discord-id="' . profile_h($profile['discord_id']) . '" data-avatar-size="256"' : ''; ?>>
                    </div>

                    <?php
                    $renderMiniBadgesHtml = '';
                    if ($visibleBadges && $showMiniBadges) {
                        ob_start();
                    ?>
                        <div class="profile-mini-badges badges-pos-<?php echo profile_h($badgesPosition); ?>" aria-label="Badge">
                            <?php
                            $badgesToDisplay = $isPremium ? $visibleBadges : array_slice($visibleBadges, 0, 4);
                            foreach ($badgesToDisplay as $badge):
                            ?>
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
                                <?php echo profile_name_style_attributes($nameStyle); ?>
                                data-text="<?php echo profile_h($displayName); ?>">
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
                            <div class="profile-tags-container">
                                <?php foreach ($profileTags as $tag):
                                    $tagText = (string)($tag['text'] ?? '');
                                    if (trim($tagText) === '') continue;
                                    $tagIcon = (string)($tag['icon'] ?? '');
                                    $tagView = profile_tag_view($tag);
                                ?>
                                    <span class="<?php echo profile_h($tagView['class']); ?>"<?php echo $tagView['style'] !== '' ? ' style="' . profile_h($tagView['style']) . '"' : ''; ?>>
                                        <?php if ($tagIcon !== ''): ?>
                                            <?php echo profile_render_icon($tagIcon, '', 'profile-tag-pill__icon'); ?>
                                        <?php endif; ?>
                                        <span><?php echo profile_h($tagText); ?></span>
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
                            <span><?php echo $pt('Aggiungi link, badge o contenuti per completare il profilo.', 'Add links, badges, or content to fill out the bio.'); ?></span>
                            <a href="/<?php echo $lang; ?>/edit-profile"><?php echo $pt('Modifica', 'Edit'); ?></a>
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
                                    const savedVolume = localStorage.getItem(volumeKey) !== null ?
                                        Number(localStorage.getItem(volumeKey)) :
                                        defaultVolume;
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
                    $sectionsHtml = [];

                    // 1. Links
                    ob_start();
                    if ($visibleLinks): ?>
                        <section class="bio-card bio-featured js-reveal js-tilt-card" <?php echo $tiltAttrs; ?> data-section-type="links" data-section-title="<?php echo profile_h(profile_get_section_title('links', 'Link')); ?>">
                            <?php profile_render_section_heading('fa-solid fa-link', 'Link', null, 'links'); ?>
                            <div class="bio-featured-grid profile-link-grid profile-link-count-<?php echo count($visibleLinks); ?>">
                                <?php foreach ($visibleLinks as $item): ?>
                                    <?php
                                    $buttonStyle = profile_allowed_value((string)($item['button_style'] ?? 'card'), ['card', 'compact', 'icon'], 'card');
                                    $linkTitle = (string)($item['title'] ?? 'Link');
                                    ?>
                                    <a class="bio-featured-link profile-link-button button-style-<?php echo profile_h($buttonStyle); ?>" href="<?php echo profile_h($item['url']); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo profile_h($linkTitle); ?>">
                                        <span class="bio-featured-link__icon"><?php echo profile_render_icon($item['icon'] ?? '', 'fa-solid fa-link'); ?></span>
                                        <?php if ($buttonStyle === 'icon'): ?>
                                            <span class="profile-link-icon-label"><?php echo profile_h($linkTitle); ?></span>
                                        <?php else: ?>
                                            <span class="bio-featured-link__content">
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
                                    <a class="bio-project-card <?php echo $hasProjectImage ? 'has-media' : ''; ?>" href="<?php echo profile_h($project['url'] ?: '#'); ?>" <?php echo $project['url'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                        <?php if ($hasProjectImage): ?>
                                            <span class="profile-card-media profile-project-media">
                                                <img src="<?php echo profile_h($projectImageUrl); ?>" alt="<?php echo profile_h($project['title']); ?>" loading="lazy" onerror="this.parentElement.classList.add('is-broken'); this.remove();">
                                                <span class="profile-card-media__fallback"><i class="fa-solid fa-image"></i></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="bio-project-card__icon"><?php echo profile_render_icon($project['icon'] ?? '', 'fa-solid fa-layer-group'); ?></span>
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
                                    $noCardStyleClass = (!empty($block['no_card_style']) && (int)($profile['is_premium'] ?? 0) === 1) ? 'no-card-style' : '';
                                    $blockMediaPos = ($block['media_position'] ?? 'top');
                                    $blockTextAlign = ($block['text_align'] ?? 'left');
                                    $blockMediaAlign = ($block['media_align'] ?? 'center');
                                    $blockMediaFit = ($block['media_fit'] ?? 'cover');
                                    $blockMediaFitClass = 'block-media-fit-' . profile_h($blockMediaFit);
                                    $blockMediaAlignClass = 'block-media-align-' . profile_h($blockMediaAlign);
                                    $blockTextAlignStyle = $blockTextAlign !== 'left' ? ' style="text-align: ' . profile_h($blockTextAlign) . ';"' : '';
                                    ?>
                                    <article class="profile-block-card profile-block-<?php echo profile_h($blockType); ?> <?php echo $noCardStyleClass; ?> <?php echo $blockMediaFitClass; ?> <?php echo $blockMediaAlignClass; ?>">
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
                                        if (!empty($block['title']) || !empty($block['body']) || (!empty($block['card_tag_text']) && (int)($profile['is_premium'] ?? 0) === 1)) {
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
                                    <a class="bio-preview-card <?php echo $hasContentThumb ? 'has-media' : ''; ?>" href="<?php echo profile_h($content['url'] ?: '#'); ?>" <?php echo $content['url'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                        <?php if ($hasContentThumb): ?>
                                            <span class="profile-card-media profile-content-media">
                                                <img src="<?php echo profile_h($contentThumbUrl); ?>" alt="<?php echo profile_h($content['title']); ?>" loading="lazy" onerror="this.parentElement.classList.add('is-broken'); this.remove();">
                                                <span class="profile-card-media__fallback"><i class="fa-solid fa-play"></i></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="bio-preview-card__icon"><?php echo profile_render_icon($content['icon'] ?? '', 'fa-solid fa-play'); ?></span>
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

    <?php /* Navigazione, ricerca utenti e segnalazione: i pulsanti del menu li
             cercano per id, e senza questo markup non si apriva niente. */ ?>
    <?php include __DIR__ . '/includes/profile_overlays.php'; ?>

    <div class="profile-qr-modal" id="profileQrModal" aria-hidden="true">
        <div class="profile-qr-backdrop js-close-qr"></div>
        <section class="bio-card profile-qr-card" role="dialog" aria-modal="true" aria-label="QR Profile">
            <button class="bio-small-button js-close-qr" type="button" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            <strong><?php echo $pt('QR del profilo', 'QR Profile'); ?></strong>
            <img class="profile-qr-image" alt="QR code of the profile" src="/api/profile_qr.php?url=<?php echo rawurlencode($profileUrl); ?>" data-qr-src="/api/profile_qr.php?url=<?php echo rawurlencode($profileUrl); ?>">
            <button class="bio-button bio-button--primary js-copy-profile" type="button"><i class="fa-solid fa-link"></i><?php echo $pt('Copia link', 'Copy link'); ?></button>
        </section>
    </div>

    <div class="bio-toast" id="bioToast" role="status" aria-live="polite"></div>

    <?php if ($hasMusic): ?>
        <div class="profile-floating-audio-btn-container position-<?php echo profile_h($audioBtnPosition); ?>"
            style="position: fixed !important; z-index: 999999 !important; display: <?php echo (!$showAudioPlayer && ($showAudioBtn || ($bgUseVideoAudio && $isBgVideo))) ? 'flex' : 'none'; ?> !important; align-items: center !important; flex-direction: <?php echo (strpos($audioBtnPosition, 'left') !== false) ? 'row' : 'row-reverse'; ?> !important; <?php
                                                                                                                                                                                                                                                                                                                                                                if ($audioBtnPosition === 'top-left') echo 'top: 24px !important; left: 24px !important;';
                                                                                                                                                                                                                                                                                                                                                                elseif ($audioBtnPosition === 'top-right') echo 'top: 24px !important; right: 24px !important;';
                                                                                                                                                                                                                                                                                                                                                                elseif ($audioBtnPosition === 'bottom-left') echo 'bottom: 24px !important; left: 24px !important;';
                                                                                                                                                                                                                                                                                                                                                                else echo 'bottom: 24px !important; right: 24px !important;'; // bottom-right
                                                                                                                                                                                                                                                                                                                                                                ?>"
            data-floating-audio
            data-default-volume="<?php echo $audioDefaultVolume; ?>"
            data-show-audio-btn="<?php echo $showAudioBtn ? '1' : '0'; ?>"
            data-bg-use-video-audio="<?php echo $bgUseVideoAudio ? '1' : '0'; ?>">
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
            // Restores the scroll offset captured before a refresh so reloading
            // the preview does not throw the user back to the top. The preview
            // stylesheet can move scrolling onto <body>, so both are handled.
            function previewScrollTop() {
                return window.scrollY
                    || document.documentElement.scrollTop
                    || document.body.scrollTop
                    || 0;
            }

            (function restorePreviewScroll() {
                let stored = null;
                try {
                    stored = sessionStorage.getItem('cripsum.preview.scroll');
                    sessionStorage.removeItem('cripsum.preview.scroll');
                } catch (_) {}
                const offset = Number(stored);
                if (!stored || !Number.isFinite(offset) || offset <= 0) return;
                const apply = () => {
                    window.scrollTo(0, offset);
                    document.documentElement.scrollTop = offset;
                    document.body.scrollTop = offset;
                };
                window.addEventListener('load', () => {
                    apply();
                    window.setTimeout(apply, 60);
                    window.setTimeout(apply, 240);
                });
            })();

            // The editor refreshes this page whenever a setting changes, so the
            // "click to enter" gate is only shown once per preview session.
            (function previewClickToEnter() {
                const KEY = 'cripsum.preview.entered';
                const dismiss = () => {
                    const overlay = document.getElementById('clickToEnterOverlay');
                    if (overlay) overlay.remove();
                    document.documentElement.classList.remove('click-to-enter-active');
                    document.body.classList.remove('click-to-enter-active');
                };

                let entered = false;
                try { entered = sessionStorage.getItem(KEY) === '1'; } catch (_) {}
                if (entered) dismiss();

                document.addEventListener('click', (event) => {
                    if (event.target && event.target.closest && event.target.closest('#clickToEnterOverlay')) {
                        try { sessionStorage.setItem(KEY, '1'); } catch (_) {}
                    }
                }, true);
            })();

            // Percent-encodes everything that could terminate the CSS string a
            // URL is dropped into, mirroring profile_css_url_value() in PHP.
            function previewCssUrl(value) {
                const url = String(value || '').trim();
                if (url === '') return '';
                if (/[\x00-\x20\x7F"'\\<>`]/.test(url)) return '';
                if (!/^https?:\/\//i.test(url) && !url.startsWith('/uploads/profile_media/')) return '';
                if (url.includes('..')) return '';
                return url.replace(/[()]/g, (ch) => (ch === '(' ? '%28' : '%29'));
            }

            /*
             * Anteprima dell'editor. L'editor manda tutti i valori del form a
             * ogni modifica ({type: 'cripsum:settings'}); qui si applica subito
             * cio' che non richiede di ridisegnare la pagina. Il resto arriva
             * con il ricaricamento dalla bozza ({type: 'cripsum:reload'}).
             */
            (function previewBridge() {
                const body = document.body;
                const $ = (selector) => document.querySelector(selector);
                const $$ = (selector) => Array.from(document.querySelectorAll(selector));
                const loadedFonts = new Set();
                let lastEffect = body.dataset.profileEffect || 'none';
                let lastNameEffect = $('.profile-display-name')?.dataset.nameEffect || 'none';
                let lastCursor = '';

                const loadFont = (family) => {
                    if (!family || ['Poppins', 'Minecraft', 'Gang of Three'].includes(family) || loadedFonts.has(family)) return;
                    loadedFonts.add(family);
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(family).replace(/%20/g, '+') + '&display=swap';
                    document.head.appendChild(link);
                };

                const on = (s, name) => s[name] === '1';

                const applyName = (s) => {
                    const nameEl = $('.profile-display-name');
                    if (!nameEl || !window.CripsumProfileStyle) return;
                    const style = window.CripsumProfileStyle.nameStyle({
                        color: s.profile_name_color,
                        effect: s.profile_name_effect,
                        grad_color1: s.profile_name_grad_color1,
                        grad_color2: s.profile_name_grad_color2,
                        grad_angle: s.profile_name_grad_angle,
                        glow_color: s.profile_name_glow_color,
                    }, s.profile_text_color, s.profile_theme);

                    nameEl.dataset.nameEffect = style.effect;
                    nameEl.dataset.nameAnim = style.effect;
                    nameEl.style.setProperty('--name-color', style.color);
                    nameEl.style.setProperty('--name-grad-1', style.grad_color1);
                    nameEl.style.setProperty('--name-grad-2', style.grad_color2);
                    nameEl.style.setProperty('--name-angle', style.grad_angle + 'deg');
                    nameEl.style.setProperty('--name-glow-color', style.glow_color);

                    // Con "usa il nome di Discord" il nome resta quello di Discord.
                    const text = on(s, 'discord_use_display_name')
                        ? (nameEl.dataset.text || nameEl.textContent)
                        : (String(s.display_name || '').trim() || String(s.username || '').trim() || nameEl.dataset.text || '');
                    nameEl.dataset.text = text;
                    nameEl.textContent = '';
                    if (style.effect === 'bounce') {
                        Array.from(text).forEach((char, index) => {
                            const span = document.createElement('span');
                            span.className = char === ' ' ? 'name-char space-char' : 'name-char';
                            span.style.setProperty('--char-index', String(index));
                            span.textContent = char === ' ' ? '\u00a0' : char;
                            nameEl.appendChild(span);
                        });
                    } else {
                        nameEl.textContent = text;
                    }
                    if ((style.effect === 'sparkles') !== (lastNameEffect === 'sparkles') && window.initNameSparkles) {
                        window.initNameSparkles();
                    }
                    lastNameEffect = style.effect;
                };

                const applyTexts = (s) => {
                    const username = $('.bio-username');
                    if (username && s.username !== undefined) username.textContent = '@' + (String(s.username).trim() || 'username');
                    if (s.bio !== undefined) {
                        let bio = $('.bio-tagline');
                        const value = String(s.bio);
                        if (!bio && value.trim() !== '') {
                            bio = document.createElement('p');
                            bio.className = 'bio-tagline';
                            $('.bio-username')?.after(bio);
                        }
                        if (bio) {
                            bio.textContent = '';
                            value.split('\n').forEach((line, i) => {
                                if (i > 0) bio.appendChild(document.createElement('br'));
                                bio.appendChild(document.createTextNode(line));
                            });
                            bio.hidden = value.trim() === '';
                        }
                    }
                };

                const applyRing = (s) => {
                    const wrap = $('.bio-avatar-wrap');
                    if (!wrap) return;
                    const style = String(s.avatar_ring_style || 'spin').replace(/[^a-z0-9_-]/gi, '');
                    const enabled = style !== 'none';
                    Array.from(wrap.classList).forEach((c) => { if (c.startsWith('ring-style-')) wrap.classList.remove(c); });
                    wrap.classList.add('ring-style-' + style);
                    wrap.classList.toggle('ring-disabled', !enabled);
                    if (s.avatar_ring_color) {
                        wrap.style.setProperty('--profile-ring', s.avatar_ring_color);
                        body.style.setProperty('--profile-ring', s.avatar_ring_color);
                    }
                    let ring = wrap.querySelector('.bio-avatar-ring');
                    if (enabled && !ring) {
                        ring = document.createElement('div');
                        ring.className = 'bio-avatar-ring';
                        wrap.prepend(ring);
                    } else if (!enabled && ring) {
                        ring.remove();
                    }
                    body.dataset.avatarBorder = on(s, 'profile_avatar_border') ? '1' : '0';
                };

                const applyCursor = (s, premium) => {
                    const url = premium ? previewCssUrl(s.profile_cursor_custom_url) : '';
                    const hover = premium ? previewCssUrl(s.profile_cursor_custom_hover_url) : '';
                    body.dataset.cursorEffect = premium ? (s.profile_cursor_effect || 'none') : 'none';
                    body.dataset.cursorCustomUrl = url;
                    body.dataset.cursorCustomCenter = premium && on(s, 'profile_cursor_custom_center') ? '1' : '0';
                    body.dataset.cursorCustomHoverUrl = hover;
                    body.dataset.cursorCustomHoverCenter = premium && on(s, 'profile_cursor_custom_hover_center') ? '1' : '0';
                    if (url) body.style.setProperty('--cursor-custom-url', `url('${url}')${on(s, 'profile_cursor_custom_center') ? ' 32 32' : ''}, auto`);
                    else body.style.removeProperty('--cursor-custom-url');
                    if (hover) body.style.setProperty('--cursor-custom-hover-url', `url('${hover}')${on(s, 'profile_cursor_custom_hover_center') ? ' 32 32' : ''}, auto`);
                    else body.style.removeProperty('--cursor-custom-hover-url');
                    if (!url) body.removeAttribute('data-cursor-custom-url');
                    if (!hover) body.removeAttribute('data-cursor-custom-hover-url');

                    const key = [body.dataset.cursorEffect, url, hover].join('|');
                    if (key !== lastCursor) {
                        lastCursor = key;
                        window.initCursorEffects?.();
                        window.initCustomCursorImage?.();
                    }
                };

                const applyTilt = (s) => {
                    const enabled = s.tilt_enabled === '0' ? '0' : '1';
                    $$('.js-tilt-card').forEach((card) => {
                        card.dataset.tiltEnabled = enabled;
                        if (s.tilt_max !== undefined) card.dataset.tiltMax = s.tilt_max;
                        if (s.tilt_glare !== undefined) card.dataset.tiltGlare = s.tilt_glare;
                        if (s.tilt_zoom !== undefined) card.dataset.tiltZoom = s.tilt_zoom;
                        if (s.tilt_speed !== undefined) card.dataset.tiltSpeed = s.tilt_speed;
                        if (enabled === '0') card.style.transform = 'none';
                    });
                };

                const applyAudio = (s, premium) => {
                    body.dataset.musicTheme = premium ? (s.profile_music_theme || 'default') : 'default';
                    const player = $('[data-audio-player]');
                    const showPlayer = on(s, 'profile_show_audio_player');
                    const audio = document.getElementById('profileAudio');
                    const hasMusic = !!(audio && (audio.getAttribute('src') || audio.currentSrc));
                    if (player) player.style.display = hasMusic && showPlayer ? '' : 'none';

                    const title = $('.profile-audio-player strong');
                    if (title) title.innerHTML = '<i class="fa-solid fa-music"></i>' + String(s.profile_music_title || 'Profile Song').replace(/[<>&]/g, '');
                    const artist = $('.profile-artist-span');
                    if (artist) {
                        artist.textContent = s.profile_music_artist || '';
                        artist.style.display = s.profile_music_artist ? '' : 'none';
                    }

                    const floating = $('[data-floating-audio]');
                    if (floating) {
                        const position = String(s.profile_audio_btn_position || 'bottom-right');
                        const show = !showPlayer && (on(s, 'profile_show_audio_btn') || on(s, 'profile_bg_use_video_audio'));
                        floating.className = 'profile-floating-audio-btn-container position-' + position;
                        floating.style.setProperty('display', show ? 'flex' : 'none', 'important');
                        floating.style.setProperty('top', position.startsWith('top') ? '24px' : 'auto', 'important');
                        floating.style.setProperty('bottom', position.startsWith('bottom') ? '24px' : 'auto', 'important');
                        floating.style.setProperty('left', position.includes('left') ? '24px' : 'auto', 'important');
                        floating.style.setProperty('right', position.includes('right') ? '24px' : 'auto', 'important');
                        floating.style.setProperty('flex-direction', position.includes('left') ? 'row' : 'row-reverse', 'important');
                    }
                    if (audio && s.profile_audio_default_volume !== undefined) {
                        const volume = Math.max(0, Math.min(1, Number(s.profile_audio_default_volume)));
                        if (Number.isFinite(volume)) audio.volume = volume;
                    }
                };

                const applySettings = (s, premium) => {
                    if (window.CripsumProfileStyle) window.CripsumProfileStyle.apply(body, s);
                    if (s.profile_font) {
                        loadFont(s.profile_font);
                        body.style.setProperty('--profile-font', `'${String(s.profile_font).replace(/'/g, '')}', sans-serif`, 'important');
                    }

                    const effect = s.profile_effect || 'none';
                    body.dataset.bgGrain = premium && effect === 'bg_grain' ? '1' : '0';
                    if (effect !== lastEffect) {
                        body.dataset.profileEffect = effect;
                        lastEffect = effect;
                        $$('.profile-effects-layer .profile-effect-dot').forEach((dot) => dot.remove());
                        window.initProfileEffects?.();
                    }

                    applyName(s);
                    applyTexts(s);
                    applyRing(s);
                    applyCursor(s, premium);
                    applyTilt(s);
                    applyAudio(s, premium);
                };

                const applyMedia = (message) => {
                    if (message.kind === 'avatar') {
                        const avatar = $('.bio-avatar');
                        if (avatar) avatar.src = message.url;
                    } else if (message.kind === 'background') {
                        const background = $('.bio-background');
                        if (!background) return;
                        background.querySelectorAll('.bio-background__media, video').forEach((node) => node.remove());
                        let media;
                        if (String(message.fileType).startsWith('video/')) {
                            media = document.createElement('video');
                            Object.assign(media, { autoplay: true, muted: true, loop: true, playsInline: true, src: message.url });
                        } else {
                            media = document.createElement('img');
                            media.src = message.url;
                            media.alt = '';
                        }
                        media.className = 'bio-background__media';
                        background.prepend(media);
                    } else if (message.kind === 'music') {
                        const audio = document.getElementById('profileAudio');
                        if (audio && audio.getAttribute('src') !== message.url) {
                            audio.src = message.url;
                            audio.load();
                        }
                        const player = $('[data-audio-player]');
                        if (player && body.dataset.previewShowPlayer !== '0') player.style.removeProperty('display');
                    }
                };

                const focusSection = (section) => {
                    const el = $(`[data-section-type="${CSS.escape(String(section || ''))}"]`);
                    if (!el || el.offsetParent === null) return;
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    el.classList.remove('profile-preview-flash');
                    void el.offsetWidth;
                    el.classList.add('profile-preview-flash');
                };

                window.addEventListener('message', (event) => {
                    // L'editor ospita questa pagina in un iframe della stessa
                    // origine; tutto il resto viene ignorato.
                    if (event.origin !== window.location.origin || !event.data || typeof event.data !== 'object') return;
                    const message = event.data;
                    if (message.type === 'cripsum:settings' && message.settings) {
                        body.dataset.previewShowPlayer = message.settings.profile_show_audio_player === '1' ? '1' : '0';
                        applySettings(message.settings, !!message.premium && body.dataset.previewPremium === '1');
                    } else if (message.type === 'cripsum:media') {
                        applyMedia(message);
                    } else if (message.type === 'cripsum:focus') {
                        focusSection(message.section);
                    } else if (message.type === 'cripsum:reload') {
                        try {
                            sessionStorage.setItem('cripsum.preview.scroll', String(previewScrollTop()));
                        } catch (_) {}
                        window.location.reload();
                    }
                });
            })();
        </script>
    <?php endif; ?>
    <script src="/assets/social/social-api.js?v=1.5" defer></script>
    <script src="/assets/social/user-card.js?v=2.9" defer></script>
</body>

</html>
