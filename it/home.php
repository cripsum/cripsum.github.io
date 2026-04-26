<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

checkBan($mysqli);

$isLoggedIn = isset($_SESSION['user_id']);
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
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

function home_safe_count(mysqli $mysqli, string $sql): int
{
    try {
        $result = $mysqli->query($sql);
        if (!$result) return 0;
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
    'shitposts' => home_table_exists($mysqli, 'shitposts') ? home_safe_count($mysqli, "SELECT COUNT(*) AS total FROM shitposts WHERE approvato = 1") : 0,
    'rimasti' => home_table_exists($mysqli, 'toprimasti') ? home_safe_count($mysqli, "SELECT COUNT(*) AS total FROM toprimasti WHERE approvato = 1") : 0,
    'achievement' => home_table_exists($mysqli, 'achievement') ? home_safe_count($mysqli, "SELECT COUNT(*) AS total FROM achievement") : 0,
];

$personalStats = [
    'personaggi' => 0,
    'achievement' => 0,
];

if ($isLoggedIn && $currentUserId > 0) {
    if (home_table_exists($mysqli, 'utenti_personaggi')) {
        $stmt = $mysqli->prepare("SELECT COALESCE(SUM(`quantità`), 0) AS total FROM utenti_personaggi WHERE utente_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $currentUserId);
            $stmt->execute();
            $personalStats['personaggi'] = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $stmt->close();
        }
    }

    if (home_table_exists($mysqli, 'utenti_achievement')) {
        $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM utenti_achievement WHERE utente_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $currentUserId);
            $stmt->execute();
            $personalStats['achievement'] = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $stmt->close();
        }
    }
}

$mainLinks = [
    [
        'title' => 'Edit',
        'text' => 'Gli ultimi video e lavori.',
        'url' => 'edits',
        'icon' => 'fas fa-clapperboard',
    ],
    [
        'title' => 'Shitpost',
        'text' => 'Meme, GIF e post della community.',
        'url' => 'shitpost',
        'icon' => 'fas fa-image',
    ],
    [
        'title' => 'Top Rimasti',
        'text' => 'La classifica dei post più votati.',
        'url' => 'rimasti',
        'icon' => 'fas fa-ranking-star',
    ],
    [
        'title' => 'Chat Globale',
        'text' => 'La chat del sito.',
        'url' => 'global-chat',
        'icon' => 'fas fa-comments',
    ],
    [
        'title' => 'Lootbox',
        'text' => 'Apri casse e colleziona personaggi.',
        'url' => 'lootbox',
        'icon' => 'fas fa-box-open',
    ],
    [
        'title' => 'GoonLand',
        'text' => 'La parte più interna del sito.',
        'url' => 'goonland',
        'icon' => 'fas fa-wand-magic-sparkles',
    ],
];

$ogDescription = 'Homepage di Cripsum™. Edit, profili, chat, shitpost, Top Rimasti e GoonLand.';
$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/it/home'), '#');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title data-i18n="meta.title">Cripsum™</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?php echo home_h($ogDescription); ?>" data-i18n-attr="content|meta.desc">
    <meta property="og:site_name" content="Cripsum™">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Cripsum™">
    <meta property="og:description" content="<?php echo home_h($ogDescription); ?>">
    <meta property="og:image" content="https://cripsum.com/img/Susremaster.png">
    <meta property="og:url" content="<?php echo home_h($ogUrl); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="preload" as="image" href="../img/amongus.jpg">
    <link rel="stylesheet" href="/assets/home-original-v2/home-original-v2.css?v=3.3-original-showcase">
    <script src="/assets/home-original-v2/home-original-v2.js?v=3.3-original-showcase" defer></script>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1527058839538660" crossorigin="anonymous"></script>
</head>

