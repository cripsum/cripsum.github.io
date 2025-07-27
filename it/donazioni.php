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
        <style>
            img {
                border-radius: 10px;
            }
        </style>
        <title>Cripsum™ - donazioni</title>
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>

        <div class="paginainteradolod testobianco fadeup" style="margin: auto; padding-top: 7rem">
            <h6 class="text text-center mt-3">
                questo sito, il quale comprende 20/25 pagine, mi è costato circa 3/4 mila euro, ti va di donare una piccola somma di denaro per finanziare lo sviluppo di questo progetto? :)
            </h6>
        </div>
        <div class="text-center mt-3 fadeup">
            <a href="https://www.buymeacoffee.com/cripsum" target="_blank"
                ><img
                    class="ombra"
                    style="border-radius: 10px"
                    src="https://img.buymeacoffee.com/button-api/?text=Buy me a coffee :P&emoji=☕&slug=cripsum&button_colour=FFDD00&font_colour=000000&font_family=Poppins&outline_colour=000000&coffee_colour=ffffff"
                    onclick="unlockAchievement(4)"
            /></a>
        </div>
        <div id="achievement-popup" class="popup">
            <img id="popup-image" src="" alt="Achievement" />
            <div>
                <h3 id="popup-title"></h3>
                <p id="popup-description"></p>
            </div>
        </div>
        <footer class="my-5 pt-5 text-muted text-center text-small fadeup" style="position: absolute; bottom: 0; right: 0; left: 0">
            <p class="mb-1 testobianco">Copyright © 2021-2025 Cripsum™. Tutti i diritti riservati.</p>
            <ul class="list-inline">
                <li class="list-inline-item"><a href="privacy" class="link-lingua">Privacy</a></li>
                <li class="list-inline-item"><a href="tos" class="link-lingua">Termini</a></li>
                <li class="list-inline-item"><a href="supporto" class="link-lingua">Supporto</a></li>
            </ul>
        </footer>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/unlockAchievement-it.js"></script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
