<?php
require_once __DIR__ . '/config/session_init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/profile_helpers.php';

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
        $socials = profile_list_socials($mysqli, $profileId, true);
        $links = profile_list_links($mysqli, $profileId, true);
        $projects = profile_list_projects($mysqli, $profileId, true);
        $contents = profile_list_contents($mysqli, $profileId, true);
        $blocks = function_exists('profile_list_blocks') ? profile_list_blocks($mysqli, $profileId, true) : [];
        $badges = profile_list_visible_badges($mysqli, $profileId);
        $activity = profile_recent_activity($mysqli, $profileId);

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
    $defaultBackgroundVideo = '/vid/Shorekeeper Wallpaper 4K Loop.mp4';
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
$cardColorCss = $cardColor ?: 'var(--card)';
$textColorCss = $textColor ?: 'var(--text)';
if ($theme === 'auto') $theme = 'dark';

$displayName = $profile ? ($profile['display_name'] ?: $profile['username']) : 'Profilo';
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
$profileEffect = $profile ? profile_allowed_value((string)($profile['profile_effect'] ?? 'none'), ['none', 'cursor_glow', 'soft_particles', 'scanlines', 'ambient'], 'none') : 'none';
$avatarRingEnabled = $profile ? ((int)($profile['avatar_ring_enabled'] ?? 1) === 1) : true;
$avatarRingStyle = $profile ? profile_allowed_value((string)($profile['avatar_ring_style'] ?? 'spin'), ['spin', 'pulse', 'orbit', 'glow', 'none'], 'spin') : 'spin';
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

$visibleSocials = $showSocials ? $socials : [];
$visibleLinks = $showLinks ? $links : [];
$visibleProjects = $showProjects ? $projects : [];
$visibleContents = $showContents ? $contents : [];
$visibleBlocks = $showContents ? $blocks : [];
$visibleBadges = $showBadges ? $badges : [];
$visibleActivity = $showActivity ? $activity : [];

$featuredLinks = array_values(array_filter($visibleLinks, fn($item) => (int)($item['is_featured'] ?? 0) === 1));
$normalLinks = array_values(array_filter($visibleLinks, fn($item) => (int)($item['is_featured'] ?? 0) !== 1));
$featuredProjects = array_values(array_filter($visibleProjects, fn($item) => (int)($item['is_featured'] ?? 0) === 1));
$normalProjects = array_values(array_filter($visibleProjects, fn($item) => (int)($item['is_featured'] ?? 0) !== 1));
$featuredContents = array_values(array_filter($visibleContents, fn($item) => (int)($item['is_featured'] ?? 0) === 1));
$normalContents = array_values(array_filter($visibleContents, fn($item) => (int)($item['is_featured'] ?? 0) !== 1));

