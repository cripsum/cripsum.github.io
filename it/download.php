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
        <title>Cripsum™ - download</title>
        <style>
            img {
                border-radius: 10px;
            }
        </style>
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>
        
        <div class="paginainteradolod testobianco" style="margin: auto; padding-top: 7rem; padding-bottom: 4rem;">
            <div class="d-flex justify-content-center image-container" style="max-width: 80%; margin: auto; padding-top: 5%">
                <div style="margin-top: 10px; margin-bottom: 5%; margin-left: 2%; margin-right: 2%" class="fadeup">
                    <img src="../img/jayquadrato.png" class="immaginealberi ombra" alt="" />
                    <p class="text text-center" style="font-size: 18px"><a href="https://payhip.com/b/m0kaT" class="linkbianco">Tutorial Spinjitzu</a></p>
                    <h6 class="text text-center">Impara la famosa mossa di ninjago!</h6>
                </div>
                <div style="margin-top: 10px; margin-bottom: 5%; margin-left: 2%; margin-right: 2%" class="fadeup">
                    <img src="../img/chinese-essay-2.jpg" class="immaginealberi ombra" alt="" />
                    <p class="text text-center" style="font-size: 18px"><a href="download/yoshukai" class="linkbianco">Corso Yoshukai</a></p>
                    <h6 class="text text-center">Gratis ancora per poco!!</h6>
                </div>
                <div style="margin-top: 10px; margin-bottom: 5%; margin-left: 2%; margin-right: 2%" class="fadeup">
                    <img src="../img/fortnitehack.jpg" class="immaginealberi ombra" alt="" />
                    <p class="text text-center" style="font-size: 18px"><a href="download/fortnite" class="linkbianco"
                        >Fortnite Hacks<br /></a></p>
                    <h6 class="text text-center">ez win</h6>
                </div>
                <div style="margin-top: 10px; margin-bottom: 5%; margin-left: 2%; margin-right: 2%"class="fadeup">
                    <img src="../img/osu.jpg" class="immaginealberi ombra" alt="" />
                    <p class="text text-center" style="font-size: 18px"><a class="linkbianco" href="download/osu">Osu!</a></p>
                    <h6 class="text text-center">hossu - il gioco ritmico per scemotti</h3>
                </div>
                <div style="margin-top: 10px; margin-bottom: 5%; margin-left: 2%; margin-right: 2%" class="fadeup">
                    <img src="../img/comingsoon.jpg" class="immaginealberi ombra" alt="" />
                    <p class="text text-center" style="font-size: 18px"><a href="" class="linkbianco">coming soon</a></p>
                </div>
                <div style="margin-top: 10px; margin-bottom: 5%; margin-left: 2%; margin-right: 2%" class="fadeup">
                    <img src="../img/comingsoon.jpg" class="immaginealberi ombra" alt="" />
                   <p class="text text-center" style="font-size: 18px"><a href="" class="linkbianco">coming soon</a></p>
                </div>
            </div>
        </div>
        <!--<div class="button-container mt-5 fadeup" style="text-align: center; max-width: 70%; margin: auto;" >
            <a class="btn btn-secondary bottone mt-2" onclick="playAudio()" style="cursor: pointer;" >Riproduci la musica</a>
            <a class="btn btn-secondary bottone mt-2"  onclick="pauseAudio()" style="cursor: pointer;">Ferma la musica</a>
            <a class="btn btn-secondary bottone mt-2" href="../audio/fitgirl.mp3" download="FitGirlRepacks_Song_downloadTEST.mp3">Clicca qui per scaricare questa musica!</a>
        </div>


        <audio autoplay id="myaudio">
            <source src="../audio/fitgirl.mp3" type="audio/mpeg">
          </audio>-->
        <?php include '../includes/footer.php'; ?>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>
        <!--<script>
            var audio = document.getElementById("myaudio");
            audio.volume = 0.2;
        </script>
        <script>
            var x = document.getElementById("myaudio"); 
            
            function playAudio() { 
              x.play(); 
            } 
            
            function pauseAudio() { 
              x.pause(); 
            } 
            </script>-->
    </body>
</html>
