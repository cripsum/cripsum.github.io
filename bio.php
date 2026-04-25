<?php
require_once __DIR__ . '/config/session_init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$isLoggedIn = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$username_session = $_SESSION['username'] ?? null;

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatShortDate($date): string
{
    if (empty($date)) {
        return '—';
    }

    $timestamp = strtotime((string) $date);
    return $timestamp ? date('d/m/Y', $timestamp) : '—';
}

function formatCompactNumber($number): string
{
    $number = (int) $number;

    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    }

    if ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    }

    return (string) $number;
}

function fetchSingleValue(mysqli $mysqli, string $query, string $types, $param, $fallback = 0)
{
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        return $fallback;
    }

    $stmt->bind_param($types, $param);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return $fallback;
    }

    $firstValue = reset($row);
    return $firstValue ?? $fallback;
}

function fetchRecentAchievements(mysqli $mysqli, int $userId): array
{
    $query = "SELECT a.id, a.nome, a.descrizione, a.img_url, a.punti, ua.data
              FROM utenti_achievement ua
              INNER JOIN achievement a ON a.id = ua.achievement_id
              WHERE ua.utente_id = ?
              ORDER BY ua.data DESC
              LIMIT 6";

    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $achievements = [];

    while ($result && ($row = $result->fetch_assoc())) {
        $achievements[] = $row;
    }

    $stmt->close();
    return $achievements;
}

$identifier = trim((string) ($_GET['username'] ?? $_GET['u'] ?? $_GET['id'] ?? 'cripsum'));
$isNumericIdentifier = ctype_digit($identifier);
$lookupColumn = $isNumericIdentifier ? 'id' : 'username';
$lookupType = $isNumericIdentifier ? 'i' : 's';
$lookupValue = $isNumericIdentifier ? (int) $identifier : $identifier;

$userQuery = "SELECT id, username, data_creazione, soldi, ruolo FROM utenti WHERE {$lookupColumn} = ? LIMIT 1";
$stmt = $mysqli->prepare($userQuery);

if (!$stmt) {
    http_response_code(500);
    exit('Errore nel caricamento del profilo.');
}

$stmt->bind_param($lookupType, $lookupValue);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    http_response_code(404);
    exit('Utente non trovato.');
}

$user = $result->fetch_assoc();
$stmt->close();

$user_cercato_id = (int) $user['id'];

$achievementCount = (int) fetchSingleValue(
    $mysqli,
    'SELECT COUNT(DISTINCT achievement_id) AS total FROM utenti_achievement WHERE utente_id = ?',
    'i',
    $user_cercato_id
);

$inventoryStmt = $mysqli->prepare('SELECT COUNT(DISTINCT personaggio_id) AS unique_characters, COALESCE(SUM(`quantità`), 0) AS total_pulls FROM utenti_personaggi WHERE utente_id = ?');
$uniqueCharacters = 0;
$totalPulls = 0;

if ($inventoryStmt) {
    $inventoryStmt->bind_param('i', $user_cercato_id);
    $inventoryStmt->execute();
    $inventoryResult = $inventoryStmt->get_result();
    $inventoryStats = $inventoryResult ? $inventoryResult->fetch_assoc() : null;
    $uniqueCharacters = (int) ($inventoryStats['unique_characters'] ?? 0);
    $totalPulls = (int) ($inventoryStats['total_pulls'] ?? 0);
    $inventoryStmt->close();
}

$recentAchievements = fetchRecentAchievements($mysqli, $user_cercato_id);

$host = $_SERVER['HTTP_HOST'] ?? 'cripsum.com';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/bio';
$profileUrl = 'https://' . $host . strtok($requestUri, '?');

