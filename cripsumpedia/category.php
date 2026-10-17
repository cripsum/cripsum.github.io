<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$lang = cp_detect_lang();
$type = cp_normalize_type($_GET['type'] ?? $_GET['category'] ?? 'person') ?? 'person';
$tag = trim((string)($_GET['tag'] ?? ''));
$canWrite = cp_can_write();

// Ogni categoria ha due ordini, scelti per quello che contiene. I valori
// vecchi (?order=timeline, popular…) portano a quello più vicino.
$sorts = match ($type) {
    'person' => ['az' => [cp_ui('sort_az', $lang), 'alphabetical'], 'new' => [cp_ui('sort_newest_people', $lang), 'latest']],
    'event' => ['new' => [cp_ui('sort_recent', $lang), 'date'], 'old' => [cp_ui('sort_oldest', $lang), 'timeline']],
    default => ['new' => [cp_ui('sort_new', $lang), 'latest'], 'az' => [cp_ui('sort_az', $lang), 'alphabetical']],
};
$sort = (string)($_GET['order'] ?? '');
$sort = match ($sort) {
    'timeline' => $type === 'event' ? 'old' : array_key_first($sorts),
    'alphabetical' => isset($sorts['az']) ? 'az' : array_key_first($sorts),
    'latest', 'date' => 'new',
    default => isset($sorts[$sort]) ? $sort : array_key_first($sorts),
};

$stats = cp_fetch_stats($mysqli);
$entries = cp_fetch_entries($mysqli, ['type' => $type, 'tag' => $tag, 'order' => $sorts[$sort][1], 'limit' => 80]);
$tags = cp_category_tags($mysqli, $type);
$count = (int)($stats[$type] ?? 0);

$sortUrl = static fn(string $key): string => cp_url('category', ['type' => $type, 'tag' => $tag, 'order' => $key === array_key_first($sorts) ? null : $key], $lang);
$tagUrl = static fn(string $slug): string => cp_url('category', ['type' => $type, 'tag' => $slug, 'order' => $sort === array_key_first($sorts) ? null : $sort], $lang);

// Gli eventi si raggruppano per anno; quelli senza data vanno in fondo.
$years = [];
$peopleByEvent = [];
if ($type === 'event') {
    foreach ($entries as $entry) {
        $year = !empty($entry['real_date']) ? substr((string)$entry['real_date'], 0, 4) : '';
        $years[$year][] = $entry;
    }
    if (isset($years[''])) {
        $undated = $years[''];
        unset($years['']);
        $years[''] = $undated;
    }
    $peopleByEvent = cp_people_for_events($mysqli, array_map(static fn(array $e): int => (int)$e['id'], $entries));
}

$plural = cp_type_plural($type, $lang);
$pageTitle = $plural . ' - Cripsumpedia';
$description = cp_ui('lead_' . $type, $lang);
?>
<!DOCTYPE html>
<html lang="<?= cp_h($lang) ?>"<?= cripsum_theme_html_attr() ?>>

<head>
    <?php cp_render_head($pageTitle, $description, $lang); ?>
</head>

<body class="cp-body cp-page-category cp-page-<?= cp_h($type) ?>">
    <?php cp_render_topbar($lang, $type); ?>

    <main class="cp-main">
        <?php if (!cp_schema_ready($mysqli)): ?>
            <?php cp_render_install_notice($lang); ?>
        <?php else: ?>
            <?php cp_render_bar($lang, $type); ?>

            <header class="cp-head">
                <h1><?= cp_h($plural) ?> <small><?= $count ?></small></h1>
                <p><?= cp_h($description) ?></p>
            </header>

            <div class="cp-filters">
                <?php if ($tags): ?>
                    <nav class="cp-chips" aria-label="<?= cp_h(cp_ui('filter_label', $lang)) ?>">
                        <a class="cp-chip<?= $tag === '' ? ' is-on' : '' ?>" href="<?= cp_h($tagUrl('')) ?>"<?= $tag === '' ? ' aria-current="true"' : '' ?>><?= cp_h(cp_t('all', $lang)) ?></a>
                        <?php foreach ($tags as $row): ?>
                            <?php $on = $tag === (string)$row['slug']; ?>
                            <a class="cp-chip<?= $on ? ' is-on' : '' ?>" href="<?= cp_h($tagUrl((string)$row['slug'])) ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= cp_h(cp_i18n($row, 'name', $lang)) ?> <small><?= (int)$row['total'] ?></small></a>
                        <?php endforeach; ?>
                    </nav>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <nav class="cp-seg" aria-label="<?= cp_h(cp_ui('sort_label', $lang)) ?>">
                    <?php foreach ($sorts as $key => [$label]): ?>
                        <a href="<?= cp_h($sortUrl($key)) ?>"<?= $sort === $key ? ' class="is-on" aria-current="true"' : '' ?>><?= cp_h($label) ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <?php if (!$entries && !$canWrite): ?>
                <p class="cp-empty"><?= cp_h(cp_ui('nobody_here', $lang)) ?></p>

            <?php elseif ($type === 'person'): ?>
                <div class="cp-people cp-people--5">
                    <?php foreach ($entries as $entry): ?>
                        <?php cp_render_person_tile($entry, $lang); ?>
                    <?php endforeach; ?>
                    <?php if ($canWrite): ?>
                        <?php cp_render_ghost_tile(cp_ui('add_person', $lang), cp_write_url($lang, 'person')); ?>
                    <?php endif; ?>
                </div>

            <?php elseif ($type === 'event'): ?>
                <?php foreach ($years as $year => $yearEntries): ?>
                    <section class="cp-year">
                        <h2><?= cp_h($year !== '' ? (string)$year : cp_ui('no_date', $lang)) ?></h2>
                        <ol>
                            <?php foreach ($yearEntries as $entry): ?>
                                <?php cp_render_timeline_item($entry, $peopleByEvent[(int)$entry['id']] ?? [], $lang); ?>
                            <?php endforeach; ?>
                        </ol>
                    </section>
                <?php endforeach; ?>
                <?php if ($canWrite): ?>
                    <section class="cp-year cp-year--ghost">
                        <h2 aria-hidden="true"></h2>
                        <ol>
                            <li>
                                <a class="cp-tl cp-tl--ghost" href="<?= cp_h(cp_write_url($lang, 'event')) ?>">
                                    <time><i class="fa-solid fa-plus" aria-hidden="true"></i></time>
                                    <span class="cp-tl__text"><strong><?= cp_h(cp_ui('add_event', $lang)) ?></strong><span class="cp-tl__line"><?= cp_h(cp_ui('add_event_text', $lang)) ?></span></span>
                                </a>
                            </li>
                        </ol>
                    </section>
                <?php endif; ?>

            <?php else: ?>
                <div class="cp-memes cp-memes--4">
                    <?php foreach ($entries as $entry): ?>
                        <?php cp_render_meme_tile($entry, $lang); ?>
                    <?php endforeach; ?>
                    <?php if ($canWrite): ?>
                        <?php cp_render_ghost_tile(cp_ui('add_meme', $lang), cp_write_url($lang, 'meme'), 'cp-meme'); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php cp_render_footer($lang); ?>
</body>

</html>
