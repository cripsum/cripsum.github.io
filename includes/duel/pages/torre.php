<?php

/**
 * Duelli 2.0 — la Torre (tavola 21), una pagina sola per IT ed EN.
 *
 * Si include da it/duelli/torre.php ed en/duelli/torre.php con $duelLang.
 * Il piano in cui sei con la squadra da battere e «Combatti»; i dieci tratti
 * e i loro piani (fatti, il tuo, chiusi); il foglio di ogni piano; a lato il
 * tuo record, i potenziamenti gratuiti da usare e la classifica.
 * /it/duelli/torre?piano=N apre subito il foglio del piano N.
 * assets/duel/torre.js disegna. Finche' l'interruttore `tower` e' spento la
 * vede solo lo staff.
 */

require __DIR__ . '/_start.php';

$dData = null;
$dTowerOff = false;
if ($dOpen) {
    try {
        require_once __DIR__ . '/../tower.php';
        if (!duel_tower_ready($mysqli) || !duel_tower_open_for($mysqli)) {
            $dTowerOff = true;
        } else {
            $dData = duel_tower_page($mysqli, $dUserId, $dLang);
        }
    } catch (Throwable $e) {
        error_log('[duel torre] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
}
$dConfig = duel_page_config($dLang, $dUserId);
?>
<!DOCTYPE html>
<html lang="<?= $dLang ?>"<?= cripsum_theme_html_attr() ?>>
<head>
    <?php include __DIR__ . '/../../head-import.php'; ?>
    <title><?= $h($dT('La Torre · Duelli · Cripsum™', 'The Tower · Duels · Cripsum™')) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="<?= $h(cripsum_asset('/assets/duel/pages.css')) ?>">
    <?php cripsum_theme_head(); ?>
</head>
<body class="d2p d2p-tower-page">
    <?php include __DIR__ . '/../../navbar.php'; ?>

<?php if (!$dOpen): ?>
    <?php include __DIR__ . '/_closed.php'; ?>
<?php elseif ($dTowerOff): ?>
    <main class="d2p-closed"><h1><?= $h($dT('La Torre apre presto', 'The Tower opens soon')) ?></h1>
        <p><?= $h($dT('Cento piani contro il bot, dal più facile al più difficile. Per ora la sta provando lo staff.', 'A hundred floors against the bot, from easiest to hardest. The staff is testing it for now.')) ?></p>
        <a class="d2p-btn d2p-btn-w" href="<?= $h($dUrls['lobby']) ?>"><?= $h($dT('Torna ai duelli', 'Back to duels')) ?></a></main>
<?php elseif (!$dData): ?>
    <main class="d2p-closed"><h1><?= $h($dT('La Torre non si è caricata', 'The Tower failed to load')) ?></h1>
        <p><?= $h($dT('Riprova tra poco.', 'Try again in a moment.')) ?></p>
        <a class="d2p-btn d2p-btn-w" href="<?= $h($dUrls['lobby']) ?>"><?= $h($dT('Torna ai duelli', 'Back to duels')) ?></a></main>
<?php else: ?>
    <main class="d2p-wrap" id="d2p-main">
        <a class="d2p-back" href="<?= $h($dUrls['lobby']) ?>"><i data-i="indietro"></i> <?= $h($dT('Duelli', 'Duels')) ?></a>
        <header class="d2p-head">
            <div>
                <span class="d2p-kick"><i data-i="attacco"></i> Cripsum™ Duel</span>
                <h1 class="d2p-h1"><?= $h($dT('La Torre', 'The Tower')) ?></h1>
            </div>
            <div class="d2p-head-side" id="d2p-tw-prog"></div>
        </header>

        <div class="d2p-grid">
            <section class="d2p-col" aria-label="<?= $h($dT('La salita', 'The climb')) ?>">
                <div class="d2p-tw-now" id="d2p-tw-now"></div>
                <div class="d2p-tw-climb" id="d2p-tw-climb"></div>
            </section>

            <aside class="d2p-side">
                <div class="d2p-block" id="d2p-tw-me"></div>
                <div class="d2p-block" id="d2p-tw-use"></div>
                <div class="d2p-block" id="d2p-tw-board"></div>
            </aside>
        </div>
    </main>
    <div class="d2p-sr" id="d2p-say" aria-live="polite"></div>

    <script id="d2p-config" type="application/json"><?= json_encode($dConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script id="d2p-data" type="application/json"><?= json_encode($dData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script src="<?= $h(cripsum_asset('/assets/duel/icons.js')) ?>"></script>
    <script src="<?= $h(cripsum_asset('/assets/duel/common.js')) ?>"></script>
    <script src="<?= $h(cripsum_asset('/assets/duel/torre.js')) ?>"></script>
<?php endif; ?>

    <?php include __DIR__ . '/../../scroll_indicator.php'; ?>
    <?php include __DIR__ . '/../../' . ($dEn ? 'footer-en.php' : 'footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