$profileConfig = [
    'display_name' => 'cripsum',
    'username' => $user['username'] ?? 'cripsum',
    'aliases' => 'AKA Leo / Cripsy Cripsy',
    'tagline' => 'Video editor, developer e founder di piccoli esperimenti web.',
    'bio' => 'Creo edit, pagine strane, giochi e strumenti per il mio sito. Questa bio è pensata come hub personale, non come una lista piatta di link.',
    'location' => 'Italy',
    'age' => '20',
    'role' => $user['ruolo'] ?? 'user',
    'discord_id' => '963536045180350474',
    'avatar_url' => 'includes/get_pfp.php?id=' . $user_cercato_id,
    'background_video' => 'vid/Shorekeeper Wallpaper 4K Loop.mp4',
    'audio_title' => "To the Shore's end",
    'audio_file' => 'audio/godo.mp3',
];

$socialLinks = [
    [
        'label' => 'TikTok',
        'handle' => '@cripsum',
        'url' => 'https://tiktok.cripsum.com',
        'icon' => 'fab fa-tiktok',
        'kind' => 'tiktok',
    ],
    [
        'label' => 'Telegram',
        'handle' => 'sburragrigliata',
        'url' => 'https://t.me/sburragrigliata',
        'icon' => 'fab fa-telegram-plane',
        'kind' => 'telegram',
    ],
    [
        'label' => 'Discord',
        'handle' => 'server',
        'url' => 'https://discord.cripsum.com',
        'icon' => 'fab fa-discord',
        'kind' => 'discord',
    ],
    [
        'label' => 'Website',
        'handle' => 'cripsum.com',
        'url' => 'https://cripsum.com',
        'icon' => 'fas fa-globe',
        'kind' => 'website',
    ],
];

$featuredLinks = [
    [
        'title' => 'Cripsum.com',
        'description' => 'Hub principale, profili, esperimenti e pagine del sito.',
        'url' => 'https://cripsum.com',
        'icon' => 'fas fa-layer-group',
        'tag' => 'Main',
    ],
    [
        'title' => 'GoonLand™',
        'description' => 'Micro-universo più ironico, visuale e personale.',
        'url' => 'https://cripsum.com',
        'icon' => 'fas fa-bolt',
        'tag' => 'Project',
    ],
    [
        'title' => 'Discord Community',
        'description' => 'Server, update, test e roba legata ai progetti.',
        'url' => 'https://discord.cripsum.com',
        'icon' => 'fab fa-discord',
        'tag' => 'Live',
    ],
];

$projectCards = [
    [
        'title' => 'VanzaKart Launcher',
        'description' => 'Launcher e updater custom per modpack su Dolphin.',
        'meta' => 'C# / Dolphin / updater',
        'icon' => 'fas fa-gamepad',
        'url' => '#',
    ],
    [
        'title' => 'Cripsum Lootbox',
        'description' => 'Sistema di personaggi, rarità, inventario e achievement.',
        'meta' => 'PHP / MySQL / JS',
        'icon' => 'fas fa-box-open',
        'url' => 'https://cripsum.com',
    ],
    [
        'title' => 'Edits',
        'description' => 'Video edit personali, prove visual e contenuti brevi.',
        'meta' => 'After Effects / TikTok',
        'icon' => 'fas fa-clapperboard',
        'url' => 'https://tiktok.cripsum.com',
    ],
];

$contentPreviews = [
    [
        'title' => 'Latest edits',
        'description' => 'Raccolta rapida dei contenuti video più recenti.',
        'url' => 'https://tiktok.cripsum.com',
        'icon' => 'fas fa-play',
        'label' => 'Video',
    ],
    [
        'title' => 'Profile hub',
        'description' => 'Statistiche, badge, social e stato Discord in un posto solo.',
        'url' => $profileUrl,
        'icon' => 'fas fa-id-card',
        'label' => 'Bio',
    ],
];

$heroBadges = [
    ['label' => 'Founder', 'icon' => 'fas fa-crown'],
    ['label' => 'Editor', 'icon' => 'fas fa-wand-magic-sparkles'],
    ['label' => 'Developer', 'icon' => 'fas fa-code'],
];

