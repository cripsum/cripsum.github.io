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
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());

            gtag('config', 'G-T0CTM2SBJJ');
        </script>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
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
        <title>Cripsum™ - tiktokpedia</title>
        <style>
            img {
                border-radius: 7px;
            }
        </style>
    </head>

    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

        <div class="container mt-5 testobianco fadeup" style=" margin: auto; padding-top: 7rem" >
            <h4 class="text-center">attenzione, la pagina è ancora in fase di creazione, è pertanto costituita esclusivamente da dei placeholder, godetevi questa musica per il momento :)</h1>
            <br /><br />
            <h1 class="text-center">TikTokPedia</h1>
            <p class="text-center">Benvenuto nella TikTokPedia, la tua enciclopedia di TikTok!</p>
        </div>
        <div class="tiktokpedia testobianco">
            <div class="d-flex justify-content-around image-container container-tiktokpedia ">
                <div class="card ombra fadeup" style="width: 18rem; margin-bottom: 30px;">
                    <img src="../img/pfp choso2 cc.png" class="card-img-top" alt="Character Photo" />
                    <div class="card-body testobianco">
                        <h5 class="card-title">Cripsum™</h5>
                        <p class="card-text">il re del congo ed il miglior editor al mondo (no non è vero)</p>
                        <button class="btn btn-secondary bottone" type="button">
                            <a href="https://www.tiktok.com/@cripsum" class="testobianco">account tiktok</a>
                        </button>
                    </div>
                </div>
                <div class="card ombra fadeup" style="width: 18rem; margin-bottom: 30px">
                    <img src="../img/foto_nino.jpg" class="card-img-top" alt="Character Photo" />
                    <div class="card-body testobianco">
                        <h5 class="card-title">napoloris222</h5>
                        <p class="card-text">Nino e Napo, i 2 boss di brawl stars italia (ti si ama Nino)</p>
                        <button class="btn btn-secondary bottone" type="button">
                            <a href="https://www.tiktok.com/@napoloris222" class="testobianco">account tiktok</a>
                        </button>
                    </div>
                </div>
                <div class="card ombra fadeup" style="width: 18rem; margin-bottom: 30px">
                    <img src="../img/sahe.jpg" class="card-img-top" alt="Character Photo" />
                    <div class="card-body testobianco">
                        <h5 class="card-title">Lorem ipsum</h5>
                        <p class="card-text">Lorem ipsum dolor sit amet</p>
                        <button class="btn btn-secondary bottone" type="button">
                            <a href="https://www.tiktok.com/@" class="testobianco">Lorem ipsum</a>
                        </button>
                    </div>
                </div>
                <div class="card ombra fadeup" style="width: 18rem; margin-bottom: 30px">
                    <img src="../img/sahe.jpg" class="card-img-top" alt="Character Photo" />
                    <div class="card-body testobianco">
                        <h5 class="card-title">Lorem ipsum</h5>
                        <p class="card-text">Lorem ipsum dolor sit amet</p>
                        <button class="btn btn-secondary bottone" type="button">
                            <a href="https://www.tiktok.com/@" class="testobianco">Lorem ipsum</a>
                        </button>
                    </div>
                </div>
                <div class="card ombra fadeup" style="width: 18rem; margin-bottom: 30px">
                    <img src="../img/sahe.jpg" class="card-img-top" alt="Character Photo" />
                    <div class="card-body testobianco">
                        <h5 class="card-title">Lorem ipsum</h5>
                        <p class="card-text">Lorem ipsum dolor sit amet</p>
                        <button class="btn btn-secondary bottone" type="button">
                            <a href="https://www.tiktok.com/@" class="testobianco">Lorem ipsum</a>
                        </button>
                    </div>
                </div>
                <div class="card ombra fadeup" style="width: 18rem; margin-bottom: 30px">
                    <img src="../img/sahe.jpg" class="card-img-top" alt="Character Photo" />
                    <div class="card-body testobianco">
                        <h5 class="card-title">Lorem ipsum</h5>
                        <p class="card-text">Lorem ipsum dolor sit amet</p>
                        <button class="btn btn-secondary bottone" type="button">
                            <a href="https://www.tiktok.com/@" class="testobianco">Lorem ipsum</a>
                        </button>
                    </div>
                </div>
                <div class="card ombra fadeup" style="width: 18rem; margin-bottom: 30px">
                    <img src="../img/sahe.jpg" class="card-img-top" alt="Character Photo" />
                    <div class="card-body testobianco">
                        <h5 class="card-title">Lorem ipsum</h5>
                        <p class="card-text">Lorem ipsum dolor sit amet</p>
                        <button class="btn btn-secondary bottone" type="button">
                            <a href="https://www.tiktok.com/@" class="testobianco">Lorem ipsum</a>
                        </button>
                    </div>
                </div>
                <div class="card ombra fadeup" style="width: 18rem; margin-bottom: 30px">
                    <img src="../img/sahe.jpg" class="card-img-top" alt="Character Photo" />
                    <div class="card-body testobianco">
                        <h5 class="card-title">Lorem ipsum</h5>
                        <p class="card-text">Lorem ipsum dolor sit amet</p>
                        <button class="btn btn-secondary bottone" type="button">
                            <a href="https://www.tiktok.com/@" class="testobianco">Lorem ipsum</a>
                        </button>
                    </div>
                </div>
                <div class="card ombra fadeup" style="width: 18rem; margin-bottom: 30px">
                    <img src="../img/sahe.jpg" class="card-img-top" alt="Character Photo" />
                    <div class="card-body testobianco">
                        <h5 class="card-title">Lorem ipsum</h5>
                        <p class="card-text">Lorem ipsum dolor sit amet</p>
                        <button class="btn btn-secondary bottone" type="button">
                            <a href="https://www.tiktok.com/@" class="testobianco">Lorem ipsum</a>
                        </button>
                    </div>
                </div>
                <div class="card ombra fadeup" style="width: 18rem; margin-bottom: 30px">
                    <img src="../img/sahe.jpg" class="card-img-top" alt="Character Photo" />
                    <div class="card-body testobianco">
                        <h5 class="card-title">Lorem ipsum</h5>
                        <p class="card-text">Lorem ipsum dolor sit amet</p>
                        <button class="btn btn-secondary bottone" type="button">
                            <a href="https://www.tiktok.com/@" class="testobianco">Lorem ipsum</a>
                        </button>
                    </div>
                </div>
                <div class="card ombra fadeup" style="width: 18rem; margin-bottom: 30px">
                    <img src="../img/sahe.jpg" class="card-img-top" alt="Character Photo" />
                    <div class="card-body testobianco">
                        <h5 class="card-title">Lorem ipsum</h5>
                        <p class="card-text">Lorem ipsum dolor sit amet</p>
                        <button class="btn btn-secondary bottone" type="button">
                            <a href="https://www.tiktok.com/@" class="testobianco">Lorem ipsum</a>
                        </button>
                    </div>
                </div>
                <div class="card ombra fadeup" style="width: 18rem; margin-bottom: 30px">
                    <img src="../img/sahe.jpg" class="card-img-top" alt="Character Photo" />
                    <div class="card-body testobianco">
                        <h5 class="card-title">Lorem ipsum</h5>
                        <p class="card-text">Lorem ipsum dolor sit amet</p>
                        <button class="btn btn-secondary bottone" type="button">
                            <a href="https://www.tiktok.com/@" class="testobianco">Lorem ipsum</a>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <footer class="my-5 pt-5 text-muted text-center text-small  fadeup">
            <p class="mb-1 testobianco">Copyright © 2021-2025 Cripsum™. Tutti i diritti riservati.</p>
            <ul class="list-inline">
                <li class="list-inline-item"><a href="privacy" class="linkbianco">Privacy</a></li>
                <li class="list-inline-item"><a href="tos" class="linkbianco">Termini</a></li>
                <li class="list-inline-item"><a href="supporto" class="linkbianco">Supporto</a></li>
            </ul>
        </footer>
        <embed src="../audio/Elevator Music.mp3" loop="true" autostart="true" width="2" height="0" />
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
