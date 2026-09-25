<?php

/**
 * Pagina dell'inventario, una sola per IT ed EN.
 *
 * Si include da it/inventario.php ed en/inventario.php con $gachaLang
 * impostata. I dati arrivano gia' dentro la pagina (gacha_collection_payload,
 * poche query in blocco): al primo caricamento non parte nessuna richiesta.
 * Il resto lo disegna assets/inventario/inventario.js.
 *
 * head-import.php e la navbar sovrascrivono $t e $lang: qui si usa $g.
 */

require_once __DIR__ . '/../../../config/session_init.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../collection.php';

$gLang = ($gachaLang ?? 'it') === 'en' ? 'en' : 'it';
$gEn = $gLang === 'en';

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = $gEn ? 'You must be logged in to view your inventory.' : "Per accedere all'inventario devi essere loggato";
    header('Location: accedi');
    exit();
}

$gUserId = (int)$_SESSION['user_id'];
$gPayload = null;
try {
    $gPayload = gacha_collection_payload($mysqli, $gUserId, $gLang);
} catch (Throwable $e) {
    error_log('[inventario] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$ogDescription = $gEn ? 'Your Character inventory on Cripsum™.' : 'Il tuo inventario personaggi su Cripsum™.';
$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/' . $gLang . '/inventario'), '#');
$ogTitle = $gEn ? 'Character Inventory - Cripsum™' : 'Inventario Personaggi - Cripsum™';
$ogImage = '/img/waguri.jpeg';
?>
<!DOCTYPE html>
<html lang="<?= $gLang ?>">

<head>
    <?php include __DIR__ . '/../../head-import.php'; ?>
    <title><?= $gEn ? 'Cripsum™ - Inventory' : 'Cripsum™ - Inventario' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="<?= $h(cripsum_asset('/assets/inventario/inventario.css')) ?>">
    <script src="<?= $h(cripsum_asset('/assets/inventario/inventario.js')) ?>" defer></script>
</head>

<body class="inv-page">
    <?php include __DIR__ . '/../../navbar.php'; ?>

    <main class="inv" id="inventory" data-lang="<?= $gLang ?>">
        <header class="inv-head">
            <div class="inv-head__title">
                <h1><?= $gEn ? 'Inventory' : 'Inventario' ?></h1>
                <p><?= $gEn ? 'Every character you found, the ones still missing and what you can do with duplicates.' : 'I personaggi che hai trovato, quelli che ti mancano e cosa fare con i doppioni.' ?></p>
            </div>

            <div class="inv-head__progress" data-progress>
                <svg viewBox="0 0 36 36" aria-hidden="true" class="inv-ring">
                    <circle cx="18" cy="18" r="15.9" class="inv-ring__track"></circle>
                    <circle cx="18" cy="18" r="15.9" class="inv-ring__fill" data-ring></circle>
                </svg>
                <div>
                    <strong data-progress-pct>0%</strong>
                    <span data-progress-text></span>
                </div>
            </div>

            <dl class="inv-head__stats" data-stats></dl>

            <div class="inv-head__actions">
                <a href="lootbox" class="inv-btn inv-btn--primary">
                    <i class="fa-solid fa-box-open" aria-hidden="true"></i>
                    <span><?= $gEn ? 'Open lootbox' : 'Apri la lootbox' ?></span>
                </a>
                <button type="button" class="inv-btn" data-upgrade-all disabled>
                    <i class="fa-solid fa-angles-up" aria-hidden="true"></i>
                    <span><?= $gEn ? 'Upgrade all' : 'Potenzia tutto' ?></span>
                </button>
            </div>
        </header>

        <nav class="inv-tabs" role="tablist" aria-label="<?= $gEn ? 'Inventory sections' : 'Sezioni dell\'inventario' ?>">
            <button type="button" role="tab" class="inv-tab is-active" data-tab="collezione" aria-selected="true">
                <i class="fa-solid fa-layer-group" aria-hidden="true"></i> <?= $gEn ? 'Characters' : 'Personaggi' ?>
            </button>
            <button type="button" role="tab" class="inv-tab" data-tab="collezioni" aria-selected="false">
                <i class="fa-solid fa-tags" aria-hidden="true"></i> <?= $gEn ? 'Collections' : 'Collezioni' ?> <span class="inv-tab__dot" data-claim-dot hidden></span>
            </button>
            <button type="button" role="tab" class="inv-tab" data-tab="frammenti" aria-selected="false">
                <img src="/img/frammento.svg" alt="" class="inv-tab__img" aria-hidden="true"> <?= $gEn ? 'Fragments' : 'Frammenti' ?>
            </button>
            <span class="inv-tabs__ink" data-tab-ink aria-hidden="true"></span>
        </nav>

        <section class="inv-panel" data-panel="collezione">
            <div class="inv-toolbar" data-toolbar>
                <label class="inv-search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" data-search placeholder="<?= $gEn ? 'Search characters...' : 'Cerca personaggi...' ?>" autocomplete="off" aria-label="<?= $gEn ? 'Search' : 'Cerca' ?>">
                </label>
                <div class="inv-chips" data-rarity-chips role="group" aria-label="<?= $gEn ? 'Rarity' : 'Rarità' ?>"></div>
                <button type="button" class="inv-btn inv-btn--ghost inv-filters-btn" data-filters-toggle aria-expanded="false">
                    <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                    <span><?= $gEn ? 'Filters' : 'Filtri' ?></span>
                    <b data-filters-count hidden></b>
                </button>
            </div>

            <div class="inv-filters" data-filters>
                <div class="inv-filters__inner">
                <div class="inv-filters__group">
                    <span class="inv-filters__label"><?= $gEn ? 'Show' : 'Mostra' ?></span>
                    <div class="inv-chips" data-status-chips></div>
                </div>
                <div class="inv-filters__group" data-category-group>
                    <span class="inv-filters__label"><?= $gEn ? 'Category' : 'Categoria' ?></span>
                    <div class="inv-chips" data-category-chips></div>
                </div>
                <div class="inv-filters__row">
                    <div class="inv-dd" data-dd="sort" data-label="<?= $gEn ? 'Sort by' : 'Ordina per' ?>"></div>
                    <div class="inv-dd" data-dd="group" data-label="<?= $gEn ? 'Group by' : 'Raggruppa' ?>"></div>
                    <div class="inv-density" role="group" aria-label="<?= $gEn ? 'Card size' : 'Dimensione carte' ?>">
                        <button type="button" data-density="comfortable" title="<?= $gEn ? 'Large cards' : 'Carte grandi' ?>"><i class="fa-solid fa-table-cells-large"></i></button>
                        <button type="button" data-density="compact" title="<?= $gEn ? 'Compact' : 'Compatte' ?>"><i class="fa-solid fa-table-cells"></i></button>
                    </div>
                    <button type="button" class="inv-btn inv-btn--ghost" data-reset>
                        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> <?= $gEn ? 'Reset' : 'Azzera' ?>
                    </button>
                </div>
                </div>
            </div>

            <div class="inv-resultbar">
                <span data-results aria-live="polite"></span>
                <button type="button" class="inv-link" data-mark-seen hidden>
                    <i class="fa-solid fa-check-double" aria-hidden="true"></i> <?= $gEn ? 'Mark all as seen' : 'Segna tutti come visti' ?>
                </button>
            </div>

            <div class="inv-groups" data-groups></div>

            <div class="inv-empty" data-empty hidden>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <strong><?= $gEn ? 'No characters found' : 'Nessun personaggio trovato' ?></strong>
                <span><?= $gEn ? 'Try a different search or filter.' : 'Prova a cambiare ricerca o filtri.' ?></span>
            </div>
        </section>

        <section class="inv-panel" data-panel="collezioni" hidden>
            <p class="inv-panel__intro"><?= $gEn
                ? 'Find every character of a category to complete its collection. Some collections have a reward: it arrives in your inbox.'
                : 'Trova tutti i personaggi di una categoria per completarne la collezione. Alcune hanno un premio: arriva nella tua posta.' ?></p>
            <div class="inv-collections" data-collections></div>
        </section>

        <section class="inv-panel" data-panel="frammenti" hidden>
            <div data-fragments></div>
        </section>

        <?php if (!$gPayload): ?>
            <div class="inv-empty">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <strong><?= $gEn ? 'Could not load the inventory' : 'Non riesco a caricare l\'inventario' ?></strong>
                <span><?= $gEn ? 'Try again in a moment.' : 'Riprova tra poco.' ?></span>
            </div>
        <?php endif; ?>
    </main>

    <div class="inv-drawer" data-drawer hidden>
        <div class="inv-drawer__backdrop" data-drawer-close></div>
        <article class="inv-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="invDrawerTitle" tabindex="-1">
            <div class="inv-drawer__bar">
                <button type="button" class="inv-icon-btn" data-drawer-prev aria-label="<?= $gEn ? 'Previous' : 'Precedente' ?>"><i class="fa-solid fa-chevron-left"></i></button>
                <button type="button" class="inv-icon-btn" data-drawer-next aria-label="<?= $gEn ? 'Next' : 'Successivo' ?>"><i class="fa-solid fa-chevron-right"></i></button>
                <span class="inv-drawer__spacer"></span>
                <button type="button" class="inv-icon-btn" data-drawer-close aria-label="<?= $gEn ? 'Close' : 'Chiudi' ?>"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="inv-drawer__body" data-drawer-body></div>
        </article>
    </div>

    <div class="inv-confirm" data-confirm hidden>
        <div class="inv-confirm__backdrop" data-confirm-cancel></div>
        <div class="inv-confirm__panel" role="alertdialog" aria-modal="true" aria-labelledby="invConfirmTitle">
            <h3 id="invConfirmTitle" data-confirm-title></h3>
            <div data-confirm-body></div>
            <div class="inv-confirm__actions">
                <button type="button" class="inv-btn inv-btn--ghost" data-confirm-cancel><?= $gEn ? 'Cancel' : 'Annulla' ?></button>
                <button type="button" class="inv-btn inv-btn--primary" data-confirm-ok><?= $gEn ? 'Confirm' : 'Conferma' ?></button>
            </div>
        </div>
    </div>

    <div class="inv-toast" data-toast role="status" aria-live="polite" hidden></div>

    <script type="application/json" id="inv-data"><?= json_encode($gPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <?php include __DIR__ . '/../../scroll_indicator.php'; ?>
    <?php include __DIR__ . '/../../' . ($gEn ? 'footer-en.php' : 'footer.php'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="<?= $h(cripsum_asset('/js/unlockAchievement-' . $gLang . '.js')) ?>"></script>
</body>

</html>