$statCards = [
    ['label' => 'Achievement', 'value' => $achievementCount, 'icon' => 'fas fa-trophy'],
    ['label' => 'Personaggi', 'value' => $uniqueCharacters, 'icon' => 'fas fa-user-astronaut'],
    ['label' => 'Pull totali', 'value' => $totalPulls, 'icon' => 'fas fa-dice-d20'],
    ['label' => 'Crediti', 'value' => (int) ($user['soldi'] ?? 0), 'icon' => 'fas fa-coins'],
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include __DIR__ . '/includes/head-import.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Bio personale di <?= e($profileConfig['display_name']); ?> su cripsum.com">
    <meta property="og:title" content="<?= e($profileConfig['display_name']); ?> — Bio">
    <meta property="og:description" content="<?= e($profileConfig['tagline']); ?>">
    <meta property="og:type" content="profile">
    <meta property="og:url" content="<?= e($profileUrl); ?>">
    <link rel="stylesheet" href="css/style-users.css?v=3">
    <link rel="stylesheet" href="css/bio-v2.css?v=20260425">
    <title><?= e($profileConfig['display_name']); ?> — Bio</title>
    <script src="js/nomePagina.js" defer></script>
    <script src="js/bio-v2.js?v=20260425" defer></script>
</head>
<body
    class="bio-v2-body"
    data-theme="dark"
    data-profile-url="<?= e($profileUrl); ?>"
    data-discord-id="<?= e($profileConfig['discord_id']); ?>"
>
    <?php include __DIR__ . '/includes/navbar-bio.php'; ?>

    <div class="bio-background" aria-hidden="true">
        <video autoplay muted loop playsinline poster="">
            <source src="<?= e($profileConfig['background_video']); ?>" type="video/mp4">
        </video>
        <div class="bio-background__overlay"></div>
        <div class="bio-orb bio-orb--one"></div>
        <div class="bio-orb bio-orb--two"></div>
        <div class="bio-grid-glow"></div>
    </div>

    <main class="bio-page" id="bioPage">
        <section class="bio-hero bio-card js-tilt-card js-reveal" aria-label="Profilo">
            <div class="bio-hero__topline">
                <span class="bio-pill bio-pill--live"><span class="bio-dot"></span> live profile</span>
                <span class="bio-pill">Cripsum™</span>
            </div>

            <div class="bio-avatar-wrap">
                <div class="bio-avatar-ring"></div>
                <img class="bio-avatar" src="<?= e($profileConfig['avatar_url']); ?>" alt="Foto profilo di <?= e($profileConfig['display_name']); ?>" loading="eager">
            </div>

            <div class="bio-name-block">
                <p class="bio-kicker"><?= e($profileConfig['aliases']); ?></p>
                <h1><?= e($profileConfig['display_name']); ?></h1>
                <p class="bio-username">@<?= e($profileConfig['username']); ?></p>
            </div>

            <p class="bio-tagline"><?= e($profileConfig['tagline']); ?></p>
            <p class="bio-description"><?= e($profileConfig['bio']); ?></p>

            <div class="bio-badges" aria-label="Badge profilo">
                <?php foreach ($heroBadges as $badge): ?>
                    <span class="bio-badge"><i class="<?= e($badge['icon']); ?>"></i><?= e($badge['label']); ?></span>
                <?php endforeach; ?>
            </div>

            <div class="bio-meta-row">
                <span><i class="fas fa-location-dot"></i><?= e($profileConfig['location']); ?></span>
                <span><i class="fas fa-calendar"></i>Dal <?= e(formatShortDate($user['data_creazione'] ?? null)); ?></span>
                <span><i class="fas fa-shield-halved"></i><?= e($profileConfig['role']); ?></span>
            </div>

            <div class="bio-actions" aria-label="Azioni profilo">
                <button class="bio-button bio-button--primary js-copy-profile" type="button">
                    <i class="fas fa-link"></i>
                    Copia link
                </button>
                <button class="bio-button js-share-profile" type="button">
                    <i class="fas fa-share-nodes"></i>
                    Share
                </button>
                <button class="bio-icon-button js-theme-toggle" type="button" aria-label="Cambia tema">
                    <i class="fas fa-moon"></i>
                </button>
            </div>

            <div class="bio-accent-picker" aria-label="Accent color">
                <button type="button" class="bio-accent is-active" data-accent="#0f5bff" aria-label="Blu"></button>
                <button type="button" class="bio-accent" data-accent="#8b5cf6" aria-label="Viola"></button>
                <button type="button" class="bio-accent" data-accent="#06b6d4" aria-label="Ciano"></button>
                <button type="button" class="bio-accent" data-accent="#f43f5e" aria-label="Rosa"></button>
            </div>

            <div class="bio-social-grid" aria-label="Link social">
                <?php foreach ($socialLinks as $link): ?>
                    <a class="bio-social bio-social--<?= e($link['kind']); ?>" href="<?= e($link['url']); ?>" target="_blank" rel="noopener noreferrer">
                        <span class="bio-social__icon"><i class="<?= e($link['icon']); ?>"></i></span>
                        <span>
                            <strong><?= e($link['label']); ?></strong>
                            <small><?= e($link['handle']); ?></small>
                        </span>
                        <i class="fas fa-arrow-up-right-from-square bio-social__arrow"></i>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="bio-discord-panel" aria-live="polite">
                <div class="bio-section-heading bio-section-heading--small">
                    <span>Discord status</span>
                    <small>si aggiorna ogni 30s</small>
                </div>
                <div class="discord-box" id="discordBox">
                    <?php
                    $discordProfileId = $profileConfig['discord_id'];
                    require __DIR__ . '/includes/discord_status.php';
                    ?>
                </div>
            </div>

            <div class="bio-audio" data-audio-player>
                <audio id="backgroundAudio" preload="metadata" loop>
                    <source src="<?= e($profileConfig['audio_file']); ?>" type="audio/mpeg">
                </audio>

                <div class="bio-audio__header">
                    <div>
                        <small>now playing</small>
                        <strong><i class="fas fa-music"></i><?= e($profileConfig['audio_title']); ?></strong>
                    </div>
                    <button class="bio-icon-button js-audio-toggle" type="button" aria-label="Play o pausa">
                        <i class="fas fa-play" id="playPauseIcon"></i>
                    </button>
                </div>

                <div class="bio-audio__progress">
                    <span id="currentTime">0:00</span>
                    <input id="progressSlider" type="range" min="0" max="100" value="0" aria-label="Posizione audio">
                    <span id="totalTime">0:00</span>
                </div>

                <div class="bio-audio__bottom">
                    <button class="bio-small-button js-volume-toggle" type="button">
                        <i class="fas fa-volume-up" id="volumeIcon"></i>
                    </button>
                    <input id="volumeSlider" type="range" min="0" max="1" step="0.01" value="0.12" aria-label="Volume audio">
                </div>
            </div>
        </section>

        <section class="bio-content" aria-label="Contenuti profilo">
            <div class="bio-stats-grid js-reveal">
                <?php foreach ($statCards as $stat): ?>
                    <article class="bio-stat-card">
                        <i class="<?= e($stat['icon']); ?>"></i>
                        <strong><?= e(formatCompactNumber($stat['value'])); ?></strong>
                        <span><?= e($stat['label']); ?></span>
                    </article>
                <?php endforeach; ?>
            </div>

            <section class="bio-card bio-featured js-reveal">
                <div class="bio-section-heading">
                    <div>
                        <span>In evidenza</span>
                        <p>Link principali e robe che contano davvero.</p>
                    </div>
                </div>

                <div class="bio-featured-grid">
                    <?php foreach ($featuredLinks as $item): ?>
                        <a class="bio-featured-link" href="<?= e($item['url']); ?>" target="_blank" rel="noopener noreferrer">
                            <span class="bio-featured-link__icon"><i class="<?= e($item['icon']); ?>"></i></span>
                            <span class="bio-featured-link__content">
                                <small><?= e($item['tag']); ?></small>
                                <strong><?= e($item['title']); ?></strong>
                                <em><?= e($item['description']); ?></em>
                            </span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <details class="bio-card bio-details js-reveal" open>
                <summary>
                    <span><i class="fas fa-cubes"></i> Progetti</span>
                    <i class="fas fa-chevron-down"></i>
                </summary>
                <div class="bio-project-grid">
                    <?php foreach ($projectCards as $project): ?>
                        <a class="bio-project-card" href="<?= e($project['url']); ?>" <?= $project['url'] !== '#' ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                            <span class="bio-project-card__icon"><i class="<?= e($project['icon']); ?>"></i></span>
                            <strong><?= e($project['title']); ?></strong>
                            <p><?= e($project['description']); ?></p>
                            <small><?= e($project['meta']); ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </details>

            <details class="bio-card bio-details js-reveal" open>
                <summary>
                    <span><i class="fas fa-play-circle"></i> Edit e contenuti</span>
                    <i class="fas fa-chevron-down"></i>
                </summary>
                <div class="bio-preview-grid">
                    <?php foreach ($contentPreviews as $preview): ?>
                        <a class="bio-preview-card" href="<?= e($preview['url']); ?>" target="_blank" rel="noopener noreferrer">
                            <span class="bio-preview-card__label"><?= e($preview['label']); ?></span>
                            <span class="bio-preview-card__icon"><i class="<?= e($preview['icon']); ?>"></i></span>
                            <strong><?= e($preview['title']); ?></strong>
                            <p><?= e($preview['description']); ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </details>

            <details class="bio-card bio-details js-reveal" open>
                <summary>
                    <span><i class="fas fa-trophy"></i> Badge recenti</span>
                    <i class="fas fa-chevron-down"></i>
                </summary>

                <?php if (!empty($recentAchievements)): ?>
                    <div class="bio-achievement-grid">
                        <?php foreach ($recentAchievements as $achievement): ?>
                            <?php
                            $achievementImage = !empty($achievement['img_url'])
                                ? 'img/' . ltrim((string) $achievement['img_url'], '/')
                                : null;
                            ?>
                            <article class="bio-achievement-card">
                                <?php if ($achievementImage): ?>
                                    <img src="<?= e($achievementImage); ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span class="bio-achievement-card__fallback"><i class="fas fa-medal"></i></span>
                                <?php endif; ?>
                                <div>
                                    <strong><?= e($achievement['nome']); ?></strong>
                                    <p><?= e($achievement['descrizione'] ?? 'Achievement sbloccato.'); ?></p>
                                    <small><?= e((int) ($achievement['punti'] ?? 0)); ?> punti · <?= e(formatShortDate($achievement['data'] ?? null)); ?></small>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bio-empty-state">
                        <i class="fas fa-medal"></i>
                        <strong>Nessun badge recente</strong>
                        <p>Quando questo profilo sblocca achievement, appariranno qui.</p>
                    </div>
                <?php endif; ?>
            </details>

            <section class="bio-card bio-about js-reveal">
                <div class="bio-section-heading">
                    <div>
                        <span>Info rapide</span>
                        <p>Dettagli utili senza riempire troppo la pagina.</p>
                    </div>
                </div>

                <div class="bio-info-list">
                    <div>
                        <span>Profilo</span>
                        <strong><?= e($profileConfig['display_name']); ?></strong>
                    </div>
                    <div>
                        <span>Username</span>
                        <strong>@<?= e($profileConfig['username']); ?></strong>
                    </div>
                    <div>
                        <span>Account creato</span>
                        <strong><?= e(formatShortDate($user['data_creazione'] ?? null)); ?></strong>
                    </div>
                    <div>
                        <span>Focus</span>
                        <strong>editing / dev / web experiments</strong>
                    </div>
                </div>
            </section>
        </section>
    </main>

    <div class="bio-toast" id="bioToast" role="status" aria-live="polite"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
</body>
</html>
