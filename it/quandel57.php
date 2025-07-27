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
        <title>Cripsum™ - Quandel57</title>
    </head>

    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

        <div style="max-width: 600px; margin: auto; padding-top: 7rem" class="testobianco">
            <div id="carouselExampleIndicators" class="carousel slide carosellone fadeup">
                <div class="carousel-indicators">
                    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1" aria-label="Slide 2"></button>
                    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2" aria-label="Slide 3"></button>
                </div>
                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <img src="../img/post1.jpeg" class="d-block w-100" alt="..." />
                    </div>
                    <div class="carousel-item">
                        <img src="../img/post2.jpeg" class="d-block w-100" alt="..." />
                    </div>
                    <div class="carousel-item">
                        <img src="../img/post3.jpeg" class="d-block w-100" alt="..." />
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Indietro</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Avanti</span>
                </button>
            </div>
            <div class="d-flex justify-content-around image-container" style="max-width: 80%; margin: auto; padding-top: 5%">
                <div style="margin: auto; text-align: center" class="fadeup">
                    <img src="../img/comment.jpeg" class="commento" alt="" />
                    <a href="https://www.tiktok.com/@quandel57?_t=8hqm7exNcBd&_r=1" class="fs-5 text text-center linkbianco">il suo account TikTok</a><br />
                    <a href="https://vm.tiktok.com/ZGeR24Qev/" class="fs-5 text text-center linkbianco">il video con questo commento</a>
                </div>
            </div>
            <div class="d-flex justify-content-center image-container" style="max-width: 80%; margin: auto; padding-top: 5%">
                <div style="margin: auto; display: block">
                    <img src="../img/nerd3.gif" class="nerd fadeup" alt="" style="width: 40%; margin: 4%" />
                    <img src="../img/nerd2.gif" class="nerd fadeup" alt="" style="width: 40%; margin: 4%" />
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
        <embed src="../audio/screaminginpublicrestrooms.mp3" loop="true" autostart="true" width="2" height="0" />
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
