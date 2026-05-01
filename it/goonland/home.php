<?php
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
checkBan($mysqli);

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$bot = preg_match('/(facebookexternalhit|Facebot|Discordbot|Twitterbot|TelegramBot|WhatsApp)/i', $ua);

if ($bot) {
    echo '
    <html><head>
      <meta property="og:title" content="Cripsum™ GoonLand - Home">
      <meta property="og:description" content="GoonLand è una sezione sperimentale rosa del sito: giochi, contenuti e piccole robe interattive.">
      <meta property="og:image" content="https://cripsum.com/img/raspberry-chan16gb.png">
      <meta property="og:url" content="https://cripsum.com/it/goonland/home">
      <meta property="og:type" content="website">
    </head><body></body></html>';
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
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta property="og:title" content="Cripsum™ GoonLand - Home">
    <meta property="og:description" content="GoonLand è una sezione sperimentale rosa del sito: giochi, contenuti e piccole robe interattive.">
    <meta property="og:image" content="https://cripsum.com/img/raspberry-chan16gb.png">
    <meta property="og:url" content="https://cripsum.com/it/goonland/home">
    <meta property="og:type" content="website">
    <?php include '../../includes/head-import.php'; ?>
    <title>GoonLand™ - Home</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/css/goonland.css?v=2.2-dropdown-footer">
    <script src="/js/goonland.js?v=2.2-dropdown-footer" defer></script>
</head>

<body class="goonland-page" data-goonland-page="home">
    <?php include '../../includes/navbar-goonland.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>

    <div class="gl-bg" aria-hidden="true"><span></span><span></span></div>


    <main class="gl-shell gl-home-shell">
        <section class="gl-hero gl-home-hero gl-reveal">
            <div class="gl-hero-text">
                <span class="gl-kicker"><i class="fas fa-heart"></i> GoonLand</span>
                <h1>GoonLand</h1>
                <p>Un progetto ideato da Zakator e Cripsum: uno spazio digitale provocatorio, giocoso e visivamente coinvolgente.</p>

                <div class="gl-actions">
                    <a class="gl-btn gl-btn-main" href="/it/goonland/goon-generator">
                        <i class="fas fa-wand-magic-sparkles"></i> Apri il generator
                    </a>
                    <a class="gl-btn gl-btn-main" href="/it/goonland/anime-girl-quiz">
                        <i class="fas fa-heart-pulse"></i> Anime Girl Quiz
                    </a>
                    <a class="gl-btn gl-btn-main" href="/it/goonland/smash-or-pass">
                        <i class="fas fa-fire"></i> Smash or Pass
                    </a>
                    <a class="gl-btn gl-btn-ghost" href="/it/home">
                        <i class="fas fa-arrow-left"></i> Torna al sito
                    </a>
                </div>
            </div>

            <div class="gl-hero-media gl-home-visual">
                <img src="/img/raspberry-chan16gb.png" alt="GoonLand visual" loading="eager" data-gl-fallback>

            </div>
        </section>

        <section class="gl-lore-stack">
            <article class="gl-lore-card gl-reveal">
                <span class="gl-kicker">Cos’è GoonLand?</span>
                <h2>Un piccolo universo digitale.</h2>
                <p>GoonLand è un progetto ideato da Zakator e Cripsum: uno spazio digitale unico nel suo genere, nato dalla volontà di creare un ambiente che fosse al tempo stesso provocatorio, giocoso e visivamente coinvolgente.</p>
                <p>Non si tratta solo di un sito, ma di un piccolo universo costruito per intrattenere, far riflettere e talvolta confondere in modo creativo e fuori dagli schemi.</p>
            </article>

            <article class="gl-lore-card gl-lore-card--wide gl-reveal">
                <span class="gl-kicker">Cosa trovi dentro</span>
                <h2>Giochi, esperimenti e contenuti a tema.</h2>
                <p>All’interno di GoonLand troverai una raccolta di giochi interattivi, esperienze sperimentali e contenuti a tema, tutti sviluppati per incarnare l’estetica visionaria e spesso surreale del progetto.</p>
                <p>Ogni elemento del sito è pensato per immergere l’utente in un viaggio digitale dove nulla è davvero come sembra, e dove l’ironia si mescola con una sottile critica alla cultura dell’intrattenimento online.</p>
            </article>

            <article class="gl-lore-card gl-reveal">
                <span class="gl-kicker">Cos’è il gooning?</span>
                <h2>Una metafora dell’era digitale.</h2>
                <p>Il termine “gooning” nasce negli angoli più assurdi di Internet, dove viene usato per descrivere uno stato mentale ipnotico, quasi trance, indotto dalla ripetizione ossessiva di stimoli sensoriali, immagini, suoni o contenuti digitali.</p>
                <p>In chiave ironica e satirica, GoonLand prende questo concetto e lo trasforma in una metafora dell’era digitale: un invito a lasciarsi assorbire consapevolmente dall’assurdo, dall’eccesso e dal caos creativo del web.</p>
            </article>

            <article class="gl-lore-card gl-lore-card--accent gl-reveal">
                <span class="gl-kicker">Benvenuto</span>
                <h2>Vi auguriamo tanto gooning.</h2>
                <p>Che tu sia un veterano della rete, cresciuto a pane e culture digitali underground, oppure un esploratore curioso alla ricerca di nuovi territori dell’assurdo, GoonLand ti dà il benvenuto.</p>
                <p>Qui puoi perderti, ritrovarti o semplicemente lasciarti trasportare da un’esperienza fuori dal comune. Mettiti comodo, dimentica le regole per un po’ e preparati a entrare in un mondo che non chiede di essere compreso, ma semplicemente vissuto.</p>
            </article>
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

    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="/js/modeChanger.js"></script>
</body>

</html>