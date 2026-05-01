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

$userAllowsNsfw = isset($_SESSION['nsfw']) && (int)$_SESSION['nsfw'] === 1;

function sopJson(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function sopHttpGet(string $url, int $timeout = 15): array
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
                'User-Agent: cripsum-goonland-smashpass/1.0'
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
            'header' => "Accept: application/json\r\nUser-Agent: cripsum-goonland-smashpass/1.0\r\n",
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

function sopRandomRating(bool $nsfw): string
{
    if (!$nsfw) {
        return 'safe';
    }

    return random_int(0, 1) === 0 ? 'questionable' : 'explicit';
}

function sopBuildDanbooruUrls(array $config): array
{
    $rating = sopRandomRating((bool)$config['nsfw']);

    $baseTags = array_merge($config['tags'], [
        'rating:' . $rating,
    ]);

    $strictBlocklist = [
        '-loli',
        '-shota',
        '-child',
        '-young',
        '-toddler',
        '-cub',
        '-feral',
        '-bestiality',
        '-gore',
        '-vore',
    ];

    $qualityFilters = [
        '-animated',
        '-video',
        '-sound',
        '-comic',
        '-multiple_views',
    ];

    $attempts = [
        array_merge($baseTags, $strictBlocklist, $qualityFilters),
        array_merge($baseTags, $strictBlocklist),
        $baseTags,
    ];

    $urls = [];

    foreach ($attempts as $tags) {
        $params = [
            'limit' => 30,
            'random' => 'true',
            'tags' => implode(' ', $tags),
        ];

        $urls[] = 'https://danbooru.donmai.us/posts.json?' . http_build_query($params);
    }

    return $urls;
}

function sopPickPost(array $posts): ?array
{
    $valid = [];

    foreach ($posts as $post) {
        if (!is_array($post)) continue;

        $fileUrl = $post['file_url'] ?? '';
        $fileExt = strtolower((string)($post['file_ext'] ?? ''));
        $allTags = ' ' . strtolower(
            (string)($post['tag_string'] ?? '') . ' ' .
            (string)($post['tag_string_general'] ?? '') . ' ' .
            (string)($post['tag_string_character'] ?? '')
        ) . ' ';

        if (!is_string($fileUrl) || $fileUrl === '') continue;
        if (!in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) continue;

        $blockedNeedles = [
            ' child ',
            ' toddler ',
            ' cub ',
            ' feral ',
            ' bestiality ',
            ' guro ',
            ' gore ',
            ' vore ',
        ];

        $blocked = false;
        foreach ($blockedNeedles as $needle) {
            if (str_contains($allTags, $needle)) {
                $blocked = true;
                break;
            }
        }

        if ($blocked) continue;

        $valid[] = $post;
    }

    if (!$valid) {
        return null;
    }

    return $valid[array_rand($valid)];
}

function sopFetchDanbooru(array $config, ?array &$debug = null): ?array
{
    $debug = [
        'attempts' => [],
    ];

    foreach (sopBuildDanbooruUrls($config) as $url) {
        $response = sopHttpGet($url, 12);

        $debug['attempts'][] = [
            'url' => $url,
            'status' => $response['status'],
            'ok' => $response['ok'],
            'error' => $response['error'],
            'body_start' => mb_substr($response['body'], 0, 180),
        ];

        if (!$response['ok']) {
            error_log('[GoonLand SmashPass] Danbooru failed: URL ' . $url);
            error_log('[GoonLand SmashPass] Danbooru failed: HTTP ' . $response['status'] . ' ' . $response['error']);
            continue;
        }

        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            error_log('[GoonLand SmashPass] Danbooru JSON invalid: ' . mb_substr($response['body'], 0, 300));
            continue;
        }

        $post = sopPickPost($data);
        if (!$post) {
            continue;
        }

        $characterTags = trim((string)($post['tag_string_character'] ?? ''));
        $copyrightTags = trim((string)($post['tag_string_copyright'] ?? ''));
        $artistTags = trim((string)($post['tag_string_artist'] ?? ''));
        $generalTags = trim((string)($post['tag_string_general'] ?? ''));

        return [
            'image' => (string)$post['file_url'],
            'preview' => (string)($post['preview_file_url'] ?? ''),
            'postUrl' => 'https://danbooru.donmai.us/posts/' . (int)$post['id'],
            'source' => (string)($post['source'] ?? ''),
            'rating' => (string)($post['rating'] ?? 'safe'),
            'characterTags' => $characterTags !== '' ? preg_split('/\s+/', $characterTags) : [],
            'copyrightTags' => $copyrightTags !== '' ? preg_split('/\s+/', $copyrightTags) : [],
            'artistTags' => $artistTags !== '' ? preg_split('/\s+/', $artistTags) : [],
            'generalTags' => $generalTags !== '' ? array_slice(preg_split('/\s+/', $generalTags), 0, 12) : [],
        ];
    }

    return null;
}

