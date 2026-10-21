<?php

/**
 * Duelli 2.0 — la pagina Squadra (tavola 2), una sola per IT ed EN.
 *
 * Si include da it/duelli/squadra.php ed en/duelli/squadra.php con
 * $duelLang. Tre squadre salvate (game_teams), la collezione con costo,
 * ruolo, legami e il kit scritto dai numeri veri. I dati arrivano nella
 * pagina; assets/duel/squadra.js disegna e salva (api/duel/team.php).
 *
 * ?slot=N apre la squadra N.
 */

require __DIR__ . '/_start.php';

$dData = null;
if ($dOpen) {
    try {
        duel_use_current_values($mysqli);
        $dData = [
            'roster' => duel_roster($mysqli, $dUserId),
            'teams' => duel_teams($mysqli, $dUserId),
            'traits' => duel_traits_view($dLang),
            'slots' => DUEL_TEAM_SLOTS,
            'slot' => max(1, min(DUEL_TEAM_SLOTS, (int)($_GET['slot'] ?? 1))),
        ];
    } catch (Throwable $e) {
        error_log('[duel squadra] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
}
$dConfig = duel_page_config($dLang, $dUserId);
?>
<!DOCTYPE html>
<html lang="<?= $dLang ?>"<?= cripsum_theme_html_attr() ?>>
<head>
    <?php include __DIR__ . '/../../head-import.php'; ?>
    <title><?= $h($dT('Squadra · Duelli · Cripsum™', 'Team · Duels · Cripsum™')) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="<?= $h(cripsum_asset('/assets/duel/pages.css')) ?>">
    <?php cripsum_theme_head(); ?>
</head>
<body class="d2p d2p-team-page">
    <?php include __DIR__ . '/../../navbar.php'; ?>

<?php if (!$dOpen): ?>
    <?php include __DIR__ . '/_closed.php'; ?>
<?php elseif (!$dData): ?>
    <main class="d2p-closed"><h1><?= $h($dT('La squadra non si è caricata', 'The team page failed to load')) ?></h1>
        <p><?= $h($dT('Riprova tra poco.', 'Try again in a moment.')) ?></p></main>
<?php else: ?>
    <main class="d2p-wrap" id="d2p-main">
        <header class="d2p-sq-head">
            <div>
                <a class="d2p-back" href="<?= $h($dUrls['lobby']) ?>"><i data-i="indietro"></i> <?= $h($dT('Duelli', 'Duels')) ?></a>
                <h1 class="d2p-h1"><?= $h($dT('Squadra', 'Team')) ?></h1>
            </div>
            <div class="d2p-tabs2 d2p-sq-tabs" id="d2p-slots" role="tablist" aria-label="<?= $h($dT('Squadre salvate', 'Saved teams')) ?>"></div>
        </header>

        <div class="d2p-sq-grid">
            <section class="d2p-sq-team" aria-label="<?= $h($dT('In campo', 'On the field')) ?>">
                <span class="d2p-lab"><?= $h($dT('In campo', 'On the field')) ?></span>
                <div class="d2p-field" id="d2p-field"></div>
                <div class="d2p-meter" id="d2p-meter"></div>
                <div class="d2p-syn" id="d2p-syn"></div>
                <div class="d2p-syn" id="d2p-roles"></div>
                <input class="d2p-name-in" id="d2p-name" maxlength="40" autocomplete="off" aria-label="<?= $h($dT('Nome della squadra', 'Team name')) ?>">
                <div class="d2p-save">
                    <button class="d2p-btn d2p-btn-w" type="button" id="d2p-save"><?= $h($dT('Salva squadra', 'Save team')) ?></button>
                    <button class="d2p-btn" type="button" id="d2p-try"><?= $h($dT('Prova col bot', 'Try vs bot')) ?></button>
                </div>
                <p class="d2p-save-note" id="d2p-note" aria-live="polite"></p>
            </section>

            <section class="d2p-sq-coll" aria-label="<?= $h($dT('Collezione', 'Collection')) ?>">
                <div class="d2p-tools">
                    <label class="d2p-search"><i data-i="cerca"></i><input id="d2p-q" type="search" autocomplete="off" placeholder="<?= $h($dT('Cerca nella collezione', 'Search your collection')) ?>" aria-label="<?= $h($dT('Cerca nella collezione', 'Search your collection')) ?>"></label>
                    <button class="d2p-toggle" type="button" id="d2p-fit" aria-pressed="false"><?= $h($dT('Disponibili per la Ranked', 'Fits Ranked')) ?></button>
                </div>
                <div class="d2p-filters" id="d2p-f-role" role="group" aria-label="<?= $h($dT('Ruolo', 'Role')) ?>"></div>
                <div class="d2p-filters" id="d2p-f-rar" role="group" aria-label="<?= $h($dT('Rarità', 'Rarity')) ?>"></div>
                <div class="d2p-coll" id="d2p-coll"></div>
            </section>

            <aside class="d2p-det" id="d2p-det" aria-label="<?= $h($dT('Scheda del personaggio', 'Character sheet')) ?>"></aside>
        </div>
    </main>
    <div class="d2p-sr" id="d2p-say" aria-live="polite"></div>

    <script id="d2p-config" type="application/json"><?= json_encode($dConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script id="d2p-data" type="application/json"><?= json_encode($dData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script src="<?= $h(cripsum_asset('/assets/duel/icons.js')) ?>"></script>
    <script src="<?= $h(cripsum_asset('/assets/duel/common.js')) ?>"></script>
    <script src="<?= $h(cripsum_asset('/assets/duel/squadra.js')) ?>"></script>
<?php endif; ?>

    <?php include __DIR__ . '/../../scroll_indicator.php'; ?>
    <?php include __DIR__ . '/../../' . ($dEn ? 'footer-en.php' : 'footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
