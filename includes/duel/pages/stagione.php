<?php

/**
 * Duelli 2.0 — la pagina Stagione (tavola 6), una sola per IT ed EN.
 *
 * Si include da it/duelli/stagione.php ed en/duelli/stagione.php con
 * $duelLang. La fascia, i punti e la posizione; quanto manca alla fine; i
 * premi di fine stagione con quelli gia' raggiunti; le stagioni passate.
 * Senza stagioni accese (game_settings.seasons = 0) e' la «Stagione 0»: la
 * classifica di sempre.
 */

require __DIR__ . '/_start.php';

$dData = null;
if ($dOpen) {
    try {
        $dData = duel_season_page($mysqli, $dUserId);
    } catch (Throwable $e) {
        error_log('[duel stagione] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
}
$dMonths = $dEn ? ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
    : ['', 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
$dDate = static fn(int $ts) => $dEn ? $dMonths[(int)date('n', $ts)] . ' ' . date('j', $ts) : date('j', $ts) . ' ' . $dMonths[(int)date('n', $ts)];
$dNum = static fn(int $n) => number_format($n, 0, ',', $dEn ? ',' : '.');
$dEmb = static fn(string $key, string $cls = '') => '<span class="d2p-emb ' . $cls . '" data-emb="' . htmlspecialchars($key, ENT_QUOTES) . '" style="--tc: var(--t-' . htmlspecialchars($key, ENT_QUOTES) . ')" aria-hidden="true"></span>';
?>
<!DOCTYPE html>
<html lang="<?= $dLang ?>"<?= cripsum_theme_html_attr() ?>>
<head>
    <?php include __DIR__ . '/../../head-import.php'; ?>
    <title><?= $h($dT('Stagione · Duelli · Cripsum™', 'Season · Duels · Cripsum™')) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="<?= $h(cripsum_asset('/assets/duel/pages.css')) ?>">
    <?php cripsum_theme_head(); ?>
</head>
<body class="d2p d2p-season-page">
    <?php include __DIR__ . '/../../navbar.php'; ?>

<?php if (!$dOpen): ?>
    <?php include __DIR__ . '/_closed.php'; ?>
<?php elseif (!$dData): ?>
    <main class="d2p-closed"><h1><?= $h($dT('La stagione non si è caricata', 'The season failed to load')) ?></h1>
        <p><?= $h($dT('Riprova tra poco.', 'Try again in a moment.')) ?></p></main>
<?php else:
    $s = $dData['season'];
    $me = $dData['me'];
    $placing = $s && $me && $me['placement'] > 0;
    $rank = $dData['rank'];
    $seasonName = $s ? $dT('Stagione ', 'Season ') . $s['number'] . ($s['name'] !== '' ? ' · ' . $s['name'] : '') : $dT('Stagione 0', 'Season 0');
    $nextIndex = null;
    foreach ($dData['rewards'] as $i => $r) {
        if (!$r['reached']) {
            $nextIndex = $i;
            break;
        }
    }
?>
    <main class="d2p-sz" id="d2p-main">
        <a class="d2p-back" href="<?= $h($dUrls['lobby']) ?>"><i data-i="indietro"></i> <?= $h($dT('Duelli', 'Duels')) ?></a>

        <section class="d2p-sz-top">
            <?= $dEmb($rank['key']) ?>
            <h1><?= $h($placing ? $dT('Piazzamento', 'Placement') : $rank['label']) ?></h1>
            <p><?php
                $bits = [$seasonName];
                if ($placing) {
                    $bits[] = $dT('ancora ' . $me['placement'] . ' Ranked per il rango', $me['placement'] . ' more Ranked for your rank');
                } else {
                    $bits[] = $dNum($dData['rating']) . ' ' . $dT('punti', 'points');
                    if ($dData['pos']) {
                        $bits[] = $dEn ? '#' . $dData['pos'] . ' of ' . $dData['total'] : $dData['pos'] . '° su ' . $dData['total'];
                    }
                }
                echo $h(implode(' · ', $bits));
            ?></p>
            <?php if ($s): ?>
                <div class="d2p-sz-bar"><span class="d2p-bar"><i style="width: <?= round($s['progress'] * 100, 1) ?>%"></i></span></div>
                <div class="d2p-sz-dates"><span><?= $h($dT('iniziata il ', 'started ') . $dDate($s['starts'])) ?></span><span><?= $h($dT('finisce il ', 'ends ') . $dDate($s['ends'])) ?></span></div>
            <?php endif; ?>
        </section>

        <span class="d2p-lab"><?= $h($dT('Premi di fine stagione', 'End of season rewards')) ?></span>
        <?php foreach ($dData['rewards'] as $i => $r):
            $label = duel_rank_label($r['tier'], $dLang);
            $isNow = !$placing && $rank['key'] === $r['tier'];
        ?>
            <div class="d2p-prize<?= $isNow ? ' d2p-now' : '' ?><?= !$r['reached'] && $i !== $nextIndex ? ' d2p-locked' : '' ?>">
                <?= $dEmb($r['tier']) ?>
                <span><b><?= $h($label . ($r['min'] > 0 ? ' · ' . $dNum($r['min']) : '')) ?></b>
                    <small><?= $h($dNum($r['godos']) . ' Godos · ' . $dT('emblema di stagione nei duelli', 'season emblem in duels')) ?></small></span>
                <?php if ($r['reached']): ?>
                    <span class="d2p-ok" aria-label="<?= $h($dT('Raggiunto', 'Reached')) ?>"><i data-i="check"></i></span>
                <?php else: ?>
                    <span class="d2p-lock" aria-label="<?= $h($dT('Non ancora', 'Not yet')) ?>"><i data-i="lucchetto"></i></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($dData['past']): ?>
            <span class="d2p-lab"><?= $h($dT('Stagioni passate', 'Past seasons')) ?></span>
            <?php foreach ($dData['past'] as $p): ?>
                <div class="d2p-past">
                    <span><?= $h($dT('Stagione ', 'Season ') . $p['n'] . ($p['name'] !== '' ? ' · ' . $p['name'] : '')) ?></span>
                    <b><?php if ($p['tier']): ?><?= $dEmb($p['tier']) ?><?= $h(duel_rank_label($p['tier'], $dLang)) ?><?php else: ?><?= $h($dT('senza piazzamento', 'not placed')) ?><?php endif; ?>
                        <?= $p['rating'] !== null ? $h(' · ' . $dNum($p['rating'])) : '' ?><?= $p['reward'] ? $h(' · +' . $dNum($p['reward']) . ' Godos') : '' ?><?= !empty($p['badge']) ? $h(' · ' . $dT('badge sul profilo', 'profile badge')) : '' ?></b>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <p class="d2p-sz-note"><?= $h($s
            ? $dT('Ogni stagione dura ' . ($dData['days'] / 7) . ' settimane. Le prime ' . $dData['placement_games'] . ' Ranked sono di piazzamento. A fine stagione chi le ha giocate prende i Godos della fascia più alta raggiunta, e i punti si avvicinano a 1.000 della metà: chi era a 1.506 riparte da 1.253.',
                'Each season lasts ' . ($dData['days'] / 7) . ' weeks. The first ' . $dData['placement_games'] . ' Ranked matches are for placement. At the end, players who played them get the Godos of the highest tier they reached, and points move halfway back toward 1,000: 1,506 becomes 1,253.')
            : $dT('Per ora è la classifica di sempre. Le stagioni partono quando lo staff le accende: ' . ($dData['days'] / 7) . ' settimane, premi in Godos per la fascia raggiunta.',
                'For now this is the all-time ranking. Seasons start when the staff turns them on: ' . ($dData['days'] / 7) . ' weeks, Godos for the tier you reach.')) ?></p>
    </main>
    <script src="<?= $h(cripsum_asset('/assets/duel/icons.js')) ?>"></script>
    <script src="<?= $h(cripsum_asset('/assets/duel/stagione.js')) ?>"></script>
<?php endif; ?>

    <?php include __DIR__ . '/../../scroll_indicator.php'; ?>
    <?php include __DIR__ . '/../../' . ($dEn ? 'footer-en.php' : 'footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
