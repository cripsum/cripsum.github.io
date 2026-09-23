<?php
/**
 * Elenco delle collezioni del Merch.
 *
 * Variabili attese: $page (shop_page_texts), $collections
 * (shop_merch_collections), $latest (shop_latest_merch), $faq.
 *
 * Ogni card prende i colori della sua collezione: l'elenco e' gia' un
 * assaggio di come sara' la pagina dentro.
 */
?>
<main class="shop-shell">
    <section class="shop-hero shop-hero--compact">
        <div class="shop-hero__content">
            <h1><?php echo shop_h($page['title'] !== '' ? $page['title'] : 'Merch'); ?></h1>
            <?php if ($page['subtitle'] !== ''): ?>
                <p><?php echo shop_h($page['subtitle']); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!$collections): ?>
        <section class="shop-panel">
            <div class="shop-empty">
                <i class="fa-solid fa-shirt" aria-hidden="true"></i>
                <strong><?php echo shop_h($S['no_collections']); ?></strong>
            </div>
        </section>
    <?php else: ?>
        <section class="shop-collections" aria-label="<?php echo shop_h($S['other_collections']); ?>">
            <?php foreach ($collections as $collection): ?>
                <?php $soon = $collection['state'] === 'in_arrivo'; ?>
                <a class="shop-collection <?php echo $soon ? 'is-soon' : ''; ?>" href="<?php echo shop_h($collection['url']); ?>" style="<?php echo shop_h($collection['style']); ?>">
                    <span class="shop-collection__cover" aria-hidden="true">
                        <?php if ($collection['cover'] !== ''): ?>
                            <img src="<?php echo shop_h($collection['cover']); ?>" alt="" width="640" height="400" loading="lazy">
                        <?php elseif ($collection['preview'] && !$soon): ?>
                            <span class="shop-collection__mosaic shop-collection__mosaic--<?php echo count($collection['preview']); ?>">
                                <?php foreach ($collection['preview'] as $image): ?>
                                    <img src="<?php echo shop_h($image); ?>" alt="" width="320" height="320" loading="lazy">
                                <?php endforeach; ?>
                            </span>
                        <?php else: ?>
                            <span class="shop-collection__emoji"><?php echo shop_h($collection['emoji'] !== '' ? $collection['emoji'] : '🛍️'); ?></span>
                        <?php endif; ?>

                        <?php if ($soon): ?>
                            <span class="shop-badge shop-badge--soon"><i class="fa-solid fa-hourglass-half"></i> <?php echo shop_h($S['coming_soon']); ?></span>
                        <?php elseif ($collection['is_new']): ?>
                            <span class="shop-badge shop-badge--new"><?php echo shop_h($S['new']); ?></span>
                        <?php endif; ?>
                    </span>

                    <span class="shop-collection__body">
                        <span class="shop-collection__head">
                            <?php if ($collection['logo'] !== ''): ?>
                                <img class="shop-collection__logo" src="<?php echo shop_h($collection['logo']); ?>" alt="" width="48" height="48" loading="lazy">
                            <?php endif; ?>
                            <span>
                                <strong><?php echo shop_h($collection['name']); ?></strong>
                                <small>
                                    <?php if ($soon && $collection['launch_at']): ?>
                                        <span data-countdown="<?php echo shop_h(date('c', strtotime($collection['launch_at']))); ?>">
                                            <?php echo shop_h($S['launch_in']); ?> <span data-countdown-output>…</span>
                                        </span>
                                    <?php elseif ($soon): ?>
                                        <?php echo shop_h($S['coming_soon']); ?>
                                    <?php else: ?>
                                        <?php echo (int)$collection['count']; ?> <?php echo shop_h($collection['count'] === 1 ? $S['product'] : $S['products']); ?>
                                    <?php endif; ?>
                                </small>
                            </span>
                        </span>
                        <?php if ($collection['subtitle'] !== ''): ?>
                            <span class="shop-collection__text"><?php echo shop_h($collection['subtitle']); ?></span>
                        <?php endif; ?>
                        <span class="shop-collection__cta"><?php echo shop_h($S['view_collection']); ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </section>

        <?php if ($latest): ?>
            <section class="shop-latest" aria-labelledby="shop-latest-title">
                <h2 id="shop-latest-title"><?php echo shop_h($S['latest_arrivals']); ?></h2>
                <div class="shop-latest__track">
                    <?php foreach ($latest as $product): ?>
                        <a class="shop-latest__item" href="<?php echo shop_h($product['url']); ?>">
                            <span class="shop-latest__image">
                                <?php if ($product['image'] !== ''): ?>
                                    <img src="<?php echo shop_h($product['image']); ?>" alt="" width="220" height="220" loading="lazy">
                                <?php endif; ?>
                            </span>
                            <small><?php echo shop_h($product['collection']); ?></small>
                            <strong><?php echo shop_h($product['name']); ?></strong>
                            <span><?php echo shop_h($product['price_label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <?php include __DIR__ . '/../partials/faq.php'; ?>
</main>
