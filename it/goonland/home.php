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
    <link rel="stylesheet" href="/css/goonland.css?v=2.0">
    <script src="/js/goonland.js?v=2.0" defer></script>
</head>

<body class="goonland-page" data-goonland-page="home">
    <?php include '../../includes/navbar-goonland.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>

    <div class="gl-bg" aria-hidden="true"><span></span><span></span></div>

    <main class="gl-shell">
        <section class="gl-hero gl-reveal">
            <div class="gl-hero-text">
                <span class="gl-kicker"><i class="fas fa-heart"></i> GoonLand</span>
                <h1>Welcome to GoonLand</h1>
                <p>Una zona rosa, strana e sperimentale dentro cripsum.com. Qui finiscono mini-giochi, idee assurde e robe che non starebbero bene altrove.</p>

                <div class="gl-actions">
                    <a class="gl-btn gl-btn-main" href="/it/goonland/goon-generator">
                        <i class="fas fa-wand-magic-sparkles"></i> Apri il generator
                    </a>
                    <a class="gl-btn gl-btn-ghost" href="/it/home">
                        <i class="fas fa-arrow-left"></i> Torna al sito
                    </a>
                </div>
            </div>

            <div class="gl-hero-media">
                <img src="/img/raspberry-chan16gb.png" alt="GoonLand visual" loading="eager" data-gl-fallback>
                <div class="gl-floating-card">
                    <span>Mood</span>
                    <strong>rosa, caotico, sperimentale</strong>
                </div>
            </div>
        </section>

        <section class="gl-section gl-reveal">
            <div class="gl-section-head">
                <span class="gl-kicker">Cos’è</span>
                <h2>Una mini-area del sito fatta per esperimenti.</h2>
                <p>Stesso tono ironico, ma con una grafica più ordinata e leggibile.</p>
            </div>

            <div class="gl-card-grid">
                <article class="gl-card">
                    <i class="fas fa-dice"></i>
                    <h3>Esperienze</h3>
                    <p>Interazioni, generatori e pagine leggere da usare subito.</p>
                </article>
                <article class="gl-card">
                    <i class="fas fa-eye"></i>
                    <h3>Estetica</h3>
                    <p>Rosa, fucsia, glow e uno stile surreale ma pulito.</p>
                </article>
                <article class="gl-card">
                    <i class="fas fa-bolt"></i>
                    <h3>Caos controllato</h3>
                    <p>Resta strano e personale, ma senza diventare illeggibile.</p>
                </article>
            </div>
        </section>

        <section class="gl-split gl-reveal">
            <article class="gl-copy">
                <span class="gl-kicker">Idea</span>
                <h2>Non una dashboard. Una stanza a parte.</h2>
                <p>GoonLand non deve spiegare troppo. Deve essere una zona separata del sito, con una sua identità e una sua vibe.</p>
            </article>
            <article class="gl-copy gl-copy-accent">
                <span class="gl-kicker">Nota</span>
                <h2>Accesso regolato dalle impostazioni.</h2>
                <p>Se i contenuti NSFW sono disattivati nel profilo, questa sezione resta bloccata.</p>
            </article>
        </section>
    </main>

    <div id="achievement-popup" class="popup">
        <img id="popup-image" src="" alt="Achievement">
        <div><h3 id="popup-title"></h3><p id="popup-description"></p></div>
    </div>

    <button class="gl-top" type="button" data-gl-top aria-label="Torna su"><i class="fas fa-arrow-up"></i></button>

    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="/js/modeChanger.js"></script>
</body>
</html>
