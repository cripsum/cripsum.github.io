<?php
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
checkBan($mysqli);


function gl_quiz_profiles(): array
{
    return [
        'sweet' => [
            'title' => 'Sweet Waifu',
            'desc' => 'Ti piace una ragazza dolce, calma e presente. Poco casino, tanta comfort zone.',
        ],
        'tsundere' => [
            'title' => 'Tsundere Bully',
            'desc' => 'Ti attira quella che fa la cattiva, ma poi si tradisce al primo blush.',
        ],
        'yandere' => [
            'title' => 'Obsessive Yandere',
            'desc' => 'Ti piacciono le red flag con gli occhi grandi. Scelta rischiosa.',
        ],
        'dommy' => [
            'title' => 'Goth Dommy Mommy',
            'desc' => 'Ti piace una ragazza sicura, dominante e con abbastanza aura da farti stare zitto.',
        ],
        'goth' => [
            'title' => 'Dark Gothic Queen',
            'desc' => 'Nero, mistero e sguardo freddo. La tua rovina, ma con estetica.',
        ],
        'gyaru' => [
            'title' => 'Gyaru Teaser',
            'desc' => 'Vuoi energia forte, teasing continuo e zero paura di esagerare.',
        ],
        'maid' => [
            'title' => 'Shy Maid',
            'desc' => 'Classica, cute e un po’ servizievole. Una scelta pulita, ma sempre efficace.',
        ],
        'fantasy' => [
            'title' => 'Demon Fantasy Girl',
            'desc' => 'Vuoi qualcosa di meno normale. Corna, magia, problemi e tanta presenza.',
        ],
        'elegant' => [
            'title' => 'Elegant Mommy',
            'desc' => 'Ti piace una ragazza composta, curata e superiore senza nemmeno provarci.',
        ],
        'chaotic' => [
            'title' => 'Chaotic Gremlin Girl',
            'desc' => 'La pace non ti interessa. Vuoi una che trasformi ogni giorno in un evento strano.',
        ],
        'cold' => [
            'title' => 'Cold Black-Haired Queen',
            'desc' => 'Fredda, distante, difficile da leggere. Ti ignora e funziona comunque.',
        ],
        'cute' => [
            'title' => 'Cute Soft Girl',
            'desc' => 'Ti piace la vibe tenera, leggera e piena di piccoli segnali affettuosi.',
        ],
        'tease' => [
            'title' => 'Flirty Teaser',
            'desc' => 'Ti piace essere provocato. Non troppo caos, ma abbastanza da perdere lucidità.',
        ],
        'dominant' => [
            'title' => 'Confident Boss Girl',
            'desc' => 'Vuoi una ragazza decisa, diretta e con più controllo di te.',
        ],
    ];
}

function gl_safe_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$glProfiles = gl_quiz_profiles();
$sharedResultKey = strtolower((string)($_GET['result'] ?? ''));
$sharedResultKey = preg_replace('/[^a-z_]/', '', $sharedResultKey) ?: '';
$sharedProfile = $glProfiles[$sharedResultKey] ?? null;
$sharedMatch = isset($_GET['match']) ? max(1, min(99, (int)$_GET['match'])) : 0;

$baseQuizUrl = 'https://cripsum.com/it/goonland/anime-girl-quiz';
$ogUrl = $baseQuizUrl;
$ogTitle = 'Cripsum™ GoonLand - Anime Girl Quiz';
$ogDescription = 'Rispondi a 10 domande e trova la tua ragazza anime ideale.';
$ogImage = 'https://cripsum.com/img/raspberry-chan16gb.png';
$pageTitle = 'GoonLand™ - Anime Girl Quiz';

if ($sharedProfile) {
    $query = ['result' => $sharedResultKey];
    if ($sharedMatch > 0) {
        $query['match'] = $sharedMatch;
    }

    $ogUrl = $baseQuizUrl . '?' . http_build_query($query);
    $ogTitle = 'Il mio tipo anime ideale su GoonLand è: ' . $sharedProfile['title'];
    $ogDescription = $sharedProfile['desc'] . ($sharedMatch > 0 ? ' Match ' . $sharedMatch . '%.' : '');
    $pageTitle = $sharedProfile['title'] . ' - GoonLand™ Anime Girl Quiz';
}

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$bot = preg_match('/(facebookexternalhit|Facebot|Discordbot|Twitterbot|TelegramBot|WhatsApp)/i', $ua);

