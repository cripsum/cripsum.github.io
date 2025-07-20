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
        <title>Cripsum™ - rimasti</title>
        <style>
            img {
                border-radius: 10px;
            }
        </style>
    </head>

    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

        <div style="max-width: 550px; margin: auto; padding-top: 7rem" class="testobianco">
            <h1 class="text-center fadein" style="padding-top: 3%; font-weight: bolder; color: red">classifica top 10 rimasti</h1>
            <div class="image-container" style="max-width: 80%; margin: auto; padding-top: 3%">
                <div style="margin: auto; padding-top: 1%">
                    <div class="image-description fadeup" style="margin-bottom: 2%; margin-left: 0">
                        <h3 class="text-center">Top 1.</h3>
                        <h5 class="text-center">WryCharles</h5>
                        <h6 class="text-center">
                            ha insistito una settimana per aiutarlo a scaricare spiderman 2 per pc nonostante non esista (è stato doxato e il suo pc è saltato in aria per colpa di un trojan)
                        </h6>
                    </div>
                    <img src="../img/wrycharles1.png" alt="WryCharles" class="img-fluid fadeup" style="display: block; margin-left: auto; margin-right: auto" />
                </div>
            </div>
            <hr class="rounded fadeuphr" />
            <div class="image-container" style="max-width: 80%; margin: auto; padding-top: 1%">
                <div style="margin: auto; padding-top: 1%">
                    <div class="image-description fadeup" style="margin-bottom: 2%">
                        <h3 class="text-center">Top 2.</h3>
                        <h5 class="text-center">Shin</h5>
                        <h6 class="text-center">insulta brawl stars dicendo che è mid, e poi guarda anime dalla mattina alla sera</h6>
                    </div>
                    <img src="../img/rimastotop2.png" alt="lulasorca" class="img-fluid fadeup" style="display: block; margin-left: auto; margin-right: auto" />
                </div>
            </div>
            <hr class="rounded fadeuphr" />
            <div class="image-container" style="max-width: 80%; margin: auto; padding-top: 3%">
                <div style="margin: auto; padding-top: 1%">
                    <div class="image-description fadeup" style="margin-bottom: 2%; margin-left: 0">
                        <h3 class="text-center">Top 3.</h3>
                        <h5 class="text-center">Lulasorca</h5>
                    </div>
                    <img src="../img/lulasorca.jpeg" alt="lulasorca" class="img-fluid fadeup" />
                </div>
            </div>

            <!--
            <hr class="rounded fadeuphr" />
            <div class="image-container" style="max-width: 80%; margin: auto; padding-top: 1%">
                <div style="margin: auto; padding-top: 1%">
                    <div class="image-description fadeup" style="margin-bottom: 2%">
                        <h3 class="text-center">Top 4.</h3>
                        <h5 class="text-center">Yumi</h5>
                        <h6 class="text-center">blud rosica per i rank 35 di 1nstxnct e poi fa i rank 30 in trio showdown</h6>
                    </div>
                    <div style="text-align: center" class="fadeup">
                        <audio controls>
                            <source src="../audio/rosica.mp3" type="audio/mpeg" />
                            Your browser does not support the audio element.
                        </audio>
                    </div>
                    <div id="carouselExampleIndicators" class="carousel slide mt-3 fadeup" data-bs-ride="carousel">
                        <div class="carousel-indicators">
                            <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                            <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1" aria-label="Slide 2"></button>
                            <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2" aria-label="Slide 3"></button>
                        </div>
                        <div class="carousel-inner">
                            <div class="carousel-item active">
                                <img src="../img/photo_2024-10-01_21-31-42.jpg" class="d-block w-100" alt="First slide" />
                            </div>
                            <div class="carousel-item">
                                <img src="../img/sticker2.webp" class="d-block w-100" alt="Second slide" />
                            </div>
                            <div class="carousel-item">
                                <img src="../img/sticker.webp" class="d-block w-100" alt="Third slide" />
                            </div>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">indietro</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">avanti</span>
                        </button>
                    </div>
                </div>
            </div>
            -->
            <hr class="rounded fadeuphr" style="margin-top: 2%" />
            <div class="image-container" style="max-width: 80%; margin: auto; padding-top: 3%">
                <div style="margin: auto; padding-top: 1%">
                    <div class="image-description fadeup" style="margin-bottom: 2%">
                        <h3 class="text-center">Top 4.</h3>
                        <h5 class="text-center">ancora da decidere</h5>
                    </div>
                    <img src="../img/segone4.png" alt="lulasorca" class="img-fluid fadeup" style="display: block; margin-left: auto; margin-right: auto" />
                </div>
            </div>
            <hr class="rounded fadeuphr" style="margin-top: 2%" />
            <div class="image-container" style="max-width: 80%; margin: auto; padding-top: 3%">
                <div style="margin: auto; padding-top: 1%">
                    <div class="image-description fadeup" style="margin-bottom: 2%">
                        <h3 class="text-center">Top 5.</h3>
                        <h5 class="text-center">ancora da decidere</h5>
                    </div>
                    <img src="../img/segone4.png" alt="lulasorca" class="img-fluid fadeup" style="display: block; margin-left: auto; margin-right: auto" />
                </div>
            </div>
            <hr class="rounded fadeuphr" />
            <div class="image-container" style="max-width: 80%; margin: auto; padding-top: 1%">
                <div style="margin: auto; padding-top: 1%">
                    <div class="image-description fadeup" style="margin-bottom: 2%">
                        <h3 class="text-center">Top 6.</h3>
                        <h5 class="text-center">ancora da decidere</h5>
                    </div>
                    <img src="../img/segone4.png" alt="lulasorca" class="img-fluid fadeup" style="display: block; margin-left: auto; margin-right: auto" />
                </div>
            </div>
            <hr class="rounded fadeuphr" />
            <div class="image-container" style="max-width: 80%; margin: auto; padding-top: 1%">
                <div style="margin: auto; padding-top: 1%">
                    <div class="image-description fadeup" style="margin-bottom: 2%">
                        <h3 class="text-center">Top 7.</h3>
                        <h5 class="text-center">ancora da decidere</h5>
                    </div>
                    <img src="../img/segone4.png" alt="lulasorca" class="img-fluid fadeup" style="display: block; margin-left: auto; margin-right: auto" />
                </div>
            </div>
            <hr class="rounded fadeuphr" />
            <div class="image-container" style="max-width: 80%; margin: auto; padding-top: 1%">
                <div style="margin: auto; padding-top: 1%">
                    <div class="image-description fadeup" style="margin-bottom: 2%">
                        <h3 class="text-center">Top 8.</h3>
                        <h5 class="text-center">ancora da decidere</h5>
                    </div>
                    <img src="../img/segone4.png" alt="lulasorca" class="img-fluid fadeup" style="display: block; margin-left: auto; margin-right: auto" />
                </div>
            </div>
            <hr class="rounded fadeuphr" />
            <div class="image-container" style="max-width: 80%; margin: auto; padding-top: 1%">
                <div style="margin: auto; padding-top: 1%">
                    <div class="image-description fadeup" style="margin-bottom: 2%">
                        <h3 class="text-center">Top 9.</h3>
                        <h5 class="text-center">ancora da decidere</h5>
                    </div>
                    <img src="../img/segone4.png" alt="lulasorca" class="img-fluid fadeup" style="display: block; margin-left: auto; margin-right: auto" />
                </div>
            </div>
            <hr class="rounded fadeuphr" />
            <div class="image-container" style="max-width: 80%; margin: auto; padding-top: 1%">
                <div style="margin: auto; padding-top: 1%">
                    <div class="image-description fadeup" style="margin-bottom: 2%">
                        <h3 class="text-center">Top 10.</h3>
                        <h5 class="text-center">ancora da decidere</h5>
                    </div>
                    <img src="../img/segone4.png" alt="lulasorca" class="img-fluid fadeup" style="display: block; margin-left: auto; margin-right: auto" />
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
