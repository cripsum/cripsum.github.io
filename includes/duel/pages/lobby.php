<?php

/**
 * Duelli 2.0 — la lobby «Duelli» (tavola 1), una pagina sola per IT ed EN.
 *
 * Si include da it/duelli/index.php ed en/duelli/index.php con $duelLang.
 * I dati arrivano gia' dentro la pagina (duel_lobby_view); assets/duel/
 * lobby.js disegna e gestisce coda, bot, stanze, classifica.
 *
 * Della tavola 1 restano alle fasi dopo: sfide in differita e agli amici
 * (fase 6), stagione con premi (fase 6), sfida del giorno (fase 8).
 */

require __DIR__ . '/_start.php';

$dData = null;
if ($dOpen) {
    try {
        duel_use_current_values($mysqli);
        $dData = duel_lobby_view($mysqli, $dUserId, $dLang);
    } catch (Throwable $e) {
        error_log('[duel lobby] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
}
$dConfig = duel_page_config($dLang, $dUserId);
?>
<!DOCTYPE html>
<html lang="<?= $dLang ?>"<?= cripsum_theme_html_attr() ?>>
<head>
    <?php include __DIR__ . '/../../head-import.php'; ?>
    <title><?= $h($dT('Duelli · Cripsum™', 'Duels · Cripsum™')) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="<?= $h(cripsum_asset('/assets/duel/pages.css')) ?>">
    <?php cripsum_theme_head(); ?>
</head>
<body class="d2p d2p-lobby">
    <?php include __DIR__ . '/../../navbar.php'; ?>

<?php if (!$dOpen): ?>
    <?php include __DIR__ . '/_closed.php'; ?>
<?php elseif (!$dData): ?>
    <main class="d2p-closed"><h1><?= $h($dT('I duelli non si sono caricati', 'Duels failed to load')) ?></h1>
        <p><?= $h($dT('Riprova tra poco.', 'Try again in a moment.')) ?></p></main>
<?php else: ?>
    <main class="d2p-wrap" id="d2p-main">
        <header class="d2p-head">
            <div>
                <span class="d2p-kick"><i data-i="attacco"></i> Cripsum™ Duel</span>
                <h1 class="d2p-h1"><?= $h($dT('Duelli', 'Duels')) ?></h1>
            </div>
            <div class="d2p-head-side">
                <div id="d2p-season"></div>
                <a class="d2p-link" href="<?= $h($dUrls['classic']) ?>"><?= $h($dT('Duelli di sempre', 'Classic duels')) ?> <i data-i="freccia"></i></a>
            </div>
        </header>

        <div class="d2p-grid">
            <section class="d2p-col" aria-label="<?= $h($dT('Gioca', 'Play')) ?>">
                <div class="d2p-seg" id="d2p-modes" role="tablist" aria-label="<?= $h($dT('Modalità', 'Mode')) ?>"></div>
                <p class="d2p-modeline" id="d2p-modeline"></p>
                <div id="d2p-team"></div>
                <div id="d2p-extra"></div>
                <div class="d2p-play" id="d2p-play"></div>
                <div class="d2p-block" id="d2p-going" hidden></div>
            </section>

            <aside class="d2p-side">
                <div class="d2p-block" id="d2p-rank"></div>
                <div class="d2p-block" id="d2p-board"></div>
                <div class="d2p-block" id="d2p-live"></div>
                <div class="d2p-block" id="d2p-recent"></div>
            </aside>
        </div>
    </main>
    <div class="d2p-sr" id="d2p-say" aria-live="polite"></div>

    <script id="d2p-config" type="application/json"><?= json_encode($dConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script id="d2p-data" type="application/json"><?= json_encode($dData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script src="<?= $h(cripsum_asset('/assets/duel/icons.js')) ?>"></script>
    <script src="<?= $h(cripsum_asset('/assets/duel/common.js')) ?>"></script>
    <script src="<?= $h(cripsum_asset('/assets/duel/lobby.js')) ?>"></script>
<?php endif; ?>

    <?php include __DIR__ . '/../../scroll_indicator.php'; ?>
    <?php include __DIR__ . '/../../' . ($dEn ? 'footer-en.php' : 'footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
