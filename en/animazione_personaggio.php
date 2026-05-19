<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

// ── Carica dati personaggio dal DB (no fetch JS, no scrittura DB) ──────────
$idPersonaggio = (int)($_GET['id_personaggio'] ?? 0);
$charData = null;

if ($idPersonaggio > 0) {
    $stmt = $mysqli->prepare(
        'SELECT id, nome, rarità, img_url, video_url, audio_url
         FROM personaggi WHERE id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $idPersonaggio);
    $stmt->execute();
    $charData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Se il personaggio non esiste mostra errore
if (!$charData) {
    $errorMsg = $idPersonaggio > 0
        ? "Personaggio con ID {$idPersonaggio} non trovato."
        : "Nessun ID personaggio specificato. Usa ?id_personaggio=X";
}

$charJson = $charData ? json_encode($charData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null';
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../includes/head-import.php'; ?>
    <link rel="stylesheet" href="/css/lootbox.css?v=8.0.6" />
    <link rel="stylesheet" href="/css/gacha.css?v=3.1" />
    <title>Cripsum™ – Animazione personaggio</title>
    <style>
        /* ── Override: la pagina è solo l'animazione, niente layout gacha ── */
        body.anim-page {
            background: #080810;
            overflow: hidden;
            margin: 0;
            min-height: 100dvh;
        }

        /* Overlay sempre visibile e occupa tutta la viewport */
        body.anim-page .gacha-overlay {
            position: fixed;
            inset: 0;
            opacity: 1 !important;
            pointer-events: all !important;
            display: flex;
        }

        /* ── Bottoni finali nella fase card ── */
        #anim-actions {
            display: none;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            width: 100%;
            max-width: 320px;
            margin-top: 4px;
        }

        #anim-actions.is-visible {
            display: flex;
        }

        /* ── Schermata errore ── */
        #anim-error {
            position: fixed;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            background: #080810;
            color: #fff;
            z-index: 99999;
            padding: 32px;
            text-align: center;
        }

        #anim-error h2 {
            color: #f87171;
            margin: 0;
        }

        #anim-error p {
            color: rgba(255, 255, 255, .55);
            margin: 0;
        }

        #anim-error a {
            padding: 10px 24px;
            background: rgba(255, 255, 255, .08);
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 24px;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }

        /* ── Tag "ANTEPRIMA" per admin (niente dati pull, solo animazione) ── */
        #anim-preview-badge {
            position: fixed;
            top: 12px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 99998;
            padding: 3px 12px;
            background: rgba(251, 191, 36, .15);
            border: 1px solid rgba(251, 191, 36, .4);
            border-radius: 20px;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #fbbf24;
            pointer-events: none;
        }

        /* ── Bottone muto visibile su mobile dopo avvio video ── */
        .anim-unmute-hint {
            font-size: .78rem;
            color: rgba(255, 255, 255, .5);
            text-align: center;
            margin-top: 4px;
        }
    </style>
</head>

<body class="anim-page">

    <?php if (!empty($errorMsg)): ?>
        <!-- ══ ERRORE ══════════════════════════════════════════════════════════ -->
        <div id="anim-error">
            <h2><i class="fas fa-circle-exclamation"></i> Personaggio non trovato</h2>
            <p><?= htmlspecialchars($errorMsg) ?></p>
            <a href="javascript:history.back()"><i class="fas fa-arrow-left"></i> Torna indietro</a>
        </div>

    <?php else: ?>
        <!-- ══ BADGE ANTEPRIMA ═════════════════════════════════════════════════ -->
        <div id="anim-preview-badge"><i class="fas fa-eye"></i> Preview animation</div>

        <!-- ══ STELLE SFONDO ══════════════════════════════════════════════════ -->
        <div class="stars" id="stars"></div>

        <!-- ══════════════════════════════════════════════════════════════════
     OVERLAY GACHA — struttura identica a lootbox.php
     (gacha.css si aspetta esattamente questi ID e classi)