if (isset($_GET['sop_api']) && $_GET['sop_api'] === '1') {
    $mode = trim((string)($_GET['mode'] ?? 'waifu_sfw'));

    $modes = [
        'waifu_sfw' => [
            'label' => 'Waifu SFW',
            'nsfw' => false,
            'tags' => ['1girl'],
        ],
        'waifu_nsfw' => [
            'label' => 'Waifu NSFW',
            'nsfw' => true,
            'tags' => ['1girl'],
        ],
        'husbando_sfw' => [
            'label' => 'Husbando SFW',
            'nsfw' => false,
            'tags' => ['1boy'],
        ],
        'husbando_nsfw' => [
            'label' => 'Husbando NSFW',
            'nsfw' => true,
            'tags' => ['1boy'],
        ],
    ];

    if (!isset($modes[$mode])) {
        sopJson(['ok' => false, 'error' => 'Modalità non valida'], 400);
    }

    if ($modes[$mode]['nsfw'] && !$userAllowsNsfw) {
        sopJson(['ok' => false, 'error' => 'Abilita i contenuti NSFW nel profilo per usare questa modalità'], 403);
    }

    $debug = [];
    $result = sopFetchDanbooru($modes[$mode], $debug);

    if (!$result) {
        sopJson([
            'ok' => false,
            'error' => 'Impossibile recuperare un personaggio adesso',
            'debug' => $debug,
        ], 200);
    }

    sopJson([
        'ok' => true,
        'mode' => $mode,
        'modeLabel' => $modes[$mode]['label'],
        'data' => $result,
    ]);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../../includes/head-import.php'; ?>
    <title>GoonLand™ - Smash or Pass</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/css/goonland.css?v=2.7-smash-polish">
    <link rel="stylesheet" href="/css/goonland-smash-pass.css?v=2.7-smash-polish">
    <script src="/js/goonland.js?v=2.7-smash-polish" defer></script>
    <script src="/js/goonland-smash-pass.js?v=2.7-smash-polish" defer></script>
</head>
<body class="goonland-page" data-goonland-page="smash-pass">
    <?php include '../../includes/navbar-goonland.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>

    <div class="gl-bg" aria-hidden="true"><span></span><span></span></div>

    <main class="gl-shell gl-sp-shell">
        <section class="gl-hero gl-hero-small gl-reveal">
            <div class="gl-hero-text">
                <span class="gl-kicker"><i class="fas fa-fire"></i> Smash or Pass</span>
                <h1>Smash or Pass</h1>
                <p>Ti esce un personaggio random. Tu decidi. Smash o pass. Puoi scegliere tra waifu e husbando, sia safe che 18+.</p>
                <div class="gl-actions">
                    <a class="gl-btn gl-btn-ghost" href="/it/goonland/home"><i class="fas fa-arrow-left"></i> Home GoonLand</a>
                </div>
            </div>
        </section>

        <section class="gl-sp-layout gl-reveal">
            <div class="gl-sp-main gl-generator">
                <div class="gl-generator-head">
                    <div>
                        <span class="gl-kicker">Modalità</span>
                        <h2>Gioca</h2>
                    </div>
                    <span class="gl-status" id="smashPassStatus">Pronto</span>
                </div>

                <div class="gl-sp-controls">
                    <div class="gl-select-field gl-custom-select gl-sp-mode-select" data-gl-custom-select>
                        <span>Tipo</span>

                        <select id="smashPassMode" class="gl-native-select" aria-label="Modalità Smash or Pass">
                            <option value="waifu_sfw">Waifu - SFW</option>
                            <option value="waifu_nsfw" <?php echo $userAllowsNsfw ? '' : 'disabled'; ?>>Waifu - NSFW</option>
                            <option value="husbando_sfw">Husbando - SFW</option>
                            <option value="husbando_nsfw" <?php echo $userAllowsNsfw ? '' : 'disabled'; ?>>Husbando - NSFW</option>
                        </select>

                        <button class="gl-select-trigger" type="button" aria-haspopup="listbox" aria-expanded="false">
                            <span class="gl-select-current">Waifu - SFW</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>

                        <div class="gl-select-menu" role="listbox" aria-label="Scegli modalità">
                            <button type="button" role="option" data-value="waifu_sfw" class="is-active">
                                <strong>Waifu</strong>
                                <span>SFW</span>
                            </button>
                            <button type="button" role="option" data-value="waifu_nsfw" <?php echo $userAllowsNsfw ? '' : 'disabled aria-disabled="true"'; ?>>
                                <strong>Waifu</strong>
                                <span>18+</span>
                            </button>
                            <button type="button" role="option" data-value="husbando_sfw">
                                <strong>Husbando</strong>
                                <span>SFW</span>
                            </button>
                            <button type="button" role="option" data-value="husbando_nsfw" <?php echo $userAllowsNsfw ? '' : 'disabled aria-disabled="true"'; ?>>
                                <strong>Husbando</strong>
                                <span>18+</span>
                            </button>
                        </div>
                    </div>

                    <div class="gl-sp-mini-actions">
                        <button class="gl-btn gl-btn-ghost" type="button" id="spNextBtn"><i class="fas fa-rotate"></i> Prossimo</button>
                    </div>
                </div>

                <div class="gl-sp-card" id="smashPassCard">
                    <div class="gl-sp-image-wrap" id="smashPassSwipeArea">
                        <div class="gl-sp-decision gl-sp-decision-pass" aria-hidden="true"><i class="fas fa-xmark"></i> PASS</div>
                        <div class="gl-sp-decision gl-sp-decision-smash" aria-hidden="true"><i class="fas fa-fire"></i> SMASH</div>
                        <div class="gl-sp-swipe-hint" aria-hidden="true"><i class="fas fa-hand-pointer"></i> Swipe destra/sinistra</div>
                        <div class="gl-placeholder gl-sp-placeholder" id="smashPassPlaceholder">
                            <i class="fas fa-heart-crack"></i>
                            <strong>Nessun personaggio ancora</strong>
                            <span>Clicca smash, pass o prossimo per iniziare.</span>
                        </div>
                        <div class="gl-spinner" id="smashPassSpinner" aria-hidden="true"></div>
                        <img id="smashPassImage" class="generated-image gl-sp-image" alt="Personaggio Smash or Pass" hidden>
                    </div>

                    <div class="gl-sp-content">
                        <div class="gl-sp-headline">
                            <div>
                                <span class="gl-kicker" id="smashPassModeLabel">Waifu SFW</span>
                                <h3 id="smashPassTitle">In attesa del primo roll</h3>
                            </div>
                            <span class="gl-sp-rating" id="smashPassRating">-</span>
                        </div>

                        <p class="gl-sp-subtitle" id="smashPassSubtitle">Quando arriva la prima immagine, qui vedrai personaggio, serie e artista.</p>

                        <div class="gl-sp-tags" id="smashPassTags"></div>

                        <div class="gl-sp-links" id="smashPassLinks"></div>
                    </div>
                </div>

                <div class="gl-sp-choices">
                    <button class="gl-btn gl-btn-pass" type="button" id="spPassBtn"><i class="fas fa-xmark"></i> Pass</button>
                    <button class="gl-btn gl-btn-smash" type="button" id="spSmashBtn"><i class="fas fa-fire"></i> Smash</button>
                </div>
            </div>

            <aside class="gl-sp-side gl-leaderboard">
                <div class="gl-leaderboard-head">
                    <div>
                        <span class="gl-kicker">Statistiche</span>
                        <h2>I tuoi numeri</h2>
                    </div>
                </div>

                <div class="gl-sp-stats">
                    <div class="gl-sp-stat">
                        <span>Totale</span>
                        <strong id="spTotalCount">0</strong>
                    </div>
                    <div class="gl-sp-stat is-smash">
                        <span>Smash</span>
                        <strong id="spSmashCount">0</strong>
                    </div>
                    <div class="gl-sp-stat is-pass">
                        <span>Pass</span>
                        <strong id="spPassCount">0</strong>
                    </div>
                    <div class="gl-sp-stat">
                        <span>Smash rate</span>
                        <strong id="spRateCount">0%</strong>
                    </div>
                </div>

                <div class="gl-copy gl-sp-help">
                    <h2>Come funziona</h2>
                    <p>Ogni volta ti esce un personaggio random. Puoi cliccare, usare le frecce o swipare direttamente sulla foto.</p>
                    <p>Le statistiche vengono salvate nel browser. Se cambi modalità, i contatori restano separati.</p>
                    <?php if (!$userAllowsNsfw): ?>
                        <p><strong>Nota:</strong> hai i contenuti NSFW disattivati nel profilo, quindi qui vedi solo le modalità safe.</p>
                    <?php endif; ?>
                </div>
            </aside>
        </section>
    </main>

    <button class="gl-top" type="button" data-gl-top aria-label="Torna su"><i class="fas fa-arrow-up"></i></button>
    <div class="gl-toast" id="goonlandToast" hidden><i class="fas fa-check"></i><span>Fatto</span></div>

    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="/js/modeChanger.js"></script>
</body>
</html>
