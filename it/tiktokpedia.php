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

    <div class="container mt-5 testobianco fadeup" style=" margin: auto; padding-top: 7rem">
        <h4 class="text-center">Attenzione, la pagina è ancora in fase di creazione, è pertanto costituita esclusivamente da dei placeholder, godetevi questa musica per il momento :)</h1>
            <br /><br />
            <h1 class="text-center">TikTokPedia</h1>
            <p class="text-center">Benvenuto nella TikTokPedia, la tua enciclopedia di TikTok!</p>
    </div>
    <div class="tiktokpedia testobianco" style="padding-bottom: 4rem;">
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
    <?php include '../includes/footer.php'; ?>
    <embed src="../audio/Elevator Music.mp3" loop="true" autostart="true" width="2" height="0" />
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
    <script src="../js/modeChanger.js"></script>
</body>

</html>