<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-T0CTM2SBJJ"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() {
                dataLayer.push(arguments);
            }
            gtag("js", new Date());

            gtag("config", "G-T0CTM2SBJJ");
        </script>
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
            rel="stylesheet"
            integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
            crossorigin="anonymous"
        />
        <link rel="icon" href="../img/Susremaster.png" type="image/png" />
        <link rel="shortcut icon" href="../img/Susremaster.png" type="image/png" />
        <link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet" />
        <link rel="stylesheet" href="../css/style.css" />
        <link rel="stylesheet" href="../css/style-dark.css" />
        <link rel="stylesheet" href="../css/animations.css" />
        <script src="../js/animations.js"></script>
<script src="../js/richpresence.js"></script>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Cripsum™ - privacy</title>
        <style>
            img {
                border-radius: 10px;
            }
        </style>
    </head>

    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

        <div style="max-width: 1200px; margin: auto; padding-top: 7rem" class="testobianco">
            <div class="privacy">
                <h1 class="text-center fadeup" style="padding-top: 3%; font-weight: bolder">Informativa sulla Privacy</h1>
                <p class="fadeup">
                    Cripsum™ si impegna a proteggere la tua privacy. Questa informativa sulla privacy spiega come raccogliamo, utilizziamo, divulghiamo e proteggiamo le tue informazioni personali.
                </p>
                <h4 class="text-center fadeup" style="padding-top: 10px">Informazioni raccolte</h4>
                <p class="fadeup">Raccogliamo diverse tipologie di informazioni, tra cui:</p>
                <ul class="fadeup">
                    <li><p>Informazioni personali fornite dall'utente (nome, indirizzo email, ecc.).</p></li>
                    <li><p>Informazioni raccolte automaticamente (indirizzo IP, tipo di browser, ecc.).</p></li>
                </ul>
                <h4 class="text-center fadeup" style="padding-top: 10px">Uso delle informazioni</h4>
                <p class="fadeup">Utilizziamo le tue informazioni per:</p>
                <ul class="fadeup">
                    <li><p>Fornire e migliorare i nostri servizi.</p></li>
                    <li><p>Comunicare con te.</p></li>
                    <li><p>Personalizzare la tua esperienza.</p></li>
                </ul>
                <h4 class="text-center fadeup" style="padding-top: 10px">Divulgazione delle informazioni</h4>
                <p class="fadeup">
                    Non vendiamo, commerciamo o trasferiamo in altro modo le tue informazioni personali a terzi, eccetto nei casi necessari per adempiere ai nostri obblighi legali o proteggere i
                    nostri diritti.
                </p>
                <h4 class="text-center fadeup" style="padding-top: 10px">Protezione delle informazioni</h4>
                <p class="fadeup">Implementiamo una varietà di misure di sicurezza per mantenere la sicurezza delle tue informazioni personali.</p>
                <h4 class="text-center fadeup" style="padding-top: 10px">I tuoi diritti</h4>
                <p class="fadeup">
                    Hai il diritto di accedere, correggere o cancellare le tue informazioni personali. Per esercitare questi diritti, contattaci tramite i dettagli forniti sul nostro sito.
                </p>
                <h4 class="text-center fadeup" style="padding-top: 10px">Contatti</h4>
                <p class="fadeup">Per ulteriori informazioni sulla nostra informativa sulla privacy, contattaci all'indirizzo email: privacy@cripsum.com.</p>
            </div>
        </div>
        <footer class="my-5 pt-5 text-muted text-center text-small fadeup">
            <p class="mb-1 testobianco">Copyright © 2021-2025 Cripsum™. Tutti i diritti riservati.</p>
            <ul class="list-inline">
                <li class="list-inline-item"><a href="#" class="linkbianco">Privacy</a></li>
                <li class="list-inline-item"><a href="tos" class="linkbianco">Termini</a></li>
                <li class="list-inline-item"><a href="supporto" class="linkbianco">Supporto</a></li>
            </ul>
        </footer>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
