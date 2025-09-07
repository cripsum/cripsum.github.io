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
            .overlay-icon{
                -webkit-background-clip: inherit;
                -webkit-text-fill-color: inherit;
                background-clip: inherit;
                background: transparent;
            }
        </style>
    </head>

    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>
        
        <div class="card-section" style="margin-top: 7rem; padding-bottom: 7rem">
            <div class="card-grid">
                <div class="card-item fadeup" onclick="window.location.href='checkout'">
                    <div class="card h-100">
                        <div class="card-header">
                            <img src="../img/4090.jpg" class="card-img" alt="RTX 4090" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">RTX 4090 - 2499,99€</a>
                            </h5>
                            <p class="card-description">troppo potente anche per il gamings</p>
                        </div>
                    </div>
                </div>

                <div class="card-item fadeup" onclick="window.location.href='checkout'">
                    <div class="card h-100">
                        <div class="card-header">
                            <img src="../img/iphone20.jpg" class="card-img" alt="Iphone 20" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">Iphone 20 - 2179,99€</a>
                            </h5>
                            <p class="card-description">il futuro, ottimo telefono, peccato per l'os</p>
                        </div>
                    </div>
                </div>

                <div class="card-item fadeup" onclick="window.location.href='checkout'">
                    <div class="card h-100">
                        <div class="card-header">
                            <img src="../img/indica.jpg" class="card-img" alt="tua madre" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                    <div class="overlay-badge">Sconto 40%!</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">tua madre - 2,49€</a>
                            </h5>
                            <p class="card-description">se acquistate entro il 2025 sconto del 40%</p>
                        </div>
                    </div>
                </div>

                <div class="card-item fadeup" onclick="window.location.href='checkout'">
                    <div class="card h-100">
                        <div class="card-header">
                            <img src="../img/s30.jpg" class="card-img" alt="Samsung galaxy s30" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">Samsung galaxy s30 - 1599,99€</a>
                            </h5>
                            <p class="card-description">
                                16 gb ram - 1tb memoria - batteria: 7000mAh<br />
                                display 4k 144hz - zoom ottico x100 con <br />
                                sensore principale da 12482345 MP
                            </p>
                        </div>
                    </div>
                </div>

                <div class="card-item fadeup" onclick="window.location.href='checkout'">
                    <div class="card h-100">
                        <div class="card-header">
                            <img src="../img/ps6.jpg" class="card-img" alt="Ps6" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">Ps6 - 849,49€</a>
                            </h5>
                            <p class="card-description">
                                con ps6 potrete giocare a gta5 <br />
                                remastered in 8k 120fps
                            </p>
                        </div>
                    </div>
                </div>

                <div class="card-item fadeup" onclick="window.location.href='checkout'">
                    <div class="card h-100">
                        <div class="card-header">
                            <img src="../img/monitor540.jpg" class="card-img" alt="Monitor 540hz" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">Monitor 540hz - 949,69€</a>
                            </h5>
                            <p class="card-description">fluidità estrema (completamente inutile)</p>
                        </div>
                    </div>
                </div>

                <div class="card-item fadeup" onclick="window.location.href='checkout'">
                    <div class="card h-100">
                        <div class="card-header">
                            <img src="../img/renegade.jpg" class="card-img" alt="renegade raider" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">renegade raider - 429,99€</a>
                            </h5>
                            <p class="card-description">
                                la skin più og di tutte, ma anche <br />
                                la più costosa
                            </p>
                        </div>
                    </div>
                </div>

                <div class="card-item fadeup" onclick="window.location.href='checkout'">
                    <div class="card h-100">
                        <div class="card-header">
                            <img src="../img/tavoletta.jpg" class="card-img" alt="Tavoletta grafica" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">Tavoletta grafica - 119,89€</a>
                            </h5>
                            <p class="card-description">accessori per osu</p>
                        </div>
                    </div>
                </div>

                <div class="card-item fadeup" onclick="window.location.href='checkout'">
                    <div class="card h-100">
                        <div class="card-header">
                            <img src="../img/tastiera2tasti.jpg" class="card-img" alt="tastiera per osu" />
                            <div class="card-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-shopping-cart overlay-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">
                                <a href="checkout" class="text-decoration-none">tastiera per osu - 44,99€</a>
                            </h5>
                            <p class="card-description">accessori per osu</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
        </style>
        
        <?php include '../includes/footer.php'; ?>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
