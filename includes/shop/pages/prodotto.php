<?php
/**
 * Pagina di un prodotto del Negozio (/it/negozio/{prodotto}) o di una
 * collezione del Merch (/it/merch/{collezione}/{prodotto}).
 *
 * La includono negozio.php e merch.php con:
 *   $productSlug          lo slug chiesto
 *   $expectedTipo         'negozio' o 'merch'
 *   $expectedVetrinaSlug  la collezione nell'indirizzo (solo Merch)
 *
 * Foto, specifiche e descrizione lunga stanno qui; il bottone Acquista porta
 * al checkout finto con la taglia gia' scelta.
 */

$found = preg_match('/^[a-z0-9-]{1,80}$/', $productSlug)
    ? shop_product_page($mysqli, $productSlug, $shopLang, shop_is_staff())
    : null;

if (!$found) {
    http_response_code(404);
    $pageTitle = $expectedTipo === 'merch' ? 'Merch' : ($shopLang === 'en' ? 'Shop' : 'Negozio');
    $bodyClass = 'shop-theme-store';
    $bodyStyle = shop_store_style($mysqli, $shopLang);
    include __DIR__ . '/../partials/top.php';
    $stateIcon = 'fa-solid fa-box-open';
    $stateTitle = $S['not_found_title'];
    $stateText = $S['product_not_found'];
    $stateLink = [
        'href' => $expectedTipo === 'merch' ? '/' . $shopLang . '/merch' : '/' . $shopLang . '/negozio',
        'label' => $expectedTipo === 'merch' ? $S['back_to_merch'] : $S['back_to_shop'],
    ];
    include __DIR__ . '/../partials/state.php';
    include __DIR__ . '/../partials/bottom.php';
    return;
}

$product = $found['product'];
$vetrina = $found['vetrina'];

// Un prodotto ha un indirizzo solo: se si arriva da quello sbagliato (link
// vecchio, collezione rinominata, prodotto spostato) si viene portati li'.
if ($vetrina['tipo'] !== $expectedTipo || ($expectedTipo === 'merch' && $vetrina['slug'] !== ($expectedVetrinaSlug ?? ''))) {
    header('Location: ' . $product['url'], true, 301);
    exit;
}

$isPreview = $found['preview'];
$related = shop_related_products($mysqli, $product, $vetrina, $found['categories'], $shopLang, 4);
$isMerch = $vetrina['tipo'] === 'merch';
$shopUrl = $isMerch ? $vetrina['url'] : '/' . $shopLang . '/negozio';
$buyUrl = '/' . $shopLang . '/checkout?p=' . rawurlencode($product['slug']);

// Sconto in percentuale, solo se c'e' un prezzo pieno piu' alto.
$discount = $product['full_price'] ? (int)round((1 - $product['price'] / $product['full_price']) * 100) : 0;

// Le specifiche automatiche vengono prima di quelle scritte nel pannello;
// un'etichetta scritta a mano con lo stesso nome prende il posto di quella
// automatica.
$facts = [];
if ($product['category_name'] !== '') {
    $facts[] = ['label' => $S['category'], 'value' => $product['category_name']];
}
if ($isMerch) {
    $facts[] = ['label' => $S['collection'], 'value' => $vetrina['name']];
}
if ($product['variant'] !== '') {
    $facts[] = ['label' => $S['variant'], 'value' => $product['variant']];
}
if ($product['sizes']) {
    $facts[] = ['label' => $S['sizes'], 'value' => implode(' · ', $product['sizes'])];
}
$manualLabels = array_map(static fn(array $f): string => mb_strtolower($f['label']), $product['specs']);
$facts = array_values(array_filter($facts, static fn(array $f): bool => !in_array(mb_strtolower($f['label']), $manualLabels, true)));
$facts = array_merge($facts, $product['specs']);

$pageTitle = $product['name'] . ($product['variant'] !== '' ? ' ' . $product['variant'] : '');
$pageDescription = $product['description'] !== '' ? $product['description'] : $product['long_description'];
$pageImage = $product['image'];
$bodyClass = $isMerch ? 'shop-theme-merch' : 'shop-theme-store';
$bodyStyle = $vetrina['style'];
$presence = [
    'title' => $isMerch ? 'Merch' : ($shopLang === 'en' ? 'Shop' : 'Negozio'),
    'state' => ($shopLang === 'en' ? 'Looking at ' : 'Guardando ') . $product['name'],
];

$shopData = [
    'kind' => 'product',
    'buyUrl' => $buyUrl,
    'strings' => [
        'linkCopied' => $S['link_copied'],
        'copyFailed' => $S['copy_failed'],
        'photo' => $S['photo'],
    ],
];

