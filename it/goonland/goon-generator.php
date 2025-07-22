<?php

ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: accedi');
    exit();
}

if (isset($_SESSION['nsfw']) && $_SESSION['nsfw'] == 0) {
    header('Location: home');
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <?php include '../../includes/head-import.php'; ?>
        <title>GoonLand™ - Goon Generator</title>
        <script src="/js/nomePagina.js"></script>
        <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 50%, #fecfef 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .title {
            font-size: 3.5rem;
            font-weight: bold;
            color: #d63384;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            margin-bottom: 30px;
            text-align: center;
            letter-spacing: 2px;
        }
        
        .image-container {
            position: relative;
            width: 400px;
            height: 400px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(214, 51, 132, 0.3);
            background: white;
            margin: 0 auto 30px auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .generated-image {
            max-width: 400px;
            max-height: 400px;
            object-fit: cover;
            transition: opacity 0.5s ease-in-out;
            display: block;
        }
        
        .loading-spinner {
            position: absolute;
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #d63384;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: none;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .generate-btn {
            display: block;
            margin: 0 auto;
            padding: 15px 30px;
            font-size: 1.2rem;
            background: linear-gradient(45deg, #d63384, #f06292);
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(214, 51, 132, 0.4);
            transition: all 0.3s ease;
            font-weight: bold;
            letter-spacing: 1px;
        }
        
        .generate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(214, 51, 132, 0.6);
        }
        
        .generate-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .countdown {
            margin: 10px auto 0 auto;
            color: #d63384;
            font-weight: bold;
            min-height: 20px;
            text-align: center;
        }

        .crediti{
            margin: 10px auto 0 auto;
            color: #d63384;
            font-weight: bold;
            min-height: 20px;
            text-align: center;
        }
        
        .placeholder-text {
            color: #999;
            font-size: 1.2rem;
            text-align: center;
        }
        
        @media (max-width: 480px) {
            .title {
                font-size: 2.5rem;
                margin-bottom: 20px;
            }
            
            .image-container {
                width: 300px;
                height: 300px;
            }
            
            .generate-btn {
                padding: 12px 25px;
                font-size: 1rem;
            }
        }
    </style>
    </head>

    <body class="">
        <?php include '../../includes/navbar-goonland.php'; ?>
        <?php include '../../includes/impostazioni.php'; ?>

        <div class="testobianco fadeup" style="padding-top: 7rem; display: flex; flex-direction: column; align-items: center; justify-content: center;">

    <h1 class="title">GOONLAND</h1>
    
    <div class="image-container text-center" id="imageContainer" style="text-align: center;">
        <div class="placeholder-text">Clicca il tasto qui sotto per generare la prima foto</div>
        <div class="loading-spinner" id="loadingSpinner"></div>
    </div>
    
    <button class="generate-btn" id="generateBtn" onclick="generateImage()">
        Genera Nuova Foto
    </button>
    
    <div class="countdown" id="countdown"></div>
        </div>

        <div id="achievement-popup" class="popup">
            <img id="popup-image" src="" alt="Achievement" />
            <div>
                <h3 id="popup-title"></h3>
                <p id="popup-description"></p>
            </div>
        </div>
        <footer class="my-5 pt-5 text-muted text-center text-small fadeup">
            <p class="crediti mb-2">Si ringrazia <a href="/user/zakator" class="arcobaleno testo-arcobaleno" style="font-weight:bolder;">Zakator</a> per il contributo creativo e tecnico nella realizzazione di GoonLand e delle relative funzionalità di gioco</p>
            <p class="mb-1 testobianco">Copyright © 2021-2025 Cripsum™. Tutti i diritti riservati.</p>
            <ul class="list-inline">
                <li class="list-inline-item"><a href="../privacy" class="linkbianco">Privacy</a></li>
                <li class="list-inline-item"><a href="../tos" class="linkbianco">Termini</a></li>
                <li class="list-inline-item"><a href="../supporto" class="linkbianco">Supporto</a></li>
            </ul>
        </footer>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="/js/modeChanger.js"></script>
            
    <script>
        let isLoading = false;
        let cooldownActive = false;
        
        async function generateImage() {
            if (isLoading || cooldownActive) return;
            
            const btn = document.getElementById('generateBtn');
            const spinner = document.getElementById('loadingSpinner');
            const container = document.getElementById('imageContainer');
            const countdown = document.getElementById('countdown');
            
            isLoading = true;
            btn.disabled = true;
            btn.textContent = 'Caricamento...';
            spinner.style.display = 'block';
            
            const existingImg = container.querySelector('.generated-image');
            if (existingImg) {
                existingImg.style.opacity = '0';
                setTimeout(() => existingImg.remove(), 300);
            }
            
            const placeholder = container.querySelector('.placeholder-text');
            if (placeholder) {
                placeholder.style.display = 'none';
            }
            
            try {
                const response = await fetch('https://api.waifu.pics/nsfw/waifu');
                const data = await response.json();
                
                const img = new Image();
                img.onload = function() {
                    const displayImg = document.createElement('img');
                    displayImg.src = data.url;
                    displayImg.className = 'generated-image';
                    displayImg.style.opacity = '0';
                    
                    container.appendChild(displayImg);
                    
                    setTimeout(() => {
                        displayImg.style.opacity = '1';
                        spinner.style.display = 'none';
                        isLoading = false;
                        
                        startCooldown();
                    }, 100);
                };
                
                img.onerror = function() {
                    throw new Error('Errore nel caricamento dell\'immagine');
                };
                
                img.src = data.url;
                
            } catch (error) {
                console.error('Errore:', error);
                spinner.style.display = 'none';
                isLoading = false;
                btn.disabled = false;
                btn.textContent = 'Genera Nuova Foto';
                
                const errorMsg = document.createElement('div');
                errorMsg.textContent = 'Errore nel caricamento. Riprova.';
                errorMsg.style.color = '#d63384';
                errorMsg.style.textAlign = 'center';
                container.appendChild(errorMsg);
                
                setTimeout(() => errorMsg.remove(), 3000);
            }
        }
        
        function startCooldown() {
            cooldownActive = true;
            const btn = document.getElementById('generateBtn');
            const countdown = document.getElementById('countdown');
            
            let timeLeft = 3;
            
            const updateCountdown = () => {
                countdown.textContent = `Attendi ${timeLeft} secondi...`;
                btn.textContent = `Attendi (${timeLeft}s)`;
                
                if (timeLeft <= 0) {
                    cooldownActive = false;
                    btn.disabled = false;
                    btn.textContent = 'Genera Nuova Foto';
                    countdown.textContent = '';
                    return;
                }
                
                timeLeft--;
                setTimeout(updateCountdown, 1000);
            };
            
            updateCountdown();
        }
        
        //setTimeout(() => {
        //    generateImage();
        //}, 1000);
    </script>
    </body>
</html>
