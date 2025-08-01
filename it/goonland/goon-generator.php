<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
error_reporting(1);

ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
checkBan($mysqli);

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Per accedere a GoonLand devi essere loggato";

    header('Location: ../accedi');
    exit();
}

if (isset($_SESSION['nsfw']) && $_SESSION['nsfw'] == 0) {
    $_SESSION['error_message'] = "Per accedere a GoonLand devi abilitare i contenuti NSFW nelle impostazioni del tuo profilo";
    header('Location: ../home');
    exit();
}

$topGooners = [];
$query = "SELECT username, clickgoon FROM utenti WHERE clickgoon > 0 ORDER BY clickgoon DESC LIMIT 10";
$result = $mysqli->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $topGooners[] = $row;
    }
    $result->free();
} else {
    error_log("Error fetching gooners leaderboard: " . $mysqli->error);
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

        .dropdownutenti .dropdown-menu {
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
            background: linear-gradient(135deg, rgba(255, 126, 201, 0.8), rgb(255, 152, 255));
            border: 0px solid rgba(255, 255, 255, 0);
            border-radius: 8px;
            box-shadow: 0 0 8px 4px rgba(0, 0, 0, 0.5);
            z-index: 9999;
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

        .classifica {
            margin-top: 30px;
            background: rgba(255, 255, 255, 0.8);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .classifica table {
            width: 100%;
            border-collapse: collapse;
        }

        .classifica th, .classifica td {
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }

        .classifica th {
            background: #f8f9fa;
            color: #d63384;
        }

        .classifica tr:nth-child(even) {
            background: #f2f2f2;
        }

        .leaderboard {
    margin-top: 40px;
    background: rgba(255, 255, 255, 0.95);
    padding: 30px;
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(214, 51, 132, 0.2);
    max-width: 600px;
    width: 100%;
}

.leaderboard-title {
    font-size: 2rem;
    font-weight: bold;
    color: #d63384;
    text-align: center;
    margin-bottom: 25px;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
}

.leaderboard-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.leaderboard-table thead {
    background: linear-gradient(45deg, #d63384, #f06292);
    color: white;
}

.leaderboard-table th {
    padding: 15px 20px;
    text-align: center;
    font-weight: bold;
    font-size: 1.1rem;
    letter-spacing: 0.5px;
}

.leaderboard-table td {
    padding: 12px 20px;
    text-align: center;
    border-bottom: 1px solid #f0f0f0;
    font-size: 1rem;
}

.leaderboard-table tbody tr:hover {
    background: linear-gradient(90deg, rgba(214, 51, 132, 0.05), rgba(240, 98, 146, 0.05));
    transform: scale(1.01);
    transition: all 0.2s ease;
}

.position {
    font-weight: bold;
    color: #d63384;
    font-size: 1.1rem;
}

.position.gold {
    color: #ffd700;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
}

.position.silver {
    color: #c0c0c0;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
}

.position.bronze {
    color: #cd7f32;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
}

.username {
    font-weight: 600;
    color: #333;
}

.username a {
    color: #d63384;
    text-decoration: none;
    transition: color 0.2s ease;
}

.username a:hover {
    color: #f06292;
    text-decoration: underline;
}

.clicks {
    font-weight: bold;
    color: #d63384;
    font-size: 1.1rem;
}

.no-data {
    text-align: center;
    color: #999;
    font-style: italic;
    padding: 30px;
}

@media (max-width: 768px) {
    .leaderboard {
        margin: 30px 10px;
        padding: 20px;
    }
    
    .leaderboard-title {
        font-size: 1.7rem;
    }
    
    .leaderboard-table th,
    .leaderboard-table td {
        padding: 10px 8px;
        font-size: 0.9rem;
    }
    
    .leaderboard-table th:first-child,
    .leaderboard-table td:first-child {
        width: 15%;
    }
    
    .leaderboard-table th:last-child,
    .leaderboard-table td:last-child {
        width: 25%;
    }
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
                        <!--<div class="alert alert-danger fadeup" role="alert" style="max-width: 800px">
                        ci scusiamo per il disagio, ma l'API di generazione delle immagini è attualmente in manutenzione. Stiamo lavorando per ripristinare il servizio il prima possibile. Grazie per la vostra pazienza
                        </div>-->

    <h1 class="title">GOONLAND</h1>

    
    <div class="image-container text-center" id="imageContainer" style="text-align: center;">
        <div class="placeholder-text">Clicca il tasto qui sotto per generare la prima foto</div>
        <div class="loading-spinner" id="loadingSpinner"></div>
    </div>

    <div style="margin: 20px 0;">
        <label for="contentType" style="color: #d63384; font-weight: bold; display: block; margin-bottom: 10px; text-align: center;">
            Seleziona tipo di contenuto:
        </label>
        <select id="contentType" style="padding: 10px; border-radius: 10px; border: 2px solid #d63384; background: white; color: #d63384; font-weight: bold; display: block; margin: 0 auto; min-width: 200px; text-align: center;">
            <option value="waifu">Waifu</option>
            <option value="neko">Neko</option>
            <option value="trap">Trap</option>
            <option value="blowjob">Blowjob</option>
        </select>
    </div>
    
    <div style="display: flex; gap: 15px; align-items: center; justify-content: center;">
        <button class="generate-btn" id="generateBtn" onclick="generateImage()">
            Genera Nuova Foto
        </button>
        <button class="generate-btn" id="downloadBtn" onclick="downloadImage()" style="width: 60px; height: 60px; border-radius: 50%; padding: 0; display: none;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 16L7 11L8.4 9.6L11 12.2V4H13V12.2L15.6 9.6L17 11L12 16Z" fill="currentColor"/>
                <path d="M5 20V18H19V20H5Z" fill="currentColor"/>
            </svg>
        </button>
    </div>
    
    <div class="countdown" id="countdown"></div>

    <div class="leaderboard fadeup">
    <h2 class="leaderboard-title">🏆 TOP 10 GOONERS 🏆</h2>
    
    <?php if (!empty($topGooners)): ?>
        <table class="leaderboard-table">
            <thead>
                <tr>
                    <th>Posizione</th>
                    <th>Gooner</th>
                    <th>Click</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topGooners as $index => $gooner): 
                    $position = $index + 1;
                    $positionClass = '';
                    $emoji = '';
                    
                    switch ($position) {
                        case 1:
                            $positionClass = 'gold';
                            $emoji = '🥇';
                            break;
                        case 2:
                            $positionClass = 'silver';
                            $emoji = '🥈';
                            break;
                        case 3:
                            $positionClass = 'bronze';
                            $emoji = '🥉';
                            break;
                        default:
                            $emoji = '#' . $position;
                    }
                ?>
                    <tr>
                        <td>
                            <span class="position <?php echo $positionClass; ?>">
                                <?php echo $emoji; ?>
                            </span>
                        </td>
                        <td class="username">
                            <a href="/user/<?php echo htmlspecialchars($gooner['username']); ?>">
                                <?php echo htmlspecialchars($gooner['username']); ?>
                            </a>
                        </td>
                        <td class="clicks">
                            <?php echo number_format($gooner['clickgoon']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">
            Nessun dato disponibile per la classifica
        </div>
    <?php endif; ?>
</div>

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
            const contentType = document.getElementById('contentType').value;
            const downloadBtn = document.getElementById('downloadBtn');
            
            // Reset countdown and download button
            downloadBtn.style.display = 'flex';
            
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
                const response = await fetch('https://api.waifu.pics/nsfw/' + contentType);
                const data = await response.json();
                
                const img = new Image();
                img.onload = function() {
                    const displayImg = document.createElement('img');
                    displayImg.src = data.url;
                    displayImg.className = 'generated-image';
                    displayImg.style.opacity = '0';
                    
                    container.appendChild(displayImg);
                    
                    setTimeout(async () => {
                        displayImg.style.opacity = '1';
                        spinner.style.display = 'none';
                        isLoading = false;

                        await fetch('https://cripsum.com/api/incrementa_counter_goon');
                        let clickResponse = await fetch('https://cripsum.com/api/get_clickgoon');
                        let clickData = await clickResponse.json();
                        let click = clickData.total;

                        if(click == 100){
                            unlockAchievement(19);
                        }
                        
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


        function checkDaysVisitedGoon() {
            let daysVisitedGoon = getCookie("daysVisitedGoon") || [];
            const today = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
            if (!daysVisitedGoon.includes(today)) {
                daysVisitedGoon.push(today);
                setCookie("daysVisitedGoon", daysVisitedGoon);
            }
            if (daysVisitedGoon.length >= 10) {
                unlockAchievement(20);
            }
        }

        checkDaysVisitedGoon();

        function downloadImage() {
            const img = document.querySelector('.generated-image');
            if (!img) return;
            const link = document.createElement('a');
            link.href = img.src;
            link.download = 'goonland_image.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        
        //setTimeout(() => {
        //    generateImage();
        //}, 1000);
    </script>
    </body>
</html>
