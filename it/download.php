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
        
        <div class="hero-section py-5">
            <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">Download Center</h1>
                <p class="hero-subtitle">Scopri i nostri contenuti esclusivi</p>
                <p class="hero-question">Cosa vorresti scaricare oggi?</p>
            </div>
            </div>
        </div>

        <div class="downloads-section">
            <div class="downloads-grid">
            <div class="download-item">
                <div class="download-card h-100 shadow-lg border-0 fadeup">
                <div class="card-img-wrapper">
                    <img src="../img/jayquadrato.png" class="card-img-top" alt="Tutorial Spinjitzu" />
                    <div class="card-overlay">
                    <div class="overlay-content">
                        <i class="fas fa-download"></i>
                    </div>
                    </div>
                </div>
                <div class="card-body text-center">
                    <h5 class="card-title mb-2">
                    <a href="https://payhip.com/b/m0kaT" class="text-decoration-none">Tutorial Spinjitzu</a>
                    </h5>
                    <p class="card-text text-muted">Impara la famosa mossa di ninjago!</p>
                </div>
                </div>
            </div>
            
            <div class="download-item">
                <div class="download-card h-100 shadow-lg border-0 fadeup">
                <div class="card-img-wrapper">
                    <img src="../img/chinese-essay-2.jpg" class="card-img-top" alt="Corso Yoshukai" />
                    <div class="card-overlay">
                    <div class="overlay-content">
                        <i class="fas fa-download"></i>
                        <span class="badge bg-success mt-2">Gratis!</span>
                    </div>
                    </div>
                </div>
                <div class="card-body text-center">
                    <h5 class="card-title mb-2">
                    <a href="download/yoshukai" class="text-decoration-none">Corso Yoshukai</a>
                    </h5>
                    <p class="card-text text-muted">Gratis ancora per poco!!</p>
                </div>
                </div>
            </div>
            
            <div class="download-item">
                <div class="download-card h-100 shadow-lg border-0 fadeup">
                <div class="card-img-wrapper">
                    <img src="../img/fortnitehack.jpg" class="card-img-top" alt="Fortnite Hacks" />
                    <div class="card-overlay">
                    <div class="overlay-content">
                        <i class="fas fa-download"></i>
                    </div>
                    </div>
                </div>
                <div class="card-body text-center">
                    <h5 class="card-title mb-2">
                    <a href="download/fortnite" class="text-decoration-none">Fortnite Hacks</a>
                    </h5>
                    <p class="card-text text-muted">ez win</p>
                </div>
                </div>
            </div>
            
            <div class="download-item">
                <div class="download-card h-100 shadow-lg border-0 fadeup">
                <div class="card-img-wrapper">
                    <img src="../img/osu.jpg" class="card-img-top" alt="Osu!" />
                    <div class="card-overlay">
                    <div class="overlay-content">
                        <i class="fas fa-download"></i>
                    </div>
                    </div>
                </div>
                <div class="card-body text-center">
                    <h5 class="card-title mb-2">
                    <a href="download/osu" class="text-decoration-none">Osu!</a>
                    </h5>
                    <p class="card-text text-muted">hossu - il gioco ritmico per scemotti</p>
                </div>
                </div>
            </div>
            
            <div class="download-item">
                <div class="download-card h-100 shadow-lg border-0 coming-soon fadeup">
                <div class="card-img-wrapper">
                    <img src="../img/comingsoon.jpg" class="card-img-top" alt="Coming Soon" />
                    <div class="card-overlay">
                    <div class="overlay-content">
                        <i class="fas fa-clock"></i>
                    </div>
                    </div>
                </div>
                <div class="card-body text-center">
                    <h5 class="card-title mb-2">
                    <span class="text-muted">Coming Soon</span>
                    </h5>
                    <p class="card-text text-muted">Prossimamente...</p>
                </div>
                </div>
            </div>
            
            <div class="download-item">
                <div class="download-card h-100 shadow-lg border-0 coming-soon fadeup">
                <div class="card-img-wrapper">
                    <img src="../img/comingsoon.jpg" class="card-img-top" alt="Coming Soon" />
                    <div class="card-overlay">
                    <div class="overlay-content">
                        <i class="fas fa-clock"></i>
                    </div>
                    </div>
                </div>
                <div class="card-body text-center">
                    <h5 class="card-title mb-2">
                    <span class="text-muted">Coming Soon</span>
                    </h5>
                    <p class="card-text text-muted">Prossimamente...</p>
                </div>
                </div>
            </div>
            </div>
        </div>

        <style>
            /* Download page styles */
            .hero-section {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
            }

            .hero-content {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%);
            border-radius: 24px;
            padding: 3rem 2rem;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            }

            .hero-title {
            font-size: clamp(2rem, 4vw, 3.5rem);
            font-weight: 700;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #ffffff 0%, #e8e8e8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
            }

            .hero-subtitle {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 400;
            }

            .hero-question {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.7);
            font-style: italic;
            }

            .downloads-section {
            max-width: 1400px;
            margin: 2rem auto 0;
            padding: 0 1rem;
            }

            .downloads-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            padding: 2rem 0;
            }

            .download-item {
            position: relative;
            transform: translateY(0);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }

            .download-card {
            height: 100%;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            }
            
            .download-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
            }
            
            .download-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
            border-color: rgba(255, 255, 255, 0.2);
            }
            
            .download-card:hover::before {
            opacity: 1;
            }
            
            .card-img-wrapper {
            position: relative;
            overflow: hidden;
            height: 160px;
            background: linear-gradient(45deg, #333, #555);
            }
            
            .card-img-top {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.4s ease;
            filter: brightness(0.9);
            }
            
            .download-card:hover .card-img-top {
            transform: scale(1.05);
            filter: brightness(1.1);
            }
            
            .card-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0,0,0,0.6), rgba(0,0,0,0.3));
            opacity: 0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            }
            
            .download-card:hover .card-overlay {
            opacity: 1;
            backdrop-filter: blur(2px);
            }
            
            .overlay-content {
            text-align: center;
            color: white;
            transform: translateY(10px);
            transition: transform 0.3s ease;
            }
            
            .download-card:hover .overlay-content {
            transform: translateY(0);
            }
            
            .overlay-content i {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
            }

            .card-body {
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.03);
            position: relative;
            z-index: 2;
            }
            
            .card-title {
            margin-bottom: 0.8rem;
            font-size: 1.1rem;
            }
            
            .card-title a {
            color: rgba(255, 255, 255, 0.95);
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            }
            
            .card-title a:hover {
            background: linear-gradient(135deg, #fff 0%, #e0e0e0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 2px 8px rgba(255,255,255,0.2);
            }

            .card-text {
            color: rgba(255, 255, 255, 0.7) !important;
            font-size: 0.9rem;
            line-height: 1.4;
            }
            
            .coming-soon {
            opacity: 0.6;
            cursor: default;
            }
            
            .coming-soon:hover {
            opacity: 0.8;
            transform: translateY(-4px);
            }
            
            .badge {
            font-size: 0.7rem;
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            }

            /* Responsive breakpoints */
            @media (min-width: 1200px) {
            .downloads-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 2rem;
            }
            }

            @media (min-width: 992px) and (max-width: 1199px) {
            .downloads-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 1.5rem;
            }
            }

            @media (min-width: 768px) and (max-width: 991px) {
            .downloads-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
            }
            
            .card-img-wrapper {
                height: 140px;
            }
            }

            @media (max-width: 767px) {
            .hero-content {
                padding: 2rem 1.5rem;
                border-radius: 16px;
            }

            .downloads-section {
                padding: 0 0.5rem;
            }

            .downloads-grid {
                grid-template-columns: 1fr;
                gap: 1.25rem;
                padding: 1.5rem 0;
            }

            .card-img-wrapper {
                height: 160px;
            }

            .card-body {
                padding: 1.25rem;
            }
            }

            @media (max-width: 480px) {
            .hero-section {
                padding: 1.5rem 0.5rem;
            }

            .hero-content {
                padding: 1.5rem 1rem;
            }

            .downloads-grid {
                gap: 1rem;
            }

            .card-img-wrapper {
                height: 140px;
            }
            }

            /* Animation for page load */
            .fadeup {
            animation: fadeUp 0.6s ease forwards;
            }

            @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
            }
        </style>
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
