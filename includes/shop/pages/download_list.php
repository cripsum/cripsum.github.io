<?php
/**
 * Lista dei download. Variabili: $page, $items, $faq, $missing.
 */

$soonCount = count(array_filter($items, static fn(array $d): bool => $d['state'] === 'presto'));

$jsonItems = [];
foreach ($items as $item) {
    $jsonItems[] = [
        'slug' => $item['slug'],
        'name' => $item['name'],
        'description' => $item['short'],
        'category' => $item['state'] === 'presto' ? 'presto' : 'disponibile',
        'featured' => $item['featured'],
        'orders' => $item['count'],
        'position' => $item['position'],
        'created' => $item['created'],
    ];
}

$shopData = [
    'kind' => 'downloads',
    'products' => $jsonItems,
    'strings' => [
        'resultsOne' => $S['results_one'],
        'resultsMany' => $S['results_many'],
        'linkCopied' => $S['link_copied'],
        'copyFailed' => $S['copy_failed'],
        'downloadStarted' => $S['download_started'],
    ],
];
?>
<main class="shop-shell">
    <?php if ($missing): ?>
        <div class="shop-preview-banner shop-preview-banner--warn" role="alert">
            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
            <span><?php echo shop_h($S['download_not_found']); ?></span>
        </div>
    <?php endif; ?>

    <section class="shop-hero shop-hero--compact">
        <div class="shop-hero__content">
            <span class="shop-kicker"><?php echo shop_h($page['kicker'] !== '' ? $page['kicker'] : 'Download'); ?></span>
            <h1><?php echo shop_h($page['title'] !== '' ? $page['title'] : 'Download Center'); ?></h1>
            <?php if ($page['subtitle'] !== ''): ?>
                <p><?php echo shop_h($page['subtitle']); ?></p>
            <?php endif; ?>
            <?php if ($page['link_url'] !== '' && $page['link_text'] !== ''): ?>
                <?php $external = preg_match('~^https?://~i', $page['link_url']); ?>
                <div class="shop-hero__actions">
                    <a class="shop-btn shop-btn--ghost" href="<?php echo shop_h($page['link_url']); ?>" <?php echo $external ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <?php if (stripos($page['link_url'], 'discord') !== false): ?><i class="fa-brands fa-discord" aria-hidden="true"></i><?php endif; ?>
                        <?php echo shop_h($page['link_text']); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <div class="shop-hero__art" aria-hidden="true"><i class="fa-solid fa-cloud-arrow-down"></i></div>
    </section>

    <?php if ($page['note'] !== ''): ?>
        <section class="shop-note">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <div>
                <?php if ($page['note_title'] !== ''): ?><strong><?php echo shop_h($page['note_title']); ?></strong><?php endif; ?>
                <span><?php echo shop_linkify($page['note']); ?></span>
            </div>
        </section>
    <?php endif; ?>

    <section class="shop-panel" id="download-list" aria-label="Download">
        <?php if ($items): ?>
            <div class="shop-toolbar" data-shop-toolbar>
                <label class="shop-search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" data-shop-search placeholder="<?php echo shop_h($S['search_downloads']); ?>" aria-label="<?php echo shop_h($S['search_downloads']); ?>" autocomplete="off">
                    <button type="button" class="shop-search__clear" data-shop-search-clear aria-label="<?php echo shop_h($S['clear_search']); ?>" hidden><i class="fa-solid fa-xmark"></i></button>
                </label>

                <label class="shop-sort">
                    <span class="visually-hidden"><?php echo shop_h($S['sort']); ?></span>
                    <i class="fa-solid fa-arrow-down-wide-short" aria-hidden="true"></i>
                    <select data-shop-sort aria-label="<?php echo shop_h($S['sort']); ?>">
                        <option value="featured"><?php echo shop_h($S['sort_featured']); ?></option>
                        <option value="popular"><?php echo shop_h($S['sort_downloads']); ?></option>
                        <option value="recent"><?php echo shop_h($S['sort_recent']); ?></option>
                        <option value="name"><?php echo shop_h($S['sort_name']); ?></option>
                    </select>
                </label>

                <?php if ($soonCount > 0): ?>
                    <div class="shop-filters" role="group">
                        <button type="button" class="shop-filter is-active" data-category="" aria-pressed="true"><?php echo shop_h($S['all']); ?> <span><?php echo count($items); ?></span></button>
                        <button type="button" class="shop-filter" data-category="disponibile" aria-pressed="false"><?php echo shop_h($S['available']); ?> <span><?php echo count($items) - $soonCount; ?></span></button>
                        <button type="button" class="shop-filter" data-category="presto" aria-pressed="false"><?php echo shop_h($S['soon']); ?> <span><?php echo $soonCount; ?></span></button>
                    </div>
                <?php endif; ?>

                <p class="shop-results" data-shop-results aria-live="polite"></p>
            </div>

            <div class="shop-grid" data-shop-grid>
                <?php foreach ($items as $index => $item): ?>
                    <?php
                    $isSoon = $item['state'] === 'presto';
                    $metaBits = [];
                    if ($item['type'] === 'link') {
                        $metaBits[] = $item['host'] !== '' ? $item['host'] : $S['external_link'];
                    } else {
                        if ($item['ext'] !== '') $metaBits[] = strtoupper($item['ext']);
                        if ($item['size_label'] !== '') $metaBits[] = $item['size_label'];
                    }
                    ?>
                    <article class="shop-card shop-card--download <?php echo $isSoon ? 'is-soon' : ''; ?>" data-shop-item="<?php echo shop_h($item['slug']); ?>">
                        <a class="shop-card__hit" href="<?php echo shop_h($item['url']); ?>">
                            <span class="visually-hidden"><?php echo shop_h($S['download_details'] . ': ' . $item['name']); ?></span>
                        </a>
                        <div class="shop-card__media">
                            <?php if ($item['image'] !== ''): ?>
                                <img src="<?php echo shop_h($item['image']); ?>" alt="" width="480" height="300" <?php echo $index > 5 ? 'loading="lazy"' : ''; ?> decoding="async">
                            <?php else: ?>
                                <i class="<?php echo shop_h(shop_download_icon($item)); ?> shop-card__placeholder" aria-hidden="true"></i>
                            <?php endif; ?>
                            <?php if ($isSoon): ?>
                                <span class="shop-badge shop-badge--soon"><?php echo shop_h($S['soon']); ?></span>
                            <?php elseif ($item['is_new']): ?>
                                <span class="shop-badge shop-badge--new"><?php echo shop_h($S['new']); ?></span>
                            <?php elseif ($item['badge'] !== ''): ?>
                                <span class="shop-badge"><?php echo shop_h($item['badge']); ?></span>
                            <?php endif; ?>
                            <?php if ($item['featured']): ?>
                                <span class="shop-flag"><i class="fa-solid fa-star" aria-hidden="true"></i><span class="visually-hidden"><?php echo shop_h($S['featured']); ?></span></span>
                            <?php endif; ?>
                        </div>
                        <div class="shop-card__body">
                            <h2><?php echo shop_h($item['name']); ?></h2>
                            <?php if ($item['short'] !== ''): ?>
                                <p><?php echo shop_h($item['short']); ?></p>
                            <?php endif; ?>
                            <ul class="shop-card__meta">
                                <li><i class="<?php echo shop_h(shop_download_icon($item)); ?>" aria-hidden="true"></i> <?php echo shop_h($metaBits ? implode(' · ', $metaBits) : $S['file']); ?></li>
                                <?php if ($item['count'] > 0): ?>
                                    <li><i class="fa-solid fa-download" aria-hidden="true"></i> <?php echo shop_h($item['count'] === 1 ? $S['downloads_count_one'] : sprintf($S['downloads_count_many'], $item['count_label'])); ?></li>
                                <?php endif; ?>
                            </ul>
                            <div class="shop-card__footer">
                                <?php if ($item['available']): ?>
                                    <a class="shop-btn shop-btn--primary shop-btn--small" href="<?php echo shop_h($item['go_url']); ?>" data-download-go <?php echo $item['type'] === 'link' ? 'target="_blank" rel="noopener"' : ''; ?>>
                                        <?php if ($item['type'] === 'link'): ?>
                                            <?php echo shop_h($S['open_link']); ?> <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                        <?php else: ?>
                                            <i class="fa-solid fa-download" aria-hidden="true"></i> <?php echo shop_h($S['download']); ?>
                                        <?php endif; ?>
                                    </a>
                                <?php else: ?>
                                    <span class="shop-btn shop-btn--small is-disabled"><?php echo shop_h($isSoon ? $S['soon'] : $S['not_available']); ?></span>
                                <?php endif; ?>
                                <a class="shop-link" href="<?php echo shop_h($item['url']); ?>"><?php echo shop_h($S['download_details']); ?> <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="shop-empty" data-shop-empty hidden>
                <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                <strong><?php echo shop_h($S['empty_downloads']); ?></strong>
                <span><?php echo shop_h($S['empty_text']); ?></span>
                <button type="button" class="shop-btn shop-btn--ghost" data-shop-reset><?php echo shop_h($S['reset_filters']); ?></button>
            </div>
        <?php else: ?>
            <div class="shop-empty">
                <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                <strong><?php echo shop_h($S['empty_downloads']); ?></strong>
            </div>
        <?php endif; ?>
    </section>

    <?php include __DIR__ . '/../partials/faq.php'; ?>
</main>

<script type="application/json" id="shop-data"><?php echo json_encode($shopData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
