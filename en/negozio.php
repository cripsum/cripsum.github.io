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
    ['id' => 'rtx-4090', 'name' => 'RTX 4090', 'price' => 2499.99, 'image' => '../img/4090.jpg', 'alt' => 'RTX 4090', 'description' => 'Too powerful even for gaming.', 'category' => 'tech', 'badge' => 'Overkill', 'link' => 'checkout'],
    ['id' => 'iphone-20', 'name' => 'Iphone 20', 'price' => 2179.99, 'image' => '../img/iphone20.jpg', 'alt' => 'Iphone 20', 'description' => 'The future. Great phone, shame about the OS.', 'category' => 'tech', 'badge' => 'Future', 'link' => 'checkout'],
    ['id' => 'tua-madre', 'name' => 'your mom', 'price' => 2.49, 'image' => '../img/indica.jpg', 'alt' => 'your mom', 'description' => 'If purchased by 2025: 40% discount.', 'category' => 'meme', 'badge' => '40% Off', 'link' => 'checkout'],
    ['id' => 'samsung-s30', 'name' => 'Samsung galaxy s30', 'price' => 1599.99, 'image' => '../img/s30.jpg', 'alt' => 'Samsung galaxy s30', 'description' => '16GB RAM, 1TB storage, 7000mAh battery and x100 zoom.', 'category' => 'tech', 'badge' => 'Specs', 'link' => 'checkout'],
    ['id' => 'ps6', 'name' => 'Ps6', 'price' => 849.49, 'image' => '../img/ps6.jpg', 'alt' => 'Ps6', 'description' => 'To play GTA 5 remastered in 8K 120fps.', 'category' => 'gaming', 'badge' => 'Next gen', 'link' => 'checkout'],
    ['id' => 'monitor-540hz', 'name' => '540hz Monitor', 'price' => 949.69, 'image' => '../img/monitor540.jpg', 'alt' => '540hz Monitor', 'description' => 'Extreme fluidity. Completely useless.', 'category' => 'gaming', 'badge' => '540hz', 'link' => 'checkout'],
    ['id' => 'renegade-raider', 'name' => 'renegade raider', 'price' => 429.99, 'image' => '../img/renegade.jpg', 'alt' => 'renegade raider', 'description' => 'The most OG skin of them all, but also the most expensive.', 'category' => 'gaming', 'badge' => 'OG', 'link' => 'checkout'],
    ['id' => 'tavoletta-grafica', 'name' => 'Drawing tablet', 'price' => 119.89, 'image' => '../img/tavoletta.jpg', 'alt' => 'Drawing tablet', 'description' => 'Accessory for osu.', 'category' => 'osu', 'badge' => 'osu', 'link' => 'checkout'],
    ['id' => 'tastiera-osu', 'name' => 'osu keyboard', 'price' => 44.99, 'image' => '../img/tastiera2tasti.jpg', 'alt' => 'osu keyboard', 'description' => 'Two keys, zero excuses.', 'category' => 'osu', 'badge' => 'osu', 'link' => 'checkout'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Cripsum™ shop. Products that only the coolest people would dare to buy.">
    <link rel="stylesheet" href="/assets/shop/shop.css?v=2.4">
    <script src="/assets/shop/shop.js?v=2.5" defer></script>
</head>

<body class="shop-page shop-theme-store" data-shop-page="negozio" data-favorites="1">
    <?php include '../includes/navbar.php'; ?>


    <main class="shop-shell">
        <section class="shop-hero shop-reveal">
            <div class="shop-hero__content">
                <span class="shop-kicker">Shop fico</span>
                <h1>Cripsum™ Shop</h1>
                <p>Products that only the coolest would dare to buy.</p>
                <div class="shop-hero__actions">
                    <a class="shop-btn shop-btn--primary" href="#prodotti">View products</a>
                </div>
            </div>
        </section>

        <section class="shop-panel shop-reveal" id="prodotti">
            <div class="shop-toolbar">
                <label class="shop-search">
                    <i class="fas fa-search"></i>
                    <input type="search" data-shop-search placeholder="Search products..." aria-label="Search products...">
                </label>

                <div class="shop-filters" aria-label="Shop filters">
                    <button type="button" class="shop-filter is-active" data-category="all">All</button>
                    <button type="button" class="shop-filter" data-category="tech">Tech</button>
                    <button type="button" class="shop-filter" data-category="gaming">Gaming</button>
                    <button type="button" class="shop-filter" data-category="osu">osu</button>
                    <button type="button" class="shop-filter" data-category="meme">Meme</button>
                </div>

                <div class="shop-custom-select" data-shop-custom-select>
                    <select class="shop-select shop-native-select" data-shop-sort aria-label="Sort products" tabindex="-1" aria-hidden="true">
                        <option value="default">Original order</option>
                        <option value="name-asc">Name A-Z</option>
                        <option value="price-asc">Price low to high</option>
                        <option value="price-desc">Price high to low</option>
                    </select>

                    <button type="button" class="shop-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                        <span class="shop-select-current">Original order</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>

                    <div class="shop-select-menu" role="listbox" aria-label="Sort products options">
                        <button type="button" data-value="default">
                            <strong>Original order</strong>
                            <span>Base</span>
                        </button>
                        <button type="button" data-value="name-asc">
                            <strong>Name A-Z</strong>
                            <span>A-Z</span>
                        </button>
                        <button type="button" data-value="price-asc">
                            <strong>Price low to high</strong>
                            <span>€ ↑</span>
                        </button>
                        <button type="button" data-value="price-desc">
                            <strong>Price high to low</strong>
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
                        data-name="<?php echo shop_h($product['name']); ?>"
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
                            <h2><?php echo shop_h($product['name']); ?></h2>
                            <p><?php echo shop_h($product['description']); ?></p>
                            <div class="shop-card__footer">
                                <strong><?php echo number_format((float)$product['price'], 2, ',', '.'); ?>€</strong>
                                <div class="shop-card__actions">
                                    <button type="button" class="shop-icon-btn" data-open-detail title="Dettagli"><i class="fas fa-eye"></i></button>
                                    <a class="shop-btn shop-btn--small" href="<?php echo shop_h($product['link']); ?>">Buy now</a>
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
                <summary>How do you have products that don't exist on the market yet?</summary>
                <p>We have a development team working on prototypes and concepts for future products. basically my uncle who works at nintendo gave them to me</p>
            </details>
            <details>
                <summary>What do you mean you're selling my mom?</summary>
                <p>Literally, we are trying to expand our product line to include more "personal" items.</p>
            </details>
            <details>
                <summary>What payment methods do you accept?</summary>
                <p>We accept "special" favors.</p>
            </details>
        </section>
    </main>

    <div class="shop-modal" data-shop-modal hidden>
        <div class="shop-modal__backdrop" data-close-modal></div>
        <article class="shop-modal__panel" role="dialog" aria-modal="true" aria-label="Dettaglio prodotto">
            <button type="button" class="shop-modal__close" data-close-modal aria-label="Chiudi"><i class="fas fa-xmark"></i></button>
            <div class="shop-modal__content" data-modal-content></div>
        </article>
    </div>

    <div class="shop-toast" data-shop-toast></div>

    <?php include '../includes/scroll_indicator.php'; ?>
    <?php include '../includes/footer-en.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>