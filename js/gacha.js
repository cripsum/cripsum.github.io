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
     i18n
  ══════════════════════════════════════════════════════ */
  const lang = location.pathname.split('/').find(s => s === 'it' || s === 'en') || 'it';

  const t = {
    it: {
      locale:             'it-IT',
      // API errors
      err_http:           (s) => `Errore ${s}`,
      err_unknown:        'Errore sconosciuto',
      err_pull:           'Errore durante la pull. Riprova!',
      err_multi:          'Errore multi pull. Riprova!',
      err_retry:          'Errore. Riprova!',
      err_history:        'Errore caricamento cronologia.',
      // Multi intro labels/subs
      intro_theone:       { label:'THE ONE',    sub:'Un miracolo raro tra i rari' },
      intro_segreto:      { label:'SEGRETO',    sub:'Qualcosa di molto raro...' },
      intro_speciale:     { label:'SPECIALE',   sub:'Una rarità fuori dal comune' },
      intro_leggendario:  { label:'LEGGENDARIO',sub:'Grande fortuna!' },
      intro_epico:        { label:'EPICO',      sub:'Una buona multi!' },
      intro_raro:         { label:'RARO',       sub:'Qualcosa di interessante' },
      intro_comune:       { label:'COMUNE',     sub:'Niente di speciale...' },
      // Multi navigation buttons
      btn_summary:        '<i class="fa-solid fa-flag-checkered"></i> Continua',
      btn_next:           (cur, tot) => `<i class="fa-solid fa-forward"></i> Prossima (${cur}/${tot})`,
      btn_next_label:     'Prossima',
      btn_skip_cov:       '<i class="fa-solid fa-forward-fast"></i> Salta',
      btn_next_inject:    '<i class="fa-solid fa-forward"></i> Prossima',
      btn_skip_inject:    '<i class="fa-solid fa-forward-fast"></i> Salta [S]',
      // Multi summary
      summary_title:      'Riepilogo Multi',
      summary_new_one:    (n) => `${n} nuovo`,
      summary_new_many:   (n) => `${n} nuovi`,
      summary_rare:       '✦ Raro trovato',
      btn_multi_again:    '<i class="fa-solid fa-rotate-right"></i> Apri x10',
      btn_close:          '<i class="fa-solid fa-xmark"></i> Chiudi',
      btn_inventory:      '<i class="fa-solid fa-layer-group"></i> Inventario',
      // History modal
      history_kicker:     'Gacha',
      history_title:      'Cronologia Pull',
      history_close:      'Chiudi',
      history_empty:      'Nessuna pull su questo banner.',
      history_pity:       (n) => `pity ${n}`,
      history_stats:      (tot, seg) => `${tot} pull totali • ${seg} segreti`,
      history_guaranteed: 'Garantito attivato',
      // Pity bar
      pity_hard:          '★ Garantito: prossima pull è Speciale o Segreto!',
      pity_soft:          '✦ Soft pity — % Speciale e Segreto aumentata',
      pity_count:         (n) => `Garantito Speciale o Segreto in ${n} pull`,
      pity_evt_soft:      '✦ Soft pity attivo — probabilità in aumento',
      pity_evt_count:     (n) => `Garantito segreto in ${n} pull`,
    },
    en: {
      locale:             'en-GB',
      err_http:           (s) => `Error ${s}`,
      err_unknown:        'Unknown error',
      err_pull:           'Pull error. Try again!',
      err_multi:          'Multi pull error. Try again!',
      err_retry:          'Error. Try again!',
      err_history:        'Could not load history.',
      intro_theone:       { label:'THE ONE',     sub:'A miracle among miracles' },
      intro_segreto:      { label:'SECRET',      sub:'Something very rare...' },
      intro_speciale:     { label:'SPECIAL',     sub:'An uncommon rarity' },
      intro_leggendario:  { label:'LEGENDARY',   sub:'Great luck!' },
      intro_epico:        { label:'EPIC',        sub:'A good multi!' },
      intro_raro:         { label:'RARE',        sub:'Something interesting' },
      intro_comune:       { label:'COMMON',      sub:'Nothing special...' },
      btn_summary:        '<i class="fa-solid fa-flag-checkered"></i> Summary',
      btn_next:           (cur, tot) => `<i class="fa-solid fa-forward"></i> Next (${cur}/${tot})`,
      btn_next_label:     'Next',
      btn_skip_cov:       '<i class="fa-solid fa-forward-fast"></i> Skip',
      btn_next_inject:    '<i class="fa-solid fa-forward"></i> Next',
      btn_skip_inject:    '<i class="fa-solid fa-forward-fast"></i> Skip [S]',
      summary_title:      'Multi Summary',
      summary_new_one:    (n) => `${n} new`,
      summary_new_many:   (n) => `${n} new`,
      summary_rare:       '✦ Rare found',
      btn_multi_again:    '<i class="fa-solid fa-rotate-right"></i> Multi again',
      btn_close:          '<i class="fa-solid fa-xmark"></i> Close',
      btn_inventory:      '<i class="fa-solid fa-layer-group"></i> Inventory',
      history_kicker:     'Gacha',
      history_title:      'Pull History',
      history_close:      'Close',
      history_empty:      'No pulls on this banner yet.',
      history_pity:       (n) => `pity ${n}`,
      history_stats:      (tot, seg) => `${tot} total pulls • ${seg} secrets`,
      history_guaranteed: 'Guaranteed activated',
      pity_hard:          '★ Guaranteed: next pull is Special or Secret!',
      pity_soft:          '✦ Soft pity — Special/Secret % increased',
      pity_count:         (n) => `Guaranteed Special/Secret in ${n} pulls`,
      pity_evt_soft:      '✦ Soft pity active — probability increasing',
      pity_evt_count:     (n) => `Guaranteed secret in ${n} pulls`,
    },
  }[lang];

  /**
   * Traduce una rarità normalizzata nel nome localizzato per la UI,
   * riutilizzando le label già presenti in t.intro_*.
   * Es. 'leggendario' → 'LEGENDARY' (EN) / 'LEGGENDARIO' (IT)
   */
  function rarityLabel(r) {
    return (t['intro_' + r]?.label ?? r).toUpperCase();
  }

  /* ══════════════════════════════════════════════════════
     CONFIG
  ══════════════════════════════════════════════════════ */
  const API_PULL = '/api/api_gacha_pull';
  const VIDEO_CARD_DELAY_MS = 15000; // ms dopo cui card appare sopra video (#1)
  const LOBOTOMY_CHARACTER_ID = 155;

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
    godoshards:       window.GACHA_INIT?.godoshards   ?? 0,
    pityStandard:     window.GACHA_INIT?.pityStandard ?? 0,
    pityEvento:       window.GACHA_INIT?.pityEvento   ?? 0,
    garantito:        window.GACHA_INIT?.garantito     ?? false,
    adminForceRarity: null,
    adminForceCharacterId: null,
    canSkip:          false,
    skipTimeout:      null,
    // Multi pull
    isMulti:          false,
    multiResults:     [],
    multiSkipping:    false,
    multiAbort:       false,  // FIX 1: salta al resoconto
    _covActions:      null,   // riferimento azioni card-over-video multi
    videoPlaying:     false,  // true mentre un video segreto/theone è in riproduzione
    _videoAbortFn:    null,   // funzione per interrompere il video in corso
  };

  const mediaPreloadCache = new Map();

  const volumeKey = 'cripsum.lootbox.volume';
  const muteKey = 'cripsum.lootbox.muted';

  let globalVolume = localStorage.getItem(volumeKey) !== null
    ? Number(localStorage.getItem(volumeKey))
    : 0.8;
  globalVolume = isNaN(globalVolume) ? 0.8 : Math.min(Math.max(globalVolume, 0), 1);

  let isMuted = localStorage.getItem(muteKey) === 'true';

  /* ══════════════════════════════════════════════════════
     DOM REFS
  ══════════════════════════════════════════════════════ */
  const $  = id  => document.getElementById(id);
  const $$ = sel => document.querySelectorAll(sel);

  const overlay        = $('gacha-overlay');
  const glowBurst      = $('gacha-glow-burst');
  const overlayStars   = $('overlay-stars');
  const particlesLayer = $('gacha-particles');
  const gachaFlash     = $('gacha-flash');

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
    initFloatingAudioBtn();
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

      // Su mobile la sidebar è fixed in basso: sottraiamo la sua altezza corretta
      const hasPremium = document.body.classList.contains('has-premium');
      const mobileSidebarH = window.innerWidth < 768 ? (hasPremium ? 230 : 148) : 0;
      const bannerMinH = mobileSidebarH
        ? `calc(100dvh - ${h}px - ${mobileSidebarH}px)`
        : vhMinusNav;

      $$('.gacha-banner-view').forEach(v => {
        v.style.minHeight = bannerMinH;
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
        const bannerId = btn.dataset.bannerId;
        const qty = btn.dataset.pullQty === '10' ? 10 : 1;
        checkGachaBalanceAndConfirm(bannerId, qty, () => {
          if (qty === 10) {
            startMultiPull(bannerId);
          } else {
            state.isFastPull = false;
            startPull(bannerId);
          }
        });
      });
    });
  }

  /* ════════════════════════════════════════════════════
     SINGLE PULL
  ════════════════════════════════════════════════════ */
  async function startPull(bannerId, fastMode = false) {
    if (state.isPulling) return;

    const bannerView = document.getElementById('banner-view-' + bannerId) || document.getElementById('banner-view-standard');
    const costPunti = bannerView ? parseInt(bannerView.dataset.costo || '100') : 100;
    const costShards = Math.ceil(costPunti / 100);

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
    if (state.adminForceCharacterId) payload.force_character_id = state.adminForceCharacterId;

    try {
      const resp = await fetch(API_PULL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        credentials: 'same-origin',
      });
      if (!resp.ok) {
        const err = await resp.json().catch(() => ({}));
        throw new Error(err.message ?? t.err_http(resp.status));
      }
      const data = await resp.json();
      if (data.status !== 'success') throw new Error(data.message ?? t.err_unknown);

      state.soldi        = data.soldi_rimasti ?? state.soldi;
      state.godoshards   = data.shards_rimaste ?? state.godoshards;
      state.pityStandard = data.pity_standard ?? state.pityStandard;
      state.pityEvento   = data.pity_evento   ?? state.pityEvento;
      state.garantito    = data.garantito     ?? state.garantito;
      window._lastPullData = data;

      // Notice banner removed

      const rarity = normalizeRarity(data.personaggio.rarità);
      state.canSkip = !data.is_new && !RARITY_NO_SKIP.has(rarity);

      preloadPullMedia([data]);
      if (!RARITY_VIDEO.has(rarity)) playPullAudio(data);

      // #2 — Fast pull: se la rarità è skippabile salta animazione orb
      const orbWait = (state.isFastPull && state.canSkip) ? 0 : 900;
      if (orbWait > 0) await delay(orbWait);

      await revealPull(data);
      updateBannerUI();
      triggerAchievements(data);

    } catch(err) {
      console.error('[Gacha] Pull error:', err);
      closeOverlay();
      showToast(err.message ?? t.err_pull, 'error');
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

    const bannerView = document.getElementById('banner-view-' + bannerId) || document.getElementById('banner-view-standard');
    const costPunti = bannerView ? parseInt(bannerView.dataset.costo || '100') : 100;
    const costTotalePunti = costPunti * 10;
    const costTotaleShards = Math.ceil(costTotalePunti / 100);

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
    if (state.adminForceCharacterId) payload.force_character_id = state.adminForceCharacterId;

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
        throw new Error(err.message ?? t.err_http(resp.status));
      }
      const data = await resp.json();
      if (data.status !== 'success') throw new Error(data.message ?? t.err_unknown);

      // Aggiorna stato globale con i dati finali del server
      state.soldi        = data.soldi_rimasti ?? state.soldi;
      state.godoshards   = data.shards_rimaste ?? state.godoshards;
      state.pityStandard = data.pity_standard ?? state.pityStandard;
      state.pityEvento   = data.pity_evento   ?? state.pityEvento;
      state.garantito    = data.garantito     ?? state.garantito;
      state.multiResults = data.pulls;

      // Notice banner removed
      preloadPullMedia(data.pulls);

      // FIX 4: animazione intro prima delle pull
      await showMultiIntro(data.pulls);
      // Mostra le pull una ad una (dati già pronti, niente fetch)
      await showMultiPulls(data.pulls, bannerId);
      updateBannerUI();
      triggerAchievements(data);

    } catch(err) {
      console.error('[Gacha] Multi pull error:', err);
      state.isMulti   = false;
      state.isPulling = false;
      closeOverlay();
      showToast(err.message ?? t.err_multi, 'error');
      return;
    }

    state.isMulti   = false;
    state.isPulling = false;
  }

  // FIX 4: Animazione intro multi — rivela il livello di rarità trovato
  async function showMultiIntro(pulls) {
    // Calcola top rarity presente nella multi
    const rankOrder = ['theone','segreto','speciale','leggendario','epico','raro','comune'];
    let topRarity = 'comune';
    for (const r of pulls) {
      const ra = normalizeRarity(r.personaggio.rarità);
      if (rankOrder.indexOf(ra) < rankOrder.indexOf(topRarity)) topRarity = ra;
    }

    // Configura visual in base alla rarità massima
    const cfg = {
      theone:     { ...t.intro_theone,     color:'#60a5fa', stars:5, duration:3200 },
      segreto:    { ...t.intro_segreto,    color:'#a855f7', stars:5, duration:2800 },
      speciale:   { ...t.intro_speciale,   color:'#ffffff', stars:5, duration:2600 },
      leggendario:{ ...t.intro_leggendario,color:'#fbbf24', stars:4, duration:2400 },
      epico:      { ...t.intro_epico,      color:'#c084fc', stars:3, duration:2200 },
      raro:       { ...t.intro_raro,       color:'#38bdf8', stars:2, duration:2000 },
      comune:     { ...t.intro_comune,     color:'#9ca3af', stars:1, duration:1800 },
    };
    const { label, color, sub, stars, duration } = cfg[topRarity] ?? cfg.comune;

    const intro = document.createElement('div');
    intro.id = 'multi-intro';
    intro.style.cssText = [
      'position:absolute;inset:0;z-index:40;',
      'display:flex;align-items:center;justify-content:center;',
      'flex-direction:column;gap:16px;',
      `background:radial-gradient(ellipse at center,${color}18 0%,#050510 65%);`,
      'opacity:0;transition:opacity .5s ease;pointer-events:none;',
    ].join('');

    // Stelle animate
    const starsHtml = Array.from({length:5}, (_,i) => {
      const active = i < stars;
      const delay  = i * 140;
      return `<span class="mi-star ${active?'mi-star--on':''}" style="animation-delay:${delay}ms">★</span>`;
    }).join('');

    // Barra glow
    // FIX 2: speciale → stelle arcobaleno
    const starsColor = topRarity === 'speciale'
      ? 'url(#rainbow-grad)'
      : color;

    intro.innerHTML = `
      <svg width="0" height="0" style="position:absolute">
        <defs>
          <linearGradient id="rainbow-grad" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%"   stop-color="#ff004c"/>
            <stop offset="17%"  stop-color="#ff7a00"/>
            <stop offset="33%"  stop-color="#fff300"/>
            <stop offset="50%"  stop-color="#35ff00"/>
            <stop offset="67%"  stop-color="#00ffd5"/>
            <stop offset="83%"  stop-color="#0077ff"/>
            <stop offset="100%" stop-color="#7a00ff"/>
          </linearGradient>
        </defs>
      </svg>
      <div class="mi-glow" style="background:${topRarity==='speciale'?'conic-gradient(#ff004c,#ff7a00,#fff300,#35ff00,#00ffd5,#0077ff,#7a00ff,#ff004c)':color};box-shadow:0 0 60px ${color},0 0 120px ${color}44"></div>
      <div class="mi-stars" data-rarity="${topRarity}">${starsHtml}</div>
    `;

    overlay.appendChild(intro);
    showPhase('opening');

    // Fade in
    await delay(20);
    intro.style.opacity = '1';
    await delay(duration);

    // Fade out
    intro.style.transition = 'opacity .6s ease';
    intro.style.opacity = '0';
    await delay(620);
    intro.remove();
  }

  // Mostra le 10 pull una ad una dai dati già ricevuti
  async function showMultiPulls(pulls, bannerId) {
    const total = pulls.length;

    for (let i = 0; i < total; i++) {
      // FIX 1: se richiesta skip-to-summary esci subito dal loop
      if (state.multiAbort) break;

      const pullData  = pulls[i];
      const rarity    = normalizeRarity(pullData.personaggio.rarità);
      const isSpecial = RARITY_NO_SKIP.has(rarity);
      const isNew     = pullData.is_new;
      const isLobotomy = getPulledCharacterId(pullData) === LOBOTOMY_CHARACTER_ID;
      const mustShow  = isNew || isSpecial || isLobotomy;

      // Aggiorna counter
      const counter = $('multi-counter');
      if (counter) { counter.textContent = `${i+1} / ${total}`; counter.style.display = 'block'; }

      if (state.multiSkipping && !mustShow) {
        // FIX 2: pull normale → skip silenzioso senza animazione
        await delay(20);
        continue;
      }

      state.multiSkipping = false;

      const hasVideo = pullData.personaggio.video_url && RARITY_VIDEO.has(rarity);

      // FIX 2: per pull normali (non mustShow) mostra card SUBITO senza orb
      if (!mustShow) {
        if (!hasVideo) playPullAudio(pullData);
        setRarityOnOverlay(rarity);
        await showCardInstant(pullData);
      } else {
        // Personaggio nuovo o rarità speciale: animazione completa
        if (!hasVideo) playPullAudio(pullData);
        showPhase('opening');
        setRarityOnOverlay('comune');
        await delay(700);

        if (hasVideo) {
          // FIX 6: video in multi → approccio semplice senza clone DOM
          await revealPullMultiVideo(pullData);
        } else {
          if (window.GachaEffects && RARITY_EFFECTS.has(rarity)) {
            await GachaEffects.play(rarity);
          }
          setRarityOnOverlay(rarity);
          await showCard(pullData);
        }
      }

      const isLast = (i === total - 1);
      await waitForMultiNext(i, total, isLast);
      stopAudio();
    }

    const counter = $('multi-counter');
    if (counter) counter.style.display = 'none';
    // FIX 2: ripristina bottoni dopo multi
    if (btnClose)     btnClose.style.display     = '';
    if (btnInventory) btnInventory.style.display  = '';
    document.getElementById('card-over-video-multi')?.remove();
    state._covActions = null;
    state.multiAbort = false;
    showMultiSummary(pulls);
  }

  // FIX 2: card istantanea — niente orb, niente bounce, comparsa veloce
  async function showCardInstant(data) {
    const p      = data.personaggio ?? data;
    const rarity = normalizeRarity(p.rarità);
    const color  = RARITY_COLORS[rarity] ?? '#fff';

    cardImg.src                  = p.img_url ? `/img/${p.img_url}` : '/img/cassa.png';
    cardImg.alt                  = characterName(p, '');
    cardName.textContent         = characterName(p);
    cardRarityBar.className      = `gacha-card-rarity-bar rarity-${rarity}`;
    cardRarityLabel.textContent  = rarityLabel(normalizeRarity(p.rarità ?? ''));
    cardBgGlow.style.background  = `radial-gradient(circle,${color}44 0%,transparent 70%)`;

    cardNewBadge.style.display = data.is_new ? '' : 'none';
    card50Win.style.display    = 'none';
    card50Loss.style.display   = 'none';
    if      (data.vinto_50_50 === 1) card50Win.style.display  = '';
    else if (data.vinto_50_50 === 0) card50Loss.style.display = '';

    if (btnInventory) btnInventory.style.display = 'none'; // FIX 2: nascosto durante multi
    if (btnClose)     btnClose.style.display     = 'none'; // FIX 2
    if (btnPullAgain) btnPullAgain.style.display = 'none';
    setMultiActionButtonsPending();

    spawnParticles(color, rarity);
    showPhase('card');

    // Animazione veloce: 150ms invece di 620ms
    gachaCard.classList.remove('is-revealed','is-idle');
    await delay(20);
    gachaCard.classList.add('is-revealed');
    await delay(150);
    gachaCard.classList.add('is-idle');
  }

  // VIDEO MULTI: video come sfondo, card sovrapposta dopo 12s (come pull singola)
  // FIX 1: risolve appena la card diventa visibile (non aspetta fine video)
  // Il video continua in background; il cleanup avviene in waitForMultiNext
  async function revealPullMultiVideo(data) {
    const p = data.personaggio;
    setRarityOnOverlay(normalizeRarity(p.rarità));

    // Fade a nero
    const velo = document.createElement('div');
    velo.style.cssText = 'position:absolute;inset:0;background:#000;z-index:30;opacity:0;transition:opacity .6s ease;pointer-events:none;';
    overlay.appendChild(velo);
    await delay(20);
    velo.style.opacity = '1';
    await delay(650);

    showPhase('video');
    velo.style.opacity = '0';
    setTimeout(() => velo.remove(), 600);

    videoEl.src    = p.video_url.startsWith('/') ? p.video_url : `/vid/${p.video_url}`;
    videoEl.muted  = isMuted;
    videoEl.volume = globalVolume;
    videoEl.load();
    videoUnmuteBtn.style.display = 'none';

    const MULTI_VIDEO_CARD_DELAY = 15000;

    return new Promise(resolve => {
      let resolved  = false;
      let cardShown = false;

      // Risolve la promise e segnala al loop che può proseguire
      const finish = () => {
        if (!resolved) { resolved = true; resolve(); }
      };

      // Card appare dopo 12s — video continua in background
      const showCardAtTimer = async () => {
        if (cardShown) return;
        cardShown = true;
        showCardOverVideoSimple(data).then(() => {}); // fire-and-forget idle animation
        await delay(200); // attendi che _covActions sia settato e card sia visibile
        finish();         // ← risolve SUBITO, senza aspettare fine video
      };

      const cardTimer = setTimeout(showCardAtTimer, MULTI_VIDEO_CARD_DELAY);

      const onVideoDone = async () => {
        clearTimeout(cardTimer);
        clearTimeout(safeguard);
        videoEl.removeEventListener('ended', onVideoDone);
        videoEl.removeEventListener('error', onVideoDone);
        if (!cardShown) {
          // Video finito prima dei 12s → card normale (niente overlay video)
          cardShown = true;
          videoEl.pause(); videoEl.src = '';
          await showCard(data);
        }
        // Se cardShown è già true il video era ancora in corso quando la card
        // è apparsa: il cleanup vero lo fa waitForMultiNext quando l'utente preme.
        finish();
      };

      const safeguard = setTimeout(onVideoDone, 90000);
      videoEl.addEventListener('ended', onVideoDone, { once: true });
      videoEl.addEventListener('error', onVideoDone, { once: true });
      const pp = videoEl.play();
      if (pp) pp.then(() => triggerLobotomyFlash(data)).catch(() => {
        videoEl.muted = true;
        videoEl.play().then(() => triggerLobotomyFlash(data)).catch(() => onVideoDone());
      });
    });
  }

  // Card sovrapposta al video nella multi — SENZA clone DOM, diretta
  async function showCardOverVideoSimple(data) {
    const p      = data.personaggio ?? data;
    const rarity = normalizeRarity(p.rarità);
    const color  = RARITY_COLORS[rarity] ?? '#fff';

    // Crea layer trasparente sopra il video
    const cardOverlay = document.createElement('div');
    cardOverlay.id = 'card-over-video-multi';
    cardOverlay.style.cssText = [
      'position:absolute;inset:0;z-index:15;',
      'display:flex;flex-direction:column;align-items:center;justify-content:center;',
      'gap:20px;padding:20px 20px calc(20px + var(--safe-bot,0px));',
      'opacity:0;transition:opacity .7s ease;background:transparent;',
    ].join('');
    phaseVideo.appendChild(cardOverlay);

    // Costruisce card compatta inline (non clona il DOM)
    const imgSrc = p.img_url ? `/img/${p.img_url}` : '/img/cassa.png';
    cardOverlay.innerHTML = `
      <div class="gacha-card" id="cov-card">
        <div class="gacha-card-bg-glow" style="background:radial-gradient(circle,${color}55 0%,transparent 70%)"></div>
        <div class="gacha-card-frame" style="border-color:${color}88;box-shadow:0 0 40px ${color}44">
          <div class="gacha-card-img-wrap">
            <img id="card-img" class="card-img-godo" src="${imgSrc}" alt="${escapeHtml(characterName(p, ''))}" draggable="false"
                 onerror="this.src='/img/cassa.png'"
                 style="width:100%;height:100%;object-fit:cover;object-position:top">
          </div>
          <div class="gacha-card-img-shine"></div>
          ${data.is_new ? '<span class="gacha-card-new-badge">NEW!</span>' : ''}
          ${data.vinto_50_50===1 ? '<span class="gacha-card-50-badge gacha-card-50-badge--win"><i class="fa-solid fa-trophy"></i> Rate-Up!</span>' : ''}
          ${data.vinto_50_50===0 ? `<span class="gacha-card-50-badge gacha-card-50-badge--loss">${t.history_guaranteed}</span>` : ''}
        </div>
        <div class="gacha-card-details">
          <div class="gacha-card-rarity-bar rarity-${rarity}"></div>
          <p class="gacha-card-rarity-label" style="color:${color}">${escapeHtml(rarityLabel(normalizeRarity(p.rarità)))}</p>
          <h2 class="gacha-card-name">${escapeHtml(characterName(p))}</h2>
        </div>
      </div>
      <div class="cov-multi-actions" id="cov-actions">
        <button class="gacha-btn gacha-btn--primary" style="visibility:hidden;pointer-events:none" tabindex="-1" aria-hidden="true">${t.btn_next_label}</button>
        <button class="gacha-btn gacha-btn--ghost" style="visibility:hidden;pointer-events:none" tabindex="-1" aria-hidden="true">${t.btn_skip_inject}</button>
      </div>
    `;

    spawnParticles(color, rarity);

    // _covActions settato SUBITO — waitForMultiNext può iniettare i bottoni
    // non appena la card è visibile (non dopo l'idle di 620ms)
    state._covActions = cardOverlay.querySelector('#cov-actions');

    // Fade in card
    await delay(30);
    cardOverlay.style.opacity = '1';
    const covCard = cardOverlay.querySelector('#cov-card');
    covCard?.classList.remove('is-revealed','is-idle');
    await delay(60);
    covCard?.classList.add('is-revealed');
    // FIX 1: i bottoni appaiono subito con la card, non aspettiamo l'idle
    // (l'idle continua in background ma non blocca la Promise)
    await delay(80);
    covCard?.classList.add('is-idle');
  }

  function waitForMultiNext(idx, total, isLast) {
    return new Promise(resolve => {
      const btnNext  = $('btn-multi-next');
      const btnSkip  = $('btn-multi-skip');

      showMultiActionButtons(idx, total, isLast);
      if (btnPullAgain) btnPullAgain.style.display = 'none';
      // FIX 2: nascondi chiudi e inventario durante multi
      if (btnClose)     btnClose.style.display     = 'none';
      if (btnInventory) btnInventory.style.display  = 'none';

      // Inietta bottoni nel layer card-over-video se presente
      const covActs = state._covActions;
      if (covActs) {
        covActs.innerHTML = '';
        const nb = document.createElement('button');
        nb.className = 'gacha-btn gacha-btn--primary';
        nb.innerHTML = btnNext?.innerHTML ?? t.btn_next_label;
        nb.addEventListener('click', () => btnNext?.click(), { once: true });
        covActs.appendChild(nb);
        if (!isLast) {
          const sb = document.createElement('button');
          sb.className = 'gacha-btn gacha-btn--ghost';
          sb.innerHTML = t.btn_skip_cov;
          sb.addEventListener('click', () => btnSkip?.click(), { once: true });
          covActs.appendChild(sb);
        }
        state._covActions = null;
      }

      let resolved = false;
      const done = (skip, abort = false) => {
        if (resolved) return;
        resolved = true;
        state.multiSkipping = skip;
        if (abort) state.multiAbort = true;
        // Ferma il video se ancora in corso (es. video multi con card sovrapposta)
        if (videoEl && !videoEl.paused) { videoEl.pause(); videoEl.src = ''; }
        // Rimuovi card-over-video-multi se presente
        document.getElementById('card-over-video-multi')?.remove();
        state._covActions = null;
        cleanup();
        resolve();
      };

      const onNext  = () => done(false);
      const onSkip  = () => done(true);
      // FIX 1: chiudi durante multi → salta al resoconto
      const onClose = () => done(true, true);

      function cleanup() {
        btnNext?.removeEventListener('click', onNext);
        btnSkip?.removeEventListener('click', onSkip);
        btnClose?.removeEventListener('click', onClose);
        document.removeEventListener('keydown', onKey);
      }

      const onKey = e => {
        if (e.code === 'Enter')  { e.preventDefault(); done(false); }
        if (e.code === 'KeyS' && !isLast) { e.preventDefault(); done(true); }
        if (e.code === 'Escape') { e.preventDefault(); done(true, true); }
      };

      btnNext?.addEventListener('click', onNext,  { once: true });
      btnSkip?.addEventListener('click', onSkip,  { once: true });
      btnClose?.addEventListener('click', onClose, { once: true });
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
    btnNext.innerHTML = t.btn_next_inject;
    actions.insertBefore(btnNext, actions.firstChild);

    const btnSkip = document.createElement('button');
    btnSkip.id = 'btn-multi-skip';
    btnSkip.className = 'gacha-btn gacha-btn--ghost';
    btnSkip.style.display = 'none';
    btnSkip.innerHTML = t.btn_skip_inject;
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
    const newBadge = newCount > 0
      ? `<span class="gms-badge gms-badge--count">${newCount === 1 ? t.summary_new_one(newCount) : t.summary_new_many(newCount)}</span>`
      : '';

    // Calcola top rarity per titolo dinamico
    const rarityRank = ['theone','segreto','speciale','leggendario','epico','raro','comune'];
    let topRarity = 'comune';
    results.forEach(r => {
      const ra = normalizeRarity(r.personaggio.rarità);
      if (rarityRank.indexOf(ra) < rarityRank.indexOf(topRarity)) topRarity = ra;
    });
    const topColor = RARITY_C[topRarity] ?? '#fff';

    const cards = results.map((r, idx) => {
      const p  = r.personaggio;
      const ra = normalizeRarity(p.rarità);
      const co = RARITY_C[ra] ?? '#fff';
      const isTop = ['theone','segreto','speciale'].includes(ra);
      return `
        <div class="gms-card gms-card--${ra}" style="--rc:${co};animation-delay:${idx * 55}ms">
          <div class="gms-card-frame">
            <img class="gms-card-img" src="${p.img_url ? '/img/'+p.img_url : '/img/cassa.png'}"
                 alt="${escapeHtml(characterName(p, ''))}" loading="lazy" onerror="this.src='/img/cassa.png'">
            <div class="gms-card-shine"></div>
            ${isTop ? '<div class="gms-card-glow"></div>' : ''}
            ${r.is_new ? '<span class="gms-badge gms-badge--new">NEW</span>' : ''}
            ${r.vinto_50_50 === 1 ? '<span class="gms-badge gms-badge--50">★ Rate-Up</span>' : ''}
          </div>
          <div class="gms-card-info">
            <span class="gms-card-rarity" style="color:${co}">${escapeHtml(rarityLabel(normalizeRarity(p.rarità)))}</span>
            <span class="gms-card-name">${escapeHtml(characterName(p, ''))}</span>
          </div>
        </div>`;
    }).join('');

    summary.innerHTML = `
      <div class="gms-inner">
        <div class="gms-header">
          <div class="gms-header-line" style="background:${topColor}"></div>
          <h2 class="gms-title">${t.summary_title}</h2>
          <div class="gms-header-badges">
            ${newBadge}
            ${results.filter(r=>['segreto','theone','speciale'].includes(normalizeRarity(r.personaggio.rarità))).length > 0
              ? `<span class="gms-badge gms-badge--rare">${t.summary_rare}</span>` : ''}
          </div>
        </div>
        <div class="gms-cards">${cards}</div>
        <div class="gms-footer">
          <button class="gms-btn gms-btn--primary" id="btn-multi-again">
            ${t.btn_multi_again}
          </button>
          <button class="gms-btn gms-btn--ghost" id="btn-summary-close">
            ${t.btn_close}
          </button>
          <a href="inventario" class="gms-btn gms-btn--ghost">
            ${t.btn_inventory}
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
    videoEl.muted  = isMuted;
    videoEl.volume = globalVolume;
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
      if (pp) pp.then(() => triggerLobotomyFlash(data)).catch(() => {
        videoEl.muted = true;
        videoEl.play().then(() => triggerLobotomyFlash(data)).catch(() => done());
      });
    });
  }

  // Mostra card in overlay sovrapposta al video (video come sfondo)
  async function showCardOverVideo(data) {
    const p      = data.personaggio ?? data;
    const rarity = normalizeRarity(p.rarità);
    const color  = RARITY_COLORS[rarity] ?? '#fff';

    cardImg.src = p.img_url ? `/img/${p.img_url}` : '/img/cassa.png';
    cardImg.alt = characterName(p, '');
    cardName.textContent         = characterName(p);
    cardRarityBar.className      = `gacha-card-rarity-bar rarity-${rarity}`;
    cardRarityLabel.textContent  = rarityLabel(normalizeRarity(p.rarità ?? ''));
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
      background:transparent;
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
      abortVideo();
      closeOverlay();
    });
    cloneAgain?.addEventListener('click', () => {
      abortVideo(); // interrompe il video e rimuove card-over-video
      gachaCard.classList.remove('is-revealed','is-idle');
      showPhase('opening');
      setRarityOnOverlay('comune');
      hideSkipBtn();
      state.isFastPull = false;
      if (btnPullAgain) btnPullAgain.style.display = '';
      setTimeout(() => pullAgainFromOverlay(), 80);
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
    cardImg.alt = characterName(p, '');
    cardName.textContent         = characterName(p);
    cardRarityBar.className      = `gacha-card-rarity-bar rarity-${rarity}`;
    cardRarityLabel.textContent  = rarityLabel(normalizeRarity(p.rarità ?? ''));
    cardBgGlow.style.background  = `radial-gradient(circle,${color}55 0%,transparent 70%)`;

    cardNewBadge.style.display = data.is_new ? '' : 'none';
    card50Win.style.display    = 'none';
    card50Loss.style.display   = 'none';
    if      (data.vinto_50_50 === 1) card50Win.style.display  = '';
    else if (data.vinto_50_50 === 0) card50Loss.style.display = '';

    // Multi: mostra/nascondi bottoni giusti
    const counter = $('multi-counter');
    if (state.isMulti) {
      if (btnPullAgain) btnPullAgain.style.display = 'none';
      if (btnClose) btnClose.style.display = 'none';
      if (btnInventory) btnInventory.style.display = 'none';
      setMultiActionButtonsPending();
      if (counter) counter.style.display = 'block';
    } else {
      if (btnPullAgain) btnPullAgain.style.display = '';
      if (btnClose) btnClose.style.display = '';
      if (btnInventory) btnInventory.style.display = '';
      hideMultiActionButtons();
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

  // Interrompe qualsiasi video in corso e pulisce lo stato
  function abortVideo() {
    if (state._videoAbortFn) { state._videoAbortFn(); state._videoAbortFn = null; }
    videoEl.pause();
    videoEl.src = '';
    state.videoPlaying = false;
    $('card-over-video')?.remove();
    document.getElementById('card-timer-shield')?.remove();
  }

  function setRarityOnOverlay(rarity) {
    overlay.setAttribute('data-rarity', rarity);
    const color = RARITY_COLORS[rarity] ?? '#fff';
    overlay.style.setProperty('--banner-accent',      color);
    overlay.style.setProperty('--banner-accent-glow', color + '55');
    glowBurst.classList.toggle('is-rainbow', rarity === 'speciale');
  }

  function setButtonHiddenButReserved(btn, hidden) {
    if (!btn) return;
    btn.style.display = '';
    btn.style.visibility = hidden ? 'hidden' : '';
    btn.style.pointerEvents = hidden ? 'none' : '';
    btn.setAttribute('aria-hidden', hidden ? 'true' : 'false');
    btn.tabIndex = hidden ? -1 : 0;
  }

  function setMultiActionButtonsPending() {
    const btnNext = $('btn-multi-next');
    const btnSkip = $('btn-multi-skip');
    if (btnNext && !btnNext.innerHTML.trim()) btnNext.innerHTML = t.btn_next_label;
    if (btnSkip && !btnSkip.innerHTML.trim()) btnSkip.innerHTML = t.btn_skip_inject;
    setButtonHiddenButReserved(btnNext, true);
    setButtonHiddenButReserved(btnSkip, true);
  }

  function showMultiActionButtons(idx, total, isLast) {
    const btnNext = $('btn-multi-next');
    const btnSkip = $('btn-multi-skip');
    if (btnNext) {
      btnNext.innerHTML = isLast ? t.btn_summary : t.btn_next(idx + 1, total);
    }
    if (btnSkip) {
      btnSkip.innerHTML = t.btn_skip_inject;
    }
    setButtonHiddenButReserved(btnNext, false);
    setButtonHiddenButReserved(btnSkip, isLast);
  }

  function hideMultiActionButtons() {
    ['btn-multi-next', 'btn-multi-skip'].forEach((id) => {
      const btn = $(id);
      if (!btn) return;
      btn.style.display = 'none';
      btn.style.visibility = '';
      btn.style.pointerEvents = '';
      btn.removeAttribute('aria-hidden');
      btn.removeAttribute('tabindex');
    });
  }

  function getPulledCharacterId(data) {
    const p = data?.personaggio ?? data ?? {};
    return Number(p.id ?? p.personaggio_id ?? p.character_id ?? p.id_personaggio ?? 0);
  }

  function triggerLobotomyFlash(data) {
    if (!gachaFlash || getPulledCharacterId(data) !== LOBOTOMY_CHARACTER_ID) return;
    const p = data?.personaggio ?? data;
    if (p && typeof p === 'object') {
      if (p._lobotomyFlashPlayed) return;
      p._lobotomyFlashPlayed = true;
    }

    gachaFlash.classList.remove('is-lobotomy');
    void gachaFlash.offsetWidth;
    gachaFlash.classList.add('is-lobotomy');
    setTimeout(() => gachaFlash.classList.remove('is-lobotomy'), 1800);
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
      // Questo listener vale solo quando btnPullAgain è visibile (non durante video)
      if (state.isPulling) return;
      const bannerView = document.getElementById('banner-view-' + state.activeBannerId) || document.getElementById('banner-view-standard');
      const costPuntiSingola = bannerView ? parseInt(bannerView.dataset.costo || '100') : 100;
      const costShards = Math.ceil(costPuntiSingola / 100);

      // Check balance first BEFORE changing overlay phase to opening
      if (state.godoshards < costShards) {
        const pointsCost = (costShards - state.godoshards) * 100;
        if (state.soldi < pointsCost) {
          closeOverlay();
          const redirectModal = new bootstrap.Modal(document.getElementById('gachaShopRedirectModal'));
          redirectModal.show();
          return;
        }
      }

      checkGachaBalanceAndConfirm(state.activeBannerId, 1, () => {
        stopAudio();
        abortVideo(); // cancella eventuale video in corso e pulisce il DOM
        gachaCard.classList.remove('is-revealed','is-idle');
        showPhase('opening');
        setRarityOnOverlay('comune');
        hideSkipBtn();
        state.isFastPull = false;
        setTimeout(() => pullAgainFromOverlay(), 80);
      });
    });

    btnClose?.addEventListener('click', () => {
      if (state.isPulling) return;
      closeOverlay();
    });
  }

  async function pullAgainFromOverlay() {
    if (state.isPulling) return;

    const bannerView = document.getElementById('banner-view-' + state.activeBannerId) || document.getElementById('banner-view-standard');
    const costPunti = bannerView ? parseInt(bannerView.dataset.costo || '100') : 100;
    const costShards = Math.ceil(costPunti / 100);

    state.isPulling  = true;
    state.isFastPull = false;
    stopAudio();
    // Assicura che non ci siano residui del video precedente
    abortVideo();
    $('card-over-video')?.remove();

    const payload = { banner_id: state.activeBannerId, quantity: 1 };
    if (state.adminForceRarity) payload.force_rarity = state.adminForceRarity;
    if (state.adminForceCharacterId) payload.force_character_id = state.adminForceCharacterId;

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
      state.godoshards   = data.shards_rimaste ?? state.godoshards;
      state.pityStandard = data.pity_standard ?? state.pityStandard;
      state.pityEvento   = data.pity_evento   ?? state.pityEvento;
      state.garantito    = data.garantito     ?? state.garantito;
      window._lastPullData = data;

      // Notice banner removed

      const rarity = normalizeRarity(data.personaggio.rarità);
      state.canSkip = !data.is_new && !RARITY_NO_SKIP.has(rarity);
      preloadPullMedia([data]);
      if (!RARITY_VIDEO.has(rarity)) playPullAudio(data);

      await delay(900);
      await revealPull(data);
      updateBannerUI();
      triggerAchievements(data);
    } catch(err) {
      console.error('[Gacha] Pull again error:', err);
      closeOverlay();
      showToast(err.message ?? t.err_retry, 'error');
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
    // Usato da riscattaCodice() per aggiornare i punti dopo un redeem di tipo 'punti'
    setSoldi: (nuoviSoldi) => {
      state.soldi = nuoviSoldi;
      updateBannerUI();
    },
    setShards: (nuoviShards) => {
      state.godoshards = nuoviShards;
      updateBannerUI();
    },
  };

  /* ════════════════════════════════════════════════════
     UPDATE BANNER UI
  ════════════════════════════════════════════════════ */
  function updateBannerUI() {
    $$('.user-points-val').forEach(el =>
      el.textContent = state.soldi.toLocaleString(t.locale)
    );
    $$('.user-shards-val').forEach(el =>
      el.textContent = state.godoshards.toLocaleString(t.locale)
    );
    const pHS = window.GACHA_INIT?.pityHardStd ?? 90;
    const pSS = window.GACHA_INIT?.pitySoftStd ?? 70;
    const sf=$('pity-std-fill'), sn=$('pity-std-num'), so=$('pity-std-note');
    if (sf) sf.style.width = Math.min(100, Math.round(state.pityStandard/pHS*100)) + '%';
    if (sn) sn.textContent = `${state.pityStandard} / ${pHS}`;
    if (so) {
      if (state.pityStandard >= pHS) {
        so.textContent = t.pity_hard;
        so.classList.add('is-active');
      } else if (state.pityStandard >= pSS) {
        so.textContent = t.pity_soft;
        so.classList.add('is-active');
      } else {
        so.textContent = t.pity_count(pHS - state.pityStandard);
        so.classList.remove('is-active');
      }
    }
    const pHE=window.GACHA_INIT?.pityHardEvt??80, pSE=window.GACHA_INIT?.pitySoftEvt??65;
    $$('.pity-evt-fill').forEach(el=>el.style.width=Math.min(100,Math.round(state.pityEvento/pHE*100))+'%');
    $$('.pity-evt-num').forEach(el=>el.textContent=`${state.pityEvento} / ${pHE}`);
    $$('.pity-evt-note').forEach(el=>{
      el.textContent=state.pityEvento>=pSE ? t.pity_evt_soft : t.pity_evt_count(pHE-state.pityEvento);
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
    if (!url) return Promise.resolve(false);
    stopAudio();
    try {
      audioEl.src = url.startsWith('/') ? url : `/audio/${url}`;
      audioEl.currentTime = 0;
      audioEl.volume = globalVolume;
      audioEl.muted = isMuted;
      const playPromise = audioEl.play();
      return playPromise
        ? playPromise.then(() => true).catch(() => false)
        : Promise.resolve(true);
    } catch(e) {
      return Promise.resolve(false);
    }
  }
  function playPullAudio(data) {
    const p = data?.personaggio ?? data ?? {};
    playAudio(p.audio_url).then((started) => {
      if (started) triggerLobotomyFlash(data);
    });
  }
  function mediaSrc(path, base) {
    if (!path) return '';
    return String(path).startsWith('/') ? String(path) : `${base}${path}`;
  }
  function rememberPreload(src, loader) {
    if (!src || mediaPreloadCache.has(src)) return;
    mediaPreloadCache.set(src, loader);
    if (mediaPreloadCache.size > 40) {
      const oldest = mediaPreloadCache.keys().next().value;
      mediaPreloadCache.delete(oldest);
    }
  }
  function preloadSecretVideo(p) {
    const rarity = normalizeRarity(p?.rarità);
    const videoSrc = mediaSrc(p?.video_url, '/vid/');
    if (!videoSrc || !RARITY_VIDEO.has(rarity) || mediaPreloadCache.has(videoSrc)) return;

    const link = document.createElement('link');
    link.rel = 'preload';
    link.as = 'video';
    link.href = videoSrc;
    link.fetchPriority = 'high';
    document.head.appendChild(link);

    const video = document.createElement('video');
    video.preload = 'auto';
    video.muted = true;
    video.playsInline = true;
    video.src = videoSrc;
    video.load();
    rememberPreload(videoSrc, { link, video });
  }
  function preloadPullMedia(results) {
    const pulls = results || [];

    pulls.forEach((result) => {
      const p = result?.personaggio ?? result ?? {};
      preloadSecretVideo(p);
    });

    pulls.forEach((result) => {
      const p = result?.personaggio ?? result ?? {};
      const imgSrc = mediaSrc(p.img_url, '/img/');
      if (imgSrc && !mediaPreloadCache.has(imgSrc)) {
        const img = new Image();
        img.src = imgSrc;
        rememberPreload(imgSrc, img);
      }

      const rarity = normalizeRarity(p.rarità);
      const audioSrc = mediaSrc(p.audio_url, '/audio/');
      if (audioSrc && !RARITY_VIDEO.has(rarity) && !mediaPreloadCache.has(audioSrc)) {
        const audio = new Audio();
        audio.preload = 'auto';
        audio.src = audioSrc;
        audio.load();
        rememberPreload(audioSrc, audio);
      }
    });
  }

  function initFloatingAudioBtn() {
    const container = document.querySelector('[data-floating-audio]');
    if (!container) return;

    const btn = container.querySelector('.profile-floating-audio-btn');
    const icon = btn.querySelector('i');
    const slider = container.querySelector('.profile-floating-audio-slider');

    const updateUI = () => {
      if (isMuted || globalVolume === 0) {
        icon.className = 'fa-solid fa-volume-xmark';
        slider.value = '0';
      } else {
        icon.className = globalVolume < 0.5 ? 'fa-solid fa-volume-low' : 'fa-solid fa-volume-high';
        slider.value = String(globalVolume);
      }
      if (audioEl) {
        audioEl.volume = globalVolume;
        audioEl.muted = isMuted;
      }
      if (videoEl) {
        videoEl.volume = globalVolume;
        videoEl.muted = isMuted;
      }
    };

    updateUI();

    const toggleMute = () => {
      isMuted = !isMuted;
      localStorage.setItem(muteKey, String(isMuted));
      updateUI();
    };

    btn.addEventListener('click', () => {
      toggleMute();
    });

    document.addEventListener('click', (e) => {
      if (!container.contains(e.target)) {
        container.classList.remove('show-slider');
      }
    });

    slider.addEventListener('input', () => {
      const val = Number(slider.value);
      globalVolume = val;
      isMuted = (val === 0);
      localStorage.setItem(volumeKey, String(val));
      localStorage.setItem(muteKey, String(isMuted));
      updateUI();
    });
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
      // if (e.code==='KeyF' && !state.overlayOpen && !state.isPulling) {
      //   e.preventDefault(); startPull(state.activeBannerId, true);
      // }
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
        if (cb.checked) {
          $$('.admin-force-character').forEach(o => { o.checked = false; });
          state.adminForceCharacterId = null;
        }
      });
    });
    $$('.admin-force-character').forEach(cb => {
      cb.addEventListener('change', () => {
        $$('.admin-force-character').forEach(o => { if(o!==cb) o.checked=false; });
        state.adminForceCharacterId = cb.checked ? Number(cb.dataset.characterId || 0) : null;
        if (cb.checked) {
          $$('.admin-force-rarity').forEach(o => { o.checked = false; });
          state.adminForceRarity = null;
        }
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
              <span class="lootbox-modal-kicker">${t.history_kicker}</span>
              <h5 class="modal-title testobianco">${t.history_title}</h5>
              <p id="gacha-history-banner-label" style="color:rgba(255,255,255,.45);font-size:.82rem;margin:0"></p>
            </div>
            <button type="button" class="lootbox-modal-close" data-bs-dismiss="modal"><i class="fa-solid fa-xmark"></i></button>
          </div>
          <div class="modal-body" style="padding:16px 20px;max-height:60vh;overflow-y:auto">
            <div id="gacha-history-list"></div>
          </div>
          <div class="modal-footer lootbox-settings-footer" style="justify-content:space-between">
            <span id="gacha-history-stats" style="font-size:.8rem;color:rgba(255,255,255,.35)"></span>
            <button type="button" class="btn btn-secondary bottone lootbox-modal-btn lootbox-modal-btn--ghost" data-bs-dismiss="modal">${t.history_close}</button>
          </div>
        </div>
      </div>
    </div>`);
  }

  async function openHistoryModal(bannerId, bannerName) {
    const modal=$('gachaHistoryModal');
    if(!modal||!window.bootstrap)return;
    $('gacha-history-banner-label').textContent=bannerName??'';
    $('gacha-history-list').innerHTML='<div style="text-align:center;color:rgba(255,255,255,.35);padding:40px"><i class="fa-solid fa-circle-notch fa-spin"></i></div>';
    bootstrap.Modal.getOrCreateInstance(modal).show();
    try{
      const resp=await fetch(`/api/api_gacha_history?banner_id=${encodeURIComponent(bannerId)}&limit=60`,{credentials:'same-origin'});
      const data=await resp.json();
      if(!data.pulls?.length){$('gacha-history-list').innerHTML=`<div style="text-align:center;color:rgba(255,255,255,.3);padding:48px">${t.history_empty}</div>`;return;}
      const RC={comune:'#9ca3af',raro:'#38bdf8',epico:'#c084fc',leggendario:'#fbbf24',speciale:'#fff',segreto:'#a855f7',theone:'#60a5fa'};
      $('gacha-history-list').innerHTML=data.pulls.map(p=>{
        const r=normalizeRarity(p.rarità),c=RC[r]??'#fff';
        const dt=new Date(p.created_at).toLocaleString(t.locale,{day:'2-digit',month:'2-digit',year:'2-digit',hour:'2-digit',minute:'2-digit'});
        const b50=p.esito_50_50===1?`<span style="font-size:.68rem;color:#fbbf24;background:rgba(251,191,36,.12);padding:2px 7px;border-radius:6px">★ Rate-Up</span>`:p.esito_50_50===0?`<span style="font-size:.68rem;color:#f87171;background:rgba(239,68,68,.1);padding:2px 7px;border-radius:6px">→ Garantito</span>`:'';
        const newBadge=p.is_new?`<span style="font-size:.68rem;color:#4ade80;background:rgba(74,222,128,.12);padding:2px 7px;border-radius:6px">NEW</span>`:'';
        return`<div style="display:flex;align-items:center;gap:12px;padding:9px 12px;background:rgba(255,255,255,.03);border-radius:8px;border-left:3px solid ${c};margin-bottom:6px">
          <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:3px">
              <span style="font-weight:700;color:#fff;font-size:.9rem">${escapeHtml(characterName(p, '?'))}</span>
              <span style="font-size:.7rem;color:${c};text-transform:uppercase;font-weight:600;letter-spacing:.07em">${escapeHtml(rarityLabel(normalizeRarity(p.rarità)))}</span>
              ${b50}${newBadge}
            </div>
            <span style="font-size:.72rem;color:rgba(255,255,255,.28)">${dt}</span>
          </div>
          <div style="text-align:right;flex-shrink:0;font-size:.72rem;color:rgba(255,255,255,.25)">${t.history_pity(p.pity_al_momento)}</div>
        </div>`;
      }).join('');
      const seg=data.pulls.filter(p=>['segreto','theone'].includes(normalizeRarity(p.rarità))).length;
      $('gacha-history-stats').textContent=t.history_stats(data.total??data.pulls.length, seg);
    }catch{$('gacha-history-list').innerHTML=`<div style="text-align:center;color:#f87171;padding:32px">${t.err_history}</div>`;}
  }

  function checkGachaBalanceAndConfirm(bannerId, quantity, onSuccess) {
    const bannerView = document.getElementById('banner-view-' + bannerId) || document.getElementById('banner-view-standard');
    const costPuntiSingola = bannerView ? parseInt(bannerView.dataset.costo || '100') : 100;
    const costPunti = costPuntiSingola * quantity;
    const costShards = Math.ceil(costPunti / 100);

    if (costPunti === 0) {
      onSuccess();
      return;
    }

    if (state.godoshards >= costShards) {
      onSuccess();
      return;
    }

    const missingShards = costShards - state.godoshards;
    const pointsCost = missingShards * 100;

    if (state.soldi >= pointsCost) {
      showConversionModal(missingShards, pointsCost, quantity, onSuccess);
    } else {
      const redirectModal = new bootstrap.Modal(document.getElementById('gachaShopRedirectModal'));
      redirectModal.show();
    }
  }

  function showConversionModal(missingShards, pointsCost, quantity, onConfirm) {
    const modalEl = document.getElementById('gachaConversionModal');
    if (!modalEl) {
      if (confirm(lang === 'it' 
          ? `Vuoi convertire ${pointsCost} Godos in ${missingShards} Godo Shards per completare la pull?` 
          : `Do you want to convert ${pointsCost} Godos into ${missingShards} Godo Shards to complete the pull?`)) {
        onConfirm();
      }
      return;
    }

    const countSpans = modalEl.querySelectorAll('.conversion-shards-count');
    const costSpans = modalEl.querySelectorAll('.conversion-godos-cost');
    const qtySpan = modalEl.querySelector('.conversion-pull-qty');

    countSpans.forEach(span => span.textContent = missingShards);
    costSpans.forEach(span => span.textContent = pointsCost.toLocaleString());
    if (qtySpan) qtySpan.textContent = quantity;

    const modal = new bootstrap.Modal(modalEl);
    
    const confirmBtn = modalEl.querySelector('.btn-confirm-conversion');
    if (confirmBtn) {
      const newConfirmBtn = confirmBtn.cloneNode(true);
      confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
      newConfirmBtn.addEventListener('click', () => {
        modal.hide();
        onConfirm();
      });
    }

    modal.show();
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
    if (typeof unlockAchievement !== 'function') return;
    try {
      unlockAchievement(5);

      const r = await fetch('/api/get_casse_aperte');
      const d = await r.json();
      const c = d.total ?? 0;
      if (c >= 100) unlockAchievement(8);
      if (c >= 500) unlockAchievement(16);

      const rarities = data.pulls
        ? data.pulls.map(p => normalizeRarity(p.personaggio.rarità)) 
        : [normalizeRarity(data.personaggio.rarità)];                  

      for (const rarity of rarities) {
        if (rarity === 'comune') {
          state.comuniDiFila++;
          if (state.comuniDiFila >= 10) unlockAchievement(9);
        } else {
          state.comuniDiFila = 0;  
        }
      }

      const hasNew = data.pulls
        ? data.pulls.some(p => p.is_new)
        : data.is_new;

      if (hasNew) {
        const inv = await fetch('/api/api_get_inventario').then(r2 => r2.json());
        if (Array.isArray(inv) && inv.length >= 100) unlockAchievement(18);
      }

    } catch(e) {}
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
  function decodeHtmlEntities(s) {
    const el = document.createElement('textarea');
    el.innerHTML = String(s ?? '');
    return el.value;
  }
  function characterName(p, fallback = '—') {
    return decodeHtmlEntities(p?.nome ?? fallback);
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
