<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

function shop_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$downloads = [
    ['id' => 'spinjitzu', 'name' => 'Tutorial Spinjitzu', 'image' => '../img/jayquadrato.png', 'alt' => 'Tutorial Spinjitzu', 'description' => 'Impara la famosa mossa di Ninjago.', 'status' => 'available', 'badge' => 'Esterno', 'link' => 'https://payhip.com/b/m0kaT'],
    ['id' => 'yoshukai', 'name' => 'Corso Yoshukai', 'image' => '../img/chinese-essay-2.jpg', 'alt' => 'Corso Yoshukai', 'description' => 'Gratis ancora per poco.', 'status' => 'available', 'badge' => 'Gratis', 'link' => 'download/yoshukai'],
    ['id' => 'fortnite', 'name' => 'Fortnite Hacks', 'image' => '../img/fortnitehack.jpg', 'alt' => 'Fortnite Hacks', 'description' => 'ez win.', 'status' => 'available', 'badge' => 'Download', 'link' => 'download/fortnite'],
    ['id' => 'osu', 'name' => 'Osu!', 'image' => '../img/osu.jpg', 'alt' => 'Osu!', 'description' => 'hossu - il gioco ritmico per scemotti.', 'status' => 'available', 'badge' => 'Game', 'link' => 'download/osu'],
    ['id' => 'vanzakart', 'name' => 'VanzaKart Launcher', 'image' => '../img/vklogo.png', 'alt' => 'VanzaKart Launcher', 'description' => 'Scarica il launcher di VanzaKart per giocare alla mod di Mario Kart Wii più fica di sempre', 'status' => 'available', 'badge' => 'Esterno', 'link' => 'https://web.sitodaking.it/'],
    ['id' => 'soon-2', 'name' => 'Coming Soon', 'image' => '../img/comingsoon.jpg', 'alt' => 'Coming Soon', 'description' => 'Prossimamente.', 'status' => 'soon', 'badge' => 'Coming soon', 'link' => ''],
];
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Download</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Download statici di Cripsum™.">
    <link rel="stylesheet" href="/assets/shop/shop.css?v=2.4">
    <script src="/assets/shop/shop.js?v=2.4" defer></script>
</head>

