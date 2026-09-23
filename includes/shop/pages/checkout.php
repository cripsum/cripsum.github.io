<?php
/**
 * Checkout finto di Negozio e Merch: /it/checkout?p={prodotto}
 *
 * E' una gag e resta tale, ma adesso mostra il prodotto scelto. I campi
 * anagrafici e quelli della carta sono solo scenografia: non hanno un
 * attributo name, quindi il browser non li invia mai, nemmeno senza
 * JavaScript. Al server arrivano soltanto prodotto, quantita' e taglia.
 * I campi carta hanno autocomplete="off" perche' il browser non ci
 * proponga una carta vera.
 */

require_once __DIR__ . '/../catalog.php';
require_once __DIR__ . '/../strings.php';

$S = shop_strings($shopLang);
$slug = strtolower(trim((string)($_GET['p'] ?? '')));
$found = (shop_catalog_ready($mysqli) && preg_match('/^[a-z0-9-]{1,80}$/', $slug))
    ? shop_product_for_checkout($mysqli, $slug, $shopLang)
    : null;

if (!$found) {
    $pageTitle = $S['checkout'];
    $bodyClass = 'shop-theme-store';
    include __DIR__ . '/../partials/top.php';
    $stateIcon = 'fa-solid fa-cart-shopping';
    $stateTitle = $S['checkout_missing'];
    $stateText = $S['checkout_missing_text'];
    $stateLink = ['href' => '/' . $shopLang . '/negozio', 'label' => $S['back_to_shop']];
    include __DIR__ . '/../partials/state.php';
    include __DIR__ . '/../partials/bottom.php';
    return;
}

$product = $found['product'];
$vetrina = $found['vetrina'];
$preselectedSize = strtoupper(trim((string)($_GET['taglia'] ?? '')));
if (!in_array($preselectedSize, $product['sizes'], true)) {
    $preselectedSize = '';
}
$username = (string)($_SESSION['username'] ?? '');

$pageTitle = $S['checkout'];
$bodyClass = $vetrina['tipo'] === 'merch' ? 'shop-theme-merch' : 'shop-theme-store';
$bodyStyle = $vetrina['style'];
$presence = [
    'title' => $S['checkout'],
    'state' => ($shopLang === 'en' ? 'Buying ' : 'Comprando ') . $product['name'],
];

