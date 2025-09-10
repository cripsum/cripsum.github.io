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

        <div style="max-width: 1920px; margin: auto; padding-top: 7rem; padding-bottom: 4rem;" class="testobianco">
            <div class="shitposttext">
                <p class="fs-4 text mt-3 fadeup" style="text-align: center">
                    Hey, hai dei meme o contenuti shitpost che sarebbero perfetti per questa pagina? manda tutto via e-mail a
                    <a href="mailto:sburra@cripsum.com" class="linkbianco">dio.covid@gmail.com</a> inserendo anche il tuo username, ti verranno dati i crediti per aver contribuito.
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
            <div class="d-flex justify-content-center image-container" style="max-width: 80%; margin: auto; padding-top: 1%">
                <div class="fadeup">
                    <img src="../img/sossio.png" class="ombra" alt="" style="margin: auto; max-width: 200px" />
                </div>
                <div class="fadeup">
                    <br />
                    <p class="fs-5" style="text-align: center; font-weight: normal; max-width: 700px">Lui è sossio, uno sviluppatore di mod per mario kart. è anche un grande giocatore di wuthering waves e si fa tante seghe, qui lo avete una sua immagine mentre se la chilla con i piedi all'aria</p>
                </div>
            </div>
            <div style="margin: auto; max-width: 80%; text-align: center" class="fadeup">
                <a style="text-align: center">by</a>
                <a style="font-weight: bolder">lacly</a>
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