══════════════════════════════════════════════════════════════════ -->
        <div class="gacha-overlay" id="gacha-overlay"
            role="dialog" aria-modal="true" aria-label="Animazione personaggio">

            <div class="gacha-overlay-bg"></div>
            <div class="gacha-glow-burst" id="gacha-glow-burst"></div>
            <div class="gacha-stars-layer" id="overlay-stars"></div>
            <div class="gacha-particles-layer" id="gacha-particles"></div>
            <div class="gacha-flash" id="gacha-flash"></div>

            <!-- Fase 1: Orb di caricamento -->
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

            <!-- Fase 2: Video (segreto / theone) -->
            <div class="gacha-phase gacha-phase--video" id="phase-video" style="display:none">
                <video id="gacha-video" playsinline preload="metadata"
                    webkit-playsinline></video>
                <button class="gacha-video-unmute" id="video-unmute-btn" style="display:none">
                    <i class="fas fa-volume-xmark"></i> Tap per audio
                </button>
            </div>

            <!-- Fase 3: Card reveal -->
            <div class="gacha-phase gacha-phase--card" id="phase-card" style="display:none">
                <div class="gacha-card" id="gacha-card">
                    <div class="gacha-card-bg-glow" id="card-bg-glow"></div>
                    <div class="gacha-card-frame" id="card-frame">
                        <div class="gacha-card-img-wrap">
                            <img id="card-img" src="/img/cassa.png" alt="Personaggio"
                                draggable="false" onerror="this.src='/img/cassa.png'">
                        </div>
                        <div class="gacha-card-img-shine"></div>
                        <!-- Nessun badge NEW/50-50 in modalità anteprima -->
                        <span class="gacha-card-new-badge" id="card-new-badge" style="display:none">NEW!</span>
                        <span class="gacha-card-50-badge gacha-card-50-badge--win" id="card-50-win" style="display:none"></span>
                        <span class="gacha-card-50-badge gacha-card-50-badge--loss" id="card-50-loss" style="display:none"></span>
                    </div>
                    <div class="gacha-card-details">
                        <div class="gacha-card-rarity-bar" id="card-rarity-bar"></div>
                        <p class="gacha-card-rarity-label" id="card-rarity-label">—</p>
                        <h2 class="gacha-card-name" id="card-name">—</h2>
                    </div>
                </div>

                <!-- Azioni post-animazione -->
                <div id="anim-actions">
                    <a href="?id_personaggio=<?= $idPersonaggio ?>" class="gacha-btn gacha-btn--primary">
                        <i class="fas fa-rotate-right"></i> Watch Again
                    </a>
                    <a href="/inventario" class="gacha-btn gacha-btn--ghost">
                        <i class="fas fa-layer-group"></i> Inventory
                    </a>
                    <button class="gacha-btn gacha-btn--ghost" onclick="history.back()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                </div>
            </div>

        </div><!-- /gacha-overlay -->

        <!-- Audio -->
        <audio id="gacha-audio" preload="none"></audio>

        <!-- ══════════════════════════════════════════════════════════════════
     SCRIPTS
