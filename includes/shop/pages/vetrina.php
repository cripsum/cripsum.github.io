<?php
/**
 * Una vetrina finta: il Negozio o una collezione del Merch.
 *
 * Variabili attese (le prepara il controller):
 *   $vetrina           shop_vetrina_view()
 *   $products          shop_products()
 *   $categories        shop_categories()
 *   $faq               shop_faq()
 *   $isPreview         lo staff sta guardando una collezione nascosta o non ancora uscita
 *   $otherCollections  le altre collezioni del Merch (vuoto per il Negozio)
 *   $hubUrl            link all'elenco del Merch, o null
 *
 * Ogni card porta alla pagina del prodotto. I dati per filtri e ordinamento
 * arrivano al JavaScript in un blocco JSON; le card nel DOM restano quelle
 * generate qui, quindi la pagina si legge anche senza JavaScript.
 */

$isComingSoon = $vetrina['state'] === 'in_arrivo' && !$isPreview;

// Prima del drop i prodotti non devono uscire nemmeno nel sorgente della
// pagina: chi apre gli strumenti del browser non deve rovinarsi la sorpresa.
if ($isComingSoon) {
    $products = [];
}

// Solo le categorie che in questa vetrina hanno almeno un prodotto.
$categoryCounts = [];
foreach ($products as $product) {
    if ($product['category'] !== '') {
        $categoryCounts[$product['category']] = ($categoryCounts[$product['category']] ?? 0) + 1;
    }
}
$visibleCategories = array_values(array_filter($categories, static fn(array $c): bool => isset($categoryCounts[$c['slug']])));

$jsonProducts = [];
foreach ($products as $product) {
    $jsonProducts[] = [
        'slug' => $product['slug'],
        'name' => $product['name'],
        'variant' => $product['variant'],
        'description' => $product['description'],
        'price' => $product['price'],
        'category' => $product['category'],
        'featured' => $product['featured'],
        'orders' => $product['orders'],
        'position' => $product['position'],
        'created' => $product['created'],
    ];
}

$shopData = [
    'kind' => 'products',
    'products' => $jsonProducts,
    'strings' => [
        'resultsOne' => $S['results_one'],
        'resultsMany' => $S['results_many'],
    ],
];

