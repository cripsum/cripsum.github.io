<?php
// Duelli 2.0 non ancora aperti a tutti (o non installati): come nell'arena.
?>
<main class="d2p-closed">
    <h1><?= $h($dT('I nuovi duelli arrivano presto', 'The new duels are coming soon')) ?></h1>
    <p><?= $h($dReady
        ? $dT('Per ora li sta provando lo staff. Intanto si gioca con i duelli di sempre.', 'The staff is testing them. Meanwhile, play the usual duels.')
        : $dT('Non sono ancora installati su questo sito.', 'They are not installed on this site yet.')) ?></p>
    <a class="d2p-btn d2p-btn-w" href="<?= $h($dUrls['classic']) ?>"><?= $h($dT('Vai ai duelli', 'Go to duels')) ?></a>
</main>
