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
    <title>Cripsum™ - Supporto</title>

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
                <span class="static-pill">Supporto</span>
                <h1>Serve aiuto?</h1>
                <p>Qui trovi contatti e risposte rapide ai problemi più comuni.</p>

                <?php if ($isLogged): ?>
                    <div class="static-alert static-alert--success" style="margin-top:1rem;">
                        <i class="fas fa-user-check"></i>
                        <p>Stai scrivendo come <strong><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></strong>. Se segnali un problema, includi anche il tuo username.</p>
                    </div>
                <?php endif; ?>
            </div>

            <aside class="static-hero__side">
                <span class="static-chip"><i class="fas fa-clock"></i> Risposta non immediata</span>
                <p>Scrivi in modo chiaro. Aiuta a risolvere prima.</p>
            </aside>
        </section>

        <section class="static-grid static-grid--2" style="margin-top:1rem;">
            <article class="static-contact-card static-reveal">
                <h2>Contatti</h2>
                <div class="static-grid">
                    <a href="mailto:sburra@cripsum.com" class="static-contact-link">
                        <i class="fas fa-envelope"></i>
                        <span>Email: sburra@cripsum.com</span>
                    </a>
                    <a href="#" class="static-contact-link">
                        <i class="fab fa-telegram"></i>
                        <span>Telegram: @cripsum</span>
                    </a>
                    <a href="#" class="static-contact-link">
                        <i class="fab fa-discord"></i>
                        <span>Discord: @cripsum</span>
                    </a>
                    <a href="#" class="static-contact-link">
                        <i class="fab fa-instagram"></i>
                        <span>Instagram: @cripsum</span>
                    </a>
                </div>
            </article>

            <article class="static-card static-reveal">
                <h2>Prima di scrivere</h2>
                <ul>
                    <li>Spiega cosa stavi facendo.</li>
                    <li>Allega uno screenshot se serve.</li>
                    <li>Scrivi browser, dispositivo e pagina coinvolta.</li>
                    <li>Se hai un account, indica lo username.</li>
                </ul>
            </article>
        </section>

        <section class="static-faq static-reveal" id="supportFaq" style="margin-top:1rem;">
            <h2>Domande rapide</h2>

            <label class="static-faq-search">
                <i class="fas fa-search"></i>
                <input type="search" placeholder="Cerca nelle FAQ..." data-static-faq-search="#supportFaq">
            </label>

            <details class="static-faq-item">
                <summary>Non riesco ad accedere</summary>
                <p>Prova il recupero password. Se non ricevi email, controlla spam o scrivi al supporto.</p>
            </details>

            <details class="static-faq-item">
                <summary>La lootbox non salva qualcosa</summary>
                <p>Controlla di essere loggato. Poi prova refresh e verifica che la sessione non sia scaduta.</p>
            </details>

            <details class="static-faq-item">
                <summary>Vedo un bug grafico</summary>
                <p>Fai refresh forzato e svuota la cache. Se resta, manda screenshot e nome pagina.</p>
            </details>

            <details class="static-faq-item">
                <summary>Come segnalo un utente?</summary>
                <p>Usa gli strumenti presenti nella pagina, se disponibili. Altrimenti scrivi al supporto con username e motivo.</p>
            </details>

            <p class="static-empty" data-static-faq-empty>Nessun risultato trovato.</p>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
