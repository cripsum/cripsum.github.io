<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

function shop_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$products = [
    ['id' => 'tshirt-big-logo', 'name' => 'simonetussi.ph T-Shirt', 'variant' => 'Big logo', 'price' => 19.99, 'image' => '../img/magliag.jpg', 'alt' => 'T-Shirt big logo', 'description' => 'Big logo for maximum visibility.', 'category' => 'shirts', 'badge' => 'Drop', 'link' => 'merch-checkout'],
    ['id' => 'tshirt-small-logo', 'name' => 'simonetussi.ph T-Shirt', 'variant' => 'Small logo', 'price' => 19.99, 'image' => '../img/magliap.jpg', 'alt' => 'T-Shirt small logo', 'description' => 'Small logo for a subtle look.', 'category' => 'shirts', 'badge' => 'Minimal', 'link' => 'merch-checkout'],
    ['id' => 'felpa-big-logo', 'name' => 'simonetussi.ph Hoodie', 'variant' => 'Big logo', 'price' => 39.99, 'image' => '../img/felpag.jpg', 'alt' => 'Hoodie big logo', 'description' => 'Warm and comfortable, with a big logo.', 'category' => 'hoodies', 'badge' => 'Premium', 'link' => 'merch-checkout'],
    ['id' => 'felpa-small-logo', 'name' => 'simonetussi.ph Hoodie', 'variant' => 'Small logo', 'price' => 39.99, 'image' => '../img/felpap.jpg', 'alt' => 'Hoodie small logo', 'description' => 'Cleaner style, still recognizable.', 'category' => 'hoodies', 'badge' => 'Clean', 'link' => 'merch-checkout'],
    ['id' => 'pantaloncini', 'name' => 'simonetussi.ph Shorts', 'variant' => '', 'price' => 23.99, 'image' => '../img/pantaloncini.jpg', 'alt' => 'Shorts', 'description' => 'Comfortable for summer and sports.', 'category' => 'apparel', 'badge' => 'Summer', 'link' => 'merch-checkout'],
    ['id' => 'calzini', 'name' => 'simonetussi.ph Socks', 'variant' => '', 'price' => 5.99, 'image' => '../img/calze.jpg', 'alt' => 'Socks', 'description' => 'Even your feet deserve style.', 'category' => 'accessories', 'badge' => 'Cheap', 'link' => 'merch-checkout'],
    ['id' => 'boxer', 'name' => 'simonetussi.ph Boxers', 'variant' => '', 'price' => 149.99, 'image' => '../img/boxers.jpg', 'alt' => 'Boxers', 'description' => 'Extreme luxury for true connoisseurs.', 'category' => 'apparel', 'badge' => 'Luxury', 'link' => 'merch-checkout'],
    ['id' => 'slip', 'name' => 'simonetussi.ph Briefs', 'variant' => '', 'price' => 249.99, 'image' => '../img/mutandinesexi.jpg', 'alt' => 'Briefs', 'description' => 'Limited edition, collector\'s item.', 'category' => 'apparel', 'badge' => 'Limited', 'link' => 'merch-checkout'],
    ['id' => 'cappellino', 'name' => 'simonetussi.ph Cap', 'variant' => '', 'price' => 7.99, 'image' => '../img/cappellino.jpg', 'alt' => 'Cap', 'description' => 'Sun protection with style.', 'category' => 'accessories', 'badge' => 'Classic', 'link' => 'merch-checkout'],
    ['id' => 'occhiali-sole', 'name' => 'simonetussi.ph Sunglasses', 'variant' => '', 'price' => 8.99, 'image' => '../img/occhialis.jpg', 'alt' => 'Sunglasses', 'description' => 'True influencer look.', 'category' => 'accessories', 'badge' => 'Drip', 'link' => 'merch-checkout'],
    ['id' => 'occhiali-vista', 'name' => 'simonetussi.ph Glasses', 'variant' => '', 'price' => 35.99, 'image' => '../img/occhialiv.jpg', 'alt' => 'Glasses', 'description' => 'See the world better with the logo.', 'category' => 'accessories', 'badge' => 'Vision', 'link' => 'merch-checkout'],
    ['id' => 'tostapane', 'name' => 'simonetussi.ph Toaster', 'variant' => '', 'price' => 79.99, 'image' => '../img/tostapane.jpg', 'alt' => 'Toaster', 'description' => 'Toast your bread with style.', 'category' => 'other', 'badge' => 'Peak', 'link' => 'merch-checkout'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Merch</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Merch statico di Cripsum™ / simonetussi.ph.">
    <link rel="stylesheet" href="/assets/shop/shop.css?v=2.4">
    <script src="/assets/shop/shop.js?v=2.5" defer></script>
</head>

<body class="shop-page shop-theme-merch" data-shop-page="merch" data-favorites="1">
    <?php include '../includes/navbar.php'; ?>


    <main class="shop-shell">
        <section class="shop-hero shop-reveal">
            <div class="shop-hero__content">
                <span class="shop-kicker">Merch drop</span>
                <h1>NEW MERCH OUT NOW</h1>
                <p>The simonetussi.ph merch. The coolest photographer and psychologist ever.</p>
                <div class="shop-hero__actions">
                    <a class="shop-btn shop-btn--primary" href="#prodotti">View Products</a>
                </div>
            </div>
            <div class="shop-hero__emoji" aria-hidden="true">🤑🐦📸</div>
        </section>

        <section class="shop-panel shop-reveal" id="prodotti">
            <div class="shop-toolbar">
                <label class="shop-search">
                    <i class="fas fa-search"></i>
                    <input type="search" data-shop-search placeholder="Search products..." aria-label="Search products">
                </label>

                <div class="shop-filters" aria-label="Merch filters">
                    <button type="button" class="shop-filter is-active" data-category="all">All</button>
                    <button type="button" class="shop-filter" data-category="maglie">T-Shirts</button>
                    <button type="button" class="shop-filter" data-category="felpe">Hoodies</button>
                    <button type="button" class="shop-filter" data-category="accessori">Accessories</button>
                    <button type="button" class="shop-filter" data-category="abbigliamento">Clothing</button>
                    <button type="button" class="shop-filter" data-category="altro">Other</button>
                </div>

                <div class="shop-custom-select" data-shop-custom-select>
                    <select class="shop-select shop-native-select" data-shop-sort aria-label="Sort products" tabindex="-1" aria-hidden="true">
                        <option value="default">Original Order</option>
                        <option value="name-asc">Name A-Z</option>
                        <option value="price-asc">Price Low to High</option>
                        <option value="price-desc">Price High to Low</option>
                    </select>

                    <button type="button" class="shop-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                        <span class="shop-select-current">Original Order</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>

                    <div class="shop-select-menu" role="listbox" aria-label="Sort products options">
                        <button type="button" data-value="default">
                            <strong>Original Order</strong>
                            <span>Base</span>
                        </button>
                        <button type="button" data-value="name-asc">
                            <strong>Name A-Z</strong>
                            <span>A-Z</span>
                        </button>
                        <button type="button" data-value="price-asc">
                            <strong>Price Low to High</strong>
                            <span>€ ↑</span>
                        </button>
                        <button type="button" data-value="price-desc">
                            <strong>Price High to Low</strong>
                            <span>€ ↓</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="shop-grid" data-shop-grid>
                <?php foreach ($products as $product): ?>
                    <article class="shop-card shop-reveal"
                        id="product-<?php echo shop_h($product['id']); ?>"
                        data-product-card
                        data-id="<?php echo shop_h($product['id']); ?>"
                        data-name="<?php echo shop_h($product['name'] . ' ' . $product['variant']); ?>"
                        data-category="<?php echo shop_h($product['category']); ?>"
                        data-price="<?php echo shop_h($product['price']); ?>"
                        data-description="<?php echo shop_h($product['description']); ?>"
                        data-image="<?php echo shop_h($product['image']); ?>"
                        data-link="<?php echo shop_h($product['link']); ?>"
                        data-badge="<?php echo shop_h($product['badge']); ?>">
                        <div class="shop-card__media">
                            <img src="<?php echo shop_h($product['image']); ?>" alt="<?php echo shop_h($product['alt']); ?>" loading="lazy">
                            <span class="shop-badge"><?php echo shop_h($product['badge']); ?></span>
                        </div>
                        <div class="shop-card__body">
                            <div>
                                <h2><?php echo shop_h($product['name']); ?></h2>
                                <?php if ($product['variant'] !== ''): ?>
                                    <span class="shop-variant"><?php echo shop_h($product['variant']); ?></span>
                                <?php endif; ?>
                            </div>
                            <p><?php echo shop_h($product['description']); ?></p>
                            <div class="shop-card__footer">
                                <strong><?php echo number_format((float)$product['price'], 2, ',', '.'); ?>€</strong>
                                <div class="shop-card__actions">
                                    <button type="button" class="shop-icon-btn" data-open-detail title="Dettagli"><i class="fas fa-eye"></i></button>
                                    <a class="shop-btn shop-btn--small" href="<?php echo shop_h($product['link']); ?>">Buy Now</a>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="shop-empty" data-shop-empty hidden>
                <i class="fas fa-box-open"></i>
                <strong>No products found</strong>
                <span>Try changing your search or filter.</span>
            </div>
        </section>

        <section class="shop-faq shop-reveal">
            <h2>Frequently Asked Questions (FAQ)</h2>
            <details>
                <summary>When will the second drop of Simone Tussi's merch come out?</summary>
                <p>We don't have an official date yet, but we recommend following our social media channels to stay updated on the latest news! (never)</p>
            </details>
            <details>
                <summary>How are the clothes made?</summary>
                <p>Our merch is made of 100% polyester, guaranteeing discomfort and lack of durability. Each piece is carefully crafted by a Filipino kid to offer maximum style and resistance.</p>
            </details>
            <details>
                <summary>What payment methods do you accept?</summary>
                <p>We accept payment in kind.</p>
            </details>
        </section>
    </main>

    <div class="shop-modal" data-shop-modal hidden>
        <div class="shop-modal__backdrop" data-close-modal></div>
        <article class="shop-modal__panel" role="dialog" aria-modal="true" aria-label="Product Detail">
            <button type="button" class="shop-modal__close" data-close-modal aria-label="Close"><i class="fas fa-xmark"></i></button>
            <div class="shop-modal__content" data-modal-content></div>
        </article>
    </div>

    <div class="shop-toast" data-shop-toast></div>

    <?php include '../includes/scroll_indicator.php'; ?>
    <?php include '../includes/footer-en.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>