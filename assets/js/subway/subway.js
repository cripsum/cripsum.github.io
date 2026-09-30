/* Cripsum Subway Surfers native Unity launcher */

(function () {
    'use strict';

    const CDN = 'https://cdn.jsdelivr.net/npm/';
    // Loader Unity 2019 e modulo di compatibilita' 4399 (che definisce
    // my4399UnityModule): per le build nuove si prendono da questo pacchetto.
    const RUNTIME_PKG = 'subwaylondon@1.0.0';
    const RUNTIME_LOADER = 'UnityLoader.2019.2.js';
    const RUNTIME_BOOTSTRAP = '4399.js';

    // Catalogo delle mappe: arriva dalla pagina (includes/subway/catalog.php).
    let buildsBase = '/subway-builds/';
    let maps = [];
    let buildSizes = {};
    function loadCatalog() {
        try {
            const data = JSON.parse(document.getElementById('subwayCatalog')?.textContent || '{}');
            if (typeof data.base === 'string' && data.base) buildsBase = data.base;
            if (Array.isArray(data.maps)) maps = data.maps;
            if (data.sizes && typeof data.sizes === 'object') buildSizes = data.sizes;
        } catch (_) {
            maps = [];
        }
    }

    const storageKey = 'cripsum-subway-settings-v2';
    // Unity stores the source length, while WebAudio sees the AAC-decoded
    // length including codec padding. Keep both exact fingerprints: broad
    // ranges would confuse the coin with the nearby jump sound.
    const runStartAudioDurations = [3.464580535888672, 3.482971];
    const coinAudioDurations = [0.5619954466819763, 0.580476];
    const coinDecodedFrames = new Set([24784, 25599, 25600]);
    const defaultBindings = { jump: 'KeyW', duck: 'KeyS', left: 'KeyA', right: 'KeyD', boost: 'KeyB' };
    const defaultWidgetConfig = () => ({
        bg: '#090d18',
        bgOpacity: 88,
        textColor: '#ffffff',
        accentColor: '#06b6d4',
        borderColor: '#06b6d4',
        borderOpacity: 68,
        blur: 16,
        borderRadius: 12,
        shadowColor: '#000000',
        shadowOpacity: 50,
        shadowBlur: 8,
        shadowX: 0,
        shadowY: 2
    });

    const defaultOverlayTheme = {
        timer: Object.assign(defaultWidgetConfig(), {
            bgImage: '',
            bgImageOpacity: 100,
            bgFit: 'cover',
            bgImageScale: 100,
            bgImageRotate: 0,
            bgImagePosX: 0,
            bgImagePosY: 0,
            timerFontSize: 33,
            timerTextScaleX: 100,
            timerTextScaleY: 100,
            timerTextPosX: 0,
            timerTextPosY: 0,
            timerWidth: 250,
            timerHeight: 90
        }),
        fps: defaultWidgetConfig(),
        keys: Object.assign(defaultWidgetConfig(), {
            keyBg: '#ffffff',
            keyBgOpacity: 7,
            keyHover: '#06b6d4',
            keyText: '#ffffff',
            keyBorderColor: '#06b6d4',
            keyBorderOpacity: 40
        }),
        audio: defaultWidgetConfig(),
        settings: defaultWidgetConfig(),
        showBoost: false,
        showHeaders: true
    };
    const unityCodes = { jump: 'ArrowUp', duck: 'ArrowDown', left: 'ArrowLeft', right: 'ArrowRight' };
    const legacyKeys = {
        ArrowUp: { key: 'ArrowUp', keyCode: 38 },
        ArrowDown: { key: 'ArrowDown', keyCode: 40 },
        ArrowLeft: { key: 'ArrowLeft', keyCode: 37 },
        ArrowRight: { key: 'ArrowRight', keyCode: 39 },
        Escape: { key: 'Escape', keyCode: 27 },
        Space: { key: ' ', keyCode: 32 }
    };
    const state = {
        loading: false,
        running: false,
        isPaused: false,
        failed: false,
        ended: false,
        runArmed: false,
        sawGameStartSignal: false,
        startTime: 0,
        accumulatedTime: 0,
        elapsed: 0,
        scoreSubmittedForRun: false,
        userBestTimeMs: 0,
        run: null,
        runMode: 'original',
        userId: 0,
        autoBoost: false,
        unity: null,
        activeMap: null,
        bindings: Object.assign({}, defaultBindings),
        overlayPositions: {},
        overlayTheme: JSON.parse(JSON.stringify(defaultOverlayTheme)),
        audioVolume: 0.8,
        audioMuted: false,
        challenge: true,
        blockSpace: false,
        vsync: true,
        fpsLimit: 144,
        renderScale: 100,
        perfMode: false,
        loopFrame: 0,
        fpsLastSample: 0,
        fpsFrames: 0,
        fpsShown: null,
        lastTimerPaint: 0
    };

    const dom = {};
    const isItalian = () => String(document.documentElement.lang || '').toLowerCase().startsWith('it');
    const t = (it, en) => isItalian() ? it : en;
    const packageUrl = (pkg, path) => `${CDN}${pkg}/${path}`;

    // Sotto questa durata una "run" chiusa da un nuovo segnale di inizio e'
    // solo il doppione audio/roundStart dello stesso avvio.
    const minFinalizedRunMs = 3000;

    /*
     * Log di diagnosi della sfida: eventi Poki, audio riconosciuti, inizio,
     * pausa e reset della run con la causa, esito HTTP di registrazione e
     * salvataggio. Resta nel browser (localStorage, ultime 400 righe) e si
     * legge dalla console:
     *   CripsumSubwayDiag.dump()      tabella in console
     *   CripsumSubwayDiag.download()  file .txt da mandare
     *   CripsumSubwayDiag.clear()
     * Con localStorage['cripsum-subway-debug'] = '1' (o ?subwaydebug=1) ogni
     * riga viene anche stampata in console mentre si gioca.
     */
    const diagKey = 'cripsum-subway-diag-v1';
    const diagMax = 400;
    let diagEntries = [];
    let diagSaveTimer = 0;
    let diagEcho = false;
    try {
        diagEntries = JSON.parse(localStorage.getItem(diagKey) || '[]');
        if (!Array.isArray(diagEntries)) diagEntries = [];
        diagEcho = localStorage.getItem('cripsum-subway-debug') === '1'
            || new URLSearchParams(location.search).has('subwaydebug');
    } catch (_) {
        diagEntries = [];
    }

    function persistDiag() {
        clearTimeout(diagSaveTimer);
        diagSaveTimer = 0;
        try {
            localStorage.setItem(diagKey, JSON.stringify(diagEntries));
        } catch (_) { /* storage pieno o bloccato: il log resta in memoria */ }
    }

    function diag(event, data = {}) {
        const now = new Date();
        const entry = Object.assign({
            at: `${now.toLocaleDateString('sv-SE')} ${now.toLocaleTimeString('it-IT')}.${String(now.getMilliseconds()).padStart(3, '0')}`,
            ev: event
        }, data);
        diagEntries.push(entry);
        if (diagEntries.length > diagMax) diagEntries.splice(0, diagEntries.length - diagMax);
        if (diagEcho) console.debug('[Subway diag]', event, data);
        if (!diagSaveTimer) diagSaveTimer = setTimeout(persistDiag, 1000);
    }

    function diagText() {
        return diagEntries.map(entry => {
            const { at, ev, ...rest } = entry;
            const extra = Object.entries(rest).map(([k, v]) => `${k}=${v}`).join(' ');
            return `${at}  ${ev}${extra ? '  ' + extra : ''}`;
        }).join('\n');
    }

    window.CripsumSubwayDiag = {
        dump() { console.table(diagEntries); return diagEntries.length; },
        text: diagText,
        download() {
            const blob = new Blob([`${navigator.userAgent}\n\n${diagText()}\n`], { type: 'text/plain' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `subway-diag-${Date.now()}.txt`;
            link.click();
            setTimeout(() => URL.revokeObjectURL(link.href), 1000);
        },
        clear() { diagEntries = []; persistDiag(); },
        pending() { return pendingScores.map(item => Object.assign({}, item)); }
    };

    function cacheDom() {
        [
            'subwayPortal', 'subwayLobby', 'subwayGameArea', 'subwayGameContainer',
            'subwayBootSplash', 'subwayBootStage', 'subwayBootStatus', 'subwayBootPercent',
            'subwayBootTrackValue', 'subwayBootConsole', 'cancelSubwayLoad', 'exitGameBtn',
            'subwayTimerDisplay', 'subwayStatusBadge', 'subwayStartHint', 'subwayFpsValue',
            'toggleNoCoinChallenge', 'subwaySettingsModal',
            'hudWidgetSettingsBtn', 'openSubwaySettings', 'closeSettingsModal',
            'subwayPersonalBestCard', 'subwayPersonalBestTime', 'subwayPersonalBestRank',
            'subwayPersonalBestMap', 'subwayLeaderboardTable', 'subwayLeaderboardBody',
            'subwayLeaderboardEmpty', 'subwayLeaderboardRefresh',
            'subwayBootMap', 'subwayBootMode', 'subwayBootBytes', 'subwayModeBadge',
            'subwayRunCard', 'subwaySavedBadge'
        ].forEach(id => { dom[id] = document.getElementById(id); });
    }

    function loadSettings() {
        try {
            const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
            state.bindings = Object.assign({}, defaultBindings, saved.bindings || {});
            state.overlayPositions = saved.overlayPositions && typeof saved.overlayPositions === 'object'
                ? saved.overlayPositions
                : {};
            
            if (saved.overlayTheme && typeof saved.overlayTheme === 'object') {
                const raw = saved.overlayTheme;
                const widgetKeys = ['timer', 'fps', 'keys', 'audio', 'settings'];
                widgetKeys.forEach(wKey => {
                    const fallback = defaultOverlayTheme[wKey];
                    let current = raw[wKey];
                    if (typeof current === 'string') {
                        current = {
                            bg: normalizeHexColor(current, fallback.bg),
                            bgOpacity: Math.min(100, Math.max(0, Number(raw.opacity) || fallback.bgOpacity)),
                            textColor: normalizeHexColor(raw.text, fallback.textColor),
                            borderColor: normalizeHexColor(raw.accent, fallback.borderColor),
                            borderOpacity: Math.min(100, Math.max(0, Number(raw.borderOpacity) || fallback.borderOpacity)),
                            blur: Math.min(30, Math.max(0, Number(raw.blur) || fallback.blur))
                        };
                    } else if (!current || typeof current !== 'object') {
                        current = Object.assign({}, fallback);
                    } else {
                        current = Object.assign({}, fallback, current);
                    }

                    current.bg = normalizeHexColor(current.bg, fallback.bg);
                    current.bgOpacity = Math.min(100, Math.max(0, Number.isFinite(Number(current.bgOpacity)) ? Number(current.bgOpacity) : fallback.bgOpacity));
                    current.textColor = normalizeHexColor(current.textColor, fallback.textColor);
                    current.borderColor = normalizeHexColor(current.borderColor, fallback.borderColor);
                    current.borderOpacity = Math.min(100, Math.max(0, Number.isFinite(Number(current.borderOpacity)) ? Number(current.borderOpacity) : fallback.borderOpacity));
                    current.blur = Math.min(30, Math.max(0, Number.isFinite(Number(current.blur)) ? Number(current.blur) : fallback.blur));

                    if (wKey === 'keys') {
                        current.keyBg = normalizeHexColor(current.keyBg, fallback.keyBg);
                        current.keyBgOpacity = Math.min(100, Math.max(0, Number.isFinite(Number(current.keyBgOpacity)) ? Number(current.keyBgOpacity) : fallback.keyBgOpacity));
                        current.keyHover = normalizeHexColor(current.keyHover, fallback.keyHover);
                        current.keyText = normalizeHexColor(current.keyText, fallback.keyText);
                        current.keyBorderColor = normalizeHexColor(current.keyBorderColor, fallback.keyBorderColor);
                        current.keyBorderOpacity = Math.min(100, Math.max(0, Number.isFinite(Number(current.keyBorderOpacity)) ? Number(current.keyBorderOpacity) : fallback.keyBorderOpacity));
                    }

                    state.overlayTheme[wKey] = current;
                });

                state.overlayTheme.showBoost = raw.showBoost === true;
                state.overlayTheme.showHeaders = raw.showHeaders !== false;
            } else {
                state.overlayTheme = JSON.parse(JSON.stringify(defaultOverlayTheme));
            }

            const { vol, muted } = getSavedAudioState();
            state.audioVolume = vol;
            state.audioMuted = muted;

            state.challenge = saved.challenge !== false;
            state.blockSpace = saved.blockSpace === true;
            state.autoBoost = saved.autoBoost === true;
            state.vsync = saved.vsync !== false;
            state.fpsLimit = Math.min(500, Math.max(30, Number(saved.fpsLimit) || 144));
            applyDefaultGraphicsProfile(saved);
        } catch (_) {
            state.bindings = Object.assign({}, defaultBindings);
            state.overlayPositions = {};
            state.overlayTheme = JSON.parse(JSON.stringify(defaultOverlayTheme));
            const { vol, muted } = getSavedAudioState();
            state.audioVolume = vol;
            state.audioMuted = muted;
            state.blockSpace = false;
            state.autoBoost = false;
            state.vsync = true;
            state.fpsLimit = 144;
            applyDefaultGraphicsProfile({});
        }
    }

    // Machines that report few cores or little memory are the ones that
    // struggle with a fullscreen WebGL canvas, so they start on the lighter
    // profile. Both values stay editable and are persisted from then on.
    function looksLikeLowEndDevice() {
        const cores = Number(navigator.hardwareConcurrency) || 0;
        const memory = Number(navigator.deviceMemory) || 0;
        return (cores > 0 && cores <= 4) || (memory > 0 && memory <= 4);
    }

    // 'native' keeps the screen's own pixel ratio, which is what the page did
    // before the setting existed; every other value is a percentage of one CSS
    // pixel, so 100 already halves the work of a 150% Windows scaling setup.
    function normalizeRenderScale(value, fallback) {
        if (value === 'native') return 'native';
        const numeric = Number(value);
        // Number(null) and Number('') are 0, which would clamp to the floor
        // instead of falling back to the default.
        if (!Number.isFinite(numeric) || numeric <= 0) return fallback;
        return Math.min(100, Math.max(35, Math.round(numeric)));
    }

    function applyDefaultGraphicsProfile(saved) {
        const lowEnd = looksLikeLowEndDevice();
        state.perfMode = typeof saved.perfMode === 'boolean' ? saved.perfMode : lowEnd;
        state.renderScale = normalizeRenderScale(saved.renderScale, lowEnd ? 85 : 100);
    }

    // Senza try/catch un'immagine del timer troppo grande (data URL) faceva
    // superare la quota e da li' nessuna impostazione veniva piu' salvata.
    // Se succede si salva tutto il resto senza l'immagine e lo si dice.
    function saveSettings() {
        const payload = () => JSON.stringify({
            bindings: state.bindings,
            overlayPositions: state.overlayPositions,
            overlayTheme: state.overlayTheme,
            challenge: state.challenge,
            blockSpace: state.blockSpace,
            autoBoost: state.autoBoost,
            vsync: state.vsync,
            fpsLimit: state.fpsLimit,
            renderScale: state.renderScale,
            perfMode: state.perfMode
        });
        try {
            localStorage.setItem(storageKey, payload());
            flashSavedBadge(t('Salvato', 'Saved'));
        } catch (_) {
            const image = state.overlayTheme.timer?.bgImage || '';
            if (image.startsWith('data:')) {
                state.overlayTheme.timer.bgImage = '';
                try { localStorage.setItem(storageKey, payload()); } catch (__) {}
                state.overlayTheme.timer.bgImage = image;
                flashSavedBadge(t('Immagine troppo grande: usa un URL', 'Image too large: use a URL'), true);
            } else {
                flashSavedBadge(t('Impossibile salvare', 'Could not save'), true);
            }
        }
    }

    let savedBadgeTimer = 0;
    function flashSavedBadge(text, warn = false) {
        const badge = dom.subwaySavedBadge;
        if (!badge || !dom.subwaySettingsModal || dom.subwaySettingsModal.hidden) return;
        badge.textContent = text;
        badge.classList.toggle('is-warn', warn);
        badge.classList.add('is-visible');
        clearTimeout(savedBadgeTimer);
        savedBadgeTimer = setTimeout(() => badge.classList.remove('is-visible'), warn ? 4000 : 1400);
    }

    // Le immagini caricate per il timer finiscono in localStorage come data
    // URL: ridotte a 800 px in WebP/JPEG pesano poche decine di KB.
    function downscaleImage(file) {
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => {
                const scale = Math.min(1, 800 / Math.max(img.naturalWidth, img.naturalHeight));
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(img.naturalWidth * scale));
                canvas.height = Math.max(1, Math.round(img.naturalHeight * scale));
                canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                URL.revokeObjectURL(url);
                let data = canvas.toDataURL('image/webp', 0.82);
                if (!data.startsWith('data:image/webp')) data = canvas.toDataURL('image/jpeg', 0.82);
                resolve(data);
            };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('image')); };
            img.src = url;
        });
    }

    /*
     * Selezione della mappa. Modalita' (classifica / allenamento), tipo di
     * allenamento, ricerca e regione vengono ricordati nel browser; le card
     * senza la variante di allenamento restano visibili ma disattivate.
     */
    const lobbyKey = 'cripsum-subway-lobby-v1';
    const lobby = { mode: 'original', training: 'training', region: 'all', query: '', last: null };
    const regionLabels = {
        europe: ['Europa', 'Europe'], america: ['America', 'America'], asia: ['Asia', 'Asia'],
        africa: ['Africa', 'Africa'], event: ['Evento', 'Event']
    };
    const trainingLabels = {
        training: ['Allenamento', 'Training'],
        training3rows: ['Allenamento · 3 file', 'Training · 3 rows'],
        trainingobstacles: ['Allenamento · ostacoli', 'Training · obstacles']
    };

    function loadLobbyState() {
        try {
            const saved = JSON.parse(localStorage.getItem(lobbyKey) || '{}');
            if (saved.mode === 'training') lobby.mode = 'training';
            if (trainingLabels[saved.training]) lobby.training = saved.training;
            if (saved.region === 'all' || regionLabels[saved.region]) lobby.region = saved.region;
            if (saved.last && typeof saved.last.slug === 'string') lobby.last = saved.last;
        } catch (_) { /* storage bloccato: valori predefiniti */ }
    }

    function saveLobbyState() {
        try {
            localStorage.setItem(lobbyKey, JSON.stringify({ mode: lobby.mode, training: lobby.training, region: lobby.region, last: lobby.last }));
        } catch (_) {}
    }

    function regionName(region) {
        const label = regionLabels[region];
        return label ? t(label[0], label[1]) : region;
    }

    function modeLabel(mode) {
        if (mode === 'original') return t('Classifica', 'Ranked');
        const label = trainingLabels[mode] || trainingLabels.training;
        return t(label[0], label[1]);
    }

    function selectedMode() {
        return lobby.mode === 'training' ? lobby.training : 'original';
    }

    function initials(name) {
        return name.split(/\s+/).map(part => part[0]).join('').slice(0, 2).toUpperCase();
    }

    function createCard(map) {
        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'sw-card';
        card.dataset.map = map.slug;
        card.style.setProperty('--sw-hue', String(map.hue ?? 200));

        const art = document.createElement('span');
        art.className = 'sw-card-art';
        art.setAttribute('aria-hidden', 'true');
        art.textContent = initials(map.name);

        const body = document.createElement('span');
        body.className = 'sw-card-body';
        const name = document.createElement('strong');
        name.textContent = map.name;
        const meta = document.createElement('span');
        meta.className = 'sw-card-meta';
        meta.textContent = `${regionName(map.region)} · ${map.mb} MB`;
        body.append(name, meta);

        const badges = document.createElement('span');
        badges.className = 'sw-card-badges';
        if (map.training) {
            const badge = document.createElement('span');
            badge.className = 'sw-badge sw-badge-training';
            badge.title = t('Allenamento disponibile', 'Training available');
            badge.innerHTML = '<i class="fa-solid fa-dumbbell"></i>';
            badges.append(badge);
        }
        if (map.beta) {
            const badge = document.createElement('span');
            badge.className = 'sw-badge sw-badge-beta';
            badge.textContent = 'Beta';
            badges.append(badge);
        }

        const play = document.createElement('span');
        play.className = 'sw-card-play';
        play.setAttribute('aria-hidden', 'true');
        play.innerHTML = '<i class="fa-solid fa-play"></i>';

        card.append(art, body, badges, play);
        card.addEventListener('click', () => {
            if (card.getAttribute('aria-disabled') === 'true') return;
            launchMap(map, selectedMode());
        });
        return card;
    }

    function buildMapGrid() {
        const grid = document.getElementById('subwayMapGrid');
        if (!grid) return;
        grid.replaceChildren(...maps.map(createCard));
        applyMapFilters();
    }

    function applyMapFilters() {
        const grid = document.getElementById('subwayMapGrid');
        if (!grid) return;
        const query = lobby.query.trim().toLowerCase();
        let visible = 0;
        grid.querySelectorAll('.sw-card').forEach(card => {
            const map = maps.find(m => m.slug === card.dataset.map);
            if (!map) return;
            const matches = (lobby.region === 'all' || map.region === lobby.region)
                && (!query || map.name.toLowerCase().includes(query));
            const unavailable = lobby.mode === 'training' && !map.training;
            card.hidden = !matches;
            card.setAttribute('aria-disabled', unavailable ? 'true' : 'false');
            card.setAttribute('aria-label', unavailable
                ? t(`${map.name}: allenamento non disponibile`, `${map.name}: training not available`)
                : t(`Gioca a ${map.name} (${modeLabel(selectedMode())})`, `Play ${map.name} (${modeLabel(selectedMode())})`));
            if (matches) visible += 1;
        });
        const empty = document.getElementById('subwayMapEmpty');
        if (empty) empty.hidden = visible > 0;
    }

    function syncLobbyControls() {
        document.querySelectorAll('[data-mode]').forEach(button => {
            const active = button.dataset.mode === lobby.mode;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-checked', active ? 'true' : 'false');
        });
        document.querySelectorAll('[data-training]').forEach(button => {
            const active = button.dataset.training === lobby.training;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-checked', active ? 'true' : 'false');
        });
        document.querySelectorAll('[data-region]').forEach(button => {
            const active = button.dataset.region === lobby.region;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-checked', active ? 'true' : 'false');
        });
        const bar = document.getElementById('subwayTrainingBar');
        if (bar) bar.hidden = lobby.mode !== 'training';
        document.getElementById('subwayMapGrid')?.classList.toggle('is-training', lobby.mode === 'training');
        syncQuickPlay();
    }

    function syncQuickPlay() {
        const button = document.getElementById('subwayQuickPlay');
        if (!button) return;
        const map = lobby.last && maps.find(m => m.slug === lobby.last.slug);
        const mode = lobby.last?.mode || 'original';
        if (!map || (mode !== 'original' && !map.training)) {
            button.hidden = true;
            return;
        }
        button.hidden = false;
        button.querySelector('span').textContent = mode === 'original'
            ? t(`Gioca di nuovo: ${map.name}`, `Play again: ${map.name}`)
            : t(`Di nuovo: ${map.name} (${modeLabel(mode)})`, `Again: ${map.name} (${modeLabel(mode)})`);
        button.onclick = () => launchMap(map, mode);
    }

    // Radio group con le frecce, come da ARIA: una sola voce tabulabile.
    function bindRadioGroup(selector, onPick) {
        const buttons = [...document.querySelectorAll(selector)];
        buttons.forEach((button, index) => {
            button.addEventListener('click', () => onPick(button));
            button.addEventListener('keydown', event => {
                const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];
                if (!step) return;
                event.preventDefault();
                const next = buttons[(index + step + buttons.length) % buttons.length];
                next.focus();
                onPick(next);
            });
        });
    }

    function bindLobby() {
        loadLobbyState();
        bindRadioGroup('[data-mode]', button => {
            lobby.mode = button.dataset.mode === 'training' ? 'training' : 'original';
            saveLobbyState();
            syncLobbyControls();
            applyMapFilters();
        });
        bindRadioGroup('[data-training]', button => {
            lobby.training = button.dataset.training;
            saveLobbyState();
            syncLobbyControls();
            applyMapFilters();
        });
        bindRadioGroup('[data-region]', button => {
            lobby.region = button.dataset.region;
            saveLobbyState();
            syncLobbyControls();
            applyMapFilters();
        });
        document.getElementById('subwayMapSearch')?.addEventListener('input', event => {
            lobby.query = event.target.value || '';
            applyMapFilters();
        });
        syncLobbyControls();

        // Il gioco si controlla solo da tastiera: su un dispositivo solo touch
        // conviene dirlo prima dei 20 MB di download.
        const notice = document.getElementById('subwayTouchNotice');
        if (notice && window.matchMedia?.('(hover: none) and (pointer: coarse)').matches) notice.hidden = false;
    }

    function updateBindingUi() {
        document.querySelectorAll('[data-keybind]').forEach(button => {
            const code = state.bindings[button.dataset.keybind] || '';
            button.textContent = friendlyKey(code);
        });
        Object.keys(state.bindings).forEach(action => {
            const hud = document.getElementById(`hudKey-${action}`);
            if (hud) hud.textContent = friendlyKey(state.bindings[action]);
        });
    }

    function friendlyKey(code) {
        return String(code || '')
            .replace(/^Key/, '')
            .replace(/^Digit/, '')
            .replace('Arrow', '')
            .replace('Space', t('SPAZIO', 'SPACE'));
    }

    function normalizeHexColor(value, fallback) {
        const color = String(value || '').trim().toLowerCase();
        return /^#[0-9a-f]{6}$/.test(color) ? color : fallback;
    }

    function hexToRgb(value) {
        const color = normalizeHexColor(value, '#090d18');
        return `${parseInt(color.slice(1, 3), 16)}, ${parseInt(color.slice(3, 5), 16)}, ${parseInt(color.slice(5, 7), 16)}`;
    }

    function formatCssUrl(raw) {
        let url = String(raw || '').trim();
        if (!url) return 'none';
        if (url.startsWith('url(') && url.endsWith(')')) {
            url = url.slice(4, -1).trim();
            if ((url.startsWith('"') && url.endsWith('"')) || (url.startsWith("'") && url.endsWith("'"))) {
                url = url.slice(1, -1).trim();
            }
        }
        if (!url) return 'none';
        const cleanUrl = url.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        return `url("${cleanUrl}")`;
    }

    function syncSettingsUi() {
        document.querySelectorAll('[data-setting="blockSpace"]').forEach(input => {
            input.checked = state.blockSpace;
        });
        document.querySelectorAll('[data-setting="autoBoost"]').forEach(input => {
            input.checked = state.autoBoost;
        });
        document.querySelectorAll('[data-setting="vsync"]').forEach(input => {
            input.checked = state.vsync;
        });
        document.querySelectorAll('[data-fps-limit]').forEach(input => {
            input.value = String(state.fpsLimit);
            input.disabled = state.vsync;
            input.closest('[data-fps-control]')?.classList.toggle('is-disabled', state.vsync);
        });
        document.querySelectorAll('[data-setting="perfMode"]').forEach(input => {
            input.checked = state.perfMode;
        });
        document.querySelectorAll('[data-render-scale]').forEach(select => {
            select.value = String(state.renderScale);
        });

        document.querySelectorAll('[data-widget][data-widget-prop]').forEach(input => {
            const wKey = input.dataset.widget;
            const prop = input.dataset.widgetProp;
            const cfg = state.overlayTheme[wKey];
            if (!cfg || cfg[prop] === undefined) return;

            if (input.type === 'color') {
                input.value = normalizeHexColor(cfg[prop], '#000000');
            } else if (input.type === 'range') {
                input.value = String(cfg[prop]);
                const output = input.parentElement?.querySelector('[data-widget-output]');
                if (output) {
                    let unit = '%';
                    if (prop === 'blur' || prop === 'borderRadius' || prop === 'shadowBlur' || prop === 'shadowX' || prop === 'shadowY' || prop === 'bgImagePosX' || prop === 'bgImagePosY' || prop === 'timerFontSize' || prop === 'timerTextPosX' || prop === 'timerTextPosY' || prop === 'timerWidth' || prop === 'timerHeight') {
                        unit = 'px';
                    } else if (prop === 'bgImageRotate') {
                        unit = '°';
                    }
                    output.textContent = `${cfg[prop]}${unit}`;
                }
            } else if (input.type === 'text') {
                if (document.activeElement !== input) {
                    input.value = String(cfg[prop] || '');
                }
            } else if (input.tagName === 'SELECT') {
                input.value = String(cfg[prop] || 'cover');
            }
        });

        document.querySelectorAll('[data-timer-bg-preview]').forEach(thumb => {
            const rawUrl = String(state.overlayTheme.timer?.bgImage || '').trim();
            if (rawUrl) {
                const bgImgUrl = formatCssUrl(rawUrl);
                const cfg = state.overlayTheme.timer || {};
                const fitMode = cfg.bgFit || 'cover';
                let bgSizeVal = 'cover';
                if (fitMode === 'contain') {
                    bgSizeVal = 'contain';
                } else if (fitMode === 'custom') {
                    const scale = cfg.bgImageScale ?? 100;
                    bgSizeVal = `${scale}% auto`;
                } else {
                    const scale = cfg.bgImageScale ?? 100;
                    bgSizeVal = scale === 100 ? 'cover' : `${scale}% auto`;
                }
                const posX = cfg.bgImagePosX ?? 0;
                const posY = cfg.bgImagePosY ?? 0;
                const bgPosVal = `calc(50% + ${posX}px) calc(50% + ${posY}px)`;
                const rotVal = `${cfg.bgImageRotate ?? 0}deg`;

                thumb.style.backgroundImage = bgImgUrl;
                thumb.style.backgroundSize = bgSizeVal;
                thumb.style.backgroundPosition = bgPosVal;
                thumb.style.transform = `rotate(${rotVal})`;
                thumb.textContent = '';
            } else {
                thumb.style.backgroundImage = 'none';
                thumb.style.transform = 'none';
                thumb.textContent = t('Nessuna immagine impostata', 'No custom banner image set');
            }
        });

        document.querySelectorAll('[data-overlay-toggle="showBoost"]').forEach(input => {
            input.checked = state.overlayTheme.showBoost === true;
        });
        document.querySelectorAll('[data-overlay-toggle="showHeaders"]').forEach(input => {
            input.checked = state.overlayTheme.showHeaders !== false;
        });
    }

    function applyOverlayTheme() {
        ['timer', 'fps', 'keys', 'audio', 'settings'].forEach(widgetKey => {
            const cfg = state.overlayTheme[widgetKey];
            if (!cfg) return;

            document.querySelectorAll(`[id^="hudWidget"], [data-preview-widget="${widgetKey}"]`).forEach(widget => {
                if (!widget) return;
                const isTarget = widgetKey === 'timer' ? widget.classList.contains('subway-timer-widget')
                    : widgetKey === 'fps' ? widget.classList.contains('subway-fps-widget')
                    : widgetKey === 'keys' ? widget.classList.contains('subway-keys-widget')
                    : widgetKey === 'audio' ? widget.classList.contains('subway-audio-widget')
                    : widget.classList.contains('subway-settings-btn-widget');
                if (!isTarget) return;

                const bgRgb = hexToRgb(cfg.bg || '#090d18');
                const borderRgb = hexToRgb(cfg.borderColor || '#06b6d4');
                const accentHex = cfg.accentColor || cfg.borderColor || '#06b6d4';
                const shadowRgb = hexToRgb(cfg.shadowColor || '#000000');

                widget.style.setProperty('--subway-overlay-bg-rgb', bgRgb);
                widget.style.setProperty('--subway-overlay-opacity', String((cfg.bgOpacity ?? 88) / 100));
                widget.style.setProperty('--subway-overlay-text', cfg.textColor || '#ffffff');
                widget.style.setProperty('--subway-overlay-accent', accentHex);
                widget.style.setProperty('--subway-overlay-accent-rgb', borderRgb);
                widget.style.setProperty('--subway-overlay-border-opacity', String((cfg.borderOpacity ?? 68) / 100));
                widget.style.setProperty('--subway-overlay-border-radius', `${cfg.borderRadius ?? 12}px`);
                widget.style.setProperty('--subway-overlay-blur', `${cfg.blur ?? 16}px`);

                widget.style.setProperty('--subway-overlay-shadow-color-rgb', shadowRgb);
                widget.style.setProperty('--subway-overlay-shadow-opacity', String((cfg.shadowOpacity ?? 50) / 100));
                widget.style.setProperty('--subway-overlay-shadow-blur', `${cfg.shadowBlur ?? 8}px`);
                widget.style.setProperty('--subway-overlay-shadow-x', `${cfg.shadowX ?? 0}px`);
                widget.style.setProperty('--subway-overlay-shadow-y', `${cfg.shadowY ?? 2}px`);

                widget.classList.toggle('hide-header', state.overlayTheme.showHeaders === false);

                if (widgetKey === 'timer') {
                    const rawUrl = String(cfg.bgImage || '').trim();
                    const bgImgUrl = formatCssUrl(rawUrl);
                    const fitMode = cfg.bgFit || 'cover';
                    let bgSizeVal = 'cover';
                    if (fitMode === 'contain') {
                        bgSizeVal = 'contain';
                    } else if (fitMode === 'custom') {
                        const scale = cfg.bgImageScale ?? 100;
                        bgSizeVal = `${scale}% auto`;
                    } else {
                        const scale = cfg.bgImageScale ?? 100;
                        bgSizeVal = scale === 100 ? 'cover' : `${scale}% auto`;
                    }

                    const posX = cfg.bgImagePosX ?? 0;
                    const posY = cfg.bgImagePosY ?? 0;
                    const bgPosVal = `calc(50% + ${posX}px) calc(50% + ${posY}px)`;
                    const rotVal = `${cfg.bgImageRotate ?? 0}deg`;

                    widget.style.setProperty('--subway-timer-bg-image', bgImgUrl);
                    widget.style.setProperty('--subway-timer-bg-image-opacity', String((cfg.bgImageOpacity ?? 100) / 100));
                    widget.style.setProperty('--subway-timer-bg-size', bgSizeVal);
                    widget.style.setProperty('--subway-timer-bg-pos', bgPosVal);
                    widget.style.setProperty('--subway-timer-bg-rotate', rotVal);

                    // Timer text size, scale & position
                    const textScaleX = (cfg.timerTextScaleX ?? 100) / 100;
                    const textScaleY = (cfg.timerTextScaleY ?? 100) / 100;
                    widget.style.setProperty('--subway-timer-font-size', `${cfg.timerFontSize ?? 33}px`);
                    widget.style.setProperty('--subway-timer-text-scale-x', String(textScaleX));
                    widget.style.setProperty('--subway-timer-text-scale-y', String(textScaleY));
                    widget.style.setProperty('--subway-timer-text-x', `${cfg.timerTextPosX ?? 0}px`);
                    widget.style.setProperty('--subway-timer-text-y', `${cfg.timerTextPosY ?? 0}px`);

                    // Timer widget dimensions
                    widget.style.setProperty('--subway-timer-width', `${cfg.timerWidth ?? 250}px`);
                    widget.style.setProperty('--subway-timer-height', `${cfg.timerHeight ?? 90}px`);
                    
                    const innerBg = widget.querySelector('.subway-timer-banner-bg');
                    if (innerBg) {
                        innerBg.style.backgroundImage = bgImgUrl;
                        innerBg.style.opacity = String((cfg.bgImageOpacity ?? 100) / 100);
                        innerBg.style.backgroundSize = bgSizeVal;
                        innerBg.style.backgroundPosition = bgPosVal;
                        innerBg.style.transform = `rotate(${rotVal})`;
                    }
                }

                if (widgetKey === 'keys') {
                    widget.style.setProperty('--subway-key-bg-rgb', hexToRgb(cfg.keyBg));
                    widget.style.setProperty('--subway-key-bg-opacity', String(cfg.keyBgOpacity / 100));
                    widget.style.setProperty('--subway-key-hover', normalizeHexColor(cfg.keyHover, '#06b6d4'));
                    widget.style.setProperty('--subway-key-hover-rgb', hexToRgb(cfg.keyHover));
                    widget.style.setProperty('--subway-key-text', normalizeHexColor(cfg.keyText, '#ffffff'));
                    widget.style.setProperty('--subway-key-border-rgb', hexToRgb(cfg.keyBorderColor));
                    widget.style.setProperty('--subway-key-border-opacity', String(cfg.keyBorderOpacity / 100));
                    widget.classList.toggle('hide-boost', state.overlayTheme.showBoost === false);
                }
            });
        });
    }

    function frameTimingSettings() {
        return state.vsync
            ? { mode: 1, value: 1 }
            : { mode: 0, value: 1000 / state.fpsLimit };
    }

    function unityModules() {
        return [state.unity?.Module, window.unityGame?.Module, window.Module]
            .filter((module, index, list) => module && list.indexOf(module) === index);
    }

    // None of the shipped runtimes read Module.mainLoopTimingMode and none of
    // them export setMainLoopTiming, so this alone never reached the engine.
    // It is kept because it is free and correct for builds that do support it;
    // installFrameLimiter() below is what actually enforces the limit.
    function applyFrameTiming() {
        const timing = frameTimingSettings();
        unityModules().forEach(module => {
            module.mainLoopTimingMode = timing.mode;
            module.mainLoopTimingValue = timing.value;
            try {
                if (typeof module.setMainLoopTiming === 'function') {
                    module.setMainLoopTiming(timing.mode, timing.value);
                }
            } catch (_) { /* the launch configuration still applies on next run */ }
        });
    }

    // The Unity framework schedules its main loop with a live global lookup of
    // window.requestAnimationFrame, so gating that call is the one lever that
    // actually caps the engine's frame rate from outside the build.
    //
    // Each animation-frame chain is throttled against its own last run rather
    // than a shared clock: the engine loop and the HUD loop are two independent
    // chains, and one shared deadline would make them take turns, halving both.
    const nativeRaf = window.requestAnimationFrame.bind(window);
    const nativeCancelRaf = window.cancelAnimationFrame.bind(window);
    const frameClocks = new WeakMap();
    const throttledFrames = new Map();
    let frameHandleSeq = 1e7;

    function frameBudgetMs() {
        if (state.vsync) return 0;
        const limit = Math.min(500, Math.max(30, Number(state.fpsLimit) || 144));
        return 1000 / limit;
    }

    function installFrameLimiter() {
        if (window.requestAnimationFrame.__cripsumFrameLimiter) return;

        function limitedRaf(callback) {
            if (typeof callback !== 'function') return nativeRaf(callback);

            const handle = ++frameHandleSeq;
            const step = timestamp => {
                const budget = frameBudgetMs();
                // A frame is released when its own chain has waited long enough.
                // The slack absorbs rAF timestamps landing just under the
                // deadline, which would otherwise cost a whole extra frame.
                if (budget > 0 && timestamp - (frameClocks.get(callback) || 0) < budget - 1) {
                    throttledFrames.set(handle, nativeRaf(step));
                    return;
                }
                throttledFrames.delete(handle);
                frameClocks.set(callback, timestamp);
                callback(timestamp);
            };

            throttledFrames.set(handle, nativeRaf(step));
            return handle;
        }

        function limitedCancelRaf(handle) {
            const nativeHandle = throttledFrames.get(handle);
            if (nativeHandle === undefined) return nativeCancelRaf(handle);
            throttledFrames.delete(handle);
            return nativeCancelRaf(nativeHandle);
        }

        try {
            Object.defineProperty(limitedRaf, '__cripsumFrameLimiter', { value: true });
        } catch (_) {
            limitedRaf.__cripsumFrameLimiter = true;
        }
        window.requestAnimationFrame = limitedRaf;
        window.cancelAnimationFrame = limitedCancelRaf;
    }

    // Unity 2019 sizes its framebuffer as canvas.clientWidth/Height multiplied
    // by (Module.devicePixelRatio || window.devicePixelRatio || 1) and polls it
    // every frame, so this single number decides how many pixels the GPU has to
    // fill. Capping it at 1 already saves 2.25x the work on a 150% Windows
    // scaling setup; going lower trades sharpness for frame rate.
    function renderScaleFactor() {
        if (state.renderScale === 'native') return window.devicePixelRatio || 1;
        return normalizeRenderScale(state.renderScale, 100) / 100;
    }

    function applyRenderScale() {
        const ratio = renderScaleFactor();
        const modules = unityModules();
        modules.forEach(module => { module.devicePixelRatio = ratio; });
        // Unity re-reads the ratio on its own screen-size poll, but the loader
        // and the HUD both key off resize, so nudge them for a mid-run change.
        if (modules.length) window.dispatchEvent(new Event('resize'));
    }

    // Chromium picks the integrated GPU by default for canvases it does not
    // consider demanding, and keeps a readback buffer alive unless told not to.
    // The patch has to be installed before Unity creates its canvas.
    function installWebglPerformancePatch() {
        if (!window.HTMLCanvasElement?.prototype?.getContext) return;
        const original = HTMLCanvasElement.prototype.getContext;
        if (original.__cripsumWebglPatch) return;

        function patchedGetContext(type, attributes) {
            const kind = String(type || '').toLowerCase();
            if (kind !== 'webgl' && kind !== 'webgl2' && kind !== 'experimental-webgl') {
                return original.apply(this, arguments);
            }

            const next = Object.assign({}, attributes || {});
            next.powerPreference = 'high-performance';
            next.preserveDrawingBuffer = false;
            next.failIfMajorPerformanceCaveat = false;
            // Dropping MSAA is the single biggest win on integrated GPUs, but it
            // changes the default framebuffer format, so only the explicit
            // performance profile does it.
            if (state.perfMode) next.antialias = false;

            try {
                return original.call(this, type, next);
            } catch (_) {
                return original.apply(this, arguments);
            }
        }

        try {
            Object.defineProperty(patchedGetContext, '__cripsumWebglPatch', { value: true });
        } catch (_) {
            patchedGetContext.__cripsumWebglPatch = true;
        }
        HTMLCanvasElement.prototype.getContext = patchedGetContext;
    }

    function applyPerformanceMode() {
        document.body.classList.toggle('subway-perf-mode', state.perfMode);
    }

    function bindSettings() {
        if (dom.toggleNoCoinChallenge) {
            dom.toggleNoCoinChallenge.checked = state.challenge;
            dom.toggleNoCoinChallenge.addEventListener('change', () => {
                // Spegnere la sfida a run in corso la chiude (e la salva):
                // altrimenti si potevano raccogliere monete a sfida spenta e
                // riaccenderla con il timer ancora in marcia.
                if (!dom.toggleNoCoinChallenge.checked) resetTimer('challenge_off');
                state.challenge = dom.toggleNoCoinChallenge.checked;
                saveSettings();
                setChallengeStatus(state.challenge ? 'ready' : 'inactive');
            });
        }
        document.querySelectorAll('[data-setting="blockSpace"]').forEach(input => {
            input.addEventListener('change', () => {
                state.blockSpace = input.checked;
                syncSettingsUi();
                saveSettings();
            });
        });

        document.querySelectorAll('[data-setting="autoBoost"]').forEach(input => {
            input.addEventListener('change', () => {
                state.autoBoost = input.checked;
                syncSettingsUi();
                saveSettings();
            });
        });

        document.querySelectorAll('[data-setting="vsync"]').forEach(input => {
            input.addEventListener('change', () => {
                state.vsync = input.checked;
                syncSettingsUi();
                applyFrameTiming();
                saveSettings();
            });
        });

        document.querySelectorAll('[data-render-scale]').forEach(select => {
            select.addEventListener('change', () => {
                state.renderScale = normalizeRenderScale(select.value, state.renderScale);
                syncSettingsUi();
                applyRenderScale();
                saveSettings();
            });
        });

        document.querySelectorAll('[data-setting="perfMode"]').forEach(input => {
            input.addEventListener('change', () => {
                state.perfMode = input.checked;
                syncSettingsUi();
                applyPerformanceMode();
                saveSettings();
            });
        });

        document.querySelectorAll('[data-fps-limit]').forEach(input => {
            input.addEventListener('input', () => {
                const requestedLimit = Number(input.value);
                if (!Number.isFinite(requestedLimit) || requestedLimit < 30 || requestedLimit > 500) return;
                state.fpsLimit = Math.round(requestedLimit);
                document.querySelectorAll('[data-fps-limit]').forEach(other => {
                    if (other !== input) other.value = String(state.fpsLimit);
                });
                applyFrameTiming();
                saveSettings();
            });
            input.addEventListener('change', () => {
                state.fpsLimit = Math.round(Math.min(500, Math.max(30, Number(input.value) || 144)));
                syncSettingsUi();
                applyFrameTiming();
                saveSettings();
            });
        });

        document.querySelectorAll('[data-overlay-tab]').forEach(tabBtn => {
            tabBtn.addEventListener('click', () => {
                const targetPane = tabBtn.dataset.overlayTab;
                const container = tabBtn.closest('.subway-overlay-customization, .subway-modal-box') || document;
                container.querySelectorAll('[data-overlay-tab]').forEach(btn => btn.classList.remove('active'));
                container.querySelectorAll('[data-overlay-pane]').forEach(pane => pane.classList.remove('active'));
                
                container.querySelectorAll(`[data-overlay-tab="${targetPane}"]`).forEach(btn => btn.classList.add('active'));
                container.querySelectorAll(`[data-overlay-pane="${targetPane}"]`).forEach(pane => pane.classList.add('active'));
                document.getElementById('subwayOverlayPreview')?.setAttribute('data-active', targetPane === 'layout' ? 'keys' : targetPane);
            });
        });

        document.querySelectorAll('[data-widget][data-widget-prop]').forEach(input => {
            const wKey = input.dataset.widget;
            const prop = input.dataset.widgetProp;
            const handler = () => {
                if (!state.overlayTheme[wKey]) return;
                if (input.type === 'color') {
                    state.overlayTheme[wKey][prop] = normalizeHexColor(input.value, '#000000');
                } else if (input.type === 'range') {
                    const val = Number(input.value);
                    const min = input.hasAttribute('min') ? parseFloat(input.min) : -Infinity;
                    const max = input.hasAttribute('max') ? parseFloat(input.max) : Infinity;
                    state.overlayTheme[wKey][prop] = Math.min(max, Math.max(min, Number.isFinite(val) ? val : 0));
                } else if (input.type === 'text') {
                    state.overlayTheme[wKey][prop] = String(input.value || '').trim();
                } else if (input.tagName === 'SELECT') {
                    state.overlayTheme[wKey][prop] = String(input.value);
                }
                syncSettingsUi();
                applyOverlayTheme();
                saveSettings();
            };
            input.addEventListener('input', handler);
            input.addEventListener('change', handler);
        });

        document.querySelectorAll('[data-timer-bg-file]').forEach(fileInput => {
            fileInput.addEventListener('change', () => {
                const file = fileInput.files?.[0];
                fileInput.value = '';
                if (!file) return;
                downscaleImage(file).then(dataUrl => {
                    state.overlayTheme.timer.bgImage = dataUrl;
                    syncSettingsUi();
                    applyOverlayTheme();
                    saveSettings();
                }).catch(() => flashSavedBadge(t('Immagine non leggibile', 'Unreadable image'), true));
            });
        });

        document.querySelectorAll('[data-timer-bg-remove]').forEach(btn => {
            btn.addEventListener('click', () => {
                state.overlayTheme.timer.bgImage = '';
                syncSettingsUi();
                applyOverlayTheme();
                saveSettings();
            });
        });

        document.querySelectorAll('[data-overlay-toggle="showBoost"]').forEach(input => {
            input.addEventListener('change', () => {
                state.overlayTheme.showBoost = input.checked;
                syncSettingsUi();
                applyOverlayTheme();
                saveSettings();
            });
        });

        document.querySelectorAll('[data-overlay-toggle="showHeaders"]').forEach(input => {
            input.addEventListener('change', () => {
                state.overlayTheme.showHeaders = input.checked;
                syncSettingsUi();
                applyOverlayTheme();
                saveSettings();
            });
        });

        document.querySelectorAll('[data-reset-overlay-theme]').forEach(button => {
            button.addEventListener('click', () => {
                state.overlayTheme = JSON.parse(JSON.stringify(defaultOverlayTheme));
                syncSettingsUi();
                applyOverlayTheme();
                saveSettings();
            });
        });

        syncSettingsUi();
        applyOverlayTheme();

        let waitingButton = null;
        document.querySelectorAll('[data-keybind]').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelectorAll('[data-keybind]').forEach(item => item.classList.remove('waiting'));
                waitingButton = button;
                button.classList.add('waiting');
                button.textContent = '...';
            });
        });
        window.addEventListener('keydown', event => {
            if (!waitingButton) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            if (event.code === 'Escape') {
                waitingButton.classList.remove('waiting');
                waitingButton = null;
                updateBindingUi();
                return;
            }
            const action = waitingButton.dataset.keybind;
            state.bindings[action] = event.code;
            document.querySelectorAll(`[data-keybind="${action}"]`).forEach(item => item.classList.remove('waiting'));
            waitingButton = null;
            saveSettings();
            updateBindingUi();
        }, true);

        bindSettingsModal();
    }

    /*
     * Finestra delle impostazioni: si apre dalla lobby o dall'ingranaggio in
     * partita, si chiude con Esc, col clic fuori o con "Fatto"; il focus resta
     * dentro finche' e' aperta e torna dov'era alla chiusura.
     */
    let settingsReturnFocus = null;

    function settingsFocusable() {
        return [...dom.subwaySettingsModal.querySelectorAll(
            'button, [href], input, select, textarea, summary, [tabindex]:not([tabindex="-1"])'
        )].filter(el => !el.disabled && !el.hidden && el.offsetParent !== null);
    }

    function openSettingsModal() {
        const modal = dom.subwaySettingsModal;
        if (!modal || !modal.hidden) return;
        settingsReturnFocus = document.activeElement;
        modal.hidden = false;
        requestAnimationFrame(() => modal.classList.add('show'));
        document.body.classList.add('sw-modal-open');
        syncSettingsUi();
        applyOverlayTheme();
        modal.querySelector('[data-settings-tab].is-active')?.focus();
    }

    function closeSettingsModal() {
        const modal = dom.subwaySettingsModal;
        if (!modal || modal.hidden) return;
        modal.classList.remove('show');
        modal.hidden = true;
        document.body.classList.remove('sw-modal-open');
        const back = settingsReturnFocus?.isConnected && settingsReturnFocus !== document.body && !modal.contains(settingsReturnFocus)
            ? settingsReturnFocus
            : dom.openSubwaySettings;
        back?.focus({ preventScroll: true });
        // In partita il focus deve tornare alla canvas, o i tasti non arrivano al gioco.
        if (state.activeMap) dom.subwayGameContainer?.querySelector('canvas')?.focus({ preventScroll: true });
    }

    function selectSettingsTab(name) {
        const modal = dom.subwaySettingsModal;
        modal.querySelectorAll('[data-settings-tab]').forEach(tab => {
            const active = tab.dataset.settingsTab === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
        });
        modal.querySelectorAll('[data-settings-pane]').forEach(pane => {
            const active = pane.dataset.settingsPane === name;
            pane.hidden = !active;
            pane.classList.toggle('is-active', active);
        });
        // "Overlay predefiniti" ha senso solo nella sezione degli overlay.
        modal.querySelectorAll('[data-reset-overlay-theme]').forEach(button => { button.hidden = name !== 'overlay'; });
    }

    function bindSettingsModal() {
        const modal = dom.subwaySettingsModal;
        if (!modal) return;
        dom.openSubwaySettings?.addEventListener('click', openSettingsModal);
        dom.hudWidgetSettingsBtn?.addEventListener('click', event => {
            if (event.defaultPrevented) return;
            openSettingsModal();
        });
        dom.closeSettingsModal?.addEventListener('click', closeSettingsModal);
        modal.querySelectorAll('[data-close-settings]').forEach(button => button.addEventListener('click', closeSettingsModal));
        modal.addEventListener('click', event => {
            if (event.target === modal) closeSettingsModal();
        });

        selectSettingsTab(modal.querySelector('[data-settings-tab].is-active')?.dataset.settingsTab || 'challenge');
        const tabs = [...modal.querySelectorAll('[data-settings-tab]')];
        tabs.forEach((tab, index) => {
            tab.tabIndex = tab.classList.contains('is-active') ? 0 : -1;
            tab.addEventListener('click', () => selectSettingsTab(tab.dataset.settingsTab));
            tab.addEventListener('keydown', event => {
                const step = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[event.key];
                if (!step) return;
                event.preventDefault();
                const next = tabs[(index + step + tabs.length) % tabs.length];
                selectSettingsTab(next.dataset.settingsTab);
                next.focus();
            });
        });

        modal.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                // Esc durante la cattura di un tasto annulla solo quella.
                if (modal.querySelector('[data-keybind].waiting')) return;
                event.preventDefault();
                closeSettingsModal();
                return;
            }
            if (event.key !== 'Tab') return;
            const items = settingsFocusable();
            if (!items.length) return;
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    }

    function createPokiCompatibilityLayer() {
        const resolved = () => Promise.resolve();
        const pokiHandler = {
            init: resolved,
            initWithVideoHB: resolved,
            commercialBreak: resolved,
            rewardedBreak: () => Promise.resolve(false),
            customEvent() {},
            displayAd() {},
            destroyAd() {},
            gameLoadingStart() {},
            gameLoadingProgress() {},
            gameLoadingFinished() {},
            gameInteractive() {},
            gameplayStart() { startTimer('gameplayStart'); },
            gameplayStop() { pauseTimer('gameplayStop'); },
            roundStart() { startNewRound('roundStart'); },
            roundEnd() { finishTimer('roundEnd'); },
            setDebug() {},
            happyTime() {},
            setPlayerAge() {},
            togglePlayerAdvertisingConsent() {},
            toggleNonPersonalized() {},
            setConsentString() {},
            logError() {}
        };
        // Ogni chiamata del gioco al bridge finisce nel log di diagnosi: e' la
        // sequenza di questi eventi che decide quando una run inizia e finisce.
        Object.keys(pokiHandler).forEach(name => {
            const original = pokiHandler[name];
            if (name === 'gameLoadingProgress') return;
            pokiHandler[name] = function (...args) {
                diag(`poki_${name}`, { running: state.running, paused: state.isPaused, ended: state.ended });
                return original.apply(this, args);
            };
        });
        window.PokiSDK = Object.assign(window.PokiSDK || {}, pokiHandler);
        window.pokiReady = true;
        window.pokiAdBlock = false;
        window.initPokiBridge = bridgeName => {
            window.pokiBridge = bridgeName;
            if (window.unityGame?.SendMessage) window.unityGame.SendMessage(bridgeName, 'ready');
            window.commercialBreak = () => {
                return window.PokiSDK.commercialBreak().then(() => {
                    window.unityGame?.SendMessage?.(bridgeName, 'commercialBreakCompleted');
                });
            };
            window.rewardedBreak = () => {
                return window.PokiSDK.rewardedBreak().then(rewarded => {
                    window.unityGame?.SendMessage?.(bridgeName, 'rewardedBreakCompleted', String(rewarded));
                });
            };
        };
    }

    function installWasmMimeFallback() {
        if (!window.WebAssembly || window.WebAssembly.__cripsumMimeFallback) return;
        const compileStreaming = window.WebAssembly.compileStreaming?.bind(window.WebAssembly);
        const instantiateStreaming = window.WebAssembly.instantiateStreaming?.bind(window.WebAssembly);
        if (compileStreaming) {
            window.WebAssembly.compileStreaming = async source => {
                const response = await source;
                const mime = response.headers?.get('content-type') || '';
                return mime.includes('application/wasm')
                    ? compileStreaming(Promise.resolve(response))
                    : window.WebAssembly.compile(await response.arrayBuffer());
            };
        }
        if (instantiateStreaming) {
            window.WebAssembly.instantiateStreaming = async (source, imports) => {
                const response = await source;
                const mime = response.headers?.get('content-type') || '';
                return mime.includes('application/wasm')
                    ? instantiateStreaming(Promise.resolve(response), imports)
                    : window.WebAssembly.instantiate(await response.arrayBuffer(), imports);
            };
        }
        Object.defineProperty(window.WebAssembly, '__cripsumMimeFallback', { value: true });
    }

    function filterKnownUnityNoise() {
        if (console.__cripsumSubwayFiltered) return;
        const ignored = [
            '[FileUtil] Error saving file:',
            'Failed to save OnlineSettings to file:',
            'FS.syncfs operations in flight at once'
        ];
        ['log', 'warn', 'error'].forEach(method => {
            const original = console[method].bind(console);
            console[method] = (...args) => {
                const message = args.map(String).join(' ');
                if (!ignored.some(fragment => message.includes(fragment))) original(...args);
            };
        });
        Object.defineProperty(console, '__cripsumSubwayFiltered', { value: true });
    }

    const activeMasterGains = new Set();
    const VOLUME_STORAGE_KEY = 'cripsum.subway.volume';
    const MUTE_STORAGE_KEY = 'cripsum.subway.muted';

    function getSavedAudioState() {
        let vol = 0.8;
        let muted = false;
        try {
            const rawVol = localStorage.getItem(VOLUME_STORAGE_KEY);
            if (rawVol !== null) vol = Math.max(0, Math.min(1, Number(rawVol)));
            const rawMute = localStorage.getItem(MUTE_STORAGE_KEY);
            if (rawMute !== null) muted = rawMute === 'true';
        } catch (_) {}
        return { vol, muted };
    }

    function applyAudioVolume(vol, muted) {
        if (typeof vol === 'number') state.audioVolume = Math.max(0, Math.min(1, vol));
        if (typeof muted === 'boolean') state.audioMuted = muted;

        const effectiveVol = state.audioMuted ? 0 : state.audioVolume;

        activeMasterGains.forEach(gainNode => {
            try {
                if (gainNode.context && gainNode.context.state !== 'closed') {
                    gainNode.gain.setValueAtTime(effectiveVol, gainNode.context.currentTime);
                }
            } catch (_) {}
        });

        document.querySelectorAll('audio, video').forEach(el => {
            try {
                el.volume = state.audioVolume;
                el.muted = state.audioMuted;
            } catch (_) {}
        });

        updateAudioWidgetsUi();
    }

    function updateAudioWidgetsUi() {
        const isMuted = state.audioMuted || state.audioVolume === 0;
        const iconClass = isMuted
            ? 'fa-solid fa-volume-xmark'
            : state.audioVolume < 0.5
                ? 'fa-solid fa-volume-low'
                : 'fa-solid fa-volume-high';

        document.querySelectorAll('.subway-audio-widget').forEach(widget => {
            const icon = widget.querySelector('.subway-audio-btn i');
            if (icon) icon.className = iconClass;
            const slider = widget.querySelector('.subway-audio-slider');
            if (slider && document.activeElement !== slider) {
                slider.value = String(state.audioMuted ? 0 : state.audioVolume);
            }
        });
    }

    function bindAudioWidgets() {
        document.querySelectorAll('.subway-audio-widget').forEach(widget => {
            if (widget.__audioBound) return;
            widget.__audioBound = true;

            const btn = widget.querySelector('.subway-audio-btn');
            const slider = widget.querySelector('.subway-audio-slider');

            widget.addEventListener('mouseenter', () => widget.classList.add('show-slider'));
            widget.addEventListener('mouseleave', () => {
                if (document.activeElement !== slider) widget.classList.remove('show-slider');
            });

            if (btn) {
                btn.addEventListener('click', e => {
                    e.stopPropagation();
                    const newMuted = !state.audioMuted;
                    let newVol = state.audioVolume;
                    if (!newMuted && newVol === 0) newVol = 0.8;
                    try {
                        localStorage.setItem(MUTE_STORAGE_KEY, String(newMuted));
                        localStorage.setItem(VOLUME_STORAGE_KEY, String(newVol));
                    } catch (_) {}
                    applyAudioVolume(newVol, newMuted);
                });
            }

            if (slider) {
                slider.addEventListener('pointerdown', e => e.stopPropagation());
                slider.addEventListener('mousedown', e => e.stopPropagation());
                slider.addEventListener('touchstart', e => e.stopPropagation());

                slider.addEventListener('focus', () => widget.classList.add('show-slider'));
                slider.addEventListener('blur', () => widget.classList.remove('show-slider'));

                slider.addEventListener('input', () => {
                    const val = Number(slider.value);
                    const newMuted = val === 0;
                    try {
                        localStorage.setItem(VOLUME_STORAGE_KEY, String(val));
                        localStorage.setItem(MUTE_STORAGE_KEY, String(newMuted));
                    } catch (_) {}
                    applyAudioVolume(val, newMuted);
                });
            }
        });

        if (!document.__subwayAudioDismissBound) {
            document.__subwayAudioDismissBound = true;
            document.addEventListener('pointerdown', e => {
                document.querySelectorAll('.subway-audio-widget').forEach(w => {
                    if (!w.contains(e.target)) w.classList.remove('show-slider');
                });
            });
        }
    }

    function installAudioMasterVolume() {
        const Context = window.AudioContext || window.webkitAudioContext;
        if (!Context || Context.prototype.__cripsumMasterVolumeHooked) return;

        function getContextMasterGain(ctx) {
            if (!ctx.__cripsumMasterGain) {
                const gain = ctx.createGain();
                const effectiveVol = state.audioMuted ? 0 : state.audioVolume;
                gain.gain.value = effectiveVol;
                
                const nativeConnect = AudioNode.prototype.__nativeConnect || AudioNode.prototype.connect;
                nativeConnect.call(gain, ctx.destination);
                ctx.__cripsumMasterGain = gain;
                activeMasterGains.add(gain);
            }
            return ctx.__cripsumMasterGain;
        }

        const nativeConnect = AudioNode.prototype.connect;
        AudioNode.prototype.__nativeConnect = nativeConnect;

        AudioNode.prototype.connect = function (target, ...args) {
            if (target && this.context && target === this.context.destination) {
                const masterGain = getContextMasterGain(this.context);
                if (this !== masterGain) {
                    return nativeConnect.call(this, masterGain, ...args);
                }
            }
            return nativeConnect.call(this, target, ...args);
        };

        Context.prototype.__cripsumMasterVolumeHooked = true;
    }

    function installAudioDetector() {
        const Source = window.AudioBufferSourceNode;
        if (Source && !Source.prototype.__cripsumSubwayHooked) {
            const originalStart = Source.prototype.start;
            Source.prototype.start = function (...args) {
                try { classifyAudio(this.buffer); } catch (_) { /* game audio must never break */ }
                return originalStart.apply(this, args);
            };
            Object.defineProperty(Source.prototype, '__cripsumSubwayHooked', { value: true });
        }

        // Some WebKit/Unity combinations do not route source instances through
        // the public AudioBufferSourceNode prototype. Hook their factory too.
        const Context = window.AudioContext || window.webkitAudioContext;
        if (Context && !Context.prototype.__cripsumSubwayFactoryHooked) {
            const originalCreateBufferSource = Context.prototype.createBufferSource;
            Context.prototype.createBufferSource = function (...args) {
                const source = originalCreateBufferSource.apply(this, args);
                if (!source.__cripsumSubwayInstanceHooked) {
                    const originalStart = source.start;
                    source.start = function (...startArgs) {
                        try { classifyAudio(this.buffer); } catch (_) { /* game audio must never break */ }
                        return originalStart.apply(this, startArgs);
                    };
                    Object.defineProperty(source, '__cripsumSubwayInstanceHooked', { value: true });
                }
                return source;
            };
            Object.defineProperty(Context.prototype, '__cripsumSubwayFactoryHooked', { value: true });
        }
    }

    function classifyAudio(buffer) {
        if (!state.challenge || !buffer) return;
        const duration = Number(buffer.duration || 0);
        if (!Number.isFinite(duration)) return;

        // The run-start clip is unique in the shipped AudioClip table.
        const looksLikeStart = runStartAudioDurations.some(target => Math.abs(duration - target) <= 0.006);
        if (looksLikeStart) {
            diag('audio_start', { running: state.running, paused: state.isPaused, ended: state.ended });
            // Una run avviata da tastiera (ripiego) si riallinea all'audio.
            if (!state.running || state.failed || state.ended || state.run?.fromInput) {
                startNewRound('audio');
                return;
            }
        }

        if (!state.running && !state.isPaused) return;

        // Hr_coin decodes to 25,599 frames / 0.580476s in every verified map.
        // Some browsers remove AAC padding and expose the original 0.561995s,
        // so both representations are supported without widening into jump.
        const sampleRate = Number(buffer.sampleRate || 0);
        const sampleFrames = Number(buffer.length || 0);
        const looksLikeCoinSound = buffer.numberOfChannels === 1 && (
            coinAudioDurations.some(target => Math.abs(duration - target) <= 0.004)
            || (Math.abs(sampleRate - 44100) <= 1 && coinDecodedFrames.has(sampleFrames))
        );
        if (looksLikeCoinSound) {
            diag('audio_coin', { running: state.running, paused: state.isPaused });
            failChallenge('coin_audio');
        }
    }

    function setChallengeStatus(kind, reason = '') {
        if (!dom.subwayStatusBadge) return;
        const isHoverboard = typeof reason === 'string' && reason.includes('hoverboard');
        const labels = {
            ready: t('Pronta', 'Ready'),
            running: t('In corsa', 'Running'),
            paused: t('In pausa', 'Paused'),
            failed: isHoverboard ? t('Hoverboard!', 'Hoverboard!') : t('Moneta!', 'Coin!'),
            ended: t('Terminata', 'Finished'),
            inactive: t('Disattiva', 'Inactive')
        };
        dom.subwayStatusBadge.className = `subway-status-badge ${kind === 'running' || kind === 'ready' || kind === 'paused' ? 'active ' + kind : kind}`;
        dom.subwayStatusBadge.textContent = labels[kind] || kind;
    }

    function formatTimeParts(milliseconds) {
        const total = Math.max(0, Math.floor(milliseconds));
        const ms = total % 1000;
        const totalSeconds = Math.floor(total / 1000);
        const seconds = totalSeconds % 60;
        const totalMinutes = Math.floor(totalSeconds / 60);
        const minutes = totalMinutes % 60;
        const totalHours = Math.floor(totalMinutes / 60);
        const hours = totalHours % 24;
        const days = Math.floor(totalHours / 24);

        const ssStr = String(seconds).padStart(2, '0');
        const mmStr = String(minutes).padStart(2, '0');
        const hhStr = String(hours).padStart(2, '0');
        const msStr = String(ms).padStart(3, '0');

        let mainStr = '';
        if (days > 0) {
            mainStr = `${days}d ${hhStr}:${mmStr}:${ssStr}`;
        } else if (hours > 0) {
            mainStr = `${hhStr}:${mmStr}:${ssStr}`;
        } else {
            mainStr = `${mmStr}:${ssStr}`;
        }

        return {
            mainStr,
            msStr,
            fullText: `${mainStr}.${msStr}`,
            days,
            hours,
            minutes,
            seconds,
            ms
        };
    }

    // The timer repaints many times per second inside a blurred HUD widget, so
    // it writes to two persistent text nodes instead of reparsing HTML: no
    // element churn, and nothing repaints when the string has not changed.
    let timerMainNode = null;
    let timerMsNode = null;
    let lastTimerText = null;

    function ensureTimerNodes() {
        const host = dom.subwayTimerDisplay;
        if (!host) return false;
        if (timerMainNode && timerMainNode.parentNode === host) return true;

        host.replaceChildren();
        timerMainNode = document.createTextNode('');
        timerMsNode = document.createTextNode('');
        const msSpan = document.createElement('span');
        msSpan.className = 'subway-ms';
        msSpan.appendChild(timerMsNode);
        host.append(timerMainNode, msSpan);
        lastTimerText = null;
        return true;
    }

    function renderTimerDisplay(milliseconds) {
        if (!ensureTimerNodes()) return;
        const { mainStr, msStr } = formatTimeParts(milliseconds);
        const text = `${mainStr}.${msStr}`;
        if (text === lastTimerText) return;
        lastTimerText = text;
        timerMainNode.nodeValue = mainStr;
        timerMsNode.nodeValue = `.${msStr}`;
    }

    function formatTime(milliseconds) {
        return formatTimeParts(milliseconds).fullText;
    }

    function getRunningElapsed(now = performance.now()) {
        return state.accumulatedTime + (state.running ? Math.max(0, now - state.startTime) : 0);
    }

    // Chiude la run in corso prima di qualsiasi reset: una run in pausa
    // (gameplayStop alla morte, pausa del gioco) o ancora in corsa va salvata,
    // non buttata. Senza questo, una run senza roundEnd spariva al successivo
    // audio di inizio o a un tasto R.
    function finalizeRun(reason) {
        const hadRun = state.running || state.isPaused || state.ended;
        if (!hadRun || state.scoreSubmittedForRun) return;
        if (state.running) {
            state.accumulatedTime = getRunningElapsed();
            state.running = false;
        }
        state.elapsed = state.accumulatedTime;
        // Il gioco a volte manda due segnali di inizio (audio e roundStart) a
        // pochi ms uno dall'altro: la "run" in mezzo non e' una run.
        // Lo stesso per una run partita da tastiera che il segnale vero
        // riallinea: il suo tempo comprendeva il menu.
        if (reason.startsWith('new_round') && (state.elapsed < minFinalizedRunMs || state.run?.fromInput)) {
            diag('run_discarded', { reason, elapsed: Math.round(state.elapsed) });
            state.scoreSubmittedForRun = true;
            return;
        }
        submitRun(reason);
    }

    function resetTimer(reason = 'reset') {
        finalizeRun(reason);
        if (state.running || state.isPaused || state.ended) {
            diag('reset', { reason, elapsed: Math.round(state.elapsed), run: state.run?.id.slice(0, 8) });
        }
        cancelAutoBoost();
        state.running = false;
        state.isPaused = false;
        state.failed = false;
        state.ended = false;
        state.runArmed = false;
        state.startTime = 0;
        state.accumulatedTime = 0;
        state.elapsed = 0;
        state.scoreSubmittedForRun = false;
        state.run = null;
        hideRunCard();
        renderTimerDisplay(0);
        setChallengeStatus(state.challenge ? 'ready' : 'inactive');
    }

    // Le mappe con il bridge Poki (4399.js) mandano roundStart/gameplayStart;
    // le altre (Mexico, Winter Holiday, Miami) contano solo sull'audio di
    // inizio. Su queste, finche' non e' arrivato nessun segnale del gioco,
    // un tasto avvia ancora il timer come prima, per non lasciarlo fermo se
    // l'impronta audio non venisse riconosciuta.
    function canStartFromInput() {
        if (!state.challenge || state.sawGameStartSignal || !state.activeMap) return false;
        if (state.ended || state.accumulatedTime > 0) return false;
        return !/^4399(\.sf)?\.js$/.test(state.activeMap.bootstrap || '');
    }

    function startNewRound(source) {
        resetTimer(`new_round_${source}`);
        startTimer(source);
    }

    function startTimer(source) {
        if (!state.challenge) return;
        if (state.running) return;

        // If previously paused and not finished, resume seamlessly from accumulated time!
        if (state.isPaused && !state.failed && !state.ended) {
            state.running = true;
            state.isPaused = false;
            state.startTime = performance.now();
            setChallengeStatus('running');
            dom.subwayStartHint?.classList.remove('is-visible');
            bootLog(t(`Run ripresa (${source})`, `Run resumed (${source})`));
            diag('run_resume', { source, elapsed: Math.round(state.accumulatedTime), run: state.run?.id.slice(0, 8) });
            updateTimer();
            return;
        }

        const fromInput = source === 'input';
        if (!fromInput) state.sawGameStartSignal = true;

        // Starting a fresh run
        resetTimer(`start_${source}`);
        state.running = true;
        state.isPaused = false;
        state.startTime = performance.now();
        state.accumulatedTime = 0;
        state.run = {
            id: newRunId(),
            map: state.activeMap?.slug || 'london',
            startPerf: state.startTime,
            mode: state.runMode === 'original' ? 'original' : 'training',
            fromInput
        };
        hideRunCard();
        setChallengeStatus('running');
        dom.subwayStartHint?.classList.remove('is-visible');
        bootLog(t(`Run avviata (${source})`, `Run started (${source})`));
        diag('run_start', { source, run: state.run.id.slice(0, 8), map: state.run.map });
        registerRun(state.run);
        triggerAutoBoost();
        updateTimer();
    }

    let autoBoostTimeouts = [];

    function cancelAutoBoost() {
        if (autoBoostTimeouts.length > 0) {
            autoBoostTimeouts.forEach(id => clearTimeout(id));
            autoBoostTimeouts = [];
        }
    }

    function triggerAutoBoost() {
        cancelAutoBoost();
        if (!state.autoBoost) return;

        bootLog(t('Auto Boost (3x) programmato a 1.5s dall\'avvio...', 'Auto Boost (3x) scheduled at 1.5s from start...'));

        // The Headstart icon in Subway Surfers appears around 1.4s - 1.8s.
        // We trigger every 280ms starting from 1400ms to immediately catch all 3 uses as early as possible.
        const delays = [1400, 1680, 1960, 2240, 2520, 2800, 3100, 3400];

        delays.forEach(delay => {
            const timeoutId = setTimeout(() => {
                if (!state.failed && !state.ended && !state.isPaused) {
                    activateStartBoost();
                    flashHudKey('boost', true);
                    setTimeout(() => flashHudKey('boost', false), 120);
                }
            }, delay);
            autoBoostTimeouts.push(timeoutId);
        });
    }

    function newRunId() {
        const bytes = new Uint8Array(16);
        crypto.getRandomValues(bytes);
        return Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
    }

    // Legge una risposta delle API senza esplodere su una pagina di errore
    // HTML (LiteSpeed, Cloudflare, redirect del ban).
    async function readApiResponse(response) {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (_) {
            return null;
        }
    }

    // Registra la run sul server. L'id lo genera il client, quindi il
    // salvataggio non dipende dalla risposta: se start_run fallisce si
    // riprova, dichiarando quanto tempo e' passato dall'inizio della run.
    const runRegistrations = new Map();
    const registerRetryDelays = [1000, 3000, 7000, 15000, 30000, 60000];

    function registerRun(run) {
        const promise = (async () => {
            for (let attempt = 0; attempt <= registerRetryDelays.length; attempt++) {
                if (attempt > 0) await new Promise(resolve => setTimeout(resolve, registerRetryDelays[attempt - 1]));
                let status = 0;
                let data = null;
                try {
                    const response = await fetch('/api/subway/start_run.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        keepalive: true,
                        body: JSON.stringify({
                            run_id: run.id,
                            map_slug: run.map,
                            mode: run.mode || 'original',
                            elapsed_ms: Math.max(0, Math.round(performance.now() - run.startPerf))
                        })
                    });
                    status = response.status;
                    data = await readApiResponse(response);
                } catch (_) { /* rete: si riprova */ }

                diag('register', { run: run.id.slice(0, 8), attempt, http: status, code: data?.code || null });
                if (data?.status === 'success') return true;
                // Un 4xx con risposta JSON e' definitivo (non loggato, ban, mappa).
                if (data && status >= 400 && status < 500 && status !== 408 && status !== 429) return false;
            }
            return false;
        })();
        runRegistrations.set(run.id, promise);
        return promise;
    }

    function pauseTimer(source) {
        if (!state.running || state.failed || state.ended) return;
        cancelAutoBoost();
        state.accumulatedTime = getRunningElapsed();
        state.elapsed = state.accumulatedTime;
        state.running = false;
        state.isPaused = true;
        renderTimerDisplay(state.elapsed);
        setChallengeStatus('paused');
        bootLog(t(`Pausa (${source})`, `Paused (${source})`));
        diag('run_pause', { source, elapsed: Math.round(state.elapsed), run: state.run?.id.slice(0, 8) });
    }

    function updateTimer(now = performance.now()) {
        if (!state.running) return;
        state.elapsed = getRunningElapsed(now);
        state.lastTimerPaint = now;
        renderTimerDisplay(state.elapsed);
        startGameLoop();
    }

    function finishTimer(source) {
        if ((!state.running && !state.isPaused) || state.failed || state.ended) return;
        cancelAutoBoost();
        if (state.running) {
            state.accumulatedTime = getRunningElapsed();
        }
        state.elapsed = state.accumulatedTime;
        state.running = false;
        state.isPaused = false;
        state.ended = true;
        renderTimerDisplay(state.elapsed);
        setChallengeStatus(state.challenge ? 'ended' : 'inactive');
        bootLog(t(`Run terminata (${source})`, `Run finished (${source})`));
        showRunCard('ended');
        submitRun(`finish_${source}`);
    }

    function failChallenge(reason = 'coin') {
        if ((!state.running && !state.isPaused) || state.failed) return;
        cancelAutoBoost();
        if (state.running) {
            state.accumulatedTime = getRunningElapsed();
        }
        state.elapsed = state.accumulatedTime;
        state.running = false;
        state.isPaused = false;
        state.failed = true;
        state.ended = true;
        renderTimerDisplay(state.elapsed);
        setChallengeStatus('failed', reason);
        const reasonText = (typeof reason === 'string' && reason.includes('hoverboard'))
            ? t('Sfida fallita: Hoverboard usato (vietato!)', 'Challenge failed: Hoverboard used (prohibited!)')
            : t(`Sfida fallita (${reason})`, `Challenge failed (${reason})`);
        bootLog(reasonText);
        showRunCard(typeof reason === 'string' && reason.includes('hoverboard') ? 'hoverboard' : (String(reason).startsWith('manual') ? 'manual' : 'coin'));
        submitRun(`fail_${reason}`);
    }

    /*
     * Scheda di fine run in partita: tempo, come e' finita e cosa ha detto il
     * server. Si nasconde da sola alla run successiva.
     */
    function showRunCard(kind) {
        const card = dom.subwayRunCard;
        if (!card || !state.challenge) return;
        const labels = {
            coin: t('Moneta presa', 'Coin collected'),
            hoverboard: t('Hoverboard usato', 'Hoverboard used'),
            manual: t('Run chiusa', 'Run ended'),
            ended: t('Run terminata', 'Run over')
        };
        card.dataset.kind = kind;
        card.dataset.run = state.run?.id || '';
        card.querySelector('[data-run-label]').textContent = labels[kind] || labels.ended;
        card.querySelector('[data-run-time]').textContent = formatTime(state.elapsed);
        const save = card.querySelector('[data-run-save]');
        save.dataset.state = 'pending';
        save.textContent = state.run?.mode === 'training'
            ? t('Allenamento: fuori classifica', 'Training: not ranked')
            : t('Salvataggio…', 'Saving…');
        card.hidden = false;
    }

    function hideRunCard() {
        if (dom.subwayRunCard) dom.subwayRunCard.hidden = true;
    }

    function updateRunCardSave(runId, text, kind) {
        const card = dom.subwayRunCard;
        if (!card || card.hidden || card.dataset.run !== runId) return;
        const save = card.querySelector('[data-run-save]');
        save.textContent = text;
        save.dataset.state = kind;
    }

    // Mette in coda il tempo della run corrente (una volta sola per run).
    function submitRun(reason) {
        if (state.scoreSubmittedForRun) return;
        state.scoreSubmittedForRun = true;
        const run = state.run;
        const timeMs = Math.floor(state.elapsed);
        if (!state.challenge || !run || timeMs <= 0) {
            diag('run_not_queued', { reason, elapsed: timeMs, challenge: state.challenge, run: !!run });
            return;
        }
        enqueueScore({
            id: run.id,
            time_ms: timeMs,
            map: run.map,
            reason: String(reason).toLowerCase().replace(/[^a-z0-9_]/g, '_').slice(0, 32)
        });
    }

    /*
     * Coda dei salvataggi. Ogni run chiusa finisce qui e in localStorage prima
     * di partire, e ne esce solo quando il server ha risposto in modo
     * definitivo: un errore di rete o un 5xx si ritenta con backoff, e cio' che
     * resta alla chiusura della pagina parte con sendBeacon e viene
     * ricontrollato al caricamento successivo (il server risponde "duplicate"
     * senza contarlo due volte).
     */
    const pendingKey = 'cripsum-subway-pending-v1';
    const pendingMaxAgeMs = 2 * 24 * 60 * 60 * 1000;
    const saveRetryDelays = [2000, 5000, 15000, 30000, 60000, 120000, 300000];
    const saveInFlight = new Set();
    let pendingScores = [];
    let flushTimer = 0;
    let flushPromise = null;

    function loadPendingScores() {
        try {
            const raw = JSON.parse(localStorage.getItem(pendingKey) || '[]');
            const now = Date.now();
            pendingScores = Array.isArray(raw)
                ? raw.filter(item => item && typeof item.id === 'string' && now - (item.createdAt || 0) < pendingMaxAgeMs)
                : [];
        } catch (_) {
            pendingScores = [];
        }
        if (pendingScores.length) diag('queue_restored', { count: pendingScores.length });
    }

    function persistPendingScores() {
        try {
            if (pendingScores.length) localStorage.setItem(pendingKey, JSON.stringify(pendingScores));
            else localStorage.removeItem(pendingKey);
        } catch (_) { /* quota piena o storage bloccato: resta la coda in memoria */ }
    }

    function enqueueScore(entry) {
        if (pendingScores.some(item => item.id === entry.id)) return;
        pendingScores.push(Object.assign({ createdAt: Date.now(), attempts: 0, nextAt: 0, warned: false }, entry));
        persistPendingScores();
        diag('run_queued', { run: entry.id.slice(0, 8), time: entry.time_ms, reason: entry.reason });
        flushScores();
    }

    function removePendingScore(id) {
        pendingScores = pendingScores.filter(item => item.id !== id);
        persistPendingScores();
    }

    function scoreBody(item) {
        return JSON.stringify({ run_id: item.id, time_ms: item.time_ms, map_slug: item.map, reason: item.reason });
    }

    function flushScores() {
        if (flushPromise) return flushPromise;
        clearTimeout(flushTimer);
        flushPromise = (async () => {
            const now = Date.now();
            for (const item of pendingScores.slice()) {
                if (item.nextAt > now || saveInFlight.has(item.id)) continue;
                await sendScore(item);
            }
        })().finally(() => {
            flushPromise = null;
            scheduleFlush();
        });
        return flushPromise;
    }

    function scheduleFlush() {
        clearTimeout(flushTimer);
        if (!pendingScores.length) return;
        const next = Math.min(...pendingScores.map(item => item.nextAt));
        flushTimer = setTimeout(flushScores, Math.max(250, next - Date.now()));
    }

    async function sendScore(item) {
        saveInFlight.add(item.id);
        try {
            // Il salvataggio parte dopo che start_run ha avuto risposta, cosi'
            // non arriva al server prima della registrazione della run.
            const registration = runRegistrations.get(item.id);
            if (registration) await registration;

            let status = 0;
            let data = null;
            try {
                const response = await fetch('/api/subway/save_score.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    keepalive: true,
                    body: scoreBody(item)
                });
                status = response.status;
                data = await readApiResponse(response);
            } catch (_) { /* rete */ }

            diag('save', { run: item.id.slice(0, 8), time: item.time_ms, attempt: item.attempts, http: status, code: data?.code || null });

            if (data?.status === 'success') {
                removePendingScore(item.id);
                if (data.best_time_ms) state.userBestTimeMs = Math.max(state.userBestTimeMs, data.best_time_ms);
                showScoreToast(data.is_new_best ? 'best' : 'saved', item.time_ms, data);
                updateRunCardSave(item.id, data.is_new_best
                    ? t(`Nuovo record! #${data.rank}`, `New best! #${data.rank}`)
                    : t(`Salvata · record ${formatTime(data.best_time_ms)}`, `Saved · best ${formatTime(data.best_time_ms)}`), data.is_new_best ? 'best' : 'ok');
                fetchLeaderboard();
                return;
            }
            if (data?.status === 'ignored') {
                removePendingScore(item.id);
                updateRunCardSave(item.id, t('Allenamento: fuori classifica', 'Training: not ranked'), 'muted');
                return;
            }
            const definitive = data && status >= 400 && status < 500 && status !== 408 && status !== 429;
            if (definitive) {
                removePendingScore(item.id);
                showScoreToast(status === 401 ? 'expired' : 'rejected', item.time_ms, data);
                updateRunCardSave(item.id, t('Non salvata', 'Not saved'), 'error');
                return;
            }

            item.attempts += 1;
            if (item.attempts > saveRetryDelays.length) {
                removePendingScore(item.id);
                showScoreToast('lost', item.time_ms, data);
                return;
            }
            item.nextAt = Date.now() + saveRetryDelays[item.attempts - 1];
            updateRunCardSave(item.id, t('Salvataggio non riuscito, riprovo…', 'Save failed, retrying…'), 'warn');
            if (!item.warned) {
                item.warned = true;
                showScoreToast('retry', item.time_ms, data);
            }
            persistPendingScores();
        } finally {
            saveInFlight.delete(item.id);
        }
    }

    // All'uscita dalla pagina: chiude la run e rispedisce con sendBeacon
    // tutto cio' che e' ancora in coda.
    function flushScoresOnExit(reason) {
        finalizeRun(reason);
        // Anche quelle gia' in volo: la fetch potrebbe non essere ancora
        // partita, e un doppio invio il server lo riconosce come duplicate.
        pendingScores.forEach(item => {
            let sent = false;
            try {
                sent = navigator.sendBeacon?.('/api/subway/save_score.php', new Blob([scoreBody(item)], { type: 'application/json' })) || false;
            } catch (_) {}
            diag('beacon', { run: item.id.slice(0, 8), sent });
        });
        persistPendingScores();
        persistDiag();
    }

    // Aspetta che la coda si svuoti, al massimo per `timeoutMs`.
    function waitForScores(timeoutMs) {
        const deadline = Date.now() + timeoutMs;
        return new Promise(resolve => {
            const check = () => {
                if (!pendingScores.length || Date.now() >= deadline) return resolve(!pendingScores.length);
                setTimeout(check, 100);
            };
            flushScores();
            check();
        });
    }

    function showScoreToast(kind, timeMs, data = {}) {
        const existing = document.getElementById('subwayScoreToast');
        if (existing) existing.remove();

        const formatted = formatTime(timeMs);
        const rankText = data?.rank ? ` · #${data.rank}` : '';
        const rejectedReasons = {
            time_mismatch: t('tempo non coerente con la sessione', 'time does not match the session'),
            unknown_run: t('run non registrata', 'run not registered'),
            banned: t('account sospeso', 'account suspended')
        };
        const variants = {
            best: {
                cls: '', icon: 'fa-trophy',
                title: t('Nuovo Record Personale! 🎉', 'New Personal Best! 🎉'),
                detail: `${formatted}${rankText}`
            },
            saved: {
                cls: 'is-saved', icon: 'fa-check',
                title: t('Run salvata', 'Run saved'),
                detail: `${formatted} · ${t('record', 'best')} ${formatTime(data?.best_time_ms || state.userBestTimeMs)}`
            },
            retry: {
                cls: 'is-warn', icon: 'fa-rotate',
                title: t('Salvataggio non riuscito, riprovo…', 'Save failed, retrying…'),
                detail: formatted
            },
            expired: {
                cls: 'is-error', icon: 'fa-user-clock',
                title: t('Sessione scaduta: accedi di nuovo', 'Session expired: please log in again'),
                detail: t(`Run non salvata (${formatted})`, `Run not saved (${formatted})`)
            },
            rejected: {
                cls: 'is-error', icon: 'fa-triangle-exclamation',
                title: t('Run non salvata', 'Run not saved'),
                detail: `${formatted} · ${rejectedReasons[data?.code] || data?.code || t('errore', 'error')}`
            },
            lost: {
                cls: 'is-error', icon: 'fa-triangle-exclamation',
                title: t('Impossibile salvare la run', 'Could not save the run'),
                detail: t(`${formatted} · server non raggiungibile`, `${formatted} · server unreachable`)
            }
        };
        const variant = variants[kind] || variants.saved;

        const toast = document.createElement('div');
        toast.id = 'subwayScoreToast';
        toast.className = `subway-score-toast ${variant.cls}`.trim();
        toast.setAttribute('role', 'status');
        toast.innerHTML = `
            <div class="subway-toast-icon"><i class="fa-solid ${variant.icon}"></i></div>
            <div class="subway-toast-content">
                <strong></strong>
                <span></span>
            </div>
        `;
        toast.querySelector('strong').textContent = variant.title;
        toast.querySelector('span').textContent = variant.detail;
        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('show'));
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 400);
        }, kind === 'saved' ? 3500 : 5000);
    }

    async function fetchLeaderboard() {
        try {
            const response = await fetch('/api/subway/get_leaderboard.php');
            const result = await readApiResponse(response);
            if (result?.status === 'success') {
                renderLeaderboard(result.data || [], result.user_record);
            }
        } catch (err) {
            console.warn('[Subway Leaderboard] Impossibile caricare la classifica', err);
        }
    }

    // I nomi arrivano gia' escapati dal server; l'URL dell'avatar no.
    function escapeAttr(value) {
        return String(value ?? '').replace(/[&"'<>]/g, c => ({ '&': '&amp;', '"': '&quot;', "'": '&#39;', '<': '&lt;', '>': '&gt;' }[c]));
    }

    function renderLeaderboard(list, userRecord) {
        if (userRecord?.utente_id) state.userId = Number(userRecord.utente_id) || 0;
        // Update personal best card if elements exist
        if (dom.subwayPersonalBestCard) {
            if (userRecord && userRecord.has_record && userRecord.best_time_ms > 0) {
                state.userBestTimeMs = userRecord.best_time_ms;
                if (dom.subwayPersonalBestTime) {
                    dom.subwayPersonalBestTime.textContent = formatTime(userRecord.best_time_ms);
                }
                if (dom.subwayPersonalBestRank) {
                    dom.subwayPersonalBestRank.textContent = `#${userRecord.rank}`;
                }
                if (dom.subwayPersonalBestMap) {
                    const foundMap = maps.find(m => m.slug === userRecord.map_slug);
                    dom.subwayPersonalBestMap.textContent = foundMap ? foundMap.name : userRecord.map_slug;
                }
                dom.subwayPersonalBestCard.hidden = false;
            } else {
                if (dom.subwayPersonalBestTime) {
                    dom.subwayPersonalBestTime.textContent = '--:--.---';
                }
                if (dom.subwayPersonalBestRank) {
                    dom.subwayPersonalBestRank.textContent = '-';
                }
                if (dom.subwayPersonalBestMap) {
                    dom.subwayPersonalBestMap.textContent = '-';
                }
            }
        }

        // Render table rows
        if (dom.subwayLeaderboardBody) {
            if (!list || list.length === 0) {
                dom.subwayLeaderboardBody.innerHTML = '';
                if (dom.subwayLeaderboardEmpty) dom.subwayLeaderboardEmpty.hidden = false;
                if (dom.subwayLeaderboardTable) dom.subwayLeaderboardTable.hidden = true;
                return;
            }

            if (dom.subwayLeaderboardEmpty) dom.subwayLeaderboardEmpty.hidden = true;
            if (dom.subwayLeaderboardTable) dom.subwayLeaderboardTable.hidden = false;

            const fragment = document.createDocumentFragment();
            list.forEach(item => {
                const tr = document.createElement('tr');
                tr.className = `subway-lb-row rank-${item.rank}`;
                if (state.userId && Number(item.utente_id) === state.userId) {
                    tr.classList.add('is-me');
                    tr.setAttribute('aria-current', 'true');
                }

                let rankHtml = `<span class="subway-lb-badge">${item.rank}</span>`;
                if (item.rank === 1) {
                    rankHtml = `<span class="subway-lb-badge rank-gold"><i class="fa-solid fa-crown"></i> 1</span>`;
                } else if (item.rank === 2) {
                    rankHtml = `<span class="subway-lb-badge rank-silver"><i class="fa-solid fa-medal"></i> 2</span>`;
                } else if (item.rank === 3) {
                    rankHtml = `<span class="subway-lb-badge rank-bronze"><i class="fa-solid fa-medal"></i> 3</span>`;
                }

                const mapObj = maps.find(m => m.slug === item.map_slug);
                const mapName = mapObj ? mapObj.name : (item.map_slug || 'London');

                const timeFormatted = formatTime(item.best_time_ms);
                const isPremiumBadge = item.is_premium
                    ? `<span class="subway-lb-premium" title="${t('Account Premium', 'Premium Account')}"><i class="fa-solid fa-star"></i></span>`
                    : '';
                const profileUrl = `/u/${encodeURIComponent(item.username)}`;
                tr.innerHTML = `
                    <td class="subway-lb-pos">${rankHtml}</td>
                    <td class="subway-lb-user">
                        <a href="${profileUrl}" class="subway-lb-user-link subway-lb-user-wrap" title="${item.display_name} (@${item.username})">
                            <img class="subway-lb-avatar" src="${escapeAttr(item.avatar_url)}" alt="" loading="lazy">
                            <div class="subway-lb-names">
                                <strong>${item.display_name} ${isPremiumBadge}</strong>
                                <small>@${item.username}</small>
                            </div>
                        </a>
                    </td>
                    <td class="subway-lb-time"><code>${timeFormatted}</code></td>
                    <td class="subway-lb-map"><span class="subway-map-pill">${mapName}</span></td>
                `;
                tr.querySelector('.subway-lb-avatar')?.addEventListener('error', event => {
                    event.currentTarget.src = '/img/abdul.jpg';
                }, { once: true });
                fragment.appendChild(tr);
            });

            dom.subwayLeaderboardBody.replaceChildren(fragment);
        }
    }

    function bindGameInput() {
        const nativeCodes = new Set(['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight']);
        const nativeActions = { ArrowUp: 'jump', ArrowDown: 'duck', ArrowLeft: 'left', ArrowRight: 'right' };

        document.getElementById('manualFailBtn')?.addEventListener('click', () => {
            if (state.running) {
                failChallenge('manual');
            } else {
                resetTimer('manual_button');
            }
        });

        let idleInputLogged = false;

        window.addEventListener('keydown', event => {
            if (!state.activeMap) return;

            if (state.blockSpace && event.code === 'Space') {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            if (event.repeat || event.target?.closest?.('.subway-settings-modal')) return;

            const action = nativeActions[event.code]
                || Object.keys(state.bindings).find(key => state.bindings[key] === event.code);

            if (action) {
                flashHudKey(action, true);
            }

            if (action === 'boost') {
                event.preventDefault();
                event.stopImmediatePropagation();
                activateStartBoost();
                return;
            }

            // 'R' chiude la run: da ferma o in pausa la salva e azzera il timer.
            if (event.code === 'KeyR') {
                if (state.running) {
                    failChallenge('manual_hotkey');
                } else {
                    resetTimer('manual_hotkey');
                }
                return;
            }

            // Il timer parte solo dai segnali del gioco (roundStart,
            // gameplayStart, audio di inizio run): un tasto premuto nel menu
            // lo faceva partire prima della run e gonfiava il tempo.
            if (event.code === 'Space' && state.running && state.challenge) {
                // Hoverboard activation via Spacebar during challenge run -> instant fail!
                failChallenge('hoverboard');
            } else if ((action || event.code === 'Space') && !state.running && !state.isPaused) {
                if (canStartFromInput()) {
                    startTimer('input');
                } else {
                    if (!idleInputLogged) diag('input_without_run', { key: event.code });
                    idleInputLogged = true;
                }
            }
            if (state.running) idleInputLogged = false;

            if (!action) return;
            if (nativeCodes.has(event.code)) return;
            event.preventDefault();
            if (!remapNativeKeyboardEvent(event, unityCodes[action])) {
                event.stopImmediatePropagation();
                dispatchUnityKey(unityCodes[action], 'keydown');
            }
        }, true);

        let lastCanvasPointerTime = 0;
        dom.subwayGameContainer?.addEventListener('pointerdown', event => {
            if (!state.running || !state.challenge) return;
            if (event.isSyntheticBoost) return;
            const now = performance.now();
            if (now - lastCanvasPointerTime < 380 && (now - state.startTime > 1000)) {
                failChallenge('hoverboard');
            }
            lastCanvasPointerTime = now;
        }, true);
        window.addEventListener('keyup', event => {
            if (!state.activeMap) return;
            if (state.blockSpace && event.code === 'Space') {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }
            const action = nativeActions[event.code]
                || Object.keys(state.bindings).find(key => state.bindings[key] === event.code);
            if (!action) return;
            flashHudKey(action, false);
            if (action === 'boost') {
                event.preventDefault();
                return;
            }
            if (nativeCodes.has(event.code)) return;
            event.preventDefault();
            if (!remapNativeKeyboardEvent(event, unityCodes[action])) {
                event.stopImmediatePropagation();
                dispatchUnityKey(unityCodes[action], 'keyup');
            }
        }, true);
    }

    function remapNativeKeyboardEvent(event, code) {
        const legacy = legacyKeys[code];
        if (!legacy) return false;
        try {
            Object.defineProperties(event, {
                key: { configurable: true, value: legacy.key },
                code: { configurable: true, value: code },
                keyCode: { configurable: true, value: legacy.keyCode },
                which: { configurable: true, value: legacy.keyCode },
                charCode: { configurable: true, value: 0 }
            });
            return event.code === code && event.keyCode === legacy.keyCode;
        } catch (_) {
            return false;
        }
    }

    function createUnityKeyboardEvent(code, type) {
        const legacy = legacyKeys[code] || { key: code, keyCode: 0 };
        const event = new KeyboardEvent(type, {
            code,
            key: legacy.key,
            bubbles: true,
            cancelable: true
        });
        try {
            Object.defineProperties(event, {
                keyCode: { configurable: true, value: legacy.keyCode },
                which: { configurable: true, value: legacy.keyCode },
                charCode: { configurable: true, value: 0 }
            });
        } catch (_) { /* modern key/code fields still work */ }
        return event;
    }

    function dispatchUnityKey(code, type, releaseAfter = false) {
        const target = dom.subwayGameContainer?.querySelector('canvas') || window;
        target.dispatchEvent(createUnityKeyboardEvent(code, type));
        if (releaseAfter) setTimeout(() => dispatchUnityKey(code, 'keyup'), 60);
    }

    // Keydown/keyup fire on the game's critical path, so the HUD keycaps are
    // looked up once instead of on every event.
    const hudKeyCache = new Map();

    function hudKeyElement(action) {
        let element = hudKeyCache.get(action);
        if (!element || !element.isConnected) {
            element = document.getElementById(`hudKey-${action}`);
            hudKeyCache.set(action, element);
        }
        return element;
    }

    function flashHudKey(action, pressed) {
        hudKeyElement(action)?.classList.toggle('pressed', pressed);
    }

    function simulateCanvasClickAt(normX, normY) {
        const canvas = dom.subwayGameContainer?.querySelector('canvas');
        if (!canvas) return;
        const rect = canvas.getBoundingClientRect();
        if (!rect.width || !rect.height) return;

        const clientX = rect.left + rect.width * normX;
        const clientY = rect.top + rect.height * normY;

        const eventProps = {
            clientX,
            clientY,
            screenX: clientX,
            screenY: clientY,
            pageX: clientX + window.scrollX,
            pageY: clientY + window.scrollY,
            bubbles: true,
            cancelable: true,
            view: window,
            button: 0,
            buttons: 1,
            pointerId: 1,
            pointerType: 'mouse',
            isPrimary: true
        };

        try {
            const downPointer = new PointerEvent('pointerdown', eventProps);
            downPointer.isSyntheticBoost = true;
            canvas.dispatchEvent(downPointer);
            const downMouse = new MouseEvent('mousedown', eventProps);
            downMouse.isSyntheticBoost = true;
            canvas.dispatchEvent(downMouse);
            setTimeout(() => {
                const upProps = Object.assign({}, eventProps, { buttons: 0 });
                const upPointer = new PointerEvent('pointerup', upProps);
                upPointer.isSyntheticBoost = true;
                canvas.dispatchEvent(upPointer);
                const upMouse = new MouseEvent('mouseup', upProps);
                upMouse.isSyntheticBoost = true;
                canvas.dispatchEvent(upMouse);
                const clickMouse = new MouseEvent('click', upProps);
                clickMouse.isSyntheticBoost = true;
                canvas.dispatchEvent(clickMouse);
            }, 30);
        } catch (_) {
            const clickMouse = new MouseEvent('click', eventProps);
            clickMouse.isSyntheticBoost = true;
            canvas.dispatchEvent(clickMouse);
        }
    }

    function activateStartBoost() {
        const unity = state.unity || window.unityGame || window.unityInstance || window.gameInstance;
        const targets = [unity, window.unityGame, window.unityInstance, window.Module, window];

        for (const target of targets) {
            if (target && typeof target.SendMessage === 'function') {
                try {
                    // The lower red rocket is powerup slot 2 (Headstart).
                    target.SendMessage('0PowerupHelper', 'SlideinPowerupClicked', 2);
                    target.SendMessage('0PowerupHelper', 'SlideinPowerupClicked', '2');
                    target.SendMessage('PowerupHelper', 'SlideinPowerupClicked', 2);
                } catch (_) {}
            }
        }

        // Also simulate canvas click on the lower-left Headstart rocket position (~12% X, ~80% Y)
        simulateCanvasClickAt(0.12, 0.80);
        simulateCanvasClickAt(0.14, 0.82);

        bootLog(t('Boost rosso attivato (Headstart)', 'Red boost activated (Headstart)'));
        return true;
    }

    function getOverlayBounds(widget) {
        const parent = widget.offsetParent || document.documentElement;
        const parentRect = parent.getBoundingClientRect();
        return {
            parentRect,
            maxX: Math.max(0, parent.clientWidth - widget.offsetWidth),
            maxY: Math.max(0, parent.clientHeight - widget.offsetHeight)
        };
    }

    function saveOverlayPosition(widget) {
        if (!widget.id) return;
        const { parentRect, maxX, maxY } = getOverlayBounds(widget);
        const rect = widget.getBoundingClientRect();
        state.overlayPositions[widget.id] = {
            x: maxX ? Math.min(1, Math.max(0, (rect.left - parentRect.left) / maxX)) : 0,
            y: maxY ? Math.min(1, Math.max(0, (rect.top - parentRect.top) / maxY)) : 0
        };
        saveSettings();
    }

    function restoreOverlayPositions() {
        document.querySelectorAll('.subway-hud-widget:not(.subway-preview-widget)').forEach(widget => {
            if (!widget.id) return;
            const saved = state.overlayPositions[widget.id];
            if (!saved || !Number.isFinite(saved.x) || !Number.isFinite(saved.y)) return;
            const { maxX, maxY } = getOverlayBounds(widget);
            widget.style.left = `${Math.round(Math.min(1, Math.max(0, saved.x)) * maxX)}px`;
            widget.style.top = `${Math.round(Math.min(1, Math.max(0, saved.y)) * maxY)}px`;
            widget.style.right = 'auto';
            widget.style.bottom = 'auto';
        });
    }

    function bindDraggableWidgets() {
        document.querySelectorAll('.subway-hud-widget:not(.subway-preview-widget)').forEach(widget => {
            const handle = widget.querySelector('.widget-handle') || widget;
            let drag = null;
            let suppressClick = false;
            let savePositionTimer = 0;

            handle.addEventListener('pointerdown', event => {
                if (event.button !== 0) return;
                const parent = widget.offsetParent || document.body;
                const parentRect = parent.getBoundingClientRect();
                const rect = widget.getBoundingClientRect();
                drag = {
                    pointerId: event.pointerId,
                    startX: event.clientX,
                    startY: event.clientY,
                    startLeft: rect.left - parentRect.left,
                    startTop: rect.top - parentRect.top,
                    started: false
                };
                try {
                    handle.setPointerCapture(event.pointerId);
                } catch (e) {}
            });

            handle.addEventListener('pointermove', event => {
                if (!drag || event.pointerId !== drag.pointerId) return;
                const deltaX = event.clientX - drag.startX;
                const deltaY = event.clientY - drag.startY;

                if (!drag.started) {
                    if (Math.hypot(deltaX, deltaY) < 4) return;
                    drag.started = true;
                    widget.style.right = 'auto';
                    widget.style.bottom = 'auto';
                    widget.classList.add('is-dragging');
                }

                const { maxX, maxY } = getOverlayBounds(widget);
                const nextX = Math.min(maxX, Math.max(0, Math.round(drag.startLeft + deltaX)));
                const nextY = Math.min(maxY, Math.max(0, Math.round(drag.startTop + deltaY)));
                widget.style.left = `${nextX}px`;
                widget.style.top = `${nextY}px`;

                clearTimeout(savePositionTimer);
                savePositionTimer = setTimeout(() => saveOverlayPosition(widget), 100);
                event.preventDefault();
            });

            const onPointerEnd = event => {
                if (!drag || (event.pointerId !== undefined && event.pointerId !== drag.pointerId)) return;
                try {
                    if (handle.hasPointerCapture(event.pointerId)) {
                        handle.releasePointerCapture(event.pointerId);
                    }
                } catch (e) {}

                if (drag.started) {
                    clearTimeout(savePositionTimer);
                    saveOverlayPosition(widget);
                    suppressClick = true;
                    window.setTimeout(() => { suppressClick = false; }, 350);
                }

                drag = null;
                widget.classList.remove('is-dragging');
            };

            handle.addEventListener('pointerup', onPointerEnd);
            handle.addEventListener('pointercancel', onPointerEnd);

            handle.addEventListener('click', event => {
                if (!suppressClick) return;
                suppressClick = false;
                event.preventDefault();
                event.stopImmediatePropagation();
            }, true);
        });

        window.addEventListener('resize', restoreOverlayPositions);
    }

    // A repaint of the timer forces the compositor to redo the widget's
    // backdrop blur over the whole canvas, and the millisecond digits are
    // unreadable above ~30 Hz anyway, so the display is throttled.
    function timerPaintInterval() {
        return state.perfMode ? 66 : 33;
    }

    // One animation frame callback drives both HUD readouts. Two independent
    // rAF loops meant two style/layout invalidations per frame on top of
    // Unity's own.
    function startGameLoop() {
        if (state.loopFrame) return;
        state.fpsLastSample = performance.now();
        state.fpsFrames = 0;

        const tick = now => {
            state.loopFrame = requestAnimationFrame(tick);

            state.fpsFrames += 1;
            const sampleElapsed = now - state.fpsLastSample;
            if (sampleElapsed >= 500) {
                const fps = Math.round(state.fpsFrames * 1000 / sampleElapsed);
                if (dom.subwayFpsValue && fps !== state.fpsShown) {
                    dom.subwayFpsValue.textContent = String(fps);
                    state.fpsShown = fps;
                }
                state.fpsFrames = 0;
                state.fpsLastSample = now;
            }

            if (!state.running) return;
            if (now - state.lastTimerPaint < timerPaintInterval()) return;
            state.lastTimerPaint = now;
            state.elapsed = getRunningElapsed(now);
            renderTimerDisplay(state.elapsed);
        };

        state.loopFrame = requestAnimationFrame(tick);
    }

    function setBootProgress(progress, stage, status) {
        const percent = Math.max(0, Math.min(100, Math.round(progress * 100)));
        if (dom.subwayBootPercent) dom.subwayBootPercent.textContent = String(percent);
        if (dom.subwayBootTrackValue) dom.subwayBootTrackValue.style.width = `${percent}%`;
        if (stage && dom.subwayBootStage) dom.subwayBootStage.textContent = stage;
        if (status && dom.subwayBootStatus) dom.subwayBootStatus.textContent = status;
    }

    function bootLog(message) {
        if (!dom.subwayBootConsole || !message) return;
        const line = document.createElement('span');
        line.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
        dom.subwayBootConsole.appendChild(line);
        dom.subwayBootConsole.scrollTop = dom.subwayBootConsole.scrollHeight;
    }

    function loadScript(url) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = url;
            script.async = true;
            script.crossOrigin = 'anonymous';
            script.onload = () => resolve(script);
            script.onerror = () => reject(new Error(`Script non disponibile: ${url}`));
            document.head.appendChild(script);
        });
    }

    // URL della configurazione della build sul nostro server.
    function buildConfigUrl(map, mode) {
        const variant = mode === 'original' ? 'alt' : mode;
        return `${buildsBase}${map.slug}/${map.slug}.${variant}.json`;
    }

    // La build c'e' sul server? Una richiesta leggera sul .json, con timeout.
    // Ritorna lo status HTTP (0 = rete o timeout).
    async function buildStatus(url) {
        const controller = typeof AbortController === 'function' ? new AbortController() : null;
        const timer = setTimeout(() => controller?.abort(), 6000);
        try {
            const response = await fetch(url, { cache: 'no-cache', signal: controller?.signal });
            return response.status;
        } catch (_) {
            return 0;
        } finally {
            clearTimeout(timer);
        }
    }

    /*
     * Il loader Unity 2019 (versione 4399), quando un file arriva senza
     * Content-Length, cerca "/Build/" nell'URL per ricavare il nome del file e
     * va in errore a ogni evento di download, anche su quello finale: cosi' la
     * mappa non partiva mai. Succede con le nostre build, perche' Cloudflare
     * le ricomprime al volo e toglie Content-Length. Qui l'evento viene
     * ripassato al loader come se la lunghezza fosse nota, usando la
     * dimensione decompressa del file dal catalogo.
     */
    function patchUnityLoader(map) {
        const progress = window.UnityLoader?.Progress;
        if (progress && !progress.__cripsumPatched) {
            const originalUpdate = progress.update;
            const isFirefox = navigator.userAgent.toLowerCase().includes('firefox');
            progress.update = function (instance, id, event) {
                let e = event;
                if (e && typeof e === 'object' && !e.lengthComputable) {
                    const url = String(e.target?.responseURL || '');
                    const file = url.split('?')[0].split('/').pop();
                    const sizes = buildSizes[state.activeMap?.slug] || {};
                    // Firefox conta i byte compressi, Chrome quelli decompressi.
                    let total = (sizes[file] || 0) * (isFirefox ? 0.4 : 1);
                    if (e.type === 'load' || !total) total = Math.max(e.loaded || 0, 1);
                    e = { type: e.type, target: e.target, lengthComputable: true, loaded: Math.min(e.loaded || 0, total), total };
                }
                try {
                    return originalUpdate.call(this, instance, id, e);
                } catch (error) {
                    diag('loader_progress_error', { error: String(error?.message || error).slice(0, 80) });
                    return undefined;
                }
            };
            Object.defineProperty(progress, '__cripsumPatched', { value: true });
        }
        // Il gestore d'errore del loader 4399 chiama questa funzione della
        // pagina che lo ospitava: senza, ogni errore ne produceva un secondo.
        if (typeof window.showUnitywebNoSupport !== 'function') {
            window.showUnitywebNoSupport = () => diag('unityweb_no_support', { map: map.slug });
        }
    }

    function formatMb(bytes) {
        return `${(bytes / 1048576).toFixed(1)} MB`;
    }

    async function launchMap(map, mode = 'original') {
        if (state.loading || state.activeMap) return;
        if (mode !== 'original' && !map.training) mode = 'original';
        state.loading = true;
        state.activeMap = map;
        state.runMode = mode;
        lobby.last = { slug: map.slug, mode };
        saveLobbyState();
        resetTimer();
        dom.subwayBootConsole?.replaceChildren();
        dom.subwayBootSplash?.classList.remove('hidden');
        if (dom.subwayBootMap) dom.subwayBootMap.textContent = map.name;
        if (dom.subwayBootMode) dom.subwayBootMode.textContent = modeLabel(mode);
        if (dom.subwayBootBytes) dom.subwayBootBytes.textContent = '';
        setBootProgress(0.02, t(`Caricamento di ${map.name}`, `Loading ${map.name}`), t('Preparazione del player...', 'Preparing the player...'));
        diag('launch', { map: map.slug, mode });

        try {
            let configUrl = buildConfigUrl(map, mode);
            let runtimePkg = RUNTIME_PKG;
            let loader = RUNTIME_LOADER;
            let bootstrap = RUNTIME_BOOTSTRAP;
            const status = await buildStatus(configUrl);
            if (status !== 200) {
                if (status === 404 && mode !== 'original') {
                    throw new Error(t(
                        `L'allenamento su ${map.name} non è ancora disponibile. Prova la modalità Classifica o un'altra mappa.`,
                        `Training on ${map.name} is not available yet. Try Ranked mode or another map.`
                    ));
                }
                if (mode !== 'original' || !map.legacy) {
                    throw new Error(status === 404
                        ? t(`${map.name} non è ancora disponibile. Riprova più tardi.`, `${map.name} is not available yet. Try again later.`)
                        : t('Il server delle build non risponde. Riprova tra poco.', 'The build server is not responding. Try again shortly.'));
                }
                // Vecchia build su jsDelivr: piu' pesante, ma sempre raggiungibile.
                configUrl = packageUrl(map.legacy.pkg, map.legacy.build);
                runtimePkg = map.legacy.pkg;
                loader = map.legacy.loader;
                bootstrap = map.legacy.bootstrap;
                bootLog(t('Server delle build non raggiungibile: uso la copia di riserva.', 'Build server unreachable: using the backup copy.'));
                diag('launch_fallback', { map: map.slug, status });
            }
            bootLog(`${map.name} · ${modeLabel(mode)}`);

            createPokiCompatibilityLayer();
            installAudioDetector();
            setBootProgress(0.05, t('Avvio del motore', 'Starting the engine'), t('Caricamento del runtime Unity...', 'Loading the Unity runtime...'));
            if (bootstrap) {
                await loadScript(packageUrl(runtimePkg, bootstrap));
                installAudioDetector();
            }
            await loadScript(packageUrl(runtimePkg, loader));
            if (!window.UnityLoader?.instantiate) throw new Error('UnityLoader non inizializzato');
            patchUnityLoader(map);

            if (!window.CripsumSubwayProfile) throw new Error('Profilo Subway non disponibile');
            const preparedProfile = await window.CripsumSubwayProfile.prepare();
            if (!preparedProfile.ok) throw new Error('Impossibile preparare il profilo Subway');
            bootLog(t(`Profilo completo preparato (${preparedProfile.files} file)`, `Complete profile prepared (${preparedProfile.files} files)`));

            document.body.classList.add('subway-fullscreen-active');
            dom.subwayLobby.style.display = 'none';
            dom.subwayGameArea.style.display = 'block';
            if (dom.subwayModeBadge) {
                dom.subwayModeBadge.hidden = mode === 'original';
                dom.subwayModeBadge.textContent = modeLabel(mode);
            }
            requestAnimationFrame(restoreOverlayPositions);
            startGameLoop();
            dom.subwayGameContainer.replaceChildren();

            const moduleConfig = {
                mainLoopTimingMode: frameTimingSettings().mode,
                mainLoopTimingValue: frameTimingSettings().value,
                devicePixelRatio: renderScaleFactor(),
                preRun: [function () {
                    const mod = this || window.Module || state.unity?.Module || window.unityGame?.Module;
                    const injected = window.CripsumSubwayProfile?.injectIntoUnityFS(mod);
                    if (injected) {
                        console.log('[Subway Portal] Profilo completo iniettato nel filesystem Unity');
                    }
                }],
                onRuntimeInitialized() { onUnityReady(); }
            };

            // Il loader riporta solo una frazione: i MB si stimano dal peso
            // compresso della mappa, la velocita' dalla media dall'inizio.
            const totalBytes = (map.mb || 20) * 1048576;
            const startedAt = performance.now();
            state.unity = window.UnityLoader.instantiate('subwayGameContainer', configUrl, {
                onProgress(instance, progress) {
                    const loaded = totalBytes * Math.min(1, progress);
                    const seconds = Math.max(0.5, (performance.now() - startedAt) / 1000);
                    if (dom.subwayBootBytes) {
                        dom.subwayBootBytes.textContent = progress < 1
                            ? `${formatMb(loaded)} / ${formatMb(totalBytes)} · ${formatMb(loaded / seconds)}/s`
                            : formatMb(totalBytes);
                    }
                    setBootProgress(
                        0.08 + progress * 0.9,
                        t(`Caricamento di ${map.name}`, `Loading ${map.name}`),
                        progress < 1
                            ? t('Download della mappa...', 'Downloading the map...')
                            : t('Avvio della scena...', 'Starting the scene...')
                    );
                },
                Module: moduleConfig
            });
            window.unityGame = state.unity;
        } catch (error) {
            showLaunchError(error);
        }
    }

    let readyHandled = false;
    function onUnityReady() {
        if (readyHandled) return;
        readyHandled = true;
        state.loading = false;
        try {
            window.CripsumSubwayProfile?.injectIntoUnityFS(
                state.unity?.Module || window.unityGame?.Module || window.Module
            );
        } catch (_) {}
        setBootProgress(1, t('Gioco pronto', 'Game ready'), t('Clicca o premi SPAZIO nel gioco per iniziare.', 'Click or press SPACE in the game to begin.'));
        bootLog(t('Canvas WebGL attivo', 'WebGL canvas active'));
        bootLog(state.vsync
            ? t('VSync adattivo al refresh del monitor', 'VSync matched to monitor refresh')
            : t(`Limite FPS attivo: ${state.fpsLimit}`, `FPS limit enabled: ${state.fpsLimit}`));
        const scaleLabel = state.renderScale === 'native' ? 'native' : `${state.renderScale}%`;
        bootLog(`Render scale: ${scaleLabel}${state.perfMode ? ' (performance)' : ''}`);
        applyFrameTiming();
        applyRenderScale();
        installAudioDetector();
        setTimeout(() => {
            dom.subwayBootSplash?.classList.add('hidden');
            dom.subwayStartHint?.classList.add('is-visible');
            const canvas = dom.subwayGameContainer?.querySelector('canvas');
            if (canvas) {
                canvas.tabIndex = 0;
                canvas.focus({ preventScroll: true });
            }
        }, 350);
    }

    function showLaunchError(error) {
        state.loading = false;
        console.error('[Subway Portal]', error);
        const message = error?.message && !/UnityLoader|Profilo/.test(error.message)
            ? error.message
            : t('La mappa non si è avviata correttamente. Riprova.', 'The map did not start correctly. Please try again.');
        setBootProgress(0, t('Caricamento non riuscito', 'Loading failed'), message);
        bootLog(error?.message || String(error));
        document.querySelector('.sw-boot-details')?.setAttribute('open', '');
        diag('launch_error', { map: state.activeMap?.slug, error: String(error?.message || error).slice(0, 120) });
        if (dom.cancelSubwayLoad) dom.cancelSubwayLoad.textContent = t('Torna alle mappe', 'Back to maps');
    }

    let leavingToLobby = false;
    async function returnToLobby() {
        if (state.activeMap) {
            if (leavingToLobby) return;
            leavingToLobby = true;
            // Il reload annullava il salvataggio in volo: prima si chiude la
            // run e si aspetta la coda, al massimo 1,5 s (quello che resta
            // parte comunque con keepalive/sendBeacon su pagehide).
            finalizeRun('exit_lobby');
            const saved = await waitForScores(1500);
            diag('exit_lobby', { queueEmpty: saved });
            persistDiag();
            window.location.reload();
            return;
        }
        dom.subwayBootSplash?.classList.add('hidden');
    }

    function initialize() {
        cacheDom();
        if (dom.subwaySettingsModal && dom.subwaySettingsModal.parentElement !== document.body) {
            document.body.appendChild(dom.subwaySettingsModal);
        }
        loadCatalog();
        loadSettings();
        installWebglPerformancePatch();
        installFrameLimiter();
        applyPerformanceMode();
        bindLobby();
        buildMapGrid();
        bindSettings();
        bindGameInput();
        bindDraggableWidgets();
        updateBindingUi();
        setChallengeStatus(state.challenge ? 'ready' : 'inactive');
        filterKnownUnityNoise();
        installWasmMimeFallback();
        createPokiCompatibilityLayer();
        installAudioMasterVolume();
        installAudioDetector();
        bindAudioWidgets();
        applyAudioVolume(state.audioVolume, state.audioMuted);
        // Salvataggi rimasti in sospeso da una visita precedente (reload,
        // scheda chiusa, rete caduta): si ritentano subito.
        loadPendingScores();
        diag('page_load', { lang: document.documentElement.lang || '' });
        flushScores();
        window.addEventListener('pagehide', () => flushScoresOnExit('page_exit'));
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') persistDiag();
        });
        fetchLeaderboard();
        dom.subwayLeaderboardRefresh?.addEventListener('click', () => {
            const icon = dom.subwayLeaderboardRefresh.querySelector('i');
            if (icon) icon.classList.add('fa-spin');
            fetchLeaderboard().finally(() => {
                setTimeout(() => { if (icon) icon.classList.remove('fa-spin'); }, 500);
            });
        });
        dom.cancelSubwayLoad?.addEventListener('click', returnToLobby);
        dom.exitGameBtn?.addEventListener('click', returnToLobby);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();
