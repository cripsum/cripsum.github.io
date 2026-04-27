<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Linee guida chat</title>

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
                <span class="static-pill">Chat Globale</span>
                <h1>Linee guida</h1>
                <p>Regole semplici per tenere la chat leggibile e usabile da tutti.</p>
                <div class="static-actions">
                    <a href="global-chat" class="static-btn static-btn--primary">
                        <i class="fas fa-comments"></i>
                        <span>Torna alla chat</span>
                    </a>
                    <a href="supporto" class="static-btn">
                        <i class="fas fa-life-ring"></i>
                        <span>Supporto</span>
                    </a>
                </div>
            </div>

            <aside class="static-hero__side">
                <span class="static-chip"><i class="fas fa-shield-halved"></i> Moderazione attiva</span>
                <p>Violazioni gravi o ripetute possono portare a mute o ban.</p>
            </aside>
        </section>

        <section class="static-grid static-grid--2" style="margin-top:1rem;">
            <article class="static-card static-reveal">
                <h2>Rispetta tutti</h2>
                <p>Niente insulti, minacce o linguaggio offensivo.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Niente spam</h2>
                <p>Evita messaggi ripetuti, pubblicità e link messi a caso.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Contenuti adatti</h2>
                <p>Non inviare contenuti violenti, sessuali, illegali o chiaramente fuori contesto.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Non impersonare</h2>
                <p>Non fingere di essere un altro utente, un admin o un moderatore.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Usa buon senso</h2>
                <p>Non serve essere perfetti. Basta non rovinare la chat agli altri.</p>
            </article>

            <article class="static-card static-reveal">
                <h2>Segui i moderatori</h2>
                <p>Le indicazioni dello staff vanno rispettate.</p>
            </article>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
