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
    $_SESSION['login_message'] = "Per accedere all'inventario devi essere loggato";

    header('Location: accedi');
    exit();
}

function inventario_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$ogDescription = 'Il tuo inventario personaggi su Cripsum™.';
$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/it/inventario'), '#');
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Inventario</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?php echo inventario_h($ogDescription); ?>">
    <meta property="og:site_name" content="Cripsum™">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Inventario Personaggi - Cripsum™">
    <meta property="og:description" content="<?php echo inventario_h($ogDescription); ?>">
    <meta property="og:image" content="https://cripsum.com/img/waguri.jpeg">
    <meta property="og:url" content="<?php echo inventario_h($ogUrl); ?>">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="stylesheet" href="/assets/inventario/inventario.css?v=2.8">
    <script src="/assets/inventario/inventario.js?v=2.8" defer></script>
</head>

<body class="inv-page">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="inv-bg" aria-hidden="true">
        <span class="inv-orb inv-orb--one"></span>
        <span class="inv-orb inv-orb--two"></span>
        <span class="inv-grid-bg"></span>
    </div>

    <main class="inv-shell">
        <section class="inv-hero inv-reveal">
            <div class="inv-hero__copy">
                <h1>Inventario</h1>
                <p>I personaggi trovati nelle lootbox, ordinati per rarità.</p>
            </div>

            <a href="lootbox" class="inv-btn inv-btn--primary">
                <i class="fas fa-box-open"></i>
                <span>Torna alla lootbox</span>
            </a>
        </section>

        <section class="inv-stats inv-reveal" aria-label="Statistiche inventario">
            <article>
                <strong id="casseAperteNumber">0</strong>
                <span>Casse aperte</span>
            </article>
            <article>
                <strong id="foundCharacters">0</strong>
                <span>Personaggi trovati</span>
            </article>
            <article>
                <strong id="totalCharactersNum">0</strong>
                <span>Personaggi totali</span>
            </article>
            <article>
                <strong id="completionRate">0%</strong>
                <span>Completamento</span>
            </article>
        </section>

        <section class="inv-controls inv-reveal">
            <label class="inv-search">
                <i class="fas fa-search"></i>
                <input type="search" id="inventorySearch" placeholder="Cerca personaggi..." autocomplete="off">
            </label>

            <div class="inv-custom-select" data-inv-custom-select>
                <select id="rarityFilter" class="inv-select inv-native-select" aria-label="Filtra per rarità" tabindex="-1" aria-hidden="true">
                    <option value="all">Tutte le rarità</option>
                    <option value="comune">Comune</option>
                    <option value="raro">Raro</option>
                    <option value="epico">Epico</option>
                    <option value="leggendario">Leggendario</option>
                    <option value="speciale">Speciale</option>
                    <option value="segreto">Segreto</option>
                    <option value="theone">The One</option>
                </select>

                <button type="button" class="inv-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                    <span class="inv-select-current">Tutte le rarità</span>
                    <i class="fas fa-chevron-down"></i>
                </button>

                <div class="inv-select-menu" role="listbox" aria-label="Filtra per rarità">
                    <button type="button" data-value="all"><strong>Tutte le rarità</strong><span>All</span></button>
                    <button type="button" data-value="comune"><strong>Comune</strong><span>Com</span></button>
                    <button type="button" data-value="raro"><strong>Raro</strong><span>Rar</span></button>
                    <button type="button" data-value="epico"><strong>Epico</strong><span>Epi</span></button>
                    <button type="button" data-value="leggendario"><strong>Leggendario</strong><span>Leg</span></button>
                    <button type="button" data-value="speciale"><strong>Speciale</strong><span>Spe</span></button>
                    <button type="button" data-value="segreto"><strong>Segreto</strong><span>Sec</span></button>
                    <button type="button" data-value="theone"><strong>The One</strong><span>One</span></button>
                </div>
            </div>

            <div class="inv-custom-select" data-inv-custom-select>
                <select id="statusFilter" class="inv-select inv-native-select" aria-label="Filtra per stato" tabindex="-1" aria-hidden="true">
                    <option value="all">Tutti</option>
                    <option value="owned">Posseduti</option>
                    <option value="missing">Mancanti</option>
                    <option value="duplicates">Duplicati</option>
                </select>

                <button type="button" class="inv-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                    <span class="inv-select-current">Tutti</span>
                    <i class="fas fa-chevron-down"></i>
                </button>

                <div class="inv-select-menu" role="listbox" aria-label="Filtra per stato">
                    <button type="button" data-value="all"><strong>Tutti</strong></button>
                    <button type="button" data-value="owned"><strong>Posseduti</strong></button>
                    <button type="button" data-value="missing"><strong>Mancanti</strong></button>
                    <button type="button" data-value="duplicates"><strong>Duplicati</strong></button>
                </div>
            </div>

            <div class="inv-custom-select" data-inv-custom-select>
                <select id="inventorySort" class="inv-select inv-native-select" aria-label="Ordina inventario" tabindex="-1" aria-hidden="true">
                    <option value="default">Ordine originale</option>
                    <option value="name">Nome</option>
                    <option value="rarity">Rarità</option>
                    <option value="quantity-desc">Più quantità</option>
                    <option value="quantity-asc">Meno quantità</option>
                </select>

                <button type="button" class="inv-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                    <span class="inv-select-current">Ordine originale</span>
                    <i class="fas fa-chevron-down"></i>
                </button>

                <div class="inv-select-menu" role="listbox" aria-label="Ordina inventario">
                    <button type="button" data-value="default"><strong>Ordine originale</strong></button>
                    <button type="button" data-value="name"><strong>Nome</strong><span>A-Z</span></button>
                    <button type="button" data-value="rarity"><strong>Rarità</strong></button>
                    <button type="button" data-value="quantity-desc"><strong>Più quantità</strong><span>x ↓</span></button>
                    <button type="button" data-value="quantity-asc"><strong>Meno quantità</strong><span>x ↑</span></button>
                </div>
            </div>

            <button type="button" class="inv-btn inv-btn--soft" id="resetInventoryFilters">
                <i class="fas fa-rotate-left"></i>
                <span>Reset</span>
            </button>
        </section>

        <section class="inv-visible-count inv-reveal">
            <span id="visibleCount">0 risultati</span>
        </section>

        <section id="inventoryLoading" class="inv-loading" aria-live="polite">
            <div class="inv-loader">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <p>Caricamento inventario...</p>
        </section>

        <section id="inventoryError" class="inv-empty" hidden>
            <i class="fas fa-triangle-exclamation"></i>
            <strong>Non riesco a caricare l’inventario</strong>
            <span>Riprova tra poco.</span>
        </section>

        <section id="inventoryEmpty" class="inv-empty" hidden>
            <i class="fas fa-magnifying-glass"></i>
            <strong>Nessun personaggio trovato</strong>
            <span>Cambia ricerca o filtri.</span>
        </section>

        <section id="inventario" class="inventory-grid" hidden></section>
    </main>

    <div class="inv-modal" id="characterModal" hidden>
        <div class="inv-modal__backdrop" data-close-character-modal></div>
        <article class="inv-modal__panel" role="dialog" aria-modal="true" aria-labelledby="characterModalTitle">
            <button type="button" class="inv-modal__close" data-close-character-modal aria-label="Chiudi">
                <i class="fas fa-xmark"></i>
            </button>

            <div id="characterModalContent"></div>
        </article>
    </div>

    <?php include '../includes/scroll_indicator.php'; ?>
    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>