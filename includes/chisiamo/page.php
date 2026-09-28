<?php
/**
 * /it/chisiamo e /en/chisiamo: la testata col collage delle foto, il team
 * (card come quelle di sempre, piu' tag, social, profilo e gemma Premium)
 * e il riquadro per candidarsi.
 *
 * Variabili attese: $cLang ('it' | 'en'), $mysqli.
 */

require_once __DIR__ . '/chisiamo.php';
require_once __DIR__ . '/strings.php';

$S = chisiamo_strings($cLang);
$cReady = chisiamo_ready($mysqli);

$cMembers = [];
if ($cReady) {
    try {
        $cMembers = array_map(
            static fn(array $row): array => chisiamo_member_view($row, $cLang),
            chisiamo_member_rows($mysqli)
        );
    } catch (Throwable $e) {
        error_log('[chisiamo] ' . $e->getMessage());
        $cReady = false;
    }
}

$cTexts = chisiamo_page_texts($cReady ? $mysqli : null, $cLang, $S);

// Il collage: 5 foto a caso del team, diverse a ogni caricamento.
$cMosaic = array_values(array_filter($cMembers, static fn(array $m): bool => $m['photo'] !== ''));
shuffle($cMosaic);
$cMosaic = array_slice($cMosaic, 0, 5);
$cMore = max(0, count($cMembers) - count($cMosaic));

$ogTitle = 'Cripsum™ - ' . $S['page_title'];
$ogDescription = $cTexts['subtitle'] !== '' ? $cTexts['subtitle'] : $S['meta_description'];
$cApplyUrl = '/' . $cLang . '/candidatura-chisiamo';
?>
<!DOCTYPE html>
<html lang="<?php echo shop_h($cLang); ?>">

<head>
    <?php include __DIR__ . '/../head-import.php'; ?>
    <title><?php echo shop_h('Cripsum™ - ' . $S['page_title']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="<?php echo shop_h(cripsum_asset('/assets/chisiamo/chisiamo.css')); ?>">
    <script src="<?php echo shop_h(cripsum_asset('/assets/chisiamo/chisiamo.js')); ?>" defer></script>
</head>

<body class="about-page">
    <?php include __DIR__ . '/../navbar.php'; ?>

    <main class="main-container about-shell">
        <section class="about-hero">
            <div class="about-hero__text">
                <h1 class="about-title"><?php echo chisiamo_title_html($cTexts['title']); ?></h1>
                <?php if ($cTexts['subtitle'] !== ''): ?>
                    <p class="about-lead"><?php echo shop_h($cTexts['subtitle']); ?></p>
                <?php endif; ?>
                <div class="about-actions">
                    <a class="about-btn about-btn--primary" href="<?php echo shop_h($cApplyUrl); ?>"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> <?php echo shop_h($S['apply']); ?></a>
                    <?php if ($cMembers): ?>
                        <a class="about-btn" href="#team"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i> <?php echo shop_h($S['meet_team']); ?></a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($cMosaic): ?>
                <div class="about-mosaic about-mosaic--<?php echo count($cMosaic); ?>" aria-hidden="true">
                    <?php foreach ($cMosaic as $i => $m): ?>
                        <img class="about-mosaic__photo about-mosaic__photo--<?php echo $i + 1; ?>" src="<?php echo shop_h($m['photo']); ?>" alt="" loading="eager" decoding="async">
                    <?php endforeach; ?>
                    <?php if ($cMore > 0): ?>
                        <span class="about-mosaic__more">+<?php echo (int)$cMore; ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!$cReady || !$cMembers): ?>
            <section class="about-state">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                <strong><?php echo shop_h($cReady ? $S['empty_title'] : $S['soon_title']); ?></strong>
                <p><?php echo shop_h($cReady ? $S['empty_text'] : $S['soon_text']); ?></p>
            </section>
        <?php else: ?>
            <section class="team-section" id="team" aria-label="<?php echo shop_h($S['team_label']); ?>">
                <div class="team-grid">
                    <?php foreach ($cMembers as $m): ?>
                        <article class="team-member">
                            <div class="member-content">
                                <div class="member-image">
                                    <?php if ($m['photo'] !== ''): ?>
                                        <img src="<?php echo shop_h($m['photo']); ?>" alt="<?php echo shop_h($m['name']); ?>" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <span class="member-initials" aria-hidden="true"><?php echo shop_h($m['initials']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="member-info">
                                    <h3 class="member-name">
                                        <?php if ($m['name_url'] !== ''): ?>
                                            <a href="<?php echo shop_h($m['name_url']); ?>"<?php echo $m['name_external'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo shop_h($m['name']); ?></a>
                                        <?php else: ?>
                                            <?php echo shop_h($m['name']); ?>
                                        <?php endif; ?>
                                        <?php if ($m['premium']): ?>
                                            <img class="cr-premium-gem member-premium" src="/img/premium.svg" alt="<?php echo shop_h($S['premium']); ?>" title="<?php echo shop_h($S['premium']); ?>">
                                        <?php endif; ?>
                                    </h3>
                                    <?php if ($m['tag'] !== ''): ?>
                                        <span class="member-tag"><?php echo shop_h($m['tag']); ?></span>
                                    <?php endif; ?>
                                    <p class="member-description"><?php echo $m['description']; ?></p>
                                    <?php if ($m['socials'] || $m['profile_url'] !== ''): ?>
                                        <div class="member-links">
                                            <?php foreach ($m['socials'] as $s): ?>
                                                <a class="member-social" href="<?php echo shop_h($s['url']); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo shop_h($s['label']); ?>" aria-label="<?php echo shop_h(sprintf($S['social_of'], $s['label'], $m['name'])); ?>"><i class="<?php echo shop_h($s['icon']); ?>" aria-hidden="true"></i></a>
                                            <?php endforeach; ?>
                                            <?php if ($m['profile_url'] !== ''): ?>
                                                <a class="member-profile" href="<?php echo shop_h($m['profile_url']); ?>" aria-label="<?php echo shop_h(sprintf($S['profile_of'], $m['name'])); ?>"><i class="fa-solid fa-user" aria-hidden="true"></i> <?php echo shop_h($S['profile']); ?></a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="join-team-section">
            <h2 class="join-title"><?php echo shop_h($cTexts['join_title']); ?></h2>
            <?php if ($cTexts['join_text'] !== ''): ?>
                <p class="join-description"><?php echo shop_h($cTexts['join_text']); ?></p>
            <?php endif; ?>
            <a href="<?php echo shop_h($cApplyUrl); ?>" class="join-email">
                <i class="fa-solid fa-paper-plane me-2" aria-hidden="true"></i><?php echo shop_h($cTexts['join_button']); ?>
            </a>
        </section>
    </main>

    <?php include __DIR__ . '/../scroll_indicator.php'; ?>
    <?php include __DIR__ . '/../' . ($cLang === 'en' ? 'footer-en.php' : 'footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
