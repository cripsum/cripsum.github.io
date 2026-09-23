<?php
/**
 * Dettaglio di un download. Variabili: $item (shop_download_view), $others,
 * $isPreview, $listUrl.
 */

$isLink = $item['type'] === 'link';
$buttonLabel = $item['button'] !== '' ? $item['button'] : ($isLink ? $S['open_link'] : $S['download']);
// Lo staff puo' provare anche un download nascosto, purche' abbia il file.
$canDownload = $item['available'] || ($isPreview && ($isLink || $item['size'] !== null));

// I dettagli automatici (tipo, dimensione, fonte, download) vengono prima di
// quelli scritti a mano nel pannello.
$facts = [];
if ($isLink) {
    $facts[] = ['label' => $S['type_label'], 'value' => $S['external_link']];
    if ($item['host'] !== '') {
        $facts[] = ['label' => $shopLang === 'en' ? 'Site' : 'Sito', 'value' => $item['host']];
    }
} else {
    if ($item['ext'] !== '') {
        $facts[] = ['label' => $S['type_label'], 'value' => strtoupper($item['ext'])];
    }
    if ($item['size_label'] !== '') {
        $facts[] = ['label' => $S['size_label'], 'value' => $item['size_label']];
    }
}
$manualLabels = array_map(static fn(array $m): string => mb_strtolower($m['label']), $item['meta']);
$facts = array_values(array_filter($facts, static fn(array $f): bool => !in_array(mb_strtolower($f['label']), $manualLabels, true)));
$facts = array_merge($facts, $item['meta']);
if ($item['count'] > 0) {
    $facts[] = ['label' => $S['downloads_label'], 'value' => $item['count_label']];
}

$shopData = [
    'kind' => 'download',
    'strings' => [
        'linkCopied' => $S['link_copied'],
        'copyFailed' => $S['copy_failed'],
        'downloadStarted' => $S['download_started'],
    ],
];
?>
<main class="shop-shell">
    <?php if ($isPreview): ?>
        <div class="shop-preview-banner" role="note">
            <i class="fa-solid fa-eye" aria-hidden="true"></i>
            <span><?php echo shop_h($S['hidden_preview']); ?></span>
        </div>
    <?php endif; ?>

    <nav class="shop-crumbs" aria-label="Breadcrumb">
        <a href="<?php echo shop_h($listUrl); ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <?php echo shop_h($S['back_to_downloads']); ?></a>
    </nav>

    <section class="shop-detail">
        <div class="shop-detail__cover">
            <?php if ($item['image'] !== ''): ?>
                <img src="<?php echo shop_h($item['image']); ?>" alt="" width="640" height="480">
            <?php else: ?>
                <i class="<?php echo shop_h(shop_download_icon($item)); ?>" aria-hidden="true"></i>
            <?php endif; ?>
        </div>

        <div class="shop-detail__content">
            <h1><?php echo shop_h($item['name']); ?></h1>
            <?php if ($item['description'] !== ''): ?>
                <p class="shop-detail__text"><?php echo shop_linkify($item['description']); ?></p>
            <?php endif; ?>

            <?php if ($item['note'] !== ''): ?>
                <div class="shop-callout shop-callout--<?php echo shop_h($item['note_tone']); ?>">
                    <i class="fa-solid <?php echo $item['note_tone'] === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-info'; ?>" aria-hidden="true"></i>
                    <p><?php echo shop_linkify($item['note']); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($facts): ?>
                <dl class="shop-facts">
                    <?php foreach ($facts as $fact): ?>
                        <div><dt><?php echo shop_h($fact['label']); ?></dt><dd><?php echo shop_h($fact['value']); ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>

            <div class="shop-detail__actions">
                <?php if ($canDownload): ?>
                    <a class="shop-btn shop-btn--primary shop-btn--large" href="<?php echo shop_h($item['go_url']); ?>" data-download-go <?php echo $isLink ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <i class="fa-solid <?php echo $isLink ? 'fa-arrow-up-right-from-square' : 'fa-download'; ?>" aria-hidden="true"></i>
                        <span><?php echo shop_h($buttonLabel); ?></span>
                    </a>
                <?php else: ?>
                    <span class="shop-btn shop-btn--large is-disabled"><?php echo shop_h($item['state'] === 'presto' ? $S['soon'] : $S['not_available']); ?></span>
                <?php endif; ?>
                <button type="button" class="shop-btn shop-btn--ghost" data-copy-page>
                    <i class="fa-solid fa-link" aria-hidden="true"></i> <span><?php echo shop_h($S['copy_link']); ?></span>
                </button>
            </div>
        </div>
    </section>

    <section class="shop-info-grid">
        <?php if ($item['steps']): ?>
            <article class="shop-info-card">
                <h2><?php echo shop_h($S['before_download']); ?></h2>
                <ol>
                    <?php foreach ($item['steps'] as $step): ?>
                        <li><?php echo shop_h($step); ?></li>
                    <?php endforeach; ?>
                </ol>
            </article>
        <?php endif; ?>
        <article class="shop-info-card">
            <h2><?php echo shop_h($S['note']); ?></h2>
            <p><?php echo shop_h($isLink ? $S['download_note_link'] : $S['download_note_file']); ?></p>
        </article>
    </section>

    <?php if ($others): ?>
        <section class="shop-others" aria-labelledby="shop-others-title">
            <h2 id="shop-others-title"><?php echo shop_h($S['other_downloads']); ?></h2>
            <div class="shop-others__list">
                <?php foreach ($others as $other): ?>
                    <a class="shop-chip-card" href="<?php echo shop_h($other['url']); ?>">
                        <span class="shop-chip-card__art" aria-hidden="true">
                            <?php if ($other['image'] !== ''): ?>
                                <img src="<?php echo shop_h($other['image']); ?>" alt="" width="44" height="44" loading="lazy">
                            <?php else: ?>
                                <i class="<?php echo shop_h(shop_download_icon($other)); ?>"></i>
                            <?php endif; ?>
                        </span>
                        <span>
                            <strong><?php echo shop_h($other['name']); ?></strong>
                            <small><?php echo shop_h($other['short']); ?></small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>

<script type="application/json" id="shop-data"><?php echo json_encode($shopData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
