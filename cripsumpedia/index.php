<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$lang = cp_detect_lang();
$stats = cp_fetch_stats($mysqli);
$latest = cp_fetch_entries($mysqli, ['limit' => 6, 'order' => 'latest']);
$updated = cp_fetch_entries($mysqli, ['limit' => 6, 'order' => 'updated']);
$popular = cp_fetch_entries($mysqli, ['limit' => 5, 'order' => 'popular']);
$random = cp_fetch_entries($mysqli, ['limit' => 4, 'order' => 'random']);
$heroEntry = $popular[0] ?? $latest[0] ?? $updated[0] ?? null;
$categorySamples = [];
foreach (CP_ENTRY_TYPES as $sampleType) {
    $categorySamples[$sampleType] = cp_fetch_entries($mysqli, [
        'type' => $sampleType,
        'limit' => 3,
        'order' => 'popular',
    ]);
}

$categoryMeta = [
    ['person', cp_t('people', $lang), $stats['person'], $lang === 'en' ? 'Characters, rivalries, aliases and historic appearances.' : 'Personaggi, rivalità, alias e presenze storiche.', '#2f6bff'],
    ['event', cp_t('events', $lang), $stats['event'], $lang === 'en' ? 'Canonical chronology, incidents and legendary chapters.' : 'Cronologia canonica, incidenti e capitoli leggendari.', '#60a5fa'],
    ['meme', cp_t('memes', $lang), $stats['meme'], $lang === 'en' ? 'Origins, recurring jokes and artifacts worth saving.' : 'Origini, running joke e reperti da conservare.', '#f97316'],
];

