<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <?php include '../includes/head-import.php'; ?>
        <title>Cripsum™</title>
        <script src="/js/nomePagina.js"></script>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1527058839538660"
     crossorigin="anonymous"></script>
                         <style>
                    .account-section {
                        text-align: center;
                        padding: 2rem 0;
                    }

                    .features-list {
                        max-width: 500px;
                        margin: 0 auto;
                    }

                    .feature-item {
                        display: flex;
                        align-items: center;
                        justify-content: flex-start;
                        margin: 1rem 0;
                        padding: 0.8rem;
                        background: rgba(255, 255, 255, 0.1);
                        border-radius: 8px;
                        backdrop-filter: blur(10px);
                    }

                    .feature-icon {
                        font-size: 1.2rem;
                        margin-right: 1rem;
                        min-width: 30px;
                    }

                    .feature-text {
                        text-align: left;
                        flex: 1;
                    }

                    .btn-link {
                        text-decoration: none;
                        padding: 0.3rem 0.6rem;
                        border-radius: 4px;
                        transition: background-color 0.3s ease;
                    }

                    .btn-link:hover {
                        background-color: rgba(255, 255, 255, 0.1);
                    }
                    </style>
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

            <div
                id="popup-overlay"
                style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.85);
                    display: none;
                    align-items: center;
                    justify-content: center;
                    z-index: 9999;
                    opacity: 0;
                    transition: opacity 0.5s ease;
                "
            >
                <div id="collegamentoedits" class="collegamentoedit ombra fadeup">
                    <button style="position: absolute; top: 0px; right: 5px; background-color: transparent; border: none; cursor: pointer" onclick="closePopup()">
                        <span class="close_div tastobianco" style="font-size: 20px"
                            >&times;<span class="linkbianco" style="font-size: small; position: relative; top: -3px; left: 3px">chiudi</span></span
                        >
                    </button>
                    <div id="banner-content"></div>
                </div>
            </div>

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

                window.onload = function () {
                    setTimeout(showPopup, 700);
                };
            </script>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger fadeup" role="alert" style="max-width: 80%; margin: auto; margin-top: 3rem">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

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
                            <!--<button type="button" class="btn-close tastobianco" data-bs-dismiss="modal" aria-label="Close" onclick="close_disclaimer(1)" style="color: #ffffff"></button>-->
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

            <div style="max-width: 70%; margin: auto; padding-top: 15px" class="d-flex justify-content-around flex-wrap testobianco fadeup">
                <img class="img-fluid imagi ombra" src="../img/amongus.jpg" alt="" style="display: inline" />
                <div class="float-end titolo">
                    <p class="fs-1 text mt-3" style="font-weight: bold">Benvenuto/a nel sito migliore del congo</p>
                    <p class="fs-5 text mt-3">cripsum re del Congo (pregate sempre e comunque per il Wise Mystical Tree)</p>
                    <p class="fs-5 text mt-3">Hai più di 25 anni e possiedi un pc?</p>
                </div>
            </div>
            <div class="d-flex justify-content-around image-container fadeup" style="max-width: 80%; margin: auto; padding-top: 5%">
                <div>
                    <img src="../img/felicita.jpg" class="immaginealberi ombra" alt="" />
                    <p class="fs-5 text text-center">Felicità</p>
                </div>
                <div>
                    <img src="../img/tristezza.jpg" class="immaginealberi ombra" alt="" />
                    <p class="fs-5 text text-center">Tristezza</p>
                </div>
                <div>
                    <img src="../img/stupore.jpg" class="immaginealberi ombra" alt="" />
                    <p class="fs-5 text text-center">Stupore</p>
                </div>
            </div>
            <hr class="rounded fadeuphr" />

            <div id="randomAd">
                <script>
                    function getRandomAd() {
                        const ads = [
                            `<div class="container fadeup">
                <div class="row justify-content-center align-items-center">
                    <div class="col-md-auto mb-3 spinjitzu spingio">
                        <img src="../img/jay.png" alt="Immagine Sinistra" class="img-fluid" />
                    </div>
                    <div class="col-md-6 text-center spinjitzu">
                        <p class="fs-1 text" style="font-weight: bold">Ciao! Sono Jay!</p>
                        <p class="fs-4 text mt-3">vuoi imparare l'arte dello Spinjitzu?</p>
                        <button class="btn btn-secondary bottone" type="button">
                            <a href="https://payhip.com/b/m0kaT" class="testobianco">Clicca qui per acquistare il mio videocorso online</a>
                        </button>
                    </div>
                    <div class="col-md-auto spinjitzu spingio">
                        <img src="../img/maranza.jpg" alt="Immagine Destra" class="img-fluid ombra" />
                    </div>
                </div>
            </div>`,
            `<div class="container fadeup">
                <div class="row justify-content-center align-items-center">
                    <div class="col-md-auto mb-3 spinjitzu spingio">
                        <img src="../img/chinese-essay-344821_1280.jpg" alt="Immagine Sinistra" class="img-fluid" style="max-width: 200px" />
                    </div>
                    <div class="col-md-6 text-center spinjitzu">
                        <p class="fs-1 text" style="font-weight: bold">Hey! Mi chiamo 優希!</p>
                        <p class="fs-4 text mt-3">vuoi imparare l'arte dello Yoshukai?</p>
                        <button class="btn btn-secondary bottone" type="button">
                            <a href="download/yoshukai" class="testobianco">Clicca qui per scaricare la mia guida gratuita</a>
                        </button>
                    </div>
                </div>
            </div>`,
                        ];
                        return ads[Math.floor(Math.random() * ads.length)];
                    }

                    document.write(getRandomAd());
                </script>
            </div>
            <hr class="rounded fadeuphr mt-3 mb-3" />
            <p class="text-center fadeup">- pubblicità -</p>
            <div class="d-flex justify-content-center image-container" style="max-width: 80%; margin: auto">
                <script>
                    function getRandomImages() {
                        const images = [
                            '<div style="margin-left: 2%; margin-right: 2%" class="fadeup"><img src="../img/arabo1.jpeg" class="immaginealberi ombra" alt="" /></div>',
                            '<div style="margin-left: 2%; margin-right: 2%" class="fadeup"><img src="../img/arabo3.jpeg" class="immaginealberi ombra" alt="" /></div>',
                            '<div style="margin-left: 2%; margin-right: 2%" class="fadeup"><img src="../img/arabo2.jpeg" class="immaginealberi ombra" alt="" /></div>',
                        ];
                        return images[Math.floor(Math.random() * images.length)];
                    }

                    document.write(getRandomImages());
                </script>
                <script>
                    function getRandomImages() {
                        const images = [
                            '<div style="margin-left: 2%; margin-right: 2%" class="fadeup godo"><img src="../img/arabo1.jpeg" class="immaginealberi ombra" alt="" /></div>',
                            '<div style="margin-left: 2%; margin-right: 2%" class="fadeup godo"><img src="../img/arabo3.jpeg" class="immaginealberi ombra" alt="" /></div>',
                            '<div style="margin-left: 2%; margin-right: 2%" class="fadeup godo"><img src="../img/arabo2.jpeg" class="immaginealberi ombra" alt="" /></div>',
                        ];
                        return images[Math.floor(Math.random() * images.length)];
                    }

                    document.write(getRandomImages());
                </script>
                <script>
                    function getRandomImages() {
                        const images = [
                            '<div style="margin-left: 2%; margin-right: 2%" class="fadeup godo2"><img src="../img/arabo1.jpeg" class="immaginealberi ombra" alt="" /></div>',
                            '<div style="margin-left: 2%; margin-right: 2%" class="fadeup godo2"><img src="../img/arabo3.jpeg" class="immaginealberi ombra" alt="" /></div>',
                            '<div style="margin-left: 2%; margin-right: 2%" class="fadeup godo2"><img src="../img/arabo2.jpeg" class="immaginealberi ombra" alt="" /></div>',
                        ];
                        return images[Math.floor(Math.random() * images.length)];
                    }

                    document.write(getRandomImages());
                </script>
            </div>
            <hr class="rounded fadeuphr mt-3 mb-3" />
            <div class="infondo fadeup">
                <div class="sotto" style="padding-bottom: 1%">
                    <div class="account-section"></div>
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
                    <div class="social">
                        <p style="font-size: large; padding-left: 0.1%; margin-top: 3%">i miei social</p>
                        <a href="https://www.tiktok.com/@cripsum"><img src="../img/tiktok-white-icon.svg" class="imgbianca" alt="" style="margin-right: 0.1%; width: 28px; border-radius: 0" /></a>
                        <a href="https://www.instagram.com/cripsum/"
                            ><img src="../img/instagram-white-icon.svg" class="imgbianca" alt="" style="margin-right: 0.3%; margin-left: 0.1%; width: 28px; border-radius: 0"
                        /></a>
                        <a href="https://discord.gg/Mmb2sNCvy6"><img src="../img/discord-white-icon.svg" class="imgbianca" alt="" style="margin: 0.3%; width: 28px; border-radius: 0" /></a>
                        <a href="https://t.me/cripsum"><img src="../img/telegram-white-icon.svg" class="imgbianca" alt="" style="margin: 0.3%; width: 28px; border-radius: 0" /></a>
                    </div>
                </div>
                <div class="button-container" style="text-align: center; margin-top: 1rem">
                    <button class="btn btn-secondary bottone" type="button" onclick="unlockAchievement(10)">
                        <a href="https://youtu.be/xvFZjo5PgG0?si=uPsap7ILF_8aYheh" class="testobianco">Clicca qui per V-bucks gratis!!!!</a>
                    </button>
                </div>

                <script>
                function searchUser() {
                    const username = document.getElementById('userSearch').value.trim();
                    if (username) {
                        window.location.href = `../user/${encodeURIComponent(username)}`;
                    } else {
                        alert('Inserisci un nome utente per continuare');
                    }
                }

                document.getElementById('userSearch').addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        searchUser();
                    }
                });
                </script>
                <!--<p style="text-decoration: none; margin-top: 3%; font-size: larger; font-weight: bold; text-align: center">
                    <a class="linkbianco" href="donazioni" style="text-decoration: none">Dona 5€ al Wise Mystical Tree per un mondo migliore :)</a>
                </p>-->
            </div>
        </div>
        <div id="achievement-popup" class="popup">
            <img id="popup-image" src="" alt="Achievement" />
            <div>
                <h3 id="popup-title"></h3>
                <p id="popup-description"></p>
            </div>
        </div>
        <footer class="my-5 pt-5 text-muted text-center text-small fadeup">
            <p class="mb-1 testobianco">Copyright © 2021-2025 Cripsum™. Tutti i diritti riservati.</p>
            <ul class="list-inline">
                <li class="list-inline-item"><a href="privacy" class="linkbianco">Privacy</a></li>
                <li class="list-inline-item"><a href="tos" class="linkbianco">Termini</a></li>
                <li class="list-inline-item"><a href="supporto" class="linkbianco">Supporto</a></li>
            </ul>
        </footer>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>

        <script src="../js/modeChanger.js"></script>
    </body>
</html>
