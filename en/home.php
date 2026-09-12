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
$onlineCount = 0;

/** Oltre questo silenzio non si e' piu' "online adesso". */
const HOME_ONLINE_WINDOW_MINUTES = 5;

/** Tante facce bastano: la fila e' decorativa, non un elenco da consultare. */
const HOME_SUPPORTERS_LIMIT = 40;

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

    require_once __DIR__ . '/../includes/account_data_helpers.php';
    // Deactivated accounts (deletion pending) drop out of the list.
    $suppActive = account_active_sql($mysqli);
    $suppActiveClause = $suppActive !== '' ? ' AND ' . $suppActive : '';
    $stmtSupp = $mysqli->prepare("SELECT id, username, display_name, discord_use_display_name, discord_global_name, discord_username, profile_updated_at, accent_color, avatar_ring_color FROM utenti WHERE is_premium = 1 $suppActiveClause ORDER BY id DESC LIMIT " . HOME_SUPPORTERS_LIMIT);
    if ($stmtSupp) {
        $stmtSupp->execute();
        $resSupp = $stmtSupp->get_result();
        while ($row = $resSupp->fetch_assoc()) {
            $supporters[] = $row;
        }
        $stmtSupp->close();
    }

    /**
     * Quante persone stanno usando il sito adesso.
     *
     * `ultimo_accesso` lo aggiorna activity-beat.js, che conta solo il tempo a
     * scheda davvero visibile: e' un numero onesto, non "quanti hanno aperto
     * una pagina oggi". Se la colonna non c'e' resta zero e la riga non viene
     * nemmeno stampata.
     */
    if (function_exists('auth_column_exists') && auth_column_exists($mysqli, 'utenti', 'ultimo_accesso')) {
        $stmtOnline = $mysqli->prepare(
            'SELECT COUNT(*) AS totale FROM utenti
             WHERE ultimo_accesso >= DATE_SUB(NOW(), INTERVAL ? MINUTE)' . $suppActiveClause
        );

        if ($stmtOnline) {
            $window = HOME_ONLINE_WINDOW_MINUTES;
            $stmtOnline->bind_param('i', $window);
            $stmtOnline->execute();
            $onlineCount = (int)($stmtOnline->get_result()->fetch_assoc()['totale'] ?? 0);
            $stmtOnline->close();
        }
    }
}

require_once __DIR__ . '/../includes/home_slides.php';
$homeSlides = home_slides_load($mysqli ?? null, 'en');

function home_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$profileUrl = ($isLoggedIn && $currentUsername)
    ? '/u/' . rawurlencode(strtolower((string)$currentUsername))
    : 'accedi';

