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

$ogDescription = 'Homepage di Cripsum™. Edit, meme, lootbox, profili e tanto gooning.';
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
                <h1>Benvenuto/a nel sito migliore del Congo.</h1>
                <p>Editing, meme, lootbox, profili, achievements, post della community e tanti segreti, cosa aspetti a unirti?</p>
                <p class="home-question">Hai più di 25 anni e possiedi un PC?</p>

                <div class="home-actions">
                    <?php if ($isLoggedIn && $currentUsername): ?>
                        <a class="home-btn home-btn--primary" href="<?php echo home_h($profileUrl); ?>">
                            <i class="fa-solid fa-user"></i>
                            <span>Vai al profilo</span>
                        </a>
                    <?php else: ?>
                        <a class="home-btn home-btn--primary" href="registrati">
                            <i class="fa-solid fa-user-plus"></i>
                            <span>Registrati</span>
                        </a>
                        <a class="home-btn home-btn--ghost" href="accedi">
                            <i class="fa-solid fa-right-to-bracket"></i>
                            <span>Accedi</span>
                        </a>
                    <?php endif; ?>

                    <button class="home-btn home-btn--ghost" type="button" onclick="if(window.newsPopup) window.newsPopup.open();">
                        <i class="fa-solid fa-layer-group"></i>
                        <span>Novità</span>
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
                <span>Felicità</span>
            </article>
            <article class="home-mood-item">
                <img src="../img/tristezza.jpg" alt="Tristezza" loading="lazy">
                <span>Tristezza</span>
            </article>
            <article class="home-mood-item">
                <img src="../img/stupore.jpg" alt="Stupore" loading="lazy">
                <span>Stupore</span>
            </article>
        </section>

        <section id="featuredContent" class="home-feature home-reveal">
            <div class="home-section-head">
                <div>
                    <h2>Cosa puoi fare su Cripsum™</h2>
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

        <!-- PREMIUM AD BLOCK & SUPPORTERS (ITALIAN) -->
        <?php if (!$isPremium): ?>
            <section class="home-premium-promo-card home-reveal">
                <div class="promo-copy">
                    <span class="promo-tag">Cripsum™ Premium</span>
                    <h3>Sblocca l'esperienza Cripsum™ definitiva</h3>
                    <p>Ottieni vantaggi esclusivi, raddoppia i tuoi punti e supporta la community.</p>
                    <div class="promo-benefits">
                        <div class="benefit-item"><i class="fa-solid fa-gem"></i><span>200k punti subito all'acquisto</span></div>
                        <div class="benefit-item"><i class="fa-solid fa-gem"></i><span>Sblocco della personalizzazione premium nei profili</span></div>
                        <div class="benefit-item"><i class="fa-solid fa-gem"></i><span>Riscatto giornaliero di 500 punti Lootbox</span></div>
                        <div class="benefit-item"><i class="fa-solid fa-gem"></i><span>Doppio boost (2x) sui punti delle missioni</span></div>
                        <div class="benefit-item"><i class="fa-solid fa-gem"></i><span>Tag premium con diamante vicino al tuo nome</span></div>
                        <div class="benefit-item"><i class="fa-solid fa-gem"></i><span>Nome in evidenza nella lista sostenitori</span></div>
                    </div>
                    <div class="promo-actions">
                        <a href="checkout-premium" class="promo-btn-primary">
                            <i class="fa-solid fa-cart-shopping"></i>
                            <span>Diventa Premium</span>
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
                        <h2>I nostri Supporter Premium</h2>
                        <p>Un grazie speciale agli utenti che supportano Cripsum™!</p>
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
                <h2>Seguimi sui social</h2>
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
                <span>Clicca qui per V-bucks gratis!!!!</span>
            </a>
        </section>

        <?php if (!$isLoggedIn): ?>
            <section class="home-account home-reveal">
                <div>
                    <span class="home-kicker">Account</span>
                    <h2>Hai un account Cripsum™?</h2>
                    <p>Con l’account usi profilo, chat, lootbox e achievement.</p>
                </div>

                <div class="home-account__actions">
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
                    <h5 class="modal-title" id="disclaimerModalLabel">Disclaimer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body">
                    <p>Cripsum™ è un sito personale fatto per intrattenere. Alcune pagine usano meme, ironia e riferimenti interni.</p>
                    <ul>
                        <li>Non prendere tutto come contenuto serio.</li>
                        <li>Shop e checkout, se presenti, sono simulati.</li>
                        <li>Le donazioni sono reali: dona solo se vuoi davvero farlo.</li>
                    </ul>
                    <p class="home-muted">
                        Parte del codice è pubblico su
                        <a href="https://github.com/cripsum/cripsum.github.io" target="_blank" rel="noopener">GitHub</a>.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="home-btn home-btn--ghost" data-bs-dismiss="modal">Chiudi</button>
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

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <?php if (!empty($supporters)): ?>
        <script src="/js/home-supporters.js?v=1.6" defer></script>
    <?php endif; ?>
</body>

</html>