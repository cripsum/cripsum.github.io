<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
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
        <link rel="icon" href="../../img/Susremaster.png" type="image/png" />
        <link rel="shortcut icon" href="../../img/Susremaster.png" type="image/png" />
        <link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet" />
        <link rel="stylesheet" href="../../css/style.css" />
        <link rel="stylesheet" href="../../css/style-dark.css" />
        <link rel="stylesheet" href="../../css/animations.css" />
        <script src="../../js/animations.js"></script>
        <script src="../../js/richpresence.js"></script>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Cripsum™ - osu</title>
    </head>
    <body>
        <?php include '../../includes/navbar.php'; ?>
        <?php include '../../includes/impostazioni.php'; ?>

        <div class="paginainteradownload testobianco" style="padding-top: 7rem">
            <div style="margin: auto; max-width: 90%" class="d-flex justify-content-around flex-wrap mt-5">
                <img class="img-fluid imagi ombra fadeup" src="../../img/osu.jpg" alt="" style="display: inline; border-radius: 10px" />
                <div class="float-end titolo fadeup">
                    <p class="fs-1 text mt-3 text-center" style="font-weight: bold">Osu!</p>
                    <p class="fs-5 text mt-3 text-center">il gioco ritmico per scemotti</p>
                    <p class="fs-5 text mt-3 text-center" style="color: red">hai il cancro o vuoi averlo? clicca il pulsante qui sotto e scarica osu!</p>
                </div>
            </div>
            <div class="button-container mt-5 fadeup" style="text-align: center; margin-top: 3%">
                <button class="btn btn-secondary bottone" type="button">
                    <a class="testobianco" href="https://github.com/ppy/osu/releases/latest/download/install.exe">Clicca qui per scaricare Osu!</a>
                </button>
            </div>
            <br />
            <p class="text text-center fadeup" style="font-size: smaller">il download dovrebbe partire automaticamente dopo aver clickato</p>
        </div>
        <footer class="my-5 pt-5 text-muted text-center text-small fadeup">
            <p class="mb-1 testobianco">Copyright © 2021-2025 Cripsum™. Tutti i diritti riservati.</p>
            <ul class="list-inline">
                <li class="list-inline-item"><a href="../privacy" class="linkbianco">Privacy</a></li>
                <li class="list-inline-item"><a href="../tos" class="linkbianco">Termini</a></li>
                <li class="list-inline-item"><a href="../supporto" class="linkbianco">Supporto</a></li>
            </ul>
        </footer>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../../js/modeChanger.js"></script>
    </body>
</html>
