<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$isLogged = function_exists('isLoggedIn') && isLoggedIn();
$username = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Donazioni</title>

    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/static/static.css?v=1.0-static">
    <script src="/assets/static/static.js?v=1.0-static" defer></script>

</head>

<body class="static-page">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>


    <div class="static-bg" aria-hidden="true">
        <span class="static-orb static-orb--one"></span>
        <span class="static-orb static-orb--two"></span>
        <span class="static-grid-bg"></span>
    </div>


    <main class="static-shell">
        <section class="static-hero static-hero--split static-reveal">
            <div>
                <span class="static-pill">Donazioni</span>
                <h1>Supporta Cripsum™</h1>
                <p>Le donazioni aiutano a mantenere il sito online e a portare avanti nuove funzioni. Sono completamente opzionali.</p>

                <?php if ($isLogged): ?>
                    <div class="static-meta">
                        <span class="static-chip"><i class="fas fa-user"></i> Grazie, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <div class="static-actions">
                    <a href="https://www.buymeacoffee.com/cripsum" target="_blank" rel="noopener" class="static-btn static-btn--primary" onclick="unlockAchievement(4)">
                        <i class="fas fa-heart"></i>
                        <span>Dona su BuyMeACoffee</span>
                    </a>
                    <a href="supporto" class="static-btn">
                        <i class="fas fa-circle-question"></i>
                        <span>Domande?</span>
                    </a>
                </div>
            </div>

            <aside class="static-hero__side">
                <span class="static-chip"><i class="fas fa-mug-hot"></i> Opzionale</span>
                <p>Il sito resta utilizzabile anche senza donare.</p>

                <div class="static-donation-button">
                    <a href="https://www.buymeacoffee.com/cripsum" target="_blank" rel="noopener" onclick="unlockAchievement(4)">
                        <img
                            src="https://img.buymeacoffee.com/button-api/?text=Buy me a coffee :P&emoji=☕&slug=cripsum&button_colour=FFDD00&font_colour=000000&font_family=Poppins&outline_colour=000000&coffee_colour=ffffff"
                            alt="Buy me a coffee">
                    </a>
                </div>
            </aside>
        </section>

        <section class="static-grid static-grid--3" style="margin-top:1rem;">
            <article class="static-card static-reveal">
                <h2>Server</h2>
                <p>Aiuta a coprire hosting, dominio e servizi usati dal sito.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Tempo</h2>
                <p>Il sito richiede manutenzione, fix e nuove idee.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Libero</h2>
                <p>Non è obbligatorio. È solo un modo per dare supporto.</p>
            </article>
        </section>

        <section class="static-faq static-reveal" id="donationFaq" style="margin-top:1rem;">
            <h2>FAQ donazioni</h2>

            <details class="static-faq-item">
                <summary>Devo donare per usare il sito?</summary>
                <p>No. La donazione è facoltativa.</p>
            </details>

            <details class="static-faq-item">
                <summary>Le donazioni sono rimborsabili?</summary>
                <p>Dipende dalla piattaforma usata per donare. Controlla le condizioni di BuyMeACoffee.</p>
            </details>

            <details class="static-faq-item">
                <summary>Ricevo qualcosa in cambio?</summary>
                <p>Non è un acquisto. È un supporto al progetto. Eventuali badge o bonus vengono gestiti separatamente, solo se esistono davvero.</p>
            </details>
        </section>
    </main>

    <div id="achievement-popup" class="popup">
        <img id="popup-image" src="" alt="Achievement">
        <div>
            <h3 id="popup-title"></h3>
            <p id="popup-description"></p>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../js/unlockAchievement-it.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
