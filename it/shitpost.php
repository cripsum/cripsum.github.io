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
        <title>Cripsum™ - shitpost</title>
        <style>
            img {
                border-radius: 10px;
            }
        </style>
    </head>

    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

        <div style="max-width: 1920px; margin: auto; padding-top: 7rem" class="testobianco">
            <div class="shitposttext">
                <p class="fs-4 text mt-3 fadeup" style="text-align: center">
                    Hey, hai dei meme o contenuti shitpost che sarebbero perfetti per questa pagina? manda tutto via e-mail a
                    <a href="mailto:sburra@cripsum.com" class="linkbianco">shitpost@cripsum.com</a> inserendo anche il tuo username, ti verranno dati i crediti per aver contribuito.
                </p>
                <p class="fs-5 text mt-3 fadeup" style="font-weight: bold; text-align: center">grazie in anticipo.</p>
            </div>
            <hr class="rounded fadeuphr" />
            <div class="d-flex justify-content-around image-container" style="max-width: 80%; margin: auto; padding-top: 1%">
                <div class="fadeup">
                    <br />
                    <p class="fs-2" style="font-weight: bolder; text-align: center">questa è la magia</p>
                    <p class="fs-5" style="font-weight: bold; text-align: center">la magia del natale</p>
                    <p class="fs-6" style="font-weight: bold; text-align: center">quella vera</p>
                </div>
                <div class="immagineshit1 fadeup">
                    <img class="immagineshit1 ombra" src="../img/beans.jpg" alt="" />
                </div>
                <div class="fadeup">
                    <img src="../img/saltellante.gif" class="ombra" alt="" style="margin: auto; max-width: 100%" />
                </div>
            </div>
            <div style="margin: auto; max-width: 80%; text-align: center" class="fadeup">
                <a style="text-align: center">by</a>
                <a style="font-weight: bolder">cripsum</a>
            </div>
            <hr class="rounded fadeuphr" />
            <div class="d-flex justify-content-center image-container" style="max-width: 80%; margin: auto; padding-top: 1%">
                <div class="dametucosita">
                    <p class="fs-5 fadeup" style="font-weight: bold; text-align: center">le mie palle quando:</p>
                    <p class="fs-6 mt-2 fadeup" style="font-weight: bold; text-align: center">ma soprattutto il mio culetto quando:</p>
                    <img style="display: block" class="ombra fadeup" src="../img/tengodiarrea.jpg" alt="" />
                    <img src="../img/cesso.gif" class="ombra fadeup" alt="" />
                </div>
                <div class="mt-4 fadeup">
                    <img class="dametucosita2 ombra" src="../img/dametucositait.gif" alt="" />
                </div>
            </div>
            <div style="margin: auto; max-width: 80%; text-align: center" class="fadeup">
                <a style="text-align: center">by</a>
                <a style="font-weight: bolder">sk8ing ray</a>
            </div>
            <hr class="rounded fadeuphr" />
        </div>
        <footer class="my-5 pt-5 text-muted text-center text-small fadeup">
            <p class="mb-1 testobianco">Copyright © 2021-2025 Cripsum™. Tutti i diritti riservati.</p>
            <ul class="list-inline">
                <li class="list-inline-item"><a href="privacy" class="linkbianco">Privacy</a></li>
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
