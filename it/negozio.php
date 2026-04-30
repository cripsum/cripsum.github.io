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
    ['id' => 'rtx-4090', 'name' => 'RTX 4090', 'price' => 2499.99, 'image' => '../img/4090.jpg', 'alt' => 'RTX 4090', 'description' => 'Troppo potente anche per il gamings.', 'category' => 'tech', 'badge' => 'Overkill', 'link' => 'checkout'],
    ['id' => 'iphone-20', 'name' => 'Iphone 20', 'price' => 2179.99, 'image' => '../img/iphone20.jpg', 'alt' => 'Iphone 20', 'description' => 'Il futuro. Ottimo telefono, peccato per l’OS.', 'category' => 'tech', 'badge' => 'Future', 'link' => 'checkout'],
    ['id' => 'tua-madre', 'name' => 'tua madre', 'price' => 2.49, 'image' => '../img/indica.jpg', 'alt' => 'tua madre', 'description' => 'Se acquistate entro il 2025: sconto del 40%.', 'category' => 'meme', 'badge' => 'Sconto 40%', 'link' => 'checkout'],
    ['id' => 'samsung-s30', 'name' => 'Samsung galaxy s30', 'price' => 1599.99, 'image' => '../img/s30.jpg', 'alt' => 'Samsung galaxy s30', 'description' => '16GB RAM, 1TB memoria, batteria 7000mAh e zoom x100.', 'category' => 'tech', 'badge' => 'Specs', 'link' => 'checkout'],
    ['id' => 'ps6', 'name' => 'Ps6', 'price' => 849.49, 'image' => '../img/ps6.jpg', 'alt' => 'Ps6', 'description' => 'Per giocare a GTA 5 remastered in 8K 120fps.', 'category' => 'gaming', 'badge' => 'Next gen', 'link' => 'checkout'],
    ['id' => 'monitor-540hz', 'name' => 'Monitor 540hz', 'price' => 949.69, 'image' => '../img/monitor540.jpg', 'alt' => 'Monitor 540hz', 'description' => 'Fluidità estrema. Completamente inutile.', 'category' => 'gaming', 'badge' => '540hz', 'link' => 'checkout'],
    ['id' => 'renegade-raider', 'name' => 'renegade raider', 'price' => 429.99, 'image' => '../img/renegade.jpg', 'alt' => 'renegade raider', 'description' => 'La skin più OG di tutte, ma anche la più costosa.', 'category' => 'gaming', 'badge' => 'OG', 'link' => 'checkout'],
    ['id' => 'tavoletta-grafica', 'name' => 'Tavoletta grafica', 'price' => 119.89, 'image' => '../img/tavoletta.jpg', 'alt' => 'Tavoletta grafica', 'description' => 'Accessorio per osu.', 'category' => 'osu', 'badge' => 'osu', 'link' => 'checkout'],
    ['id' => 'tastiera-osu', 'name' => 'tastiera per osu', 'price' => 44.99, 'image' => '../img/tastiera2tasti.jpg', 'alt' => 'tastiera per osu', 'description' => 'Due tasti, zero scuse.', 'category' => 'osu', 'badge' => 'osu', 'link' => 'checkout'],
];
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Negozio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Shop statico di Cripsum™. Prodotti seri il giusto.">
    <link rel="stylesheet" href="/assets/shop/shop.css?v=2.3-hard-click-fix">
    <script src="/assets/shop/shop.js?v=2.3-hard-click-fix" defer></script>
</head>

<body class="shop-page shop-theme-store" data-shop-page="negozio" data-favorites="1">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <main class="shop-shell">
        <section class="shop-hero shop-reveal">
            <div class="shop-hero__content">
                <span class="shop-kicker">Negozio da fichi</span>
                <h1>Negozio Cripsum™</h1>
                <p>Prodotti che solo i più fichi oserebbero comprare.</p>
                <div class="shop-hero__actions">
                    <a class="shop-btn shop-btn--primary" href="#prodotti">Guarda prodotti</a>
                    <button class="shop-btn shop-btn--ghost" type="button" data-show-favorites>Preferiti</button>
                </div>
            </div>
        </section>

        <section class="shop-panel shop-reveal" id="prodotti">
            <div class="shop-toolbar">
                <label class="shop-search">
                    <i class="fas fa-search"></i>
                    <input type="search" data-shop-search placeholder="Cerca prodotto">
                </label>

                <div class="shop-filters" aria-label="Filtri negozio">
                    <button type="button" class="shop-filter is-active" data-category="all">Tutti</button>
                    <button type="button" class="shop-filter" data-category="tech">Tech</button>
                    <button type="button" class="shop-filter" data-category="gaming">Gaming</button>
                    <button type="button" class="shop-filter" data-category="osu">osu</button>
                    <button type="button" class="shop-filter" data-category="meme">Meme</button>
                </div>

                <div class="shop-custom-select" data-shop-custom-select>
                    <select class="shop-select shop-native-select" data-shop-sort aria-label="Ordina prodotti" tabindex="-1" aria-hidden="true">
                        <option value="default">Ordine originale</option>
                        <option value="name-asc">Nome A-Z</option>
                        <option value="price-asc">Prezzo crescente</option>
                        <option value="price-desc">Prezzo decrescente</option>
                    </select>

                    <button type="button" class="shop-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                        <span class="shop-select-current">Ordine originale</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>

                    <div class="shop-select-menu" role="listbox" aria-label="Ordina prodotti">
                        <button type="button" data-value="default">
                            <strong>Ordine originale</strong>
                            <span>Base</span>
                        </button>
                        <button type="button" data-value="name-asc">
                            <strong>Nome A-Z</strong>
                            <span>A-Z</span>
                        </button>
                        <button type="button" data-value="price-asc">
                            <strong>Prezzo crescente</strong>
                            <span>€ ↑</span>
                        </button>
                        <button type="button" data-value="price-desc">
                            <strong>Prezzo decrescente</strong>
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
                            <button type="button" class="shop-fav" data-favorite-toggle aria-label="Salva preferito">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>
                        <div class="shop-card__body">
                            <h2><?php echo shop_h($product['name']); ?></h2>
                            <p><?php echo shop_h($product['description']); ?></p>
                            <div class="shop-card__footer">
                                <strong><?php echo number_format((float)$product['price'], 2, ',', '.'); ?>€</strong>
                                <div class="shop-card__actions">
                                    <button type="button" class="shop-icon-btn" data-open-detail title="Dettagli"><i class="fas fa-eye"></i></button>
                                    <a class="shop-btn shop-btn--small" href="<?php echo shop_h($product['link']); ?>">Acquista</a>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="shop-empty" data-shop-empty hidden>
                <i class="fas fa-box-open"></i>
                <strong>Nessun prodotto trovato</strong>
                <span>Prova a cambiare ricerca o filtro.</span>
            </div>
        </section>

        <section class="shop-faq shop-reveal">
            <h2>Domande frequenti (FAQ)</h2>
            <details>
                <summary>Come fate ad avere prodotti che ancora non esistono in commercio?</summary>
                <p>Abbiamo un team di sviluppo che lavora su prototipi e concept per prodotti futuri. in pratica me le ha date il mio cuggino con lo zio in america</p>
            </details>
            <details>
                <summary>In che senso vendete mia madre?</summary>
                <p>In senso letterale, stiamo cercando di espandere la nostra linea di prodotti per includere articoli più "personali".</p>
            </details>
            <details>
                <summary>Quali metodi di pagamento accettate?</summary>
                <p>Accettiamo pagamento in natura.</p>
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
    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>