<body class="shop-page shop-theme-download" data-shop-page="download" data-favorites="0">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <main class="shop-shell">
        <section class="shop-hero shop-reveal">
            <div class="shop-hero__content">
                <span class="shop-kicker">Download</span>
                <h1>Download Center</h1>
                <p>suca e scarica</p>
                <div class="shop-hero__actions">
                    <a class="shop-btn shop-btn--primary" href="#download-list">Vai ai download</a>
                    <a class="shop-btn shop-btn--ghost" href="https://discord.gg/XdheJHVURw" target="_blank" rel="noopener">Discord</a>
                </div>
            </div>
        </section>

        <section class="shop-note shop-reveal">
            <i class="fas fa-circle-info"></i>
            <div>
                <strong>Prima di scaricare</strong>
                <span>Alcuni link portano a pagine interne, altri a siti esterni. In ogni caso lo staff di Cripsum™ assicura che i download siano sempre sicuri.</span>
            </div>
        </section>

        <section class="shop-panel shop-reveal" id="download-list">
            <div class="shop-toolbar">
                <label class="shop-search">
                    <i class="fas fa-search"></i>
                    <input type="search" data-shop-search placeholder="Cerca download">
                </label>

                <div class="shop-filters" aria-label="Filtri download">
                    <button type="button" class="shop-filter is-active" data-category="all">Tutti</button>
                    <button type="button" class="shop-filter" data-category="available">Disponibili</button>
                    <button type="button" class="shop-filter" data-category="soon">Coming soon</button>
                </div>

                <div class="shop-custom-select" data-shop-custom-select>
                    <select class="shop-select shop-native-select" data-shop-sort aria-label="Ordina download" tabindex="-1" aria-hidden="true">
                        <option value="default">Ordine originale</option>
                        <option value="name-asc">Nome A-Z</option>
                    </select>

                    <button type="button" class="shop-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                        <span class="shop-select-current">Ordine originale</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>

                    <div class="shop-select-menu" role="listbox" aria-label="Ordina download">
                        <button type="button" data-value="default">
                            <strong>Ordine originale</strong>
                            <span>Base</span>
                        </button>
                        <button type="button" data-value="name-asc">
                            <strong>Nome A-Z</strong>
                            <span>A-Z</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="shop-grid shop-grid--downloads" data-shop-grid>
                <?php foreach ($downloads as $item): ?>
                    <article class="shop-card shop-card--download shop-reveal <?php echo $item['status'] === 'soon' ? 'is-soon' : ''; ?>"
                        id="download-<?php echo shop_h($item['id']); ?>"
                        data-product-card
                        data-id="<?php echo shop_h($item['id']); ?>"
                        data-name="<?php echo shop_h($item['name']); ?>"
                        data-category="<?php echo shop_h($item['status']); ?>"
                        data-price="0"
                        data-description="<?php echo shop_h($item['description']); ?>"
                        data-image="<?php echo shop_h($item['image']); ?>"
                        data-link="<?php echo shop_h($item['link']); ?>"
                        data-badge="<?php echo shop_h($item['badge']); ?>">
                        <div class="shop-card__media">
                            <img src="<?php echo shop_h($item['image']); ?>" alt="<?php echo shop_h($item['alt']); ?>" loading="lazy">
                            <span class="shop-badge"><?php echo shop_h($item['badge']); ?></span>
                        </div>
                        <div class="shop-card__body">
                            <h2><?php echo shop_h($item['name']); ?></h2>
                            <p><?php echo shop_h($item['description']); ?></p>
                            <div class="shop-card__footer">
                                <strong><?php echo $item['status'] === 'soon' ? 'Non disponibile' : 'Disponibile'; ?></strong>
                                <div class="shop-card__actions">
                                    <button type="button" class="shop-icon-btn" data-open-detail title="Dettagli"><i class="fas fa-eye"></i></button>
                                    <?php if ($item['status'] === 'available'): ?>
                                        <button type="button" class="shop-icon-btn" data-copy-link title="Copia link"><i class="fas fa-link"></i></button>
                                        <a class="shop-btn shop-btn--small" href="<?php echo shop_h($item['link']); ?>">Scarica</a>
                                    <?php else: ?>
                                        <span class="shop-btn shop-btn--small is-disabled">Presto</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="shop-empty" data-shop-empty hidden>
                <i class="fas fa-folder-open"></i>
                <strong>Nessun download trovato</strong>
                <span>Prova a cambiare filtro o ricerca.</span>
            </div>
        </section>

        <section class="shop-faq shop-reveal">
            <h2>Domande frequenti (FAQ)</h2>
            <details>
                <summary>Come funzionano i download?</summary>
                <p>boh tipo clicchi e ti scarica fra non lo so tipo</p>
            </details>
            <details>
                <summary>Serve un account?</summary>
                <p>No.</p>
            </details>
            <details>
                <summary>I download sono sicuri?</summary>
                <p>Sì, lo staff di Cripsum™ verifica che tutti i download siano sicuri e privi di malware.</p>
            </details>
            <details>
                <summary>Perché alcuni download non sono disponibili?</summary>
                <p>I contenuti contrassegnati come "Coming soon" saranno disponibili prossimamente. Torna a visitarci per novità!</p>
            </details>
            <details>
                <summary>Posso suggerire nuovi contenuti da scaricare?</summary>
                <p>Certo! Unisciti al nostro <a href="https://discord.gg/XdheJHVURw" target="_blank" rel="noopener">server Discord</a> e facci sapere cosa vorresti vedere.</p>
            </details>
        </section>
    </main>

    <div class="shop-modal" data-shop-modal hidden>
        <div class="shop-modal__backdrop" data-close-modal></div>
        <article class="shop-modal__panel" role="dialog" aria-modal="true" aria-label="Dettaglio download">
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