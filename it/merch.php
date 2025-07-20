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
        <title>Cripsum™ - merch</title>
        <style>
            img {
                border-radius: 10px;
            }
            .dropdownlingua {
                background: linear-gradient(135deg, rgba(214, 187, 32, 0.5), rgb(99, 85, 31)); /* Sfondo trasparente */
            }

            .dropdownutenti .dropdown-menu {
                background: linear-gradient(135deg, rgba(214, 187, 32, 0.5), rgb(99, 85, 31)); /* Sfondo trasparente */
            }
        </style>
    </head>

    <body style="background-color: #bb930f; padding-top: 7rem" class="testobianco">
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

        <div class="paginaintera">
            <p class="text-center fadein" style="font-size: 50px; font-weight: bolder; margin-top: 20px">NEW MERCH OUT NOW</p>
            <p class="text-center fadein" style="font-size: 50px">🤑🐦📸</p>
            <div class="d-flex justify-content-around image-container" style="max-width: 80%; margin: auto; padding-top: 5%">
                <div style="margin-top: 10px; margin-bottom: 5%" class="fadeup">
                    <img src="../img/magliag.jpg" class="immaginealberi ombra" alt="" />
                    <p class="text text-center" style="font-size: 18px">
                        <a href="merch-checkout" class="linkbianco">T-Shirt simonetussi.ph - big logo<br />19,99€</a>
                    </p>
                </div>
                <div style="margin-top: 10px; margin-bottom: 5%" class="fadeup">
                    <img src="../img/magliap.jpg" class="immaginealberi ombra" alt="" />
                    <p class="text text-center" style="font-size: 18px">
                        <a href="merch-checkout" class="linkbianco">T-Shirt simonetussi.ph - small logo<br />19,99€</a>
                    </p>
                </div>
                <div style="margin-top: 10px; margin-bottom: 5%" class="fadeup">
                    <img src="../img/felpag.jpg" class="immaginealberi ombra" alt="" />
                    <p class="text text-center" style="font-size: 18px">
                        <a href="merch-checkout" class="linkbianco">Felpa simonetussi.ph - big logo<br />39,99€</a>
                    </p>
                </div>
                <div style="margin-top: 10px; margin-bottom: 5%" class="fadeup">
                    <img src="../img/felpap.jpg" class="immaginealberi ombra" alt="" />
                    <p class="text text-center" style="font-size: 18px">
                        <a href="merch-checkout" class="linkbianco">Felpa simonetussi.ph - small logo<br />39,99€</a>
                    </p>
                </div>
                <div style="margin-top: 10px; margin-bottom: 5%" class="fadeup">
                    <img src="../img/pantaloncini.jpg" class="immaginealberi ombra" alt="" />
                    <p class="text text-center" style="font-size: 18px">
                        <a href="merch-checkout" class="linkbianco">Pantaloncini simonetussi.ph<br />23,99€</a>
                    </p>
                </div>
                <div style="margin-top: 10px; margin-bottom: 5%" class="fadeup">
                    <img src="../img/calze.jpg" class="immaginealberi ombra" alt="" />
                    <p class="text text-center" style="font-size: 18px">
                        <a href="merch-checkout" class="linkbianco">calzini simonetussi.ph <br />5,99€</a>
                    </p>
                </div>
                <div style="margin-top: 10px; margin-bottom: 5%" class="fadeup">
                    <img src="../img/boxers.jpg" class="immaginealberi ombra" alt="" />
                    <p class="text text-center" style="font-size: 18px">
                        <a href="merch-checkout" class="linkbianco">boxer simonetussi.ph <br />149,99€</a>
                    </p>
                </div>
                <div style="margin-top: 10px; margin-bottom: 5%" class="fadeup">
                    <img src="../img/mutandinesexi.jpg" class="immaginealberi ombra" alt="" />
                    <p class="text text-center" style="font-size: 18px">
                        <a href="merch-checkout" class="linkbianco">slip simonetussi.ph <br />249,99€</a>
                    </p>
                </div>
                <div style="margin-top: 10px; margin-bottom: 5%" class="fadeup">
                    <img src="../img/cappellino.jpg" class="immaginealberi ombra" alt="" />
                    <p class="text text-center" style="font-size: 18px">
                        <a href="merch-checkout" class="linkbianco">cappellino simonetussi.ph <br />7,99€</a>
                    </p>
                </div>
                <div style="margin-top: 10px; margin-bottom: 5%" class="fadeup">
                    <img src="../img/occhialis.jpg" class="immaginealberi ombra" alt="" />
                    <p class="text text-center" style="font-size: 18px">
                        <a href="merch-checkout" class="linkbianco">occhiali da sole simonetussi.ph <br />8,99€</a>
                    </p>
                </div>
                <div style="margin-top: 10px; margin-bottom: 5%" class="fadeup">
                    <img src="../img/occhialiv.jpg" class="immaginealberi ombra" alt="" />
                    <p class="text text-center" style="font-size: 18px">
                        <a href="merch-checkout" class="linkbianco">occhiali da vista simonetussi,ph <br />35,99€</a>
                    </p>
                </div>
                <div style="margin-top: 10px; margin-bottom: 5%" class="fadeup">
                    <img src="../img/tostapane.jpg" class="immaginealberi ombra" alt="" />
                    <p class="text text-center" style="font-size: 18px">
                        <a href="merch-checkout" class="linkbianco">tostapane simonetussi.ph <br />79,99€</a>
                    </p>
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
