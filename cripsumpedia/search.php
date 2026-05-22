<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$lang = cp_detect_lang();
$query = trim((string)($_GET['q'] ?? ''));
$type = cp_normalize_type($_GET['type'] ?? null);
$results = $query !== ''
    ? cp_search_entries($mysqli, $query, $lang, ['type' => $type, 'limit' => 40])
    : cp_fetch_entries($mysqli, ['type' => $type, 'limit' => 24, 'order' => 'trending']);

$title = cp_t('search', $lang) . ' - Cripsumpedia';
$description = cp_t('subtitle', $lang);
?>
<!DOCTYPE html>
<html lang="<?= cp_h($lang) ?>">
<head>
    <?php cp_render_head($title, $description, $lang); ?>
</head>
<body class="cp-body cp-page-search">
<?php cp_render_background(); ?>
<?php cp_render_topbar($lang, 'search'); ?>

<main class="cp-shell">
    <?php if (!cp_schema_ready($mysqli)): ?>
        <?php cp_render_install_notice($lang); ?>
    <?php else: ?>
        <?php cp_render_breadcrumbs($lang, [
            ['label' => cp_t('search', $lang), 'url' => null],
        ]); ?>

        <section class="cp-search-hero cp-reveal">
            <span class="cp-kicker"><i class="fa-solid fa-magnifying-glass-chart"></i> Search engine</span>
            <h1><?= cp_h(cp_t('search', $lang)) ?></h1>
            <p><?= cp_h($lang === 'en'
                ? 'Live suggestions across titles, descriptions, tags, aliases and quotes.'
                : 'Suggerimenti live su titoli, descrizioni, tag, alias e citazioni.') ?></p>
            <?php cp_render_search_box($lang, $query); ?>
        </section>

        <section class="cp-search-tabs cp-reveal" role="tablist">
            <?php
            $tabs = [
                [null, cp_t('all', $lang), 'fa-layer-group'],
                ['person', cp_t('people', $lang), 'fa-user-astronaut'],
                ['event', cp_t('events', $lang), 'fa-timeline'],
                ['meme', cp_t('memes', $lang), 'fa-face-grin-squint-tears'],
            ];
            foreach ($tabs as [$tabType, $label, $icon]):
                $url = cp_url('search', array_filter(['q' => $query, 'type' => $tabType]), $lang);
            ?>
                <a href="<?= cp_h($url) ?>" class="<?= $type === $tabType ? 'is-active' : '' ?>">
                    <i class="fa-solid <?= cp_h($icon) ?>"></i>
                    <span><?= cp_h($label) ?></span>
                </a>
            <?php endforeach; ?>
        </section>

        <section class="cp-section cp-reveal">
            <div class="cp-section-head">
                <div>
                    <span class="cp-kicker"><?= count($results) ?> results</span>
                    <h2><?= $query !== '' ? cp_h($query) : cp_h(cp_t('trending', $lang)) ?></h2>
                </div>
            </div>

            <?php if (!$results): ?>
                <div class="cp-empty"><?= cp_h(cp_t('no_results', $lang)) ?></div>
            <?php else: ?>
                <div class="cp-search-list" data-cp-search-list>
                    <?php foreach ($results as $entry): ?>
                        <?php $item = cp_entry_public($entry, $lang, $mysqli); ?>
                        <article class="cp-search-result" style="--entry-accent: <?= cp_h($item['accent']) ?>">
                            <a class="cp-search-result__image" href="<?= cp_h($item['url']) ?>">
                                <img src="<?= cp_h($item['image']) ?>" alt="<?= cp_h($item['title']) ?>" loading="lazy" onerror="this.parentElement.classList.add('is-broken'); this.remove();">
                                <i class="fa-solid <?= cp_h(cp_type_icon($item['type'])) ?>"></i>
                            </a>
                            <div>
                                <div class="cp-entry-card__meta">
                                    <span><?= cp_h($item['type_label']) ?></span>
                                    <span><?= (int)$item['views'] ?> <?= cp_h(cp_t('views', $lang)) ?></span>
                                </div>
                                <h2><a href="<?= cp_h($item['url']) ?>"><?= cp_h($item['title']) ?></a></h2>
                                <p><?= cp_h(cp_excerpt($item['description'], 230)) ?></p>
                                <div class="cp-tag-row">
                                    <?php foreach (array_slice($item['tags'], 0, 5) as $tagRow): ?>
                                        <a href="<?= cp_h(cp_url('category', ['type' => $item['type'], 'tag' => $tagRow['slug']], $lang)) ?>" style="--tag-color: <?= cp_h($tagRow['color']) ?>">
                                            <?= cp_h($tagRow['name']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>

<?php cp_render_footer($lang); ?>
</body>
</html>
