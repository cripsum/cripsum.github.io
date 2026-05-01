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

function goonJsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function goonHttpGet(string $url, int $timeout = 10): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: cripsum-goonland/1.0'
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'ok' => $body !== false && $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $body === false ? '' : (string)$body,
            'error' => $error,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'header' => "Accept: application/json\r\nUser-Agent: cripsum-goonland/1.0\r\n",
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $status = 0;

    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                $status = (int)$matches[1];
                break;
            }
        }
    }

    return [
        'ok' => $body !== false && $status >= 200 && $status < 300,
        'status' => $status,
        'body' => $body === false ? '' : (string)$body,
        'error' => $body === false ? 'file_get_contents failed' : '',
    ];
}

function goonBuildWaifuImUrl(array $tags, bool $isNsfw): string
{
    $parts = [];

    foreach ($tags as $tag) {
        $tag = trim((string)$tag);
        if ($tag === '') {
            continue;
        }

        $parts[] = 'IncludedTags=' . rawurlencode($tag);
    }

    // Waifu.im usa questi nomi parametro: IncludedTags, IsNsfw e PageSize.
    // IsNsfw accetta False, True oppure All.
    $parts[] = 'IsNsfw=' . ($isNsfw ? 'True' : 'False');
    $parts[] = 'PageSize=1';

    return 'https://api.waifu.im/images?' . implode('&', $parts);
}

function goonFetchWaifuPics(string $path): ?string
{
    $url = 'https://api.waifu.pics/' . ltrim($path, '/');
    $response = goonHttpGet($url);

    if (!$response['ok']) {
        error_log('[GoonLand] waifu.pics failed: HTTP ' . $response['status'] . ' ' . $response['error']);
        return null;
    }

    $data = json_decode($response['body'], true);
    $imageUrl = $data['url'] ?? '';

    return is_string($imageUrl) && $imageUrl !== '' ? $imageUrl : null;
}

function goonFetchWaifuIm(array $tags, bool $isNsfw): ?string
{
    $url = goonBuildWaifuImUrl($tags, $isNsfw);
    $response = goonHttpGet($url);

    if (!$response['ok']) {
        error_log('[GoonLand] waifu.im failed: HTTP ' . $response['status'] . ' ' . $response['error']);
        return null;
    }

    $data = json_decode($response['body'], true);

    // La nuova API di Waifu.im ritorna le immagini in "items".
    // Tengo anche "images" come fallback, nel caso cambi ancora o arrivi da wrapper vecchi.
    $image = $data['items'][0] ?? $data['images'][0] ?? null;

    if (!is_array($image)) {
        error_log('[GoonLand] waifu.im response senza items/images: ' . substr($response['body'], 0, 500));
        return null;
    }

    $imageUrl = $image['url'] ?? $image['preview_url'] ?? '';

    return is_string($imageUrl) && $imageUrl !== '' ? $imageUrl : null;
}

if (isset($_GET['generate_image']) && $_GET['generate_image'] === '1') {
    $contentType = trim((string)($_GET['contentType'] ?? 'sfw/waifu'));

    $types = [
        'sfw/waifu' => [
            'waifuPics' => 'sfw/waifu',
            'waifuImTags' => ['waifu'],
            'isNsfw' => false,
        ],
        'nsfw/waifu' => [
            'waifuPics' => 'nsfw/waifu',
            'waifuImTags' => ['waifu'],
            'isNsfw' => true,
        ],
        'nsfw/neko' => [
            'waifuPics' => 'nsfw/neko',
            'waifuImTags' => ['ero'],
            'isNsfw' => true,
        ],
        'nsfw/trap' => [
            'waifuPics' => 'nsfw/trap',
            'waifuImTags' => ['ero'],
            'isNsfw' => true,
        ],
        'nsfw/blowjob' => [
            'waifuPics' => 'nsfw/blowjob',
            'waifuImTags' => ['oral'],
            'isNsfw' => true,
        ],
    ];

    if (!isset($types[$contentType])) {
        goonJsonResponse(['ok' => false, 'error' => 'Tipo contenuto non valido'], 400);
    }

    $config = $types[$contentType];

    $imageUrl = goonFetchWaifuPics($config['waifuPics']);
    $source = 'waifu.pics';

    if (!$imageUrl) {
        $imageUrl = goonFetchWaifuIm($config['waifuImTags'], (bool)$config['isNsfw']);
        $source = 'waifu.im';
    }

    if (!$imageUrl) {
        goonJsonResponse(['ok' => false, 'error' => 'Nessuna API immagini ha risposto'], 502);
    }

    goonJsonResponse([
        'ok' => true,
        'url' => $imageUrl,
        'source' => $source,
    ]);
}

if (isset($_GET['download_image']) && $_GET['download_image'] === '1' && isset($_GET['url'])) {
    $url = trim((string)$_GET['url']);
    $parsed = parse_url($url);

    $scheme = strtolower((string)($parsed['scheme'] ?? ''));
    $host = strtolower((string)($parsed['host'] ?? ''));

    $isAllowedHost = (
        preg_match('/(^|\.)waifu\.pics$/i', $host) ||
        preg_match('/(^|\.)waifu\.im$/i', $host)
    );

    if ($scheme !== 'https' || !$isAllowedHost) {
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
    <link rel="stylesheet" href="/css/goonland.css?v=2.2-api-proxy-fix2">
    <script src="/js/goonland.js?v=2.2-api-proxy-fix2" defer></script>
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
                    <div class="gl-select-field gl-custom-select" data-gl-custom-select>
                        <span>Tipo contenuto</span>

                        <select id="contentType" class="gl-native-select" aria-label="Tipo contenuto">
                            <option value="sfw/waifu">Waifu - SFW</option>
                            <option value="nsfw/waifu">Waifu - 18+</option>
                            <option value="nsfw/neko">Neko - 18+</option>
                            <option value="nsfw/trap">Trap - 18+</option>
                            <option value="nsfw/blowjob">BJ - 18+</option>
                        </select>

                        <button class="gl-select-trigger" type="button" aria-haspopup="listbox" aria-expanded="false">
                            <span class="gl-select-current">Waifu - SFW</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>

                        <div class="gl-select-menu" role="listbox" aria-label="Scegli tipo contenuto">
                            <button type="button" role="option" data-value="sfw/waifu" class="is-active">
                                <strong>Waifu</strong>
                                <span>SFW</span>
                            </button>
                            <button type="button" role="option" data-value="nsfw/waifu">
                                <strong>Waifu</strong>
                                <span>18+</span>
                            </button>
                            <button type="button" role="option" data-value="nsfw/neko">
                                <strong>Neko</strong>
                                <span>18+</span>
                            </button>
                            <button type="button" role="option" data-value="nsfw/trap">
                                <strong>Trap</strong>
                                <span>18+</span>
                            </button>
                            <button type="button" role="option" data-value="nsfw/blowjob">
                                <strong>BJ</strong>
                                <span>18+</span>
                            </button>
                        </div>
                    </div>

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
                    <p>Chi ha goonato di più.</p>
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
        <div>
            <h3 id="popup-title"></h3>
            <p id="popup-description"></p>
        </div>
    </div>

    <button class="gl-top" type="button" data-gl-top aria-label="Torna su"><i class="fas fa-arrow-up"></i></button>
    <div class="gl-toast" id="goonlandToast" hidden><i class="fas fa-check"></i><span>Fatto</span></div>

    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="/js/modeChanger.js"></script>
</body>

</html>