if ($bot) {
    echo '<!DOCTYPE html><html lang="it"><head>' .
        '<meta charset="UTF-8">' .
        '<title>' . gl_safe_html($pageTitle) . '</title>' .
        '<meta property="og:title" content="' . gl_safe_html($ogTitle) . '">' .
        '<meta property="og:description" content="' . gl_safe_html($ogDescription) . '">' .
        '<meta property="og:image" content="' . gl_safe_html($ogImage) . '">' .
        '<meta property="og:url" content="' . gl_safe_html($ogUrl) . '">' .
        '<meta property="og:type" content="website">' .
        '<meta name="twitter:card" content="summary_large_image">' .
        '<meta name="twitter:title" content="' . gl_safe_html($ogTitle) . '">' .
        '<meta name="twitter:description" content="' . gl_safe_html($ogDescription) . '">' .
        '<meta name="twitter:image" content="' . gl_safe_html($ogImage) . '">' .
        '</head><body></body></html>';
    exit;
}

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

function gl_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function gl_contains_bad_tag(array $tags, array $blockedTags): bool
{
    $lookup = array_fill_keys($tags, true);

    foreach ($blockedTags as $tag) {
        if (isset($lookup[$tag])) {
            return true;
        }
    }

    return false;
}

function gl_fetch_danbooru_posts(string $tags, int $limit = 100): array
{
    $params = [
        'limit' => max(1, min(100, $limit)),
        'random' => 'true',
        'tags' => $tags,
    ];

    // Opzionale: se un giorno vuoi usare più tag senza limiti strani,
    // metti queste variabili d'ambiente sul server.
    $login = getenv('DANBOORU_LOGIN') ?: '';
    $apiKey = getenv('DANBOORU_API_KEY') ?: '';

    if ($login !== '' && $apiKey !== '') {
        $params['login'] = $login;
        $params['api_key'] = $apiKey;
    }

    $url = 'https://danbooru.donmai.us/posts.json?' . http_build_query($params);

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'header' => "User-Agent: CripsumGoonLand/1.0\r\nAccept: application/json\r\n",
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);

    if ($raw === false) {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function gl_build_quiz_tags(array $answers): array
{
    $ratingMap = [
        'safe' => 'rating:general',
        'suggestive' => 'rating:sensitive',
        'nsfw_soft' => 'rating:questionable',
        'explicit' => 'rating:explicit',
    ];

    $tagMap = [
        'sweet' => ['smile', 'gentle_smile', 'heart'],
        'cold' => ['expressionless', 'cold', 'black_hair'],
        'tsundere' => ['tsundere', 'annoyed', 'blush'],
        'dominant' => ['dominant', 'smirk', 'confident'],

        'dominated_yes' => ['dominant', 'looking_at_viewer', 'smirk'],
        'dominated_soft' => ['teasing', 'smile', 'blush'],
        'control_me' => ['confident', 'assertive', 'standing'],
        'depends' => ['smirk', 'teasing'],

        'shy' => ['shy', 'blush', 'looking_away'],
        'yandere' => ['yandere', 'crazy_eyes', 'smile'],
        'dommy' => ['dominant', 'tall_female', 'smirk'],

        'cute' => ['cute', 'smile', 'heart'],
        'dark' => ['gothic', 'black_clothes', 'dark'],
        'elegant' => ['elegant', 'dress', 'jewelry'],
        'chaotic' => ['crazy_eyes', 'messy_hair', 'open_mouth'],

        'wholesome' => ['smile', 'heart', 'hug'],
        'tease' => ['teasing', 'smirk', 'looking_at_viewer'],
        'command' => ['dominant', 'standing', 'looking_down'],
        'toxic' => ['yandere', 'crazy_eyes', 'smirk'],

        'maid' => ['maid', 'apron', 'frills'],
        'goth' => ['gothic', 'black_dress', 'black_clothes'],
        'gyaru' => ['gyaru', 'tan', 'blonde_hair'],
        'fantasy' => ['demon_girl', 'elf', 'horns'],

        'tall' => ['tall_female', 'standing'],
        'short_adult' => ['short_female', 'adult'],
        'curvy' => ['curvy', 'large_breasts'],
        'athletic' => ['athletic', 'toned'],

        'black_hair' => ['black_hair'],
        'blonde_hair' => ['blonde_hair'],
        'white_hair' => ['white_hair', 'silver_hair'],
        'colored_hair' => ['pink_hair', 'blue_hair', 'multicolored_hair'],

        'uniform' => ['uniform', 'office_lady', 'suit'],
        'dress' => ['dress', 'elegant'],
        'dark_outfit' => ['black_clothes', 'gothic'],
        'provocative' => ['revealing_clothes', 'cleavage'],
    ];

    $rating = $ratingMap[$answers['intensity'] ?? 'safe'] ?? 'rating:general';
    $pickedTags = [];

    foreach ($answers as $value) {
        if (!isset($tagMap[$value])) {
            continue;
        }

        foreach ($tagMap[$value] as $tag) {
            $pickedTags[$tag] = true;
        }
    }

    $pickedTags = array_keys($pickedTags);
    shuffle($pickedTags);

    $anchorTags = [];
    foreach ($pickedTags as $tag) {
        if (!in_array($tag, ['smile', 'heart', 'standing', 'looking_at_viewer'], true)) {
            $anchorTags[] = $tag;
        }
    }

    if (empty($anchorTags)) {
        $anchorTags[] = '1girl';
    }

    return [
        'rating' => $rating,
        'pickedTags' => $pickedTags,
        'anchorTags' => $anchorTags,
    ];
}

if (isset($_GET['quiz_api']) && $_GET['quiz_api'] === 'danbooru_result') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        gl_json_response(['ok' => false, 'message' => 'Metodo non valido'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $answers = is_array($input['answers'] ?? null) ? $input['answers'] : [];

    $requiredKeys = ['type', 'control', 'personality', 'vibe', 'relationship', 'style', 'body', 'hair', 'outfit', 'intensity'];
    foreach ($requiredKeys as $key) {
        if (!isset($answers[$key]) || !is_string($answers[$key])) {
            gl_json_response(['ok' => false, 'message' => 'Quiz incompleto'], 422);
        }
    }

    $blockedTags = [
        'loli', 'shota', 'child', 'children', 'toddler', 'baby', 'young', 'younger', 'underage', 'preteen',
        'kindergarten', 'kindergarten_uniform', 'elementary_schooler', 'elementary_school_uniform',
        'middle_schooler', 'middle_school_uniform', 'aged_down', 'age_regression',
        'guro', 'gore', 'vore', 'bestiality', 'zoophilia'
    ];

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $built = gl_build_quiz_tags($answers);

    // Query progressive. La prima è più precisa, le altre servono se Danbooru limita troppi tag senza API key.
    $queryAttempts = [];
    $queryAttempts[] = trim($built['rating'] . ' ' . implode(' ', array_slice($built['anchorTags'], 0, 2)));
    $queryAttempts[] = trim($built['rating'] . ' ' . ($built['anchorTags'][0] ?? '1girl'));
    $queryAttempts[] = trim($built['rating'] . ' 1girl');

    $bestPosts = [];
    $usedQuery = '';

    foreach ($queryAttempts as $query) {
        $posts = gl_fetch_danbooru_posts($query, 100);

        if (empty($posts)) {
            continue;
        }

        $usedQuery = $query;

        foreach ($posts as $post) {
            if (!is_array($post)) {
                continue;
            }

            $fileUrl = $post['file_url'] ?? $post['large_file_url'] ?? '';
            $previewUrl = $post['preview_file_url'] ?? '';
            $extension = strtolower((string)($post['file_ext'] ?? pathinfo(parse_url($fileUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)));

            if ($fileUrl === '' || !in_array($extension, $allowedExtensions, true)) {
                continue;
            }

            $allTagsString = trim(implode(' ', [
                $post['tag_string_general'] ?? '',
                $post['tag_string_character'] ?? '',
                $post['tag_string_copyright'] ?? '',
                $post['tag_string_artist'] ?? '',
                $post['tag_string_meta'] ?? '',
            ]));

            $postTags = array_values(array_filter(preg_split('/\s+/', $allTagsString)));

            if (gl_contains_bad_tag($postTags, $blockedTags)) {
                continue;
            }

            $score = 0;
            $matched = [];
            $lookup = array_fill_keys($postTags, true);

            foreach ($built['pickedTags'] as $tag) {
                if (isset($lookup[$tag])) {
                    $score += 10;
                    $matched[] = $tag;
                }
            }

            // Piccolo bonus per immagini complete e pulite.
            if (!empty($post['image_width']) && !empty($post['image_height'])) {
                $score += 1;
            }

            $bestPosts[] = [
                'score' => $score,
                'post' => $post,
                'matched' => $matched,
                'file_url' => $fileUrl,
                'preview_url' => $previewUrl,
                'tags' => $postTags,
            ];
        }

        if (!empty($bestPosts)) {
            break;
        }
    }

    if (empty($bestPosts)) {
        gl_json_response([
            'ok' => false,
            'message' => 'Nessun risultato trovato. Riprova o scegli risposte meno specifiche.'
        ], 404);
    }

    usort($bestPosts, static function ($a, $b) {
        return ($b['score'] <=> $a['score']) ?: random_int(-1, 1);
    });

    $topPool = array_slice($bestPosts, 0, min(12, count($bestPosts)));
    $selected = $topPool[array_rand($topPool)];
    $post = $selected['post'];
    $postId = (int)($post['id'] ?? 0);

    $artistTags = array_values(array_filter(preg_split('/\s+/', (string)($post['tag_string_artist'] ?? ''))));
    $characterTags = array_values(array_filter(preg_split('/\s+/', (string)($post['tag_string_character'] ?? ''))));
    $copyrightTags = array_values(array_filter(preg_split('/\s+/', (string)($post['tag_string_copyright'] ?? ''))));

    gl_json_response([
        'ok' => true,
        'image' => $selected['file_url'],
        'preview' => $selected['preview_url'],
        'postUrl' => $postId > 0 ? 'https://danbooru.donmai.us/posts/' . $postId : '',
        'source' => (string)($post['source'] ?? ''),
        'rating' => (string)($post['rating'] ?? ''),
        'artistTags' => array_slice($artistTags, 0, 5),
        'characterTags' => array_slice($characterTags, 0, 5),
        'copyrightTags' => array_slice($copyrightTags, 0, 5),
        'matchedTags' => array_slice($selected['matched'], 0, 8),
        'usedQuery' => $usedQuery,
    ]);
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta property="og:title" content="<?php echo gl_safe_html($ogTitle); ?>">
    <meta property="og:description" content="<?php echo gl_safe_html($ogDescription); ?>">
    <meta property="og:image" content="<?php echo gl_safe_html($ogImage); ?>">
    <meta property="og:url" content="<?php echo gl_safe_html($ogUrl); ?>">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo gl_safe_html($ogTitle); ?>">
    <meta name="twitter:description" content="<?php echo gl_safe_html($ogDescription); ?>">
    <meta name="twitter:image" content="<?php echo gl_safe_html($ogImage); ?>">
    <?php include '../../includes/head-import.php'; ?>
    <title><?php echo gl_safe_html($pageTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/css/goonland.css?v=2.4-share-og">
    <script src="/js/goonland.js?v=2.4-share-og" defer></script>
</head>

<body class="goonland-page" data-goonland-page="anime-quiz">
    <?php include '../../includes/navbar-goonland.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>

    <div class="gl-bg" aria-hidden="true"><span></span><span></span></div>

    <main class="gl-shell gl-quiz-shell">
        <section class="gl-hero gl-hero-small gl-reveal">
            <div class="gl-hero-text">
                <span class="gl-kicker"><i class="fas fa-heart-pulse"></i> Anime Girl Quiz</span>
                <h1>Trova la tua ragazza anime ideale</h1>
                <p>10 domande veloci. Rispondi, ottieni un tipo ideale e una reference presa da Danbooru.</p>
                <div class="gl-actions">
                    <a class="gl-btn gl-btn-main" href="#glAnimeQuiz"><i class="fas fa-play"></i> Inizia quiz</a>
                    <a class="gl-btn gl-btn-ghost" href="/it/goonland/home"><i class="fas fa-arrow-left"></i> Home GoonLand</a>
                </div>
            </div>
        </section>

        <?php if ($sharedProfile): ?>
            <section class="gl-shared-result gl-reveal">
                <span class="gl-kicker"><i class="fas fa-share-nodes"></i> Risultato condiviso</span>
                <h2><?php echo gl_safe_html($ogTitle); ?></h2>
                <p><?php echo gl_safe_html($ogDescription); ?></p>
                <div class="gl-actions">
                    <a class="gl-btn gl-btn-main" href="#glAnimeQuiz"><i class="fas fa-play"></i> Fai anche tu il quiz</a>
                    <button class="gl-btn gl-btn-ghost" type="button" data-copy-current-url><i class="fas fa-link"></i> Copia link</button>
                </div>
            </section>
        <?php endif; ?>

        <section class="gl-quiz-layout gl-reveal" id="glAnimeQuiz">
            <div class="gl-quiz-card" id="glQuizBox">
                <div class="gl-quiz-top">
                    <span class="gl-kicker" id="glQuizStep">Domanda 1 / 10</span>
                    <span class="gl-status" id="glQuizStatus">Pronto</span>
                </div>

                <div class="gl-quiz-progress" aria-hidden="true">
                    <span id="glQuizProgress"></span>
                </div>

                <div class="gl-question-wrap">
                    <h2 id="glQuizQuestion">Caricamento...</h2>
                    <p id="glQuizHint"></p>
                    <div class="gl-answer-grid" id="glAnswerGrid"></div>
                </div>

                <div class="gl-quiz-nav">
                    <button class="gl-btn gl-btn-ghost" type="button" id="glQuizBack"><i class="fas fa-arrow-left"></i> Indietro</button>
                    <button class="gl-btn gl-btn-main" type="button" id="glQuizNext"><i class="fas fa-arrow-right"></i> Avanti</button>
                </div>
            </div>

            <aside class="gl-quiz-side">
                <div class="gl-lore-card gl-lore-card--accent">
                    <span class="gl-kicker">Come funziona</span>
                    <h2>Risposte → tag → risultato.</h2>
                    <p>Ogni risposta aggiunge punti a un archetipo e crea una ricerca su Danbooru.</p>
                    <p>La modalità NSFW resta adult-only. I tag problematici sui minorenni restano bloccati.</p>
                </div>
            </aside>
        </section>

        <section class="gl-result gl-reveal" id="glQuizResult" hidden>
            <div class="gl-result-head">
                <div>
                    <span class="gl-kicker"><i class="fas fa-star"></i> Risultato</span>
                    <h2 id="glResultTitle">Il tuo tipo ideale</h2>
                    <p id="glResultDescription"></p>
                </div>
                <span class="gl-status" id="glResultMatch">Match 0%</span>
            </div>

            <div class="gl-result-grid">
                <div class="gl-result-media" id="glResultMedia">
                    <div class="gl-placeholder" id="glResultPlaceholder">
                        <i class="fas fa-circle-notch fa-spin"></i>
                        <strong>Sto cercando la reference</strong>
                        <span>Ci mette un attimo.</span>
                    </div>
                    <img id="glResultImage" class="gl-result-image" alt="Risultato anime girl" hidden>
                </div>

                <div class="gl-result-panel">
                    <h3>Tag match</h3>
                    <div class="gl-chip-list" id="glResultTags"></div>

                    <div class="gl-result-meta" id="glResultMeta"></div>

                    <div class="gl-share-box" id="glShareBox" hidden>
                        <span>Link condivisibile</span>
                        <button type="button" id="glShareUrlButton" title="Copia link condivisibile">
                            <strong id="glShareText">Il mio tipo anime ideale su GoonLand è:</strong>
                            <small id="glShareUrlText"></small>
                        </button>
                    </div>

                    <div class="gl-result-actions">
                        <button class="gl-btn gl-btn-main" type="button" id="glQuizReroll"><i class="fas fa-shuffle"></i> Reroll immagine</button>
                        <button class="gl-btn gl-btn-ghost" type="button" id="glQuizRestart"><i class="fas fa-rotate-left"></i> Rifai quiz</button>
                        <button class="gl-icon-btn" type="button" id="glQuizShare" aria-label="Copia risultato"><i class="fas fa-share-nodes"></i></button>
                    </div>
                </div>
            </div>
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
