        <?php
        $uri = $_SERVER['REQUEST_URI'];
        $lang = explode('/', trim($uri, '/'))[0];

        if (!in_array($lang, ['it', 'en'])) {
            $lang = 'it';
        }

        ?>

        <?php if (function_exists('csrf_token')): ?>
            <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>

        <?php
        /**
         * Anteprima per le condivisioni, per tutte le pagine.
         *
         * Prima ce l'avevano diciassette pagine su centoquaranta: tutte le
         * altre, incollate su Discord o WhatsApp, uscivano come un link nudo.
         * Qui la riceve chiunque includa questo file, cioe' tutto il sito.
         *
         * Una pagina che ha un'anteprima sua la prepara **prima** dell'include,
         * in una di queste due forme:
         *
         *   $ogMeta = cripsum_og_profile($mysqli, $profilo);   // gia' pronta
         *   $ogTitle / $ogDescription / $ogImage / $ogType;     // pezzo per pezzo
         *
         * Va fatto prima e non dopo perche' quando una pagina dichiara due
         * volte og:image i social prendono la prima, e la prima e' questa.
         */
        require_once __DIR__ . '/cripsum_og.php';

        if (!isset($ogMeta) || !is_array($ogMeta)) {
            $ogMeta = cripsum_og_default('site');

            if (!empty($ogTitle)) {
                $ogMeta['title'] = (string)$ogTitle;
            }
            if (!empty($ogDescription)) {
                $ogMeta['description'] = cripsum_og_trim((string)$ogDescription);
            }
            if (!empty($ogImage)) {
                $ogMeta['image'] = cripsum_og_abs((string)$ogImage);
            }
            if (!empty($ogUrl)) {
                $ogMeta['url'] = (string)$ogUrl;
            }
            if (!empty($ogType)) {
                $ogMeta['type'] = (string)$ogType;
            }
        }

        cripsum_og_print($ogMeta);
        ?>

        <script>
            // One-time cleanup for legacy profile drafts that serialized the
            // whole form, including its CSRF field, into localStorage.
            try {
                for (let index = localStorage.length - 1; index >= 0; index -= 1) {
                    const key = localStorage.key(index);
                    if (key && key.startsWith('cripsum.profile.draft.')) {
                        localStorage.removeItem(key);
                    }
                }
            } catch (_) {}
        </script>

        <script>
            /*
             * Google Analytics: attivo di default, si spegne dal pulsante nel
             * footer o dalla pagina Cookie. La scelta vive solo in questo
             * browser (localStorage) e spegnerlo non tocca nessun cookie del
             * sito: sessione, lingua e tema restano come sono.
             *
             * Google Signals e personalizzazione degli annunci restano spenti:
             * le statistiche servono solo a sapere quante visite fa il sito.
             */
            window.dataLayer = window.dataLayer || [];

            function gtag() {
                dataLayer.push(arguments);
            }

            window.cripsumAnalytics = (function() {
                var id = 'G-T0CTM2SBJJ';
                var key = 'cripsum.analytics';
                var loaded = false;

                function enabled() {
                    try {
                        return localStorage.getItem(key) !== 'off';
                    } catch (_) {
                        return true;
                    }
                }

                function load() {
                    if (loaded) return;
                    loaded = true;
                    window['ga-disable-' + id] = false;
                    var script = document.createElement('script');
                    script.async = true;
                    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + id;
                    document.head.appendChild(script);
                    gtag('js', new Date());
                    gtag('config', id, {
                        allow_google_signals: false,
                        allow_ad_personalization_signals: false
                    });
                }

                function clearCookies() {
                    var host = location.hostname;
                    var domains = ['', host, '.' + host, '.' + host.split('.').slice(-2).join('.')];
                    document.cookie.split('; ').forEach(function(part) {
                        var name = part.split('=')[0];
                        if (name !== '_ga' && name.indexOf('_ga_') !== 0 && name !== '_gid') return;
                        domains.forEach(function(domain) {
                            document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + (domain ? '; domain=' + domain : '');
                        });
                    });
                }

                function set(on) {
                    try {
                        if (on) {
                            localStorage.removeItem(key);
                        } else {
                            localStorage.setItem(key, 'off');
                        }
                    } catch (_) {}

                    if (on) {
                        load();
                    } else {
                        window['ga-disable-' + id] = true;
                        clearCookies();
                    }
                    document.dispatchEvent(new CustomEvent('cripsum:analytics', { detail: { enabled: on } }));
                }

                if (enabled()) {
                    load();
                } else {
                    window['ga-disable-' + id] = true;
                }

                return { enabled: enabled, set: set };
            })();
        </script>
        <script src="/js/activity-beat.js?v=2" defer></script>
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
            rel="stylesheet"
            integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
            crossorigin="anonymous" />

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7.2.0/css/all.min.css">
        <link rel="icon" href="/img/Susremaster.png" type="image/png" />
        <link rel="shortcut icon" href="/img/Susremaster.png" type="image/png" />
        <?php /* Rende il sito installabile come app: solo così le notifiche arrivano a nome di Cripsum e non del browser. */ ?>
        <link rel="manifest" href="/manifest.json" />
        <link rel="apple-touch-icon" href="/img/app-192.png" />
        <link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet" />
        <link rel="stylesheet" href="/css/style.css?v=28" />
        <link rel="stylesheet" href="/css/style-dark.css?v=24" />
        <link rel="stylesheet" href="/css/navbar-search.css?v=4.2" />
        <link rel="stylesheet" href="/css/animations.css" />
        <link rel="stylesheet" href="/css/achievement-style.css?v=3.0" />
        <link rel="stylesheet" href="/assets/auth/password-strength.css?v=1.1" />
        <script src="/assets/auth/password-strength.js?v=1.1" defer></script>

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
        <script src="/js/animations.js"></script>
        <script src="/js/controlloLingua-it.js?v=2"></script>
        <script src="/js/controlloTema.js"></script>
        <script src="/js/impostazioni.js?v=2"></script>
        <?php /* Popup e richieste di sblocco: un file solo per le due lingue. Il resto lo conta il server (includes/achievements.php). */ ?>
        <script src="/js/achievements-popup.js?v=3.0"></script>
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
