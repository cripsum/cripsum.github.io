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
    ['id' => 'tshirt-big-logo', 'name' => 'T-Shirt simonetussi.ph', 'variant' => 'Big logo', 'price' => 19.99, 'image' => '../img/magliag.jpg', 'alt' => 'T-Shirt big logo', 'description' => 'Logo grande per massima visibilità.', 'category' => 'maglie', 'badge' => 'Drop', 'link' => 'merch-checkout'],
    ['id' => 'tshirt-small-logo', 'name' => 'T-Shirt simonetussi.ph', 'variant' => 'Small logo', 'price' => 19.99, 'image' => '../img/magliap.jpg', 'alt' => 'T-Shirt small logo', 'description' => 'Logo piccolo per chi ama la discrezione.', 'category' => 'maglie', 'badge' => 'Minimal', 'link' => 'merch-checkout'],
    ['id' => 'felpa-big-logo', 'name' => 'Felpa simonetussi.ph', 'variant' => 'Big logo', 'price' => 39.99, 'image' => '../img/felpag.jpg', 'alt' => 'Felpa big logo', 'description' => 'Calda e comoda, con logo grande.', 'category' => 'felpe', 'badge' => 'Premium', 'link' => 'merch-checkout'],
    ['id' => 'felpa-small-logo', 'name' => 'Felpa simonetussi.ph', 'variant' => 'Small logo', 'price' => 39.99, 'image' => '../img/felpap.jpg', 'alt' => 'Felpa small logo', 'description' => 'Stile più pulito, sempre riconoscibile.', 'category' => 'felpe', 'badge' => 'Clean', 'link' => 'merch-checkout'],
    ['id' => 'pantaloncini', 'name' => 'Pantaloncini simonetussi.ph', 'variant' => '', 'price' => 23.99, 'image' => '../img/pantaloncini.jpg', 'alt' => 'Pantaloncini', 'description' => 'Comodi per estate e sport.', 'category' => 'abbigliamento', 'badge' => 'Summer', 'link' => 'merch-checkout'],
    ['id' => 'calzini', 'name' => 'Calzini simonetussi.ph', 'variant' => '', 'price' => 5.99, 'image' => '../img/calze.jpg', 'alt' => 'Calzini', 'description' => 'Anche i piedi meritano stile.', 'category' => 'accessori', 'badge' => 'Cheap', 'link' => 'merch-checkout'],
    ['id' => 'boxer', 'name' => 'Boxer simonetussi.ph', 'variant' => '', 'price' => 149.99, 'image' => '../img/boxers.jpg', 'alt' => 'Boxer', 'description' => 'Lusso estremo per veri intenditori.', 'category' => 'abbigliamento', 'badge' => 'Luxury', 'link' => 'merch-checkout'],
    ['id' => 'slip', 'name' => 'Slip simonetussi.ph', 'variant' => '', 'price' => 249.99, 'image' => '../img/mutandinesexi.jpg', 'alt' => 'Slip', 'description' => 'Edizione limitata, pezzo da collezione.', 'category' => 'abbigliamento', 'badge' => 'Limited', 'link' => 'merch-checkout'],
    ['id' => 'cappellino', 'name' => 'Cappellino simonetussi.ph', 'variant' => '', 'price' => 7.99, 'image' => '../img/cappellino.jpg', 'alt' => 'Cappellino', 'description' => 'Protezione solare con stile.', 'category' => 'accessori', 'badge' => 'Classic', 'link' => 'merch-checkout'],
    ['id' => 'occhiali-sole', 'name' => 'Occhiali da sole simonetussi.ph', 'variant' => '', 'price' => 8.99, 'image' => '../img/occhialis.jpg', 'alt' => 'Occhiali da sole', 'description' => 'Look da vero influencer.', 'category' => 'accessori', 'badge' => 'Drip', 'link' => 'merch-checkout'],
    ['id' => 'occhiali-vista', 'name' => 'Occhiali da vista simonetussi.ph', 'variant' => '', 'price' => 35.99, 'image' => '../img/occhialiv.jpg', 'alt' => 'Occhiali da vista', 'description' => 'Vedi meglio il mondo col logo.', 'category' => 'accessori', 'badge' => 'Vision', 'link' => 'merch-checkout'],
    ['id' => 'tostapane', 'name' => 'Tostapane simonetussi.ph', 'variant' => '', 'price' => 79.99, 'image' => '../img/tostapane.jpg', 'alt' => 'Tostapane', 'description' => 'Toasta il pane con stile.', 'category' => 'altro', 'badge' => 'Peak', 'link' => 'merch-checkout'],
];
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Merch</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Merch statico di Cripsum™ / simonetussi.ph.">
    <link rel="stylesheet" href="/assets/shop/shop.css?v=2.3-hard-click-fix">
    <script src="/assets/shop/shop.js?v=2.3-hard-click-fix" defer></script>
</head>

<body class="shop-page shop-theme-merch" data-shop-page="merch" data-favorites="1">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <main class="shop-shell">
        <section class="shop-hero shop-reveal">
            <div class="shop-hero__content">
                <span class="shop-kicker">Merch drop</span>
                <h1>NEW MERCH OUT NOW</h1>
                <p>Il merch simonetussi.ph. il fotografo e psicologo più fico di sempre.</p>
                <div class="shop-hero__actions">
                    <a class="shop-btn shop-btn--primary" href="#prodotti">Guarda prodotti</a>
                    <button class="shop-btn shop-btn--ghost" type="button" data-show-favorites>Preferiti</button>
                </div>
            </div>
            <div class="shop-hero__emoji" aria-hidden="true">🤑🐦📸</div>
        </section>

        <section class="shop-panel shop-reveal" id="prodotti">
            <div class="shop-toolbar">
                <label class="shop-search">
                    <i class="fas fa-search"></i>
                    <input type="search" data-shop-search placeholder="Cerca prodotto">
                </label>

                <div class="shop-filters" aria-label="Filtri merch">
                    <button type="button" class="shop-filter is-active" data-category="all">Tutti</button>
                    <button type="button" class="shop-filter" data-category="maglie">Maglie</button>
                    <button type="button" class="shop-filter" data-category="felpe">Felpe</button>
                    <button type="button" class="shop-filter" data-category="accessori">Accessori</button>
                    <button type="button" class="shop-filter" data-category="abbigliamento">Abbigliamento</button>
                    <button type="button" class="shop-filter" data-category="altro">Altro</button>
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
                            <button type="button" class="shop-fav" data-favorite-toggle aria-label="Salva preferito">
                                <i class="far fa-heart"></i>
                            </button>
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
                <summary>Quando uscirà il secondo drop del merch di Simone Tussi?</summary>
                <p>Non abbiamo ancora una data ufficiale, ma ti consigliamo di seguire i nostri canali social per rimanere aggiornato sulle novità! (mai)</p>
            </details>
            <details>
                <summary>Come vengono prodotti i capi?</summary>
                <p>Il nostro merch è prodotto in 100% poliestere, garantendo discomfort e non-durabilità. Ogni pezzo è realizzato con cura da un ragazzino filippino per offrire il massimo stile e resistenza.</p>
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