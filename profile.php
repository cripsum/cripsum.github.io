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
$socials = $links = $projects = $contents = $badges = $activity = [];
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
        $badges = profile_list_visible_badges($mysqli, $profileId);
        $activity = profile_recent_activity($mysqli, $profileId);

        if (function_exists('isUserOnline')) {
            $isOnline = isUserOnline($mysqli, $profileId);
        }
        $lastSeen = $profile['ultimo_accesso'] ?? null;
    }
}

function profile_state_page(string $code, string $title, string $text, ?string $buttonText = null, ?string $buttonUrl = null): void
{
    ?>
    <main class="bio-page bio-state-page">
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

function profile_bio_empty(string $icon, string $title, string $text): void
{
    ?>
    <div class="bio-empty-state">
        <i class="<?php echo profile_h($icon); ?>"></i>
        <strong><?php echo profile_h($title); ?></strong>
        <p><?php echo profile_h($text); ?></p>
    </div>
    <?php
}

$theme = $profile ? profile_allowed_value((string)($profile['profile_theme'] ?? 'dark'), ['dark', 'light', 'auto'], 'dark') : 'dark';
$accent = $profile ? profile_normalize_hex_color($profile['accent_color'] ?? '#0f5bff') : '#0f5bff';
if ($theme === 'auto') {
    $theme = 'dark';
}

$displayName = $profile ? ($profile['display_name'] ?: $profile['username']) : 'Profilo';
$profileUrl = $profile ? 'https://cripsum.com/u/' . rawurlencode(strtolower($profile['username'])) : 'https://cripsum.com/profile.php';
$discordId = $profile ? trim((string)($profile['discord_id'] ?? '')) : '';
$bannerUrl = $profile && !empty($profile['profile_banner_type']) ? '/includes/get_profile_banner.php?id=' . (int)$profile['id'] : null;
$defaultBackgroundVideo = '/vid/Shorekeeper Wallpaper 4K Loop.mp4';
$backgroundMediaUrl = $bannerUrl ?: $defaultBackgroundVideo;
$backgroundMediaType = $bannerUrl ? (string)$profile['profile_banner_type'] : 'video/mp4';
$backgroundIsVideo = str_starts_with($backgroundMediaType, 'video/');
$backgroundIsImage = str_starts_with($backgroundMediaType, 'image/');
$featuredLinks = array_values(array_filter($links, fn($item) => (int)($item['is_featured'] ?? 0) === 1));
if (!$featuredLinks) {
    $featuredLinks = array_slice($links, 0, 4);
}
$featuredProjects = array_values(array_filter($projects, fn($item) => (int)($item['is_featured'] ?? 0) === 1));
if (!$featuredProjects) {
    $featuredProjects = $projects;
}
$featuredContents = array_values(array_filter($contents, fn($item) => (int)($item['is_featured'] ?? 0) === 1));
if (!$featuredContents) {
    $featuredContents = $contents;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include __DIR__ . '/includes/head-import.php'; ?>
    <title><?php echo $profile ? 'Cripsum™ - ' . profile_h($displayName) : 'Cripsum™ - Profilo'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($profile): ?>
        <meta name="description" content="<?php echo profile_h($profile['bio'] ?: 'Profilo pubblico su Cripsum™'); ?>">
        <meta property="og:title" content="<?php echo profile_h($displayName); ?> su Cripsum™">
        <meta property="og:description" content="<?php echo profile_h($profile['bio'] ?: 'Profilo pubblico su Cripsum™'); ?>">
        <meta property="og:type" content="profile">
        <meta property="og:url" content="<?php echo profile_h($profileUrl); ?>">
        <meta property="og:image" content="/includes/get_pfp.php?id=<?php echo (int)$profile['id']; ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/profile.css?v=2.3-full-bg">
    <script src="/assets/js/profile.js?v=2.3-full-bg" defer></script>
</head>
<body
    class="bio-v2-body public-profile-body"
    data-theme="<?php echo profile_h($theme); ?>"
    data-accent="<?php echo profile_h($accent); ?>"
    data-profile-url="<?php echo profile_h($profileUrl); ?>"
    data-discord-id="<?php echo profile_h($discordId); ?>"
>
    <?php
    if (file_exists(__DIR__ . '/includes/navbar-bio.php')) {
        include __DIR__ . '/includes/navbar-bio.php';
    } else {
        include __DIR__ . '/includes/navbar.php';
    }
    if (file_exists(__DIR__ . '/includes/impostazioni.php')) include __DIR__ . '/includes/impostazioni.php';
    ?>

    <div class="bio-background" aria-hidden="true">
        <?php if ($backgroundIsVideo): ?>
            <video class="bio-background__media" autoplay muted loop playsinline poster="">
                <source src="<?php echo profile_h($backgroundMediaUrl); ?>" type="<?php echo profile_h($backgroundMediaType); ?>">
            </video>
        <?php elseif ($backgroundIsImage): ?>
            <img class="bio-background__media" src="<?php echo profile_h($backgroundMediaUrl); ?>" alt="" loading="eager">
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

    <?php if ($isNotFound): ?>
        <?php profile_state_page('404', 'Profilo non trovato', 'Questo utente non esiste o ha cambiato username.', 'Torna alla home', '/it/home'); ?>
    <?php elseif ($isPrivateBlocked): ?>
        <?php profile_state_page('Privato', 'Profilo privato', '@' . $profile['username'] . ' ha scelto di non mostrare questo profilo.', 'Torna alla home', '/it/home'); ?>
    <?php elseif ($isLoginBlocked): ?>
        <?php profile_state_page('Login', 'Accesso richiesto', 'Questo profilo è visibile solo agli utenti registrati.', 'Accedi', '/it/accedi'); ?>
    <?php else: ?>
        <main class="bio-page" id="bioPage">
            <section class="bio-hero bio-card js-tilt-card js-reveal" aria-label="Profilo pubblico">
                <div class="bio-hero__topline">
                    <span class="bio-pill <?php echo $isOnline ? 'bio-pill--live' : ''; ?>">
                        <span class="bio-dot <?php echo $isOnline ? '' : 'bio-dot--off'; ?>"></span>
                        <?php echo $isOnline ? 'online ora' : 'offline'; ?>
                    </span>
                    <span class="bio-pill">Cripsum™ profile</span>
                </div>

                <div class="bio-avatar-wrap">
                    <div class="bio-avatar-ring"></div>
                    <img class="bio-avatar" src="/includes/get_pfp.php?id=<?php echo (int)$profile['id']; ?>&t=<?php echo (int)strtotime((string)($profile['profile_updated_at'] ?? 'now')); ?>" alt="Avatar di <?php echo profile_h($profile['username']); ?>" loading="eager">
                </div>

                <div class="bio-name-block">
                    <p class="bio-kicker"><?php echo profile_h($profile['ruolo'] ?: 'utente'); ?> · <?php echo $isOnline ? 'attivo adesso' : 'ultima attività ' . profile_time_ago($lastSeen); ?></p>
                    <h1><?php echo profile_h($displayName); ?></h1>
                    <p class="bio-username">@<?php echo profile_h($profile['username']); ?></p>
                </div>

                <?php if (!empty($profile['bio'])): ?>
                    <p class="bio-tagline"><?php echo nl2br(profile_h($profile['bio'])); ?></p>
                    <p class="bio-description">Profilo pubblico su Cripsum: link, contenuti, progetti e badge in un posto solo.</p>
                <?php else: ?>
                    <p class="bio-tagline">Questo profilo non ha ancora una bio.</p>
                    <p class="bio-description">Quando l’utente aggiunge una descrizione, apparirà qui.</p>
                <?php endif; ?>

                <div class="bio-badges" aria-label="Badge profilo">
                    <?php if ($badges): ?>
                        <?php foreach (array_slice($badges, 0, 3) as $badge): ?>
                            <span class="bio-badge"><i class="fas fa-trophy"></i><?php echo profile_h($badge['nome']); ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="bio-badge"><i class="fas fa-user"></i>Membro</span>
                        <span class="bio-badge"><i class="fas fa-shield-halved"></i><?php echo profile_h($profile['ruolo'] ?: 'utente'); ?></span>
                    <?php endif; ?>
                </div>

                <div class="bio-meta-row">
                    <span><i class="fas fa-calendar"></i>Dal <?php echo date('d/m/Y', strtotime($profile['data_creazione'])); ?></span>
                    <span><i class="fas fa-eye"></i><?php echo profile_compact_number($profile['profile_views']); ?> visite</span>
                    <?php if ($discordId): ?><span><i class="fab fa-discord"></i>Discord collegato</span><?php endif; ?>
                </div>

                <div class="bio-actions" aria-label="Azioni profilo">
                    <?php if ($canEdit): ?>
                        <a class="bio-button bio-button--primary" href="/edit-profile.php<?php echo profile_is_staff() && !$isOwnProfile ? '?user_id=' . (int)$profile['id'] : ''; ?>">
                            <i class="fas fa-pen"></i> Modifica
                        </a>
                    <?php else: ?>
                        <button class="bio-button bio-button--primary js-copy-profile" type="button"><i class="fas fa-link"></i>Copia link</button>
                    <?php endif; ?>
                    <button class="bio-button js-share-profile" type="button"><i class="fas fa-share-nodes"></i>Share</button>
                    <button class="bio-icon-button js-theme-toggle" type="button" aria-label="Cambia tema"><i class="fas fa-moon"></i></button>
                    <?php if ($canEdit): ?>
                        <button class="bio-button bio-button--full js-copy-profile" type="button"><i class="fas fa-link"></i>Copia link</button>
                    <?php endif; ?>
                </div>

                <?php if ($socials): ?>
                    <div class="bio-social-grid" aria-label="Link social">
                        <?php foreach ($socials as $social): ?>
                            <a class="bio-social" href="<?php echo profile_h($social['url']); ?>" target="_blank" rel="noopener noreferrer">
                                <span class="bio-social__icon"><i class="<?php echo profile_h(profile_social_icon_class($social['platform'])); ?>"></i></span>
                                <span>
                                    <strong><?php echo profile_h($social['label'] ?: ucfirst($social['platform'])); ?></strong>
                                    <small><?php echo profile_h(profile_short_url_label($social['url'])); ?></small>
                                </span>
                                <i class="fas fa-arrow-up-right-from-square bio-social__arrow"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php profile_bio_empty('fas fa-link', 'Nessun social', 'Questo utente non ha ancora aggiunto link social.'); ?>
                <?php endif; ?>

                <div class="bio-discord-panel" aria-live="polite">
                    <div class="bio-section-heading bio-section-heading--small">
                        <span><i class="fab fa-discord"></i> Discord status</span>
                        <small><?php echo $discordId ? 'si aggiorna ogni 30s' : 'non collegato'; ?></small>
                    </div>
                    <div class="discord-box" id="discordBox">
                        <?php if ($discordId): ?>
                            <?php $discordProfileId = $discordId; require __DIR__ . '/includes/discord_status.php'; ?>
                        <?php else: ?>
                            <?php profile_bio_empty('fab fa-discord', 'Discord non collegato', 'L’utente può aggiungere il proprio Discord dalla modifica profilo.'); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="bio-content" aria-label="Contenuti profilo">
                <div class="bio-stats-grid js-reveal">
                    <article class="bio-stat-card"><i class="fas fa-trophy"></i><strong><?php echo profile_compact_number($profile['num_achievement']); ?></strong><span>Badge</span></article>
                    <article class="bio-stat-card"><i class="fas fa-user-astronaut"></i><strong><?php echo profile_compact_number($profile['num_personaggi']); ?></strong><span>Personaggi</span></article>
                    <article class="bio-stat-card"><i class="fas fa-dice-d20"></i><strong><?php echo profile_compact_number($profile['total_personaggi']); ?></strong><span>Pull totali</span></article>
                    <article class="bio-stat-card"><i class="fas fa-eye"></i><strong><?php echo profile_compact_number($profile['profile_views']); ?></strong><span>Visite</span></article>
                </div>

                <section class="bio-card bio-featured js-reveal">
                    <div class="bio-section-heading"><div><span>In evidenza</span><p>Link principali scelti da questo profilo.</p></div></div>
                    <?php if ($featuredLinks): ?>
                        <div class="bio-featured-grid">
                            <?php foreach ($featuredLinks as $item): ?>
                                <a class="bio-featured-link" href="<?php echo profile_h($item['url']); ?>" target="_blank" rel="noopener noreferrer">
                                    <span class="bio-featured-link__icon"><i class="<?php echo profile_h($item['icon'] ?: 'fas fa-link'); ?>"></i></span>
                                    <span class="bio-featured-link__content">
                                        <small><?php echo !empty($item['is_featured']) ? 'Featured' : 'Link'; ?></small>
                                        <strong><?php echo profile_h($item['title']); ?></strong>
                                        <em><?php echo profile_h($item['description'] ?: profile_short_url_label($item['url'])); ?></em>
                                    </span>
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <?php profile_bio_empty('fas fa-star', 'Nessun link in evidenza', 'I link principali appariranno qui.'); ?>
                    <?php endif; ?>
                </section>

                <details class="bio-card bio-details js-reveal" open>
                    <summary><span><i class="fas fa-cubes"></i> Progetti</span><i class="fas fa-chevron-down"></i></summary>
                    <?php if ($featuredProjects): ?>
                        <div class="bio-project-grid">
                            <?php foreach ($featuredProjects as $project): ?>
                                <a class="bio-project-card" href="<?php echo profile_h($project['url'] ?: '#'); ?>" <?php echo $project['url'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                    <span class="bio-project-card__icon"><i class="fas fa-layer-group"></i></span>
                                    <strong><?php echo profile_h($project['title']); ?></strong>
                                    <p><?php echo profile_h($project['description'] ?: 'Nessuna descrizione.'); ?></p>
                                    <small><?php echo profile_h($project['tech_stack'] ?: profile_status_label($project['status'])); ?></small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <?php profile_bio_empty('fas fa-cube', 'Nessun progetto', 'Quando aggiunge progetti, li vedrai qui.'); ?>
                    <?php endif; ?>
                </details>

                <details class="bio-card bio-details js-reveal" open>
                    <summary><span><i class="fas fa-play-circle"></i> Edit e contenuti</span><i class="fas fa-chevron-down"></i></summary>
                    <?php if ($featuredContents): ?>
                        <div class="bio-preview-grid">
                            <?php foreach ($featuredContents as $content): ?>
                                <a class="bio-preview-card" href="<?php echo profile_h($content['url'] ?: '#'); ?>" <?php echo $content['url'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                    <span class="bio-preview-card__label"><?php echo profile_h($content['content_type']); ?></span>
                                    <span class="bio-preview-card__icon"><i class="fas fa-play"></i></span>
                                    <strong><?php echo profile_h($content['title']); ?></strong>
                                    <p><?php echo profile_h($content['description'] ?: 'Contenuto in evidenza.'); ?></p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <?php profile_bio_empty('fas fa-clapperboard', 'Nessun contenuto', 'Edit, video o showcase appariranno qui.'); ?>
                    <?php endif; ?>
                </details>

                <details class="bio-card bio-details js-reveal" open>
                    <summary><span><i class="fas fa-trophy"></i> Badge mostrati</span><i class="fas fa-chevron-down"></i></summary>
                    <?php if ($badges): ?>
                        <div class="bio-achievement-grid">
                            <?php foreach ($badges as $badge): ?>
                                <?php $achievementImage = !empty($badge['img_url']) ? '/img/' . ltrim((string)$badge['img_url'], '/') : null; ?>
                                <article class="bio-achievement-card">
                                    <?php if ($achievementImage): ?><img src="<?php echo profile_h($achievementImage); ?>" alt="" loading="lazy"><?php else: ?><span class="bio-achievement-card__fallback"><i class="fas fa-medal"></i></span><?php endif; ?>
                                    <div><strong><?php echo profile_h($badge['nome']); ?></strong><p><?php echo profile_h($badge['descrizione'] ?: 'Achievement sbloccato.'); ?></p><small><?php echo (int)($badge['punti'] ?? 0); ?> punti</small></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <?php profile_bio_empty('fas fa-medal', 'Nessun badge mostrato', 'L’utente può scegliere quali achievement mostrare.'); ?>
                    <?php endif; ?>
                </details>

                <section class="bio-card bio-about js-reveal">
                    <div class="bio-section-heading"><div><span>Attività recente</span><p>Update pubblici e ultimi achievement.</p></div></div>
                    <?php if ($activity): ?>
                        <div class="bio-info-list bio-activity-list">
                            <?php foreach ($activity as $item): ?>
                                <div>
                                    <span><?php echo profile_h($item['activity_type']); ?> · <?php echo profile_h(profile_time_ago($item['created_at'] ?? null)); ?></span>
                                    <?php if (!empty($item['url'])): ?>
                                        <strong><a href="<?php echo profile_h($item['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo profile_h($item['label']); ?></a></strong>
                                    <?php else: ?>
                                        <strong><?php echo profile_h($item['label']); ?></strong>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <?php profile_bio_empty('fas fa-clock', 'Nessuna attività recente', 'Gli aggiornamenti pubblici appariranno qui.'); ?>
                    <?php endif; ?>
                </section>

                <section class="bio-card bio-about js-reveal">
                    <div class="bio-section-heading"><div><span>Info rapide</span><p>Dettagli utili senza riempire troppo la pagina.</p></div></div>
                    <div class="bio-info-list">
                        <div><span>Profilo</span><strong><?php echo profile_h($displayName); ?></strong></div>
                        <div><span>Username</span><strong>@<?php echo profile_h($profile['username']); ?></strong></div>
                        <div><span>Account creato</span><strong><?php echo date('d/m/Y', strtotime($profile['data_creazione'])); ?></strong></div>
                        <div><span>Ultima attività</span><strong><?php echo $isOnline ? 'online ora' : profile_h(profile_time_ago($lastSeen)); ?></strong></div>
                    </div>
                </section>
            </section>
        </main>
    <?php endif; ?>

    <div class="bio-toast" id="bioToast" role="status" aria-live="polite"></div>
    <?php if (file_exists(__DIR__ . '/includes/footer.php')) include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
