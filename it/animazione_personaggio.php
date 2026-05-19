<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

// Recupero l'ID del personaggio dall'URL
$idPersonaggio = $_GET['id_personaggio'] ?? 0;
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>

    <link rel="stylesheet" href="/css/lootbox.css?v=8.2" />
    <link rel="stylesheet" href="/css/gacha.css?v=10" />
    <title>Cripsum™ - Animazione personaggio</title>

    <style>
        body.animazione-page {
            background-color: #080810;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }

        /* Nascondiamo header o navbar in caso vengano importate per sbaglio */
        nav,
        header,
        .navbar,
        #navbar {
            display: none !important;
        }

        /* Nascondiamo lo skip: l'obiettivo di questa view è proprio guardare l'animazione */
        #gacha-skip-btn {
            display: none !important;
        }
    </style>
</head>

<body class="animazione-page">

    <div id="stars"></div>
    <audio id="gacha-audio"></audio>
    <div id="gacha-toast"></div>
    <div id="gacha-layout" style="display:none"></div>
    <button id="btn-pull-10" style="display:none"></button>
    <button id="btn-close-overlay" style="display:none"></button>

    <button class="gacha-pull-btn" id="fake-pull-btn" data-banner-id="standard" style="display:none"></button>

    <div class="gacha-overlay" id="gacha-overlay" role="dialog" aria-modal="true" aria-label="Risultato pull" aria-live="polite" style="display:none">
        <div class="gacha-overlay-bg"></div>
        <div class="gacha-glow-burst" id="gacha-glow-burst"></div>
        <div class="gacha-stars-layer" id="overlay-stars"></div>
        <div class="gacha-particles-layer" id="gacha-particles"></div>
        <div class="gacha-flash" id="gacha-flash"></div>

        <button class="gacha-skip-btn" id="gacha-skip-btn" style="display:none">
            <i class="fas fa-forward-fast"></i> Salta
        </button>

        <div class="gacha-phase gacha-phase--opening" id="phase-opening">
            <div class="gacha-orb-container">
                <div class="gacha-orb">
                    <div class="gacha-orb-ring gacha-orb-ring--3"></div>
                    <div class="gacha-orb-ring gacha-orb-ring--2"></div>
                    <div class="gacha-orb-ring gacha-orb-ring--1"></div>
                    <div class="gacha-orb-core" id="orb-core"></div>
                </div>
            </div>
        </div>

        <div class="gacha-phase gacha-phase--video" id="phase-video" style="display:none">
            <video id="gacha-video" playsinline preload="metadata" webkit-playsinline></video>
            <button class="gacha-video-unmute" id="video-unmute-btn" style="display:none">
                <i class="fas fa-volume-xmark"></i> Tap per audio
            </button>
        </div>

        <div class="gacha-phase gacha-phase--card" id="phase-card" style="display:none">
            <div class="gacha-card" id="gacha-card" aria-live="polite">
                <div class="gacha-card-bg-glow" id="card-bg-glow"></div>
                <div class="gacha-card-frame" id="card-frame">
                    <div class="gacha-card-img-wrap" id="card-img-wrap">
                        <img id="card-img" src="/img/cassa.png" alt="Personaggio" draggable="false" onerror="this.src='/img/cassa.png'">
                    </div>
                    <div class="gacha-card-img-shine"></div>
                    <span class="gacha-card-new-badge" id="card-new-badge" style="display:none">NEW!</span>
                    <span class="gacha-card-50-badge gacha-card-50-badge--win" id="card-50-win" style="display:none"><i class="fas fa-trophy"></i> Rate-Up Vinto!</span>
                    <span class="gacha-card-50-badge gacha-card-50-badge--loss" id="card-50-loss" style="display:none">Rate-Up Perso...</span>
                </div>

                <div class="gacha-card-info">
                    <h3 class="gacha-card-name" id="card-name">...</h3>
                    <div class="gacha-card-rarity-bar" id="card-rarity-bar"></div>
                    <span class="gacha-card-rarity-label" id="card-rarity-label">...</span>
                </div>

                <div class="gacha-card-actions">
                    <button class="gacha-action-btn gacha-action-btn--primary" id="btn-pull-again">
                        <i class="fas fa-rotate-right"></i> Ripeti animazione
                    </button>
                    <a href="inventario" class="gacha-action-btn gacha-action-btn--secondary" id="btn-go-inventory">
                        <i class="fas fa-layer-group"></i> Inventario
                    </a>
                </div>
            </div>
        </div>

        <div class="gacha-phase gacha-phase--multi" id="phase-multi" style="display:none"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="/js/gacha-effects.js?v=5"></script>
    <script src="/js/gacha.js?v=23"></script>

    <script>
        const idPersonaggio = <?= json_encode($idPersonaggio) ?>;
        let mockCharacterData = null;

        // Inizializza l'ambiente gacha fake 
        window.GACHA_INIT = {
            soldi: 0,
            pityStandard: 0,
            pityEvento: 0,
            garantito: false,
            activeBannerId: 'standard'
        };

        // 1. IL TRUCCO: Intercettiamo le comunicazioni di gacha.js
        const originalFetch = window.fetch;
        window.fetch = async function(url, options) {
            // Quando gacha.js cerca di scalare i punti per la pull...
            if (url.includes('/api/api_gacha_pull')) {
                // ...noi blocchiamo tutto e gli restituiamo istantaneamente il personaggio finto
                return new Response(JSON.stringify({
                    status: 'success',
                    soldi_rimasti: 0,
                    pity_standard: 0,
                    pity_evento: 0,
                    is_new: false,
                    vinto_50_50: null,
                    personaggio: mockCharacterData // Iniettiamo i dati appena prelevati
                }), {
                    status: 200,
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
            }
            // Lascia passare eventuali altre chiamate innocue
            return originalFetch.apply(this, arguments);
        };

        document.addEventListener('DOMContentLoaded', async () => {
            if (!idPersonaggio || idPersonaggio === '0') return;

            try {
                // Recuperiamo dal DB SOLO le info visive (url immagine, rarità, ecc.)
                const response = await originalFetch(`/api/get_character_from_id?id=${encodeURIComponent(idPersonaggio)}`);
                mockCharacterData = await response.json();

                if (!mockCharacterData || mockCharacterData.error) return;

                // 2. Simuliamo un "click" umano sul bottone fittizio della pull
                // In questo modo gacha.js farà tutto il lavoro, farà partire le orb, i video di Yuno o altri, e poi formatterà i testi per bene
                const fakeBtn = document.getElementById('fake-pull-btn');
                if (fakeBtn) {
                    setTimeout(() => fakeBtn.click(), 150);
                }

            } catch (err) {
                console.error('Errore durante il fetch personaggio:', err);
            }
        });
    </script>
</body>

</html>