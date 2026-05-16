/**
 * gacha-effects.js — GoonLand Gacha System v1.0
 * Effetti visivi avanzati per rarità premium.
 *
 * Attivati da gacha.js tramite window.GachaEffects.
 * Completamente separati dalla logica pull.
 *
 * Rarità coperte:
 *   segreto  → intro viola: cerchio, lightning, testo giapponese
 *   theone   → intro blu: cinematic bars, energy burst, testo iconico
 *   speciale → rainbow animated overlay
 *   leggendario → golden shimmer + rising stars
 *   epico    → purple particle burst
 */

'use strict';

(function () {

  /* ════════════════════════════════════════════════════
     UTILITY
  ════════════════════════════════════════════════════ */

  function delay(ms) {
    return new Promise(r => setTimeout(r, ms));
  }

  function isMobile() {
    return window.innerWidth < 768;
  }

  /** Inietta CSS keyframes una sola volta nel <head> */
  const _injectedStyles = new Set();
  function injectStyle(id, css) {
    if (_injectedStyles.has(id)) return;
    _injectedStyles.add(id);
    const s = document.createElement('style');
    s.id = id;
    s.textContent = css;
    document.head.appendChild(s);
  }

  /** Crea overlay fullscreen temporaneo */
  function makeOverlay(zIndex = 10000, bg = '#000') {
    const el = document.createElement('div');
    el.style.cssText = `
      position:fixed;inset:0;
      background:${bg};
      z-index:${zIndex};
      display:flex;align-items:center;justify-content:center;
      overflow:hidden;opacity:0;pointer-events:none;
      transition:opacity 0.7s ease;
    `;
    document.body.appendChild(el);
    return el;
  }

  /** Rimuove elemento con fade */
  function fadeRemove(el, durationMs = 800) {
    return new Promise(resolve => {
      el.style.transition = `opacity ${durationMs}ms ease`;
      el.style.opacity = '0';
      setTimeout(() => { el.remove(); resolve(); }, durationMs);
    });
  }

  /* ════════════════════════════════════════════════════
     INJECT BASE KEYFRAMES
  ════════════════════════════════════════════════════ */
  injectStyle('gacha-fx-base', `
    @keyframes gfxPulse {
      0%,100% { transform:scale(1);   opacity:.8; }
      50%      { transform:scale(1.12);opacity:1; }
    }
    @keyframes gfxRingExpand {
      0%   { transform:translate(-50%,-50%) scale(.5); opacity:.7; }
      100% { transform:translate(-50%,-50%) scale(2);  opacity:0; }
    }
    @keyframes gfxLightning {
      0%,100% { opacity:0; transform:translate(-50%,-50%) rotate(var(--r,0deg)) scaleY(.2); }
      40%     { opacity:1; transform:translate(-50%,-50%) rotate(var(--r,0deg)) scaleY(1); }
      60%     { opacity:.6;transform:translate(-50%,-50%) rotate(var(--r,0deg)) scaleY(.8); }
    }
    @keyframes gfxFloat {
      0%,100% { transform:translateY(0) scale(1);   opacity:.6; }
      50%      { transform:translateY(-18px) scale(1.1);opacity:1; }
    }
    @keyframes gfxTextReveal {
      0%   { opacity:0; transform:scale(.4) translateY(20px); letter-spacing:.02em; }
      60%  { opacity:1; transform:scale(1.05) translateY(-4px); letter-spacing:.18em; }
      100% { opacity:1; transform:scale(1) translateY(0);      letter-spacing:.14em; }
    }
    @keyframes gfxCinemaBarTop {
      0%   { transform:translateY(-100%); }
      100% { transform:translateY(0); }
    }
    @keyframes gfxCinemaBarBot {
      0%   { transform:translateY(100%); }
      100% { transform:translateY(0); }
    }
    @keyframes gfxEnergyBurst {
      0%   { transform:translate(-50%,-50%) scale(0); opacity:1; }
      100% { transform:translate(-50%,-50%) scale(4); opacity:0; }
    }
    @keyframes gfxRainbowPan {
      0%   { background-position:0% 50%; }
      100% { background-position:200% 50%; }
    }
    @keyframes gfxGoldRise {
      0%   { transform:translateY(0)   scale(1);   opacity:1; }
      100% { transform:translateY(-80px) scale(.5); opacity:0; }
    }
    @keyframes gfxShimmer {
      0%   { background-position:-200% 0; }
      100% { background-position: 200% 0; }
    }
    @keyframes gfxFlickerIn {
      0%,100% { opacity:0; } 10%,90% { opacity:1; }
      20% { opacity:.3; } 30% { opacity:1; } 50% { opacity:.6; } 70% { opacity:1; }
    }
    @keyframes gfxRotate {
      to { transform:translate(-50%,-50%) rotate(360deg); }
    }
    @keyframes gfxSpiralIn {
      0%   { opacity:0; transform:rotate(0deg) scale(0); }
      60%  { opacity:1; transform:rotate(540deg) scale(1.1); }
      100% { opacity:1; transform:rotate(720deg) scale(1); }
    }
  `);

  /* ════════════════════════════════════════════════════
     EFFETTO SEGRETO
     Viola: cerchio pulsante, energy rings, lightning,
     particelle flottanti, testo giapponese misterioso
  ════════════════════════════════════════════════════ */
  async function playSecretoEffect() {
    const overlay = makeOverlay(10500, '#000');
    document.body.appendChild(overlay);

    // Container centrale
    const center = document.createElement('div');
    center.style.cssText = `
      position:relative;width:100%;height:100%;
      display:flex;align-items:center;justify-content:center;
    `;
    overlay.appendChild(center);

    // Glow circle
    const circle = document.createElement('div');
    circle.style.cssText = `
      position:absolute;
      width:${isMobile() ? 220 : 300}px;height:${isMobile() ? 220 : 300}px;
      border-radius:50%;
      background:radial-gradient(circle,
        rgba(168,85,247,1) 0%,
        rgba(109,40,217,0.9) 30%,
        rgba(139,92,246,0.7) 60%,
        transparent 100%);
      box-shadow:0 0 60px rgba(168,85,247,0.9),0 0 120px rgba(109,40,217,0.6);
      animation:gfxPulse 1.8s ease-in-out infinite;
      opacity:0;transition:opacity .6s;
    `;
    center.appendChild(circle);

    // Energy rings
    const ringCount = isMobile() ? 2 : 3;
    for (let i = 0; i < ringCount; i++) {
      const ring = document.createElement('div');
      const size = (isMobile() ? 180 : 240) + i * 80;
      ring.style.cssText = `
        position:absolute;left:50%;top:50%;
        width:${size}px;height:${size}px;
        border-radius:50%;
        border:1.5px solid rgba(168,85,247,${0.6 - i * 0.15});
        transform:translate(-50%,-50%) scale(.5);
        animation:gfxRingExpand 2.4s ease-out infinite ${i * 0.5}s;
        opacity:0;transition:opacity .5s ${0.4 + i * 0.15}s;
      `;
      center.appendChild(ring);
      setTimeout(() => ring.style.opacity = '1', 50);
    }

    // Lightning bolts
    const boltCount = isMobile() ? 5 : 8;
    for (let i = 0; i < boltCount; i++) {
      const bolt = document.createElement('div');
      const h = 90 + Math.random() * 60;
      const rot = (i / boltCount) * 360;
      bolt.style.cssText = `
        position:absolute;left:50%;top:50%;
        width:2px;height:${h}px;
        background:linear-gradient(to bottom,
          rgba(255,255,255,1) 0%,
          rgba(168,85,247,.9) 25%,
          rgba(139,92,246,.6) 65%,
          transparent 100%);
        transform-origin:50% 100%;
        --r:${rot}deg;
        animation:gfxLightning 1.6s ease-in-out ${0.8 + i * 0.08}s infinite;
        box-shadow:0 0 8px rgba(255,255,255,.7),0 0 16px rgba(168,85,247,.5);
        border-radius:1px;opacity:0;
      `;
      center.appendChild(bolt);
    }

    // Floating particles
    const partCount = isMobile() ? 8 : 14;
    for (let i = 0; i < partCount; i++) {
      const pt = document.createElement('div');
      const size = 3 + Math.random() * 5;
      pt.style.cssText = `
        position:absolute;
        width:${size}px;height:${size}px;border-radius:50%;
        background:radial-gradient(circle,rgba(255,255,255,.9),rgba(168,85,247,.8));
        left:${38 + Math.random() * 24}%;top:${38 + Math.random() * 24}%;
        box-shadow:0 0 8px rgba(168,85,247,.8);
        opacity:0;
        animation:gfxFloat ${2.5 + Math.random() * 1.5}s ease-in-out ${1.2 + Math.random() * 0.8}s infinite;
      `;
      center.appendChild(pt);
    }

    // Testo giapponese misterioso
    const txt = document.createElement('div');
    txt.textContent = 'オーラシグマゴド';
    txt.style.cssText = `
      position:absolute;
      color:#fff;
      font-size:${isMobile() ? '3.5rem' : '6rem'};
      font-weight:900;
      text-shadow:0 0 20px #9333ea,0 0 50px #6d28d9;
      opacity:0;
      letter-spacing:.14em;
      white-space:nowrap;
      animation:gfxTextReveal .9s cubic-bezier(.34,1.56,.64,1) 1.2s forwards;
    `;
    center.appendChild(txt);

    // Mostra overlay
    overlay.style.opacity = '1';
    await delay(100);
    circle.style.opacity = '1';

    // Durata totale effetto
    await delay(4200);
    await fadeRemove(overlay, 900);
  }

  /* ════════════════════════════════════════════════════
     EFFETTO THE ONE
     Cinematic bars, energy burst blu/bianco,
     testo épico, rotazione geometrica
  ════════════════════════════════════════════════════ */
  async function playTheOneEffect() {
    const overlay = makeOverlay(10500, '#000005');
    document.body.appendChild(overlay);

    // ── Cinematic black bars ────────────────────────
    const barH = isMobile() ? 60 : 80;
    const barTop = document.createElement('div');
    barTop.style.cssText = `
      position:absolute;top:0;left:0;right:0;height:${barH}px;
      background:#000;z-index:2;
      animation:gfxCinemaBarTop .6s ease forwards;
    `;
    const barBot = document.createElement('div');
    barBot.style.cssText = `
      position:absolute;bottom:0;left:0;right:0;height:${barH}px;
      background:#000;z-index:2;
      animation:gfxCinemaBarBot .6s ease forwards;
    `;
    overlay.appendChild(barTop);
    overlay.appendChild(barBot);

    // ── Geometric rotating rings ────────────────────
    const center = document.createElement('div');
    center.style.cssText = `
      position:absolute;inset:0;
      display:flex;align-items:center;justify-content:center;
    `;
    overlay.appendChild(center);

    const geoSizes = isMobile() ? [100, 160, 220] : [140, 220, 300];
    geoSizes.forEach((size, i) => {
      const ring = document.createElement('div');
      const speed = 8 + i * 4;
      const dir   = i % 2 === 0 ? 1 : -1;
      ring.style.cssText = `
        position:absolute;left:50%;top:50%;
        width:${size}px;height:${size}px;
        border-radius:50%;
        border:1px solid rgba(96,165,250,${0.5 - i * 0.1});
        transform:translate(-50%,-50%);
        animation:gfxRotate ${speed}s linear ${dir === 1 ? '' : 'reverse'} infinite;
        box-shadow:0 0 12px rgba(96,165,250,0.4);
        opacity:0;transition:opacity .4s ${0.3 + i * 0.2}s;
      `;
      // Aggiunge 4 "nodi" ai quadranti
      for (let n = 0; n < 4; n++) {
        const node = document.createElement('div');
        const angle = (n / 4) * 360;
        const rad   = angle * Math.PI / 180;
        const nx    = 50 + 50 * Math.cos(rad);
        const ny    = 50 + 50 * Math.sin(rad);
        node.style.cssText = `
          position:absolute;
          width:6px;height:6px;border-radius:50%;
          background:#60a5fa;
          box-shadow:0 0 6px #60a5fa,0 0 12px rgba(96,165,250,.6);
          left:calc(${nx}% - 3px);top:calc(${ny}% - 3px);
        `;
        ring.appendChild(node);
      }
      center.appendChild(ring);
      setTimeout(() => ring.style.opacity = '1', 50);
    });

    // ── Core burst ──────────────────────────────────
    const core = document.createElement('div');
    core.style.cssText = `
      position:absolute;left:50%;top:50%;
      width:80px;height:80px;
      border-radius:50%;
      background:radial-gradient(circle,#fff 0%,#60a5fa 40%,transparent 80%);
      box-shadow:0 0 40px #60a5fa,0 0 80px rgba(96,165,250,.6),0 0 160px rgba(29,78,216,.4);
      animation:gfxPulse 1.4s ease-in-out infinite;
      transform:translate(-50%,-50%);
      opacity:0;transition:opacity .5s;
    `;
    center.appendChild(core);

    // ── Energy burst rings ──────────────────────────
    for (let i = 0; i < 3; i++) {
      const burst = document.createElement('div');
      burst.style.cssText = `
        position:absolute;left:50%;top:50%;
        width:40px;height:40px;border-radius:50%;
        background:radial-gradient(circle,rgba(96,165,250,.8) 0%,transparent 80%);
        animation:gfxEnergyBurst 1.8s ease-out ${0.6 + i * 0.5}s infinite;
      `;
      center.appendChild(burst);
    }

    // ── Testo THE ONE ───────────────────────────────
    const txt1 = document.createElement('div');
    txt1.textContent = 'THE ONE';
    txt1.style.cssText = `
      position:absolute;
      font-size:${isMobile() ? '3rem' : '5.5rem'};
      font-weight:900;
      color:#fff;
      letter-spacing:.14em;
      text-shadow:0 0 20px #60a5fa,0 0 50px rgba(96,165,250,.6);
      opacity:0;
      background:linear-gradient(90deg,#60a5fa,#fff,#93c5fd,#fff,#60a5fa);
      background-size:300% 100%;
      -webkit-background-clip:text;
      -webkit-text-fill-color:transparent;
      background-clip:text;
      animation:
        gfxTextReveal .8s cubic-bezier(.34,1.56,.64,1) 1s forwards,
        gfxShimmer 2.5s linear 1.8s infinite;
    `;
    center.appendChild(txt1);

    const txt2 = document.createElement('div');
    txt2.textContent = '最強 • IL PIÙ FORTE';
    txt2.style.cssText = `
      position:absolute;
      margin-top:${isMobile() ? '90px' : '130px'};
      font-size:${isMobile() ? '.8rem' : '1rem'};
      font-weight:600;
      letter-spacing:.3em;
      color:rgba(148,163,184,.7);
      opacity:0;
      animation:gfxTextReveal .6s ease 1.8s forwards;
    `;
    center.appendChild(txt2);

    // ── Mostra tutto ────────────────────────────────
    overlay.style.opacity = '1';
    await delay(150);
    core.style.opacity = '1';

    await delay(4800);
    await fadeRemove(overlay, 1000);
  }

  /* ════════════════════════════════════════════════════
     EFFETTO SPECIALE
     Rainbow overlay fullscreen, prisma rotante
  ════════════════════════════════════════════════════ */
  async function playSpecialeEffect() {
    const overlay = makeOverlay(10500, 'transparent');
    document.body.appendChild(overlay);

    // Rainbow BG animato
    const rainbow = document.createElement('div');
    rainbow.style.cssText = `
      position:absolute;inset:0;
      background:linear-gradient(45deg,
        #ff004c,#ff7a00,#fff300,#35ff00,
        #00ffd5,#0077ff,#7a00ff,#ff00d4,#ff004c);
      background-size:400% 400%;
      opacity:.18;
      animation:gfxRainbowPan 3s linear infinite;
    `;
    overlay.appendChild(rainbow);

    // Prisma al centro
    const prism = document.createElement('div');
    prism.style.cssText = `
      position:absolute;left:50%;top:50%;
      width:${isMobile() ? 140 : 200}px;height:${isMobile() ? 140 : 200}px;
      border-radius:50%;
      background:conic-gradient(#ff004c,#ff7a00,#fff300,#35ff00,#00ffd5,#0077ff,#7a00ff,#ff00d4,#ff004c);
      transform:translate(-50%,-50%);
      opacity:.6;
      animation:gfxRotate 3s linear infinite;
      filter:blur(8px);
    `;
    overlay.appendChild(prism);

    // Testo SPECIALE
    const txt = document.createElement('div');
    txt.textContent = 'SPECIALE!';
    txt.style.cssText = `
      position:absolute;
      font-size:${isMobile() ? '2.5rem' : '4rem'};
      font-weight:900;
      color:#fff;
      text-shadow:0 0 20px #fff,0 0 40px rgba(255,255,255,.5);
      letter-spacing:.14em;
      opacity:0;
      animation:gfxFlickerIn 1.2s ease .3s forwards;
    `;
    overlay.appendChild(txt);

    overlay.style.opacity = '1';
    await delay(2200);
    await fadeRemove(overlay, 700);
  }

  /* ════════════════════════════════════════════════════
     EFFETTO LEGGENDARIO
     Golden shimmer, rising gold stars
  ════════════════════════════════════════════════════ */
  async function playLeggendarioEffect() {
    const overlay = makeOverlay(10500, 'rgba(0,0,0,0)');
    overlay.style.pointerEvents = 'none';
    document.body.appendChild(overlay);

    // FIX 5 — Shimmer dorato: barra larga e solida che attraversa lo schermo
    const shimmer = document.createElement('div');
    shimmer.style.cssText = `
      position:absolute;
      top:0;bottom:0;
      width:55%;
      background:linear-gradient(
        90deg,
        transparent 0%,
        rgba(251,191,36,.08) 15%,
        rgba(255,255,255,.25) 40%,
        rgba(251,191,36,.45) 50%,
        rgba(255,255,255,.25) 60%,
        rgba(251,191,36,.08) 85%,
        transparent 100%
      );
      left:-55%;
      animation:gfxLegendaryShimmer 1.1s ease forwards;
      pointer-events:none;
    `;
    overlay.appendChild(shimmer);

    // Glow ovale dorato al centro
    const glow = document.createElement('div');
    glow.style.cssText = `
      position:absolute;left:50%;top:50%;
      width:${isMobile()?280:420}px;height:${isMobile()?120:180}px;
      transform:translate(-50%,-50%);
      background:radial-gradient(ellipse,rgba(251,191,36,.5) 0%,rgba(251,191,36,.2) 40%,transparent 70%);
      border-radius:50%;
      filter:blur(${isMobile()?18:28}px);
      opacity:0;transition:opacity .3s;
    `;
    overlay.appendChild(glow);

    // Rising gold stars
    const starCount = isMobile() ? 16 : 32;
    for (let i = 0; i < starCount; i++) {
      const s = document.createElement('div');
      const size = 6 + Math.random() * 12;
      const x    = 5 + Math.random() * 90;
      const dur  = 700 + Math.random() * 700;
      s.textContent = '★';
      s.style.cssText = `
        position:absolute;
        left:${x}%;bottom:${5+Math.random()*20}%;
        font-size:${size}px;
        color:#fbbf24;
        text-shadow:0 0 10px #fbbf24,0 0 22px rgba(251,191,36,.7);
        animation:gfxGoldRise ${dur}ms ease ${Math.random()*300}ms forwards;
        opacity:1;
      `;
      overlay.appendChild(s);
    }

    overlay.style.opacity = '1';
    await delay(60);
    glow.style.opacity = '1';
    await delay(1400);
    await fadeRemove(overlay, 600);
  }

  /* ════════════════════════════════════════════════════
     EFFETTO EPICO
     Viola burst semplice, spiral in
  ════════════════════════════════════════════════════ */
  async function playEpicoEffect() {
    const overlay = makeOverlay(10500, 'transparent');
    overlay.style.pointerEvents = 'none';
    document.body.appendChild(overlay);

    // FIX 5 — Centrato con transform
    const spiral = document.createElement('div');
    const sz = isMobile() ? 150 : 220;
    spiral.style.cssText = `
      position:absolute;
      left:50%;top:50%;
      width:${sz}px;height:${sz}px;
      margin-left:${-sz/2}px;margin-top:${-sz/2}px;
      border-radius:50%;
      border:3px solid rgba(192,132,252,.8);
      box-shadow:0 0 40px rgba(192,132,252,.6),
                 0 0 80px rgba(192,132,252,.2),
                 inset 0 0 30px rgba(192,132,252,.15);
      animation:gfxSpiralIn .9s cubic-bezier(.34,1.56,.64,1) forwards;
    `;
    overlay.appendChild(spiral);

    // Particelle viola
    for (let i = 0; i < (isMobile()?6:12); i++) {
      const pt = document.createElement('div');
      const angle = (i / (isMobile()?6:12)) * Math.PI * 2;
      const dist  = sz/2 + 20 + Math.random()*40;
      pt.style.cssText = `
        position:absolute;left:50%;top:50%;
        width:5px;height:5px;border-radius:50%;
        background:#c084fc;
        box-shadow:0 0 8px #c084fc;
        margin-left:${Math.cos(angle)*dist-2.5}px;
        margin-top:${Math.sin(angle)*dist-2.5}px;
        opacity:0;
        animation:gfxSpiralIn ${0.6+Math.random()*.4}s ease ${0.2+i*.05}s forwards;
      `;
      overlay.appendChild(pt);
    }

    overlay.style.opacity = '1';
    await delay(1100);
    await fadeRemove(overlay, 400);
  }

  /* ════════════════════════════════════════════════════
     EFFETTO RARO
     Piccolo flash azzurro
  ════════════════════════════════════════════════════ */
  async function playRaroEffect() {
    const overlay = makeOverlay(10500, 'rgba(56,189,248,0)');
    overlay.style.pointerEvents = 'none';
    document.body.appendChild(overlay);

    const flash = document.createElement('div');
    flash.style.cssText = `
      position:absolute;inset:0;
      background:radial-gradient(circle at center,rgba(56,189,248,.25) 0%,transparent 60%);
      opacity:0;transition:opacity .25s;
    `;
    overlay.appendChild(flash);

    overlay.style.opacity = '1';
    await delay(30);
    flash.style.opacity = '1';
    await delay(350);
    flash.style.opacity = '0';
    await delay(280);
    overlay.remove();
  }

  /* ════════════════════════════════════════════════════
     DISPATCHER PRINCIPALE
     Chiamato da gacha.js prima del reveal card.
     Restituisce una Promise che si risolve quando
     l'effetto è completato.
  ════════════════════════════════════════════════════ */
  async function playRarityEffect(rarity) {
    const r = String(rarity ?? '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .trim();

    switch (r) {
      // FIX 3: segreto/theone gestiti da gacha.js con fade+video, nessun effetto qui
      case 'theone':
      case 'segreto':    return Promise.resolve();
      case 'speciale':   return playSpecialeEffect();
      case 'leggendario':return playLeggendarioEffect();
      case 'epico':      return playEpicoEffect();
      case 'raro':       return playRaroEffect();
      default:           return Promise.resolve();
    }
  }

  /* ════════════════════════════════════════════════════
     SCROLL LOCK (helper per l'overlay gacha)
  ════════════════════════════════════════════════════ */
  let _touchHandler = null;
  function lockScroll() {
    document.body.style.overflow = 'hidden';
    _touchHandler = e => e.preventDefault();
    document.addEventListener('touchmove', _touchHandler, { passive: false });
  }
  function unlockScroll() {
    document.body.style.overflow = '';
    if (_touchHandler) {
      document.removeEventListener('touchmove', _touchHandler);
      _touchHandler = null;
    }
  }

  /* ════════════════════════════════════════════════════
     ESPOSIZIONE PUBBLICA
  ════════════════════════════════════════════════════ */
  window.GachaEffects = {
    play:         playRarityEffect,
    lockScroll,
    unlockScroll,
    // Singoli effetti (per debug/admin)
    playTheOne:     playTheOneEffect,
    playSecreto:    playSecretoEffect,
    playSpeciale:   playSpecialeEffect,
    playLeggendario:playLeggendarioEffect,
    playEpico:      playEpicoEffect,
    playRaro:       playRaroEffect,
  };

})();
