<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "You must be logged in to view your inventory.";

    header('Location: accedi');
    exit();
}

function inventario_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$ogDescription = 'Your Character inventory on Cripsum™.';
$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/it/inventario'), '#');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Inventory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?php echo inventario_h($ogDescription); ?>">
    <meta property="og:site_name" content="Cripsum™">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Character Inventory - Cripsum™">
    <meta property="og:description" content="<?php echo inventario_h($ogDescription); ?>">
    <meta property="og:image" content="https://cripsum.com/img/waguri.jpeg">
    <meta property="og:url" content="<?php echo inventario_h($ogUrl); ?>">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="stylesheet" href="/assets/inventario/inventario.css?v=2.9.7">
    <script src="/assets/inventario/inventario.js?v=3.0.7" defer></script>
</head>

<body class="inv-page">
    <?php include '../includes/navbar.php'; ?>


    <div class="inv-bg" aria-hidden="true">
        <span class="inv-orb inv-orb--one"></span>
        <span class="inv-orb inv-orb--two"></span>
        <span class="inv-grid-bg"></span>
    </div>

    <main class="inv-shell">
        <section class="inv-hero inv-reveal">
            <div class="inv-hero__copy">
                <h1>Inventory</h1>
                <p>Characters found in loot boxes, sorted by rarity.</p>
            </div>

            <a href="lootbox" class="inv-btn inv-btn--primary">
                <i class="fa-solid fa-box-open"></i>
                <span>Return to Loot Box</span>
            </a>
        </section>

        <section class="inv-stats inv-reveal" aria-label="Inventory Statistics">
            <article>
                <strong id="casseAperteNumber">0</strong>
                <span>Opened Boxes</span>
            </article>
            <article>
                <strong id="foundCharacters">0</strong>
                <span>Found Characters</span>
            </article>
            <article>
                <strong id="totalCharactersNum">0</strong>
                <span>Total Characters</span>
            </article>
            <article>
                <strong id="completionRate">0%</strong>
                <span>Completion Rate</span>
            </article>
        </section>

        <section class="inv-controls inv-reveal">
            <label class="inv-search">
                <i class="fa-solid fa-search"></i>
                <input type="search" id="inventorySearch" placeholder="Search characters..." autocomplete="off">
            </label>

            <div class="inv-custom-select" data-inv-custom-select>
                <select id="rarityFilter" class="inv-select inv-native-select" aria-label="Filtra per rarità" tabindex="-1" aria-hidden="true">
                    <option value="all">All Rarities</option>
                    <option value="comune">Common</option>
                    <option value="raro">Rare</option>
                    <option value="epico">Epic</option>
                    <option value="leggendario">Legendary</option>
                    <option value="speciale">Special</option>
                    <option value="segreto">Secret</option>
                    <option value="segreto_limited">Secret Limited</option>
                    <option value="theone">The One</option>
                </select>

                <button type="button" class="inv-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                    <span class="inv-select-current">All Rarities</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </button>

                <div class="inv-select-menu" role="listbox" aria-label="Filtra per rarità">
                    <button type="button" data-value="all"><strong>All Rarities</strong></button>
                    <button type="button" data-value="comune"><strong>Common</strong></button>
                    <button type="button" data-value="raro"><strong>Rare</strong></button>
                    <button type="button" data-value="epico"><strong>Epic</strong></button>
                    <button type="button" data-value="leggendario"><strong>Legendary</strong></button>
                    <button type="button" data-value="speciale"><strong>Special</strong></button>
                    <button type="button" data-value="segreto"><strong>Secret</strong></button>
                    <button type="button" data-value="segreto_limited"><strong>Secret Limited</strong></button>
                    <button type="button" data-value="theone"><strong>The One</strong></button>
                </div>
            </div>

            <div class="inv-custom-select" data-inv-custom-select>
                <select id="statusFilter" class="inv-select inv-native-select" aria-label="Filtra per stato" tabindex="-1" aria-hidden="true">
                    <option value="all">All</option>
                    <option value="owned">Owned</option>
                    <option value="missing">Missing</option>
                    <option value="duplicates">Duplicates</option>
                </select>

                <button type="button" class="inv-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                    <span class="inv-select-current">All</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </button>

                <div class="inv-select-menu" role="listbox" aria-label="Filtra per stato">
                    <button type="button" data-value="all"><strong>All</strong></button>
                    <button type="button" data-value="owned"><strong>Owned</strong></button>
                    <button type="button" data-value="missing"><strong>Missing</strong></button>
                    <button type="button" data-value="duplicates"><strong>Duplicates</strong></button>
                </div>
            </div>

            <div class="inv-custom-select" data-inv-custom-select>
                <select id="inventorySort" class="inv-select inv-native-select" aria-label="Ordina inventario" tabindex="-1" aria-hidden="true">
                    <option value="default">Original order</option>
                    <option value="name">Name</option>
                    <option value="quantity-desc">Most quantity</option>
                    <option value="quantity-asc">Least quantity</option>
                </select>

                <button type="button" class="inv-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                    <span class="inv-select-current">Original order</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </button>

                <div class="inv-select-menu" role="listbox" aria-label="Ordina inventario">
                    <button type="button" data-value="default"><strong>Original order</strong></button>
                    <button type="button" data-value="name"><strong>Name</strong><span>A-Z</span></button>
                    <button type="button" data-value="quantity-desc"><strong>Most quantity</strong><span>x ↓</span></button>
                    <button type="button" data-value="quantity-asc"><strong>Least quantity</strong><span>x ↑</span></button>
                </div>
            </div>

            <button type="button" class="inv-btn inv-btn--soft" id="resetInventoryFilters">
                <i class="fa-solid fa-rotate-left"></i>
                <span>Reset</span>
            </button>
        </section>

        <section class="inv-visible-count inv-reveal">
            <span id="visibleCount">0 results</span>
        </section>

        <section id="inventoryLoading" class="inv-loading" aria-live="polite">
            <div class="inv-loader">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <p>Loading inventory...</p>
        </section>

        <section id="inventoryError" class="inv-empty" hidden>
            <i class="fa-solid fa-triangle-exclamation"></i>
            <strong>Unable to load inventory</strong>
            <span>Please try again later.</span>
        </section>

        <section id="inventoryEmpty" class="inv-empty" hidden>
            <i class="fa-solid fa-magnifying-glass"></i>
            <strong>No characters found</strong>
            <span>Change search or filters.</span>
        </section>

        <section id="inventario" class="inventory-grid" hidden></section>
    </main>

    <div class="inv-modal" id="characterModal" hidden>
        <div class="inv-modal__backdrop" data-close-character-modal></div>
        <article class="inv-modal__panel" role="dialog" aria-modal="true" aria-labelledby="characterModalTitle">
            <button type="button" class="inv-modal__close" data-close-character-modal aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <div id="characterModalContent"></div>
        </article>
    </div>

    <?php include '../includes/scroll_indicator.php'; ?>
    <?php include '../includes/footer-en.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>