<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$lang = cp_detect_lang();
$type = cp_normalize_type($_GET['type'] ?? $_GET['category'] ?? 'person') ?? 'person';
$query = trim((string)($_GET['q'] ?? ''));
$tag = trim((string)($_GET['tag'] ?? ''));
$order = trim((string)($_GET['order'] ?? ($type === 'event' ? 'timeline' : 'latest')));
$validOrders = ['latest', 'popular', 'trending', 'importance', 'timeline'];
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

        <section class="cp-category-hero cp-reveal" style="--entry-accent: <?= cp_h($type === 'person' ? '#42f5b0' : ($type === 'event' ? '#60a5fa' : '#f97316')) ?>">
            <div>
                <span class="cp-kicker"><i class="fa-solid <?= cp_h(cp_type_icon($type)) ?>"></i> <?= cp_h(cp_type_plural($type, $lang)) ?></span>
                <h1><?= cp_h(cp_type_plural($type, $lang)) ?></h1>
                <p><?= cp_h($type === 'event'
                    ? ($lang === 'en' ? 'Chronological lore with real dates, lore dates and importance filters.' : 'Cronologia lore con date reali, date narrative e filtri per importanza.')
                    : ($lang === 'en' ? 'Browse entries, relations, tags and aliases with live filtering.' : 'Sfoglia voci, relazioni, tag e alias con filtri live.')) ?></p>
            </div>
            <aside>
                <strong><?= (int)$count ?></strong>
                <span><?= cp_h(cp_type_plural($type, $lang)) ?></span>
            </aside>
        </section>

        <section class="cp-toolbar cp-reveal" aria-label="<?= cp_h(cp_t('filters', $lang)) ?>">
            <?php cp_render_search_box($lang, $query, 'toolbar'); ?>

            <form class="cp-filter-bar" method="get" action="<?= cp_h(cp_url('category', ['type' => $type], $lang)) ?>">
                <input type="hidden" name="type" value="<?= cp_h($type) ?>">
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
                    <span>Order</span>
                    <select name="order" onchange="this.form.submit()">
                        <option value="latest" <?= $order === 'latest' ? 'selected' : '' ?>>Latest</option>
                        <option value="popular" <?= $order === 'popular' ? 'selected' : '' ?>>Popular</option>
                        <option value="trending" <?= $order === 'trending' ? 'selected' : '' ?>>Trending</option>
                        <option value="importance" <?= $order === 'importance' ? 'selected' : '' ?>>Importance</option>
                        <?php if ($type === 'event'): ?>
                            <option value="timeline" <?= $order === 'timeline' ? 'selected' : '' ?>>Timeline</option>
                        <?php endif; ?>
                    </select>
                </label>
                <button class="cp-btn cp-btn--small" type="submit">
                    <i class="fa-solid fa-filter"></i>
                    <span><?= cp_h(cp_t('filters', $lang)) ?></span>
                </button>
            </form>
        </section>

        <?php if ($type === 'event' && $order === 'timeline'): ?>
            <section class="cp-section cp-reveal">
                <div class="cp-section-head">
                    <div>
                        <span class="cp-kicker"><?= cp_h(cp_t('timeline', $lang)) ?></span>
                        <h2>Loreline</h2>
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
                        <span class="cp-kicker"><?= count($entries) ?> results</span>
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
