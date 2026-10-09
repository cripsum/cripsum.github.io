<?php
require_once '../config/session_init.php';
require_once '../includes/theme.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
?>
<!DOCTYPE html>
<html lang="it"<?= cripsum_theme_html_attr() ?>>
<head>
    <?php include '../includes/head-import.php'; ?>
    <?php cripsum_theme_head(); ?>
    <title>Cripsum™ - Linee guida chat</title>

    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="<?= cripsum_theme_asset('/assets/static/static.css') ?>">
    <script src="/assets/static/static.js?v=1.0-static" defer></script>

</head>

<body class="static-page">
    <?php include '../includes/navbar.php'; ?>
    


    <div class="static-bg" aria-hidden="true">
        <span class="static-orb static-orb--one"></span>
        <span class="static-orb static-orb--two"></span>
        <span class="static-grid-bg"></span>
    </div>


    <main class="static-shell">
        <section class="static-hero static-hero--split static-reveal">
            <div>
                <h1>Linee guida</h1>
                <p>Regole semplici per tenere la chat leggibile e sicura per tutti. Fanno parte dei <a href="tos">Termini di servizio</a>.</p>
                <div class="static-actions">
                    <a href="global-chat" class="static-btn static-btn--primary">
                        <i class="fa-solid fa-comments"></i>
                        <span>Torna alla chat</span>
                    </a>
                    <a href="supporto" class="static-btn">
                        <i class="fa-solid fa-life-ring"></i>
                        <span>Supporto</span>
                    </a>
                </div>
            </div>

            <aside class="static-hero__side">
                <span class="static-chip"><i class="fa-solid fa-shield-halved"></i> Moderazione attiva</span>
                <p>Violazioni gravi o ripetute possono portare a mute, sospensione o ban.</p>
            </aside>
        </section>

        <section class="static-grid static-grid--2" style="margin-top:1rem;">
            <article class="static-card static-reveal">
                <h2>Rispetta tutti</h2>
                <p>Niente insulti, minacce, discriminazioni o linguaggio offensivo.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Niente bullismo</h2>
                <p>Prendere di mira qualcuno è vietato, anche «per scherzo». Se succede a te, scrivi a <a href="mailto:tos@cripsum.com">tos@cripsum.com</a> con oggetto «Cyberbullismo»: rispondiamo entro 24 ore.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Niente dati degli altri</h2>
                <p>Non pubblicare foto, numeri di telefono, indirizzi o altri dati di altre persone senza il loro consenso.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Contenuti adatti</h2>
                <p>In chat ci sono anche minorenni: niente contenuti sessuali, violenti, illegali o chiaramente fuori contesto.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Niente spam</h2>
                <p>Evita messaggi ripetuti, pubblicità e link messi a caso.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Allegati sicuri</h2>
                <p>Niente malware, link sospetti o materiale protetto da diritto d'autore senza permesso.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Non impersonare</h2>
                <p>Non fingere di essere un altro utente, un admin, un moderatore o una persona reale.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Chat private e di gruppo</h2>
                <p>Valgono le stesse regole. Lo staff non legge queste chat: vede solo i messaggi che un partecipante segnala. Se qualcuno ti dà fastidio puoi segnalare il messaggio dal suo menu, bloccare la persona e, se serve, aprire un ticket.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Messaggi da chi non conosci</h2>
                <p>Chi non è tra i tuoi amici ti scrive come richiesta: finché non accetti o rispondi può mandare solo pochi messaggi. Dalla Privacy della chat puoi decidere che ti scrivano solo gli amici.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Segnala</h2>
                <p>Usa «Segnala» sui messaggi, in chat globale come nelle chat private e di gruppo. Ogni segnalazione la guarda una persona dello staff; chi ha scritto il messaggio non viene avvisato.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Segui i moderatori</h2>
                <p>Le indicazioni dello staff vanno rispettate. Se non sei d'accordo con una decisione, puoi contestarla come spiegato nei Termini.</p>
            </article>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