include __DIR__ . '/../partials/top.php';
?>
<main class="shop-shell">
    <?php if ($isPreview): ?>
        <div class="shop-preview-banner" role="note">
            <i class="fa-solid fa-eye" aria-hidden="true"></i>
            <span><?php echo shop_h($S['product_preview']); ?></span>
        </div>
    <?php endif; ?>

    <nav class="shop-crumbs shop-crumbs--trail" aria-label="Breadcrumb">
        <?php if ($isMerch): ?>
            <a href="/<?php echo shop_h($shopLang); ?>/merch">Merch</a>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            <a href="<?php echo shop_h($vetrina['url']); ?>"><?php echo shop_h($vetrina['name']); ?></a>
        <?php else: ?>
            <a href="<?php echo shop_h($shopUrl); ?>"><?php echo shop_h($shopLang === 'en' ? 'Shop' : 'Negozio'); ?></a>
        <?php endif; ?>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span aria-current="page"><?php echo shop_h($product['name']); ?></span>
    </nav>

    <section class="shop-product">
        <div class="shop-gallery" data-shop-gallery>
            <div class="shop-gallery__main">
                <?php if ($product['gallery']): ?>
                    <img src="<?php echo shop_h($product['gallery'][0]); ?>" alt="<?php echo shop_h($product['name']); ?>" width="720" height="720" data-gallery-main>
                <?php else: ?>
                    <i class="fa-solid fa-box-open shop-card__placeholder" aria-hidden="true"></i>
                <?php endif; ?>
                <?php if ($product['badge'] !== ''): ?>
                    <span class="shop-badge"><?php echo shop_h($product['badge']); ?></span>
                <?php endif; ?>
                <?php if ($product['featured']): ?>
                    <span class="shop-flag" title="<?php echo shop_h($S['featured']); ?>"><i class="fa-solid fa-star" aria-hidden="true"></i><span class="visually-hidden"><?php echo shop_h($S['featured']); ?></span></span>
                <?php endif; ?>
            </div>

            <?php if (count($product['gallery']) > 1): ?>
                <div class="shop-gallery__thumbs" role="group" aria-label="<?php echo shop_h($S['photos']); ?>">
                    <?php foreach ($product['gallery'] as $i => $image): ?>
                        <button type="button" class="shop-gallery__thumb <?php echo $i === 0 ? 'is-active' : ''; ?>" data-gallery-thumb="<?php echo shop_h($image); ?>" aria-pressed="<?php echo $i === 0 ? 'true' : 'false'; ?>" aria-label="<?php echo shop_h(sprintf($S['photo'], $i + 1, count($product['gallery']))); ?>">
                            <img src="<?php echo shop_h($image); ?>" alt="" width="96" height="96" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="shop-product__info">
            <h1><?php echo shop_h($product['name']); ?></h1>
            <?php if ($product['variant'] !== ''): ?>
                <p class="shop-product__variant"><?php echo shop_h($product['variant']); ?></p>
            <?php endif; ?>

            <div class="shop-product__price">
                <strong><?php echo shop_h($product['price_label']); ?></strong>
                <?php if ($product['full_price_label'] !== ''): ?>
                    <s><?php echo shop_h($product['full_price_label']); ?></s>
                    <?php if ($discount > 0): ?>
                        <span class="shop-product__discount">−<?php echo $discount; ?>%</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($product['description'] !== ''): ?>
                <p class="shop-product__lead"><?php echo shop_h($product['description']); ?></p>
            <?php endif; ?>

            <?php if ($product['sizes']): ?>
                <div class="shop-product__sizes">
                    <span class="shop-product__label" id="shop-size-label"><?php echo shop_h($S['choose_size_label']); ?></span>
                    <div class="shop-sizes" role="radiogroup" aria-labelledby="shop-size-label">
                        <?php foreach ($product['sizes'] as $size): ?>
                            <label class="shop-size">
                                <input type="radio" name="taglia" value="<?php echo shop_h($size); ?>" data-product-size>
                                <span><?php echo shop_h($size); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="shop-product__actions">
                <a class="shop-btn shop-btn--primary shop-btn--large" href="<?php echo shop_h($buyUrl); ?>" data-product-buy>
                    <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> <?php echo shop_h($S['buy']); ?>
                </a>
                <button type="button" class="shop-btn shop-btn--ghost shop-btn--large" data-copy-page>
                    <i class="fa-solid fa-link" aria-hidden="true"></i> <?php echo shop_h($S['copy_link']); ?>
                </button>
            </div>

            <ul class="shop-product__perks">
                <li><i class="fa-solid fa-truck-fast" aria-hidden="true"></i> <?php echo shop_h($S['perk_shipping']); ?></li>
                <li><i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i> <?php echo shop_h($S['perk_payment']); ?></li>
                <?php if ($product['orders'] > 0): ?>
                    <li><i class="fa-solid fa-fire" aria-hidden="true"></i> <?php echo shop_h($product['orders'] === 1 ? $S['bought_one'] : sprintf($S['bought_many'], $product['orders'])); ?></li>
                <?php endif; ?>
            </ul>
        </div>
    </section>

    <?php if ($facts || $product['long_description'] !== ''): ?>
        <section class="shop-product-details">
            <?php if ($product['long_description'] !== ''): ?>
                <article class="shop-info-card">
                    <h2><?php echo shop_h($S['description']); ?></h2>
                    <p class="shop-product__long"><?php echo shop_linkify($product['long_description']); ?></p>
                </article>
            <?php endif; ?>
            <?php if ($facts): ?>
                <article class="shop-info-card">
                    <h2><?php echo shop_h($S['specifications']); ?></h2>
                    <dl class="shop-specs">
                        <?php foreach ($facts as $fact): ?>
                            <div><dt><?php echo shop_h($fact['label']); ?></dt><dd><?php echo shop_h($fact['value']); ?></dd></div>
                        <?php endforeach; ?>
                    </dl>
                </article>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($related): ?>
        <section class="shop-related" aria-labelledby="shop-related-title">
            <h2 id="shop-related-title"><?php echo shop_h($S['related']); ?></h2>
            <div class="shop-grid shop-grid--related">
                <?php foreach ($related as $cardIndex => $product): ?>
                    <?php include __DIR__ . '/../partials/product_card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>

<script type="application/json" id="shop-data"><?php echo json_encode($shopData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
<?php
include __DIR__ . '/../partials/bottom.php';
