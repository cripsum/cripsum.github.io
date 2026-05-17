/**
 * gacha.js — GoonLand Gacha System v3.0
 *
 * Fix v3:
 *  1. Video segreto/theone: card appare dopo 12s con video come sfondo
 *  2. Apertura veloce con F (fast pull): skip animazione, no skip su nuovi/speciali/segreti/theone
 *  3. Speciale: animazione arcobaleno fullscreen (rimossa scritta, no cerchio)
 *  4. Multi pull 10x con skip intelligente e resoconto finale
 *  5-7. Gestite nel CSS (navbar offset, layout, spazio vuoto)
 */

'use strict';

(function () {

  /* ══════════════════════════════════════════════════════
     CONFIG
  ══════════════════════════════════════════════════════ */
  const API_PULL = '/api/api_gacha_pull';
  const VIDEO_CARD_DELAY_MS = 12000; // ms dopo cui card appare sopra video (#1)

  const RARITY_VIDEO   = new Set(['segreto', 'theone']);
  const RARITY_NO_SKIP = new Set(['segreto', 'theone', 'speciale']); // non skippabili con F
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
    isFastPull:       false,  // #2: modalità apertura veloce
    overlayOpen:      false,
    soldi:            window.GACHA_INIT?.soldi        ?? 0,
    pityStandard:     window.GACHA_INIT?.pityStandard ?? 0,
    pityEvento:       window.GACHA_INIT?.pityEvento   ?? 0,
    garantito:        window.GACHA_INIT?.garantito     ?? false,
    adminForceRarity: null,
    canSkip:          false,
    skipTimeout:      null,
    // Multi pull #4
    isMulti:          false,
    multiResults:     [],
    multiTotal:       0,
    multiCurrent:     0,
    multiSkipping:    false,
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
  const phaseMulti     = $('phase-multi'); // #4

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
  const btnPull10      = $('btn-pull-10');   // #4
  const btnClose       = $('btn-close-overlay');
  const btnInventory   = $('btn-go-inventory');

  const audioEl        = $('gacha-audio');
  const toastEl        = $('gacha-toast');

  /* ══════════════════════════════════════════════════════
     INIT
  ══════════════════════════════════════════════════════ */
  function init() {
    fixLayout();
    createStars($('stars'), 100);
    createStars(overlayStars, 60);
    initSidebarCards();
    initPullButtons();
    initOverlayButtons();
    initSkipButton();
    initKeyboard();
    initAdminCheats();
    initTimers();
    initSettingsBtn();
    initLeaderboard();
    injectHistoryModal();
    injectMultiModal(); // #4
  }

  /* ════════════════════════════════════════════════════
     FIX 5/6/7 — LAYOUT: navbar offset + spazio vuoto
  ════════════════════════════════════════════════════ */
  function fixLayout() {
    const nav = document.querySelector('nav.navbar, header.navbar, #navbar, .navbar');
    const sidebar = document.getElementById('gacha-sidebar');
    const layout  = document.getElementById('gacha-layout');

    if (!nav) return;

    const apply = () => {
      const h = nav.getBoundingClientRect().height;
      if (h <= 0) return;

      // Sidebar sticky top
      if (sidebar) {
        sidebar.style.top    = h + 'px';
        sidebar.style.height = `calc(100dvh - ${h}px)`;
      }

      // Layout e banner views
      const vhMinusNav = `calc(100dvh - ${h}px)`;
      if (layout) layout.style.minHeight = vhMinusNav;

      $$('.gacha-banner-view').forEach(v => {
        v.style.minHeight = vhMinusNav;
      });

      // FIX 5/7: nessun padding-bottom residuo, layout riempie tutto
      document.body.style.paddingBottom = '0';
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
     SIDEBAR CARDS
  ════════════════════════════════════════════════════ */
  function initSidebarCards() {
    document.getElementById('gsb-banners')?.addEventListener('click', e => {
      const card = e.target.closest('.gsb-card[data-banner-id]');
      if (!card || state.isPulling) return;
      switchBanner(card.dataset.bannerId, card.dataset.bannerType);
    });
  }

  function switchBanner(bannerId, bannerType) {
    if (state.activeBannerId === bannerId) return;
    $$('.gsb-card').forEach(c => { c.classList.remove('is-active'); c.setAttribute('aria-pressed','false'); });
    const activeCard = document.querySelector(`.gsb-card[data-banner-id="${bannerId}"]`);
    if (activeCard) {
      activeCard.classList.add('is-active');
      activeCard.setAttribute('aria-pressed','true');
      activeCard.scrollIntoView({ behavior:'smooth', block:'nearest', inline:'center' });
    }
    $(`banner-view-${state.activeBannerId}`)?.style && ($(`banner-view-${state.activeBannerId}`).style.display = 'none');
    const nv = $(`banner-view-${bannerId}`);
    if (nv) {
      nv.style.cssText = 'display:flex;opacity:0;flex:1;';
      requestAnimationFrame(() => {
        nv.style.transition = 'opacity .35s ease';
        nv.style.opacity = '1';
        setTimeout(() => nv.style.transition = '', 370);
      });
    }
    state.activeBannerId   = bannerId;
    state.activeBannerType = bannerType ?? 'standard';
    if (window.GACHA_INIT) window.GACHA_INIT.activeBannerId = bannerId;
  }

  /* ════════════════════════════════════════════════════
     PULL BUTTONS
  ════════════════════════════════════════════════════ */
  function initPullButtons() {
    // Unico listener per tutti i .gacha-pull-btn
    // data-pull-qty="10" → multi, altrimenti singola
    $$('.gacha-pull-btn[data-banner-id]').forEach(btn => {
      btn.addEventListener('click', () => {
        if (state.isPulling) return;
        if (btn.dataset.pullQty === '10') {
          startMultiPull(btn.dataset.bannerId);
        } else {
          state.isFastPull = false;
          startPull(btn.dataset.bannerId);
        }
      });
    });
  }

  /* ════════════════════════════════════════════════════
     SINGLE PULL
  ════════════════════════════════════════════════════ */
  async function startPull(bannerId, fastMode = false) {
    if (state.isPulling) return;
    state.isPulling  = true;
    state.isFastPull = fastMode;

    stopAudio();
    lockScroll();
    if (!state.overlayOpen) openOverlay();
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
      state.canSkip = !data.is_new && !RARITY_NO_SKIP.has(rarity);

      if (!RARITY_VIDEO.has(rarity)) playAudio(data.personaggio.audio_url);

      // #2 — Fast pull: se la rarità è skippabile salta animazione orb
      const orbWait = (state.isFastPull && state.canSkip) ? 0 : 900;
      if (orbWait > 0) await delay(orbWait);

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
     #4 — MULTI PULL (10x)
  ════════════════════════════════════════════════════ */
  const API_MULTI = '/api/api_gacha_multi_pull';

  async function startMultiPull(bannerId) {
    if (state.isPulling) return;

    state.isMulti       = true;
    state.multiResults  = [];
    state.multiSkipping = false;
    state.isPulling     = true;

    stopAudio();
    lockScroll();
    openOverlay();
    showPhase('opening');
    setRarityOnOverlay('comune');

    const payload = { banner_id: bannerId, quantity: 10 };
    if (state.adminForceRarity) payload.force_rarity = state.adminForceRarity;

    try {
      // UN SOLO FETCH — tutte e 10 le pull calcolate server-side
      const resp = await fetch(API_MULTI, {
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

      // Aggiorna stato globale con i dati finali del server
      state.soldi        = data.soldi_rimasti ?? state.soldi;
      state.pityStandard = data.pity_standard ?? state.pityStandard;
      state.pityEvento   = data.pity_evento   ?? state.pityEvento;
      state.garantito    = data.garantito     ?? state.garantito;
      state.multiResults = data.pulls;

      // Mostra le pull una ad una (dati già pronti, niente fetch)
      await showMultiPulls(data.pulls, bannerId);
      updateBannerUI();

    } catch(err) {
      console.error('[Gacha] Multi pull error:', err);
      state.isMulti   = false;
      state.isPulling = false;
      closeOverlay();
      showToast(err.message ?? 'Errore multi pull. Riprova!', 'error');
      return;
    }

    state.isMulti   = false;
    state.isPulling = false;
  }

  // Mostra le 10 pull una ad una dai dati già ricevuti
  async function showMultiPulls(pulls, bannerId) {
    const total = pulls.length;

    for (let i = 0; i < total; i++) {
      const pullData = pulls[i];
      const rarity   = normalizeRarity(pullData.personaggio.rarità);
      const isSpecial = RARITY_NO_SKIP.has(rarity);
      const isNew     = pullData.is_new;
      const mustShow  = isNew || isSpecial;

      // Aggiorna counter
      const counter = $('multi-counter');
      if (counter) { counter.textContent = `${i+1} / ${total}`; counter.style.display = 'block'; }

      if (state.multiSkipping && !mustShow) {
        // Skip veloce: solo attesa minima
        await delay(30);
        continue;
      }

      state.multiSkipping = false;

      // Audio solo se la pull va mostrata e non ha video
      const hasVideo = pullData.personaggio.video_url && RARITY_VIDEO.has(rarity);
      if (!hasVideo) playAudio(pullData.personaggio.audio_url);

      // Orb animazione
      showPhase('opening');
      setRarityOnOverlay('comune');
      await delay(800);

      // Reveal (usa i dati già ricevuti, niente fetch)
      await revealPull(pullData);

      // Aspetta input utente
      const isLast = (i === total - 1);
      await waitForMultiNext(i, total, isLast);
      stopAudio();
    }

    // Counter via, resoconto
    const counter = $('multi-counter');
    if (counter) counter.style.display = 'none';
    showMultiSummary(pulls);
  }

  function waitForMultiNext(idx, total, isLast) {
    return new Promise(resolve => {
      const btnNext = $('btn-multi-next');
      const btnSkip = $('btn-multi-skip');

      if (btnNext) {
        btnNext.style.display = '';
        btnNext.innerHTML = isLast
          ? '<i class="fas fa-flag-checkered"></i> Resoconto'
          : `<i class="fas fa-forward"></i> Prossima (${idx+1}/${total})`;
      }
      if (btnSkip) btnSkip.style.display = isLast ? 'none' : '';
      if (btnPullAgain) btnPullAgain.style.display = 'none';

      let resolved = false;
      const done = (skip) => {
        if (resolved) return;
        resolved = true;
        state.multiSkipping = skip;
        cleanup();
        resolve();
      };

      const onNext = () => done(false);
      const onSkip = () => done(true);

      function cleanup() {
        btnNext?.removeEventListener('click', onNext);
        btnSkip?.removeEventListener('click', onSkip);
        document.removeEventListener('keydown', onKey);
      }

      const onKey = e => {
        if (e.code === 'Enter') { e.preventDefault(); done(false); }
        if (e.code === 'KeyS' && !isLast) { e.preventDefault(); done(true); }
      };

      btnNext?.addEventListener('click', onNext, { once: true });
      btnSkip?.addEventListener('click', onSkip, { once: true });
      document.addEventListener('keydown', onKey);
    });
  }

  function injectMultiModal() {
    const actions = $('overlay-actions');
    if (!actions || $('btn-multi-next')) return; // evita duplicati

    const btnNext = document.createElement('button');
    btnNext.id = 'btn-multi-next';
    btnNext.className = 'gacha-btn gacha-btn--primary';
    btnNext.style.display = 'none';
    btnNext.innerHTML = '<i class="fas fa-forward"></i> Prossima';
    actions.insertBefore(btnNext, actions.firstChild);

    const btnSkip = document.createElement('button');
    btnSkip.id = 'btn-multi-skip';
    btnSkip.className = 'gacha-btn gacha-btn--ghost';
    btnSkip.style.display = 'none';
    btnSkip.innerHTML = '<i class="fas fa-forward-fast"></i> Salta [S]';
    actions.insertBefore(btnSkip, btnNext.nextSibling);

    const counter = document.createElement('div');
    counter.id = 'multi-counter';
    counter.style.cssText = 'position:absolute;top:14px;right:18px;font-size:.75rem;color:rgba(255,255,255,.35);font-weight:600;z-index:20;display:none;';
    overlay.appendChild(counter);
  }

  function showMultiSummary(results) {
    stopAudio(); // nessun audio nel resoconto

    const RARITY_C = {
      comune:'#9ca3af',raro:'#38bdf8',epico:'#c084fc',
      leggendario:'#fbbf24',speciale:'#fff',segreto:'#a855f7',theone:'#60a5fa'
    };

    phaseOpening.style.display = 'none';
    phaseVideo.style.display   = 'none';
    phaseCard.style.display    = 'none';
    if ($('btn-multi-next')) $('btn-multi-next').style.display = 'none';
    if ($('btn-multi-skip')) $('btn-multi-skip').style.display = 'none';
    if (btnPullAgain) btnPullAgain.style.display = '';

    let summary = $('phase-summary');
    if (!summary) {
      summary = document.createElement('div');
      summary.id = 'phase-summary';
      summary.className = 'gacha-phase gacha-phase--summary';
      overlay.appendChild(summary);
    }
    summary.style.display = 'flex';

    const newCount = results.filter(r => r.is_new).length;
    const cards = results.map(r => {
      const p  = r.personaggio;
      const ra = normalizeRarity(p.rarità);
      const co = RARITY_C[ra] ?? '#fff';
      return `
        <div class="gms-card" style="border-color:${co}25">
          <div class="gms-card-img" style="border-color:${co}55">
            <img src="${p.img_url ? '/img/'+p.img_url : '/img/cassa.png'}"
                 alt="${escapeHtml(p.nome)}" onerror="this.src='/img/cassa.png'">
            ${r.is_new ? '<span class="gms-new">NEW</span>' : ''}
          </div>
          <p class="gms-rarity" style="color:${co}">${escapeHtml(p.rarità)}</p>
          <p class="gms-name">${escapeHtml(p.nome)}</p>
        </div>`;
    }).join('');

    summary.innerHTML = `
      <div class="gms-inner">
        <div class="gms-header">
          <h2 class="gms-title">Riepilogo Multi</h2>
          ${newCount > 0 ? `<span class="gms-new-count">${newCount} NUOV${newCount===1?'O':'I'}!</span>` : ''}
        </div>
        <div class="gms-cards">${cards}</div>
        <div class="gms-actions">
          <button class="gacha-btn gacha-btn--primary" id="btn-multi-again">
            <i class="fas fa-rotate-right"></i> Multi ancora
          </button>
          <button class="gacha-btn gacha-btn--ghost" id="btn-summary-close">
            <i class="fas fa-xmark"></i> Chiudi
          </button>
          <a href="/inventario" class="gacha-btn gacha-btn--ghost">
            <i class="fas fa-layer-group"></i> Inventario
          </a>
        </div>
      </div>`;

    $('btn-summary-close')?.addEventListener('click', () => {
      summary.style.display = 'none';
      state.isMulti       = false;
      state.multiSkipping = false;
      closeOverlay();
    });
    $('btn-multi-again')?.addEventListener('click', () => {
      summary.style.display = 'none';
      state.isMulti       = false;
      state.multiSkipping = false;
      startMultiPull(state.activeBannerId);
    });
  }


  /* ════════════════════════════════════════════════════
     REVEAL PRINCIPALE
  ════════════════════════════════════════════════════ */
  async function revealPull(data) {
    const p       = data.personaggio;
    const rarity  = normalizeRarity(p.rarità);
    const hasVideo = p.video_url && RARITY_VIDEO.has(rarity);

    setRarityOnOverlay(rarity);

    if (hasVideo) {
      await fadeToBlackThenVideo(p.video_url, data);
    } else {
      if (window.GachaEffects && RARITY_EFFECTS.has(rarity) && !state.isFastPull) {
        await GachaEffects.play(rarity);
      }
      await showCard(data);
    }
  }

  /* ════════════════════════════════════════════════════
     #1 — VIDEO + CARD OVERLAY DOPO 12s
  ════════════════════════════════════════════════════ */
  async function fadeToBlackThenVideo(videoUrl, data) {
    const velo = document.createElement('div');
    velo.style.cssText = 'position:absolute;inset:0;background:#000;z-index:30;opacity:0;transition:opacity .8s ease;pointer-events:none;';
    overlay.appendChild(velo);
    await delay(20);
    velo.style.opacity = '1';
    await delay(850);

    showPhase('video');
    velo.style.opacity = '0';
    setTimeout(() => velo.remove(), 800);

    await playRevealVideoWithEarlyCard(videoUrl, data);
  }

  async function playRevealVideoWithEarlyCard(videoUrl, data) {
    videoEl.src    = videoUrl.startsWith('/') ? videoUrl : `/vid/${videoUrl}`;
    videoEl.muted  = false;
    videoEl.volume = 1;
    videoEl.load();
    videoUnmuteBtn.style.display = 'none';

    let cardShown = false;

    // #1 — Mostra la card dopo VIDEO_CARD_DELAY_MS con video ancora in play come sfondo
    const cardTimer = setTimeout(async () => {
      if (!cardShown) {
        cardShown = true;
        await showCardOverVideo(data);
      }
    }, VIDEO_CARD_DELAY_MS);

    return new Promise(resolve => {
      let resolved = false;
      const done = async () => {
        if (resolved) return;
        resolved = true;
        clearTimeout(safeguard);
        clearTimeout(cardTimer);
        videoEl.removeEventListener('ended', done);
        videoEl.removeEventListener('error', done);
        // Se il video finisce prima che la card sia apparsa, mostrala ora
        if (!cardShown) {
          cardShown = true;
          videoEl.pause();
          videoEl.src = '';
          hideSkipBtn();
          await showCard(data);
        } else {
          // Card già visibile, video finito — lascia il video (src vuoto silenzioso)
          videoEl.pause();
          videoEl.src = '';
        }
        resolve();
      };

      const safeguard = setTimeout(done, 90000);
      videoEl.addEventListener('ended', done, { once: true });
      videoEl.addEventListener('error', done, { once: true });

      const pp = videoEl.play();
      if (pp) pp.catch(() => {
        videoEl.muted = true;
        videoEl.play().catch(() => done());
      });
    });
  }

  // Mostra card in overlay sovrapposta al video (video come sfondo)
  async function showCardOverVideo(data) {
    const p      = data.personaggio ?? data;
    const rarity = normalizeRarity(p.rarità);
    const color  = RARITY_COLORS[rarity] ?? '#fff';

    cardImg.src = p.img_url ? `/img/${p.img_url}` : '/img/cassa.png';
    cardImg.alt = escapeHtml(p.nome ?? '');
    cardName.textContent         = escapeHtml(p.nome ?? '—');
    cardRarityBar.className      = `gacha-card-rarity-bar rarity-${rarity}`;
    cardRarityLabel.textContent  = (p.rarità ?? '—').toUpperCase();
    cardBgGlow.style.background  = `radial-gradient(circle,${color}55 0%,transparent 70%)`;

    cardNewBadge.style.display = data.is_new ? '' : 'none';
    card50Win.style.display    = 'none';
    card50Loss.style.display   = 'none';
    if      (data.vinto_50_50 === 1) card50Win.style.display  = '';
    else if (data.vinto_50_50 === 0) card50Loss.style.display = '';

    if (btnInventory) btnInventory.style.display = '';
    spawnParticles(color, rarity);

    // #1 — Mostra card sovrapposta, video resta sotto
    // Il video continua in background, phase-video resta visibile
    // La card si sovrappone con position absolute
    const cardOverlay = document.createElement('div');
    cardOverlay.id = 'card-over-video';
    cardOverlay.style.cssText = `
      position:absolute;inset:0;z-index:15;
      display:flex;flex-direction:column;align-items:center;justify-content:center;
      gap:24px;padding:20px 20px calc(20px + var(--safe-bot,0px));
      opacity:0;transition:opacity .7s ease;
      background:linear-gradient(to top,rgba(0,0,0,0.75) 0%,transparent 60%);
    `;

    // Clona card e actions in questo layer
    const cardClone   = gachaCard.cloneNode(true);
    const actClone    = $('overlay-actions').cloneNode(true);
    cardClone.id      = 'gacha-card-video-clone';
    cardClone.classList.remove('is-revealed','is-idle');
    cardOverlay.appendChild(cardClone);
    cardOverlay.appendChild(actClone);
    phaseVideo.appendChild(cardOverlay);

    // Collega pulsanti clone
    const cloneClose = cardOverlay.querySelector('#btn-close-overlay');
    const cloneAgain = cardOverlay.querySelector('#btn-pull-again');
    const cloneMulti = cardOverlay.querySelectorAll('[id^="btn-multi"]');

    cloneClose?.addEventListener('click', () => {
      videoEl.pause(); videoEl.src = '';
      cardOverlay.remove();
      closeOverlay();
    });
    cloneAgain?.addEventListener('click', () => {
      videoEl.pause(); videoEl.src = '';
      cardOverlay.remove();
      state.isFastPull = false;
      pullAgainFromOverlay();
    });
    cloneMulti.forEach(b => b.style.display = 'none');

    // Multi: collega Prossima
    const cloneNext = cardOverlay.querySelector('#btn-multi-next');
    const cloneSkip = cardOverlay.querySelector('#btn-multi-skip');
    if (state.isMulti && cloneNext) {
      const origNext = $('btn-multi-next');
      cloneNext.style.display = origNext?.style.display ?? 'none';
      cloneNext.textContent = origNext?.textContent ?? '';
      cloneNext.addEventListener('click', () => origNext?.click());
    }
    if (state.isMulti && cloneSkip) {
      const origSkip = $('btn-multi-skip');
      cloneSkip.style.display = origSkip?.style.display ?? 'none';
      cloneSkip.addEventListener('click', () => origSkip?.click());
    }

    await delay(40);
    cardOverlay.style.opacity = '1';
    const clone = cardOverlay.querySelector('.gacha-card') ?? cardClone;
    clone.classList.remove('is-revealed','is-idle');
    await delay(80);
    clone.classList.add('is-revealed');
    await delay(620);
    clone.classList.add('is-idle');
  }

  /* ════════════════════════════════════════════════════
     SHOW CARD (rarità senza video)
  ════════════════════════════════════════════════════ */
  async function showCard(data) {
    const p      = data.personaggio ?? data;
    const rarity = normalizeRarity(p.rarità);
    const color  = RARITY_COLORS[rarity] ?? '#fff';

    cardImg.src = p.img_url ? `/img/${p.img_url}` : '/img/cassa.png';
    cardImg.alt = escapeHtml(p.nome ?? '');
    cardName.textContent         = escapeHtml(p.nome ?? '—');
    cardRarityBar.className      = `gacha-card-rarity-bar rarity-${rarity}`;
    cardRarityLabel.textContent  = (p.rarità ?? '—').toUpperCase();
    cardBgGlow.style.background  = `radial-gradient(circle,${color}55 0%,transparent 70%)`;

    cardNewBadge.style.display = data.is_new ? '' : 'none';
    card50Win.style.display    = 'none';
    card50Loss.style.display   = 'none';
    if      (data.vinto_50_50 === 1) card50Win.style.display  = '';
    else if (data.vinto_50_50 === 0) card50Loss.style.display = '';

    if (btnInventory) btnInventory.style.display = '';

    // Multi: mostra/nascondi bottoni giusti
    const btnNext = $('btn-multi-next');
    const btnSkip = $('btn-multi-skip');
    const counter = $('multi-counter');
    if (state.isMulti) {
      if (btnPullAgain) btnPullAgain.style.display = 'none';
      if (counter) counter.style.display = 'block';
    } else {
      if (btnPullAgain) btnPullAgain.style.display = '';
      if (btnNext) btnNext.style.display = 'none';
      if (btnSkip) btnSkip.style.display = 'none';
      if (counter) counter.style.display = 'none';
    }

    if (state.canSkip) showSkipBtn();

    spawnParticles(color, rarity);
    showPhase('card');

    gachaCard.classList.remove('is-revealed','is-idle');
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
    stopAudio();
    overlay.classList.remove('is-visible');
    state.overlayOpen = false;
    hideSkipBtn();

    // Rimuovi card-over-video se esiste
    $('card-over-video')?.remove();
    $('phase-summary') && ($('phase-summary').style.display = 'none');

    setTimeout(() => {
      setRarityOnOverlay('comune');
      gachaCard.classList.remove('is-revealed','is-idle');
      glowBurst.classList.remove('is-rainbow');
      phaseVideo.style.display   = 'none';
      phaseCard.style.display    = 'none';
      phaseOpening.style.display = 'flex';
      videoEl.pause();
      videoEl.src = '';
      card50Win.style.display  = 'none';
      card50Loss.style.display = 'none';
      cardNewBadge.style.display = 'none';
      state.canSkip    = false;
      state.isMulti    = false;
      particlesLayer.innerHTML = '';
      // Reset multi buttons
      $('btn-multi-next') && ($('btn-multi-next').style.display = 'none');
      $('btn-multi-skip') && ($('btn-multi-skip').style.display = 'none');
      $('multi-counter')  && ($('multi-counter').style.display  = 'none');
      if (btnPullAgain) btnPullAgain.style.display = '';
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
     SKIP BUTTON
  ════════════════════════════════════════════════════ */
  function initSkipButton() {
    skipBtn?.addEventListener('click', doSkip);
  }

  function doSkip() {
    if (!state.canSkip || state.isPulling) return;
    if (phaseVideo.style.display !== 'none') {
      videoEl.pause();
      videoEl.dispatchEvent(new Event('ended'));
    }
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
      stopAudio();
      $('card-over-video')?.remove();
      gachaCard.classList.remove('is-revealed','is-idle');
      showPhase('opening');
      setRarityOnOverlay('comune');
      hideSkipBtn();
      state.isFastPull = false;
      setTimeout(() => pullAgainFromOverlay(), 80);
    });

    btnClose?.addEventListener('click', () => {
      if (state.isPulling) return;
      closeOverlay();
    });
  }

  async function pullAgainFromOverlay() {
    if (state.isPulling) return;
    state.isPulling  = true;
    state.isFastPull = false;
    stopAudio();

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
      if (!RARITY_VIDEO.has(rarity)) playAudio(data.personaggio.audio_url);

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
     GachaUI (per riscattaCodice)
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
    const sf=$('pity-std-fill'), sn=$('pity-std-num'), so=$('pity-std-note');
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
    const pHE=window.GACHA_INIT?.pityHardEvt??80, pSE=window.GACHA_INIT?.pitySoftEvt??65;
    $$('.pity-evt-fill').forEach(el=>el.style.width=Math.min(100,Math.round(state.pityEvento/pHE*100))+'%');
    $$('.pity-evt-num').forEach(el=>el.textContent=`${state.pityEvento} / ${pHE}`);
    $$('.pity-evt-note').forEach(el=>{
      el.textContent=state.pityEvento>=pSE?'✦ Soft pity attivo — probabilità in aumento':`Garantito segreto in ${pHE-state.pityEvento} pull`;
      el.classList.toggle('is-active',state.pityEvento>=pSE);
    });
    $$('[id^="garantito-badge-"]').forEach(el=>el.style.display=state.garantito?'':'none');
  }

  /* ════════════════════════════════════════════════════
     PARTICELLE
  ════════════════════════════════════════════════════ */
  function spawnParticles(color, rarity) {
    const mob=window.innerWidth<768;
    const count=rarity==='theone'?mob?30:70:rarity==='segreto'?mob?25:60:
      rarity==='speciale'?mob?20:50:rarity==='leggendario'?mob?15:40:
      rarity==='epico'?mob?10:28:mob?6:15;
    const frag=document.createDocumentFragment();
    for(let i=0;i<count;i++){
      const p=document.createElement('div'),sz=3+Math.random()*5,ang=Math.random()*Math.PI*2;
      const d=80+Math.random()*200,dur=600+Math.random()*900;
      p.className='gacha-particle';
      p.style.cssText=`width:${sz}px;height:${sz}px;background:${color};box-shadow:0 0 ${sz*2}px ${color};--px:${Math.cos(ang)*d}px;--py:${Math.sin(ang)*d}px;--dur:${dur}ms;`;
      frag.appendChild(p);
      setTimeout(()=>p.remove(),dur+100);
    }
    particlesLayer.appendChild(frag);
  }

  /* ════════════════════════════════════════════════════
     AUDIO
  ════════════════════════════════════════════════════ */
  function stopAudio() {
    if (!audioEl) return;
    audioEl.pause(); audioEl.currentTime = 0; audioEl.src = '';
  }
  function playAudio(url) {
    if (!url) return;
    stopAudio();
    try {
      audioEl.src = url.startsWith('/') ? url : `/audio/${url}`;
      audioEl.currentTime = 0; audioEl.volume = 0.8;
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
    document.addEventListener('touchmove', _tl, { passive:false });
  }
  function unlockScroll() {
    document.body.style.overflow = '';
    if (_tl) { document.removeEventListener('touchmove', _tl); _tl = null; }
  }

  /* ════════════════════════════════════════════════════
     TOAST
  ════════════════════════════════════════════════════ */
  let _tt = null;
  function showToast(msg, type='info') {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.className = `gacha-toast${type==='error'?' gacha-toast--error':''}`;
    toastEl.classList.add('is-visible');
    clearTimeout(_tt);
    _tt = setTimeout(() => toastEl.classList.remove('is-visible'), 3500);
  }

  /* ════════════════════════════════════════════════════
     #2 — KEYBOARD (F = fast pull, Space = pull, S = skip)
  ════════════════════════════════════════════════════ */
  function initKeyboard() {
    document.addEventListener('keydown', e => {
      if (e.repeat) return;
      // Space = pull normale
      if (e.code==='Space' && !state.overlayOpen && !state.isPulling) {
        e.preventDefault(); state.isFastPull=false; startPull(state.activeBannerId);
      }
      // F = apertura veloce (skip animazione orb se skippabile)
      if (e.code==='KeyF' && !state.overlayOpen && !state.isPulling) {
        e.preventDefault(); startPull(state.activeBannerId, true);
      }
      // Enter = pull ancora
      if (e.code==='Enter' && state.overlayOpen && !state.isPulling) {
        e.preventDefault(); btnPullAgain?.click();
      }
      // Escape = chiudi
      if (e.code==='Escape' && state.overlayOpen && !state.isPulling) {
        e.preventDefault(); closeOverlay();
      }
      // S = skip
      if (e.code==='KeyS' && state.canSkip) { e.preventDefault(); doSkip(); }
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
     TIMER
  ════════════════════════════════════════════════════ */
  function initTimers() {
    $$('.gacha-timer-digits[data-ends]').forEach(el => {
      updateTimer(el); setInterval(() => updateTimer(el), 60_000);
    });
  }
  function updateTimer(el) {
    const diff = new Date(el.dataset.ends.replace(' ','T')+'Z').getTime()-Date.now();
    if(diff<=0){el.closest('.gacha-timer-wrap')?.remove();return;}
    const d=el.querySelector('.t-days'),h=el.querySelector('.t-hours'),m=el.querySelector('.t-mins');
    if(d)d.textContent=Math.floor(diff/86400000);
    if(h)h.textContent=String(Math.floor(diff%86400000/3600000)).padStart(2,'0');
    if(m)m.textContent=String(Math.floor(diff%3600000/60000)).padStart(2,'0');
  }

  /* ════════════════════════════════════════════════════
     SETTINGS
  ════════════════════════════════════════════════════ */
  function initSettingsBtn() {
    $('btn-settings')?.addEventListener('click', () => {
      const modal=document.getElementById('impostazioniModal');
      if(modal&&window.bootstrap) bootstrap.Modal.getOrCreateInstance(modal).show();
    });
  }

  /* ════════════════════════════════════════════════════
     CRONOLOGIA
  ════════════════════════════════════════════════════ */
  function injectHistoryModal() {
    document.body.insertAdjacentHTML('beforeend',`
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
    const modal=$('gachaHistoryModal');
    if(!modal||!window.bootstrap)return;
    $('gacha-history-banner-label').textContent=bannerName??'';
    $('gacha-history-list').innerHTML='<div style="text-align:center;color:rgba(255,255,255,.35);padding:40px"><i class="fas fa-circle-notch fa-spin"></i></div>';
    bootstrap.Modal.getOrCreateInstance(modal).show();
    try{
      const resp=await fetch(`/api/api_gacha_history?banner_id=${encodeURIComponent(bannerId)}&limit=60`,{credentials:'same-origin'});
      const data=await resp.json();
      if(!data.pulls?.length){$('gacha-history-list').innerHTML='<div style="text-align:center;color:rgba(255,255,255,.3);padding:48px">Nessuna pull su questo banner.</div>';return;}
      const RC={comune:'#9ca3af',raro:'#38bdf8',epico:'#c084fc',leggendario:'#fbbf24',speciale:'#fff',segreto:'#a855f7',theone:'#60a5fa'};
      $('gacha-history-list').innerHTML=data.pulls.map(p=>{
        const r=normalizeRarity(p.rarità),c=RC[r]??'#fff';
        const dt=new Date(p.created_at).toLocaleString('it-IT',{day:'2-digit',month:'2-digit',year:'2-digit',hour:'2-digit',minute:'2-digit'});
        const b50=p.esito_50_50===1?`<span style="font-size:.68rem;color:#fbbf24;background:rgba(251,191,36,.12);padding:2px 7px;border-radius:6px">★ Rate-Up</span>`:p.esito_50_50===0?`<span style="font-size:.68rem;color:#f87171;background:rgba(239,68,68,.1);padding:2px 7px;border-radius:6px">→ Garantito</span>`:'';
        const newBadge=p.is_new?`<span style="font-size:.68rem;color:#4ade80;background:rgba(74,222,128,.12);padding:2px 7px;border-radius:6px">NEW</span>`:'';
        return`<div style="display:flex;align-items:center;gap:12px;padding:9px 12px;background:rgba(255,255,255,.03);border-radius:8px;border-left:3px solid ${c};margin-bottom:6px">
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
      const seg=data.pulls.filter(p=>['segreto','theone'].includes(normalizeRarity(p.rarità))).length;
      $('gacha-history-stats').textContent=`${data.total??data.pulls.length} pull totali • ${seg} segreti`;
    }catch{$('gacha-history-list').innerHTML='<div style="text-align:center;color:#f87171;padding:32px">Errore caricamento cronologia.</div>';}
  }

  window.GachaHistory = { open: openHistoryModal };

  /* ════════════════════════════════════════════════════
     LEADERBOARD
  ════════════════════════════════════════════════════ */
  function initLeaderboard() {
    $$('.gacha-leaderboard-btn').forEach(btn => {
      btn.addEventListener('click', () => { if(typeof toggleLeaderboard==='function') toggleLeaderboard(); });
    });
  }

  /* ════════════════════════════════════════════════════
     ACHIEVEMENTS
  ════════════════════════════════════════════════════ */
  async function triggerAchievements(data) {
    if(typeof unlockAchievement!=='function')return;
    try{
      unlockAchievement(5);
      const r=await fetch('/api/get_casse_aperte');
      const d=await r.json();
      const c=d.total??0;
      if(c>=100)unlockAchievement(8);
      if(c>=500)unlockAchievement(16);
    }catch(e){}
  }

  /* ════════════════════════════════════════════════════
     UTILITY
  ════════════════════════════════════════════════════ */
  function normalizeRarity(r){
    return String(r??'comune').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[\s_-]+/g,'').trim();
  }
  function escapeHtml(s){
    return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }
  function delay(ms){return new Promise(r=>setTimeout(r,ms));}

  /* ════════════════════════════════════════════════════
     BOOT
  ════════════════════════════════════════════════════ */
  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded',init);
  }else{
    init();
  }

})();