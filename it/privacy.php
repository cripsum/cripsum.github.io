<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$lastUpdated = 'Aprile 2026';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Privacy</title>

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
        <section class="static-hero static-reveal">
            <span class="static-pill">Privacy</span>
            <h1>Informativa sulla Privacy</h1>
            <p>Qui trovi come vengono gestite le informazioni personali su Cripsum™.</p>
            <div class="static-meta">
                <span class="static-chip"><i class="fas fa-calendar"></i> Aggiornata: <?php echo htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="static-chip"><i class="fas fa-shield-halved"></i> Dati e sicurezza</span>
            </div>
        </section>

        <div class="static-layout">
            <aside class="static-toc static-reveal">
                <h2>Indice</h2>
                <a href="#raccolta">Informazioni raccolte</a>
                <a href="#uso">Uso delle informazioni</a>
                <a href="#divulgazione">Divulgazione</a>
                <a href="#protezione">Protezione</a>
                <a href="#diritti">I tuoi diritti</a>
                <a href="#contatti">Contatti</a>
            </aside>

            <div class="static-content">
                <section class="static-legal-section static-reveal" id="raccolta">
                    <h2>Informazioni raccolte</h2>
                    <p>Raccogliamo solo le informazioni necessarie per far funzionare il sito e il tuo account.</p>
                    <ul>
                        <li>Informazioni fornite dall’utente, come nome utente ed email.</li>
                        <li>Informazioni tecniche raccolte automaticamente, come indirizzo IP, browser e dati di sessione.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="uso">
                    <h2>Uso delle informazioni</h2>
                    <p>Usiamo le informazioni per gestire il sito, migliorare i servizi e comunicare con te quando serve.</p>
                    <ul>
                        <li>Fornire e migliorare le funzioni del sito.</li>
                        <li>Gestire account, login e sicurezza.</li>
                        <li>Personalizzare alcune parti dell’esperienza.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="divulgazione">
                    <h2>Divulgazione delle informazioni</h2>
                    <p>Non vendiamo i tuoi dati personali. Possiamo condividerli solo se necessario per obblighi legali o per proteggere i nostri diritti.</p>
                </section>

                <section class="static-legal-section static-reveal" id="protezione">
                    <h2>Protezione delle informazioni</h2>
                    <p>Usiamo misure tecniche e organizzative per proteggere le informazioni personali da accessi non autorizzati.</p>
                </section>

                <section class="static-legal-section static-reveal" id="diritti">
                    <h2>I tuoi diritti</h2>
                    <p>Puoi chiedere accesso, correzione o cancellazione dei tuoi dati personali, nei limiti previsti dalla legge.</p>
                </section>

                <section class="static-legal-section static-reveal" id="contatti">
                    <h2>Contatti</h2>
                    <p>Per domande sulla privacy puoi scrivere a <a href="mailto:privacy@cripsum.com">privacy@cripsum.com</a>.</p>
                </section>
            </div>
        </div>
    </main>

    <button class="static-top-btn" id="staticBackTop" type="button" aria-label="Torna su">
        <i class="fas fa-arrow-up"></i>
    </button>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