include __DIR__ . '/../partials/top.php';
?>
<main class="shop-shell shop-shell--checkout">
    <nav class="shop-crumbs" aria-label="Breadcrumb">
        <a href="<?php echo shop_h($product['url']); ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <?php echo shop_h($product['name']); ?></a>
    </nav>

    <form class="shop-checkout" method="post" action="/api/shop/fake_order.php" data-shop-checkout novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo shop_h(csrf_token()); ?>">
        <input type="hidden" name="p" value="<?php echo shop_h($product['slug']); ?>">
        <input type="hidden" name="lang" value="<?php echo shop_h($shopLang); ?>">

        <div class="shop-checkout__main">
            <header class="shop-checkout__head">
                <h1><?php echo shop_h($S['checkout']); ?></h1>
                <p><?php echo shop_h($S['checkout_intro']); ?></p>
            </header>

            <fieldset class="shop-fieldset">
                <legend><?php echo shop_h($S['contacts']); ?></legend>
                <div class="shop-fields shop-fields--2">
                    <label class="shop-field"><span><?php echo shop_h($S['first_name']); ?></span><input type="text" autocomplete="given-name" required></label>
                    <label class="shop-field"><span><?php echo shop_h($S['last_name']); ?></span><input type="text" autocomplete="family-name" required></label>
                </div>
                <label class="shop-field"><span><?php echo shop_h($S['username']); ?></span><input type="text" value="<?php echo shop_h($username); ?>" autocomplete="off" required></label>
                <label class="shop-field"><span><?php echo shop_h($S['email_optional']); ?> <small><?php echo shop_h($S['optional']); ?></small></span><input type="email" placeholder="email@example.com" autocomplete="email"></label>
            </fieldset>

            <fieldset class="shop-fieldset">
                <legend><?php echo shop_h($S['address_title']); ?></legend>
                <label class="shop-field"><span><?php echo shop_h($S['address']); ?></span><input type="text" placeholder="<?php echo shop_h($shopLang === 'en' ? '221B Baker Street' : 'Via esempio, 123'); ?>" autocomplete="street-address" required></label>
                <label class="shop-field"><span><?php echo shop_h($S['address2']); ?> <small><?php echo shop_h($S['optional']); ?></small></span><input type="text" placeholder="<?php echo shop_h($S['address2_placeholder']); ?>"></label>
                <div class="shop-fields shop-fields--3">
                    <label class="shop-field"><span><?php echo shop_h($S['country']); ?></span><input type="text" autocomplete="country-name" required></label>
                    <label class="shop-field"><span><?php echo shop_h($S['region']); ?></span><input type="text" autocomplete="address-level1" required></label>
                    <label class="shop-field"><span><?php echo shop_h($S['zip']); ?></span><input type="text" autocomplete="postal-code" inputmode="numeric" required></label>
                </div>
            </fieldset>

            <fieldset class="shop-fieldset">
                <legend><?php echo shop_h($S['payment']); ?></legend>
                <div class="shop-radios">
                    <label class="shop-radio"><input type="radio" name="fake_payment" value="credit" checked><span><i class="fa-regular fa-credit-card" aria-hidden="true"></i> <?php echo shop_h($S['credit_card']); ?></span></label>
                    <label class="shop-radio"><input type="radio" name="fake_payment" value="debit"><span><i class="fa-solid fa-credit-card" aria-hidden="true"></i> <?php echo shop_h($S['debit_card']); ?></span></label>
                    <label class="shop-radio"><input type="radio" name="fake_payment" value="paypal"><span><i class="fa-brands fa-paypal" aria-hidden="true"></i> PayPal</span></label>
                </div>
                <div class="shop-fields shop-fields--2">
                    <label class="shop-field"><span><?php echo shop_h($S['card_name']); ?></span><input type="text" autocomplete="off" required></label>
                    <label class="shop-field"><span><?php echo shop_h($S['card_number']); ?></span><input type="text" inputmode="numeric" autocomplete="off" required></label>
                </div>
                <div class="shop-fields shop-fields--2">
                    <label class="shop-field"><span><?php echo shop_h($S['card_expiry']); ?></span><input type="text" placeholder="MM/AA" autocomplete="off" required></label>
                    <label class="shop-field"><span><?php echo shop_h($S['card_cvv']); ?></span><input type="text" inputmode="numeric" autocomplete="off" required></label>
                </div>
            </fieldset>
        </div>

        <aside class="shop-summary" aria-labelledby="shop-summary-title">
            <h2 id="shop-summary-title"><?php echo shop_h($S['summary']); ?></h2>

            <div class="shop-summary__product">
                <?php if ($product['image'] !== ''): ?>
                    <img src="<?php echo shop_h($product['image']); ?>" alt="" width="96" height="96">
                <?php endif; ?>
                <div>
                    <strong><?php echo shop_h($product['name']); ?></strong>
                    <?php if ($product['variant'] !== ''): ?>
                        <small><?php echo shop_h($product['variant']); ?></small>
                    <?php endif; ?>
                    <span><?php echo shop_h($product['price_label']); ?></span>
                </div>
            </div>

            <?php if ($product['sizes']): ?>
                <div class="shop-summary__row shop-summary__row--stack">
                    <span id="shop-size-label"><?php echo shop_h($S['size']); ?></span>
                    <div class="shop-sizes" role="radiogroup" aria-labelledby="shop-size-label">
                        <?php foreach ($product['sizes'] as $size): ?>
                            <label class="shop-size">
                                <input type="radio" name="taglia" value="<?php echo shop_h($size); ?>" <?php echo $size === $preselectedSize ? 'checked' : ''; ?> required>
                                <span><?php echo shop_h($size); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="shop-summary__row">
                <label for="shop-qty"><?php echo shop_h($S['quantity']); ?></label>
                <div class="shop-qty">
                    <button type="button" data-qty-step="-1" aria-label="-1"><i class="fa-solid fa-minus"></i></button>
                    <input id="shop-qty" type="number" name="qty" value="1" min="1" max="99" inputmode="numeric" data-qty data-unit-price="<?php echo shop_h((string)$product['price']); ?>">
                    <button type="button" data-qty-step="1" aria-label="+1"><i class="fa-solid fa-plus"></i></button>
                </div>
            </div>

            <dl class="shop-summary__totals">
                <div><dt><?php echo shop_h($S['subtotal']); ?></dt><dd data-subtotal><?php echo shop_h($product['price_label']); ?></dd></div>
                <div><dt><?php echo shop_h($S['shipping']); ?></dt><dd><?php echo shop_h($S['shipping_free']); ?></dd></div>
                <div class="is-total"><dt><?php echo shop_h($S['total']); ?></dt><dd data-total><?php echo shop_h($product['price_label']); ?></dd></div>
            </dl>

            <p class="shop-form-error" data-checkout-error role="alert" hidden></p>

            <button type="submit" class="shop-btn shop-btn--primary shop-btn--wide" data-checkout-submit>
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <span><?php echo shop_h($S['place_order']); ?></span>
            </button>
        </aside>
    </form>
</main>

<script type="application/json" id="shop-data"><?php echo json_encode([
    'kind' => 'checkout',
    'strings' => [
        'chooseSize' => $S['choose_size'],
        'processing' => $S['processing'],
        'placeOrder' => $S['place_order'],
        'orderError' => $S['order_error'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
<?php
include __DIR__ . '/../partials/bottom.php';
