<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$lang = cp_detect_lang();
$stats = cp_fetch_stats($mysqli);
$latest = cp_fetch_entries($mysqli, ['limit' => 6, 'order' => 'latest']);
$popular = cp_fetch_entries($mysqli, ['limit' => 6, 'order' => 'popular']);

$categoryMeta = [
    ['person', cp_t('people', $lang), $stats['person'], '#2f6bff'],
    ['event', cp_t('events', $lang), $stats['event'], '#60a5fa'],
    ['meme', cp_t('memes', $lang), $stats['meme'], '#f97316'],
];

$title = 'Cripsumpedia - Cripsum';
$description = cp_t('subtitle', $lang);
?>
<!DOCTYPE html>
<html lang="<?= cp_h($lang) ?>">

<head>
    <?php cp_render_head($title, $description, $lang); ?>
</head>

<body class="cp-body cp-page-home">
    <?php cp_render_background(); ?>
    <?php cp_render_topbar($lang, 'home'); ?>

    <main class="cp-shell">
        <?php if (!cp_schema_ready($mysqli)): ?>
            <?php cp_render_install_notice($lang); ?>
        <?php else: ?>
            <!-- Hero Section -->
            <section class="cp-hero cp-hero--minimal cp-reveal">
                <div class="cp-hero__copy">
                    <h1>Cripsumpedia</h1>
                    <p><?= cp_h($description) ?></p>

                    <div class="cp-hero__search">
                        <?php cp_render_search_box($lang, '', 'hero'); ?>
                    </div>

                    <div class="cp-hero__actions cp-hero__actions--simple">
                        <?php foreach ($categoryMeta as [$type, $label, $count, $accent]): ?>
                            <a class="cp-btn cp-btn--section" href="<?= cp_h(cp_url('category', ['type' => $type], $lang)) ?>" style="--entry-accent: <?= cp_h($accent) ?>">
                                <i class="fa-solid <?= cp_h(cp_type_icon($type)) ?>"></i>
                                <span><?= cp_h($label) ?></span>
                            </a>
                        <?php endforeach; ?>
                        <button class="cp-btn cp-btn--ghost" type="button" data-cp-random>
                            <i class="fa-solid fa-shuffle"></i>
                            <span><?= cp_h(cp_t('random', $lang)) ?></span>
                        </button>
                        <?php if (cp_is_admin_user()): ?>
                            <a class="cp-btn cp-btn--admin" href="<?= cp_h(cp_url('admin', [], $lang)) ?>">
                                <i class="fa-solid fa-shield-halved"></i>
                                <span>Admin</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Categories Grid -->
            <section class="cp-section cp-section--minimal cp-reveal">
                <div class="cp-section-head">
                    <div>
                        <h2><?= cp_h($lang === 'en' ? 'Sections' : 'Sezioni') ?></h2>
                    </div>
                </div>
                <div class="cp-category-grid">
                    <?php foreach ($categoryMeta as [$type, $label, $count, $accent]): ?>
                        <a class="cp-category-card" href="<?= cp_h(cp_url('category', ['type' => $type], $lang)) ?>" style="--entry-accent: <?= cp_h($accent) ?>">
                            <div class="cp-category-card__top">
                                <i class="fa-solid <?= cp_h(cp_type_icon($type)) ?>"></i>
                                <span class="cp-category-card__label"><?= cp_h($label) ?></span>
                                <strong><?= (int)$count ?></strong>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="cp-section cp-reveal">
                <div class="cp-section-head">
                    <div>
                        <h2><?= cp_h(cp_t('popular', $lang)) ?></h2>
                    </div>
                </div>
                <div class="cp-card-grid cp-card-grid--compact">
                    <?php foreach ($popular as $entry): ?>
                        <?php cp_render_entry_card($mysqli, $entry, $lang, 'cp-entry-card--compact cp-entry-card--quiet'); ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="cp-section cp-reveal">
                <div class="cp-section-head">
                    <div>
                        <h2><?= cp_h(cp_t('latest', $lang)) ?></h2>
                    </div>
                </div>
                <div class="cp-card-grid cp-card-grid--compact">
                    <?php foreach ($latest as $entry): ?>
                        <?php cp_render_entry_card($mysqli, $entry, $lang, 'cp-entry-card--compact cp-entry-card--quiet'); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php cp_render_footer($lang); ?>
</body>

</html>