$events = cp_fetch_entries($mysqli, ['type' => 'event', 'limit' => 30, 'order' => 'importance']);
$dailyEvent = $events ? $events[cp_seeded_daily_index(count($events))] : null;
$quote = cp_fetch_quotes($mysqli, null, 1, true)[0] ?? cp_fetch_quotes($mysqli, null, 1, false)[0] ?? null;

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
            <section class="cp-hero cp-hero--home cp-reveal">
                <div class="cp-hero__copy">
                    <span class="cp-kicker"><i class="fa-solid fa-book-open"></i> Lore Database</span>
                    <h1>Cripsumpedia</h1>
                    <p><?= cp_h($description) ?></p>

                    <div class="cp-hero__search">
                        <?php cp_render_search_box($lang, '', 'hero'); ?>
                    </div>

                    <div class="cp-hero__actions cp-hero__actions--guided">
                        <?php foreach ($categoryMeta as [$type, $label, $count, $copy, $accent]): ?>
                            <a class="cp-btn cp-btn--guide" href="<?= cp_h(cp_url('category', ['type' => $type], $lang)) ?>" style="--entry-accent: <?= cp_h($accent) ?>">
                                <i class="fa-solid <?= cp_h(cp_type_icon($type)) ?>"></i>
                                <span><?= cp_h($label) ?></span>
                                <small><?= (int)$count ?></small>
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

                <!-- Stats Panel -->
                <aside class="cp-hero__panel cp-hero__panel--guide">
                    <?php if ($heroEntry): ?>
                        <?php $hero = cp_entry_public($heroEntry, $lang, $mysqli, false); ?>
                        <a class="cp-spotlight-entry" href="<?= cp_h($hero['url']) ?>" style="--entry-accent: <?= cp_h($hero['accent']) ?>">
                            <span class="cp-spotlight-entry__label"><?= cp_h($lang === 'en' ? 'Start here' : 'Punto di partenza') ?></span>
                            <span class="cp-spotlight-entry__media">
                                <img src="<?= cp_h($hero['image']) ?>" alt="<?= cp_h($hero['title']) ?>" loading="lazy" onerror="this.parentElement.classList.add('is-broken'); this.remove();">
                                <i class="fa-solid <?= cp_h(cp_type_icon($hero['type'])) ?>"></i>
                            </span>
                            <span class="cp-spotlight-entry__body">
                                <small><?= cp_h($hero['type_label']) ?> · <?= (int)$hero['views'] ?> <?= cp_h(cp_t('views', $lang)) ?></small>
                                <strong><?= cp_h($hero['title']) ?></strong>
                                <em><?= cp_h(cp_excerpt($hero['description'], 118)) ?></em>
                            </span>
                        </a>
                    <?php endif; ?>
                    <div class="cp-hero-stat-grid">
                        <div class="cp-signal-card cp-signal-card--main">
                            <strong><?= (int)array_sum([$stats['person'], $stats['event'], $stats['meme']]) ?></strong>
                            <span><?= cp_h($lang === 'en' ? 'pages online' : 'pagine online') ?></span>
                        </div>
                        <div class="cp-signal-card">
                            <i class="fa-solid fa-link"></i>
                            <span><?= (int)$stats['relations'] ?> <?= cp_h($lang === 'en' ? 'connections' : 'collegamenti') ?></span>
                        </div>
                    </div>
                </aside>
            </section>

            <!-- Categories Grid -->
            <section class="cp-section cp-reveal">
                <div class="cp-section-head">
                    <div>
                        <span class="cp-kicker">Archivio</span>
                        <h2><?= cp_h($lang === 'en' ? 'Lore Categories' : 'Categorie Lore') ?></h2>
                    </div>
                </div>
                <div class="cp-category-grid">
                    <?php foreach ($categoryMeta as [$type, $label, $count, $copy, $accent]): ?>
                        <a class="cp-category-card" href="<?= cp_h(cp_url('category', ['type' => $type], $lang)) ?>" style="--entry-accent: <?= cp_h($accent) ?>">
                            <div class="cp-category-card__top">
                                <i class="fa-solid <?= cp_h(cp_type_icon($type)) ?>"></i>
                                <span class="cp-category-card__label"><?= cp_h($label) ?></span>
                                <strong><?= (int)$count ?></strong>
                            </div>
                            <p><?= cp_h($copy) ?></p>
                            <span class="cp-category-card__preview">
                                <?php foreach (($categorySamples[$type] ?? []) as $sample): ?>
                                    <?php $samplePublic = cp_entry_public($sample, $lang, $mysqli, false); ?>
                                    <img src="<?= cp_h($samplePublic['image']) ?>" alt="<?= cp_h($samplePublic['title']) ?>" loading="lazy" onerror="this.remove();">
                                <?php endforeach; ?>
                            </span>
                            <span class="cp-category-card__cta">
                                <?= cp_h($lang === 'en' ? 'Open archive' : 'Apri archivio') ?>
                                <i class="fa-solid fa-arrow-right"></i>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Dashboard / Spotlight Section -->
            <section class="cp-dashboard-grid cp-reveal">
                <?php if ($dailyEvent): ?>
                    <?php $daily = cp_entry_public($dailyEvent, $lang, $mysqli); ?>
                    <article class="cp-feature-card" style="--entry-accent: <?= cp_h($daily['accent']) ?>">
                        <span class="cp-kicker"><i class="fa-solid fa-calendar-day"></i> <?= cp_h(cp_t('event_day', $lang)) ?></span>
                        <h2><?= cp_h($daily['title']) ?></h2>
                        <p><?= cp_h(cp_excerpt($daily['description'], 170)) ?></p>
                        <a class="cp-btn cp-btn--primary" href="<?= cp_h($daily['url']) ?>">
                            <span><?= cp_h(cp_t('read', $lang)) ?></span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </article>
                <?php endif; ?>

                <?php if ($quote): ?>
                    <?php
                    $quoteText = cp_i18n($quote, 'quote_text', $lang);
                    $quoteEntryUrl = cp_entry_url($quote, $lang);
                    ?>
                    <article class="cp-feature-card cp-feature-card--quote">
                        <span class="cp-kicker"><i class="fa-solid fa-quote-left"></i> <?= cp_h(cp_t('random_quote', $lang)) ?></span>
                        <blockquote><?= cp_h($quoteText) ?></blockquote>
                        <a class="cp-text-link" href="<?= cp_h($quoteEntryUrl) ?>"><?= cp_h(cp_i18n($quote, 'title', $lang)) ?></a>
                    </article>
                <?php endif; ?>
            </section>

            <!-- Core Content Grid -->
            <div class="cp-home-layout cp-reveal">
                <!-- Left Column: Added & Updated -->
                <div class="cp-home-main">
                    <!-- Latest Added -->
                    <section class="cp-section cp-section--noborder">
                        <div class="cp-section-head">
                            <div>
                                <span class="cp-kicker"><?= cp_h(cp_t('latest', $lang)) ?></span>
                                <h2><?= cp_h($lang === 'en' ? 'New entries' : 'Ultime pagine aggiunte') ?></h2>
                            </div>
                        </div>
                        <div class="cp-card-grid">
                            <?php foreach ($latest as $entry): ?>
                                <?php cp_render_entry_card($mysqli, $entry, $lang); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <!-- Recently Updated -->
                    <section class="cp-section">
                        <div class="cp-section-head">
                            <div>
                                <span class="cp-kicker"><?= cp_h($lang === 'en' ? 'Updated' : 'Aggiornate') ?></span>
                                <h2><?= cp_h($lang === 'en' ? 'Recently updated' : 'Ultime pagine aggiornate') ?></h2>
                            </div>
                        </div>
                        <div class="cp-card-grid cp-card-grid--compact">
                            <?php foreach ($updated as $entry): ?>
                                <?php cp_render_entry_card($mysqli, $entry, $lang, 'cp-entry-card--compact'); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>

                <!-- Right Column: Popular & Random -->
                <aside class="cp-home-side">
                    <!-- Popular stack -->
                    <div class="cp-side-stack">
                        <h3><?= cp_h($lang === 'en' ? 'Popular pages' : 'Pagine popolari') ?></h3>
                        <div class="cp-side-list">
                            <?php foreach ($popular as $entry): ?>
                                <?php $item = cp_entry_public($entry, $lang, $mysqli, false); ?>
                                <a class="cp-mini-row" href="<?= cp_h($item['url']) ?>" style="--entry-accent: <?= cp_h($item['accent']) ?>">
                                    <span class="cp-mini-row__media">
                                        <img src="<?= cp_h($item['image']) ?>" alt="<?= cp_h($item['title']) ?>" loading="lazy" onerror="this.parentElement.classList.add('is-broken'); this.remove();">
                                        <i class="fa-solid <?= cp_h(cp_type_icon($item['type'])) ?>"></i>
                                    </span>
                                    <span class="cp-mini-row__content">
                                        <strong><?= cp_h($item['title']) ?></strong>
                                        <small><?= cp_h($item['type_label']) ?></small>
                                    </span>
                                    <em><?= (int)$item['views'] ?></em>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Random explorer -->
                    <div class="cp-side-stack">
                        <h3><?= cp_h($lang === 'en' ? 'Random explorer' : 'Elementi casuali') ?></h3>
                        <div class="cp-side-list">
                            <?php foreach ($random as $entry): ?>
                                <?php $item = cp_entry_public($entry, $lang, $mysqli, false); ?>
                                <a class="cp-mini-row" href="<?= cp_h($item['url']) ?>" style="--entry-accent: <?= cp_h($item['accent']) ?>">
                                    <span class="cp-mini-row__media">
                                        <img src="<?= cp_h($item['image']) ?>" alt="<?= cp_h($item['title']) ?>" loading="lazy" onerror="this.parentElement.classList.add('is-broken'); this.remove();">
                                        <i class="fa-solid <?= cp_h(cp_type_icon($item['type'])) ?>"></i>
                                    </span>
                                    <span class="cp-mini-row__content">
                                        <strong><?= cp_h($item['title']) ?></strong>
                                        <small><?= cp_h($item['type_label']) ?></small>
                                    </span>
                                    <em><i class="fa-solid fa-shuffle"></i></em>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </main>

    <?php cp_render_footer($lang); ?>
</body>

</html>
