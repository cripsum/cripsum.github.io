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
    ['id' => 'spinjitzu', 'name' => 'Spinjitzu Tutorial', 'image' => '../img/jayquadrato.png', 'alt' => 'Spinjitzu Tutorial', 'description' => 'Learn the famous Ninjago move.', 'status' => 'available', 'badge' => 'External', 'link' => 'https://payhip.com/b/m0kaT'],
    ['id' => 'yoshukai', 'name' => 'Yoshukai Course', 'image' => '../img/chinese-essay-2.jpg', 'alt' => 'Yoshukai Course', 'description' => 'Free for a limited time.', 'status' => 'available', 'badge' => 'Free', 'link' => 'download/yoshukai'],
    ['id' => 'fortnite', 'name' => 'Fortnite Hacks', 'image' => '../img/fortnitehack.jpg', 'alt' => 'Fortnite Hacks', 'description' => 'ez win.', 'status' => 'available', 'badge' => 'Download', 'link' => 'download/fortnite'],
    ['id' => 'osu', 'name' => 'Osu!', 'image' => '../img/osu.jpg', 'alt' => 'Osu!', 'description' => 'hossu - the rhythm game for dummies.', 'status' => 'available', 'badge' => 'Game', 'link' => 'download/osu'],
    ['id' => 'vanzakart', 'name' => 'VanzaKart Launcher', 'image' => '../img/vklogo.png', 'alt' => 'VanzaKart Launcher', 'description' => 'Download the VanzaKart launcher to play the coolest Mario Kart Wii mod ever', 'status' => 'available', 'badge' => 'External', 'link' => 'https://web.sitodaking.it/'],
    ['id' => 'soon-2', 'name' => 'Coming Soon', 'image' => '../img/comingsoon.jpg', 'alt' => 'Coming Soon', 'description' => 'Coming Soon', 'status' => 'soon', 'badge' => 'Coming soon', 'link' => ''],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Download</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Cripsum™ Download Center.">
    <link rel="stylesheet" href="/assets/shop/shop.css?v=2.4">
    <script src="/assets/shop/shop.js?v=2.4" defer></script>
</head>

<body class="shop-page shop-theme-download" data-shop-page="download" data-favorites="0">
    <?php include '../includes/navbar.php'; ?>


    <main class="shop-shell">
        <section class="shop-hero shop-reveal">
            <div class="shop-hero__content">
                <span class="shop-kicker">Download</span>
                <h1>Download Center</h1>
                <p>Download and enjoy</p>
                <div class="shop-hero__actions">
                    <a class="shop-btn shop-btn--primary" href="#download-list">Go to downloads</a>
                    <a class="shop-btn shop-btn--ghost" href="https://discord.gg/XdheJHVURw" target="_blank" rel="noopener">Discord</a>
                </div>
            </div>
        </section>

        <section class="shop-note shop-reveal">
            <i class="fas fa-circle-info"></i>
            <div>
                <strong>Before downloading</strong>
                <span>Some links lead to internal pages, others to external sites. In any case, the Cripsum™ staff ensures that downloads are always safe.</span>
            </div>
        </section>

        <section class="shop-panel shop-reveal" id="download-list">
            <div class="shop-toolbar">
                <label class="shop-search">
                    <i class="fas fa-search"></i>
                    <input type="search" data-shop-search placeholder="Search downloads">
                </label>

                <div class="shop-filters" aria-label="Filtri download">
                    <button type="button" class="shop-filter is-active" data-category="all">All</button>
                    <button type="button" class="shop-filter" data-category="available">Available</button>
                    <button type="button" class="shop-filter" data-category="soon">Coming soon</button>
                </div>

                <div class="shop-custom-select" data-shop-custom-select>
                    <select class="shop-select shop-native-select" data-shop-sort aria-label="Sort downloads" tabindex="-1" aria-hidden="true">
                        <option value="default">Original order</option>
                        <option value="name-asc">Name A-Z</option>
                    </select>

                    <button type="button" class="shop-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                        <span class="shop-select-current">Original order</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>

                    <div class="shop-select-menu" role="listbox" aria-label="Sort downloads options">
                        <button type="button" data-value="default">
                            <strong>Original order</strong>
                            <span>Base</span>
                        </button>
                        <button type="button" data-value="name-asc">
                            <strong>Name A-Z</strong>
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
                                <strong><?php echo $item['status'] === 'soon' ? 'Not available' : 'Available'; ?></strong>
                                <div class="shop-card__actions">
                                    <button type="button" class="shop-icon-btn" data-open-detail title="Details"><i class="fas fa-eye"></i></button>
                                    <?php if ($item['status'] === 'available'): ?>
                                        <button type="button" class="shop-icon-btn" data-copy-link title="Copy link"><i class="fas fa-link"></i></button>
                                        <a class="shop-btn shop-btn--small" href="<?php echo shop_h($item['link']); ?>">Download</a>
                                    <?php else: ?>
                                        <span class="shop-btn shop-btn--small is-disabled">Coming soon</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="shop-empty" data-shop-empty hidden>
                <i class="fas fa-folder-open"></i>
                <strong>No downloads found</strong>
                <span>Try changing the filter or search.</span>
            </div>
        </section>

        <section class="shop-faq shop-reveal">
            <h2>Frequently Asked Questions (FAQ)</h2>
            <details>
                <summary>How do downloads work?</summary>
                <p>I don't know, you click and it downloads, I guess.</p>
            </details>
            <details>
                <summary>Do I need an account?</summary>
                <p>No.</p>
            </details>
            <details>
                <summary>Are downloads safe?</summary>
                <p>Yes, the Cripsum™ staff verifies that all downloads are safe and free of malware.</p>
            </details>
            <details>
                <summary>Why are some downloads not available?</summary>
                <p>Content marked as "Coming soon" will be available in the future. Check back for updates!</p>
            </details>
            <details>
                <summary>Can I suggest new content to download?</summary>
                <p>Sure! Join our <a href="https://discord.gg/XdheJHVURw" target="_blank" rel="noopener">Discord server</a> and let us know what you'd like to see.</p>
            </details>
        </section>
    </main>

    <div class="shop-modal" data-shop-modal hidden>
        <div class="shop-modal__backdrop" data-close-modal></div>
        <article class="shop-modal__panel" role="dialog" aria-modal="true" aria-label="Download details">
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