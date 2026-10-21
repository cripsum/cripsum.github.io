<?php
// Duelli 2.0 non ancora aperti a tutti (o non installati): come nell'arena.
?>
<main class="d2p-closed">
    <h1><?= $h($dT('I nuovi duelli arrivano presto', 'The new duels are coming soon')) ?></h1>
    <p><?= $h($dReady
        ? $dT('Per ora li sta provando lo staff: torna tra poco.', 'The staff is testing them: come back soon.')
        : $dT('Non sono ancora installati su questo sito.', 'They are not installed on this site yet.')) ?></p>
    <a class="d2p-btn d2p-btn-w" href="<?= $h($dUrls['home']) ?>"><?= $h($dT('Torna alla home', 'Back to the home page')) ?></a>
</main>
