        <?php
        $uri = $_SERVER['REQUEST_URI'];
        $lang = explode('/', trim($uri, '/'))[0];

        if (!in_array($lang, ['it', 'en'])) {
            $lang = 'it';
        }

        $t = [
            'it' => [
                'achievement'        => 'unlockAchievement-it.jsv=2',
            ],
            'en' => [
                'achievement'        => 'unlockAchievement-en.jsv=2',
            ],
        ][$lang];

        ?>

        <script async src="https://www.googletagmanager.com/gtag/js?id=G-T0CTM2SBJJ"></script>
        <script>
            window.dataLayer = window.dataLayer || [];

            function gtag() {
                dataLayer.push(arguments);
            }
            gtag("js", new Date());

            gtag("config", "G-T0CTM2SBJJ");
        </script>
        <script>
            fetch('/api/update_activity.php');
            setInterval(() => {
                fetch('/api/update_activity.php');
            }, 25000);
        </script>
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
            rel="stylesheet"
            integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
            crossorigin="anonymous" />

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="icon" href="/img/Susremaster.png" type="image/png" />
        <link rel="shortcut icon" href="/img/Susremaster.png" type="image/png" />
        <link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet" />
        <link rel="stylesheet" href="/css/style.css?v=27" />
        <link rel="stylesheet" href="/css/style-dark.css?v=23" />
        <link rel="stylesheet" href="/css/navbar-search.css?v=1.2" />
        <link rel="stylesheet" href="/css/animations.css" />
        <link rel="stylesheet" href="/css/achievement-style.css" />

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
        <script src="/js/animations.js"></script>
        <script src="/js/controlloLingua-it.js?v=2"></script>
        <script src="/js/controlloTema.js"></script>
        <script src="/js/<?php echo $t['achievement']; ?>"></script>
        <script src="/js/unlockAchievement-it.js?v=2"></script>
        <script src="/js/achievements-globali.js?v=4"></script>
        <!-- <script src="/js/nomePagina.js"></script> -->

        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />

        <!-- <audio id="globalMusic" loop>
            <source src="../audio/sahur.mp3" type="audio/mpeg">
        </audio>

        <script>
            const audio = document.getElementById("globalMusic");

            audio.volume = 0.2;

            const savedTime = localStorage.getItem("musicTime");
            const wasPlaying = localStorage.getItem("musicPlaying");

            if (savedTime !== null) {
                audio.currentTime = parseFloat(savedTime);
            }

            function startMusic() {
                audio.play().then(() => {
                    localStorage.setItem("musicPlaying", "true");
                }).catch(() => {});
            }

            if (wasPlaying === "true") {
                startMusic();
            }

            document.addEventListener("click", () => {
                if (audio.paused) {
                    startMusic();
                }
            }, {
                once: true
            });

            setInterval(() => {
                localStorage.setItem("musicTime", audio.currentTime);
            }, 500);

            audio.addEventListener("pause", () => {
                localStorage.setItem("musicPlaying", "false");
            });

            audio.addEventListener("play", () => {
                localStorage.setItem("musicPlaying", "true");
            });
        </script> -->