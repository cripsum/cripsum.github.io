/**
 * gacha.js — GoonLand Gacha System v2.0
 * Fix: navbar offset tab, audio stop, video segreto/theone solo nero+video,
 *      audio anticipato, animazioni rarità, card UI, skip, cronologia, leaderboard.
 */

'use strict';

(function () {

  /* ══════════════════════════════════════════════════════
     CONFIG
  ══════════════════════════════════════════════════════ */
  const API_PULL = '/api/api_gacha_pull';

  // Rarità con video custom (fade nero → video, nessun effetto, nessun audio separato)
  const RARITY_VIDEO   = new Set(['segreto', 'theone']);
  // Rarità non skippabili mai
  const RARITY_NO_SKIP = new Set(['segreto', 'theone', 'speciale']);
  // Rarità con effetti premium (solo queste, NON segreto/theone)
  const RARITY_EFFECTS = new Set(['leggendario', 'speciale', 'epico', 'raro']);

  const RARITY_COLORS = {
    comune:'#9ca3af', raro:'#38bdf8', epico:'#c084fc',
    leggendario:'#fbbf24', speciale:'#ffffff',
    segreto:'#a855f7', theone:'#60a5fa',
  };

  /* ══════════════════════════════════════════════════════
     STATE
  ══════════════════════════════════════════════════════ */
  const state = {
    activeBannerId:   'standard',
    activeBannerType: 'standard',
    isPulling:        false,
    overlayOpen:      false,
    soldi:            window.GACHA_INIT?.soldi        ?? 0,
    pityStandard:     window.GACHA_INIT?.pityStandard ?? 0,
    pityEvento:       window.GACHA_INIT?.pityEvento   ?? 0,
    garantito:        window.GACHA_INIT?.garantito     ?? false,
    adminForceRarity: null,
    canSkip:          false,
    skipTimeout:      null,
  };

  /* ══════════════════════════════════════════════════════
     DOM REFS
  ══════════════════════════════════════════════════════ */
  const $  = id  => document.getElementById(id);
  const $$ = sel => document.querySelectorAll(sel);

  const overlay        = $('gacha-overlay');
  const glowBurst      = $('gacha-glow-burst');
  const overlayStars   = $('overlay-stars');
  const particlesLayer = $('gacha-particles');

  const phaseOpening   = $('phase-opening');
  const phaseVideo     = $('phase-video');
  const phaseCard      = $('phase-card');

  const videoEl        = $('gacha-video');
  const videoUnmuteBtn = $('video-unmute-btn');
  const skipBtn        = $('gacha-skip-btn');

  const gachaCard      = $('gacha-card');
  const cardBgGlow     = $('card-bg-glow');
  const cardImg        = $('card-img');
  const cardNewBadge   = $('card-new-badge');
  const card50Win      = $('card-50-win');
  const card50Loss     = $('card-50-loss');
  const cardRarityBar  = $('card-rarity-bar');
  const cardRarityLabel= $('card-rarity-label');
  const cardName       = $('card-name');

  const btnPullAgain   = $('btn-pull-again');
  const btnClose       = $('btn-close-overlay');
  const btnInventory   = $('btn-go-inventory');

  const audioEl        = $('gacha-audio');
  const toastEl        = $('gacha-toast');
  const tabsContainer  = null; // sostituito da sidebar cards

  /* ══════════════════════════════════════════════════════
     INIT
  ══════════════════════════════════════════════════════ */
  function init() {
    fixTabsOffset();           // FIX 1
    createStars($('stars'), 100);
    createStars(overlayStars, 60);
    initTabs();                // FIX 9
    initPullButtons();
    initOverlayButtons();
    initSkipButton();          // FIX 10
    initKeyboard();
    initAdminCheats();
    initTimers();
    initSettingsBtn();
    initLeaderboard();         // FIX 12
    injectHistoryModal();      // FIX 11
  }

  /* ════════════════════════════════════════════════════
     FIX 1 — TABS OFFSET SOTTO NAVBAR
  ════════════════════════════════════════════════════ */
  function fixTabsOffset() {
    // Trova la navbar e setta il top della sidebar sticky
    const nav = document.querySelector(
      'nav.navbar, header.navbar, #navbar, .navbar, nav[class*="nav"]'
    );
    const sidebar = document.getElementById('gacha-sidebar');
    if (!nav) return;

    const apply = () => {
      const h = nav.getBoundingClientRect().height;
      if (h <= 0) return;
      // Sidebar desktop sticky top
      if (sidebar) sidebar.style.top = h + 'px';
      // Banner view altezza
      document.querySelectorAll('.gacha-banner-view').forEach(v => {
        v.style.minHeight = `calc(100dvh - ${h}px)`;
      });
      // Layout min-height
      const layout = document.getElementById('gacha-layout');
      if (layout) layout.style.minHeight = `calc(100dvh - ${h}px)`;
    };
    apply();
    window.addEventListener('resize', apply, { passive: true });
    if (window.ResizeObserver) new ResizeObserver(apply).observe(nav);
  }

  /* ════════════════════════════════════════════════════
     STARS
  ════════════════════════════════════════════════════ */
  function createStars(container, count) {
    if (!container) return;
    const frag = document.createDocumentFragment();
    for (let i = 0; i < count; i++) {
      const s = document.createElement('div');
      s.className = 'star';
      s.style.cssText = `left:${Math.random()*100}%;top:${Math.random()*100}%;animation-delay:${Math.random()*4}s;animation-duration:${2+Math.random()*3}s;`;
      frag.appendChild(s);
    }
    container.appendChild(frag);
  }

  /* ════════════════════════════════════════════════════
     SIDEBAR CARD CLICK → switch banner
  ════════════════════════════════════════════════════ */
  function initTabs() {
    // Ascolta click su tutti i riquadri sidebar
    const sidebarEl = document.getElementById('gsb-banners');
    sidebarEl?.addEventListener('click', e => {
      const card = e.target.closest('.gsb-card[data-banner-id]');
      if (!card || state.isPulling) return;
      switchBanner(card.dataset.bannerId, card.dataset.bannerType);
    });
  }

  function switchBanner(bannerId, bannerType) {
    if (state.activeBannerId === bannerId) return;

    // Aggiorna stato card sidebar
    $$('.gsb-card[data-banner-id]').forEach(c => {
      c.classList.remove('is-active');
      c.setAttribute('aria-pressed', 'false');
    });
    const activeCard = document.querySelector(`.gsb-card[data-banner-id="${bannerId}"]`);
    if (activeCard) {
      activeCard.classList.add('is-active');
      activeCard.setAttribute('aria-pressed', 'true');
      // Scroll in vista su mobile
      activeCard.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });

      // Propaga colore accent dalla card al CSS var del banner
      const accentColor = bannerType === 'standard' ? '#9ca3af' : '#38bdf8';
      document.documentElement.style.setProperty('--banner-accent', accentColor);
    }

    // Nascondi vecchia view
    const oldView = $(`banner-view-${state.activeBannerId}`);
    if (oldView) oldView.style.display = 'none';

    // Mostra nuova view con fade
    const newView = $(`banner-view-${bannerId}`);
    if (newView) {
      newView.style.cssText = 'display:flex;opacity:0;flex:1;';
      requestAnimationFrame(() => {
        newView.style.transition = 'opacity .35s ease';
        newView.style.opacity = '1';
        setTimeout(() => newView.style.transition = '', 370);
      });
    }

    state.activeBannerId   = bannerId;
    state.activeBannerType = bannerType ?? 'standard';
    // Aggiorna tracking cronologia
    if (window.GACHA_INIT) window.GACHA_INIT.activeBannerId = bannerId;
  }

  /* ════════════════════════════════════════════════════
     PULL BUTTONS
  ════════════════════════════════════════════════════ */
  function initPullButtons() {
    $$('.gacha-pull-btn[data-banner-id]').forEach(btn => {
      btn.addEventListener('click', () => {
        if (state.isPulling) return;
        startPull(btn.dataset.bannerId);
      });
    });
  }

  /* ════════════════════════════════════════════════════
     PULL FLOW
  ════════════════════════════════════════════════════ */
  async function startPull(bannerId) {
    if (state.isPulling) return;
    state.isPulling = true;

    stopAudio();         // FIX 2 — ferma audio precedente
    lockScroll();
    openOverlay();
    showPhase('opening');
    setRarityOnOverlay('comune');
    hideSkipBtn();

    const payload = { banner_id: bannerId, quantity: 1 };
    if (state.adminForceRarity) payload.force_rarity = state.adminForceRarity;

    try {
      const resp = await fetch(API_PULL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        credentials: 'same-origin',
      });
      if (!resp.ok) {
        const err = await resp.json().catch(() => ({}));
        throw new Error(err.message ?? `Errore ${resp.status}`);
      }
      const data = await resp.json();
      if (data.status !== 'success') throw new Error(data.message ?? 'Errore sconosciuto');

      state.soldi        = data.soldi_rimasti ?? state.soldi;
      state.pityStandard = data.pity_standard ?? state.pityStandard;
      state.pityEvento   = data.pity_evento   ?? state.pityEvento;
      state.garantito    = data.garantito     ?? state.garantito;
      window._lastPullData = data;

      const rarity = normalizeRarity(data.personaggio.rarità);

      // FIX 4 — Audio parte SUBITO dopo il fetch, prima del reveal
      // Segreto/TheOne: nessun audio separato, c'è il video con audio
      if (!RARITY_VIDEO.has(rarity)) {
        playAudio(data.personaggio.audio_url);
      }

      // FIX 10 — Skip abilitato se personaggio già noto e rarità skippabile
      state.canSkip = !data.is_new && !RARITY_NO_SKIP.has(rarity);

      await delay(900);
      await revealPull(data);
      updateBannerUI();
      triggerAchievements(data);

    } catch(err) {
      console.error('[Gacha] Pull error:', err);
      closeOverlay();
      showToast(err.message ?? 'Errore durante la pull. Riprova!', 'error');
    } finally {
      state.isPulling = false;
    }
  }

  /* ════════════════════════════════════════════════════
     REVEAL
  ════════════════════════════════════════════════════ */
  async function revealPull(data) {
    const p      = data.personaggio;
    const rarity = normalizeRarity(p.rarità);
    const hasVideo = p.video_url && RARITY_VIDEO.has(rarity);

    setRarityOnOverlay(rarity);

    if (hasVideo) {
      // FIX 3 — Segreto/TheOne: SOLO fade nero → video. Zero effetti.
      await fadeToBlackThenVideo(p.video_url, data);
    } else {
      // Effetti per le altre rarità premium
      if (window.GachaEffects && RARITY_EFFECTS.has(rarity)) {
        await GachaEffects.play(rarity);
      }
      await showCard(data);
    }
  }

  /* ── FIX 3 — Fade nero → video ──────────────────────── */
  async function fadeToBlackThenVideo(videoUrl, data) {
    // Crea velo nero sull'overlay
    const velo = document.createElement('div');
    velo.style.cssText = `
      position:absolute;inset:0;background:#000;z-index:30;
      opacity:0;transition:opacity .8s ease;pointer-events:none;
    `;
    overlay.appendChild(velo);
    await delay(20);
    velo.style.opacity = '1';
    await delay(850);

    showPhase('video');

    // Fade via velo (dissolvenza da nero a video)
    velo.style.opacity = '0';
    setTimeout(() => velo.remove(), 800);

    await playRevealVideo(videoUrl, data);
  }

  /* ── Video reveal ────────────────────────────────────── */
  async function playRevealVideo(videoUrl, data) {
    videoEl.src    = videoUrl.startsWith('/') ? videoUrl : `/vid/${videoUrl}`;
    videoEl.muted  = false;  // FIX 3 — audio video attivo da subito
    videoEl.volume = 1;
    videoEl.load();

    videoUnmuteBtn.style.display = 'none'; // FIX 3 — no hint unmute

    return new Promise(resolve => {
      let resolved = false;
      const done = async () => {
        if (resolved) return;
        resolved = true;
        clearTimeout(safeguard);
        videoEl.removeEventListener('ended', done);
        videoEl.removeEventListener('error', done);
        videoEl.pause();
        videoEl.src = '';
        hideSkipBtn();
        await showCard(data);
        resolve();
      };

      const safeguard = setTimeout(done, 60000);
      videoEl.addEventListener('ended', done, { once: true });
      videoEl.addEventListener('error', done, { once: true });

      const pp = videoEl.play();
      if (pp) pp.catch(() => {
        videoEl.muted = true;
        videoEl.play().catch(() => done());
      });
    });
  }

  /* ── FIX 6 + FIX 7 — Card: solo nome+rarità, animazione idle ── */
  async function showCard(data) {
    const p      = data.personaggio ?? data;
    const rarity = normalizeRarity(p.rarità);
    const color  = RARITY_COLORS[rarity] ?? '#fff';

    // FIX 6 — Solo nome e rarità, no caratteristiche/descrizione
    cardImg.src = p.img_url ? `/img/${p.img_url}` : '/img/cassa.png';
    cardImg.alt = escapeHtml(p.nome ?? '');
    cardName.textContent      = escapeHtml(p.nome ?? '—');
    cardRarityBar.className   = `gacha-card-rarity-bar rarity-${rarity}`;
    cardRarityLabel.textContent = (p.rarità ?? '—').toUpperCase();
    cardBgGlow.style.background = `radial-gradient(circle,${color}55 0%,transparent 70%)`;

    cardNewBadge.style.display = data.is_new ? '' : 'none';
    card50Win.style.display  = 'none';
    card50Loss.style.display = 'none';
    if      (data.vinto_50_50 === 1) card50Win.style.display  = '';
    else if (data.vinto_50_50 === 0) card50Loss.style.display = '';

    // FIX 8 — Inventario SEMPRE visibile
    if (btnInventory) btnInventory.style.display = '';

    // FIX 10 — Skip
    if (state.canSkip) showSkipBtn();

    spawnParticles(color, rarity);
    showPhase('card');

    // FIX 7 — Bounce in poi idle float
    gachaCard.classList.remove('is-revealed', 'is-idle');
    await delay(40);
    gachaCard.classList.add('is-revealed');
    await delay(620);
    gachaCard.classList.add('is-idle');
  }

  /* ════════════════════════════════════════════════════
     OVERLAY
  ════════════════════════════════════════════════════ */
  function openOverlay() {
    overlay.classList.add('is-visible');
    state.overlayOpen = true;
  }

  function closeOverlay() {
    stopAudio(); // FIX 2
    overlay.classList.remove('is-visible');
    state.overlayOpen = false;
    hideSkipBtn();

    setTimeout(() => {
      setRarityOnOverlay('comune');
      gachaCard.classList.remove('is-revealed', 'is-idle');
      glowBurst.classList.remove('is-rainbow');
      phaseVideo.style.display   = 'none';
      phaseCard.style.display    = 'none';
      phaseOpening.style.display = 'flex';
      videoEl.pause();
      videoEl.src = '';
      card50Win.style.display  = 'none';
      card50Loss.style.display = 'none';
      cardNewBadge.style.display = 'none';
      state.canSkip = false;
      particlesLayer.innerHTML = '';
    }, 420);

    unlockScroll();
  }

  function showPhase(p) {
    phaseOpening.style.display = p === 'opening' ? 'flex' : 'none';
    phaseVideo.style.display   = p === 'video'   ? 'flex' : 'none';
    phaseCard.style.display    = p === 'card'    ? 'flex' : 'none';
  }

  function setRarityOnOverlay(rarity) {
    overlay.setAttribute('data-rarity', rarity);
    const color = RARITY_COLORS[rarity] ?? '#fff';
    overlay.style.setProperty('--banner-accent',      color);
    overlay.style.setProperty('--banner-accent-glow', color + '55');
    glowBurst.classList.toggle('is-rainbow', rarity === 'speciale');
  }

  /* ════════════════════════════════════════════════════
     FIX 10 — SKIP BUTTON
  ════════════════════════════════════════════════════ */
  function initSkipButton() {
    skipBtn?.addEventListener('click', () => {
      if (!state.canSkip || state.isPulling) return;
      if (phaseVideo.style.display !== 'none') {
        videoEl.pause();
        videoEl.dispatchEvent(new Event('ended'));
      } else if (phaseOpening.style.display !== 'none') {
        // Anche in fase apertura skip → fetch è già in corso, aspetta
      }
    });
  }
  function showSkipBtn() {
    if (!skipBtn) return;
    skipBtn.style.display = 'flex';
    clearTimeout(state.skipTimeout);
    state.skipTimeout = setTimeout(hideSkipBtn, 5000);
  }
  function hideSkipBtn() {
    if (!skipBtn) return;
    skipBtn.style.display = 'none';
    clearTimeout(state.skipTimeout);
  }

  /* ════════════════════════════════════════════════════
     OVERLAY BUTTONS
  ════════════════════════════════════════════════════ */
  function initOverlayButtons() {
    btnPullAgain?.addEventListener('click', () => {
      if (state.isPulling) return;
      stopAudio(); // FIX 2
      gachaCard.classList.remove('is-revealed', 'is-idle');
      showPhase('opening');
      setRarityOnOverlay('comune');
      hideSkipBtn();
      setTimeout(() => startPullFromOverlay(), 80);
    });

    btnClose?.addEventListener('click', () => {
      if (state.isPulling) return;
      closeOverlay();
    });
  }

  async function startPullFromOverlay() {
    if (state.isPulling) return;
    state.isPulling = true;
    stopAudio(); // FIX 2

    const payload = { banner_id: state.activeBannerId, quantity: 1 };
    if (state.adminForceRarity) payload.force_rarity = state.adminForceRarity;

    try {
      const resp = await fetch(API_PULL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        credentials: 'same-origin',
      });
      const data = await resp.json();
      if (data.status !== 'success') throw new Error(data.message);

      state.soldi        = data.soldi_rimasti ?? state.soldi;
      state.pityStandard = data.pity_standard ?? state.pityStandard;
      state.pityEvento   = data.pity_evento   ?? state.pityEvento;
      state.garantito    = data.garantito     ?? state.garantito;
      window._lastPullData = data;

      const rarity = normalizeRarity(data.personaggio.rarità);
      state.canSkip = !data.is_new && !RARITY_NO_SKIP.has(rarity);
      if (!RARITY_VIDEO.has(rarity)) playAudio(data.personaggio.audio_url); // FIX 4

      await delay(900);
      await revealPull(data);
      updateBannerUI();
      triggerAchievements(data);
    } catch(err) {
      console.error('[Gacha] Pull again error:', err);
      closeOverlay();
      showToast(err.message ?? 'Errore. Riprova!', 'error');
    } finally {
      state.isPulling = false;
    }
  }

  /* ════════════════════════════════════════════════════
     REVEAL ESTERNO (riscattaCodice)
  ════════════════════════════════════════════════════ */
  window.GachaUI = {
    showToast,
    openRevealWithData: async (p) => {
      lockScroll();
      openOverlay();
      showPhase('card');
      setRarityOnOverlay(normalizeRarity(p.rarità ?? ''));
      await delay(60);
      await showCard({ personaggio: p, is_new: true, vinto_50_50: null });
    },
  };

  /* ════════════════════════════════════════════════════
     UPDATE BANNER UI
  ════════════════════════════════════════════════════ */
  function updateBannerUI() {
    $$('#user-points-std,.user-points-evt').forEach(el =>
      el.textContent = state.soldi.toLocaleString('it-IT')
    );

    const pHS = window.GACHA_INIT?.pityHardStd ?? 90;
    const pSS = window.GACHA_INIT?.pitySoftStd ?? 70;
    const sf = $('pity-std-fill'), sn = $('pity-std-num'), so = $('pity-std-note');
    if (sf) sf.style.width = Math.min(100, Math.round(state.pityStandard/pHS*100)) + '%';
    if (sn) sn.textContent = `${state.pityStandard} / ${pHS}`;
    if (so) {
      if (state.pityStandard >= pHS) {
        so.textContent = '★ Garantito: prossima pull è Speciale o Segreto!';
        so.classList.add('is-active');
      } else if (state.pityStandard >= pSS) {
        so.textContent = '✦ Soft pity — % Speciale/Segreto aumentata';
        so.classList.add('is-active');
      } else {
        so.textContent = `Garantito Speciale/Segreto in ${pHS - state.pityStandard} pull`;
        so.classList.remove('is-active');
      }
    }

    const pHE = window.GACHA_INIT?.pityHardEvt ?? 80;
    const pSE = window.GACHA_INIT?.pitySoftEvt ?? 65;
    $$('.pity-evt-fill').forEach(el => el.style.width = Math.min(100, Math.round(state.pityEvento/pHE*100)) + '%');
    $$('.pity-evt-num').forEach(el => el.textContent = `${state.pityEvento} / ${pHE}`);
    $$('.pity-evt-note').forEach(el => {
      el.textContent = state.pityEvento >= pSE
        ? '✦ Soft pity attivo — probabilità in aumento'
        : `Garantito segreto in ${pHE - state.pityEvento} pull`;
      el.classList.toggle('is-active', state.pityEvento >= pSE);
    });

    $$('[id^="garantito-badge-"]').forEach(el =>
      el.style.display = state.garantito ? '' : 'none'
    );
  }

  /* ════════════════════════════════════════════════════
     PARTICELLE
  ════════════════════════════════════════════════════ */
  function spawnParticles(color, rarity) {
    const mob   = window.innerWidth < 768;
    const count = rarity==='theone'?mob?30:70:rarity==='segreto'?mob?25:60:
                  rarity==='speciale'?mob?20:50:rarity==='leggendario'?mob?15:40:
                  rarity==='epico'?mob?10:28:mob?6:15;
    const frag  = document.createDocumentFragment();
    for (let i = 0; i < count; i++) {
      const p = document.createElement('div');
      const sz = 3 + Math.random()*5, ang = Math.random()*Math.PI*2;
      const d  = 80 + Math.random()*200, dur = 600 + Math.random()*900;
      p.className = 'gacha-particle';
      p.style.cssText = `width:${sz}px;height:${sz}px;background:${color};box-shadow:0 0 ${sz*2}px ${color};--px:${Math.cos(ang)*d}px;--py:${Math.sin(ang)*d}px;--dur:${dur}ms;`;
      frag.appendChild(p);
      setTimeout(() => p.remove(), dur + 100);
    }
    particlesLayer.appendChild(frag);
  }

  /* ════════════════════════════════════════════════════
     FIX 2 — AUDIO
  ════════════════════════════════════════════════════ */
  function stopAudio() {
    if (!audioEl) return;
    audioEl.pause();
    audioEl.currentTime = 0;
    audioEl.src = '';
  }
  function playAudio(url) {
    if (!url) return;
    stopAudio();
    try {
      audioEl.src = url.startsWith('/') ? url : `/audio/${url}`;
      audioEl.currentTime = 0;
      audioEl.volume = 0.8;
      audioEl.play().catch(() => {});
    } catch(e) {}
  }

  /* ════════════════════════════════════════════════════
     SCROLL LOCK
  ════════════════════════════════════════════════════ */
  let _tl = null;
  function lockScroll() {
    document.body.style.overflow = 'hidden';
    _tl = e => e.preventDefault();
    document.addEventListener('touchmove', _tl, { passive: false });
  }
  function unlockScroll() {
    document.body.style.overflow = '';
    if (_tl) { document.removeEventListener('touchmove', _tl); _tl = null; }
  }

  /* ════════════════════════════════════════════════════
     TOAST
  ════════════════════════════════════════════════════ */
  let _tt = null;
  function showToast(msg, type = 'info') {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.className = `gacha-toast${type==='error'?' gacha-toast--error':''}`;
    toastEl.classList.add('is-visible');
    clearTimeout(_tt);
    _tt = setTimeout(() => toastEl.classList.remove('is-visible'), 3500);
  }

  /* ════════════════════════════════════════════════════
     KEYBOARD
  ════════════════════════════════════════════════════ */
  function initKeyboard() {
    document.addEventListener('keydown', e => {
      if (e.repeat) return;
      if (e.code==='Space'  && !state.overlayOpen && !state.isPulling) { e.preventDefault(); startPull(state.activeBannerId); }
      if (e.code==='Enter'  &&  state.overlayOpen && !state.isPulling) { e.preventDefault(); btnPullAgain?.click(); }
      if (e.code==='Escape' &&  state.overlayOpen && !state.isPulling) { e.preventDefault(); closeOverlay(); }
      if (e.code==='KeyS'   &&  state.canSkip)                         { e.preventDefault(); skipBtn?.click(); }
    });
  }

  /* ════════════════════════════════════════════════════
     ADMIN CHEATS
  ════════════════════════════════════════════════════ */
  function initAdminCheats() {
    $$('.admin-force-rarity').forEach(cb => {
      cb.addEventListener('change', () => {
        $$('.admin-force-rarity').forEach(o => { if(o!==cb) o.checked=false; });
        state.adminForceRarity = cb.checked ? cb.dataset.rarity : null;
      });
    });
  }

  /* ════════════════════════════════════════════════════
     TIMER EVENTO
  ════════════════════════════════════════════════════ */
  function initTimers() {
    $$('.gacha-timer-digits[data-ends]').forEach(el => {
      updateTimer(el);
      setInterval(() => updateTimer(el), 60_000);
    });
  }
  function updateTimer(el) {
    const diff = new Date(el.dataset.ends.replace(' ','T')+'Z').getTime() - Date.now();
    if (diff <= 0) { el.closest('.gacha-timer-wrap')?.remove(); return; }
    const d=el.querySelector('.t-days'),h=el.querySelector('.t-hours'),m=el.querySelector('.t-mins');
    if(d) d.textContent = Math.floor(diff/86400000);
    if(h) h.textContent = String(Math.floor(diff%86400000/3600000)).padStart(2,'0');
    if(m) m.textContent = String(Math.floor(diff%3600000/60000)).padStart(2,'0');
  }

  /* ════════════════════════════════════════════════════
     SETTINGS
  ════════════════════════════════════════════════════ */
  function initSettingsBtn() {
    $('btn-settings')?.addEventListener('click', () => {
      const modal = document.getElementById('impostazioniModal');
      if (modal && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modal).show();
    });
  }

  /* ════════════════════════════════════════════════════
     FIX 11 — CRONOLOGIA PULL
  ════════════════════════════════════════════════════ */
  function injectHistoryModal() {
    document.body.insertAdjacentHTML('beforeend', `
    <div class="modal fade" id="gachaHistoryModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content bgimpostazioni lootbox-settings-content">
          <div class="modal-header lootbox-settings-header">
            <div>
              <span class="lootbox-modal-kicker">Gacha</span>
              <h5 class="modal-title testobianco">Cronologia Pull</h5>
              <p id="gacha-history-banner-label" style="color:rgba(255,255,255,.45);font-size:.82rem;margin:0"></p>
            </div>
            <button type="button" class="lootbox-modal-close" data-bs-dismiss="modal"><i class="fas fa-xmark"></i></button>
          </div>
          <div class="modal-body" style="padding:16px 20px;max-height:60vh;overflow-y:auto">
            <div id="gacha-history-list"></div>
          </div>
          <div class="modal-footer lootbox-settings-footer" style="justify-content:space-between">
            <span id="gacha-history-stats" style="font-size:.8rem;color:rgba(255,255,255,.35)"></span>
            <button type="button" class="btn btn-secondary bottone lootbox-modal-btn lootbox-modal-btn--ghost" data-bs-dismiss="modal">Chiudi</button>
          </div>
        </div>
      </div>
    </div>`);
  }

  async function openHistoryModal(bannerId, bannerName) {
    const modal = $('gachaHistoryModal');
    if (!modal || !window.bootstrap) return;
    $('gacha-history-banner-label').textContent = bannerName ?? '';
    $('gacha-history-list').innerHTML = `<div style="text-align:center;color:rgba(255,255,255,.35);padding:40px"><i class="fas fa-circle-notch fa-spin"></i></div>`;
    bootstrap.Modal.getOrCreateInstance(modal).show();

    try {
      const resp = await fetch(`/api/api_gacha_history?banner_id=${encodeURIComponent(bannerId)}&limit=60`, { credentials:'same-origin' });
      const data = await resp.json();

      if (!data.pulls?.length) {
        $('gacha-history-list').innerHTML = `<div style="text-align:center;color:rgba(255,255,255,.3);padding:48px"><i class="fas fa-scroll" style="font-size:2.4rem;display:block;margin-bottom:14px;opacity:.4"></i>Nessuna pull su questo banner.</div>`;
        return;
      }

      const RC = {comune:'#9ca3af',raro:'#38bdf8',epico:'#c084fc',leggendario:'#fbbf24',speciale:'#fff',segreto:'#a855f7',theone:'#60a5fa'};
      $('gacha-history-list').innerHTML = data.pulls.map(p => {
        const r  = normalizeRarity(p.rarità);
        const c  = RC[r] ?? '#fff';
        const dt = new Date(p.created_at).toLocaleString('it-IT',{day:'2-digit',month:'2-digit',year:'2-digit',hour:'2-digit',minute:'2-digit'});
        const b50 = p.esito_50_50===1 ? `<span style="font-size:.68rem;color:#fbbf24;background:rgba(251,191,36,.12);padding:2px 7px;border-radius:6px">★ Rate-Up</span>`
                  : p.esito_50_50===0 ? `<span style="font-size:.68rem;color:#f87171;background:rgba(239,68,68,.1);padding:2px 7px;border-radius:6px">→ Garantito</span>` : '';
        const newBadge = p.is_new ? `<span style="font-size:.68rem;color:#4ade80;background:rgba(74,222,128,.12);padding:2px 7px;border-radius:6px">NEW</span>` : '';
        return `<div style="display:flex;align-items:center;gap:12px;padding:9px 12px;background:rgba(255,255,255,.03);border-radius:8px;border-left:3px solid ${c};margin-bottom:6px">
          <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:3px">
              <span style="font-weight:700;color:#fff;font-size:.9rem">${escapeHtml(p.nome??'?')}</span>
              <span style="font-size:.7rem;color:${c};text-transform:uppercase;font-weight:600;letter-spacing:.07em">${escapeHtml(p.rarità??'')}</span>
              ${b50}${newBadge}
            </div>
            <span style="font-size:.72rem;color:rgba(255,255,255,.28)">${dt}</span>
          </div>
          <div style="text-align:right;flex-shrink:0;font-size:.72rem;color:rgba(255,255,255,.25)">pity ${p.pity_al_momento}</div>
        </div>`;
      }).join('');

      const seg = data.pulls.filter(p => ['segreto','theone'].includes(normalizeRarity(p.rarità))).length;
      $('gacha-history-stats').textContent = `${data.total??data.pulls.length} pull totali • ${seg} segreti`;
    } catch {
      $('gacha-history-list').innerHTML = `<div style="text-align:center;color:#f87171;padding:32px">Errore caricamento cronologia.</div>`;
    }
  }

  window.GachaHistory = { open: openHistoryModal };

  /* ════════════════════════════════════════════════════
     FIX 12 — LEADERBOARD
  ════════════════════════════════════════════════════ */
  function initLeaderboard() {
    $$('.gacha-leaderboard-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        if (typeof toggleLeaderboard === 'function') toggleLeaderboard();
      });
    });
  }

  /* ════════════════════════════════════════════════════
     ACHIEVEMENTS
  ════════════════════════════════════════════════════ */
  async function triggerAchievements(data) {
    if (typeof unlockAchievement !== 'function') return;
    try {
      unlockAchievement(5);
      const r = await fetch('/api/get_casse_aperte');
      const d = await r.json();
      const c = d.total ?? 0;
      if (c >= 100) unlockAchievement(8);
      if (c >= 500) unlockAchievement(16);
    } catch(e) {}
  }

  /* ════════════════════════════════════════════════════
     UTILITY
  ════════════════════════════════════════════════════ */
  function normalizeRarity(r) {
    return String(r ?? 'comune').toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
      .replace(/[\s_-]+/g,'').trim();
  }
  function escapeHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }
  function delay(ms) { return new Promise(r => setTimeout(r, ms)); }

  /* ════════════════════════════════════════════════════
     BOOT
  ════════════════════════════════════════════════════ */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();