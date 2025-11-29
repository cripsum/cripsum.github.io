<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - merch</title>
    <style>
        img {
            border-radius: 10px;
        }

        .dropdownlingua {
            background: linear-gradient(135deg, rgba(214, 187, 32, 0.5), rgb(99, 85, 31));
        }

        .dropdownutenti .dropdown-menu {
            background: linear-gradient(135deg, rgba(214, 187, 32, 0.5), rgb(99, 85, 31));
        }

        .overlay-icon {
            -webkit-background-clip: inherit;
            -webkit-text-fill-color: inherit;
            background-clip: inherit;
            background: transparent;
        }
    </style>
</head>

<body style="background-color: #bb930f; padding-top: 7rem" class="testobianco">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="paginaintera" style="padding-bottom: 1rem;">
        <p class="text-center fadein" style="font-size: 50px; font-weight: bolder; margin-top: 20px">NEW MERCH OUT NOW</p>
        <p class="text-center fadein" style="font-size: 50px">🤑🐦📸</p>
    </div>

    <div class="card-section" style="margin-top: 3rem; padding-bottom: 7rem">
        <div class="card-grid">
            <div class="card-item fadeup" onclick="window.location.href='merch-checkout'">
                <div class="card h-100">
                    <div class="card-header">
                        <img src="../img/magliag.jpg" class="card-img" alt="T-Shirt big logo" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-shopping-cart overlay-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <a href="merch-checkout" class="text-decoration-none">T-Shirt simonetussi.ph - big logo - 19,99€</a>
                        </h5>
                        <p class="card-description">logo grande per massima visibilità</p>
                    </div>
                </div>
            </div>

            <div class="card-item fadeup" onclick="window.location.href='merch-checkout'">
                <div class="card h-100">
                    <div class="card-header">
                        <img src="../img/magliap.jpg" class="card-img" alt="T-Shirt small logo" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-shopping-cart overlay-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <a href="merch-checkout" class="text-decoration-none">T-Shirt simonetussi.ph - small logo - 19,99€</a>
                        </h5>
                        <p class="card-description">logo piccolo per chi ama la discrezione</p>
                    </div>
                </div>
            </div>

            <div class="card-item fadeup" onclick="window.location.href='merch-checkout'">
                <div class="card h-100">
                    <div class="card-header">
                        <img src="../img/felpag.jpg" class="card-img" alt="Felpa big logo" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-shopping-cart overlay-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <a href="merch-checkout" class="text-decoration-none">Felpa simonetussi.ph - big logo - 39,99€</a>
                        </h5>
                        <p class="card-description">calda e confortevole con logo grande</p>
                    </div>
                </div>
            </div>

            <div class="card-item fadeup" onclick="window.location.href='merch-checkout'">
                <div class="card h-100">
                    <div class="card-header">
                        <img src="../img/felpap.jpg" class="card-img" alt="Felpa small logo" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-shopping-cart overlay-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <a href="merch-checkout" class="text-decoration-none">Felpa simonetussi.ph - small logo - 39,99€</a>
                        </h5>
                        <p class="card-description">stile minimal ma sempre riconoscibile</p>
                    </div>
                </div>
            </div>

            <div class="card-item fadeup" onclick="window.location.href='merch-checkout'">
                <div class="card h-100">
                    <div class="card-header">
                        <img src="../img/pantaloncini.jpg" class="card-img" alt="Pantaloncini" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-shopping-cart overlay-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <a href="merch-checkout" class="text-decoration-none">Pantaloncini simonetussi.ph - 23,99€</a>
                        </h5>
                        <p class="card-description">comodi per l'estate e lo sport</p>
                    </div>
                </div>
            </div>

            <div class="card-item fadeup" onclick="window.location.href='merch-checkout'">
                <div class="card h-100">
                    <div class="card-header">
                        <img src="../img/calze.jpg" class="card-img" alt="Calzini" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-shopping-cart overlay-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <a href="merch-checkout" class="text-decoration-none">Calzini simonetussi.ph - 5,99€</a>
                        </h5>
                        <p class="card-description">anche i piedi meritano stile</p>
                    </div>
                </div>
            </div>

            <div class="card-item fadeup" onclick="window.location.href='merch-checkout'">
                <div class="card h-100">
                    <div class="card-header">
                        <img src="../img/boxers.jpg" class="card-img" alt="Boxer" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-shopping-cart overlay-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <a href="merch-checkout" class="text-decoration-none">Boxer simonetussi.ph - 149,99€</a>
                        </h5>
                        <p class="card-description">lusso estremo per veri intenditori</p>
                    </div>
                </div>
            </div>

            <div class="card-item fadeup" onclick="window.location.href='merch-checkout'">
                <div class="card h-100">
                    <div class="card-header">
                        <img src="../img/mutandinesexi.jpg" class="card-img" alt="Slip" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-shopping-cart overlay-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <a href="merch-checkout" class="text-decoration-none">Slip simonetussi.ph - 249,99€</a>
                        </h5>
                        <p class="card-description">
                            edizione limitata, pezzo da <br />
                            collezione esclusiva
                        </p>
                    </div>
                </div>
            </div>

            <div class="card-item fadeup" onclick="window.location.href='merch-checkout'">
                <div class="card h-100">
                    <div class="card-header">
                        <img src="../img/cappellino.jpg" class="card-img" alt="Cappellino" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-shopping-cart overlay-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <a href="merch-checkout" class="text-decoration-none">Cappellino simonetussi.ph - 7,99€</a>
                        </h5>
                        <p class="card-description">protezione solare con stile</p>
                    </div>
                </div>
            </div>

            <div class="card-item fadeup" onclick="window.location.href='merch-checkout'">
                <div class="card h-100">
                    <div class="card-header">
                        <img src="../img/occhialis.jpg" class="card-img" alt="Occhiali da sole" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-shopping-cart overlay-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <a href="merch-checkout" class="text-decoration-none">Occhiali da sole simonetussi.ph - 8,99€</a>
                        </h5>
                        <p class="card-description">look da vero influencer</p>
                    </div>
                </div>
            </div>

            <div class="card-item fadeup" onclick="window.location.href='merch-checkout'">
                <div class="card h-100">
                    <div class="card-header">
                        <img src="../img/occhialiv.jpg" class="card-img" alt="Occhiali da vista" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-shopping-cart overlay-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <a href="merch-checkout" class="text-decoration-none">Occhiali da vista simonetussi.ph - 35,99€</a>
                        </h5>
                        <p class="card-description">
                            vedi meglio il mondo con <br />
                            il logo simonetussi.ph
                        </p>
                    </div>
                </div>
            </div>

            <div class="card-item fadeup" onclick="window.location.href='merch-checkout'">
                <div class="card h-100">
                    <div class="card-header">
                        <img src="../img/tostapane.jpg" class="card-img" alt="Tostapane" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-shopping-cart overlay-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <a href="merch-checkout" class="text-decoration-none">Tostapane simonetussi.ph - 79,99€</a>
                        </h5>
                        <p class="card-description">
                            toasta il pane con logo <br />
                            simonetussi.ph impresso
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/scroll_indicator.php'; ?>

    <?php include '../includes/footer.php'; ?>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
    <script src="../js/modeChanger.js"></script>
</body>

</html>