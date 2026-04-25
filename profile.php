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

    if ($profile['profile_visibility'] === 'private' && !$canEdit) {
        $isPrivateBlocked = true;
    } elseif ($profile['profile_visibility'] === 'logged_in' && !$isLoggedIn) {
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

function profile_empty_state(string $title, string $text): void
{
    ?>
    <div class="profile-empty-state">
        <div class="profile-empty-icon">✦</div>
        <h3><?php echo profile_h($title); ?></h3>
        <p><?php echo profile_h($text); ?></p>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include __DIR__ . '/includes/head-import.php'; ?>
    <title><?php echo $profile ? 'Cripsum™ - ' . profile_h($profile['display_name'] ?: $profile['username']) : 'Cripsum™ - Profilo'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($profile): ?>
        <meta name="description" content="<?php echo profile_h($profile['bio'] ?: 'Profilo pubblico su Cripsum™'); ?>">
        <meta property="og:title" content="<?php echo profile_h($profile['display_name'] ?: $profile['username']); ?> su Cripsum™">
        <meta property="og:description" content="<?php echo profile_h($profile['bio'] ?: 'Profilo pubblico su Cripsum™'); ?>">
        <meta property="og:image" content="/includes/get_pfp.php?id=<?php echo (int)$profile['id']; ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/profile.css?v=2.0">
</head>
<body class="profile-shell profile-theme-<?php echo $profile ? profile_h($profile['profile_theme'] ?: 'dark') : 'dark'; ?> profile-layout-<?php echo $profile ? profile_h($profile['profile_layout'] ?: 'standard') : 'standard'; ?>" style="--profile-accent: <?php echo $profile ? profile_h(profile_normalize_hex_color($profile['accent_color'] ?? '#0f5bff')) : '#0f5bff'; ?>;">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <?php if (file_exists(__DIR__ . '/includes/impostazioni.php')) include __DIR__ . '/includes/impostazioni.php'; ?>

    <main class="profile-page">
        <?php if ($isNotFound): ?>
            <section class="profile-state-card profile-animate-in">
                <span class="profile-state-badge">404</span>
                <h1>Profilo non trovato</h1>
                <p>Questo utente non esiste o ha cambiato username.</p>
                <form class="profile-search" action="/profile.php" method="get">
                    <input type="text" name="username" placeholder="Cerca username..." maxlength="20" autocomplete="off">
                    <button type="submit">Cerca</button>
                </form>
                <a class="profile-soft-link" href="/it/home">Torna alla home</a>
            </section>
        <?php elseif ($isPrivateBlocked): ?>
            <section class="profile-state-card profile-animate-in">
                <span class="profile-state-badge"><i class="fas fa-lock"></i></span>
                <h1>Profilo privato</h1>
                <p>@<?php echo profile_h($profile['username']); ?> ha scelto di non mostrare questo profilo pubblicamente.</p>
                <a class="profile-soft-link" href="/it/home">Torna alla home</a>
            </section>
        <?php elseif ($isLoginBlocked): ?>
            <section class="profile-state-card profile-animate-in">
                <span class="profile-state-badge"><i class="fas fa-user-lock"></i></span>
                <h1>Accesso richiesto</h1>
                <p>Questo profilo è visibile solo agli utenti registrati.</p>
                <a class="profile-main-button" href="/it/accedi">Accedi</a>
            </section>
        <?php else: ?>
            <?php
                $displayName = $profile['display_name'] ?: $profile['username'];
                $profileUrl = 'https://cripsum.com/u/' . rawurlencode(strtolower($profile['username']));
                $bannerUrl = !empty($profile['profile_banner_type']) ? '/includes/get_profile_banner.php?id=' . (int)$profile['id'] : null;
            ?>

            <section class="profile-hero profile-animate-in">
                <div class="profile-banner" <?php if ($bannerUrl): ?>style="background-image: linear-gradient(180deg, rgba(0,0,0,.1), rgba(0,0,0,.85)), url('<?php echo profile_h($bannerUrl); ?>');"<?php endif; ?>>
                    <div class="profile-banner-noise"></div>
                </div>

                <div class="profile-hero-content">
                    <div class="profile-avatar-wrap">
                        <img class="profile-avatar" src="/includes/get_pfp.php?id=<?php echo (int)$profile['id']; ?>&t=<?php echo time(); ?>" alt="Avatar di <?php echo profile_h($profile['username']); ?>">
                        <span class="profile-online-dot <?php echo $isOnline ? 'is-online' : 'is-offline'; ?>" title="<?php echo $isOnline ? 'Online' : 'Offline'; ?>"></span>
                    </div>

                    <div class="profile-title-area">
                        <div class="profile-kicker">
                            <span><?php echo $isOnline ? 'Online ora' : 'Ultima attività ' . profile_time_ago($lastSeen); ?></span>
                            <span>•</span>
                            <span>Membro dal <?php echo date('d/m/Y', strtotime($profile['data_creazione'])); ?></span>
                        </div>
                        <h1><?php echo profile_h($displayName); ?></h1>
                        <p class="profile-username">@<?php echo profile_h($profile['username']); ?></p>
                        <?php if (!empty($profile['bio'])): ?>
                            <p class="profile-bio"><?php echo nl2br(profile_h($profile['bio'])); ?></p>
                        <?php else: ?>
                            <p class="profile-bio profile-muted">Questo profilo non ha ancora una bio.</p>
                        <?php endif; ?>
                    </div>

                    <div class="profile-actions">
                        <?php if ($canEdit): ?>
                            <a class="profile-main-button" href="/edit-profile.php<?php echo profile_is_staff() && !$isOwnProfile ? '?user_id=' . (int)$profile['id'] : ''; ?>">
                                <i class="fas fa-pen"></i> Modifica
                            </a>
                        <?php endif; ?>
                        <button class="profile-icon-button" type="button" data-copy-profile="<?php echo profile_h($profileUrl); ?>" title="Copia link">
                            <i class="fas fa-link"></i>
                        </button>
                        <button class="profile-icon-button" type="button" data-share-profile data-title="<?php echo profile_h($displayName); ?>" data-url="<?php echo profile_h($profileUrl); ?>" title="Condividi">
                            <i class="fas fa-share-nodes"></i>
                        </button>
                    </div>
                </div>
            </section>

            <section class="profile-grid">
                <aside class="profile-side profile-animate-in profile-delay-1">
                    <div class="profile-card">
                        <h2>Statistiche</h2>
                        <div class="profile-stats-grid">
                            <div class="profile-stat"><strong><?php echo (int)$profile['num_achievement']; ?></strong><span>Badge</span></div>
                            <div class="profile-stat"><strong><?php echo (int)$profile['num_personaggi']; ?></strong><span>Personaggi</span></div>
                            <div class="profile-stat"><strong><?php echo number_format((int)$profile['profile_views']); ?></strong><span>Visite</span></div>
                            <div class="profile-stat"><strong><?php echo profile_h($profile['ruolo']); ?></strong><span>Ruolo</span></div>
                        </div>
                    </div>

                    <div class="profile-card">
                        <div class="profile-section-head">
                            <h2>Social</h2>
                        </div>
                        <?php if ($socials): ?>
                            <div class="profile-social-list">
                                <?php foreach ($socials as $social): ?>
                                    <a class="profile-social" href="<?php echo profile_h($social['url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <i class="<?php echo profile_h(profile_social_icon_class($social['platform'])); ?>"></i>
                                        <span><?php echo profile_h($social['label'] ?: ucfirst($social['platform'])); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <?php profile_empty_state('Nessun social', 'Quando verranno aggiunti, li vedrai qui.'); ?>
                        <?php endif; ?>
                    </div>

                    <div class="profile-card">
                        <h2>Badge in vista</h2>
                        <?php if ($badges): ?>
                            <div class="profile-badge-list">
                                <?php foreach ($badges as $badge): ?>
                                    <div class="profile-badge-item" title="<?php echo profile_h($badge['descrizione'] ?? ''); ?>">
                                        <?php if (!empty($badge['img_url'])): ?>
                                            <img src="/img/<?php echo profile_h($badge['img_url']); ?>" alt="">
                                        <?php else: ?>
                                            <span>✦</span>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?php echo profile_h($badge['nome']); ?></strong>
                                            <small><?php echo (int)($badge['punti'] ?? 0); ?> punti</small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <?php profile_empty_state('Nessun badge scelto', 'L’utente può scegliere quali achievement mostrare.'); ?>
                        <?php endif; ?>
                    </div>
                </aside>

                <div class="profile-main profile-animate-in profile-delay-2">
                    <section class="profile-card profile-featured-links">
                        <div class="profile-section-head">
                            <div>
                                <h2>Link in evidenza</h2>
                                <p>Le cose più importanti da aprire subito.</p>
                            </div>
                        </div>
                        <?php if ($links): ?>
                            <div class="profile-link-stack">
                                <?php foreach ($links as $link): ?>
                                    <a class="profile-link-card <?php echo ((int)$link['is_featured'] === 1) ? 'is-featured' : ''; ?>" href="<?php echo profile_h($link['url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <span class="profile-link-icon"><i class="<?php echo profile_h($link['icon'] ?: 'fas fa-arrow-up-right-from-square'); ?>"></i></span>
                                        <span>
                                            <strong><?php echo profile_h($link['title']); ?></strong>
                                            <?php if (!empty($link['description'])): ?><small><?php echo profile_h($link['description']); ?></small><?php endif; ?>
                                        </span>
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <?php profile_empty_state('Nessun link aggiunto', 'Qui possono stare portfolio, server Discord, shop, repo o pagine importanti.'); ?>
                        <?php endif; ?>
                    </section>

                    <section class="profile-card profile-collapse-section is-open" data-profile-collapse>
                        <button class="profile-collapse-title" type="button">
                            <span><i class="fas fa-layer-group"></i> Progetti</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="profile-collapse-body">
                            <?php if ($projects): ?>
                                <div class="profile-project-grid">
                                    <?php foreach ($projects as $project): ?>
                                        <article class="profile-project-card">
                                            <?php if (!empty($project['image_url'])): ?>
                                                <img src="<?php echo profile_h($project['image_url']); ?>" alt="">
                                            <?php endif; ?>
                                            <div>
                                                <span class="profile-pill"><?php echo profile_h(profile_status_label($project['status'])); ?></span>
                                                <h3><?php echo profile_h($project['title']); ?></h3>
                                                <?php if (!empty($project['description'])): ?><p><?php echo profile_h($project['description']); ?></p><?php endif; ?>
                                                <?php if (!empty($project['tech_stack'])): ?><small><?php echo profile_h($project['tech_stack']); ?></small><?php endif; ?>
                                                <?php if (!empty($project['url'])): ?><a href="<?php echo profile_h($project['url']); ?>" target="_blank" rel="noopener noreferrer">Apri progetto</a><?php endif; ?>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <?php profile_empty_state('Nessun progetto pubblico', 'Quando l’utente aggiunge progetti, compaiono qui.'); ?>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="profile-card profile-collapse-section is-open" data-profile-collapse>
                        <button class="profile-collapse-title" type="button">
                            <span><i class="fas fa-play"></i> Edit e contenuti</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="profile-collapse-body">
                            <?php if ($contents): ?>
                                <div class="profile-content-grid">
                                    <?php foreach ($contents as $content): ?>
                                        <a class="profile-content-card" href="<?php echo profile_h($content['url'] ?: '#'); ?>" <?php if (!empty($content['url'])): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>>
                                            <div class="profile-content-thumb" <?php if (!empty($content['thumbnail_url'])): ?>style="background-image: url('<?php echo profile_h($content['thumbnail_url']); ?>')"<?php endif; ?>>
                                                <i class="fas fa-play"></i>
                                            </div>
                                            <div>
                                                <span><?php echo profile_h($content['content_type']); ?></span>
                                                <strong><?php echo profile_h($content['title']); ?></strong>
                                                <?php if (!empty($content['description'])): ?><small><?php echo profile_h($content['description']); ?></small><?php endif; ?>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <?php profile_empty_state('Nessun contenuto', 'Perfetto per edit, video, giochi o post in evidenza.'); ?>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="profile-card">
                        <h2>Attività recente</h2>
                        <?php if ($activity): ?>
                            <div class="profile-activity-list">
                                <?php foreach ($activity as $item): ?>
                                    <div class="profile-activity-item">
                                        <span class="profile-activity-dot"></span>
                                        <div>
                                            <?php if (!empty($item['url'])): ?>
                                                <a href="<?php echo profile_h($item['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo profile_h($item['label']); ?></a>
                                            <?php else: ?>
                                                <strong><?php echo profile_h($item['label']); ?></strong>
                                            <?php endif; ?>
                                            <small><?php echo profile_time_ago($item['created_at'] ?? null); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <?php profile_empty_state('Ancora tutto fermo', 'Le attività pubbliche appariranno qui.'); ?>
                        <?php endif; ?>
                    </section>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <div class="profile-toast" id="profileToast" role="status" aria-live="polite"></div>
    <?php if (file_exists(__DIR__ . '/includes/footer.php')) include __DIR__ . '/includes/footer.php'; ?>
    <script src="/assets/js/profile.js?v=2.0"></script>
</body>
</html>