$ogDescription = 'Cripsum™ Homepage. Edits, memes, gambling, custom profiles, lots of games and plenty of gooning.';
$ogTitle = 'Cripsum™ — memes, edits, lootboxes and community profiles';
$ogUrl = 'https://cripsum.com' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/en/home'), '#');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <?php /* "Cripsum™" da solo non dice niente a chi ci arriva da una ricerca.
             Il nome resta davanti, il resto spiega cos'e' senza cambiare tono. */ ?>
    <title data-i18n="meta.title">Cripsum™ — memes, edits, lootboxes and community profiles</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <link rel="preload" as="image" href="../img/amongus.jpg">
    <link rel="stylesheet" href="/assets/home-v5/home.css?v=7.0">
    <link rel="stylesheet" href="/assets/news/news-popup.css?v=1.0">
    <script src="/assets/home-v5/home.js?v=6.1" defer></script>
    <script src="/assets/news/news-popup.js?v=1.1" defer></script>

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
                <h1>Welcome to the best site in the Congo.</h1>
                <p>Editing, memes, lootboxes, profiles, achievements, community posts and many secrets. What are you waiting for? Join us!</p>
                <p class="home-question">Are you over 25 and own a PC?</p>

                <?php if ($onlineCount > 0): ?>
                    <p class="home-online">
                        <span class="home-online__dot" aria-hidden="true"></span>
                        <strong><?php echo home_h(number_format($onlineCount)); ?></strong>
                        <?php echo $onlineCount === 1 ? 'person around right now' : 'people around right now'; ?>
                    </p>
                <?php endif; ?>

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

            <?php if ($homeSlides): ?>
                <?php /* Le slide arrivano dentro il documento invece che con una
                         chiamata a parte: niente richiesta in piu' e niente
                         sezione che si riempie dopo. JSON_HEX_TAG chiude la
                         porta a un `</script>` dentro un testo salvato dal
                         pannello. */ ?>
                <script type="application/json" id="homeSlidesData"><?php
                    echo json_encode($homeSlides, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                ?></script>
            <?php endif; ?>

            <div class="home-slider" id="homeSlider">
                <div class="home-slider__backdrop" id="homeSliderBackdrop" aria-hidden="true"></div>

                <div class="home-slider__stage" id="homeSliderStage" aria-live="polite"></div>

                <?php /* Le frecce erano gia' disegnate nel CSS ma non esistevano
                         nell'HTML: lo slider si poteva muovere solo trascinando
                         o dalle linguette, e da tastiera per niente. */ ?>
                <div class="home-slider__controls">
                    <button type="button" class="home-slider__arrow" id="homeSliderPrev" aria-label="Previous content">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>

                    <div class="home-slider__progress">
                        <span id="homeSliderProgress"></span>
                    </div>

                    <button type="button" class="home-slider__arrow" id="homeSliderPause" aria-pressed="false" aria-label="Pause">
                        <i class="fa-solid fa-pause"></i>
                    </button>

                    <button type="button" class="home-slider__arrow" id="homeSliderNext" aria-label="Next content">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>

                <div class="home-slider__tabs" id="homeSliderTabs" role="tablist" aria-label="Select content"></div>
            </div>
        </section>

        <?php
        /**
         * Lo scherzo dei V-bucks cambia posto a seconda di chi sta guardando.
         *
         * Per chi non ha l'account la pagina deve chiudersi con l'invito a
         * iscriversi, quindi la battuta sta prima; per chi e' gia' dentro
         * l'invito non c'e' e la battuta puo' stare in fondo. Sta in una
         * funzione per non ritrovarsi lo stesso pezzo di markup scritto due
         * volte e poi modificato una sola.
         */
        $sezioneScherzo = static function (): void { ?>
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
        <?php };

        // Chi non ha ancora un account non sa cosa sia un Godo: la scheda
        // Premium arriva dopo che ha visto cosa c'e' sul sito.
        if (!$isLoggedIn) {
            $sezioneScherzo();
        }
        ?>

        <!-- PREMIUM AD BLOCK & SUPPORTERS (ENGLISH) -->
        <?php if (!$isPremium): ?>
            <section class="home-premium-promo-card home-reveal">
                <div class="promo-copy">
                    <span class="promo-tag">Cripsum™ Premium</span>
                    <h3>Unlock the Ultimate Cripsum™ Experience</h3>
                    <p>Get premium perks, double your rewards, and show off your support to the community.</p>
                    <div class="promo-benefits">
                        <div class="benefit-item"><i class="fa-solid fa-gem"></i><span>25.000 Godos instantly upon purchase</span></div>
                        <div class="benefit-item"><i class="fa-solid fa-gem"></i><span>Unlock premium profile customization</span></div>
                        <div class="benefit-item"><i class="fa-solid fa-gem"></i><span>Daily claim of 500 Godos in Lootbox</span></div>
                        <div class="benefit-item"><i class="fa-solid fa-gem"></i><span>Double Godos (2x) on Daily & Weekly missions</span></div>
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
                                    <img src="/includes/get_pfp.php?id=<?= (int)$s['id'] ?>&amp;t=<?= $stamp ?>&amp;size=96" alt="" class="supporter-pfp" width="48" height="48" decoding="async">
                                    <div class="supporter-badge"><i class="fa-solid fa-gem"></i></div>
                                </div>
                                <span class="supporter-name"><?= htmlspecialchars($dispName) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php
        /* La sezione "My socials" e' stata tolta: TikTok, Instagram e Telegram
           mandavano via la gente dalla homepage e stanno gia' nel footer. Il
           Discord invece resta, perche' e' dove la community vive davvero, ma
           spostato nel riquadro finale dove serve a qualcosa. */

        if ($isLoggedIn) {
            $sezioneScherzo();
        }
        ?>

        <?php if (!$isLoggedIn): ?>
            <section class="home-account home-reveal">
                <div>
                    <h2>Got a Cripsum™ account?</h2>
                    <p>Your account unlocks your profile, chat, lootboxes, and achievements.</p>
                </div>

                <div class="home-account__actions">
                    <a href="https://discord.gg/XdheJHVURw" class="home-btn home-btn--ghost home-btn--discord"
                        data-discord-guild="1275495488229081108" target="_blank" rel="noopener">
                        <i class="fa-brands fa-discord"></i>
                        <span>Discord</span>
                        <span class="home-discord-count" data-discord-count hidden></span>
                    </a>
                    <a href="accedi" class="home-btn home-btn--ghost">Log in</a>
                    <a href="registrati" class="home-btn home-btn--primary">Sign up</a>
                </div>
            </section>
        <?php endif; ?>
    </main>



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