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

if (isset($_GET['download_image']) && $_GET['download_image'] == '1' && isset($_GET['url'])) {
    $url = $_GET['url'];
    
    // Controllo sicurezza base: consenti solo immagini da waifu.pics
    if (strpos($url, 'https://i.waifu.pics/') !== 0) {
        http_response_code(403);
        exit('URL non valido');
    }

    $imageData = @file_get_contents($url);
    if ($imageData === false) {
        http_response_code(404);
        exit('Immagine non trovata');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($imageData);

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="goonland_image.' . explode('/', $mimeType)[1] . '"');
    echo $imageData;
    exit;
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
        <link rel="stylesheet" href="/css/style-goonland.css?v=5" />
        <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
    </style>
    </head>

    <body class="">
        <?php include '../../includes/navbar-goonland.php'; ?>
        <?php include '../../includes/impostazioni.php'; ?>

        <div class="testobianco fadeup goonland-container" style="padding-top: 7rem; display: flex; flex-direction: column; align-items: center; justify-content: center; padding-bottom: 4rem;">
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
            <option value="waifu" style=" color: #d63384;">Waifu</option>
            <option value="neko" style=" color: #d63384;">Neko</option>
            <option value="trap" style=" color: #d63384;">Trap</option>
            <option value="blowjob" style=" color: #d63384;">Blowjob</option>
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
        <?php include '../../includes/footer.php'; ?>
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
            downloadBtn.style.display = 'block';
            
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

        async function downloadImage() {
            const img = document.querySelector('.generated-image');
            if (!img) return;

            try {
                const proxyUrl = window.location.pathname + '?download_image=1&url=' + encodeURIComponent(img.src);

                const link = document.createElement('a');
                link.href = proxyUrl;
                link.download = ''; // il download avviene lato server
                link.target = '_blank';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } catch (error) {
                console.error('Errore nel download:', error);
            }
        }


        
        //setTimeout(() => {
        //    generateImage();
        //}, 1000);
    </script>
    </body>
</html>