$sortOptions = [
    'featured' => $S['sort_featured'],
    'popular' => $S['sort_popular'],
    'price-asc' => $S['sort_price_asc'],
    'price-desc' => $S['sort_price_desc'],
    'name' => $S['sort_name'],
];
?>
<main class="shop-shell">
    <?php if ($isPreview): ?>
        <div class="shop-preview-banner" role="note">
            <i class="fa-solid fa-eye" aria-hidden="true"></i>
            <span><?php echo shop_h($shopLang === 'en'
                ? 'Staff preview: this collection is not public yet.'
                : 'Anteprima staff: questa collezione non è ancora pubblica.'); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($hubUrl): ?>
        <nav class="shop-crumbs" aria-label="Breadcrumb">
            <a href="<?php echo shop_h($hubUrl); ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <?php echo shop_h($S['back_to_merch']); ?></a>
        </nav>
    <?php endif; ?>

    <section class="shop-hero">
        <div class="shop-hero__content">
            <h1><?php echo shop_h($vetrina['title']); ?></h1>
            <?php if ($vetrina['subtitle'] !== ''): ?>
                <p><?php echo shop_h($vetrina['subtitle']); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($vetrina['logo'] !== ''): ?>
            <div class="shop-hero__art shop-hero__art--logo" aria-hidden="true">
                <img src="<?php echo shop_h($vetrina['logo']); ?>" alt="" width="220" height="220">
            </div>
        <?php elseif ($vetrina['emoji'] !== ''): ?>
            <div class="shop-hero__art" aria-hidden="true"><?php echo shop_h($vetrina['emoji']); ?></div>
        <?php endif; ?>
    </section>

    <?php if ($isComingSoon): ?>
        <section class="shop-soon" <?php if ($vetrina['launch_at']): ?>data-countdown="<?php echo shop_h(date('c', strtotime($vetrina['launch_at']))); ?>"<?php endif; ?>>
            <i class="fa-solid fa-hourglass-half shop-soon__icon" aria-hidden="true"></i>
            <h2><?php echo shop_h($S['coming_soon_text']); ?></h2>
            <?php if ($vetrina['launch_at']): ?>
                <p class="shop-soon__label"><?php echo shop_h($S['launch_in']); ?></p>
                <div class="shop-countdown" data-countdown-output aria-live="polite">
                    <?php echo shop_h(date($shopLang === 'en' ? 'M j, Y · H:i' : 'd/m/Y · H:i', strtotime($vetrina['launch_at']))); ?>
                </div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="shop-panel" id="prodotti" aria-label="<?php echo shop_h($vetrina['name']); ?>">
            <?php if ($products): ?>
                <div class="shop-toolbar" data-shop-toolbar>
                    <label class="shop-search">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" data-shop-search placeholder="<?php echo shop_h($S['search_products']); ?>" aria-label="<?php echo shop_h($S['search_products']); ?>" autocomplete="off">
                        <button type="button" class="shop-search__clear" data-shop-search-clear aria-label="<?php echo shop_h($S['clear_search']); ?>" hidden><i class="fa-solid fa-xmark"></i></button>
                    </label>

                    <?php include __DIR__ . '/../partials/sort_select.php'; ?>

                    <?php if (count($visibleCategories) > 1): ?>
                        <div class="shop-filters" role="group" aria-label="<?php echo shop_h($S['all']); ?>">
                            <button type="button" class="shop-filter is-active" data-category="" aria-pressed="true">
                                <?php echo shop_h($S['all']); ?> <span><?php echo count($products); ?></span>
                            </button>
                            <?php foreach ($visibleCategories as $category): ?>
                                <button type="button" class="shop-filter" data-category="<?php echo shop_h($category['slug']); ?>" aria-pressed="false">
                                    <?php echo shop_h($category['name']); ?> <span><?php echo (int)$categoryCounts[$category['slug']]; ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <p class="shop-results" data-shop-results aria-live="polite"></p>
                </div>

                <div class="shop-grid" data-shop-grid>
                    <?php foreach ($products as $cardIndex => $product): ?>
                        <?php include __DIR__ . '/../partials/product_card.php'; ?>
                    <?php endforeach; ?>
                </div>

                <div class="shop-empty" data-shop-empty hidden>
                    <i class="fa-solid fa-box-open" aria-hidden="true"></i>
                    <strong><?php echo shop_h($S['empty_title']); ?></strong>
                    <span><?php echo shop_h($S['empty_text']); ?></span>
                    <button type="button" class="shop-btn shop-btn--ghost" data-shop-reset><?php echo shop_h($S['reset_filters']); ?></button>
                </div>
            <?php else: ?>
                <div class="shop-empty">
                    <i class="fa-solid fa-box-open" aria-hidden="true"></i>
                    <strong><?php echo shop_h($S['empty_collection']); ?></strong>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php include __DIR__ . '/../partials/faq.php'; ?>

    <?php if (!empty($otherCollections)): ?>
        <section class="shop-others" aria-labelledby="shop-others-title">
            <h2 id="shop-others-title"><?php echo shop_h($S['other_collections']); ?></h2>
            <div class="shop-others__list">
                <?php foreach ($otherCollections as $other): ?>
                    <a class="shop-chip-card" href="<?php echo shop_h($other['url']); ?>" style="<?php echo shop_h($other['style']); ?>">
                        <span class="shop-chip-card__art" aria-hidden="true">
                            <?php if ($other['logo'] !== ''): ?>
                                <img src="<?php echo shop_h($other['logo']); ?>" alt="" width="44" height="44" loading="lazy">
                            <?php elseif ($other['preview']): ?>
                                <img src="<?php echo shop_h($other['preview'][0]); ?>" alt="" width="44" height="44" loading="lazy">
                            <?php else: ?>
                                <?php echo shop_h($other['emoji'] !== '' ? mb_substr($other['emoji'], 0, 2) : '🛍️'); ?>
                            <?php endif; ?>
                        </span>
                        <span>
                            <strong><?php echo shop_h($other['name']); ?></strong>
                            <small><?php echo shop_h($other['state'] === 'in_arrivo' ? $S['coming_soon'] : $other['count'] . ' ' . ($other['count'] === 1 ? $S['product'] : $S['products'])); ?></small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>

<script type="application/json" id="shop-data"><?php echo json_encode($shopData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
