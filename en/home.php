<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (isset($mysqli) && $mysqli instanceof mysqli) {
    @$mysqli->set_charset('utf8mb4');
}

if (function_exists('checkBan')) {
    checkBan($mysqli);
}

$isLoggedIn = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
$currentUsername = $_SESSION['username'] ?? null;

$isPremium = false;
$supporters = [];

if (isset($mysqli) && $mysqli instanceof mysqli) {
    if ($isLoggedIn && isset($_SESSION['user_id'])) {
        $stmtPrem = $mysqli->prepare("SELECT is_premium FROM utenti WHERE id = ? LIMIT 1");
        if ($stmtPrem) {
            $stmtPrem->bind_param('i', $_SESSION['user_id']);
            $stmtPrem->execute();
            $resPrem = $stmtPrem->get_result()->fetch_assoc();
            $isPremium = ((int)($resPrem['is_premium'] ?? 0) === 1);
            $stmtPrem->close();
        }
    }

    $stmtSupp = $mysqli->prepare("SELECT id, username, display_name, discord_use_display_name, discord_global_name, discord_username, profile_updated_at, accent_color, avatar_ring_color FROM utenti WHERE is_premium = 1 ORDER BY id DESC");
    if ($stmtSupp) {
        $stmtSupp->execute();
        $resSupp = $stmtSupp->get_result();
        while ($row = $resSupp->fetch_assoc()) {
            $supporters[] = $row;
        }
        $stmtSupp->close();
    }
}

function home_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$profileUrl = ($isLoggedIn && $currentUsername)
    ? '/u/' . rawurlencode(strtolower((string)$currentUsername))
    : 'accedi';

$ogDescription = 'Cripsum™ Homepage. Edits, memes, lootboxes, profiles, and plenty of gooning.';
$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/en/home'), '#');
?>
<!DOCTYPE html>
<html lang="en">

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
    <link rel="stylesheet" href="/assets/home-v5/home.css?v=6.3">
    <link rel="stylesheet" href="/assets/news/news-popup.css?v=1.0">
    <script src="/assets/home-v5/home.js?v=5.7" defer></script>
    <script src="/assets/news/news-popup.js?v=1.0" defer></script>

    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1527058839538660" crossorigin="anonymous"></script>
</head>

