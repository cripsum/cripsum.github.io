<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$lang = cp_detect_lang();
$type = cp_normalize_type($_GET['type'] ?? $_GET['category'] ?? 'person') ?? 'person';
$query = trim((string)($_GET['q'] ?? ''));
$tag = trim((string)($_GET['tag'] ?? ''));
$order = trim((string)($_GET['order'] ?? ($type === 'event' ? 'timeline' : 'latest')));
$validOrders = ['latest', 'popular', 'trending', 'importance', 'timeline', 'alphabetical', 'date'];
if (!in_array($order, $validOrders, true)) $order = 'latest';

$entries = cp_fetch_entries($mysqli, [
    'type' => $type,
    'q' => $query,
    'tag' => $tag,
    'order' => $order,
    'limit' => 60,
]);
$allTags = cp_fetch_tags($mysqli);
$count = cp_count_entries($mysqli, ['type' => $type]);
$pageTitle = cp_type_plural($type, $lang) . ' - Cripsumpedia';
$description = cp_t('subtitle', $lang);
?>
<!DOCTYPE html>
<html lang="<?= cp_h($lang) ?>">

<head>
    <?php cp_render_head($pageTitle, $description, $lang); ?>
</head>

<body class="cp-body cp-page-category">
    <?php cp_render_background(); ?>
    <?php cp_render_topbar($lang, $type); ?>

    <main class="cp-shell">
        <?php if (!cp_schema_ready($mysqli)): ?>
            <?php cp_render_install_notice($lang); ?>
        <?php else: ?>
            <?php cp_render_breadcrumbs($lang, [
                ['label' => cp_type_plural($type, $lang), 'url' => null],
            ]); ?>

            <section class="cp-category-hero cp-reveal" style="--entry-accent: <?= cp_h($type === 'person' ? '#2f6bff' : ($type === 'event' ? '#60a5fa' : '#f97316')) ?>">
                <div>
                    <span class="cp-kicker"><i class="fa-solid <?= cp_h(cp_type_icon($type)) ?>"></i> <?= cp_h(cp_type_plural($type, $lang)) ?></span>
                    <h1><?= cp_h(cp_type_plural($type, $lang)) ?></h1>
                    <p><?= cp_h($type === 'event'
                            ? ($lang === 'en' ? 'Browse events in chronological order, filter by date and importance.' : 'Sfoglia gli eventi in ordine cronologico, filtra per data e importanza.')
                            : ($lang === 'en' ? 'Browse all entries with live search, tag and order filters.' : 'Sfoglia tutte le voci con ricerca live, filtri per tag e ordinamento.')) ?></p>
                </div>
                <aside>
                    <strong><?= (int)$count ?></strong>
                    <span><?= cp_h(cp_type_plural($type, $lang)) ?></span>
                </aside>
            </section>

            <section class="cp-toolbar cp-reveal" aria-label="<?= cp_h(cp_t('filters', $lang)) ?>">
                <?php cp_render_search_box($lang, $query, 'toolbar', cp_url('category', ['type' => $type], $lang), [
                    'tag' => $tag,
                    'order' => $order,
                ]); ?>

                <div class="cp-toolbar-actions">
                    <form class="cp-filter-bar" method="get" action="<?= cp_h(cp_url('category', ['type' => $type], $lang)) ?>">
                        <input type="hidden" name="type" value="<?= cp_h($type) ?>">
                        <input type="hidden" name="q" value="<?= cp_h($query) ?>">
                        <label>
                            <span><?= cp_h(cp_t('tag', $lang)) ?></span>
                            <select name="tag" onchange="this.form.submit()">
                                <option value=""><?= cp_h(cp_t('all', $lang)) ?></option>
                                <?php foreach ($allTags as $row): ?>
                                    <?php $slug = (string)$row['slug']; ?>
                                    <option value="<?= cp_h($slug) ?>" <?= $tag === $slug ? 'selected' : '' ?>>
                                        <?= cp_h(cp_i18n($row, 'name', $lang)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span><?= cp_h($lang === 'en' ? 'Sort by' : 'Ordina per') ?></span>
                            <select name="order" onchange="this.form.submit()">
                                <option value="latest" <?= $order === 'latest' ? 'selected' : '' ?>><?= cp_h($lang === 'en' ? 'Latest' : 'Ultimi inseriti') ?></option>
                                <option value="popular" <?= $order === 'popular' ? 'selected' : '' ?>><?= cp_h($lang === 'en' ? 'Popular' : 'Più visti') ?></option>
                                <option value="trending" <?= $order === 'trending' ? 'selected' : '' ?>><?= cp_h($lang === 'en' ? 'Trending' : 'In tendenza') ?></option>
                                <option value="importance" <?= $order === 'importance' ? 'selected' : '' ?>><?= cp_h($lang === 'en' ? 'Importance' : 'Importanza') ?></option>
                                <option value="alphabetical" <?= $order === 'alphabetical' ? 'selected' : '' ?>><?= cp_h($lang === 'en' ? 'Alphabetical' : 'Alfabetico') ?></option>
                                <option value="date" <?= $order === 'date' ? 'selected' : '' ?>><?= cp_h($lang === 'en' ? 'Lore Date' : 'Data cronologica') ?></option>
                                <?php if ($type === 'event'): ?>
                                    <option value="timeline" <?= $order === 'timeline' ? 'selected' : '' ?>><?= cp_h($lang === 'en' ? 'Timeline' : 'Cronologia') ?></option>
                                <?php endif; ?>
                            </select>
                        </label>
                        <button class="cp-btn cp-btn--small" type="submit">
                            <i class="fa-solid fa-filter"></i>
                            <span><?= cp_h(cp_t('filters', $lang)) ?></span>
                        </button>
                    </form>

                    <?php if ($order !== 'timeline' || $type !== 'event'): ?>
                        <div class="cp-view-toggle">
                            <button type="button" class="cp-icon-btn is-active" data-cp-view-toggle="grid" title="<?= cp_h($lang === 'en' ? 'Grid view' : 'Visualizzazione griglia') ?>"><i class="fa-solid fa-table-cells"></i></button>
                            <button type="button" class="cp-icon-btn" data-cp-view-toggle="list" title="<?= cp_h($lang === 'en' ? 'List view' : 'Visualizzazione lista') ?>"><i class="fa-solid fa-list"></i></button>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($type === 'event' && $order === 'timeline'): ?>
                <section class="cp-section cp-reveal">
                    <div class="cp-section-head">
                        <div>
                            <span class="cp-kicker"><?= cp_h(cp_t('timeline', $lang)) ?></span>
                            <h2><?= cp_h(cp_t('timeline', $lang)) ?></h2>
                        </div>
                    </div>
                    <?php if (!$entries): ?>
                        <div class="cp-empty"><?= cp_h(cp_t('empty', $lang)) ?></div>
                    <?php else: ?>
                        <ol class="cp-timeline" data-cp-timeline>
                            <?php foreach ($entries as $entry): ?>
                                <?php $item = cp_entry_public($entry, $lang, $mysqli); ?>
                                <li style="--entry-accent: <?= cp_h($item['accent']) ?>">
                                    <time><?= cp_h($entry['real_date'] ? cp_safe_date($entry['real_date']) : ($entry['lore_date'] ?: cp_safe_date($entry['created_at']))) ?></time>
                                    <a href="<?= cp_h($item['url']) ?>">
                                        <span><?= cp_h($item['title']) ?></span>
                                        <small><?= cp_h(cp_excerpt($item['description'], 130)) ?></small>
                                    </a>
                                    <em><?= (int)$item['importance'] ?></em>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>
            <?php else: ?>
                <section class="cp-section cp-reveal">
                    <div class="cp-section-head">
                        <div>
                            <span class="cp-kicker"><?= count($entries) ?> <?= cp_h($lang === 'en' ? 'results' : 'risultati') ?></span>
                            <h2><?= cp_h(cp_type_plural($type, $lang)) ?></h2>
                        </div>
                    </div>
                    <?php if (!$entries): ?>
                        <div class="cp-empty"><?= cp_h(cp_t('no_results', $lang)) ?></div>
                    <?php else: ?>
                        <div class="cp-card-grid" data-cp-card-grid>
                            <?php foreach ($entries as $entry): ?>
                                <?php cp_render_entry_card($mysqli, $entry, $lang); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php cp_render_footer($lang); ?>
</body>

</html>