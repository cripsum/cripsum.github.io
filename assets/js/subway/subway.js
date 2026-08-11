/* Cripsum Subway Surfers native Unity launcher */

(function () {
    'use strict';

    const CDN = 'https://cdn.jsdelivr.net/npm/';
    const maps = [
        { slug: 'london', name: 'London', region: 'Europe', pkg: 'subwaylondon@1.0.0', build: 'Build/London.json', loader: 'UnityLoader.2019.2.js', bootstrap: '4399.js', preview: 'screenshots/1.jpg' },
        { slug: 'zurich', name: 'Zurich', region: 'Europe', pkg: 'subwayzurich@1.1.3', build: 'Build/ZurichNewPrivacy.json', loader: 'UnityLoader.2019.2.js', bootstrap: '4399.js', preview: 'screenshots/1.jpg' },
        { slug: 'beijing', name: 'Beijing', region: 'Asia', pkg: 'subwaybeijing@1.0.0', build: 'Build/Beijing_2.json', loader: 'loaders/v2/unity/static/UnityLoader.2019.2.js', bootstrap: '4399.js', preview: 'screenshots/1.jpg' },
        { slug: 'berlin', name: 'Berlin', region: 'Europe', pkg: 'subwayberlin@1.0.0', build: 'Build/Berlin.json', loader: 'UnityLoader.2019.2.js', bootstrap: '4399.js', preview: 'screenshots/1.jpg' },
        { slug: 'havana', name: 'Havana', region: 'America', pkg: 'subwayhavana@2.0.0', build: 'Build/Havana_4.json', loader: 'loaders/v2/unity/static/UnityLoader.2019.2.js', bootstrap: '4399.js', preview: 'screenshots/1.jpg' },
        { slug: 'houston', name: 'Houston', region: 'America', pkg: 'subwayhouston@1.1.0', build: 'Build/Houston/Houston.json', loader: 'UnityLoader.2019.2.js', bootstrap: '4399.sf.js', preview: 'screenshots/1.jpg' },
        { slug: 'iceland', name: 'Iceland', region: 'Europe', pkg: 'subwayiceland@1.0.0', build: 'Build/Iceland/Iceland_1.json', loader: 'UnityLoader.2019.2.js', bootstrap: '4399.js', preview: 'icy.webp' },
        { slug: 'mexico', name: 'Mexico', region: 'America', pkg: 'subwaymexico@1.0.0', build: 'Build/Mexico/Mexico3.json', loader: 'UnityLoader.2019.2.js', bootstrap: 'subwaySurf14.08.js', preview: 'img.webp' },
        { slug: 'miami', name: 'Miami', region: 'America', pkg: 'subwaymiami@1.0.0', build: 'Build/Miami/subway_miami_v1.json', loader: 'UnityLoader.2019.2.js', preview: 'screenshots/1.jpg' },
        { slug: 'monaco', name: 'Monaco', region: 'Europe', pkg: 'subwaymonaco@1.1.0', build: 'Build/Monaco.json', loader: 'UnityLoader.2019.2.js', bootstrap: '4399.js', preview: 'screenshots/1.jpg' },
        { slug: 'neworleans', name: 'New Orleans', region: 'America', pkg: 'subwayneworleans@1.0.0', build: 'Build/NewOrleans.json', loader: 'UnityLoader.2019.2.js', bootstrap: '4399.js', preview: 'screenshots/1.jpg' },
        { slug: 'sanfrancisco', name: 'San Francisco', region: 'America', pkg: 'subwaysanfrancisco@1.0.0', build: 'Build/SanFrancisco.json', loader: 'UnityLoader.2019.2.js', bootstrap: '4399.js', preview: 'screenshots/1.jpg' },
        { slug: 'saintpetersburg', name: 'Saint Petersburg', region: 'Europe', pkg: 'subwaystpetersburg@1.0.0', build: 'Build/StPetersburg.json', loader: 'loaders/v2/unity/static/UnityLoader.2019.2.js', bootstrap: '4399.js', preview: 'screenshots/1.jpg' },
        { slug: 'winterholiday', name: 'Winter Holiday', region: 'Europe', pkg: 'subwaywinterholiday@1.0.0', build: 'Build/WinterHoliday/WinterHoliday.json', loader: 'UnityLoader.2019.2.js', bootstrap: 'subwaySurf14.08.js', preview: null }
    ];

    const storageKey = 'cripsum-subway-settings-v2';
    const defaultBindings = { jump: 'KeyW', duck: 'KeyS', left: 'KeyA', right: 'KeyD' };
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
        failed: false,
        runArmed: false,
        startedAt: 0,
        elapsed: 0,
        timerFrame: 0,
        unity: null,
        activeMap: null,
        bindings: Object.assign({}, defaultBindings),
        challenge: true,
        autoPause: true,
        lastJumpInput: 0
    };

    const dom = {};
    const isItalian = () => String(document.documentElement.lang || '').toLowerCase().startsWith('it');
    const t = (it, en) => isItalian() ? it : en;
    const packageUrl = (map, path) => `${CDN}${map.pkg}/${path}`;

    function cacheDom() {
        [
            'subwayPortal', 'subwayLobby', 'subwayGameArea', 'subwayGameContainer',
            'subwayBootSplash', 'subwayBootStage', 'subwayBootStatus', 'subwayBootPercent',
            'subwayBootTrackValue', 'subwayBootConsole', 'cancelSubwayLoad', 'exitGameBtn',
            'subwayTimerDisplay', 'subwayStatusBadge', 'subwayStartHint',
            'toggleNoCoinChallenge', 'toggleAutoPause', 'subwaySettingsModal',
            'hudWidgetSettingsBtn', 'closeSettingsModal'
        ].forEach(id => { dom[id] = document.getElementById(id); });
    }

    function loadSettings() {
        try {
            const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
            state.bindings = Object.assign({}, defaultBindings, saved.bindings || {});
            state.challenge = saved.challenge !== false;
            state.autoPause = saved.autoPause !== false;
        } catch (_) {
            state.bindings = Object.assign({}, defaultBindings);
        }
    }

    function saveSettings() {
        localStorage.setItem(storageKey, JSON.stringify({
            bindings: state.bindings,
            challenge: state.challenge,
            autoPause: state.autoPause
        }));
    }

    function regionName(region) {
        if (!isItalian()) return region;
        return region === 'Europe' ? 'Europa' : region === 'America' ? 'America' : 'Asia';
    }

    function mapDescription(map) {
        return t(
            `Edizione ${map.name}, caricata direttamente nel player Cripsum.`,
            `${map.name} edition, loaded directly in the Cripsum player.`
        );
    }

    function previewUrl(map) {
        return map.preview ? packageUrl(map, map.preview) : '/img/Susremaster.png';
    }

    function createCard(map, index) {
        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'subway-map-card';
        card.dataset.map = map.slug;
        card.setAttribute('aria-label', t(`Gioca a ${map.name}`, `Play ${map.name}`));

        const background = document.createElement('div');
        background.className = 'subway-map-bg';
        background.style.backgroundImage = `url("${previewUrl(map)}")`;

        const content = document.createElement('div');
        content.className = 'subway-map-content';
        const tag = document.createElement('span');
        tag.className = `subway-map-tag tier-${(index % 3) + 1}`;
        tag.textContent = regionName(map.region);
        const heading = document.createElement('h3');
        heading.textContent = map.name;
        const description = document.createElement('p');
        description.textContent = mapDescription(map);

        content.append(tag, heading, description);
        card.append(background, content);
        card.addEventListener('click', () => launchMap(map));
        return card;
    }

    function buildMapGrid() {
        const grid = dom.subwayLobby && dom.subwayLobby.querySelector('.subway-grid');
        if (!grid) return;
        grid.replaceChildren(...maps.map(createCard));
        grid.hidden = false;
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

    function bindSettings() {
        if (dom.toggleNoCoinChallenge) {
            dom.toggleNoCoinChallenge.checked = state.challenge;
            dom.toggleNoCoinChallenge.addEventListener('change', () => {
                state.challenge = dom.toggleNoCoinChallenge.checked;
                saveSettings();
                setChallengeStatus(state.challenge ? 'ready' : 'inactive');
            });
        }
        if (dom.toggleAutoPause) {
            dom.toggleAutoPause.checked = state.autoPause;
            dom.toggleAutoPause.addEventListener('change', () => {
                state.autoPause = dom.toggleAutoPause.checked;
                saveSettings();
            });
        }

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
            const action = waitingButton.dataset.keybind;
            state.bindings[action] = event.code;
            document.querySelectorAll(`[data-keybind="${action}"]`).forEach(item => item.classList.remove('waiting'));
            waitingButton = null;
            saveSettings();
            updateBindingUi();
        }, true);

        dom.hudWidgetSettingsBtn?.addEventListener('click', event => {
            if (event.defaultPrevented) return;
            dom.subwaySettingsModal?.classList.add('show');
        });
        dom.closeSettingsModal?.addEventListener('click', () => dom.subwaySettingsModal?.classList.remove('show'));
        dom.subwaySettingsModal?.addEventListener('click', event => {
            if (event.target === dom.subwaySettingsModal) dom.subwaySettingsModal.classList.remove('show');
        });
    }

    function createPokiCompatibilityLayer() {
        const resolved = () => Promise.resolve();
        window.PokiSDK = Object.assign(window.PokiSDK || {}, {
            init: resolved,
            initWithVideoHB: resolved,
            commercialBreak: resolved,
            rewardedBreak: () => Promise.resolve(false),
            customEvent(...values) { inspectGameEvent(values); },
            displayAd() {},
            destroyAd() {},
            gameLoadingStart() {},
            gameLoadingProgress() {},
            gameLoadingFinished() {},
            gameInteractive() {},
            gameplayStart() { startTimer('gameplayStart'); },
            gameplayStop() {},
            roundStart() { startTimer('roundStart'); },
            roundEnd() {},
            setDebug() {},
            happyTime() {},
            setPlayerAge() {},
            togglePlayerAdvertisingConsent() {},
            toggleNonPersonalized() {},
            setConsentString() {},
            logError() {}
        });
        window.pokiReady = true;
        window.pokiAdBlock = false;
        window.initPokiBridge = bridgeName => {
            window.pokiBridge = bridgeName;
            if (window.unityGame?.SendMessage) window.unityGame.SendMessage(bridgeName, 'ready');
            window.commercialBreak = () => window.PokiSDK.commercialBreak().then(() => {
                window.unityGame?.SendMessage?.(bridgeName, 'commercialBreakCompleted');
            });
            window.rewardedBreak = () => window.PokiSDK.rewardedBreak().then(rewarded => {
                window.unityGame?.SendMessage?.(bridgeName, 'rewardedBreakCompleted', String(rewarded));
            });
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

    function installAudioDetector() {
        const Source = window.AudioBufferSourceNode;
        if (!Source || Source.prototype.__cripsumSubwayHooked) return;
        const originalStart = Source.prototype.start;
        Source.prototype.start = function (...args) {
            try { classifyAudio(this.buffer); } catch (_) { /* game audio must never break */ }
            return originalStart.apply(this, args);
        };
        Object.defineProperty(Source.prototype, '__cripsumSubwayHooked', { value: true });
    }

    function inspectGameEvent(values) {
        let serialized = '';
        try {
            serialized = values.map(value => typeof value === 'string' ? value : JSON.stringify(value)).join(' ');
        } catch (_) {
            serialized = values.map(String).join(' ');
        }
        if (/\b(coin[_ -]?(collected|pickup)|collect(ed)?[_ -]?coin|pickup[_ -]?coin)\b/i.test(serialized)) {
            failChallenge();
        }
    }

    function classifyAudio(buffer) {
        if (!buffer || !state.challenge) return;
        const duration = Number(buffer.duration || 0);
        const looksLikeStart = Math.abs(duration - 3.465) < 0.14;
        const looksLikeCoin = duration >= 0.515 && duration < 0.595;
        const looksLikeJump = duration >= 0.595 && duration < 0.67;

        if (looksLikeStart && !state.running) {
            startTimer('audio');
        } else if (looksLikeJump) {
            state.lastJumpInput = performance.now();
        } else if (looksLikeCoin && state.running && performance.now() - state.lastJumpInput > 170) {
            failChallenge();
        }
    }

    function setChallengeStatus(kind) {
        if (!dom.subwayStatusBadge) return;
        const labels = {
            ready: t('Pronta', 'Ready'),
            running: t('In corsa', 'Running'),
            failed: t('Moneta!', 'Coin!'),
            inactive: t('Disattiva', 'Inactive')
        };
        dom.subwayStatusBadge.className = `subway-status-badge ${kind === 'running' || kind === 'ready' ? 'active' : kind}`;
        dom.subwayStatusBadge.textContent = labels[kind] || kind;
    }

    function resetTimer() {
        cancelAnimationFrame(state.timerFrame);
        state.running = false;
        state.failed = false;
        state.runArmed = false;
        state.startedAt = 0;
        state.elapsed = 0;
        if (dom.subwayTimerDisplay) dom.subwayTimerDisplay.textContent = '00:00.000';
        setChallengeStatus(state.challenge ? 'ready' : 'inactive');
    }

    function startTimer(source) {
        if (!state.challenge || state.running) return;
        if (state.failed || state.elapsed > 0) resetTimer();
        state.running = true;
        state.runArmed = false;
        state.startedAt = performance.now();
        setChallengeStatus('running');
        dom.subwayStartHint?.classList.remove('is-visible');
        bootLog(t(`Run rilevata (${source})`, `Run detected (${source})`));
        updateTimer();
    }

    function updateTimer(now = performance.now()) {
        if (!state.running) return;
        state.elapsed = now - state.startedAt;
        if (dom.subwayTimerDisplay) dom.subwayTimerDisplay.textContent = formatTime(state.elapsed);
        state.timerFrame = requestAnimationFrame(updateTimer);
    }

    function formatTime(milliseconds) {
        const total = Math.max(0, Math.floor(milliseconds));
        const minutes = Math.floor(total / 60000);
        const seconds = Math.floor((total % 60000) / 1000);
        const ms = total % 1000;
        return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}.${String(ms).padStart(3, '0')}`;
    }

    function failChallenge() {
        if (!state.running || state.failed) return;
        state.elapsed = performance.now() - state.startedAt;
        state.running = false;
        state.failed = true;
        cancelAnimationFrame(state.timerFrame);
        if (dom.subwayTimerDisplay) dom.subwayTimerDisplay.textContent = formatTime(state.elapsed);
        setChallengeStatus('failed');
        if (state.autoPause) dispatchUnityKey('Escape', 'keydown', true);
    }

    function bindGameInput() {
        const nativeCodes = new Set(['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight']);
        const nativeActions = { ArrowUp: 'jump', ArrowDown: 'duck', ArrowLeft: 'left', ArrowRight: 'right' };
        window.addEventListener('keydown', event => {
            if (!state.activeMap || event.repeat || event.target?.closest?.('.subway-settings-modal')) return;
            if (event.code === 'Space') state.runArmed = true;

            const action = nativeActions[event.code]
                || Object.keys(state.bindings).find(key => state.bindings[key] === event.code);
            if (!action) return;
            flashHudKey(action, true);
            if (state.runArmed && !state.running && !state.failed) startTimer('input');
            if (nativeCodes.has(event.code)) return;
            event.preventDefault();
            if (action === 'jump') state.lastJumpInput = performance.now();
            if (!remapNativeKeyboardEvent(event, unityCodes[action])) {
                event.stopImmediatePropagation();
                dispatchUnityKey(unityCodes[action], 'keydown');
            }
        }, true);
        window.addEventListener('keyup', event => {
            if (!state.activeMap) return;
            const action = nativeActions[event.code]
                || Object.keys(state.bindings).find(key => state.bindings[key] === event.code);
            if (!action) return;
            flashHudKey(action, false);
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

    function flashHudKey(action, pressed) {
        document.getElementById(`hudKey-${action}`)?.classList.toggle('pressed', pressed);
    }

    function bindDraggableWidgets() {
        document.querySelectorAll('.subway-hud-widget').forEach(widget => {
            const handle = widget.querySelector('.widget-handle') || widget;
            let drag = null;
            handle.addEventListener('pointerdown', event => {
                if (event.button !== 0) return;
                const rect = widget.getBoundingClientRect();
                drag = { x: event.clientX, y: event.clientY, left: rect.left, top: rect.top };
                widget.style.left = `${rect.left}px`;
                widget.style.top = `${rect.top}px`;
                widget.style.right = 'auto';
                widget.style.bottom = 'auto';
                widget.classList.add('is-dragging');
                handle.setPointerCapture(event.pointerId);
                event.preventDefault();
            });
            handle.addEventListener('pointermove', event => {
                if (!drag) return;
                const maxX = Math.max(0, window.innerWidth - widget.offsetWidth);
                const maxY = Math.max(0, window.innerHeight - widget.offsetHeight);
                widget.style.left = `${Math.min(maxX, Math.max(0, drag.left + event.clientX - drag.x))}px`;
                widget.style.top = `${Math.min(maxY, Math.max(0, drag.top + event.clientY - drag.y))}px`;
            });
            const endDrag = () => {
                drag = null;
                widget.classList.remove('is-dragging');
            };
            handle.addEventListener('pointerup', endDrag);
            handle.addEventListener('pointercancel', endDrag);
        });
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

    async function launchMap(map) {
        if (state.loading || state.activeMap) return;
        state.loading = true;
        state.activeMap = map;
        resetTimer();
        dom.subwayBootConsole?.replaceChildren();
        dom.subwayBootSplash?.classList.remove('hidden');
        setBootProgress(0.02, t(`Caricamento ${map.name}`, `Loading ${map.name}`), t('Preparazione del player Unity...', 'Preparing the Unity player...'));
        bootLog(t(`Build selezionata: ${map.pkg}`, `Selected build: ${map.pkg}`));

        try {
            createPokiCompatibilityLayer();
            installAudioDetector();
            bootLog(t('Bridge No-Coin installato', 'No-Coin bridge installed'));
            setBootProgress(0.06, t('Avvio motore', 'Starting engine'), t('Caricamento del runtime Unity 2019...', 'Loading the Unity 2019 runtime...'));
            if (map.bootstrap) {
                bootLog(t('Caricamento del modulo di compatibilità...', 'Loading the compatibility module...'));
                await loadScript(packageUrl(map, map.bootstrap));
            }
            await loadScript(packageUrl(map, map.loader));
            if (!window.UnityLoader?.instantiate) throw new Error('UnityLoader non inizializzato');

            document.body.classList.add('subway-fullscreen-active');
            dom.subwayLobby.style.display = 'none';
            dom.subwayGameArea.style.display = 'block';
            dom.subwayGameContainer.replaceChildren();
            bootLog(t('Runtime pronto; download degli asset...', 'Runtime ready; downloading assets...'));

            state.unity = window.UnityLoader.instantiate(
                'subwayGameContainer',
                packageUrl(map, map.build),
                {
                    onProgress(instance, progress) {
                        setBootProgress(
                            0.08 + progress * 0.90,
                            t(`Caricamento ${map.name}`, `Loading ${map.name}`),
                            progress < 1
                                ? t('Download e decompressione degli asset di gioco...', 'Downloading and decompressing game assets...')
                                : t('Inizializzazione della scena...', 'Initializing the scene...')
                        );
                    },
                    Module: {
                        onRuntimeInitialized() { onUnityReady(); }
                    }
                }
            );
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
        setBootProgress(1, t('Gioco pronto', 'Game ready'), t('Clicca o premi SPAZIO nel gioco per iniziare.', 'Click or press SPACE in the game to begin.'));
        bootLog(t('Canvas WebGL attivo', 'WebGL canvas active'));
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
        setBootProgress(0, t('Caricamento non riuscito', 'Loading failed'), t('La build non ha risposto correttamente. Riprova.', 'The build did not respond correctly. Please try again.'));
        bootLog(error?.message || String(error));
        if (dom.cancelSubwayLoad) dom.cancelSubwayLoad.textContent = t('Torna alle mappe', 'Back to maps');
    }

    function returnToLobby() {
        if (state.activeMap) {
            window.location.reload();
            return;
        }
        dom.subwayBootSplash?.classList.add('hidden');
    }

    function initialize() {
        cacheDom();
        loadSettings();
        buildMapGrid();
        bindSettings();
        bindGameInput();
        bindDraggableWidgets();
        updateBindingUi();
        setChallengeStatus(state.challenge ? 'ready' : 'inactive');
        filterKnownUnityNoise();
        installWasmMimeFallback();
        createPokiCompatibilityLayer();
        installAudioDetector();
        dom.cancelSubwayLoad?.addEventListener('click', returnToLobby);
        dom.exitGameBtn?.addEventListener('click', returnToLobby);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();