$hasStats = $showStats && $profile && ((int)$profile['profile_views'] > 0 || (int)$profile['num_achievement'] > 0 || (int)$profile['num_personaggi'] > 0 || (int)$profile['total_personaggi'] > 0);
$hasRightContent = $hasStats || $featuredLinks || $normalLinks || $visibleProjects || $visibleContents || $visibleBlocks || $visibleBadges || $visibleActivity;
$hasAnyPublicContent = $visibleSocials || $visibleLinks || $visibleProjects || $visibleContents || $visibleBlocks || $visibleBadges || ($showDiscord && $discordId) || $hasMusic;

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
    if ((int)$profile['profile_views'] > 0) $stats[] = ['icon' => 'fas fa-eye', 'value' => profile_compact_number($profile['profile_views']), 'label' => 'Visite'];
    if ((int)$profile['num_achievement'] > 0) $stats[] = ['icon' => 'fas fa-trophy', 'value' => profile_compact_number($profile['num_achievement']), 'label' => 'Badge'];
    if ((int)$profile['num_personaggi'] > 0) $stats[] = ['icon' => 'fas fa-user-astronaut', 'value' => profile_compact_number($profile['num_personaggi']), 'label' => 'Personaggi'];
    if ((int)$profile['total_personaggi'] > 0) $stats[] = ['icon' => 'fas fa-dice-d20', 'value' => profile_compact_number($profile['total_personaggi']), 'label' => 'Pull'];
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include __DIR__ . '/includes/head-import.php'; ?>
    <title><?php echo $profile ? 'Cripsum™ - ' . profile_h($displayName) : 'Cripsum™ - Profilo'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($profile): ?>
        <meta name="description" content="<?php echo profile_h($profile['bio'] ?: '@' . $profile['username'] . ' su Cripsum™'); ?>">
        <meta property="og:title" content="<?php echo profile_h($displayName); ?> su Cripsum™">
        <meta property="og:description" content="<?php echo profile_h($profile['bio'] ?: '@' . $profile['username']); ?>">
        <meta property="og:type" content="profile">
        <meta property="og:url" content="<?php echo profile_h($profileUrl); ?>">
        <meta property="og:image" content="/includes/get_pfp.php?id=<?php echo (int)$profile['id']; ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/profile.css?v=2.7-links-colors">
    <script src="/assets/js/profile.js?v=2.7-links-colors" defer></script>
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
    style="--profile-ring: <?php echo profile_h($avatarRingColor); ?>; --accent-2: <?php echo profile_h($secondaryColor); ?>; --profile-card-color: <?php echo profile_h($cardColorCss); ?>; --profile-text-color: <?php echo profile_h($textColorCss); ?>;">
    <?php
    if (file_exists(__DIR__ . '/includes/navbar-bio.php')) include __DIR__ . '/includes/navbar-bio.php';
    else include __DIR__ . '/includes/navbar.php';
    if (file_exists(__DIR__ . '/includes/impostazioni.php')) include __DIR__ . '/includes/impostazioni.php';
    ?>

    <?php profile_render_background($profile, $backgroundUrl, $backgroundType); ?>
    <div class="profile-effects-layer" aria-hidden="true"></div>

    <?php if ($isNotFound): ?>
        <?php profile_state_page('404', 'Profilo non trovato', 'Questo utente non esiste o ha cambiato username.', 'Home', '/it/home'); ?>
    <?php elseif ($isPrivateBlocked): ?>
        <?php profile_state_page('Privato', 'Profilo privato', '@' . $profile['username'] . ' non mostra questo profilo.', 'Home', '/it/home'); ?>
    <?php elseif ($isLoginBlocked): ?>
        <?php profile_state_page('Login', 'Accesso richiesto', 'Questo profilo è visibile solo agli utenti registrati.', 'Accedi', '/it/accedi'); ?>
    <?php else: ?>
        <main class="bio-page profile-smart-page <?php echo !$hasRightContent ? 'profile-smart-page--single' : ''; ?>" id="bioPage">
            <section class="bio-hero bio-card profile-smart-hero js-tilt-card js-reveal" aria-label="Profilo pubblico">
                <div class="profile-hero-actions-top">
                    <?php if ($isOnline): ?>
                        <span class="bio-pill bio-pill--live"><span class="bio-dot"></span>online</span>
                    <?php elseif ($customStatus): ?>
                        <span class="bio-pill"><i class="fas fa-signal"></i><?php echo profile_h($customStatus); ?></span>
                    <?php endif; ?>
                    <?php if ($canEdit): ?>
                        <a class="bio-small-button" href="/edit-profile.php<?php echo profile_is_staff() && !$isOwnProfile ? '?user_id=' . (int)$profile['id'] : ''; ?>" aria-label="Modifica"><i class="fas fa-pen"></i></a>
                    <?php endif; ?>
                </div>

                <div class="bio-avatar-wrap profile-smart-avatar ring-style-<?php echo profile_h($avatarRingStyle); ?> <?php echo (!$avatarRingEnabled || $avatarRingStyle === 'none') ? 'ring-disabled' : ''; ?>">
                    <?php if ($avatarRingEnabled && $avatarRingStyle !== 'none'): ?><div class="bio-avatar-ring"></div><?php endif; ?>
                    <img class="bio-avatar" src="/includes/get_pfp.php?id=<?php echo (int)$profile['id']; ?>&t=<?php echo (int)strtotime((string)($profile['profile_updated_at'] ?? 'now')); ?>" alt="Avatar di <?php echo profile_h($profile['username']); ?>" loading="eager">
                </div>

                <div class="bio-name-block profile-smart-name">
                    <h1><?php echo profile_h($displayName); ?></h1>
                    <p class="bio-username">@<?php echo profile_h($profile['username']); ?></p>
                    <?php if (!empty($profile['bio'])): ?>
                        <p class="bio-tagline"><?php echo nl2br(profile_h($profile['bio'])); ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($visibleBadges): ?>
                    <div class="profile-mini-badges" aria-label="Badge in evidenza">
                        <?php foreach (array_slice($visibleBadges, 0, 4) as $badge): ?>
                            <?php $badgeImage = !empty($badge['img_url']) ? '/img/' . ltrim((string)$badge['img_url'], '/') : null; ?>
                            <span class="profile-mini-badge" title="<?php echo profile_h($badge['nome']); ?>">
                                <?php if ($badgeImage): ?><img src="<?php echo profile_h($badgeImage); ?>" alt="" loading="lazy"><?php else: ?><i class="fas fa-medal"></i><?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($visibleSocials): ?>
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

                <?php if ($showDiscord && $discordId): ?>
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

                <?php if (!$hasAnyPublicContent && $isOwnProfile): ?>
                    <div class="profile-owner-nudge">
                        <i class="fas fa-plus"></i>
                        <span>Aggiungi link, badge o contenuti per riempire la bio.</span>
                        <a href="/edit-profile.php">Modifica</a>
                    </div>
                <?php endif; ?>

                <div class="bio-actions profile-smart-actions" aria-label="Azioni profilo">
                    <button class="bio-button bio-button--primary js-copy-profile" type="button"><i class="fas fa-link"></i>Copia</button>
                    <button class="bio-button js-share-profile" type="button"><i class="fas fa-share-nodes"></i>Share</button>
                    <button class="bio-icon-button js-open-qr" type="button" aria-label="QR code"><i class="fas fa-qrcode"></i></button>
                    <button class="bio-icon-button js-theme-toggle" type="button" aria-label="Tema"><i class="fas fa-moon"></i></button>
                </div>

                <?php if ($hasMusic && $showAudioPlayer): ?>
                    <div class="bio-audio profile-audio-player" data-audio-player>
                        <audio id="profileAudio" preload="metadata" src="<?php echo profile_h($musicUrl); ?>"></audio>
                        <div class="bio-audio__header">
                            <div>
                                <small>Audio</small>
                                <strong><i class="fas fa-music"></i><?php echo profile_h($musicTitle ?: 'Canzone profilo'); ?></strong>
                                <?php if ($musicArtist): ?><span><?php echo profile_h($musicArtist); ?></span><?php endif; ?>
                            </div>
                            <button class="bio-small-button js-profile-audio-toggle" type="button" aria-label="Play pausa"><i id="profileAudioIcon" class="fas fa-play"></i></button>
                        </div>
                        <div class="bio-audio__progress">
                            <span id="profileAudioCurrent">0:00</span>
                            <input id="profileAudioProgress" type="range" min="0" max="100" step="0.1" value="0" aria-label="Avanzamento audio">
                            <span id="profileAudioTotal">0:00</span>
                        </div>
                        <div class="bio-audio__bottom">
                            <button class="bio-small-button js-profile-volume-toggle" type="button" aria-label="Mute"><i id="profileVolumeIcon" class="fas fa-volume-down"></i></button>
                            <input id="profileVolumeSlider" type="range" min="0" max="1" step="0.01" value="0.18" aria-label="Volume">
                        </div>
                    </div>
                <?php endif; ?>

                <div class="profile-small-meta">
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
                                    <small><?php echo profile_h($spotlight['type']); ?> in evidenza</small>
                                    <strong><?php echo profile_h($spotlight['title']); ?></strong>
                                    <?php if ($spotlight['description']): ?><em><?php echo profile_h($spotlight['description']); ?></em><?php endif; ?>
                                    <?php if ($spotlight['meta']): ?><span><?php echo profile_h($spotlight['meta']); ?></span><?php endif; ?>
                                </span>
                                <?php if ($spotlight['url']): ?><i class="fas fa-arrow-up-right-from-square"></i><?php endif; ?>
                            </a>
                        </section>
                    <?php endif; ?>

                    <?php if ($featuredLinks || $normalLinks): ?>
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
                    <?php endif; ?>

                    <?php if ($hasStats): ?>
                        <div class="bio-stats-grid profile-stats-compact js-reveal">
                            <?php foreach (array_slice($stats, 0, 4) as $stat): ?>
                                <article class="bio-stat-card"><i class="<?php echo profile_h($stat['icon']); ?>"></i><strong><?php echo profile_h($stat['value']); ?></strong><span><?php echo profile_h($stat['label']); ?></span></article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($visibleProjects): ?>
                        <section class="bio-card bio-details profile-clean-section js-reveal">
                            <?php profile_render_section_heading('fas fa-cubes', 'Progetti'); ?>
                            <div class="bio-project-grid">
                                <?php foreach ($visibleProjects as $project): ?>
                                    <a class="bio-project-card <?php echo !empty($project['is_featured']) ? 'is-pinned' : ''; ?>" href="<?php echo profile_h($project['url'] ?: '#'); ?>" <?php echo $project['url'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                        <span class="bio-project-card__icon"><i class="fas fa-layer-group"></i></span>
                                        <strong><?php echo profile_h($project['title']); ?></strong>
                                        <?php if (!empty($project['description'])): ?><p><?php echo profile_h($project['description']); ?></p><?php endif; ?>
                                        <small><?php echo profile_h($project['tech_stack'] ?: profile_status_label($project['status'])); ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if ($visibleBlocks): ?>
                        <section class="bio-card bio-details profile-clean-section js-reveal">
                            <?php profile_render_section_heading('fas fa-wand-magic-sparkles', 'Custom'); ?>
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
                    <?php endif; ?>

                    <?php if ($visibleContents): ?>
                        <section class="bio-card bio-details profile-clean-section js-reveal">
                            <?php profile_render_section_heading('fas fa-play-circle', 'Edit e contenuti'); ?>
                            <div class="bio-preview-grid">
                                <?php foreach ($visibleContents as $content): ?>
                                    <a class="bio-preview-card <?php echo !empty($content['is_featured']) ? 'is-pinned' : ''; ?>" href="<?php echo profile_h($content['url'] ?: '#'); ?>" <?php echo $content['url'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                        <span class="bio-preview-card__label"><?php echo profile_h($content['content_type']); ?></span>
                                        <span class="bio-preview-card__icon"><i class="fas fa-play"></i></span>
                                        <strong><?php echo profile_h($content['title']); ?></strong>
                                        <?php if (!empty($content['description'])): ?><p><?php echo profile_h($content['description']); ?></p><?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if ($visibleBadges): ?>
                        <section class="bio-card bio-details profile-clean-section js-reveal">
                            <?php profile_render_section_heading('fas fa-trophy', 'Badge'); ?>
                            <div class="profile-badge-grid">
                                <?php foreach ($visibleBadges as $badge): ?>
                                    <?php
                                    $achievementImage = !empty($badge['img_url']) ? '/img/' . ltrim((string)$badge['img_url'], '/') : null;
                                    $rarity = function_exists('profile_badge_rarity') ? profile_badge_rarity((int)($badge['punti'] ?? 0)) : ['label' => 'Badge', 'class' => 'common'];
                                    $isFeaturedBadge = (int)($profile['featured_badge_id'] ?? 0) === (int)$badge['id'];
                                    ?>
                                    <article class="profile-badge-card rarity-<?php echo profile_h($rarity['class']); ?> <?php echo $isFeaturedBadge ? 'is-featured' : ''; ?>" tabindex="0">
                                        <div class="profile-badge-art">
                                            <?php if ($achievementImage): ?><img src="<?php echo profile_h($achievementImage); ?>" alt="" loading="lazy"><?php else: ?><i class="fas fa-medal"></i><?php endif; ?>
                                        </div>
                                        <div class="profile-badge-info">
                                            <strong><?php echo profile_h($badge['nome']); ?></strong>
                                            <?php if (!empty($badge['descrizione'])): ?><p><?php echo profile_h($badge['descrizione']); ?></p><?php endif; ?>
                                            <small><?php echo profile_h($rarity['label']); ?><?php echo (int)($badge['punti'] ?? 0) > 0 ? ' · ' . (int)$badge['punti'] . ' punti' : ''; ?></small>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>


                    <?php if ($visibleActivity): ?>
                        <section class="bio-card bio-about js-reveal">
                            <?php profile_render_section_heading('fas fa-clock', 'Attività'); ?>
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
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    <?php endif; ?>

    <div class="profile-qr-modal" id="profileQrModal" aria-hidden="true">
        <div class="profile-qr-backdrop js-close-qr"></div>
        <section class="bio-card profile-qr-card" role="dialog" aria-modal="true" aria-label="QR profilo">
            <button class="bio-small-button js-close-qr" type="button" aria-label="Chiudi"><i class="fas fa-xmark"></i></button>
            <strong>QR profilo</strong>
            <img class="profile-qr-image" alt="QR code del profilo" src="/api/profile_qr.php?url=<?php echo rawurlencode($profileUrl); ?>" data-qr-src="/api/profile_qr.php?url=<?php echo rawurlencode($profileUrl); ?>">
            <button class="bio-button bio-button--primary js-copy-profile" type="button"><i class="fas fa-link"></i>Copia link</button>
        </section>
    </div>

    <div class="bio-toast" id="bioToast" role="status" aria-live="polite"></div>
    <!-- <?php if (file_exists(__DIR__ . '/includes/footer.php')) include __DIR__ . '/includes/footer.php'; ?> -->
</body>

</html>