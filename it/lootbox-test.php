<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);


if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Per accedere alle lootbox devi essere loggato";

    header('Location: accedi');
    exit();
}
require_once '../api/api_personaggi.php';

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <?php include '../includes/head-import.php'; ?>
        <link rel="stylesheet" href="/css/lootbox.css" />
        <title>Cripsum™ - lootbox</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                background: linear-gradient(135deg, #0f0f23 0%, #1a1a3a 50%, #0f0f23 100%);
                color: white;
                overflow-x: hidden;
                min-height: 100vh;
                position: relative;
            }

            body.overflow-hidden {
                overflow: hidden;
            }

            .stars {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                pointer-events: none;
                z-index: -1;
            }

            .star {
                position: absolute;
                width: 2px;
                height: 2px;
                background: white;
                border-radius: 50%;
                animation: twinkle 4s infinite;
            }

            @keyframes twinkle {
                0%, 100% { opacity: 0.3; }
                50% { opacity: 1; }
            }

            .main-container {
                max-width: 1520px;
                padding: 5rem 2rem 2rem;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                position: relative;
                
            }

            .title {
                font-size: 3rem;
                font-weight: bold;
                background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1, #96ceb4);
                background-size: 400% 400%;
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                text-align: center;
                margin-bottom: 2rem;
                animation: gradientShift 3s ease-in-out infinite;
            }

            @keyframes gradientShift {
                0%, 100% { background-position: 0% 50%; }
                50% { background-position: 100% 50%; }
            }

            .lootbox-container {
                position: relative;
                margin: 3rem 0;
                perspective: 1000px;
            }

            .lootbox {
                width: 200px;
                height: 200px;
                cursor: pointer;
                transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                transform-style: preserve-3d;
                filter: drop-shadow(0 20px 40px rgba(0, 255, 255, 0.3));
                position: relative;
            }

            .lootbox img {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }

            .lootbox:hover {
                transform: scale(1.1) rotateY(10deg);
                filter: drop-shadow(0 25px 50px rgba(0, 255, 255, 0.5));
            }

            .lootbox.opening {
                animation: boxShake 0.6s ease-in-out, boxGlow 0.6s ease-in-out;
            }

            .lootbox.aperta {
                transform: scale(0.8) rotateX(15deg);
                opacity: 0.7;
                filter: drop-shadow(0 10px 20px rgba(255, 255, 255, 0.2));
            }

            .lootbox.dissolvi {
                opacity: 0.3;
                transform: scale(0.7);
            }

            @keyframes boxShake {
                0%, 100% { transform: translateX(0); }
                10% { transform: translateX(-10px) rotateZ(-1deg); }
                20% { transform: translateX(10px) rotateZ(1deg); }
                30% { transform: translateX(-10px) rotateZ(-1deg); }
                40% { transform: translateX(10px) rotateZ(1deg); }
                50% { transform: translateX(-5px) rotateZ(-0.5deg); }
                60% { transform: translateX(5px) rotateZ(0.5deg); }
                70% { transform: translateX(-5px) rotateZ(-0.5deg); }
                80% { transform: translateX(5px) rotateZ(0.5deg); }
                90% { transform: translateX(0); }
            }

            @keyframes boxGlow {
                0% { filter: drop-shadow(0 20px 40px rgba(0, 255, 255, 0.3)); }
                50% { filter: drop-shadow(0 30px 60px rgba(255, 255, 255, 0.8)); }
                100% { filter: drop-shadow(0 20px 40px rgba(0, 255, 255, 0.3)); }
            }

            #baglioreWrapper {
                position: absolute;
                top: 50%;
                left: 50%;
                width: 300px;
                height: 300px;
                transform: translate(-50%, -50%);
                z-index: -1;
            }

            #bagliore {
                position: absolute;
                top: 50%;
                left: 50%;
                width: 100%;
                height: 100%;
                border-radius: 50%;
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.5);
                pointer-events: none;
                transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            }

            .character-reveal {
                position: relative;
                text-align: center;
                opacity: 0;
                transform: translateY(50px) scale(0.8);
                transition: all 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                margin: 2rem 0;
            }

            .character-reveal.salto {
                opacity: 1;
                transform: translateY(0) scale(1);
            }

            #contenuto {
                text-align: center;
            }

            #contenuto img {
                max-width: 300px;
                max-height: 300px;
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
                margin-bottom: 1rem;
                transition: transform 0.3s ease;
            }

            #contenuto img:hover {
                transform: scale(1.05);
            }

            #nomePersonaggio {
                font-size: 2.5rem;
                font-weight: bold;
                margin-bottom: 0.5rem;
                text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
                color: white;
            }

            #messaggio {
                transition: all 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                opacity: 0;
                transform: translateY(50px);
            }

            #messaggio.salto {
                opacity: 1;
                transform: translateY(0);
            }

            #messaggioRarita {
                font-size: 1.2rem;
                margin: 2rem 0;
                padding: 1rem 2rem;
                border-radius: 50px;
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.2);
                text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.8);
                background: rgba(255, 255, 255, 0.1);
            }

            #divApriAncora {
                transition: all 0.6s ease 0.5s;
                opacity: 0;
                transform: translateY(30px);
            }

            #divApriAncora.salto {
                opacity: 1;
                transform: translateY(0);
            }

            .nascosto {
                display: none;
            }

            .button-container {
                display: flex;
                gap: 1rem;
                flex-wrap: wrap;
                justify-content: center;
            }

            .btn {
                padding: 1rem 2rem;
                border: none;
                border-radius: 50px;
                font-size: 1.1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                text-decoration: none;
                display: inline-block;
                backdrop-filter: blur(10px);
                border: 2px solid transparent;
                position: relative;
                overflow: hidden;
            }

            .btn::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
                transition: left 0.6s;
            }

            .btn:hover::before {
                left: 100%;
            }

            .btn-secondary {
                background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
                color: white;
                box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            }

            .btn-secondary:hover {
                transform: translateY(-3px);
                box-shadow: 0 12px 35px rgba(102, 126, 234, 0.6);
            }

            .bottone {
                background: linear-gradient(45deg, #f093fb 0%, #f5576c 100%);
                color: white;
                box-shadow: 0 8px 25px rgba(240, 147, 251, 0.4);
            }

            .bottone:hover {
                transform: translateY(-3px);
                box-shadow: 0 12px 35px rgba(240, 147, 251, 0.6);
            }

            .particles {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                pointer-events: none;
                z-index: 1000;
            }

            .particella {
                position: absolute;
                width: 4px;
                height: 4px;
                background: radial-gradient(circle, #fff, #4ecdc4);
                border-radius: 50%;
                animation: particleFloat 3s ease-out forwards;
            }

            @keyframes particleFloat {
                0% {
                    opacity: 1;
                    transform: scale(1);
                }
                100% {
                    opacity: 0;
                    transform: scale(0) translate(var(--x), var(--y));
                }
            }

            /* Settings Modal Styles */
            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                backdrop-filter: blur(10px);
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 10000;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .modal-overlay.show {
                display: flex;
                opacity: 1;
            }

            .modal-content {
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 20px;
                padding: 2rem;
                max-width: 600px;
                width: 90%;
                backdrop-filter: blur(20px);
                transform: scale(0.8);
                transition: transform 0.3s ease;
                position: relative;
                max-height: 80vh;
                overflow-y: auto;
            }

            .modal-overlay.show .modal-content {
                transform: scale(1);
            }

            .close-btn {
                position: absolute;
                top: 1rem;
                right: 1rem;
                background: none;
                border: none;
                color: white;
                font-size: 1.5rem;
                cursor: pointer;
                width: 30px;
                height: 30px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background 0.3s ease;
            }

            .close-btn:hover {
                background: rgba(255, 255, 255, 0.2);
            }

            .modal-header h5 {
                color: white;
                margin-bottom: 1rem;
            }

            .modal-body {
                color: white;
                margin: 1rem 0;
            }

            .form-check {
                margin: 0.5rem 0;
            }

            .form-check-label {
                color: white;
                margin-left: 0.5rem;
            }

            .form-control {
                background: rgba(255, 255, 255, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 10px;
                padding: 0.5rem;
                color: white;
                width: 100%;
            }

            .form-control::placeholder {
                color: rgba(255, 255, 255, 0.7);
            }

            .form-label {
                color: white;
                display: block;
                margin-bottom: 0.5rem;
            }

            /* Popup styles */
            #popup-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.85);
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                opacity: 0;
                transition: opacity 0.5s ease;
            }

            #collegamentoedits {
                backdrop-filter: blur(15px);
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(64, 64, 64, 0.1));
                box-shadow: 0 0 8px 4px rgba(255, 255, 255, 0.5);
                padding: 20px;
                border: 1px solid rgba(255, 255, 255, 0.5);
                border-radius: 10px;
                max-width: 80%;
                text-align: center;
                position: relative;
                opacity: 0;
                transform: translateY(-20px);
                transition: opacity 0.5s ease, transform 0.5s ease;
            }

            .bannerino {
                color: white;
            }

            .bannerino h2 {
                color: white;
                padding-top: 11px;
            }

            .bannerino p {
                color: white;
            }

            /* Achievement popup */
            #achievement-popup {
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 15px;
                padding: 1rem;
                backdrop-filter: blur(20px);
                max-width: 300px;
                transform: translateX(100%);
                transition: transform 0.3s ease;
                z-index: 10001;
            }

            #achievement-popup.show {
                transform: translateX(0);
            }

            /* Rainbow background animation for speciale rarity */
            @keyframes rainbowBackground {
                0% { background-position: 0% 50%; }
                100% { background-position: 100% 50%; }
            }

            /* Secret glow animation */
            @keyframes secretGlowRotate {
                0% { 
                    transform: translate(-50%, -50%) scale(1) rotate(0deg);
                    filter: brightness(1) saturate(1);
                }
                25% { 
                    transform: translate(-50%, -50%) scale(1.2) rotate(90deg);
                    filter: brightness(1.3) saturate(1.5);
                }
                50% { 
                    transform: translate(-50%, -50%) scale(1) rotate(180deg);
                    filter: brightness(1) saturate(1);
                }
                75% { 
                    transform: translate(-50%, -50%) scale(1.2) rotate(270deg);
                    filter: brightness(1.3) saturate(1.5);
                }
                100% { 
                    transform: translate(-50%, -50%) scale(1) rotate(360deg);
                    filter: brightness(1) saturate(1);
                }
            }

            .instruction-text {
                position: absolute;
                bottom: 2rem;
                left: 50%;
                transform: translateX(-50%);
                text-align: center;
                opacity: 0.7;
                font-size: 0.9rem;
            }

            .testobianco, .tastobianco, .linkbianco {
                color: white;
            }

            .linkbianco:hover {
                color: #4ecdc4;
            }

            .non-selezionabile {
                user-select: none;
            }

            .fadein, .fadeup {
                animation: fadeInUp 0.8s ease-out;
            }

            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @media (max-width: 768px) {
                .title {
                    font-size: 2rem;
                }
                
                .lootbox {
                    width: 150px;
                    height: 150px;
                }

                #nomePersonaggio {
                    font-size: 1.8rem;
                }

                .main-container {
                    padding: 1rem;
                }
                
                .button-container {
                    flex-direction: column;
                    align-items: center;
                }

                .modal-content {
                    padding: 1rem;
                    max-width: 95%;
                }
            }
        </style>
    </head>

    <body class="">
        <?php include '../includes/navbar.php'; ?>
        <!-- Animated stars background -->
        <div class="stars" id="stars"></div>

        <div style="max-width: 1520px; padding-top: 5rem" class="testobianco main-container" id="paginaintera">
            <!-- Cookie popup from original -->
            <div
                id="popup-overlay"
                style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.85);
                    display: none;
                    align-items: center;
                    justify-content: center;
                    z-index: 9999;
                    opacity: 0;
                    transition: opacity 0.5s ease;
                "
            >
                <div
                    id="collegamentoedits"
                    class="collegamentoedit ombra fadeup"
                    style="
                        backdrop-filter: blur(15px);
                        background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(64, 64, 64, 0.1));
                        box-shadow: 0 0 8px 4px rgba(255, 255, 255, 0.5);
                        padding: 20px;
                        border: 1px solid rgba(255, 255, 255, 0.5);
                        border-radius: 10px;
                        max-width: 80%;
                        text-align: center;
                        position: relative;
                        opacity: 0;
                        transform: translateY(-20px);
                        transition: opacity 0.5s ease, transform 0.5s ease;
                    "
                >
                    <button style="position: absolute; top: 0px; right: 5px; background-color: transparent; border: none; cursor: pointer" onclick="closePopup()">
                        <span class="close_div tastobianco" style="font-size: 20px; color: rgb(255, 255, 255)"
                            >&times;<span class="linkbianco" style="font-size: small; position: relative; top: -3px; left: 3px">chiudi</span></span
                        >
                    </button>
                    <div id="banner-content"></div>
                </div>
            </div>

            <!-- <h1 class="title">✨ Cripsum™ Lootbox ✨</h1> -->
            
            <div class="container">
                <div class="lootbox-container">
                    <img src="../img/cassa.png" alt="Cassa" id="cassa" class="lootbox fadein" ondblclick="apriVeloce()" onclick="pullaPersonaggio(); apriNormale()" />
                    
                    <div id="baglioreWrapper">
                        <div class="bagliore" id="bagliore"></div>
                    </div>
                </div>

                <div id="contenuto" class="character-reveal"></div>

                <div id="messaggio" class="nascosto character-reveal">
                    <h1 style="margin-top: 100px; font-size: 25px" id="messaggioRarita" class="non-selezionabile"></h1>
                    <a onclick="refresh()" id="apriAncora" class="linkbianco"></a>
                </div>

                <div id="divApriAncora" class="nascosto">
                    <div class="button-container mt-4" style="text-align: center; max-width: 95%; margin: auto">
                        <a class="btn btn-secondary bottone mt-2" onclick="refresh()" style="cursor: pointer">Apri cassa</a>
                        <a class="btn btn-secondary bottone mt-2" href="inventario" style="cursor: pointer">Apri l'inventario</a>
                    </div>
                </div>

                <div class="modal fade" id="impostazioniModal" tabindex="-1" aria-labelledby="impostazioniModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content bgimpostazioni">
                            <div class="modal-header">
                                <h5 class="modal-title" id="disclaimerModalLabel">Impostazioni & Probabilità</h5>
                            </div>
                                <div class="col-md-6 d-flex text-center" style="text-align: center; padding-top: 20px; padding-bottom: 20px; margin: auto;">
                                    <div style="color: white; font-size: 14px; margin: auto;">
                                        <h6 style="color: white; margin-bottom: 10px;">🎲 Probabilità Rarità:</h6>
                                        <div>Comune: 45%</div>
                                        <div>Raro: 25%</div>
                                        <div>Epico: 15%</div>
                                        <div>Leggendario: 10%</div>
                                        <div>Mitico: 4%</div>
                                        <div>Speciale: 0.9%</div>
                                        <div>???: 0.1%</div>
                                    </div>
                                </div>

                            <?php if ($ruolo === 'admin' || $ruolo === 'owner'): ?>
                            <div id="admin-cheats">
                                <h4>🎮 Cheats (Admin Only)</h4>
                                <div class="modal-body">
                                    <div class="col-md-6 d-flex" style="text-align: center">
                                        <div class="form-check mb-3 mb-md-0" style="text-align: center">
                                            <input class="form-check-input checco" type="checkbox" value="" id="RimuoviAnime" />
                                            <label class="form-check-label" for="RimuoviAnime">Rimuovi Anime</label>
                                        </div>
                                    </div>

                                    <div class="col-md-6 d-flex" style="text-align: center">
                                        <div class="form-check mb-3 mb-md-0" style="text-align: center">
                                            <input class="form-check-input checco" type="checkbox" value="" id="SoloSpeciali" />
                                            <label class="form-check-label" for="SoloSpeciali">Solo Speciali</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 d-flex" style="text-align: center">
                                        <div class="form-check mb-3 mb-md-0" style="text-align: center">
                                            <input class="form-check-input checco" type="checkbox" value="" id="SoloSegreti" />
                                            <label class="form-check-label" for="SoloSegreti">Solo Segreti</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 d-flex" style="text-align: center">
                                        <div class="form-check mb-3 mb-md-0" style="text-align: center">
                                            <input class="form-check-input checco" type="checkbox" value="" id="SoloPoppy" />
                                            <label class="form-check-label" for="SoloPoppy">Meow</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 d-flex" style="text-align: center">
                                        <div class="form-check mb-3 mb-md-0" style="text-align: center">
                                            <input class="form-check-input checco" type="checkbox" value="" id="SoloComuni" />
                                            <label class="form-check-label" for="SoloComuni">Solo Comuni</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                                <div data-mdb-input-init class="form-outline mb-4">
                                    <label class="form-label" for="registerName">🔐 Codice Segreto</label>
                                    <input type="text" id="codiceSegreto" class="form-control" />
                                    <br />
                                    <button type="button" class="btn btn-secondary bottone" data-bs-dismiss="modal" onclick="riscattaCodice()">Riscatta codice</button>
                                </div>
                            
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary bottone" data-bs-dismiss="modal" onclick="salvaPreferenze()">Salva Preferenze</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="particelle" class="particles"></div>
            </div>

            <div class="instruction-text">
                <p>Premi <strong>SPAZIO</strong> per aprire la cassa • Premi <strong>R</strong> o <strong>INVIO</strong> per aprirne un'altra</p>
            </div>

            <audio id="suonoCassa"></audio>
        </div>
        
        <div id="achievement-popup" class="popup" style="max-height: 100px">
            <img id="popup-image" src="" alt="Achievement" />
            <div>
                <h3 id="popup-title"></h3>
                <p id="popup-description"></p>
            </div>
        </div>

        <!-- All original scripts -->
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/characters.js"></script>
        <script src="../js/unlockAchievement-it.js"></script>
        <script>
            // Initialize stars
            function createStars() {
                const starsContainer = document.getElementById('stars');
                for (let i = 0; i < 100; i++) {
                    const star = document.createElement('div');
                    star.className = 'star';
                    star.style.left = Math.random() * 100 + '%';
                    star.style.top = Math.random() * 100 + '%';
                    star.style.animationDelay = Math.random() * 4 + 's';
                    starsContainer.appendChild(star);
                }
            }

            // Cookie popup functions
            function getRandomBanner() {
                const banners = [
                    `<div class="bannerino">
        <h2 style="color: rgb(255, 255, 255); padding-top: 11px">Ti offriamo un cookie! 🍪</h2>
        <p style="color: rgb(255, 255, 255);">Questo sito utilizza i cookie per salvare i tuoi dati. Se li disattivi, alcune funzioni come le impostazioni e l'inventario potrebbero non funzionare correttamente.</p>
        <p style="color: rgb(255, 255, 255);">Buon divertimento!</p>
        <button type="button" class="btn btn-secondary bottone" data-bs-dismiss="modal" onclick="closePopup()">Prendi i miei dati 😆</button>
    </div>`,
                ];
                return banners[Math.floor(Math.random() * banners.length)];
            }

            function showPopup() {
                if (getCookie("popupSeen")) return;

                const overlay = document.getElementById("popup-overlay");
                const popup = document.getElementById("collegamentoedits");
                document.getElementById("banner-content").innerHTML = getRandomBanner();
                overlay.style.display = "flex";
                document.body.style.overflow = "hidden";
                setTimeout(() => {
                    overlay.style.opacity = "1";
                    popup.style.opacity = "1";
                    popup.style.transform = "translateY(0)";
                }, 10);
            }

            function closePopup() {
                const overlay = document.getElementById("popup-overlay");
                const popup = document.getElementById("collegamentoedits");
                popup.style.opacity = "0";
                popup.style.transform = "translateY(-20px)";
                overlay.style.opacity = "0";
                document.body.style.overflow = "auto";
                setTimeout(() => {
                    overlay.style.display = "none";
                }, 500);
                setCookie("popupSeen", true);
            }

            window.onload = function () {
                setTimeout(showPopup, 700);
            };

            // All original JavaScript logic
            const cassa = document.getElementById("cassa");
            const nomePersonaggio = document.getElementById("nomePersonaggio");
            const messaggioRarita = document.getElementById("messaggioRarita");
            const audio = document.getElementById("suonoCassa");
            const bagliore = document.getElementById("bagliore");
            const messaggio = document.getElementById("messaggio");
            const contenuto = document.getElementById("contenuto");
            const particelleContainer = document.getElementById("particelle");
            const paginaintera = document.getElementById("paginaintera");
            const apriAncora = document.getElementById("apriAncora");
            const apriInventario = document.getElementById("apriInventario");
            const divApriAncora = document.getElementById("divApriAncora");
            const wrapper = document.getElementById("bagliore-wrapper");

            const soloSpecialiCheckbox = document.getElementById("SoloSpeciali");
            const rimuoviAnimeCheckbox = document.getElementById("RimuoviAnime");
            const soloPoppyCheckbox = document.getElementById("SoloPoppy");

            const codiceSegreto = document.getElementById("codiceSegreto");

            var rarityProbabilities = aggiornaRarita();
            let isProcessing = false;

            var casseAperte;
            var comuniDiFila;

            function getRandomRarity() {
                const totalWeight = Object.values(rarityProbabilities).reduce((sum, weight) => sum + weight, 0);
                let randomNum = Math.random() * totalWeight;

                for (let [rarity, weight] of Object.entries(rarityProbabilities)) {
                    if (randomNum < weight) {
                        return rarity;
                    }
                    randomNum -= weight;
                }
            }

            function getCookie(name) {
                const cookies = document.cookie.split("; ");
                for (let cookie of cookies) {
                    let [key, value] = cookie.split("=");
                    if (key === name) return JSON.parse(value);
                }
                return null;
            }

            function setCookie(name, value) {
                document.cookie = `${name}=${JSON.stringify(value)}; path=/; expires=Fri, 31 Dec 9999 23:59:59 GMT`;
            }

            async function addToInventory(character) {
                const response = await fetch(`https://cripsum.com/api/add_character_to_inventory?character_id=${character.id}`);
                const data = await response.json();

                if (data.status === 'success') {
                    let inventory = JSON.parse(localStorage.getItem("inventory")) || [];
                    let characterFound = inventory.find((p) => p.nome === character.nome);

                    if (!characterFound) {
                        inventory.push({ ...character, count: 1 });
                        testoNuovo();
                    } else {
                        characterFound.count++;
                    }

                    localStorage.setItem("inventory", JSON.stringify(inventory));
                } else {
                    alert(data.message);
                }
            }

            async function getInventory() {
                const response = await fetch('https://cripsum.com/api/api_get_inventario');
                const data = await response.json();

                localStorage.setItem("inventory", JSON.stringify(data));
                return data;
            }

            async function resettaInventario() {
                if (!confirm("Sei sicuro di voler resettare l'inventario? Tutti i personaggi saranno persi!")) {
                    return;
                }

                const response = await fetch('https://cripsum.com/api/delete_inventory', {
                    method: 'DELETE',
                });

                const data = await response.json();
                if (data.status === 'success') {
                    localStorage.setItem("inventory", JSON.stringify([]));
                    setCookie("casseAperte", 0);
                    setCookie("comuniDiFila", 0);
                    setCookie("preferences", {});
                    setLastCharacterFound("");
                    localStorage.clear();
                    alert("Inventario resettato con successo!");
                    location.reload();
                } else {
                    alert(data.message);
                }
            }

            async function getAllCharacters() {
                const response = await fetch('https://cripsum.com/api/get_all_characters');
                const data = await response.json();
                return data;
            }

            async function getCharacterNumber() {
                const response = await fetch('https://cripsum.com/api/api_get_characters_num');
                const data = await response.json();
                return data;
            }

            async function getRandomPull() {
                const allCharacters = await getAllCharacters();
                const selectedRarity = getRandomRarity();
                const filteredRarities = allCharacters.filter((item) => item.rarità === selectedRarity);
                return filteredRarities[Math.floor(Math.random() * filteredRarities.length)];
            }

            function salvaPreferenze() {
                const preferences = {};
                const checkboxes = document.querySelectorAll(".checco");
                checkboxes.forEach((checkbox) => {
                    preferences[checkbox.id] = checkbox.checked;
                });
                document.cookie = `preferences=${JSON.stringify(preferences)}; path=/; expires=Fri, 31 Dec 9999 23:59:59 GMT`;

                rarityProbabilities = aggiornaRarita();
            }

            document.addEventListener("DOMContentLoaded", () => {
                const preferences = getCookie("preferences");

                if (preferences) {
                    const checkboxes = document.querySelectorAll(".checco");
                    checkboxes.forEach((checkbox) => {
                        checkbox.checked = preferences[checkbox.id];
                    });
                }
                rarityProbabilities = aggiornaRarita();
            });

            function aggiornaRarita() {
                const preferences = getCookie("preferences");

                if (preferences) {
                    if (preferences.SoloSpeciali === true) {
                        return (rarityProbabilities = {
                            comune: 0,
                            raro: 0,
                            epico: 0,
                            leggendario: 0,
                            speciale: 100,
                            segreto: 0,
                        });
                    } else if (preferences.SoloSegreti === true) {
                        return (rarityProbabilities = {
                            comune: 0,
                            raro: 0,
                            epico: 0,
                            leggendario: 0,
                            speciale: 0,
                            segreto: 100,
                        });
                    } else if (preferences.SoloComuni === true) {
                        return (rarityProbabilities = {
                            comune: 100,
                            raro: 0,
                            epico: 0,
                            leggendario: 0,
                            speciale: 0,
                            segreto: 0,
                        });
                    } else {
                        return (rarityProbabilities = {
                            comune: 52,
                            raro: 27,
                            epico: 12,
                            leggendario: 8,
                            speciale: 0.9,
                            segreto: 0.1,
                        });
                    }
                }

                return (rarityProbabilities = {
                    comune: 52,
                    raro: 27,
                    epico: 12,
                    leggendario: 8,
                    speciale: 0.9,
                    segreto: 0.1,
                });
            }

            function getAllPossiblePulls() {
                return rarities;
            }

            async function filtroPull() {
                const preferences = getCookie("preferences");

                if (preferences) {
                    if (preferences.SoloPoppy === true) {
                        while (true) {
                            const pull = await getRandomPull();
                            if (pull.categoria === "poppy") {
                                return pull;
                            }
                        }
                    }
                    if (preferences.RimuoviAnime === true) {
                        while (true) {
                            const pull = await getRandomPull();
                            if (pull.categoria !== "anime") {
                                return pull;
                            }
                        }
                    }
                }
                return await getRandomPull();
            }

            async function pullaPersonaggio(){

                if (isProcessing) {
                    return;
                }
                    
                isProcessing = true;

                try {
                    const pull = await filtroPull();
                    
                    document.getElementById("contenuto").innerHTML = `
                        <p style="top 10px; font-size: 20px; max-width: 600px;" id="nomePersonaggio">${pull.nome}</p>
                        <img src="/img/${pull.img_url}" alt="Premio" class="premio" />
                    `;
                    
                    await addToInventory(pull);
                    
                    if (typeof setLastCharacterFound === 'function') {
                        setLastCharacterFound(pull.nome);
                    }

                    const rarita = pull.rarità;
                    setComuniDiFila(rarita);

                    if (rarita === "comune") {
                        messaggioRarita.innerText = "bravo fra hai pullato un personaggio comune, skill issue xd";
                        bagliore.style.background = "radial-gradient(circle, rgba(150, 150, 150, 1) 0%, rgba(255, 255, 0, 0) 70%)";
                    } else if (rarita === "leggendario") {
                        messaggioRarita.innerText = "che fortuna, hai pullato un personaggio leggendario!";
                        bagliore.style.background = "radial-gradient(circle, rgba(255, 228, 23, 1) 0%, rgba(0, 0, 255, 0) 70%)";
                    } else if (rarita === "epico") {
                        messaggioRarita.innerText = "hai pullato un personaggio epico, tanta roba, ma poteva andare meglio";
                        bagliore.style.background = "radial-gradient(circle, rgba(195, 0, 235, 1) 0%, rgba(0, 0, 255, 0) 70%)";
                    } else if (rarita === "raro") {
                        messaggioRarita.innerText = "buono dai, hai pullato un personaggio raro!";
                        bagliore.style.background = "radial-gradient(circle, rgba(0, 74, 247, 1) 0%, rgba(0, 0, 255, 0) 70%)";
                    } else if (rarita === "speciale") {
                        messaggioRarita.innerText = "COM'È POSSIBILE? HAI PULLATO UN PERSONAGGIO SPECIALE!";

                        bagliore.style.position = "fixed";
                        bagliore.style.width = "100vw";
                        bagliore.style.height = "100vh";
                        bagliore.style.zIndex = "-1";

                        bagliore.style.background = "linear-gradient(90deg, #ff0000, #ff7300, #fffb00, #48ff00, #00f7ff, #2b65ff, #8000ff, #ff0000)";
                        bagliore.style.backgroundSize = "300% 100%";
                        bagliore.style.animation = "rainbowBackground 6s linear infinite";
                    } else if (rarita === "segreto") {

                        startIntroAnimation(pull.nome);
                        messaggioRarita.innerText = "COSA? HAI PULLATO UN PERSONAGGIO SEGRETO? aura.";
                        bagliore.style.position = "fixed";
                        bagliore.style.width = "100vw";
                        bagliore.style.height = "100vh";
                        bagliore.style.zIndex = "-1";

                    }
                    document.getElementById("suonoCassa").innerHTML = `
                        <source src="/audio/${pull.audio_url}" type="audio/mpeg" id="suono" />
                    `;
                    
                } catch (error) {
                    console.error('Errore nel pull del personaggio:', error);
                    messaggioRarita.innerText = "Errore durante l'apertura della cassa. Riprova.";
                } finally {
                    setTimeout(() => {
                        isProcessing = false;
                    }, 1000);
                }
            }

            document.addEventListener("DOMContentLoaded", function() {
            document.addEventListener("keydown", function (event) {
                if (event.code === "Space") {
                    event.preventDefault(); 

                    if (!cassa.classList.contains("aperta")) {
                        if (!contenuto.classList.contains("salto")) {
                            pullaPersonaggio().then(() => {
                                bagliore.style.opacity = 0.6;
                                bagliore.style.transform = "translate(-50%, -50%) scale(1.5)";

                                audio.currentTime = 0;
                                audio.play();

                                generaParticelle();
                                apriCassa();
                                apriVeloce();
                            });
                        }
                    } else {
                        apriVeloce();
                    }
                }
            });

            document.addEventListener("keydown", function (event) {
                if (event.code === "KeyR" || event.code === "Enter") {
                    event.preventDefault();
                    refresh();
                }
            });
        });

            async function riscattaCodice() {
                if (codiceSegreto.value === "godo") {
                    const inventory = await getInventory();
                    if (inventory.find((p) => p.nome === "CRIPSUM")) {
                        alert("il Codice è già riscattato o cripsum è già nel tuo inventario!");
                        return;
                    }
                    let pullRiscattata = getCharacter("CRIPSUM");
                    addToInventory(pullRiscattata);
                    alert("Codice riscattato con successo! cripsum è stato aggiunto al tuo inventario!");
                } else {
                    alert("Codice non valido, skill issue!");
                }
            }

            function getCharacter(name) {
                return rarities.find((p) => p.name === name);
            }

            async function apriCassa() {
                const casseAperteResponse = await fetch('https://cripsum.com/api/get_casse_aperte');
                const casseAperteData = await casseAperteResponse.json();
                const casseAperte = await casseAperteData.total;
                const inventory = await getInventory();
                comuniDiFila = getCookie("comuniDiFila") || 0;

                if (comuniDiFila === 10) {
                    unlockAchievement(9);
                }

                if (casseAperte === 100) {
                    unlockAchievement(8);
                }
                if (casseAperte === 500) {
                    unlockAchievement(16);
                }
                if (inventory.length === 1) {
                    unlockAchievement(5);
                }
                if (inventory.length === 44) {
                    unlockAchievement(18);
                }
            }

            async function apriNormale() {
                cassa.onclick = null;
                await apriCassa();

                generaParticelle();

                bagliore.style.opacity = 0.6;
                bagliore.style.transform = "translate(-50%, -50%) scale(1.5)";

                audio.currentTime = 0;
                audio.play();

                cassa.src = "../img/cassa_aperta.png";
                cassa.classList.add("aperta");

                setTimeout(() => {
                    contenuto.classList.add("salto");
                    messaggio.classList.add("salto");
                    cassa.classList.add("dissolvi");
                }, 3000);

                setTimeout(() => {
                    divApriAncora.classList.remove("nascosto");
                    divApriAncora.classList.add("salto");
                }, 4000);
            }

            function testoNuovo() {
                let newLabel = document.createElement("span");
                newLabel.classList.add("new-label");
                newLabel.innerText = "NEW!";
                contenuto.appendChild(newLabel);
            }

            function apriVeloce() {
                contenuto.classList.add("salto");
                messaggio.classList.add("salto");
                cassa.classList.add("dissolvi");

                divApriAncora.classList.remove("nascosto");
                divApriAncora.classList.add("salto");

                cassa.onclick = null;
            }

            function generaParticelle() {
                const container = document.getElementById("particelle");
                const cassa = document.getElementById("cassa");
                const rect = cassa.getBoundingClientRect();

                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;

                for (let i = 0; i < 100; i++) {
                    const particella = document.createElement("div");
                    particella.classList.add("particella");

                    particella.style.left = `${centerX}px`;
                    particella.style.top = `${centerY}px`;

                    const angle = Math.random() * 2 * Math.PI;
                    const distance = Math.random() * 200 + 50;
                    const x = Math.cos(angle) * distance;
                    const y = Math.sin(angle) * distance;

                    particella.style.setProperty("--x", `${x}px`);
                    particella.style.setProperty("--y", `${y}px`);

                    container.appendChild(particella);

                    setTimeout(() => particella.remove(), 2000);
                }
            }

            function refresh() {
                location.reload();
            }

            function setComuniDiFila(rarita) {
                comuniDiFila = getCookie("comuniDiFila") || 0;
                if (rarita === "comune") {
                    comuniDiFila++;
                    setCookie("comuniDiFila", comuniDiFila);
                } else {
                    setCookie("comuniDiFila", 0);
                }
            }

            function getComuniDiFila(rarita) {
                tempComuniDiFila = getCookie("comuniDiFila") || 0;
                if (rarita === "comune") {
                    tempComuniDiFila++;
                    setCookie("comuniDiFila", tempComuniDiFila);
                    return tempComuniDiFila;
                } else {
                    setCookie("comuniDiFila", 0);
                    return tempComuniDiFila;
                }
            }

            function startIntroAnimation(nome_personaggio) {
                const introOverlay = document.createElement('div');
                introOverlay.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100vw;
                    height: 100vh;
                    background: #000;
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    overflow: hidden;
                    opacity: 0;
                    transition: opacity 0.8s ease-in-out;
                `;

                const purpleContainer = document.createElement('div');
                purpleContainer.style.cssText = `
                    position: relative;
                    width: 100%;
                    height: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    opacity: 0;
                    transform: scale(0.8);
                    transition: opacity 1s ease-out 0.3s, transform 1s ease-out 0.3s;
                `;

                const purpleCircle = document.createElement('div');
                purpleCircle.style.cssText = `
                    width: 300px;
                    height: 300px;
                    border-radius: 50%;
                    background: radial-gradient(circle, rgba(147, 0, 211, 1) 0%, rgba(75, 0, 130, 0.9) 30%, rgba(138, 43, 226, 0.7) 60%, transparent 100%);
                    animation: epicPulse 2s ease-in-out infinite;
                    box-shadow: 0 0 50px rgba(147, 0, 211, 0.8), 0 0 100px rgba(75, 0, 130, 0.6), inset 0 0 30px rgba(138, 43, 226, 0.4);
                    filter: brightness(1.2) saturate(1.3);
                    opacity: 0;
                    transform: scale(0.5);
                    animation-delay: 0.8s;
                    transition: opacity 0.8s ease-out 0.8s, transform 0.8s ease-out 0.8s;
                `;

                const mysteriousText = document.createElement('div');
                mysteriousText.style.cssText = `
                    position: absolute;
                    color:rgb(255, 255, 255);
                    font-size: 10rem;
                    font-weight: bold;
                    text-shadow: 0 0 20px #9932cc, 0 0 40px #4b0082;
                    opacity: 0;
                    transform: scale(0.3);
                    transition: opacity 1s ease-out 1s, transform 1s ease-out 2.5s;
                `;
                mysteriousText.textContent = 'オーラシグマゴッド';

                purpleContainer.appendChild(purpleCircle);
                purpleContainer.appendChild(mysteriousText);
                introOverlay.appendChild(purpleContainer);
                document.body.appendChild(introOverlay);

                setTimeout(() => {
                    introOverlay.style.opacity = '1';
                    purpleContainer.style.opacity = '1';
                    purpleContainer.style.transform = 'scale(1)';
                    
                    setTimeout(() => {
                        purpleCircle.style.opacity = '1';
                        purpleCircle.style.transform = 'scale(1)';
                    }, 300);

                    setTimeout(() => {
                        mysteriousText.style.opacity = '1';
                        mysteriousText.style.transform = 'scale(1)';
                    }, 1000);

                    bagliore.style.background = "radial-gradient(circle, rgba(147, 0, 211, 1) 0%, rgba(75, 0, 130, 0.8) 30%, rgba(138, 43, 226, 0.6) 60%, rgba(148, 0, 211, 0) 100%)";
                    bagliore.style.animation = "secretGlowRotate 8s ease-in-out infinite";
                    bagliore.style.boxShadow = "0 0 100px rgba(147, 0, 211, 0.8), 0 0 200px rgba(75, 0, 130, 0.6), inset 0 0 50px rgba(138, 43, 226, 0.4)";
                    bagliore.style.borderRadius = "50%";
                    bagliore.style.width = "150vw";
                    bagliore.style.height = "150vw";
                    
                }, 100);

                setTimeout(() => {
                    introOverlay.style.opacity = '0';
                    setTimeout(() => {
                        document.body.removeChild(introOverlay);
                    }, 800);
                }, 4000);
            }

            // Initialize everything
            createStars();

            // Show admin cheats if user is admin
            document.addEventListener("DOMContentLoaded", function() {
                // This would normally come from PHP, but for demo purposes
                // You can uncomment the line below to show admin cheats
                // document.getElementById('admin-cheats').style.display = 'block';
            });
        </script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>