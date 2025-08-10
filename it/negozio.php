<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
?>
<!DOCTYPE html>

<html lang="en">
    <head>
        <?php include '../includes/head-import.php'; ?>
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
        
        <div style="max-width: 1920px; margin: auto; padding-top: 7rem; padding-bottom: 4rem;" class="testobianco">
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
        <?php include '../includes/footer.php'; ?>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