<body class="home-original-v2-body">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="home-bg" aria-hidden="true">
        <span class="home-orb home-orb--one"></span>
        <span class="home-orb home-orb--two"></span>
        <span class="home-grid"></span>
    </div>

    <main class="home-wrap">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="home-alert" role="alert">
                <i class="fas fa-triangle-exclamation"></i>
                <span><?php echo home_h($_SESSION['error_message']); ?></span>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <section class="home-hero home-reveal">
            <div class="home-hero__image">
                <img src="../img/amongus.jpg" alt="Cripsum Hero" loading="eager">
            </div>

            <div class="home-hero__text">
                <span class="home-pill">Cripsum™</span>
                <h1>Benvenuto nel sito migliore del Congo.</h1>
                <p class="home-subtitle">Edit, meme, profili, lootbox e robe della community.</p>
                <p class="home-question">Hai più di 25 anni e possiedi un PC?</p>

                <div class="home-actions">
                    <?php if ($isLoggedIn && $currentUsername): ?>
                        <a class="home-btn home-btn--primary" href="/u/<?php echo rawurlencode(strtolower($currentUsername)); ?>">
                            <i class="fas fa-user"></i>
                            <span>Vai al profilo</span>
                        </a>
                    <?php else: ?>
                        <a class="home-btn home-btn--primary" href="registrati">
                            <i class="fas fa-user-plus"></i>
                            <span>Registrati</span>
                        </a>
                        <a class="home-btn home-btn--ghost" href="accedi">
                            <i class="fas fa-right-to-bracket"></i>
                            <span>Accedi</span>
                        </a>
                    <?php endif; ?>

                    <a class="home-btn home-btn--ghost" href="#featuredContent">
                        <i class="fas fa-layer-group"></i>
                        <span>Contenuti</span>
                    </a>
                </div>
            </div>
        </section>
        <section class="home-tree-section home-reveal">
            <article class="home-tree-card">
                <img src="../img/felicita.jpg" alt="Felicità" loading="lazy">
                <strong>Felicità</strong>
            </article>

            <article class="home-tree-card">
                <img src="../img/tristezza.jpg" alt="Tristezza" loading="lazy">
                <strong>Tristezza</strong>
            </article>

            <article class="home-tree-card">
                <img src="../img/stupore.jpg" alt="Stupore" loading="lazy">
                <strong>Stupore</strong>
            </article>
        </section>

        <section id="featuredContent" class="home-showcase-section home-reveal">
            <div class="home-section-head">
                <div>
                    <span class="home-kicker">Contenuti</span>
                    <h2>Le pagine del sito</h2>
                </div>
                <p>Un giro veloce tra le cose principali, senza casino.</p>
            </div>

            <div class="home-showcase" id="homeShowcase">
                <button class="home-showcase-arrow home-showcase-arrow--prev" type="button" id="homeShowcasePrev" aria-label="Contenuto precedente">
                    <i class="fas fa-chevron-left"></i>
                </button>

                <article class="home-showcase-main" id="homeShowcaseMain" aria-live="polite"></article>

                <button class="home-showcase-arrow home-showcase-arrow--next" type="button" id="homeShowcaseNext" aria-label="Contenuto successivo">
                    <i class="fas fa-chevron-right"></i>
                </button>

                <div class="home-showcase-thumbs" id="homeShowcaseThumbs" aria-label="Scegli contenuto"></div>
            </div>
        </section>

        <section class="home-social-section home-reveal">
            <div class="home-section-head home-section-head--center">
                <span class="home-kicker">Social</span>
                <h2>I link seri</h2>
            </div>

            <div class="home-social-grid home-social-grid--original">
                <a href="https://www.tiktok.com/@cripsum" target="_blank" rel="noopener" class="home-social-btn home-social-btn--tiktok">
                    <i class="fab fa-tiktok"></i>
                    <span>TikTok</span>
                </a>
                <a href="https://www.instagram.com/cripsum/" target="_blank" rel="noopener" class="home-social-btn home-social-btn--instagram">
                    <i class="fab fa-instagram"></i>
                    <span>Instagram</span>
                </a>
                <a href="https://discord.gg/XdheJHVURw" target="_blank" rel="noopener" class="home-social-btn home-social-btn--discord">
                    <i class="fab fa-discord"></i>
                    <span>Discord</span>
                </a>
                <a href="https://t.me/cripsum" target="_blank" rel="noopener" class="home-social-btn home-social-btn--telegram">
                    <i class="fab fa-telegram-plane"></i>
                    <span>Telegram</span>
                </a>
            </div>
        </section>

        <section class="home-chaos-section<section class="home-chaos-section home-reveal">
            <button class="home-btn home-btn--ghost" type="button" onclick="if (typeof unlockAchievement === 'function') unlockAchievement(10); window.open('https://youtu.be/xvFZjo5PgG0?si=uPsap7ILF_8aYheh', '_blank', 'noopener');">
                <i class="fas fa-gift"></i>
                <span>V-bucks gratis!!!!</span>
            </button>
        </section>

        <?php if (!$isLoggedIn): ?>
            <section class="home-account-section home-reveal">
                <div>
                    <span class="home-kicker">Account</span>
                    <h2>Hai un account Cripsum™?</h2>
                    <p>Con l’account usi chat, lootbox, achievement, profilo e altre pagine.</p>
                </div>

                <div class="home-account-actions">
                    <a href="accedi" class="home-btn home-btn--ghost">Accedi</a>
                    <a href="registrati" class="home-btn home-btn--primary">Registrati</a>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <div class="modal fade home-modal" id="disclaimerModal" tabindex="-1" aria-labelledby="disclaimerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content home-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="disclaimerModalLabel">Note sul sito</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body">
                    <p>Cripsum™ è fatto per intrattenere. Alcune pagine usano meme, ironia e contenuti interni.</p>
                    <ul>
                        <li>I contenuti sono pensati per far ridere, non per offendere.</li>
                        <li>Le pagine di download sono contenuti del sito e meme.</li>
                        <li>Shop e checkout, se presenti, sono parti simulate.</li>
                        <li>Le donazioni sono reali: dona solo se vuoi davvero farlo.</li>
                    </ul>
                    <p class="home-muted">
                        Per trasparenza, parte del codice può essere controllata su
                        <a href="https://github.com/cripsum/cripsum.github.io" class="home-inline-link" target="_blank" rel="noopener">GitHub</a>.
                    </p>
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

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
