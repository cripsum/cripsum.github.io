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

        <div class="container-fluid py-5" style="margin-top: 5rem;">
            <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="card shadow-lg border-0 fadeup" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px;">
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                    <i class="fas fa-heart" style="font-size: 3rem; color: #ff6b6b; animation: heartbeat 2s infinite;"></i>
                    </div>
                    <h2 class="text-white fw-bold mb-4">Supporta il Progetto</h2>
                    <p class="text-white-50 fs-5 mb-4 lh-lg">
                    Questo sito, con oltre <span class="fw-bold text-warning">20+ pagine</span>, 
                    rappresenta <span class="fw-bold text-warning">migliaia di ore</span> di sviluppo e 
                    un investimento di <span class="fw-bold text-warning">€3000+</span>.
                    </p>
                    <p class="text-white fs-6 mb-4">
                    La tua donazione ci aiuta a mantenere il sito gratuito e a sviluppare nuove funzionalità! ☕✨
                    </p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <span class="badge bg-light text-dark px-3 py-2 rounded-pill">
                        <i class="fas fa-code me-2"></i>Open Source
                    </span>
                    <span class="badge bg-light text-dark px-3 py-2 rounded-pill">
                        <i class="fas fa-globe me-2"></i>Gratuito
                    </span>
                    <span class="badge bg-light text-dark px-3 py-2 rounded-pill">
                        <i class="fas fa-rocket me-2"></i>In Crescita
                    </span>
                    </div>
                </div>
                </div>
            </div>
            </div>
        </div>
        
        <style>
            @keyframes heartbeat {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
            }
        </style>
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
