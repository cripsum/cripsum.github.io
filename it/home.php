<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

checkBan($mysqli);

$isLoggedIn = function_exists('isLoggedIn') && isLoggedIn();
$currentUsername = $_SESSION['username'] ?? null;

function home_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function home_table_exists(mysqli $mysqli, string $table): bool
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return $cache[$table] = false;
    }

    try {
        $stmt = $mysqli->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND BINARY TABLE_NAME = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return $cache[$table] = false;
        }

        $stmt->bind_param('s', $table);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $cache[$table] = $exists;
    } catch (Throwable $e) {
        return $cache[$table] = false;
    }
}

function home_count(mysqli $mysqli, string $sql): int
{
    try {
        $result = $mysqli->query($sql);
        if (!$result) {
            return 0;
        }

        $row = $result->fetch_assoc();
        return (int)($row['total'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function home_compact_number(int $number): string
{
    if ($number >= 1000000) return round($number / 1000000, 1) . 'M';
    if ($number >= 1000) return round($number / 1000, 1) . 'K';
    return (string)$number;
}

$stats = [
    'utenti' => home_table_exists($mysqli, 'utenti') ? home_count($mysqli, "SELECT COUNT(*) AS total FROM utenti") : 0,
    'shitpost' => home_table_exists($mysqli, 'shitposts') ? home_count($mysqli, "SELECT COUNT(*) AS total FROM shitposts WHERE approvato = 1") : 0,
    'rimasti' => home_table_exists($mysqli, 'toprimasti') ? home_count($mysqli, "SELECT COUNT(*) AS total FROM toprimasti WHERE approvato = 1") : 0,
    'achievement' => home_table_exists($mysqli, 'achievement') ? home_count($mysqli, "SELECT COUNT(*) AS total FROM achievement") : 0,
];

$quickLinks = [
    [
        'title' => 'Profili',
        'text' => 'Crea una bio e mostra link, badge e contenuti.',
        'icon' => 'fas fa-user-astronaut',
        'url' => $isLoggedIn && $currentUsername ? '/u/' . rawurlencode(strtolower($currentUsername)) : '/it/accedi',
        'tag' => 'Bio',
    ],
    [
        'title' => 'Shitpost',
        'text' => 'Meme, GIF e post della community.',
        'icon' => 'fas fa-image',
        'url' => '/it/shitpost',
        'tag' => home_compact_number($stats['shitpost']) . ' post',
    ],
    [
        'title' => 'Top Rimasti',
        'text' => 'La classifica dei post più votati.',
        'icon' => 'fas fa-ranking-star',
        'url' => '/it/rimasti',
        'tag' => home_compact_number($stats['rimasti']) . ' post',
    ],
    [
        'title' => 'Chat Globale',
        'text' => 'Entra nella chat del sito.',
        'icon' => 'fas fa-comments',
        'url' => '/it/global-chat',
        'tag' => 'Live',
    ],
    [
        'title' => 'Lootbox',
        'text' => 'Apri casse, colleziona personaggi e badge.',
        'icon' => 'fas fa-box-open',
        'url' => '/it/lootbox',
        'tag' => 'Game',
    ],
    [
        'title' => 'Edit',
        'text' => 'Guarda gli ultimi video/edit.',
        'icon' => 'fas fa-clapperboard',
        'url' => '/it/edits',
        'tag' => 'Video',
    ],
];

$featured = [
    [
        'title' => 'GoonLand',
        'text' => 'La parte più strana del sito. Giochi, post e robe interne.',
        'icon' => 'fas fa-wand-magic-sparkles',
        'url' => '/it/goonland',
    ],
    [
        'title' => 'Achievement',
        'text' => 'Badge da sbloccare usando il sito.',
        'icon' => 'fas fa-trophy',
        'url' => '/it/achievement',
    ],
    [
        'title' => 'CripsumPedia',
        'text' => 'Pagine, profili e lore del sito.',
        'icon' => 'fas fa-book-skull',
        'url' => '/it/cripsumpedia',
    ],
];

$ogTitle = 'Cripsum™';
$ogDescription = 'Profili, chat, shitpost, Top Rimasti, lootbox e contenuti della community.';
$ogImage = 'https://cripsum.com/img/Susremaster.png';
$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/it/home'), '#');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include __DIR__ . '/../includes/head-import.php'; ?>
    <title>Cripsum™</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?php echo home_h($ogDescription); ?>">
    <meta property="og:site_name" content="Cripsum™">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo home_h($ogTitle); ?>">
    <meta property="og:description" content="<?php echo home_h($ogDescription); ?>">
    <meta property="og:image" content="<?php echo home_h($ogImage); ?>">
    <meta property="og:url" content="<?php echo home_h($ogUrl); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo home_h($ogTitle); ?>">
    <meta name="twitter:description" content="<?php echo home_h($ogDescription); ?>">
    <meta name="twitter:image" content="<?php echo home_h($ogImage); ?>">
    <link rel="preload" as="image" href="/img/Susremaster.png">
    <link rel="stylesheet" href="/assets/home-v2/home-v2.css?v=2.0-home">
    <script src="/assets/home-v2/home-v2.js?v=2.0-home" defer></script>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1527058839538660" crossorigin="anonymous"></script>
</head>
<body class="home-v2-body">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/impostazioni.php'; ?>

    <div class="home-bg" aria-hidden="true">
        <span class="home-orb home-orb--one"></span>
        <span class="home-orb home-orb--two"></span>
        <span class="home-grid"></span>
    </div>

    <main class="home-shell">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="home-alert" role="alert">
                <i class="fas fa-triangle-exclamation"></i>
                <span><?php echo home_h($_SESSION['error_message']); ?></span>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <section class="home-hero js-home-reveal">
            <div class="home-hero__content">
                <span class="home-pill">Cripsum™</span>
                <h1>Il sito dove finisce la roba normale.</h1>
                <p>Profili, chat, meme, lootbox, edit e cose della community. Tutto nello stesso posto.</p>

                <div class="home-hero__actions">
                    <?php if ($isLoggedIn && $currentUsername): ?>
                        <a class="home-btn home-btn--primary" href="/u/<?php echo rawurlencode(strtolower($currentUsername)); ?>">
                            <i class="fas fa-user"></i>
                            <span>Vai al tuo profilo</span>
                        </a>
                    <?php else: ?>
                        <a class="home-btn home-btn--primary" href="/it/registrati">
                            <i class="fas fa-user-plus"></i>
                            <span>Crea account</span>
                        </a>
                    <?php endif; ?>
                    <a class="home-btn home-btn--ghost" href="/it/shitpost">
                        <i class="fas fa-image"></i>
                        <span>Guarda il feed</span>
                    </a>
                </div>
            </div>

            <div class="home-hero__visual">
                <div class="home-hero-card">
                    <img src="/img/amongus.jpg" alt="Cripsum" loading="eager">
                    <div class="home-hero-card__caption">
                        <strong>GoonLand ready</strong>
                        <span>Feed, profili e caos controllato.</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="home-stats js-home-reveal" aria-label="Statistiche sito">
            <article>
                <strong><?php echo home_h(home_compact_number($stats['utenti'])); ?></strong>
                <span>Utenti</span>
            </article>
            <article>
                <strong><?php echo home_h(home_compact_number($stats['shitpost'])); ?></strong>
                <span>Shitpost</span>
            </article>
            <article>
                <strong><?php echo home_h(home_compact_number($stats['rimasti'])); ?></strong>
                <span>Top Rimasti</span>
            </article>
            <article>
                <strong><?php echo home_h(home_compact_number($stats['achievement'])); ?></strong>
                <span>Achievement</span>
            </article>
        </section>

        <section class="home-section js-home-reveal">
            <div class="home-section-head">
                <div>
                    <span class="home-kicker">Sezioni</span>
                    <h2>Scegli dove andare</h2>
                </div>
            </div>

            <div class="home-link-grid">
                <?php foreach ($quickLinks as $link): ?>
                    <a class="home-link-card" href="<?php echo home_h($link['url']); ?>">
                        <span class="home-link-card__tag"><?php echo home_h($link['tag']); ?></span>
                        <i class="<?php echo home_h($link['icon']); ?>"></i>
                        <strong><?php echo home_h($link['title']); ?></strong>
                        <p><?php echo home_h($link['text']); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="home-featured js-home-reveal">
            <?php foreach ($featured as $item): ?>
                <a class="home-feature-card" href="<?php echo home_h($item['url']); ?>">
                    <i class="<?php echo home_h($item['icon']); ?>"></i>
                    <div>
                        <strong><?php echo home_h($item['title']); ?></strong>
                        <span><?php echo home_h($item['text']); ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </section>

        <section class="home-mood js-home-reveal" aria-label="Mood Cripsum">
            <article>
                <img src="/img/felicita.jpg" alt="Felicità" loading="lazy">
                <strong>Felicità</strong>
            </article>
            <article>
                <img src="/img/tristezza.jpg" alt="Tristezza" loading="lazy">
                <strong>Tristezza</strong>
            </article>
            <article>
                <img src="/img/stupore.jpg" alt="Stupore" loading="lazy">
                <strong>Stupore</strong>
            </article>
        </section>

        <section id="featuredContent" class="home-slider-section js-home-reveal">
            <div class="home-section-head">
                <div>
                    <span class="home-kicker">In evidenza</span>
                    <h2>Ultimi contenuti</h2>
                </div>
            </div>
            <div id="content-slider" class="content-slider">
                <div class="slider-wrapper" id="sliderWrapper"></div>
                <div class="slider-dots" id="sliderDots"></div>
            </div>
        </section>

        <section class="home-social js-home-reveal">
            <div class="home-section-head home-section-head--center">
                <span class="home-kicker">Social</span>
                <h2>Seguimi anche fuori dal sito</h2>
            </div>

            <div class="home-social-grid">
                <a href="https://www.tiktok.com/@cripsum" target="_blank" rel="noopener" class="home-social-link">
                    <i class="fab fa-tiktok"></i>
                    <span>TikTok</span>
                </a>
                <a href="https://www.instagram.com/cripsum/" target="_blank" rel="noopener" class="home-social-link">
                    <i class="fab fa-instagram"></i>
                    <span>Instagram</span>
                </a>
                <a href="https://discord.gg/XdheJHVURw" target="_blank" rel="noopener" class="home-social-link">
                    <i class="fab fa-discord"></i>
                    <span>Discord</span>
                </a>
                <a href="https://t.me/cripsum" target="_blank" rel="noopener" class="home-social-link">
                    <i class="fab fa-telegram-plane"></i>
                    <span>Telegram</span>
                </a>
            </div>
        </section>

        <?php if (!$isLoggedIn): ?>
            <section class="home-account js-home-reveal">
                <div>
                    <span class="home-kicker">Account</span>
                    <h2>Con un account fai di più.</h2>
                    <p>Puoi usare profili, chat, lootbox, achievement e contenuti della community.</p>
                </div>
                <div class="home-account__actions">
                    <a class="home-btn home-btn--primary" href="/it/registrati">Registrati</a>
                    <a class="home-btn home-btn--ghost" href="/it/accedi">Accedi</a>
                </div>
            </section>
        <?php endif; ?>

        <section class="home-small-actions js-home-reveal">
            <button class="home-btn home-btn--ghost" type="button" data-bs-toggle="modal" data-bs-target="#disclaimerModal">
                <i class="fas fa-circle-info"></i>
                <span>Note sul sito</span>
            </button>

            <button class="home-btn home-btn--ghost js-copy-home" type="button" data-url="https://cripsum.com/it/home">
                <i class="fas fa-link"></i>
                <span>Copia link</span>
            </button>

            <button class="home-btn home-btn--ghost" type="button" onclick="if (typeof unlockAchievement === 'function') unlockAchievement(10); window.open('https://youtu.be/xvFZjo5PgG0?si=uPsap7ILF_8aYheh', '_blank', 'noopener');">
                <i class="fas fa-gift"></i>
                <span>V-bucks gratis</span>
            </button>
        </section>
    </main>

    <div class="modal fade home-modal" id="disclaimerModal" tabindex="-1" aria-labelledby="disclaimerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content home-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="disclaimerModalLabel">Note sul sito</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body">
                    <p>Cripsum™ è fatto per intrattenere. Alcune pagine usano meme, ironia e contenuti interni alla community.</p>
                    <ul>
                        <li>I contenuti sono pensati per far ridere, non per offendere.</li>
                        <li>Le pagine di download vanno usate solo se ti fidi del progetto.</li>
                        <li>Shop e checkout, se presenti, sono parti simulate.</li>
                        <li>Le donazioni sono reali: dona solo se vuoi davvero farlo.</li>
                    </ul>
                    <p class="home-muted">Per trasparenza, parte del codice può essere controllata su GitHub.</p>
                </div>
            </div>
        </div>
    </div>

    <div id="achievement-popup" class="popup">
        <img id="popup-image" src="" alt="Achievement">
        <div>
            <h3 id="popup-title"></h3>
            <p id="popup-description"></p>
        </div>
    </div>

    <div id="homeToast" class="home-toast" role="status" aria-live="polite"></div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="/js/slider.js?v=5"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
