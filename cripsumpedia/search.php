<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$lang = cp_detect_lang();
$query = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100, 'UTF-8');
$type = cp_normalize_type($_GET['type'] ?? null);

// Senza parole cercate: le voci più lette adesso.
$all = $query !== ''
    ? cp_search_entries($mysqli, $query, $lang, ['limit' => 30])
    : cp_fetch_entries($mysqli, ['order' => 'trending', 'limit' => 24]);

$counts = ['person' => 0, 'event' => 0, 'meme' => 0];
foreach ($all as $entry) {
    $counts[(string)$entry['entry_type']] = ($counts[(string)$entry['entry_type']] ?? 0) + 1;
}
$results = $type ? array_values(array_filter($all, static fn(array $e): bool => $e['entry_type'] === $type)) : $all;

$title = ($query !== '' ? $query . ' - ' : '') . cp_t('search', $lang) . ' - Cripsumpedia';
$description = cp_t('subtitle', $lang);
$tabUrl = static fn(?string $tab): string => cp_url('search', ['q' => $query, 'type' => $tab], $lang);
?>
<!DOCTYPE html>
<html lang="<?= cp_h($lang) ?>"<?= cripsum_theme_html_attr() ?>>
<head>
    <?php cp_render_head($title, $description, $lang); ?>
    <meta name="robots" content="noindex">
</head>
<body class="cp-body cp-page-search">
<?php cp_render_topbar($lang, 'search'); ?>

<main class="cp-main">
    <?php if (!cp_schema_ready($mysqli)): ?>
        <?php cp_render_install_notice($lang); ?>
    <?php else: ?>
        <?php cp_render_bar($lang, '', true, $query); ?>

        <header class="cp-head">
            <h1><?= cp_h($query !== '' ? cp_ui('results_for', $lang, $query) : cp_ui('popular_now', $lang)) ?></h1>
            <?php if ($query !== ''): ?><p><?= cp_h(cp_ui_n('results', count($all), $lang)) ?></p><?php endif; ?>
        </header>

        <?php if ($query !== '' && $all): ?>
            <nav class="cp-chips cp-chips--tabs" aria-label="<?= cp_h(cp_ui('filter_label', $lang)) ?>">
                <a class="cp-chip<?= $type === null ? ' is-on' : '' ?>" href="<?= cp_h($tabUrl(null)) ?>"><?= cp_h(cp_t('all', $lang)) ?> <small><?= count($all) ?></small></a>
                <?php foreach ($counts as $tabType => $n): ?>
                    <?php if ($n > 0): ?>
                        <a class="cp-chip<?= $type === $tabType ? ' is-on' : '' ?>" href="<?= cp_h($tabUrl($tabType)) ?>"><?= cp_h(cp_type_plural($tabType, $lang)) ?> <small><?= $n ?></small></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <?php if (!$results): ?>
            <section class="cp-nothing">
                <?php if ($query === ''): ?>
                    <p><?= cp_h(cp_ui('nobody_here', $lang)) ?></p>
                <?php else: ?>
                    <p><strong><?= cp_h(cp_ui('nothing_found', $lang, $query)) ?></strong> <?= cp_h(cp_ui('nothing_hint', $lang)) ?></p>
                <?php endif; ?>
                <?php if (cp_can_write() && $query !== ''): ?>
                    <a class="cp-btn" href="<?= cp_h(cp_write_url($lang, $type, $query)) ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i><?= cp_h(cp_ui('write_this', $lang, $query)) ?></a>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <ol class="cp-hits">
                <?php foreach ($results as $entry): ?>
                    <?php
                    $line = cp_entry_line($entry, $lang, 160);
                    $meta = cp_type_label((string)$entry['entry_type'], $lang);
                    $when = $entry['entry_type'] === 'event' ? cp_event_date($entry, $lang) : '';
                    ?>
                    <li>
                        <a class="cp-hit" href="<?= cp_h(cp_entry_url($entry, $lang)) ?>">
                            <?php cp_render_media($entry, 'cp-hit__img', $lang); ?>
                            <span>
                                <strong><?= cp_highlight(cp_i18n($entry, 'title', $lang), $query) ?></strong>
                                <span><?= cp_h($meta . ($when !== '' ? ' · ' . $when : '')) ?><?php if ($line !== ''): ?> · <?= cp_highlight($line, $query) ?><?php endif; ?></span>
                            </span>
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php if ($query !== '' && cp_can_write()): ?>
                <p class="cp-nothing cp-nothing--after">
                    <a class="cp-btn" href="<?= cp_h(cp_write_url($lang, $type, $query)) ?>"><i class="fa-solid fa-plus" aria-hidden="true"></i><?= cp_h(cp_ui('write_this', $lang, $query)) ?></a>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php cp_render_footer($lang); ?>
</body>
</html>
