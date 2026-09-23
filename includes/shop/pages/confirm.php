<?php
/**
 * Conferma dell'ordine finto: /it/confirm
 *
 * Legge l'ultimo ordine dalla sessione (lo scrive api/shop/fake_order.php):
 * chi apre l'indirizzo senza aver fatto un ordine torna al negozio. Niente
 * piu' rimbalzo automatico alla home dopo quattro secondi: si resta qui e si
 * sceglie dove andare.
 */

require_once __DIR__ . '/../catalog.php';
require_once __DIR__ . '/../strings.php';

$S = shop_strings($shopLang);
$order = $_SESSION['shop_last_order'] ?? null;
$found = null;

if (is_array($order) && shop_catalog_ready($mysqli) && (time() - (int)($order['at'] ?? 0)) < 86400) {
    $found = shop_product_for_checkout($mysqli, (string)$order['slug'], $shopLang);
}

if (!$found) {
    header('Location: /' . $shopLang . '/negozio', true, 302);
    exit;
}

$product = $found['product'];
$vetrina = $found['vetrina'];
$qty = max(1, (int)$order['qty']);
$total = shop_price($product['price'] * $qty, $shopLang);

$achievement = (int)($_SESSION['shop_achievement_pending'] ?? 0);
unset($_SESSION['shop_achievement_pending']);

$pageTitle = $S['order_confirmed'];
$bodyClass = $vetrina['tipo'] === 'merch' ? 'shop-theme-merch' : 'shop-theme-store';
$bodyStyle = $vetrina['style'];
$presence = [
    'title' => $S['order_confirmed'],
    'state' => ($shopLang === 'en' ? 'Just bought ' : 'Ha appena comprato ') . $product['name'],
];

include __DIR__ . '/../partials/top.php';
?>
<main class="shop-shell shop-shell--narrow">
    <section class="shop-confirm">
        <div class="shop-confirm__icon" aria-hidden="true"><i class="fa-solid fa-check"></i></div>
        <span class="shop-kicker"><?php echo shop_h($S['order_number'] . ' #' . $order['ref']); ?></span>
        <h1><?php echo shop_h($S['thanks']); ?></h1>
        <p><?php echo shop_h($S['order_eta']); ?></p>

        <div class="shop-confirm__line">
            <?php if ($product['image'] !== ''): ?>
                <img src="<?php echo shop_h($product['image']); ?>" alt="" width="72" height="72">
            <?php endif; ?>
            <div>
                <strong><?php echo shop_h($product['name']); ?></strong>
                <small>
                    <?php echo shop_h(implode(' · ', array_filter([
                        $product['variant'],
                        $order['size'] !== '' ? $S['size'] . ' ' . $order['size'] : '',
                        $S['quantity'] . ' ' . $qty,
                    ]))); ?>
                </small>
            </div>
            <span><?php echo shop_h($total); ?></span>
        </div>

        <div class="shop-confirm__actions">
            <a class="shop-btn shop-btn--primary" href="<?php echo shop_h($vetrina['url']); ?>">
                <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> <?php echo shop_h($S['continue_shopping']); ?>
            </a>
            <a class="shop-btn shop-btn--ghost" href="/<?php echo shop_h($shopLang); ?>/home">
                <i class="fa-solid fa-house" aria-hidden="true"></i> <?php echo shop_h($S['go_home']); ?>
            </a>
        </div>
    </section>
</main>

<?php if ($achievement > 0 && isLoggedIn()): ?>
    <script>
        window.addEventListener('load', () => {
            if (typeof window.unlockAchievement === 'function') {
                window.unlockAchievement(<?php echo $achievement; ?>);
            }
        });
    </script>
<?php endif; ?>
<?php
include __DIR__ . '/../partials/bottom.php';
