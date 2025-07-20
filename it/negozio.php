<?php
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
        <title>Cripsum™ - negozio</title>
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
            <div class="d-flex justify-content-center image-container" style="max-width: 80%; margin: auto; padding-top: 5%">
                <div style="margin-left: 2%; margin-right: 2%" class="fadeup">
                    <img src="../img/4090.jpg" class="immaginealberi ombra" alt="" />
                    <p class="fs-5 text text-center"><a href="checkout" class="linkbianco">RTX 4090 - 2499,99€</a></p>
                    <p class="text text-center">troppo potente anche per il gamings</p>
                </div>
                <div style="margin-left: 2%; margin-right: 2%" class="fadeup">
                    <img src="../img/iphone20.jpg" class="immaginealberi ombra" alt="" />
                    <p class="fs-5 text text-center"><a href="checkout" class="linkbianco">Iphone 20 - 2179,99€</a></p>
                    <p class="text text-center">il futuro, ottimo telefono, peccato per l'os</p>
                </div>
                <div style="margin-left: 2%; margin-right: 2%" class="fadeup">
                    <img src="../img/indica.jpg" class="immaginealberi ombra" alt="" />
                    <p class="fs-5 text text-center"><a href="checkout" class="linkbianco">tua madre - 2,49€</a></p>
                    <p class="text text-center">se acquistate entro il 2025 sconto del 40%</p>
                </div>
                <div style="margin-left: 2%; margin-right: 2%" class="fadeup">
                    <img src="../img/s30.jpg" class="immaginealberi ombra" alt="" />
                    <p class="fs-5 text text-center"><a href="checkout" class="linkbianco">Samsung galaxy s30 - 1599,99€</a></p>
                    <p class="text text-center">
                        16 gb ram - 1tb memoria - batteria: 7000mAh<br />
                        display 4k 144hz - zoom ottico x100 con <br />
                        sensore principale da 12482345 MP
                    </p>
                </div>
                <div style="margin-left: 2%; margin-right: 2%" class="fadeup">
                    <img src="../img/ps6.jpg" class="immaginealberi ombra" alt="" />
                    <p class="fs-5 text text-center"><a href="checkout" class="linkbianco">Ps6 - 849,49€</a></p>
                    <p class="text text-center">
                        con ps6 potrete giocare a gta5 <br />
                        remastered in 8k 120fps
                    </p>
                </div>
                <div style="margin-left: 2%; margin-right: 2%" class="fadeup">
                    <img src="../img/monitor540.jpg" class="immaginealberi ombra" alt="" />
                    <p class="fs-5 text text-center"><a href="checkout" class="linkbianco">Monitor 540hz - 949,69€</a></p>
                    <p class="text text-center">fluidità estrema (completamente inutile)</p>
                </div>
                <div style="margin-left: 2%; margin-right: 2%" class="fadeup">
                    <img src="../img/renegade.jpg" class="immaginealberi ombra" alt="" />
                    <p class="fs-5 text text-center"><a href="checkout" class="linkbianco">renegade raider - 429,99€</a></p>
                    <p class="text text-center">
                        la skin più og di tutte, ma anche <br />
                        la più costosa
                    </p>
                </div>
                <div style="margin-left: 2%; margin-right: 2%" class="fadeup">
                    <img src="../img/tavoletta.jpg" class="immaginealberi ombra" alt="" />
                    <p class="fs-5 text text-center"><a href="checkout" class="linkbianco">Tavoletta grafica - 119,89€</a></p>
                    <p class="text text-center">accessori per osu</p>
                </div>
                <div style="margin-left: 2%; margin-right: 2%" class="fadeup">
                    <img src="../img/tastiera2tasti.jpg" class="immaginealberi ombra" alt="" />
                    <p class="fs-5 text text-center"><a href="checkout" class="linkbianco">tastiera per osu - 44,99€</a></p>
                    <p class="text text-center">accessori per osu</p>
                </div>
            </div>
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