<body class="home-v5-body">
    <?php include '../includes/navbar.php'; ?>

    <div class="home-bg" aria-hidden="true">
        <span class="home-noise"></span>
        <span class="home-orb home-orb--one"></span>
        <span class="home-orb home-orb--two"></span>
        <span class="home-grid"></span>
    </div>

    <main class="home-page">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="home-alert" role="alert">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?php echo home_h($_SESSION['error_message']); ?></span>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <section class="home-hero home-reveal">
            <div class="home-hero__copy">
                <span class="home-pill">Cripsum™</span>
                <h1>Welcome to the best site in the Congo.</h1>
                <p>Editing, memes, lootboxes, profiles, achievements, community posts and many secrets. What are you waiting for? Join us!</p>
                <p class="home-question">Are you over 25 and own a PC?</p>

                <div class="home-actions">
                    <?php if ($isLoggedIn && $currentUsername): ?>
                        <a class="home-btn home-btn--primary" href="<?php echo home_h($profileUrl); ?>">
                            <i class="fa-solid fa-user"></i>
                            <span>Go to profile</span>
                        </a>
                    <?php else: ?>
                        <a class="home-btn home-btn--primary" href="registrati">
                            <i class="fa-solid fa-user-plus"></i>
                            <span>Sign up</span>
                        </a>
                        <a class="home-btn home-btn--ghost" href="accedi">
                            <i class="fa-solid fa-right-to-bracket"></i>
                            <span>Login</span>
                        </a>
                    <?php endif; ?>

                    <button class="home-btn home-btn--ghost" type="button" onclick="if(window.newsPopup) window.newsPopup.open();">
                        <i class="fa-solid fa-layer-group"></i>
                        <span>News</span>
                    </button>

                    <button class="home-btn home-btn--plain" type="button" data-bs-toggle="modal" data-bs-target="#disclaimerModal">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>Disclaimer</span>
                    </button>
                </div>
            </div>

            <div class="home-hero__art" aria-hidden="true">
                <div class="home-hero__glow"></div>
                <img src="../img/amongus-logo.jpg" alt="">
            </div>
        </section>

        <section class="home-mood home-reveal" aria-label="Mood del sito">
            <article class="home-mood-item">
                <img src="../img/felicita.jpg" alt="Felicità" loading="lazy">
                <span>Happiness</span>
            </article>
            <article class="home-mood-item">
                <img src="../img/tristezza.jpg" alt="Tristezza" loading="lazy">
                <span>Sadness</span>
            </article>
            <article class="home-mood-item">
                <img src="../img/stupore.jpg" alt="Stupore" loading="lazy">
                <span>Amazement</span>
            </article>
        </section>

        <section id="featuredContent" class="home-feature home-reveal">
            <div class="home-section-head">
                <div>
                    <h2>What you can do on Cripsum™</h2>
                </div>
                <!-- <p>Una preview delle pagine principali.</p> -->
            </div>

            <div class="home-slider" id="homeSlider">
                <div class="home-slider__backdrop" id="homeSliderBackdrop" aria-hidden="true"></div>

                <div class="home-slider__stage" id="homeSliderStage" aria-live="polite"></div>

                <div class="home-slider__controls" aria-hidden="true">
                    <div class="home-slider__progress">
                        <span id="homeSliderProgress"></span>
                    </div>
                </div>

                <div class="home-slider__tabs" id="homeSliderTabs" aria-label="Seleziona contenuto"></div>
            </div>
        </section>

        <!-- PREMIUM AD BLOCK & SUPPORTERS (ENGLISH) -->
        <?php if (!$isPremium): ?>
            <section class="home-premium-promo-card home-reveal">
                <div class="promo-copy">
                    <span class="promo-tag">Cripsum™ Premium</span>
                    <h3>Unlock the Ultimate Cripsum™ Experience</h3>
                    <p>Get premium perks, double your rewards, and show off your support to the community.</p>
                    <div class="promo-benefits">
                        <div class="benefit-item"><i class="fa-solid fa-gem"></i><span>200k points instantly upon purchase</span></div>
                        <div class="benefit-item"><i class="fa-solid fa-gem"></i><span>Unlock premium profile customization</span></div>
                        <div class="benefit-item"><i class="fa-solid fa-gem"></i><span>Daily claim of 500 points in Lootbox</span></div>
                        <div class="benefit-item"><i class="fa-solid fa-gem"></i><span>Double points (2x) on Daily & Weekly missions</span></div>
                        <div class="benefit-item"><i class="fa-solid fa-gem"></i><span>Exclusive premium gem tag next to your name</span></div>
                        <div class="benefit-item"><i class="fa-solid fa-gem"></i><span>Featured in the homepage Supporters list</span></div>
                    </div>
                    <div class="promo-actions">
                        <a href="checkout-premium" class="promo-btn-primary">
                            <i class="fa-solid fa-cart-shopping"></i>
                            <span>Get Premium</span>
                        </a>
                    </div>
                </div>
                <div class="promo-art" aria-hidden="true">
                    <i class="fa-solid fa-gem"></i>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($supporters)): ?>
            <section class="home-supporters-section home-reveal">
                <div class="home-supporters-title">
                    <div>
                        <h2>Our Premium Supporters</h2>
                        <p>A big thanks to the users who support Cripsum™!</p>
                    </div>
                </div>
                <div class="supporters-scroll-wrapper">
                    <div class="supporters-grid">
                        <?php foreach ($supporters as $s): 
                            $useDiscord = (int)($s['discord_use_display_name'] ?? 0) === 1;
                            $discord = trim((string)($s['discord_global_name'] ?? '')) ?: trim((string)($s['discord_username'] ?? ''));
                            $dispName = ($useDiscord && $discord !== '') ? $discord : (trim((string)($s['display_name'] ?? '')) ?: $s['username']);
                            $stamp = !empty($s['profile_updated_at']) ? strtotime((string)$s['profile_updated_at']) : time();
                            
                            $suppColor = !empty($s['accent_color']) ? $s['accent_color'] : '#db2777';
                        ?>
                            <a href="/u/<?= rawurlencode(strtolower($s['username'])) ?>" class="supporter-card" title="<?= htmlspecialchars($dispName) ?>" style="--supporter-color: <?= htmlspecialchars($suppColor) ?>;">
                                <div class="supporter-avatar-container">
                                    <img src="/includes/get_pfp.php?id=<?= (int)$s['id'] ?>&t=<?= $stamp ?>" alt="" class="supporter-pfp">
                                    <div class="supporter-badge"><i class="fa-solid fa-gem"></i></div>
                                </div>
                                <span class="supporter-name"><?= htmlspecialchars($dispName) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="home-social-section home-reveal">
            <div class="home-section-head home-section-head--center">
                <h2>My socials</h2>
            </div>

            <div class="social-icons-modern">
                <a href="https://www.tiktok.com/@cripsum" class="social-link-modern tiktok" title="TikTok" target="_blank" rel="noopener">
                    <div class="social-icon-wrapper">
                        <i class="fa-brands fa-tiktok"></i>
                        <span class="social-label">TikTok</span>
                    </div>
                </a>
                <a href="https://www.instagram.com/cripsum/" class="social-link-modern instagram" title="Instagram" target="_blank" rel="noopener">
                    <div class="social-icon-wrapper">
                        <i class="fa-brands fa-instagram"></i>
                        <span class="social-label">Instagram</span>
                    </div>
                </a>
                <a href="https://discord.gg/XdheJHVURw" class="social-link-modern discord" title="Discord" target="_blank" rel="noopener">
                    <div class="social-icon-wrapper">
                        <i class="fa-brands fa-discord"></i>
                        <span class="social-label">Discord</span>
                    </div>
                </a>
                <a href="https://t.me/cripsum" class="social-link-modern telegram" title="Telegram" target="_blank" rel="noopener">
                    <div class="social-icon-wrapper">
                        <i class="fa-brands fa-telegram"></i>
                        <span class="social-label">Telegram</span>
                    </div>
                </a>
            </div>
        </section>

        <section class="home-chaos home-reveal">
            <a class="home-btn home-btn--ghost home-chaos__btn"
                href="https://youtu.be/xvFZjo5PgG0?si=uPsap7ILF_8aYheh"
                target="_blank"
                rel="noopener"
                onclick="if (typeof unlockAchievement === 'function') unlockAchievement(10);">
                <i class="fa-solid fa-gift"></i>
                <span>Grab your free V-bucks here!!!!</span>
            </a>
        </section>

        <?php if (!$isLoggedIn): ?>
            <section class="home-account home-reveal">
                <div>
                    <span class="home-kicker">Account</span>
                    <h2>Got a Cripsum™ account?</h2>
                    <p>Your account unlocks your profile, chat, lootboxes, and achievements.</p>
                </div>

                <div class="home-account__actions">
                    <a href="accedi" class="home-btn home-btn--ghost">Log in</a>
                    <a href="registrati" class="home-btn home-btn--primary">Sign up</a>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <div class="modal fade home-modal" id="disclaimerModal" tabindex="-1" aria-labelledby="disclaimerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content home-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="disclaimerModalLabel">Disclaimer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Cripsum™ is a personal project made for fun. Expect plenty of memes, irony, and inside jokes throughout the site.</p>
                    <ul>
                        <li>Don't take everything here seriously.</li>
                        <li>Any shops or checkouts you see are just for show (simulated).</li>
                        <li>Donations are the real deal: only donate if you actually want to support the project.</li>
                    </ul>
                    <p class="home-muted">
                        Some of the code is public on
                        <a href="https://github.com/cripsum/cripsum.github.io" target="_blank" rel="noopener">GitHub</a>.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="home-btn home-btn--ghost" data-bs-dismiss="modal">Close</button>
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

    <?php include '../includes/footer-en.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <?php if (!empty($supporters)): ?>
        <script src="/js/home-supporters.js?v=1.9" defer></script>
    <?php endif; ?>
</body>

</html>