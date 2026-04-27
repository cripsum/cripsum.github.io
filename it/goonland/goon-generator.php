<?php
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../../config/session_init.php';
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

if (isset($_GET['download_image']) && $_GET['download_image'] === '1' && isset($_GET['url'])) {
    $url = trim((string)$_GET['url']);

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
    $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if (!in_array($mimeType, $allowedMime, true)) {
        http_response_code(415);
        exit('Formato non valido');
    }

    $extension = explode('/', $mimeType)[1] ?? 'jpg';

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="goonland_image.' . $extension . '"');
    echo $imageData;
    exit;
}

$topGooners = [];

$stmt = $mysqli->prepare("SELECT username, clickgoon FROM utenti WHERE clickgoon > 0 ORDER BY clickgoon DESC LIMIT 10");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $topGooners[] = $row;
    }

    $stmt->close();
} else {
    error_log("Error preparing gooners leaderboard: " . $mysqli->error);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../../includes/head-import.php'; ?>
    <title>GoonLand™ - Generator</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/css/goonland.css?v=2.0">
    <script src="/js/goonland.js?v=2.0" defer></script>
</head>

<body class="goonland-page" data-goonland-page="generator">
    <?php include '../../includes/navbar-goonland.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>

    <div class="gl-bg" aria-hidden="true"><span></span><span></span></div>

    <main class="gl-shell">
        <section class="gl-hero gl-hero-small gl-reveal">
            <div class="gl-hero-text">
                <span class="gl-kicker"><i class="fas fa-wand-magic-sparkles"></i> Generator</span>
                <h1>Goon Generator</h1>
                <p>Genera una foto, scaricala se vuoi e scala la classifica. Stessa funzione, interfaccia più pulita.</p>
                <div class="gl-actions">
                    <a class="gl-btn gl-btn-ghost" href="/it/goonland/home"><i class="fas fa-arrow-left"></i> Home GoonLand</a>
                </div>
            </div>
        </section>

        <section class="gl-generator-layout">
            <div class="gl-generator gl-reveal">
                <div class="gl-generator-head">
                    <div>
                        <span class="gl-kicker">Output</span>
                        <h2>Generatore immagini</h2>
                    </div>
                    <span class="gl-status" id="generatorStatus">Pronto</span>
                </div>

                <div class="gl-image-stage" id="imageContainer">
                    <div class="gl-placeholder">
                        <i class="fas fa-image"></i>
                        <strong>Nessuna immagine ancora</strong>
                        <span>Clicca il tasto rosa per generare la prima foto.</span>
                    </div>
                    <div class="gl-spinner" id="loadingSpinner" aria-hidden="true"></div>
                </div>

                <div class="gl-controls">
                    <label class="gl-select-field" for="contentType">
                        <span>Tipo contenuto</span>
                        <select id="contentType">
                            <option value="sfw/waifu">Waifu - SFW</option>
                            <option value="nsfw/waifu">Waifu - 18+</option>
                            <option value="nsfw/neko">Neko - 18+</option>
                            <option value="nsfw/trap">Trap - 18+</option>
                            <option value="nsfw/blowjob">BJ - 18+</option>
                        </select>
                    </label>

                    <div class="gl-generator-buttons">
                        <button class="gl-btn gl-btn-main" id="generateBtn" type="button" onclick="generateImage()">
                            <i class="fas fa-shuffle"></i> Genera nuova foto
                        </button>
                        <button class="gl-icon-btn" id="downloadBtn" type="button" onclick="downloadImage()" aria-label="Scarica immagine" hidden>
                            <i class="fas fa-download"></i>
                        </button>
                    </div>

                    <div class="gl-countdown" id="countdown" aria-live="polite"></div>
                </div>
            </div>

            <aside class="gl-leaderboard gl-reveal">
                <div class="gl-leaderboard-head">
                    <span class="gl-kicker">Classifica</span>
                    <h2>Top 10</h2>
                    <p>Chi ha cliccato di più.</p>
                </div>

                <?php if (!empty($topGooners)): ?>
                    <div class="gl-rank-list">
                        <?php foreach ($topGooners as $index => $gooner): ?>
                            <?php
                            $position = $index + 1;
                            $rankClass = $position === 1 ? 'is-gold' : ($position === 2 ? 'is-silver' : ($position === 3 ? 'is-bronze' : ''));
                            $rankLabel = $position <= 3 ? ['🥇', '🥈', '🥉'][$position - 1] : '#' . $position;
                            ?>
                            <a class="gl-rank <?php echo $rankClass; ?>" href="/user/<?php echo htmlspecialchars($gooner['username'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="gl-rank-pos"><?php echo $rankLabel; ?></span>
                                <span class="gl-rank-user"><?php echo htmlspecialchars($gooner['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <strong><?php echo number_format((int)$gooner['clickgoon']); ?></strong>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="gl-empty">
                        <i class="fas fa-ranking-star"></i>
                        <strong>Nessun dato disponibile</strong>
                        <span>La classifica apparirà dopo i primi click.</span>
                    </div>
                <?php endif; ?>
            </aside>
        </section>
    </main>

    <div id="achievement-popup" class="popup">
        <img id="popup-image" src="" alt="Achievement">
        <div><h3 id="popup-title"></h3><p id="popup-description"></p></div>
    </div>

    <button class="gl-top" type="button" data-gl-top aria-label="Torna su"><i class="fas fa-arrow-up"></i></button>
    <div class="gl-toast" id="goonlandToast" hidden><i class="fas fa-check"></i><span>Fatto</span></div>

    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="/js/modeChanger.js"></script>
</body>
</html>
