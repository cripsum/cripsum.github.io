<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™</title>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1527058839538660"
        crossorigin="anonymous"></script>
</head>

<body class="">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="testobianco paginaprincipale">
        <script>
        </script>
        <script>
            function close_div(id) {
                if (id === 1) {
                    jQuery("#collegamentoedits").hide();
                }
            }

            function close_disclaimer(id) {
                if (id === 1) {
                    jQuery("#disclaimer").hide();
                }
            }
        </script>
        <script>
            function getRandomBanner() {
                const banners = [
                    `<div class="bannerino">
            <h2 style="padding-top: 11px">Hey tu!</h2>
            <p class="testobianco">Dai un'occhiata ai miei ultimi edit! - <a href="edits" class="linkbianco">clicca qui</a></p>
        </div>`,
                    `<div class="bannerino">
            <h2 class="testobianco" style="padding-top: 11px">Ciao bro!</h2>
            <p class="testobianco">Entra nel mio server discord - <a href="../discord" class="linkbianco">clicca qui</a></p>
        </div>`,
                    `<div class="bannerino">
            <h2 class="testobianco" style="padding-top: 11px">Hey bro!</h2>
            <p class="testobianco">Entra nel mio gruppo telegram - <a href="https://t.me/sburragrigliata" class="linkbianco">clicca qui</a></p>
        </div>`,
                    `<div class="bannerino">
            <h2 class="testobianco" style="padding-top: 11px">Buonasera!</h2>
            <p class="testobianco"><a href="https://www.tiktok.com/@cripsum" class="linkbianco">Seguimi su tiktok!</a></p>
        </div>`,

                ];
                return banners[Math.floor(Math.random() * banners.length)];
            }

            function showPopup() {
                const overlay = document.getElementById("popup-overlay");
                const popup = document.getElementById("collegamentoedits");
                document.getElementById("banner-content").innerHTML = getRandomBanner();
                overlay.style.display = "flex";
                document.body.style.overflow = "hidden";
                setTimeout(() => {
                    overlay.style.opacity = "1";
                    popup.style.opacity = "1";
                    popup.style.transform = "translateY(0)";
                }, 10);
            }

            function closePopup() {
                const overlay = document.getElementById("popup-overlay");
                const popup = document.getElementById("collegamentoedits");
                popup.style.opacity = "0";
                popup.style.transform = "translateY(-20px)";
                overlay.style.opacity = "0";
                document.body.style.overflow = "auto";
                setTimeout(() => {
                    overlay.style.display = "none";
                }, 500);
            }
        </script>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger fadeup" role="alert" style="max-width: 80%; margin: auto; margin-top: 3rem">
                <i class="bi bi-exclamation-triangle</a>-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div style="margin: 2rem 0;">
            <a href="https://store.steampowered.com/app/3527290/PEAK/" target="_blank" rel="noopener"></a>
            <img src="../img/FREE.png" alt="Centered Image" style="max-width: 1000px" />
            </a>
        </div>

        <div id="disclaimer" class="divdisclaimer fadeup">
            <button class="btn btn-secondary bottone" type="button" data-bs-toggle="modal" data-bs-target="#disclaimerModal" style="margin-top: 30px; max-width: 70%">
                <span class="testobianco"> Disclaimer prima di proseguire nel sito ▼</span>
            </button>
        </div>

        <div class="modal fade" id="disclaimerModal" tabindex="-1" aria-labelledby="disclaimerModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content bgdisclaimer">
                    <div class="modal-header">
                        <h5 class="modal-title" id="disclaimerModalLabel">Disclaimer</h5>
                    </div>
                    <div class="modal-body">
                        <p class="fw-bold">Questo sito è pensato per intrattenere e far sorridere. <br />Di seguito alcune note importanti per un’esperienza sicura e positiva.</p>
                        <ul class="text-start mb-2 mb-lg-0">
                            <li class="mb-2">
                                Il contenuto di questo sito è creato per divertire, senza intenzione di offendere o mancare di rispetto a nessuno. un esempio sono le pagine "TikTokPedia", "Top
                                Rimasti" o "Chi Siamo".
                            </li>
                            <li class="mb-2">Le pagine di download sono sicure e prive di virus o contenuti dannosi; si tratta esclusivamente di meme e contenuti umoristici.*</li>
                            <li class="mb-2">
                                Il negozio e le pagine di acquisto sono puramente fittizie, e qualsiasi tentativo di checkout è simulato. I dati inseriti non vengono memorizzati né trasmessi.*
                            </li>
                            <li class="mb-2">
                                La pagina delle donazioni è reale e consente invii di denaro; tuttavia, questo sito non è a scopo di lucro, quindi ti invitiamo a non procedere con donazioni.
                            </li>
                        </ul>
                        <p class="">
                            *NOTA: Per trasparenza, il codice del sito è pubblico su <a href="https://github.com/cripsum/cripsum.github.io" class="linkbianco">GitHub</a>. Puoi verificare tu stesso
                            l'autenticità del disclaimer.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary bottone" data-bs-dismiss="modal">Chiudi</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="home-section fadeup">
            <div class="home-content">
                <div class="home-image">
                    <img class="home-img ombra" src="../img/amongus.jpg" alt="Cripsum Hero" />
                </div>
                <div class="home-text">
                    <h1 class="home-title">Benvenuto/a nel sito migliore del congo</h1>
                    <p class="home-subtitle">cripsum re del Congo (pregate sempre e comunque per il Wise Mystical Tree)</p>
                    <p class="home-question">Hai più di 25 anni e possiedi un pc?</p>
                </div>
            </div>
        </div>

        <div class="alberi-section fadeup">
            <div class="alberi-grid">
                <div class="albero-card">
                    <div class="albero-image-wrapper">
                        <img src="../img/felicita.jpg" class="albero-img ombra" alt="Felicità" />
                    </div>
                    <h3 class="albero-label">Felicità</h3>
                </div>
                <div class="albero-card">
                    <div class="albero-image-wrapper">
                        <img src="../img/tristezza.jpg" class="albero-img ombra" alt="Tristezza" />
                    </div>
                    <h3 class="albero-label">Tristezza</h3>
                </div>
                <div class="albero-card">
                    <div class="albero-image-wrapper">
                        <img src="../img/stupore.jpg" class="albero-img ombra" alt="Stupore" />
                    </div>
                    <h3 class="albero-label">Stupore</h3>
                </div>
            </div>
        </div>

        <div id="featuredContent" class="fadeup">
            <div id="content-slider" class="content-slider">
                <div class="slider-wrapper" id="sliderWrapper"></div>
                <div class="slider-dots" id="sliderDots"></div>
            </div>
        </div>
        <script src="/js/slider.js?v=5"></script>
        <div class="infondo">
            <div class="sotto">
                <div class="social-section mt-5 fadeup">
                    <h4 class="sottopag mb-3 text-center">Seguimi sui social</h4>
                    <div class="social-icons-modern d-flex justify-content-center align-items-center gap-4 flex-wrap">
                        <a href="https://www.tiktok.com/@cripsum" class="social-link-modern tiktok" title="TikTok">
                            <div class="social-icon-wrapper">
                                <i class="fab fa-tiktok"></i>
                                <span class="social-label">TikTok</span>
                            </div>
                        </a>
                        <a href="https://www.instagram.com/cripsum/" class="social-link-modern instagram" title="Instagram">
                            <div class="social-icon-wrapper">
                                <i class="fab fa-instagram"></i>
                                <span class="social-label">Instagram</span>
                            </div>
                        </a>
                        <a href="https://discord.gg/XdheJHVURw" class="social-link-modern discord" title="Discord">
                            <div class="social-icon-wrapper">
                                <i class="fab fa-discord"></i>
                                <span class="social-label">Discord</span>
                            </div>
                        </a>
                        <a href="https://t.me/cripsum" class="social-link-modern telegram" title="Telegram">
                            <div class="social-icon-wrapper">
                                <i class="fab fa-telegram-plane"></i>
                                <span class="social-label">Telegram</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            <div class="button-container fadeup" style="text-align: center; margin-top: 2rem">
                <button class="btn btn-secondary bottone" type="button" onclick="unlockAchievement(10)">
                    <a href="https://youtu.be/xvFZjo5PgG0?si=uPsap7ILF_8aYheh" class="testobianco">Clicca qui per V-bucks gratis!!!!</a>
                </button>
            </div>

            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="account-section fadeup mt-4">
                    <h3 class="sottopag mb-3">Hai un account Cripsum™?</h3>
                    <p class="sottopag mb-4">
                        <a href="accedi" class="linkbianco btn-link">Accedi</a> al sito per sbloccare tutti i contenuti:
                    </p>

                    <div class="features-list mb-4">
                        <div class="feature-item">
                            <span class="feature-icon">✨</span>
                            <span class="feature-text">Accesso a pagine speciali come Chat Globale e Goonland</span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon">🎮</span>
                            <span class="feature-text">Giochi come Lootbox e tanti Achievements da sbloccare</span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon">👤</span>
                            <span class="feature-text">Possibilità di modificare il tuo profilo e molto altro</span>
                        </div>
                    </div>

                    <p class="sottopag">
                        Non hai un account?
                        <a href="registrati" class="linkbianco btn-link">Registrati ora</a>
                        e inizia a esplorare!
                    </p>
                </div>

            <?php endif; ?>
        </div>
    </div>
    <div id="achievement-popup" class="popup">
        <img id="popup-image" src="" alt="Achievement" />
        <div>
            <h3 id="popup-title"></h3>
            <p id="popup-description"></p>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>

    <script src="../js/modeChanger.js"></script>
</body>

</html>