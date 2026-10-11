<?php
/**
 * Pagina del pannello del bot, per /it/bot e /en/bot.
 *
 * Qui c'e' solo il guscio: testata del sito, un contenitore vuoto e gli
 * script. Tutto il resto lo disegna assets/bot/panel.js con quello che gli
 * rispondono gli endpoint di api/botpanel, che sono anche gli unici a
 * decidere chi puo' vedere e salvare cosa. Questa pagina non controlla
 * nessun permesso e non stampa nessun dato di un server.
 *
 * Indirizzi:
 *   /it/bot                          scelta del server
 *   /it/bot/<id del server>          panoramica
 *   /it/bot/<id del server>/<sezione>
 */

require_once __DIR__ . '/../../config/session_init.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../theme.php';

if (isset($mysqli) && $mysqli instanceof mysqli && function_exists('checkBan')) {
    checkBan($mysqli);
}

$botPanelLang = isset($botPanelLang) && $botPanelLang === 'en' ? 'en' : 'it';

// Finiscono in attributi della pagina: solo cifre e lettere minuscole.
$botPanelGuild = preg_match('/^\d{15,25}$/', (string)($_GET['guild'] ?? '')) ? (string)$_GET['guild'] : '';
$botPanelSection = preg_match('/^[a-z-]{1,30}$/', (string)($_GET['section'] ?? '')) ? (string)$_GET['section'] : '';

$botPanelTitle = $botPanelLang === 'en' ? 'Poppy, the Cripsum bot' : 'Poppy, il bot di Cripsum';
?>
<!DOCTYPE html>
<html lang="<?= $botPanelLang ?>"<?= cripsum_theme_html_attr() ?>>

<head>
    <?php include __DIR__ . '/../head-import.php'; ?>
    <?php cripsum_theme_head(); ?>
    <title>Cripsum™ - Poppy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="<?= cripsum_theme_asset('/assets/bot/panel.css') ?>">
    <script src="<?= cripsum_theme_asset('/assets/bot/panel.js') ?>" defer></script>
    <script src="<?= cripsum_theme_asset('/assets/bot/panel-sections.js') ?>" defer></script>
</head>

<body class="bot-panel-page">
    <?php include __DIR__ . '/../navbar.php'; ?>

    <main class="bp-shell" id="bp-root"
        data-lang="<?= $botPanelLang ?>"
        data-guild="<?= $botPanelGuild ?>"
        data-section="<?= $botPanelSection ?>">
        <noscript>
            <p class="bp-lead"><?= $botPanelLang === 'en'
                ? 'The panel needs JavaScript to work.'
                : 'Il pannello ha bisogno di JavaScript per funzionare.' ?></p>
        </noscript>
        <p class="bp-loading" aria-live="polite"><?= $botPanelLang === 'en' ? 'Loading…' : 'Carico…' ?></p>
    </main>

    <?php include __DIR__ . ($botPanelLang === 'en' ? '/../footer-en.php' : '/../footer.php'); ?>
</body>

</html>
