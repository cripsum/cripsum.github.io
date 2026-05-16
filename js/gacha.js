/**
 * gacha.js — GoonLand Gacha System v1.0
 * Gestisce: tab switching, pull flow, overlay fullscreen,
 *           video reveal, pity UI, particelle, timer evento.
 *
 * Dipende da: gacha-effects.js (window.GachaEffects)
 * Nessun RNG qui. Tutto calcolato dal backend.
 * Il JS gestisce SOLO animazioni e UI.
 */

'use strict';

(function () {

  /* ══════════════════════════════════════════════════════
     CONFIG
  ══════════════════════════════════════════════════════ */
  const API_PULL    = '/api/api_gacha_pull';
  const API_BANNERS = '/api/api_gacha_banners';

  // Rarità che triggerano il reveal video
  const RARITY_VIDEO = new Set(['segreto', 'theone']);
  // Rarità con effetti "premium" (cinematic flash + particelle intense)
  const RARITY_PREMIUM = new Set(['leggendario', 'speciale', 'segreto', 'theone']);

  // Colori per rarità (glow overlay, particelle)
  const RARITY_COLORS = {
    comune:      '#9ca3af',
    raro:        '#38bdf8',
    epico:       '#c084fc',
    leggendario: '#fbbf24',
    speciale:    '#ffffff',
    segreto:     '#a855f7',
    theone:      '#60a5fa',
  };

  // Messaggi per rarità (preserva lo stile GoonLand)
  const RARITY_MESSAGES = {
    comune:      'bravo fra hai pullato un personaggio comune, skill issue xd',
    raro:        'buono dai, hai pullato un personaggio raro!',
    epico:       'hai pullato un personaggio epico, tanta roba, ma poteva andare meglio',
    leggendario: 'che fortuna, hai pullato un personaggio leggendario!',
    speciale:    "COM'É POSSIBILE? HAI PULLATO UN PERSONAGGIO SPECIALE!",
    segreto:     'COSA? HAI PULLATO UN PERSONAGGIO SEGRETO? aura.',
    theone:      'INCREDIBILE! HAI PULLATO IL PERSONAGGIO PIÙ RARO DI TUTTI!!!',
  };

  /* ══════════════════════════════════════════════════════
     STATE
  ══════════════════════════════════════════════════════ */
  const state = {
    activeBannerId:   'standard',
    activeBannerType: 'standard',
    isPulling:        false,
    overlayOpen:      false,
    soldi:            window.GACHA_INIT?.soldi          ?? 0,
    pityStandard:     window.GACHA_INIT?.pityStandard   ?? 0,
    pityEvento:       window.GACHA_INIT?.pityEvento     ?? 0,
    garantito:        window.GACHA_INIT?.garantito       ?? false,
    adminForceRarity: null,
    videoUnmuted:     false,
  };

  /* ══════════════════════════════════════════════════════
     DOM REFS (immutabili dopo init)
  ══════════════════════════════════════════════════════ */
  const $ = id => document.getElementById(id);

  const overlay         = $('gacha-overlay');
  const glowBurst       = $('gacha-glow-burst');
  const overlayStars    = $('overlay-stars');
  const particlesLayer  = $('gacha-particles');
  const flashEl         = $('gacha-flash');

  const phaseOpening    = $('phase-opening');
  const phaseVideo      = $('phase-video');
  const phaseCard       = $('phase-card');

  const orbCore         = $('orb-core');
  const videoEl         = $('gacha-video');
  const videoUnmuteBtn  = $('video-unmute-btn');

  const gachaCard       = $('gacha-card');
  const cardBgGlow      = $('card-bg-glow');
  const cardFrame       = $('card-frame');
  const cardImg         = $('card-img');
  const cardNewBadge    = $('card-new-badge');
  const card50Win       = $('card-50-win');
  const card50Loss      = $('card-50-loss');
  const cardRarityBar   = $('card-rarity-bar');
  const cardRarityLabel = $('card-rarity-label');
  const cardName        = $('card-name');
  const cardChars       = $('card-chars');

  const overlayActions  = $('overlay-actions');
  const btnPullAgain    = $('btn-pull-again');
  const btnClose        = $('btn-close-overlay');
  const btnInventory    = $('btn-go-inventory');

  const audioEl         = $('gacha-audio');
  const toastEl         = $('gacha-toast');
  const tabsContainer   = $('gacha-tabs');

  /* ══════════════════════════════════════════════════════
     INIT
  ══════════════════════════════════════════════════════ */
  function init() {
    createStars($('stars'), 100);
    createStars(overlayStars, 60);
    initTabs();
    initPullButtons();
    initOverlayButtons();
    initKeyboard();
    initAdminCheats();
    initTimers();
    initSettingsBtn();
    showFirstBanner();
  }

  /* ══════════════════════════════════════════════════════
     STARS
  ══════════════════════════════════════════════════════ */
  function createStars(container, count) {
    if (!container) return;
    const frag = document.createDocumentFragment();
    for (let i = 0; i < count; i++) {
      const s = document.createElement('div');
      s.className = 'star';
      s.style.left             = Math.random() * 100 + '%';
      s.style.top              = Math.random() * 100 + '%';
      s.style.animationDelay   = Math.random() * 4 + 's';
      s.style.animationDuration= (2 + Math.random() * 3) + 's';
      frag.appendChild(s);
    }
    container.appendChild(frag);
  }

  /* ══════════════════════════════════════════════════════
     TABS
  ══════════════════════════════════════════════════════ */
  function initTabs() {
    tabsContainer?.addEventListener('click', e => {
      const tab = e.target.closest('.gacha-tab[data-banner-id]');
      if (!tab) return;
      if (state.isPulling) return;
      switchBanner(tab.dataset.bannerId, tab.dataset.bannerType);
    });
  }

  function switchBanner(bannerId, bannerType) {
    if (state.activeBannerId === bannerId) return;

    // Disattiva tab attiva
    document.querySelectorAll('.gacha-tab[data-banner-id]').forEach(t => {
      t.classList.remove('is-active');
      t.setAttribute('aria-selected', 'false');
    });

    // Attiva nuova tab
    const newTab = tabsContainer?.querySelector(`[data-banner-id="${bannerId}"]`);
    newTab?.classList.add('is-active');
    newTab?.setAttribute('aria-selected', 'true');

    // Nascondi view corrente
    const oldView = document.getElementById(`banner-view-${state.activeBannerId}`);
    if (oldView) oldView.style.display = 'none';

    // Mostra nuova view
    const newView = document.getElementById(`banner-view-${bannerId}`);
    if (newView) {
      newView.style.display = '';
      newView.style.animation = 'none';
      void newView.offsetWidth;
      newView.style.animation = '';
    }

    state.activeBannerId   = bannerId;
    state.activeBannerType = bannerType ?? 'standard';
  }

  function showFirstBanner() {
    // Inizialmente mostra solo il banner standard (già visibile di default)
  }

  /* ══════════════════════════════════════════════════════
     PULL BUTTONS
  ══════════════════════════════════════════════════════ */
  function initPullButtons() {
    document.querySelectorAll('.gacha-pull-btn[data-banner-id]').forEach(btn => {
      btn.addEventListener('click', () => {
        if (state.isPulling) return;
        startPull(btn.dataset.bannerId);
      });
    });
  }

  /* ══════════════════════════════════════════════════════
     PULL FLOW
  ══════════════════════════════════════════════════════ */
  async function startPull(bannerId) {
    if (state.isPulling) return;
    state.isPulling = true;

    // Blocca scroll (incluso touchmove iOS)
    if (window.GachaEffects) GachaEffects.lockScroll();
    else document.body.style.overflow = 'hidden';

    // Apri overlay e mostra fase opening
    openOverlay();
    showPhase('opening');
    setRarityOnOverlay('comune'); // neutro durante caricamento

    // Build payload
    const payload = { banner_id: bannerId, quantity: 1 };
    if (state.adminForceRarity) {
      payload.force_rarity = state.adminForceRarity;
    }

    try {
      // Fetch pull (in parallelo con animazione apertura orb)
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

      if (data.status !== 'success') {
        throw new Error(data.message ?? 'Errore sconosciuto');
      }

      // Aggiorna state locale
      state.soldi        = data.soldi_rimasti  ?? state.soldi;
      state.pityStandard = data.pity_standard  ?? state.pityStandard;
      state.pityEvento   = data.pity_evento    ?? state.pityEvento;
      state.garantito    = data.garantito      ?? state.garantito;
      window._lastPullData = data; // usato dal video resolver

      // Attendi minimo di animazione (almeno 900ms di orb) poi rivela
      await delay(900);

      // Reveal
      await revealPull(data);

      // Aggiorna UI pity / punti nella banner view
      updateBannerUI();

      // Achievement (compatibile con sistema esistente)
      triggerAchievements(data);

    } catch (err) {
      console.error('[Gacha] Pull error:', err);
      closeOverlay();
      showToast(err.message ?? 'Errore durante la pull. Riprova!', 'error');
    } finally {
      state.isPulling = false;
    }
  }

  /* ══════════════════════════════════════════════════════
     REVEAL
  ══════════════════════════════════════════════════════ */
  async function revealPull(data) {
    const p       = data.personaggio;
    const rarity  = normalizeRarity(p.rarità);
    const hasVideo = p.video_url && RARITY_VIDEO.has(rarity);

    // Imposta colore overlay per rarità
    setRarityOnOverlay(rarity);

    // ── Effetti premium tramite gacha-effects.js ──────────────
    if (window.GachaEffects) {
      await GachaEffects.play(rarity);
    } else if (RARITY_PREMIUM.has(rarity)) {
      // Fallback: solo flash CSS se gacha-effects.js non è caricato
      await cinematicFlash(rarity);
    }

    if (hasVideo) {
      await playRevealVideo(p.video_url, p);
    } else {
      await showCard(data);
    }

    // Audio
    playAudio(p.audio_url);
  }

  /* ── Video reveal ────────────────────────────────────── */
  async function playRevealVideo(videoUrl, personaggio) {
    showPhase('video');
    videoEl.src = videoUrl.startsWith('/') ? videoUrl : `/vid/${videoUrl}`;
    videoEl.muted   = true; // start muted (autoplay policy)
    videoEl.load();

    // Mostra hint unmute su mobile
    videoUnmuteBtn.style.display = 'block';
    videoUnmuteBtn.onclick = () => {
      videoEl.muted = false;
      state.videoUnmuted = true;
      videoUnmuteBtn.style.display = 'none';
    };

    return new Promise(resolve => {
      let resolved = false;
      const done = async () => {
        if (resolved) return;
        resolved = true;
        videoEl.removeEventListener('ended', done);
        videoEl.removeEventListener('error', done);
        videoEl.pause();
        videoEl.src = '';
        videoUnmuteBtn.style.display = 'none';
        await showCard({ personaggio, ...window._lastPullData });
        resolve();
      };

      // Timeout di sicurezza: 45s
      const safeguard = setTimeout(done, 45000);

      videoEl.addEventListener('ended', () => { clearTimeout(safeguard); done(); }, { once: true });
      videoEl.addEventListener('error', () => { clearTimeout(safeguard); done(); }, { once: true });

      // Play — fallback se autoplay bloccato
      const playPromise = videoEl.play();
      if (playPromise) {
        playPromise.catch(() => {
          // Autoplay bloccato: tenta muted
          videoEl.muted = true;
          videoEl.play().catch(() => done());
        });
      }
    });
  }

  /* ── Card reveal ─────────────────────────────────────── */
  async function showCard(data) {
    const p      = data.personaggio ?? data;
    const rarity = normalizeRarity(p.rarità);
    const color  = RARITY_COLORS[rarity] ?? '#fff';

    // Popola card
    cardImg.src        = p.img_url ? `/img/${p.img_url}` : '/img/cassa.png';
    cardImg.alt        = escapeHtml(p.nome ?? '');
    cardName.textContent  = escapeHtml(p.nome ?? '—');
    cardChars.textContent = escapeHtml(p.caratteristiche ?? '');

    cardRarityBar.className   = `gacha-card-rarity-bar rarity-${rarity}`;
    cardRarityLabel.textContent = (p.rarità ?? '—').toUpperCase();

    // Glow card bg
    cardBgGlow.style.background = `radial-gradient(circle, ${color}55 0%, transparent 70%)`;

    // Badge NEW
    cardNewBadge.style.display  = data.is_new  ? '' : 'none';

    // Badge 50/50
    card50Win.style.display  = 'none';
    card50Loss.style.display = 'none';
    if (data.vinto_50_50 === 1)       card50Win.style.display  = '';
    else if (data.vinto_50_50 === 0)  card50Loss.style.display = '';

    // Se primo personaggio nuovo → mostra link inventario
    btnInventory.style.display = data.is_new ? '' : 'none';

    // Particelle
    spawnParticles(color, rarity);

    // Mostra fase card con animazione
    showPhase('card');
    await delay(60); // micro-delay per il browser
    gachaCard.classList.add('is-revealed');
  }

  /* ══════════════════════════════════════════════════════
     OVERLAY MANAGEMENT
  ══════════════════════════════════════════════════════ */
  function openOverlay() {
    overlay.classList.add('is-visible');
    state.overlayOpen = true;
  }

  function closeOverlay() {
    overlay.classList.remove('is-visible');
    state.overlayOpen = false;

    // Reset overlay per il prossimo uso
    setTimeout(() => {
      setRarityOnOverlay('comune');
      gachaCard.classList.remove('is-revealed');
      glowBurst.classList.remove('is-rainbow');
      phaseVideo.style.display   = 'none';
      phaseCard.style.display    = 'none';
      phaseOpening.style.display = 'flex';
      videoEl.src = '';
      videoEl.pause();
      card50Win.style.display  = 'none';
      card50Loss.style.display = 'none';
      cardNewBadge.style.display = 'none';
      state.videoUnmuted = false;
    }, 400);

    if (window.GachaEffects) GachaEffects.unlockScroll();
    else document.body.style.overflow = '';
  }

  function showPhase(phase) {
    phaseOpening.style.display = phase === 'opening' ? 'flex' : 'none';
    phaseVideo.style.display   = phase === 'video'   ? 'flex' : 'none';
    phaseCard.style.display    = phase === 'card'    ? 'flex' : 'none';
  }

  function setRarityOnOverlay(rarity) {
    overlay.setAttribute('data-rarity', rarity);
    const color = RARITY_COLORS[rarity] ?? '#fff';
    overlay.style.setProperty('--banner-accent',      color);
    overlay.style.setProperty('--banner-accent-glow', color + '55');

    if (rarity === 'speciale') {
      glowBurst.classList.add('is-rainbow');
    } else {
      glowBurst.classList.remove('is-rainbow');
    }
  }

  /* ══════════════════════════════════════════════════════
     OVERLAY BUTTONS
  ══════════════════════════════════════════════════════ */
  function initOverlayButtons() {
    // Apri ancora: NON chiude overlay, nuovo pull diretto
    btnPullAgain?.addEventListener('click', () => {
      if (state.isPulling) return;
      // Reset card per animazione
      gachaCard.classList.remove('is-revealed');
      showPhase('opening');
      setRarityOnOverlay('comune');
      // Piccolo delay poi pull
      setTimeout(() => startPullFromOverlay(), 80);
    });

    btnClose?.addEventListener('click', () => {
      if (state.isPulling) return;
      closeOverlay();
    });
  }

  async function startPullFromOverlay() {
    // Pull sullo stesso banner attivo senza chiudere overlay
    if (state.isPulling) return;
    state.isPulling = true;

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

      state.soldi        = data.soldi_rimasti  ?? state.soldi;
      state.pityStandard = data.pity_standard  ?? state.pityStandard;
      state.pityEvento   = data.pity_evento    ?? state.pityEvento;
      state.garantito    = data.garantito      ?? state.garantito;
      window._lastPullData = data;

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

  /* ══════════════════════════════════════════════════════
     REVEAL CON DATI ESTERNI (per riscattaCodice)
  ══════════════════════════════════════════════════════ */
  window.GachaUI = {
    showToast,
    openRevealWithData: async (p) => {
      document.body.style.overflow = 'hidden';
      openOverlay();
      showPhase('card');
      setRarityOnOverlay(normalizeRarity(p.rarità ?? ''));
      await delay(60);
      await showCard({ personaggio: p, is_new: true, vinto_50_50: null });
    },
  };

  /* ══════════════════════════════════════════════════════
     UPDATE UI BANNER (pity / punti dopo pull)
  ══════════════════════════════════════════════════════ */
  function updateBannerUI() {
    // Punti
    document.querySelectorAll('#user-points-std, .user-points-evt').forEach(el => {
      el.textContent = state.soldi.toLocaleString('it-IT');
    });

    // Pity standard
    const pityStdHard = window.GACHA_INIT?.pityHardStd ?? 90;
    const pityStdSoft = window.GACHA_INIT?.pitySoftStd ?? 70;
    const stdPct = Math.min(100, Math.round(state.pityStandard / pityStdHard * 100));
    const stdFill = $('pity-std-fill');
    const stdNum  = $('pity-std-num');
    const stdNote = $('pity-std-note');
    if (stdFill) stdFill.style.width = stdPct + '%';
    if (stdNum)  stdNum.textContent  = `${state.pityStandard} / ${pityStdHard}`;
    if (stdNote) {
      if (state.pityStandard >= pityStdHard) {
        stdNote.textContent = '★ Garantito: prossima pull è leggendario!';
        stdNote.classList.add('is-active');
      } else if (state.pityStandard >= pityStdSoft) {
        stdNote.textContent = '✦ Soft pity attivo — probabilità leggendario aumentata';
        stdNote.classList.add('is-active');
      } else {
        stdNote.textContent = `Garantito leggendario in ${pityStdHard - state.pityStandard} pull`;
        stdNote.classList.remove('is-active');
      }
    }

    // Pity evento (tutti i banner evento)
    const pityEvtHard = window.GACHA_INIT?.pityHardEvt ?? 80;
    const pityEvtSoft = window.GACHA_INIT?.pitySoftEvt ?? 65;
    const evtPct = Math.min(100, Math.round(state.pityEvento / pityEvtHard * 100));
    document.querySelectorAll('.pity-evt-fill').forEach(el => { el.style.width = evtPct + '%'; });
    document.querySelectorAll('.pity-evt-num').forEach(el => { el.textContent = `${state.pityEvento} / ${pityEvtHard}`; });
    document.querySelectorAll('.pity-evt-note').forEach(el => {
      if (state.pityEvento >= pityEvtSoft) {
        el.textContent = '✦ Soft pity attivo — probabilità in aumento';
        el.classList.add('is-active');
      } else {
        el.textContent = `Garantito segreto in ${pityEvtHard - state.pityEvento} pull`;
        el.classList.remove('is-active');
      }
    });

    // Badge garantito
    document.querySelectorAll('[id^="garantito-badge-"]').forEach(el => {
      el.style.display = state.garantito ? '' : 'none';
    });
  }

  /* ══════════════════════════════════════════════════════
     PARTICELLE
  ══════════════════════════════════════════════════════ */
  function spawnParticles(color, rarity) {
    // Meno particelle su mobile
    const isMobile = window.innerWidth < 768;
    const count = rarity === 'theone' || rarity === 'segreto' ? (isMobile ? 40 : 90)
                : rarity === 'speciale' ? (isMobile ? 30 : 70)
                : RARITY_PREMIUM.has(rarity) ? (isMobile ? 20 : 50)
                : (isMobile ? 10 : 25);

    for (let i = 0; i < count; i++) {
      const p = document.createElement('div');
      p.className = 'gacha-particle';

      const size  = 3 + Math.random() * 5;
      const angle = Math.random() * Math.PI * 2;
      const dist  = 80 + Math.random() * 200;
      const px    = Math.cos(angle) * dist;
      const py    = Math.sin(angle) * dist;
      const dur   = 600 + Math.random() * 900;

      p.style.cssText = `
        width:${size}px;height:${size}px;
        background:${color};
        box-shadow:0 0 ${size*2}px ${color};
        --px:${px}px;--py:${py}px;--dur:${dur}ms;
      `;
      particlesLayer.appendChild(p);
      setTimeout(() => p.remove(), dur + 100);
    }
  }

  /* ══════════════════════════════════════════════════════
     CINEMATIC FLASH
  ══════════════════════════════════════════════════════ */
  async function cinematicFlash(rarity) {
    const color = RARITY_COLORS[rarity] ?? '#fff';
    flashEl.style.background = color;
    flashEl.style.opacity    = '0.7';
    await delay(120);
    flashEl.style.transition = 'opacity 0.5s ease';
    flashEl.style.opacity    = '0';
    await delay(500);
    flashEl.style.transition = '';
  }

  /* ══════════════════════════════════════════════════════
     AUDIO
  ══════════════════════════════════════════════════════ */
  function playAudio(audioUrl) {
    if (!audioUrl) return;
    try {
      audioEl.src = `/audio/${audioUrl}`;
      audioEl.currentTime = 0;
      audioEl.volume = 0.8;
      audioEl.play().catch(() => {});
    } catch(e) {}
  }

  /* ══════════════════════════════════════════════════════
     TOAST
  ══════════════════════════════════════════════════════ */
  let toastTimer = null;
  function showToast(msg, type = 'info') {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.className   = `gacha-toast${type === 'error' ? ' gacha-toast--error' : ''}`;
    toastEl.classList.add('is-visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('is-visible'), 3500);
  }

  /* ══════════════════════════════════════════════════════
     KEYBOARD
  ══════════════════════════════════════════════════════ */
  function initKeyboard() {
    document.addEventListener('keydown', e => {
      if (e.repeat) return;

      if (e.code === 'Space' && !state.overlayOpen) {
        e.preventDefault();
        if (!state.isPulling) startPull(state.activeBannerId);
      }

      if (e.code === 'Enter' && state.overlayOpen && !state.isPulling) {
        e.preventDefault();
        btnPullAgain?.click();
      }

      if (e.code === 'Escape' && state.overlayOpen && !state.isPulling) {
        e.preventDefault();
        closeOverlay();
      }
    });
  }

  /* ══════════════════════════════════════════════════════
     ADMIN CHEATS
  ══════════════════════════════════════════════════════ */
  function initAdminCheats() {
    document.querySelectorAll('.admin-force-rarity').forEach(cb => {
      cb.addEventListener('change', () => {
        // Solo una checkbox attiva alla volta
        document.querySelectorAll('.admin-force-rarity').forEach(other => {
          if (other !== cb) other.checked = false;
        });
        state.adminForceRarity = cb.checked ? cb.dataset.rarity : null;
      });
    });
  }

  /* ══════════════════════════════════════════════════════
     COUNTDOWN TIMER
  ══════════════════════════════════════════════════════ */
  function initTimers() {
    document.querySelectorAll('.gacha-timer-digits[data-ends]').forEach(el => {
      updateTimer(el);
      setInterval(() => updateTimer(el), 60_000);
    });
  }

  function updateTimer(el) {
    const endsAt = new Date(el.dataset.ends.replace(' ', 'T') + 'Z').getTime();
    const now    = Date.now();
    const diff   = endsAt - now;

    if (diff <= 0) {
      el.closest('.gacha-timer-wrap')?.remove();
      return;
    }

    const days  = Math.floor(diff / 86_400_000);
    const hours = Math.floor((diff % 86_400_000) / 3_600_000);
    const mins  = Math.floor((diff % 3_600_000) / 60_000);

    const dEl = el.querySelector('.t-days');
    const hEl = el.querySelector('.t-hours');
    const mEl = el.querySelector('.t-mins');
    if (dEl) dEl.textContent = days;
    if (hEl) hEl.textContent = String(hours).padStart(2, '0');
    if (mEl) mEl.textContent = String(mins).padStart(2, '0');
  }

  /* ══════════════════════════════════════════════════════
     SETTINGS BUTTON
  ══════════════════════════════════════════════════════ */
  function initSettingsBtn() {
    $('btn-settings')?.addEventListener('click', () => {
      const modal = document.getElementById('impostazioniModal');
      if (modal && window.bootstrap) {
        const m = bootstrap.Modal.getOrCreateInstance(modal);
        m.show();
      }
    });
  }

  /* ══════════════════════════════════════════════════════
     ACHIEVEMENTS (compatibilità sistema esistente)
  ══════════════════════════════════════════════════════ */
  async function triggerAchievements(data) {
    if (typeof unlockAchievement !== 'function') return;
    try {
      // Achievement "prima pull"
      unlockAchievement(5);

      // Fetch casse aperte (come nell'originale)
      const r = await fetch('/api/get_casse_aperte');
      const d = await r.json();
      const casse = d.total ?? 0;
      if (casse >= 100) unlockAchievement(8);
      if (casse >= 500) unlockAchievement(16);
    } catch(e) {}
  }

  /* ══════════════════════════════════════════════════════
     UTILITY
  ══════════════════════════════════════════════════════ */
  function normalizeRarity(r) {
    return String(r ?? 'comune')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[\s_-]+/g, '')
      .trim();
  }

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  /* ══════════════════════════════════════════════════════
     BOOT
  ══════════════════════════════════════════════════════ */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
