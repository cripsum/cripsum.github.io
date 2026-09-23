<?php
/**
 * Card di un prodotto (Negozio o Merch). Tutta la card porta alla pagina del
 * prodotto: li' ci sono foto, specifiche e il bottone Acquista.
 *
 * Variabili: $product (shop_product_view con 'url'), $S, $cardIndex (per il
 * caricamento pigro delle immagini sotto la piega).
 */
$cardIndex = $cardIndex ?? 0;
?>
<article class="shop-card" data-shop-item="<?php echo shop_h($product['slug']); ?>">
    <a class="shop-card__hit" href="<?php echo shop_h($product['url']); ?>">
        <span class="visually-hidden"><?php echo shop_h($product['name'] . ($product['variant'] !== '' ? ' ' . $product['variant'] : '')); ?></span>
    </a>
    <div class="shop-card__media">
        <?php if ($product['image'] !== ''): ?>
            <img src="<?php echo shop_h($product['image']); ?>" alt="" width="480" height="375" <?php echo $cardIndex > 5 ? 'loading="lazy"' : ''; ?> decoding="async">
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
    <div class="shop-card__body">
        <h2><?php echo shop_h($product['name']); ?></h2>
        <?php if ($product['variant'] !== ''): ?>
            <span class="shop-variant"><?php echo shop_h($product['variant']); ?></span>
        <?php endif; ?>
        <?php if ($product['description'] !== ''): ?>
            <p><?php echo shop_h($product['description']); ?></p>
        <?php endif; ?>
        <div class="shop-card__footer">
            <div class="shop-price">
                <?php if ($product['full_price_label'] !== ''): ?>
                    <s><?php echo shop_h($product['full_price_label']); ?></s>
                <?php endif; ?>
                <strong><?php echo shop_h($product['price_label']); ?></strong>
            </div>
            <span class="shop-card__go" aria-hidden="true"><?php echo shop_h($S['view_product']); ?> <i class="fa-solid fa-arrow-right"></i></span>
        </div>
    </div>
</article>
