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
    <title>Cripsum™ - download</title>
    <style>
        img {
            border-radius: 10px;
        }

        .overlay-icon {
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
            <div class="card-item fadeup" onclick="window.location.href='https://payhip.com/b/m0kaT'">
                <div class="card h-100">
                    <div class="card-header">
                        <img src="../img/jayquadrato.png" class="card-img" alt="Tutorial Spinjitzu" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-download overlay-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <a href="https://payhip.com/b/m0kaT" class="text-decoration-none">Tutorial Spinjitzu</a>
                        </h5>
                        <p class="card-description">Impara la famosa mossa di ninjago!</p>
                    </div>
                </div>
            </div>

            <div class="card-item fadeup" onclick="window.location.href='download/yoshukai'">
                <div class="card h-100 download">
                    <div class="card-header">
                        <img src="../img/chinese-essay-2.jpg" class="card-img" alt="Corso Yoshukai" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-download overlay-icon"></i>
                                <div class="overlay-badge download">Gratis!</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <a href="download/yoshukai" class="text-decoration-none">Corso Yoshukai</a>
                        </h5>
                        <p class="card-description">Gratis ancora per poco!!</p>
                    </div>
                </div>
            </div>

            <div class="card-item fadeup" onclick="window.location.href='download/fortnite'">
                <div class="card h-100">
                    <div class="card-header">
                        <img src="../img/fortnitehack.jpg" class="card-img" alt="Fortnite Hacks" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-download overlay-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <a href="download/fortnite" class="text-decoration-none">Fortnite Hacks</a>
                        </h5>
                        <p class="card-description">ez win</p>
                    </div>
                </div>
            </div>

            <div class="card-item fadeup" onclick="window.location.href='download/osu'">
                <div class="card h-100">
                    <div class="card-header">
                        <img src="../img/osu.jpg" class="card-img" alt="Osu!" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-download overlay-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <a href="download/osu" class="text-decoration-none">Osu!</a>
                        </h5>
                        <p class="card-description">hossu - il gioco ritmico per scemotti</p>
                    </div>
                </div>
            </div>

            <div class="card-item fadeup">
                <div class="card h-100 coming-soon">
                    <div class="card-header">
                        <img src="../img/comingsoon.jpg" class="card-img" alt="Coming Soon" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-clock overlay-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <span>Coming Soon</span>
                        </h5>
                        <p class="card-description">Prossimamente...</p>
                    </div>
                </div>
            </div>

            <div class="card-item fadeup">
                <div class="card h-100 coming-soon">
                    <div class="card-header">
                        <img src="../img/comingsoon.jpg" class="card-img" alt="Coming Soon" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-clock overlay-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <span>Coming Soon</span>
                        </h5>
                        <p class="card-description">Prossimamente...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
    <script src="../js/modeChanger.js"></script>
</body>

</html>