══════════════════════════════════════════════════════════════════ -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            crossorigin="anonymous"></script>

        <!-- gacha-effects.js: GachaEffects (speciale, leggendario, epico, raro) -->
        <script src="/js/gacha-effects.js?v=3.1"></script>

        <script>
            /* ═══════════════════════════════════════════════════════════════════════
   AnimazionePersonaggio — motore di reveal standalone
   Replica la logica di revealPull() / showCard() / fadeToBlackThenVideo()
   di gacha.js senza richiedere l'intero sistema di lootbox.
   NON esegue fetch al DB, NON salva nulla.
═══════════════════════════════════════════════════════════════════════ */

            'use strict';

            (function() {

                /* ── Dati personaggio passati server-side ─────────────────────────── */
                const CHAR = <?= $charJson ?>;

                /* ── Configurazione ────────────────────────────────────────────────── */
                const RARITY_VIDEO = new Set(['segreto', 'theone']);
                const RARITY_EFFECTS = new Set(['leggendario', 'speciale', 'epico', 'raro']);
                const VIDEO_CARD_DELAY_MS = 12000; // card appare sopra il video dopo 12s

                const RARITY_COLORS = {
                    comune: '#9ca3af',
                    raro: '#38bdf8',
                    epico: '#c084fc',
                    leggendario: '#fbbf24',
                    speciale: '#ffffff',
                    segreto: '#a855f7',
                    theone: '#60a5fa',
                };

                /* ── DOM refs ─────────────────────────────────────────────────────── */
                const $ = id => document.getElementById(id);

                const overlay = $('gacha-overlay');
                const glowBurst = $('gacha-glow-burst');
                const overlayStars = $('overlay-stars');
                const particlesLayer = $('gacha-particles');

                const phaseOpening = $('phase-opening');
                const phaseVideo = $('phase-video');
                const phaseCard = $('phase-card');

                const videoEl = $('gacha-video');
                const videoUnmuteBtn = $('video-unmute-btn');

                const gachaCard = $('gacha-card');
                const cardBgGlow = $('card-bg-glow');
                const cardImg = $('card-img');
                const cardRarityBar = $('card-rarity-bar');
                const cardRarityLabel = $('card-rarity-label');
                const cardName = $('card-name');

                const animActions = $('anim-actions');
                const audioEl = $('gacha-audio');

                /* ── Utility ──────────────────────────────────────────────────────── */
                const delay = ms => new Promise(r => setTimeout(r, ms));

                function normalizeRarity(r) {
                    return String(r ?? 'comune')
                        .toLowerCase()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .replace(/[\s_-]+/g, '')
                        .trim();
                }

                function escapeHtml(s) {
                    return String(s ?? '')
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                }

                /* ── Stars ────────────────────────────────────────────────────────── */
                function createStars(container, count) {
                    if (!container) return;
                    const frag = document.createDocumentFragment();
                    for (let i = 0; i < count; i++) {
                        const s = document.createElement('div');
                        s.className = 'star';
                        s.style.cssText = `left:${Math.random()*100}%;top:${Math.random()*100}%;` +
                            `animation-delay:${Math.random()*4}s;` +
                            `animation-duration:${2+Math.random()*3}s;`;
                        frag.appendChild(s);
                    }
                    container.appendChild(frag);
                }

                /* ── Rarity overlay ───────────────────────────────────────────────── */
                function setRarityOnOverlay(rarity) {
                    overlay.setAttribute('data-rarity', rarity);
                    const color = RARITY_COLORS[rarity] ?? '#fff';
                    overlay.style.setProperty('--banner-accent', color);
                    overlay.style.setProperty('--banner-accent-glow', color + '55');
                    glowBurst.classList.toggle('is-rainbow', rarity === 'speciale');
                }

                /* ── Phase switcher ───────────────────────────────────────────────── */
                function showPhase(p) {
                    phaseOpening.style.display = p === 'opening' ? 'flex' : 'none';
                    phaseVideo.style.display = p === 'video' ? 'flex' : 'none';
                    phaseCard.style.display = p === 'card' ? 'flex' : 'none';
                }

                /* ── Particles ────────────────────────────────────────────────────── */
                function spawnParticles(color, rarity) {
                    const mob = window.innerWidth < 768;
                    const counts = {
                        theone: mob ? 30 : 70,
                        segreto: mob ? 25 : 60,
                        speciale: mob ? 20 : 50,
                        leggendario: mob ? 15 : 40,
                        epico: mob ? 10 : 28,
                    };
                    const count = counts[rarity] ?? (mob ? 6 : 15);
                    const frag = document.createDocumentFragment();
                    for (let i = 0; i < count; i++) {
                        const p = document.createElement('div');
                        const sz = 3 + Math.random() * 5;
                        const ang = Math.random() * Math.PI * 2;
                        const d = 80 + Math.random() * 200;
                        const dur = 600 + Math.random() * 900;
                        p.className = 'gacha-particle';
                        p.style.cssText = `width:${sz}px;height:${sz}px;background:${color};` +
                            `box-shadow:0 0 ${sz*2}px ${color};` +
                            `--px:${Math.cos(ang)*d}px;--py:${Math.sin(ang)*d}px;--dur:${dur}ms;`;
                        frag.appendChild(p);
                        setTimeout(() => p.remove(), dur + 100);
                    }
                    particlesLayer.appendChild(frag);
                }

                /* ── Audio ────────────────────────────────────────────────────────── */
                function playAudio(url) {
                    if (!url || !audioEl) return;
                    audioEl.src = url.startsWith('/') ? url : `/audio/${url}`;
                    audioEl.currentTime = 0;
                    audioEl.volume = 0.8;
                    audioEl.play().catch(() => {});
                }

                /* ══════════════════════════════════════════════════════════════════
                   SHOW CARD — identica a gacha.js showCard(), senza i bottoni pull
                ══════════════════════════════════════════════════════════════════ */
                async function showCard(charObj) {
                    const rarity = normalizeRarity(charObj.rarità);
                    const color = RARITY_COLORS[rarity] ?? '#fff';

                    cardImg.src = charObj.img_url ? `/img/${charObj.img_url}` : '/img/cassa.png';
                    cardImg.alt = escapeHtml(charObj.nome ?? '');
                    cardName.textContent = escapeHtml(charObj.nome ?? '—');
                    cardRarityBar.className = `gacha-card-rarity-bar rarity-${rarity}`;
                    cardRarityLabel.textContent = (charObj.rarità ?? '—').toUpperCase();
                    cardBgGlow.style.background = `radial-gradient(circle,${color}55 0%,transparent 70%)`;

                    spawnParticles(color, rarity);
                    showPhase('card');

                    gachaCard.classList.remove('is-revealed', 'is-idle');
                    await delay(40);
                    gachaCard.classList.add('is-revealed');
                    await delay(620);
                    gachaCard.classList.add('is-idle');

                    // Mostra azioni dopo che la card è idle
                    animActions.classList.add('is-visible');
                }

                /* ══════════════════════════════════════════════════════════════════
                   CARD OVER VIDEO — card sovrapposta al video in corso
                   Replica showCardOverVideo() di gacha.js
                ══════════════════════════════════════════════════════════════════ */
                async function showCardOverVideo(charObj) {
                    const rarity = normalizeRarity(charObj.rarità);
                    const color = RARITY_COLORS[rarity] ?? '#fff';
                    const imgSrc = charObj.img_url ? `/img/${charObj.img_url}` : '/img/cassa.png';

                    // Layer trasparente sopra il video
                    const cardOverlay = document.createElement('div');
                    cardOverlay.id = 'card-over-video-anim';
                    cardOverlay.style.cssText = [
                        'position:absolute;inset:0;z-index:15;',
                        'display:flex;flex-direction:column;align-items:center;justify-content:center;',
                        'gap:24px;padding:20px 20px calc(20px + var(--safe-bot,0px));',
                        'opacity:0;transition:opacity .7s ease;background:transparent;',
                    ].join('');

                    cardOverlay.innerHTML = `
      <div class="gacha-card" id="cov-card-anim">
        <div class="gacha-card-bg-glow"
             style="background:radial-gradient(circle,${color}55 0%,transparent 70%)"></div>
        <div class="gacha-card-frame"
             style="border-color:${color}88;box-shadow:0 0 40px ${color}44">
          <div class="gacha-card-img-wrap">
            <img src="${imgSrc}" alt="${escapeHtml(charObj.nome)}" draggable="false"
                 onerror="this.src='/img/cassa.png'"
                 style="width:100%;height:100%;object-fit:cover;object-position:top">
          </div>
          <div class="gacha-card-img-shine"></div>
        </div>
        <div class="gacha-card-details">
          <div class="gacha-card-rarity-bar rarity-${rarity}"></div>
          <p class="gacha-card-rarity-label" style="color:${color}">
            ${escapeHtml(charObj.rarità.toUpperCase())}
          </p>
          <h2 class="gacha-card-name">${escapeHtml(charObj.nome ?? '—')}</h2>
        </div>
      </div>
      <div id="cov-anim-actions" class="gacha-overlay-actions" style="display:none">
        <a href="?id_personaggio=<?= $idPersonaggio ?>"
           class="gacha-btn gacha-btn--primary">
          <i class="fas fa-rotate-right"></i> Ripeti animazione
        </a>
        <a href="/inventario" class="gacha-btn gacha-btn--ghost">
          <i class="fas fa-layer-group"></i> Inventario
        </a>
        <button class="gacha-btn gacha-btn--ghost" onclick="history.back()">
          <i class="fas fa-arrow-left"></i> Indietro
        </button>
      </div>
    `;

                    phaseVideo.appendChild(cardOverlay);
                    spawnParticles(color, rarity);

                    await delay(40);
                    cardOverlay.style.opacity = '1';

                    const covCard = cardOverlay.querySelector('#cov-card-anim');
                    covCard.classList.remove('is-revealed', 'is-idle');
                    await delay(80);
                    covCard.classList.add('is-revealed');
                    await delay(620);
                    covCard.classList.add('is-idle');

                    // Mostra azioni
                    const covActions = cardOverlay.querySelector('#cov-anim-actions');
                    if (covActions) covActions.style.display = 'flex';
                }

                /* ══════════════════════════════════════════════════════════════════
                   VIDEO REVEAL — identico a playRevealVideoWithEarlyCard() di gacha.js
                   Card appare dopo VIDEO_CARD_DELAY_MS con video ancora in riproduzione.
                ══════════════════════════════════════════════════════════════════ */
                async function playVideoReveal(videoUrl, charObj) {
                    videoEl.src = videoUrl.startsWith('/') ? videoUrl : `/vid/${videoUrl}`;
                    videoEl.muted = false;
                    videoEl.volume = 1;
                    videoEl.load();
                    videoUnmuteBtn.style.display = 'none';

                    let cardShown = false;

                    // Tap per sbloccare audio su mobile/Safari
                    const unmuteHandler = () => {
                        videoEl.muted = false;
                        videoEl.volume = 1;
                        videoEl.play().catch(() => {});
                        videoUnmuteBtn.style.display = 'none';
                    };
                    videoUnmuteBtn.addEventListener('click', unmuteHandler, {
                        once: true
                    });

                    // Timer: card appare dopo VIDEO_CARD_DELAY_MS
                    const cardTimer = setTimeout(async () => {
                        if (!cardShown) {
                            cardShown = true;
                            await showCardOverVideo(charObj);
                        }
                    }, VIDEO_CARD_DELAY_MS);

                    return new Promise(resolve => {
                        let resolved = false;

                        const done = async () => {
                            if (resolved) return;
                            resolved = true;
                            clearTimeout(cardTimer);
                            clearTimeout(safeguard);
                            videoEl.removeEventListener('ended', done);
                            videoEl.removeEventListener('error', done);

                            if (!cardShown) {
                                // Video finito prima dei 12s: mostra card normale
                                cardShown = true;
                                videoEl.pause();
                                videoEl.src = '';
                                await showCard(charObj);
                            } else {
                                // Card già visibile sopra il video: metti in loop il video (come nel vecchio sistema)
                                videoEl.loop = true;
                                videoEl.play().catch(() => {});
                            }
                            // Nota: la Promise si risolve qui ma il video continua in loop
                            // sotto la card — comportamento identico al vecchio theone/tung.
                            resolve();
                        };

                        const safeguard = setTimeout(done, 90000);
                        videoEl.addEventListener('ended', done, {
                            once: true
                        });
                        videoEl.addEventListener('error', done, {
                            once: true
                        });

                        const pp = videoEl.play();
                        if (pp) {
                            pp.catch(() => {
                                // Autoplay bloccato → mostra bottone unmute
                                videoEl.muted = true;
                                videoUnmuteBtn.style.display = 'flex';
                                videoEl.play().catch(() => done());
                            });
                        }
                    });
                }

                /* ══════════════════════════════════════════════════════════════════
                   FADE TO BLACK THEN VIDEO
                   Replica fadeToBlackThenVideo() di gacha.js
                ══════════════════════════════════════════════════════════════════ */
                async function fadeToBlackThenVideo(videoUrl, charObj) {
                    const velo = document.createElement('div');
                    velo.style.cssText = [
                        'position:absolute;inset:0;background:#000;',
                        'z-index:30;opacity:0;pointer-events:none;',
                        'transition:opacity .8s ease;',
                    ].join('');
                    overlay.appendChild(velo);

                    await delay(20);
                    velo.style.opacity = '1';
                    await delay(850);

                    showPhase('video');
                    velo.style.opacity = '0';
                    setTimeout(() => velo.remove(), 800);

                    await playVideoReveal(videoUrl, charObj);
                }

                /* ══════════════════════════════════════════════════════════════════
                   REVEAL PRINCIPALE — adatta revealPull() di gacha.js
                ══════════════════════════════════════════════════════════════════ */
                async function revealCharacter(charObj) {
                    const rarity = normalizeRarity(charObj.rarità);
                    const hasVideo = charObj.video_url && RARITY_VIDEO.has(rarity);

                    setRarityOnOverlay(rarity);

                    if (hasVideo) {
                        // segreto/theone con video: fade → video → card sovrapposta dopo 12s
                        await fadeToBlackThenVideo(charObj.video_url, charObj);
                    } else {
                        // Effetto visivo (speciale, leggendario, epico, raro) poi card
                        if (window.GachaEffects && RARITY_EFFECTS.has(rarity)) {
                            await GachaEffects.play(rarity);
                        }
                        setRarityOnOverlay(rarity); // ri-set dopo effetto (per sicurezza)
                        await showCard(charObj);
                    }
                }

                /* ══════════════════════════════════════════════════════════════════
                   INIT — avvia tutto al DOMContentLoaded
                ══════════════════════════════════════════════════════════════════ */
                async function init() {
                    // Stelle sfondo
                    createStars(document.getElementById('stars'), 100);
                    createStars(overlayStars, 60);

                    // Overlay visibile da subito (CSS lo forza già via .anim-page)
                    setRarityOnOverlay('comune');
                    showPhase('opening');

                    if (!CHAR) return; // errore già gestito server-side

                    const rarity = normalizeRarity(CHAR.rarità);

                    // Audio: non per video (lo gestisce il video stesso)
                    if (!RARITY_VIDEO.has(rarity)) {
                        playAudio(CHAR.audio_url);
                    }

                    // Orb intro: breve pausa poi via
                    await delay(900);

                    await revealCharacter(CHAR);
                }

                // Boot
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', init);
                } else {
                    init();
                }

            })();
        </script>

    <?php endif; ?>
</body>

</html>