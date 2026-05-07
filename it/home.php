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

function home_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$profileUrl = ($isLoggedIn && $currentUsername)
    ? '/u/' . rawurlencode(strtolower((string)$currentUsername))
    : 'accedi';

$ogDescription = 'Homepage di Cripsum™. Edit, meme, lootbox, profili e GoonLand.';
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
    <link rel="stylesheet" href="/assets/home-v5/home.css?v=5.6">
    <script src="/assets/home-v5/home.js?v=5.5" defer></script>

    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1527058839538660" crossorigin="anonymous"></script>
</head>

<body class="home-v5-body">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="home-bg" aria-hidden="true">
        <span class="home-noise"></span>
        <span class="home-orb home-orb--one"></span>
        <span class="home-orb home-orb--two"></span>
        <span class="home-grid"></span>
    </div>

    <main class="home-page">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="home-alert" role="alert">
                <i class="fas fa-triangle-exclamation"></i>
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

                    <button class="home-btn home-btn--plain" type="button" data-bs-toggle="modal" data-bs-target="#disclaimerModal">
                        <i class="fas fa-circle-info"></i>
                        <span>Disclaimer</span>
                    </button>
                </div>
            </div>

            <div class="home-hero__art" aria-hidden="true">
                <div class="home-hero__glow"></div>
                <img src="../img/amongus.jpg" alt="">
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
                <p>Una preview delle pagine principali.</p>
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

        <section class="home-social-section home-reveal">
            <div class="home-section-head home-section-head--center">
                <h2>Seguimi sui social</h2>
            </div>

            <div class="social-icons-modern">
                <a href="https://www.tiktok.com/@cripsum" class="social-link-modern tiktok" title="TikTok" target="_blank" rel="noopener">
                    <div class="social-icon-wrapper">
                        <i class="fab fa-tiktok"></i>
                        <span class="social-label">TikTok</span>
                    </div>
                </a>
                <a href="https://www.instagram.com/cripsum/" class="social-link-modern instagram" title="Instagram" target="_blank" rel="noopener">
                    <div class="social-icon-wrapper">
                        <i class="fab fa-instagram"></i>
                        <span class="social-label">Instagram</span>
                    </div>
                </a>
                <a href="https://discord.gg/XdheJHVURw" class="social-link-modern discord" title="Discord" target="_blank" rel="noopener">
                    <div class="social-icon-wrapper">
                        <i class="fab fa-discord"></i>
                        <span class="social-label">Discord</span>
                    </div>
                </a>
                <a href="https://t.me/cripsum" class="social-link-modern telegram" title="Telegram" target="_blank" rel="noopener">
                    <div class="social-icon-wrapper">
                        <i class="fab fa-telegram-plane"></i>
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
                <i class="fas fa-gift"></i>
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
</body>

</html>