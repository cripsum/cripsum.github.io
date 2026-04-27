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
    <title>Cripsum™ - Termini</title>

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
            <span class="static-pill">Termini</span>
            <h1>Termini e Condizioni</h1>
            <p>Usando Cripsum™ accetti queste regole. Sono scritte in modo più leggibile, senza cambiare il senso.</p>
            <div class="static-meta">
                <span class="static-chip"><i class="fas fa-calendar"></i> Aggiornati: <?php echo htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="static-chip"><i class="fas fa-scale-balanced"></i> Regole del sito</span>
            </div>
        </section>

        <div class="static-layout">
            <aside class="static-toc static-reveal">
                <h2>Indice</h2>
                <a href="#uso">Uso dei servizi</a>
                <a href="#account">Account</a>
                <a href="#eta">Età e responsabilità</a>
                <a href="#proprieta">Proprietà intellettuale</a>
                <a href="#responsabilita">Responsabilità</a>
                <a href="#modifiche">Modifiche</a>
                <a href="#contatti">Contatti</a>
            </aside>

            <div class="static-content">
                <section class="static-legal-section static-reveal" id="uso">
                    <h2>Uso dei servizi</h2>
                    <p>Devi usare Cripsum™ solo per scopi legali e nel rispetto delle leggi applicabili.</p>
                    <ul>
                        <li>Non usare il sito per attività illegali o non autorizzate.</li>
                        <li>Non inviare spam, contenuti dannosi o comportamenti che rovinano l’esperienza agli altri.</li>
                    </ul>
                </section>

                <section class="static-legal-section static-reveal" id="account">
                    <h2>Account dell’utente</h2>
                    <p>Se crei un account, sei responsabile della sicurezza delle tue credenziali e delle attività fatte dal tuo account.</p>
                </section>

                <section class="static-legal-section static-reveal" id="eta">
                    <h2>Età e responsabilità</h2>
                    <p>Dichiarando di avere 18 anni o più, confermi di essere maggiorenne secondo le leggi del tuo paese e responsabile delle tue azioni.</p>
                    <p>Se sei minorenne, devi avere il consenso di un genitore o tutore legale prima di usare i servizi.</p>
                    <p>L’utente è responsabile dell’uso dei servizi e delle conseguenze che ne derivano.</p>
                </section>

                <section class="static-legal-section static-reveal" id="proprieta">
                    <h2>Proprietà intellettuale</h2>
                    <p>Testi, immagini, loghi e software presenti su Cripsum™ appartengono a Cripsum™ o ai rispettivi titolari e sono protetti dalle leggi sul diritto d’autore.</p>
                </section>

                <section class="static-legal-section static-reveal" id="responsabilita">
                    <h2>Limitazione di responsabilità</h2>
                    <p>Cripsum™ non è responsabile per danni diretti, indiretti, incidentali o consequenziali derivanti dall’uso dei servizi.</p>
                </section>

                <section class="static-legal-section static-reveal" id="modifiche">
                    <h2>Modifiche ai termini</h2>
                    <p>Possiamo modificare questi termini. Le modifiche saranno pubblicate sul sito e, se importanti, comunicate tramite email.</p>
                </section>

                <section class="static-legal-section static-reveal" id="contatti">
                    <h2>Contatti</h2>
                    <p>Per domande sui termini puoi scrivere a <a href="mailto:tos@cripsum.com">tos@cripsum.com</a>.</p>
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
