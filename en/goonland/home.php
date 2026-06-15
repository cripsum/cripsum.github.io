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
      <meta property="og:description" content="Welcome to GoonLand: the place on Cripsum where you can goon as much as you want.">
      <meta property="og:image" content="https://cripsum.com/img/raspberry-chan16gb.png">
      <meta property="og:url" content="https://cripsum.com/it/goonland/home">
      <meta property="og:type" content="website">
    </head><body></body></html>';
    exit;
}

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "You need to be logged in to access GoonLand";
    header('Location: ../accedi');
    exit();
}

if (isset($_SESSION['nsfw']) && $_SESSION['nsfw'] == 0) {
    $_SESSION['error_message'] = "You need to enable NSFW content in your profile settings to access GoonLand";
    header('Location: ../home');
    exit();
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta property="og:title" content="Cripsum™ GoonLand - Home">
    <meta property="og:description" content="Welcome to GoonLand: the place on Cripsum where you can goon as much as you want.">
    <meta property="og:image" content="https://cripsum.com/img/raspberry-chan16gb.png">
    <meta property="og:url" content="https://cripsum.com/it/goonland/home">
    <meta property="og:type" content="website">
    <?php include '../../includes/head-import.php'; ?>
    <title>GoonLand™ - Home</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/css/goonland.css?v=2.2-dropdown-footer">
    <script src="/js/goonland.js?v=2.3" defer></script>
</head>

<body class="goonland-page" data-goonland-page="home">
    <?php include '../../includes/navbar-goonland.php'; ?>


    <div class="gl-bg" aria-hidden="true"><span></span><span></span></div>
    <img src="https://media1.tenor.com/m/QJ7OYh157fcAAAAC/sonic.gif" class="goonrpcimg" style="display:none" alt="">


    <main class="gl-shell gl-home-shell">
        <section class="gl-hero gl-home-hero gl-reveal">
            <div class="gl-hero-text">
                <span class="gl-kicker"><i class="fa-solid fa-heart"></i> GoonLand</span>
                <h1>GoonLand</h1>
                <p>A project designed by Zakator and Cripsum: a provocative, playful, and visually immersive digital space.</p>

                <div class="gl-actions">
                    <a class="gl-btn gl-btn-main" href="goon-generator">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Goon Generator
                    </a>
                    <a class="gl-btn gl-btn-main" href="anime-girl-quiz">
                        <i class="fa-solid fa-heart-pulse"></i> Anime Girl Quiz
                    </a>
                    <a class="gl-btn gl-btn-main" href="smash-or-pass">
                        <i class="fa-solid fa-fire"></i> Smash or Pass
                    </a>
                    <a class="gl-btn gl-btn-ghost" href="../home">
                        <i class="fa-solid fa-arrow-left"></i> Back to main site
                    </a>
                </div>
            </div>

            <div class="gl-hero-media gl-home-visual">
                <img src="/img/raspberry-chan16gb.png" alt="GoonLand visual" loading="eager" data-gl-fallback>

            </div>
        </section>

        <section class="gl-lore-stack">
            <article class="gl-lore-card gl-reveal">
                <span class="gl-kicker">What is GoonLand?</span>
                <h2>A small digital universe.</h2>
                <p>GoonLand is a project conceived by Zakator and Cripsum: a one-of-a-kind digital space, born from the desire to create an environment that is simultaneously provocative, playful, and visually immersive.</p>
                <p>It’s not just a website, but a small universe built to entertain, provoke thought, and sometimes creatively confuse in an unconventional way.</p>
            </article>

            <article class="gl-lore-card gl-lore-card--wide gl-reveal">
                <span class="gl-kicker">What’s inside</span>
                <h2>Games, experiments, and themed content.</h2>
                <p>Within GoonLand, you’ll find a collection of interactive games, experimental experiences, and themed content, all developed to embody the visionary and often surreal aesthetic of the project.</p>
                <p>Every element of the site is designed to immerse the user in a digital journey where nothing is quite as it seems, and where irony blends with a subtle critique of online entertainment culture.</p>
            </article>

            <article class="gl-lore-card gl-reveal">
                <span class="gl-kicker">What is gooning?</span>
                <h2>A metaphor for the digital age.</h2>
                <p>The term “gooning” originated in the weirdest corners of the internet, where it is used to describe a hypnotic, almost trance-like mental state induced by the obsessive repetition of sensory stimuli, images, sounds, or digital content.</p>
                <p>In an ironic and satirical key, GoonLand takes this concept and transforms it into a metaphor for the digital age: an invitation to consciously let yourself be absorbed by the absurd, the excess, and the creative chaos of the web.</p>
            </article>

            <article class="gl-lore-card gl-lore-card--accent gl-reveal">
                <span class="gl-kicker">Welcome</span>
                <h2>We wish you a happy gooning.</h2>
                <p>Whether you are a web veteran, raised on underground digital culture, or a curious explorer in search of new territories of the absurd, GoonLand welcomes you.</p>
                <p>Here you can lose yourself, find yourself, or simply let yourself be carried away by an out-of-the-ordinary experience. Get comfortable, forget the rules for a while, and prepare to enter a world that doesn’t ask to be understood, but simply lived.</p>
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

    <button class="gl-top" type="button" data-gl-top aria-label="Torna su"><i class="fa-solid fa-arrow-up"></i></button>

    <?php include '../../includes/footer-en.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="/js/modeChanger.js"></script>
</body>

</html>