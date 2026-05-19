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
        /* Sfondo scuro per la pagina dedicata all'animazione e blocco scroll */
        body.animazione-page {
            background-color: #080810;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }

        /* Nascondiamo bottoni e skip che non servono nel visualizzatore singolo */
        #btn-pull-10,
        #btn-close,
        #gacha-skip-btn {
            display: none !important;
        }
    </style>
</head>

<body class="animazione-page">

    <div class="gacha-overlay" id="gacha-overlay" role="dialog" aria-modal="true" aria-label="Risultato pull" aria-live="polite">
        <div class="gacha-overlay-bg"></div>
        <div class="gacha-glow-burst" id="gacha-glow-burst"></div>
        <div class="gacha-stars-layer" id="overlay-stars"></div>
        <div class="gacha-particles-layer" id="gacha-particles"></div>
        <div class="gacha-flash" id="gacha-flash"></div>

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
            <video id="gacha-video" autoplay muted playsinline preload="metadata" webkit-playsinline></video>
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
                    <span class="gacha-card-50-badge gacha-card-50-badge--win" id="card-50-win" style="display:none">Rate-Up Vinto!</span>
                    <span class="gacha-card-50-badge gacha-card-50-badge--loss" id="card-50-loss" style="display:none">Rate-Up Perso...</span>
                </div>

                <div class="gacha-card-info">
                    <h3 class="gacha-card-name" id="card-name">...</h3>
                    <div class="gacha-card-rarity-bar" id="card-rarity-bar"></div>
                    <span class="gacha-card-rarity-label" id="card-rarity-label">...</span>
                </div>

                <div class="gacha-card-actions">
                    <button class="gacha-action-btn gacha-action-btn--primary" id="btn-pull-again-custom" onclick="location.reload()">
                        <i class="fas fa-rotate-right"></i> Ripeti animazione
                    </button>
                    <button class="gacha-action-btn gacha-action-btn--secondary" id="btn-inventory" onclick="location.href='inventario'">
                        <i class="fas fa-layer-group"></i> Inventario
                    </button>
                </div>
            </div>
        </div>

        <div class="gacha-phase gacha-phase--multi" id="phase-multi" style="display:none">
            <div class="gacha-multi-grid" id="multi-grid"></div>
            <div class="gacha-multi-actions">
                <button class="gacha-action-btn gacha-action-btn--secondary" id="btn-multi-inventory">Inventario</button>
                <button class="gacha-action-btn gacha-action-btn--primary" id="btn-multi-again">Multi x10</button>
                <button class="gacha-action-btn gacha-action-btn--close" id="btn-multi-close">Chiudi</button>
            </div>
            <div class="gacha-multi-summary" id="multi-summary" style="display:none"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="/js/gacha-effects.js?v=5"></script>
    <script src="/js/gacha.js?v=23"></script>

    <script>
        const idPersonaggio = <?= json_encode($idPersonaggio) ?>;

        // Mock dell'oggetto di inizializzazione per prevenire errori di undefined in gacha.js
        window.GACHA_INIT = {
            soldi: 0,
            pityStandard: 0,
            pityEvento: 0,
            garantito: false,
            activeBannerId: 'standard'
        };

        async function initAnimazionePersonaggio() {
            if (!idPersonaggio || idPersonaggio === '0') {
                console.error("Nessun ID personaggio specificato.");
                return;
            }

            try {
                // Legge i dati del personaggio unicamente dal DB, simulando l'output dell'API Gacha
                const response = await fetch(`/api/get_character_from_id?id=${encodeURIComponent(idPersonaggio)}`);
                const pullData = await response.json();

                if (!pullData || pullData.error) {
                    console.error("Personaggio non trovato.");
                    return;
                }

                // Sfrutta la funzione openRevealWithData esportata da gacha.js 
                // per mostrare la UI video/particelle senza intaccare database o valute
                if (window.GachaUI && typeof window.GachaUI.openRevealWithData === 'function') {
                    window.GachaUI.openRevealWithData(pullData);
                } else {
                    console.error("Funzione window.GachaUI.openRevealWithData non trovata in gacha.js");
                }

            } catch (err) {
                console.error('Errore durante il recupero del personaggio:', err);
            }
        }

        // Trigger automatico al momento del caricamento
        document.addEventListener('DOMContentLoaded', initAnimazionePersonaggio);
    </script>
</body>

</html>