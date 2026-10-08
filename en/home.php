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
$supportersTotal = 0;
$onlineCount = 0;

/** Riga di chi sta guardando: serve al suo posto fisso tra i supporter e al riscatto giornaliero. */
$viewerRow = [];
$premiumClaimedToday = false;
$premiumClaimLeft = 0;

/** Oltre questo silenzio non si e' piu' "online adesso". */
const HOME_ONLINE_WINDOW_MINUTES = 5;

/** Tante facce bastano: la fila e' decorativa, non un elenco da consultare. */
const HOME_SUPPORTERS_LIMIT = 40;

if (isset($mysqli) && $mysqli instanceof mysqli) {
    if ($isLoggedIn && isset($_SESSION['user_id'])) {
        $stmtPrem = $mysqli->prepare("SELECT is_premium, last_premium_claim, accent_color, profile_updated_at FROM utenti WHERE id = ? LIMIT 1");
        if ($stmtPrem) {
            $stmtPrem->bind_param('i', $_SESSION['user_id']);
            $stmtPrem->execute();
            $viewerRow = $stmtPrem->get_result()->fetch_assoc() ?: [];
            $isPremium = ((int)($viewerRow['is_premium'] ?? 0) === 1);
            $stmtPrem->close();
        }
    }

    if ($isPremium) {
        // Stesso "oggi" di api/premium_daily_claim.php: il pulsante della home
        // e l'API devono essere d'accordo su quando il riscatto e' gia' fatto.
        require_once __DIR__ . '/../includes/mission_generator.php';
        $premiumClaimedToday = (($viewerRow['last_premium_claim'] ?? null) === getMissionDailyPeriod());
        $premiumClaimLeft = max(0, strtotime('tomorrow') - time());
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

    // La fila si ferma a HOME_SUPPORTERS_LIMIT facce, il conteggio no.
    $supportersTotal = count($supporters);
    $stmtSuppCount = $mysqli->prepare("SELECT COUNT(*) AS totale FROM utenti WHERE is_premium = 1 $suppActiveClause");
    if ($stmtSuppCount) {
        $stmtSuppCount->execute();
        $supportersTotal = max($supportersTotal, (int)($stmtSuppCount->get_result()->fetch_assoc()['totale'] ?? 0));
        $stmtSuppCount->close();
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

// Tema nuovo (sperimentale): stessi dati, altra pagina. Si accende dalle
// impostazioni; chi non l'ha acceso prosegue qui sotto con la home di sempre.
require_once __DIR__ . '/../includes/theme.php';
if (cripsum_theme_is_next()) {
    $homeLang = 'en';
    require __DIR__ . '/../includes/home_next.php';
    return;
}
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
    <?php /* head-import carica solo Poppins 400: senza questi pesi ogni
             grassetto della home era simulato dal browser. */ ?>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,500;0,600;1,600&amp;display=swap">
    <link rel="stylesheet" href="/assets/home-v5/home.css?v=7.3">
    <link rel="stylesheet" href="/assets/news/news-popup.css?v=1.0">
    <script src="/assets/home-v5/home.js?v=6.4" defer></script>
    <script src="/assets/news/news-popup.js?v=1.1" defer></script>

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
                        <?php if ($isPremium): ?>
                            <?php /* Il riscatto giornaliero sta qui e non piu' solo in
                                     Lootbox: e' il motivo per cui un Premium torna ogni
                                     giorno. Etichette e conto alla rovescia li gestisce
                                     home.js. */ ?>
                            <button class="home-btn <?php echo $premiumClaimedToday ? 'is-claimed' : 'home-btn--premium'; ?>"
                                type="button"
                                data-premium-claim
                                data-seconds-left="<?php echo (int)$premiumClaimLeft; ?>"
                                <?php echo $premiumClaimedToday ? 'disabled' : ''; ?>>
                                <?php if ($premiumClaimedToday): ?>
                                    <i class="fa-solid fa-check"></i>
                                    <span><strong>Claimed</strong> · again in <span data-claim-countdown>--:--:--</span></span>
                                <?php else: ?>
                                    <img class="home-btn__coin" src="/img/godos-icon.png" alt="" width="20" height="20">
                                    <span>Claim 500 Godos</span>
                                <?php endif; ?>
                            </button>
                        <?php endif; ?>
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
            <section class="home-premium-promo-card home-reveal" aria-labelledby="homePremiumTitle">
                <div class="promo-main">
                    <h2 id="homePremiumTitle">Unlock the Ultimate Cripsum™ Experience</h2>
                    <p class="promo-lead">Get premium perks, double your rewards, and show off your support to the community.</p>
                    <div class="promo-perks">
                        <ul>
                            <li><img class="benefit-currency" src="/img/godos.png" alt="" width="22" height="22"><span><strong>25.000 Godos</strong> instantly upon purchase</span></li>
                            <li><img class="benefit-currency" src="/img/godos.png" alt="" width="22" height="22"><span>Daily claim of <strong>500 Godos</strong> in Lootbox</span></li>
                            <li><img class="benefit-currency" src="/img/godos.png" alt="" width="22" height="22"><span><strong>Double Godos (2x)</strong> on Daily &amp; Weekly missions</span></li>
                        </ul>
                        <ul>
                            <li><img class="cr-premium-gem" src="/img/premium.svg" alt="" width="22" height="22"><span>Unlock <strong>premium profile customization</strong></span></li>
                            <li><img class="cr-premium-gem" src="/img/premium.svg" alt="" width="22" height="22"><span><strong>Cripsum Rewind any day</strong>, not just one week a year</span></li>
                            <li><img class="cr-premium-gem" src="/img/premium.svg" alt="" width="22" height="22"><span>Exclusive <strong>premium gem tag</strong> next to your name</span></li>
                            <li><img class="cr-premium-gem" src="/img/premium.svg" alt="" width="22" height="22"><span><strong>Featured</strong> in the homepage Supporters list</span></li>
                        </ul>
                    </div>
                </div>
                <?php /* Prezzo e condizioni sono quelli di checkout-premium: se
                         cambiano li' vanno cambiati anche qui. */ ?>
                <div class="promo-offer">
                    <img class="cr-premium-gem promo-offer__gem" src="/img/premium.svg" alt="" width="92" height="92">
                    <p class="promo-offer__name">Cripsum™ Premium</p>
                    <p class="promo-offer__price">€2.99</p>
                    <p class="promo-offer__terms">One-time, no subscription</p>
                    <a href="checkout-premium" class="home-btn home-btn--premium promo-offer__cta">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span>Get Premium</span>
                    </a>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($supporters)): ?>
            <section class="home-supporters-section home-reveal" aria-labelledby="homeSupportersTitle">
                <div class="home-supporters-title">
                    <div>
                        <h2 id="homeSupportersTitle">Our Premium Supporters</h2>
                        <p>A big thanks to the users who support Cripsum™!</p>
                    </div>
                    <p class="supporters-count">
                        <img class="cr-premium-gem" src="/img/premium.svg" alt="" width="14" height="14">
                        <span><strong><?= home_h(number_format($supportersTotal)) ?></strong> <?= $supportersTotal === 1 ? 'supporter' : 'supporters' ?></span>
                    </p>
                </div>
                <div class="supporters-row">
                    <?php /* Il posto di chi guarda sta fuori dalla fila che scorre: chi
                             e' Premium ci trova se stesso, gli altri un posto vuoto
                             che porta al checkout. */ ?>
                    <div class="supporters-you">
                        <?php if ($isPremium):
                            $viewerStamp = !empty($viewerRow['profile_updated_at']) ? strtotime((string)$viewerRow['profile_updated_at']) : time();
                            $viewerColor = !empty($viewerRow['accent_color']) ? $viewerRow['accent_color'] : '#db2777';
                        ?>
                            <a href="<?= home_h($profileUrl) ?>" class="supporter-card supporter-card--me" style="--supporter-color: <?= home_h($viewerColor) ?>;">
                                <span class="supporter-avatar-container">
                                    <img src="/includes/get_pfp.php?id=<?= (int)$_SESSION['user_id'] ?>&amp;t=<?= (int)$viewerStamp ?>&amp;size=96" alt="" class="supporter-pfp" width="64" height="64" decoding="async">
                                </span>
                                <span class="supporter-name">You</span>
                            </a>
                        <?php else: ?>
                            <a href="checkout-premium" class="supporter-card supporter-card--slot" aria-label="Your spot: get Premium">
                                <span class="supporter-avatar-container"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
                                <span class="supporter-name">Your spot</span>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="supporters-scroll-wrapper">
                        <div class="supporters-grid">
                            <?php foreach ($supporters as $s):
                                // Chi guarda da Premium ha gia' il suo posto fisso qui accanto.
                                if ($isPremium && (int)$s['id'] === (int)$_SESSION['user_id']) {
                                    continue;
                                }

                                $useDiscord = (int)($s['discord_use_display_name'] ?? 0) === 1;
                                $discord = trim((string)($s['discord_global_name'] ?? '')) ?: trim((string)($s['discord_username'] ?? ''));
                                $dispName = ($useDiscord && $discord !== '') ? $discord : (trim((string)($s['display_name'] ?? '')) ?: $s['username']);
                                $stamp = !empty($s['profile_updated_at']) ? strtotime((string)$s['profile_updated_at']) : time();

                                $suppColor = !empty($s['accent_color']) ? $s['accent_color'] : '#db2777';
                            ?>
                                <a href="/u/<?= rawurlencode(strtolower($s['username'])) ?>" class="supporter-card" title="<?= htmlspecialchars($dispName) ?>" style="--supporter-color: <?= htmlspecialchars($suppColor) ?>;">
                                    <span class="supporter-avatar-container">
                                        <img src="/includes/get_pfp.php?id=<?= (int)$s['id'] ?>&amp;t=<?= $stamp ?>&amp;size=96" alt="" class="supporter-pfp" width="64" height="64" decoding="async">
                                    </span>
                                    <span class="supporter-name"><?= htmlspecialchars($dispName) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
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
        <script src="/js/home-supporters.js?v=2.0" defer></script>
    <?php endif; ?>
</body>

</html>