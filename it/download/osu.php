<?php
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
checkBan($mysqli);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../../includes/head-import.php'; ?>
    <title>Cripsum™ - osu</title>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>

    <div class="paginainteradownload testobianco" style="padding-top: 7rem">
        <div style="margin: auto; max-width: 90%" class="d-flex justify-content-around flex-wrap mt-5">
            <img class="img-fluid imagi ombra fadeup" src="../../img/osu.jpg" alt="" style="display: inline; border-radius: 10px" />
            <div class="float-end titolo fadeup">
                <p class="fs-1 text mt-3 text-center" style="font-weight: bold">Osu!</p>
                <p class="fs-5 text mt-3 text-center">il gioco ritmico per scemotti</p>
                <p class="fs-5 text mt-3 text-center" style="color: red">hai il cancro o vuoi averlo? clicca il pulsante qui sotto e scarica osu!</p>
            </div>
        </div>
        <div class="button-container mt-5 fadeup" style="text-align: center; margin-top: 3%">
            <button class="btn btn-secondary bottone" type="button">
                <a class="testobianco" href="https://github.com/ppy/osu/releases/latest/download/install.exe">Clicca qui per scaricare Osu!</a>
            </button>
        </div>
        <br />
        <p class="text text-center fadeup" style="font-size: smaller">il download dovrebbe partire automaticamente dopo aver clickato</p>
    </div>
    <?php include '../../includes/footer.php'; ?>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
    
</body>

</html>