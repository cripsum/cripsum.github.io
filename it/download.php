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
        
    <!--<div class="hero-section py-5" style="margin-top: 5rem;">
        <div class="hero-content fadeup">
            <div class="hero-text">
                <h1 class="hero-title">Cripsum™ - Download</h1>
                <p class="hero-subtitle">Scopri i nostri contenuti esclusivi</p>
                <p class="hero-question">Cosa vorresti scaricare oggi?</p>
            </div>
        </div>
    </div>-->

    <div class="downloads-section" style="margin-top: 5rem; padding-bottom: 7rem">
        <div class="downloads-grid">
            <div class="download-item fadeup" onclick="window.location.href='https://payhip.com/b/m0kaT'">
                <div class="download-card h-100">
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

            <div class="download-item fadeup" onclick="window.location.href='download/yoshukai'">
                <div class="download-card h-100">
                    <div class="card-header">
                        <img src="../img/chinese-essay-2.jpg" class="card-img" alt="Corso Yoshukai" />
                        <div class="card-overlay">
                            <div class="overlay-content">
                                <i class="fas fa-download overlay-icon"></i>
                                <div class="overlay-badge">Gratis!</div>
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

            <div class="download-item fadeup" onclick="window.location.href='download/fortnite'">
                <div class="download-card h-100">
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

            <div class="download-item fadeup" onclick="window.location.href='download/osu'">
                <div class="download-card h-100">
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

            <div class="download-item fadeup">
                <div class="download-card h-100 coming-soon">
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

            <div class="download-item fadeup">
                <div class="download-card h-100 coming-soon">
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

        <style>
        .downloads-section {
            max-width: 1400px;
            margin: 2rem auto 0;
            padding: 0 1rem;
        }

        .downloads-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            padding: 2rem 0;
        }

        .download-item {
            position: relative;
            transform: translateY(0);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .download-card {
            height: 100%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(15px);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }
        
        .download-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                rgba(100, 200, 255, 0.05) 0%, 
                rgba(255, 100, 200, 0.03) 50%,
                rgba(100, 255, 150, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: 1;
            border-radius: 24px;
        }
        
        .download-card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.4),
                0 0 40px rgba(100, 200, 255, 0.1);
            border-color: rgba(100, 200, 255, 0.2);
        }
        
        .download-card:hover::before {
            opacity: 1;
        }
        
        .card-header {
            position: relative;
            overflow: hidden;
            height: 280px;
            background: linear-gradient(135deg, 
                rgba(30, 32, 42, 0.8) 0%, 
                rgba(40, 45, 60, 0.8) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }
        
        .card-img {
            width: 220px;
            height: 220px;
            object-fit: cover;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            filter: brightness(0.9) contrast(1.1) saturate(1.2);
            border-radius: 20px;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }
        
        .download-card:hover .card-img {
            transform: scale(1.08);
            filter: brightness(1) contrast(1.2) saturate(1.4);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .card-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                rgba(0,0,0,0.7), 
                rgba(30, 32, 42, 0.8),
                rgba(0,0,0,0.6));
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 3;
            backdrop-filter: blur(4px);
        }
        
        .download-card:hover .card-overlay {
            opacity: 1;
        }
        
        .overlay-content {
            text-align: center;
            color: white;
            transform: translateY(15px) scale(0.9);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .download-card:hover .overlay-content {
            transform: translateY(0) scale(1);
        }
        
        .overlay-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #64c8ff, #ff64c8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.4));
        }

        .overlay-badge {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            box-shadow: 
                0 4px 12px rgba(40, 167, 69, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: inline-block;
            margin-top: 8px;
        }

        .card-body {
            padding: 2rem 1.5rem;
            background: linear-gradient(135deg, 
                rgba(255, 255, 255, 0.06), 
                rgba(255, 255, 255, 0.03));
            position: relative;
            z-index: 2;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }
        
        .card-title {
            margin-bottom: 1rem;
            font-size: 1.4rem;
            font-weight: 600;
            text-align: center;
        }
        
        .card-title a {
            background: linear-gradient(135deg, #ffffff 0%, #e8e8e8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            transition: all 0.3s ease;
            text-decoration: none;
            position: relative;
            display: inline-block;
        }
        
        .card-title a::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(135deg, #64c8ff, #ff64c8);
            transform: scaleX(0);
            transition: transform 0.3s ease;
            border-radius: 1px;
        }
        
        .card-title a:hover::after {
            transform: scaleX(1);
        }
        
        .card-title a:hover {
            background: linear-gradient(135deg, #64c8ff 0%, #ff64c8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .card-description {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
            line-height: 1.6;
            font-weight: 400;
            text-align: center;
            margin: 0;
        }
        
        .coming-soon {
            opacity: 0.6;
            cursor: default;
        }
        
        .coming-soon:hover {
            opacity: 0.8;
            transform: translateY(-6px) scale(1.01);
        }
        
        .coming-soon .card-title span {
            color: rgba(255, 255, 255, 0.7);
        }

        .fadeup {
            animation: fadeUp 0.8s ease forwards;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (min-width: 1200px) {
            .downloads-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 2.5rem;
            }
        }

        @media (min-width: 992px) and (max-width: 1199px) {
            .downloads-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 2rem;
            }
        }

        @media (min-width: 768px) and (max-width: 991px) {
            .downloads-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            }
            
            .card-header {
                height: 240px;
            }
            
            .card-img {
                width: 180px;
                height: 180px;
            }
            
            .card-body {
                padding: 1.5rem 1.25rem;
            }
        }

        @media (max-width: 767px) {
            .hero-content {
                padding: 2rem 1.5rem;
                border-radius: 20px;
            }

            .downloads-section {
                padding: 0 0.5rem;
            }

            .downloads-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 1.5rem 0;
            }

            .card-header {
                height: 220px;
            }

            .card-img {
                width: 160px;
                height: 160px;
            }

            .card-body {
                padding: 1.5rem 1.25rem;
            }
            
            .card-title {
                font-size: 1.25rem;
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
                gap: 1.25rem;
                grid-template-columns: minmax(280px, 1fr);
            }

            .card-header {
                height: 200px;
            }

            .card-img {
                width: 140px;
                height: 140px;
            }
            
            .card-body {
                padding: 1.25rem 1rem;
            }

            .overlay-icon {
                font-size: 2.5rem;
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
