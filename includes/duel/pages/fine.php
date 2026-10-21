<?php

/**
 * Duelli 2.0 — la fine partita (tavola 5), una pagina sola per IT ed EN.
 *
 * Si include da it/duelli/fine.php ed en/duelli/fine.php con $duelLang.
 * Indirizzo: /it/duelli/fine?m=<partita>. Esito, punti Ranked con la soglia
 * del rango, MVP e i numeri di tutte e sei le carte (duel_summary);
 * rivincita, replay, ritorno alla lobby. assets/duel/fine.js disegna.
 *
 * La vede chi ha giocato, e chi puo' vedere il replay. Una partita ancora in
 * corso rimanda all'arena.
 */

require __DIR__ . '/_start.php';

$dData = null;
$dError = '';
if ($dOpen) {
    $dMatch = max(0, (int)($_GET['m'] ?? 0));
    try {
        $row = $dMatch ? duel_match_row($mysqli, $dMatch) : null;
        if (!$row || $row['engine_version'] === null) {
            $dError = $dT('Partita non trovata.', 'Match not found.');
        } elseif (in_array((string)$row['status'], ['waiting', 'active'], true)) {
            header('Location: ' . $dUrls['arena'] . '?m=' . $dMatch);
            exit();
        } elseif (!duel_can_review($row, $dUserId)) {
            $dError = (string)$row['status'] === 'cancelled' ? $dT('Questa partita non è mai cominciata.', 'This match never started.') : $dT('Questa partita non si può rivedere.', 'This match cannot be viewed.');
        } else {
            $dData = duel_summary($mysqli, $dUserId, $row, $dLang);
        }
    } catch (Throwable $e) {
        error_log('[duel fine] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        $dError = $dT('Il riepilogo non si è caricato. Riprova tra poco.', 'The summary failed to load. Try again in a moment.');
    }
}
$dConfig = duel_page_config($dLang, $dUserId);
?>
<!DOCTYPE html>
<html lang="<?= $dLang ?>"<?= cripsum_theme_html_attr() ?>>
<head>
    <?php include __DIR__ . '/../../head-import.php'; ?>
    <title><?= $h($dT('Fine partita · Duelli · Cripsum™', 'Match result · Duels · Cripsum™')) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="<?= $h(cripsum_asset('/assets/duel/pages.css')) ?>">
    <?php cripsum_theme_head(); ?>
</head>
<body class="d2p d2p-end-page">
    <?php include __DIR__ . '/../../navbar.php'; ?>

<?php if (!$dOpen): ?>
    <?php include __DIR__ . '/_closed.php'; ?>
<?php elseif (!$dData): ?>
    <main class="d2p-closed"><h1><?= $h($dError) ?></h1>
        <a class="d2p-btn d2p-btn-w" href="<?= $h($dUrls['lobby']) ?>"><?= $h($dT('Torna ai duelli', 'Back to duels')) ?></a></main>
<?php else: ?>
    <main class="d2p-wrap" id="d2p-main">
        <a class="d2p-back" href="<?= $h($dUrls['lobby']) ?>"><i data-i="indietro"></i> <?= $h($dT('Duelli', 'Duels')) ?></a>
        <div class="d2p-res" id="d2p-res" style="margin-top: 24px"></div>
    </main>
    <div class="d2p-sr" id="d2p-say" aria-live="polite"></div>

    <script id="d2p-config" type="application/json"><?= json_encode($dConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script id="d2p-data" type="application/json"><?= json_encode($dData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script src="<?= $h(cripsum_asset('/assets/duel/icons.js')) ?>"></script>
    <script src="<?= $h(cripsum_asset('/assets/duel/common.js')) ?>"></script>
    <script src="<?= $h(cripsum_asset('/assets/duel/fine.js')) ?>"></script>
<?php endif; ?>

    <?php include __DIR__ . '/../../scroll_indicator.php'; ?>
    <?php include __DIR__ . '/../../' . ($dEn ? 'footer-en.php' : 'footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
