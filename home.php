<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (function_exists('checkBan') && isset($mysqli)) {
    checkBan($mysqli);
}

if (!function_exists('home_e')) {
    function home_e($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$isLoggedIn = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? 'utente';
?>

<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title data-i18n="meta.title">Cripsum™</title>
    <meta name="description" content="Cripsum™: meme, edit, lootbox, GoonLand e roba strana fatta con cura." data-i18n-attr="content|meta.desc">
    <meta property="og:title" content="Cripsum™">
    <meta property="og:description" content="Meme, edit, lootbox, GoonLand e robe strane. Tutto in un solo posto.">
    <meta property="og:image" content="/img/Susremaster.png">
    <meta property="og:type" content="website">
    <link rel="preload" as="image" href="/img/amongus.jpg">
    <link rel="stylesheet" href="/css/home-v2.css?v=1">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1527058839538660" crossorigin="anonymous"></script>
</head>

<body>
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <main class="testobianco paginaprincipale home-v2">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="home-alert home-alert-error fadeup" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?php echo home_e($_SESSION['error_message']); ?></span>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="home-alert home-alert-success fadeup" role="alert">
                <i class="bi bi-check-circle-fill"></i>
                <span><?php echo home_e($_SESSION['success_message']); ?></span>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <section class="home-disclaimer fadeup" aria-label="Disclaimer sito">
            <button class="home-disclaimer-btn" type="button" data-bs-toggle="modal" data-bs-target="#disclaimerModal">
                <i class="bi bi-info-circle"></i>
                <span>Disclaimer rapido</span>
            </button>
        </section>

        <div class="modal fade" id="disclaimerModal" tabindex="-1" aria-labelledby="disclaimerModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content bgdisclaimer home-modal">
                    <div class="modal-header">
                        <h5 class="modal-title" id="disclaimerModalLabel">Disclaimer</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                    </div>
                    <div class="modal-body">
                        <p>Cripsum™ è un sito fatto per intrattenere. Alcune pagine usano ironia, meme e contenuti volutamente stupidi.</p>
                        <ul>
                            <li>I contenuti non vogliono offendere nessuno.</li>
                            <li>I download sono pensati come contenuti meme. Scarica sempre solo dal sito ufficiale.</li>
                            <li>Shop e checkout, quando presenti, sono fittizi se indicato nella pagina.</li>
                            <li>Le donazioni sono reali, ma non sono necessarie.</li>
                        </ul>
                        <p class="home-modal-note">Per trasparenza, il codice è pubblico su <a href="https://github.com/cripsum/cripsum.github.io" class="linkbianco" target="_blank" rel="noopener">GitHub</a>.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="home-btn home-btn-muted" data-bs-dismiss="modal">Ho capito</button>
                    </div>
                </div>
            </div>
        </div>

        <section class="home-hero fadeup">
            <div class="home-hero-media">
                <div class="home-hero-glow"></div>
                <img class="home-hero-img" src="../img/amongus.jpg" alt="Cripsum Hero" loading="eager" data-fallback="/img/Susremaster.png">
            </div>

            <div class="home-hero-content">
                <div class="home-kicker">Cripsum™ / GoonLand</div>
                <h1>Il sito più inutile che aveva comunque bisogno di una home bella.</h1>
                <p class="home-hero-text">Meme, edit, lootbox, chat, pagine strane e qualche esperimento. Tutto con lo stile giusto, senza prendersi troppo sul serio.</p>

                <div class="home-hero-actions">
                    <a href="goonland/home" class="home-btn home-btn-primary">Entra in GoonLand</a>
                    <?php if ($isLoggedIn): ?>
                        <a href="lootbox" class="home-btn home-btn-secondary">Apri lootbox</a>
                    <?php else: ?>
                        <a href="accedi" class="home-btn home-btn-secondary">Accedi</a>
                    <?php endif; ?>
                </div>

                <div class="home-hero-note">
                    <span class="home-dot"></span>
                    <span>Homepage, non dashboard. Solo le robe principali.</span>
                </div>
            </div>
        </section>

        <section class="home-mood-section fadeup" aria-labelledby="moodTitle">
            <div class="home-section-head">
                <p class="home-section-kicker">Mood del sito</p>
                <h2 id="moodTitle">Tre stati mentali, nessuna spiegazione utile.</h2>
            </div>

            <div class="home-mood-grid">
                <article class="home-mood-card">
                    <img src="../img/felicita.jpg" alt="Felicità" loading="lazy" data-fallback="/img/Susremaster.png">
                    <h3>Felicità</h3>
                </article>
                <article class="home-mood-card">
                    <img src="../img/tristezza.jpg" alt="Tristezza" loading="lazy" data-fallback="/img/Susremaster.png">
                    <h3>Tristezza</h3>
                </article>
                <article class="home-mood-card">
                    <img src="../img/stupore.jpg" alt="Stupore" loading="lazy" data-fallback="/img/Susremaster.png">
                    <h3>Stupore</h3>
                </article>
            </div>
        </section>

        <section class="home-featured fadeup" aria-labelledby="featuredTitle">
            <div class="home-section-head home-section-head-row">
                <div>
                    <p class="home-section-kicker">In evidenza</p>
                    <h2 id="featuredTitle">Da dove vuoi iniziare?</h2>
                </div>
                <p class="home-section-desc">Poche sezioni, quelle che servono davvero.</p>
            </div>

            <div id="content-slider" class="content-slider" aria-label="Contenuti in evidenza">
                <button class="slider-arrow slider-arrow-left" type="button" aria-label="Slide precedente" data-slider-prev>
                    <i class="bi bi-chevron-left"></i>
                </button>

                <div class="slider-wrapper" id="sliderWrapper"></div>

                <button class="slider-arrow slider-arrow-right" type="button" aria-label="Slide successiva" data-slider-next>
                    <i class="bi bi-chevron-right"></i>
                </button>

                <div class="slider-dots" id="sliderDots" aria-label="Seleziona slide"></div>
            </div>
        </section>

        <section class="home-links fadeup" aria-labelledby="linksTitle">
            <div class="home-section-head">
                <p class="home-section-kicker">Link rapidi</p>
                <h2 id="linksTitle">Le zone principali</h2>
            </div>

            <div class="home-links-grid">
                <a class="home-link-card" href="lootbox">
                    <span class="home-link-icon">📦</span>
                    <span>
                        <strong>Lootbox</strong>
                        <small>Apri casse e trova personaggi.</small>
                    </span>
                </a>

                <a class="home-link-card" href="achievements">
                    <span class="home-link-icon">🏆</span>
                    <span>
                        <strong>Achievements</strong>
                        <small>Sblocca badge e obiettivi.</small>
                    </span>
                </a>

                <a class="home-link-card" href="edits">
                    <span class="home-link-icon">🎬</span>
                    <span>
                        <strong>Edit</strong>
                        <small>Video, montaggi e roba mia.</small>
                    </span>
                </a>

                <a class="home-link-card" href="global-chat">
                    <span class="home-link-icon">💬</span>
                    <span>
                        <strong>Chat globale</strong>
                        <small>Parla con gli altri utenti.</small>
                    </span>
                </a>
            </div>
        </section>

        <section class="home-social fadeup" aria-labelledby="socialTitle">
            <div class="home-section-head home-section-head-center">
                <p class="home-section-kicker">Social</p>
                <h2 id="socialTitle">Seguimi anche fuori dal sito</h2>
            </div>

            <div class="home-social-grid">
                <a href="https://www.tiktok.com/@cripsum" class="home-social-link" target="_blank" rel="noopener" aria-label="TikTok Cripsum">
                    <i class="fab fa-tiktok"></i>
                    <span>TikTok</span>
                </a>
                <a href="https://www.instagram.com/cripsum/" class="home-social-link" target="_blank" rel="noopener" aria-label="Instagram Cripsum">
                    <i class="fab fa-instagram"></i>
                    <span>Instagram</span>
                </a>
                <a href="https://discord.gg/XdheJHVURw" class="home-social-link" target="_blank" rel="noopener" aria-label="Discord Cripsum">
                    <i class="fab fa-discord"></i>
                    <span>Discord</span>
                </a>
                <a href="https://t.me/cripsum" class="home-social-link" target="_blank" rel="noopener" aria-label="Telegram Cripsum">
                    <i class="fab fa-telegram-plane"></i>
                    <span>Telegram</span>
                </a>
            </div>
        </section>

        <section class="home-account fadeup" aria-label="Account Cripsum">
            <?php if ($isLoggedIn): ?>
                <div>
                    <p class="home-section-kicker">Bentornato</p>
                    <h2><?php echo home_e($username); ?>, sei dentro.</h2>
                    <p>Continua da lootbox, achievement o profilo. Niente pannello gigante, tranquillo.</p>
                </div>
                <div class="home-account-actions">
                    <a href="profilo" class="home-btn home-btn-primary">Vai al profilo</a>
                    <a href="achievements" class="home-btn home-btn-muted">Achievement</a>
                </div>
            <?php else: ?>
                <div>
                    <p class="home-section-kicker">Account</p>
                    <h2>Accedi per salvare progressi e badge.</h2>
                    <p>Ti serve per lootbox, achievement, profilo e funzioni collegate.</p>
                </div>
                <div class="home-account-actions">
                    <a href="accedi" class="home-btn home-btn-primary">Accedi</a>
                    <a href="registrati" class="home-btn home-btn-muted">Registrati</a>
                </div>
            <?php endif; ?>
        </section>

        <section class="home-easter fadeup" aria-label="Easter egg">
            <a href="https://youtu.be/xvFZjo5PgG0?si=uPsap7ILF_8aYheh" class="home-easter-link" target="_blank" rel="noopener" data-achievement-id="10">
                V-bucks gratis, ma più onesto di prima
            </a>
        </section>
    </main>

    <div id="achievement-popup" class="popup" aria-live="polite">
        <img id="popup-image" src="" alt="Achievement">
        <div>
            <h3 id="popup-title"></h3>
            <p id="popup-description"></p>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script src="/js/slider.js?v=6"></script>
    <script src="/js/home-v2.js?v=1"></script>
</body>

</html>
