/* Subway Surfers WebGL Mod & Challenge Engine - Cripsum™ Edition */

(function () {
    'use strict';

    // -------------------------------------------------------------
    // Tavvkkj & Ashuni Anti-Piracy / Poki Site-Lock Bypass Engine
    // -------------------------------------------------------------
    window.__blockUnityExternalEval = function (s) {
        const u = String(s || "");
        return (
            u.includes("poki.com/sitelock") ||
            u.includes("po.ki/sitelockredirect") ||
            u.includes("games.poki.com/458768/crossyroad") ||
            u.includes("aHR0cDovL3BvLmtpL3NpdGVsb2NrcmVkaXJlY3Q")
        );
    };

    window.__blockUnityExternalOpenURL = function (s) {
        return window.__blockUnityExternalEval(s);
    };

    if (!window.__unityOpenPatchInstalled && typeof window.open === "function") {
        const origOpen = window.open;
        window.open = function (u, ...d) {
            if (window.__blockUnityExternalOpenURL && window.__blockUnityExternalOpenURL(u)) return null;
            return origOpen.call(window, u, ...d);
        };
        window.__unityOpenPatchInstalled = true;
    }

    // Poki Bridge & SDK Window Contract
    window.PokiBridge = window.PokiBridge || {};
    window.pokiReady = window.pokiReady || true;
    window.pokiAdBlock = window.pokiAdBlock || false;

    function sendUnityMessage(method, arg) {
        const objName = window.__pokiUnityObjectName || window.PokiBridge?.gameObjectName || window.pokiBridge || "PokiUnitySDK";
        const instance = state.unityInstance || window.unityInstance || window.__unityInstance || window.gameInstance;
        if (!objName || !instance || typeof instance.SendMessage !== "function") return false;
        try {
            if (arg === undefined) {
                instance.SendMessage(objName, method);
            } else {
                instance.SendMessage(objName, method, String(arg));
            }
            return true;
        } catch (e) {
            return false;
        }
    }

    function notifyPokiReady(attempt = 0) {
        if (sendUnityMessage("ready")) return;
        if (!window.__pokiUnityObjectName || attempt > 100) return;
        setTimeout(() => notifyPokiReady(attempt + 1), 50);
    }

    window.initPokiBridge = function (r) {
        window.__pokiUnityObjectName = r;
        window.PokiBridge = window.PokiBridge || {};
        window.PokiBridge.gameObjectName = r || "";
        window.pokiBridge = r;
        notifyPokiReady();
        return window.PokiSDK;
    };

    window.commercialBreak = function () {
        if (window.PokiSDK && typeof window.PokiSDK.commercialBreak === 'function') {
            return Promise.resolve(window.PokiSDK.commercialBreak()).then(() => {
                if (typeof window.PokiSDK.gameplayStart === 'function') window.PokiSDK.gameplayStart();
            });
        }
        return Promise.resolve();
    };

    window.rewardedBreak = function () {
        if (window.PokiSDK && typeof window.PokiSDK.rewardedBreak === 'function') {
            return Promise.resolve(window.PokiSDK.rewardedBreak()).then(r => {
                return r === true || (r && r.completed === true);
            });
        }
        return Promise.resolve(true);
    };

    // GitHub serves .unityweb as application/octet-stream. Instantiate from an
    // ArrayBuffer so Chromium does not reject compileStreaming on the MIME type.
    if (!window.__unityStreamingFallbackInstalled
        && window.WebAssembly
        && typeof window.WebAssembly.instantiateStreaming === 'function') {
        const nativeInstantiate = window.WebAssembly.instantiate;
        window.WebAssembly.instantiateStreaming = function (response, importObject) {
            return Promise.resolve(response).then(async resolvedResponse => {
                const bytes = await resolvedResponse.arrayBuffer();
                return nativeInstantiate.call(window.WebAssembly, bytes, importObject);
            });
        };
        window.__unityStreamingFallbackInstalled = true;
    }

    // Satisfy WASM framework and warning telemetry requirements
    window._JS_PokiSDK_gameLoadingProgress = window._JS_PokiSDK_gameLoadingProgress || function() {};
    window._JS_PokiSDK_gameLoadingFinished = window._JS_PokiSDK_gameLoadingFinished || function() {};
    window._JS_PokiSDK_gameInteractive = window._JS_PokiSDK_gameInteractive || function() {};

    window.my4399UnityModule = function (r) {
        if (typeof window.UnityModule === "function") return window.UnityModule(r);
        if (typeof window.Module === "function") return window.Module(r);
        if (typeof r === "function") return r(r);
        if (r) return r;
        return window.Module || {};
    };
    
    window.showUnitywebNoSupport = window.showUnitywebNoSupport || function (r) { console.warn(r); };
    try {
        window.parent.showUnitywebNoSupport = window.parent.showUnitywebNoSupport || function (r) { console.warn(r); };
    } catch (e) {}

    // WebGL Compatibility & Safe Parameter Access (tavvkkj e2 patch)
    (function initWebGlCompatibility() {
        if (window.__troUnityWebGlCompatibilityPatchInstalled) return;
        function emptyInt32Array() { return new Int32Array(0); }
        function patchContext(n) {
            const r = n == null ? void 0 : n.prototype;
            if (!r || r.__troUnityWebGlInternalformatPatch || typeof r.getInternalformatParameter !== "function") return;
            r.getInternalformatParameter = function() { return emptyInt32Array(); };
            r.__troUnityWebGlInternalformatPatch = true;
        }
        window.__troUnitySafeGetInternalformatParameter = function() { return emptyInt32Array(); };
        if (typeof window.WebGL2RenderingContext !== 'undefined') patchContext(window.WebGL2RenderingContext);
        if (typeof window.WebGLRenderingContext !== 'undefined') patchContext(window.WebGLRenderingContext);
        window.__troUnityWebGlCompatibilityPatchInstalled = true;
    })();

    // WASM table size mapping extracted directly from Subway Surfers builds telemetry
    const wasmTableSizes = [
        ["wasm_code_shared.unityweb", 126009],
        ["newyork.wasm.code.unityweb", 126009],
        ["wasm_code_shared_2.unityweb", 126009],
        ["wasm_code_shared_3.unityweb", 125739],
        ["berlin.wasm.code.unityweb", 126031],
        ["houston.wasm.code.unityweb", 126031],
        ["miami.wasm.code.unityweb", 108524],
        ["winterholiday.wasm.code.unityweb", 126009],
        ["zurich.wasm.code.4399.unityweb", 126009],
        ["zurich.wasm.code.unityweb", 125739]
    ];

    function getExpectedTableSize(wasmUrl) {
        if (!wasmUrl) return 0;
        const cleanUrl = String(wasmUrl).split('?')[0].split('#')[0];
        for (const [filename, size] of wasmTableSizes) {
            if (cleanUrl.endsWith(filename)) {
                return size;
            }
        }
        return 0;
    }

    let activeTablePatchSize = 0;
    const _OrigWasmTable = WebAssembly.Table;
    WebAssembly.Table = function (descriptor) {
        if (activeTablePatchSize && descriptor && typeof descriptor.initial === 'number') {
            descriptor = Object.assign({}, descriptor, {
                initial: Math.max(descriptor.initial, activeTablePatchSize),
                maximum: descriptor.maximum === undefined
                    ? undefined
                    : Math.max(descriptor.maximum, activeTablePatchSize)
            });
        }
        return new _OrigWasmTable(descriptor);
    };
    WebAssembly.Table.prototype = _OrigWasmTable.prototype;
    try { Object.setPrototypeOf(WebAssembly.Table, _OrigWasmTable); } catch (e) {}

    // 1. Game State & Settings
    const state = {
        activeMap: null,
        activeRepo: null,
        unityInstance: null,
        startTime: 0,
        elapsedTime: 0,
        isRunning: false,
        isFailed: false,
        noCoinChallenge: true,
        autoPauseOnCoin: true,
        firstInputStarted: false,
        unityReady: false,
        gameplayStartedAt: 0,
        lastAudioSignature: '',
        lastAudioAt: 0,
        lastJumpInputAt: 0,
        configBlobUrl: null,
        loadToken: 0,
        loading: false,
        customKeys: {
            jump: 'KeyW',
            duck: 'KeyS',
            left: 'KeyA',
            right: 'KeyD'
        },
        keyMapping: {
            'KeyW': 'ArrowUp',
            'KeyS': 'ArrowDown',
            'KeyA': 'ArrowLeft',
            'KeyD': 'ArrowRight'
        },
        defaultKeys: {
            jump: 'ArrowUp',
            duck: 'ArrowDown',
            left: 'ArrowLeft',
            right: 'ArrowRight'
        }
    };

    // Audio characteristics matching tavvkkj's thresholds
    const audioTargets = {
        start: {
            approxLengths: [167183, 166069],
            durationTolerance: 0.09,
            expectedDuration: 3.46458,
            lengthTolerance: 700
        },
        coin: {
            approxLengths: [27863],
            durationMin: 0.52,
            durationMax: 0.589,
            lengthTolerance: 900
        },
        jump: {
            approxLengths: [27863, 28675],
            durationMin: 0.59,
            durationMax: 0.625,
            lengthTolerance: 900
        }
    };

    // Map List & Repository Mappings
    const mapRepos = {
        // builds-1
        'beijing': 'therealoness-builds-1',
        'cairo': 'therealoness-builds-1',
        'houston': 'therealoness-builds-1',
        'iceland': 'therealoness-builds-1',
        'neworleans': 'therealoness-builds-1',
        'hongkong': 'therealoness-builds-1',
        'paris': 'therealoness-builds-1',
        'saintpetersburg': 'therealoness-builds-1',
        'zurich': 'therealoness-builds-1',
        // builds-2
        'barcelona': 'therealoness-builds-2',
        'london': 'therealoness-builds-2',
        'mexico': 'therealoness-builds-2',
        'miami': 'therealoness-builds-2',
        'newyork': 'therealoness-builds-2',
        'sanfrancisco': 'therealoness-builds-2',
        'tokyo': 'therealoness-builds-2',
        // builds-3
        'bangkok': 'therealoness-builds-3',
        'berlin': 'therealoness-builds-3',
        'buenosaires': 'therealoness-builds-3',
        'havana': 'therealoness-builds-3',
        'monaco': 'therealoness-builds-3',
        'moscow': 'therealoness-builds-3',
        'rio': 'therealoness-builds-3',
        'venice': 'therealoness-builds-3',
        'winterholiday': 'therealoness-builds-3'
    };

    const defaultPlayerPrefsBase64 = 'VW5pdHlQcmYAAAEAAAAQABlDbG91ZFNhdmVMYXN0VXNlZEtpbG9vVGFnABNIb3ZlcmJvYXJkaGFzU2NhbGVk/gEAAAAFU291bmT9AACAPxdhcHBsaWNhdGlvbkF1dGhlbnRpY2l0eQNzZXQ3c2F2ZWRhdGFfZmlsZW5vdGZvdW5kX29uY2UtRGF0YS5TYXZlLkludGVybmFsLkNsb3VkRGF0Yf4BAAAAN3NhdmVkYXRhX2ZpbGVub3Rmb3VuZF9vbmNlLURhdGEuU2F2ZS5JbnRlcm5hbC5Mb2NhbERhdGH+AQAAABJ1bml0eS5jbG91ZF91c2VyaWQgMjk5YTcwNGZlMWZkNTY2NGQ5OTc5M2JhNzVkZGZiNGMadW5pdHkucGxheWVyX3Nlc3Npb25fY291bnQBMRZ1bml0eS5wbGF5ZXJfc2Vzc2lvbmlkEzkwODQ1MTY0MTc1MDg3NjQ2Njc=';

    function bytesToHex(bytes) {
        return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    }

    function getSaveScope(mapSlug) {
        return `${window.location.origin}/__cripsum-subway-save__/${mapSlug}`;
    }

    function installSharedSaveHashPatch(mapSlug) {
        const cryptography = window.UnityLoader && window.UnityLoader.Cryptography;
        const currentMd5 = cryptography && cryptography.md5;
        if (typeof currentMd5 !== 'function' || typeof TextEncoder !== 'function') return false;
        if (currentMd5.__cripsumSavePatchInstalled) return true;

        const nativeMd5 = currentMd5.__cripsumNativeMd5 || currentMd5;
        const encoder = new TextEncoder();
        const decoder = typeof TextDecoder === 'function' ? new TextDecoder() : null;
        const encodedScope = encoder.encode(getSaveScope(mapSlug));

        function shouldUseSharedSave(input) {
            if (!decoder) return false;
            const bytes = input instanceof ArrayBuffer
                ? new Uint8Array(input)
                : ArrayBuffer.isView(input)
                    ? new Uint8Array(input.buffer, input.byteOffset, input.byteLength)
                    : Array.isArray(input)
                        ? new Uint8Array(input)
                    : null;
            if (!bytes || bytes.length === 0 || bytes.length > 2048) return false;

            let value;
            try { value = decoder.decode(bytes); } catch (error) { return false; }
            if (!/^https?:\/\//i.test(value)) return false;

            try {
                const url = new URL(value);
                const mapBuild = `/builds/${mapSlug}/`;
                const currentPage = url.origin === window.location.origin;
                return currentPage || url.pathname.includes(mapBuild);
            } catch (error) {
                return false;
            }
        }

        function patchedMd5(input) {
            return nativeMd5(shouldUseSharedSave(input) ? encodedScope : input);
        }

        patchedMd5.module = currentMd5.module;
        patchedMd5.__cripsumNativeMd5 = nativeMd5;
        patchedMd5.__cripsumSavePatchInstalled = true;
        cryptography.md5 = patchedMd5;
        return true;
    }

    function openIdbfsDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('/idbfs', 21);
            request.onupgradeneeded = () => {
                const database = request.result;
                const transaction = request.transaction;
                const store = database.objectStoreNames.contains('FILE_DATA')
                    ? transaction.objectStore('FILE_DATA')
                    : database.createObjectStore('FILE_DATA');
                if (!store.indexNames.contains('timestamp')) {
                    store.createIndex('timestamp', 'timestamp', { unique: false });
                }
            };
            request.onerror = () => reject(request.error || new Error('IndexedDB non disponibile.'));
            request.onblocked = () => reject(new Error('IndexedDB occupato da un’altra scheda.'));
            request.onsuccess = () => resolve(request.result);
        });
    }

    function decodeBase64Bytes(value) {
        const decoded = atob(value);
        const bytes = new Uint8Array(decoded.length);
        for (let index = 0; index < decoded.length; index += 1) {
            bytes[index] = decoded.charCodeAt(index);
        }
        return bytes;
    }

    async function installDefaultSave(mapSlug, config) {
        const cryptography = window.UnityLoader && window.UnityLoader.Cryptography;
        const md5 = cryptography && (cryptography.md5.__cripsumNativeMd5 || cryptography.md5);
        if (typeof md5 !== 'function' || !window.indexedDB) return false;

        const currentPageUrl = `${window.location.origin}${window.location.pathname}`;
        const pageDirectoryUrl = new URL('.', window.location.href).href;
        const targetInputs = new Set([
            getSaveScope(mapSlug),
            currentPageUrl,
            pageDirectoryUrl,
            pageDirectoryUrl.replace(/\/$/, ''),
            config && config.dataUrl,
            config && config.dataUrl && config.dataUrl.slice(0, config.dataUrl.lastIndexOf('/') + 1)
        ].filter(Boolean));
        const saveRoots = Array.from(targetInputs, input => {
            const saveHash = bytesToHex(md5(new TextEncoder().encode(input)));
            return `/idbfs/${saveHash}`;
        });
        const slotNames = ['local', 'cloud', 'local_old', 'cloud_old'];
        const response = await fetch('/assets/js/subway/runtime/default-save.bin', { cache: 'force-cache' });
        if (!response.ok) throw new Error(`Save predefinito non disponibile (HTTP ${response.status}).`);
        const saveBytes = new Uint8Array(await response.arrayBuffer());
        const playerPrefs = decodeBase64Bytes(defaultPlayerPrefsBase64);
        const database = await openIdbfsDatabase();

        try {
            await new Promise((resolve, reject) => {
                const transaction = database.transaction('FILE_DATA', 'readwrite');
                const store = transaction.objectStore('FILE_DATA');
                const timestamp = Date.now();

                for (const rootDirectory of saveRoots) {
                    const saveDirectory = `${rootDirectory}/Save`;
                    store.put({ mode: 16895, timestamp }, rootDirectory);
                    store.put({ mode: 16895, timestamp }, saveDirectory);

                    for (const slotName of slotNames) {
                        const key = `${saveDirectory}/${slotName}`;
                        const request = store.get(key);
                        request.onsuccess = () => {
                            if (!request.result) {
                                store.put({ contents: saveBytes.slice(), mode: 33206, timestamp }, key);
                            }
                        };
                    }

                    const prefsKey = `${rootDirectory}/PlayerPrefs`;
                    const prefsRequest = store.get(prefsKey);
                    prefsRequest.onsuccess = () => {
                        if (!prefsRequest.result) {
                            store.put({ contents: playerPrefs.slice(), mode: 33206, timestamp }, prefsKey);
                        }
                    };
                }

                transaction.oncomplete = resolve;
                transaction.onerror = () => reject(transaction.error || new Error('Scrittura save fallita.'));
                transaction.onabort = () => reject(transaction.error || new Error('Scrittura save annullata.'));
            });
        } finally {
            database.close();
        }

        logToConsole(`Profilo moddato pronto per ${mapSlug}.`);
        return true;
    }

    // 2. Audio Hooking (Web Audio API Interceptor)
    function initAudioHooks() {
        const sourcePrototype = window.AudioBufferSourceNode && window.AudioBufferSourceNode.prototype;
        if (!sourcePrototype || sourcePrototype.__cripsumChallengeHookInstalled) return;

        const origStart = sourcePrototype.start;
        sourcePrototype.start = function (when, offset, duration) {
            if (this.buffer) {
                analyzeAudioBuffer(this.buffer, 'play');
            }
            return origStart.apply(this, arguments);
        };
        sourcePrototype.__cripsumChallengeHookInstalled = true;
        
        logToConsole("Iniettore AudioContext caricato. Intercettazione in ascolto...");
    }

    function installPokiHooks() {
        const sdk = window.PokiSDK || {};
        if (sdk.__cripsumHooksInstalled) return;
        const callOriginal = (method, fallback) => {
            const original = typeof sdk[method] === 'function' ? sdk[method].bind(sdk) : fallback;
            sdk[method] = function (...args) {
                if (method === 'gameplayStart' || method === 'roundStart') {
                    triggerRunStart(`poki-${method}`);
                }
                return original ? original(...args) : undefined;
            };
        };

        callOriginal('gameplayStart');
        callOriginal('roundStart');
        sdk.rewardedBreak = () => Promise.resolve(true);
        Object.defineProperty(sdk, '__cripsumHooksInstalled', { value: true });
        window.PokiSDK = sdk;
    }

    function matchesAudioTarget(buffer, target) {
        const length = buffer.length;
        const duration = buffer.duration;
        
        // Verify length tolerance
        const lengthMatches = target.approxLengths.some(approx => Math.abs(length - approx) <= (target.lengthTolerance || 0));
        if (!lengthMatches) return false;
        
        // Verify duration bounds
        if (target.durationMin !== undefined && target.durationMax !== undefined) {
            return duration >= target.durationMin && duration <= target.durationMax;
        }
        if (target.expectedDuration !== undefined) {
            return Math.abs(duration - target.expectedDuration) <= (target.durationTolerance || 0);
        }
        return true;
    }

    function analyzeAudioBuffer(buffer, action) {
        // ONLY analyze played audio during active gameplay, NEVER on buffer decode at boot!
        if (action !== 'play') return;
        
        const now = performance.now();
        const signature = `${buffer.length}:${Math.round(buffer.duration * 1000)}`;
        if (state.lastAudioSignature === signature && now - state.lastAudioAt < 80) return;
        state.lastAudioSignature = signature;
        state.lastAudioAt = now;

        // The coin and jump clips overlap in older builds. Ignore ambiguous
        // samples immediately after a jump input and during the startup window.
        if (matchesAudioTarget(buffer, audioTargets.coin)) {
            const gameplayAge = now - state.gameplayStartedAt;
            const jumpAge = now - state.lastJumpInputAt;
            const ambiguousJumpClip = buffer.duration >= 0.585 && buffer.duration <= 0.605;
            if (gameplayAge >= 1200 && !(ambiguousJumpClip && jumpAge >= 0 && jumpAge <= 70)) {
                triggerCoinPickup('audio-hook');
            }
        }
        // Check for run start sound
        else if (matchesAudioTarget(buffer, audioTargets.start)) {
            triggerRunStart('audio-start');
        }
    }

    // 3. Unity Console Log Hooking
    function initConsoleHooks() {
        const origLog = console.log;
        console.log = function (...args) {
            const msg = args.join(' ');
            if (/\bCoinManager_OnCoinCollected|OnCoinCollected|CoinHit|PlayerInfo_OnCoinsChanged|coin collected|coins changed|moeda coletada\b/i.test(msg)) {
                triggerCoinPickup('console-log-hook');
            }
            origLog.apply(console, args);
        };
        logToConsole("Iniettore console log attivo. Ricerca telemetria in ascolto...");
    }

    // 4. Game Run Handlers
    function triggerRunStart(source) {
        if (!state.noCoinChallenge || state.isRunning) return;
        
        state.startTime = performance.now() - state.elapsedTime;
        state.isRunning = true;
        state.isFailed = false;
        state.firstInputStarted = true;
        state.gameplayStartedAt = performance.now();
        
        updateStatusHUD('RUNNING', 'var(--game-blue)');
        setStartHintVisible(false);
        
        // Run animation loop
        function tick() {
            if (!state.isRunning) return;
            state.elapsedTime = performance.now() - state.startTime;
            updateTimerDisplay(state.elapsedTime);
            requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
        
        logToConsole(`Rilevato inizio corsa (${source}). Timer avviato.`);
    }

    function triggerCoinPickup(source) {
        if (!state.noCoinChallenge || state.isFailed || !state.isRunning) return;
        
        state.isRunning = false;
        state.isFailed = true;
        
        updateStatusHUD('FAILED', 'var(--game-red)');
        logToConsole(`SFIDA FALLITA: Rilevato ritiro moneta tramite [${source}]!`);
        
        if (state.autoPauseOnCoin && state.unityInstance) {
            dispatchUnityKey('keydown', 'Escape');
            dispatchUnityKey('keyup', 'Escape');
            logToConsole("Richiesta autopausa inviata al gioco.");
        }
    }

    function resetChallenge() {
        state.isRunning = false;
        state.isFailed = false;
        state.elapsedTime = 0;
        state.firstInputStarted = false;
        state.gameplayStartedAt = 0;
        updateTimerDisplay(0);
        updateStatusHUD('ACTIVE', 'var(--game-green)');
        setStartHintVisible(true);
        logToConsole("Sfida resettata. Pronti per la prossima corsa.");
    }

    // 5. Key Remapping Logic
    function isGameActive() {
        const gameArea = document.getElementById('subwayGameArea');
        return Boolean(gameArea && gameArea.style.display !== 'none' && state.unityReady);
    }

    function dispatchUnityKey(type, code) {
        const keyCode = getKeyCodeForCode(code);
        const eventInit = {
            code,
            key: code === 'Space' ? ' ' : code,
            keyCode,
            which: keyCode,
            bubbles: true,
            cancelable: true
        };
        const unityEvent = new KeyboardEvent(type, eventInit);
        Object.defineProperty(unityEvent, '__cripsumUnityForwarded', { value: true });
        // Chromium treats these fields as legacy read-only values. Unity 2019
        // still reads them, so expose the expected numeric values explicitly.
        try { Object.defineProperty(unityEvent, 'keyCode', { value: keyCode }); } catch (error) {}
        try { Object.defineProperty(unityEvent, 'which', { value: keyCode }); } catch (error) {}
        try { Object.defineProperty(unityEvent, 'charCode', { value: type === 'keypress' ? keyCode : 0 }); } catch (error) {}

        const canvas = document.querySelector('#subwayGameContainer canvas');
        (canvas || document).dispatchEvent(unityEvent);
    }

    function initKeyRemapper() {
        const forwardKey = function (event) {
            if (event.__cripsumUnityForwarded || !isGameActive() || activeRemappingKey) return;
            const type = event.type;
            const mappedCode = event.code === 'Space' ? 'Space' : state.keyMapping[event.code];
            if (!mappedCode || mappedCode === event.code && event.code !== 'Space') return;

            event.preventDefault();
            event.stopImmediatePropagation();
            if (type === 'keydown') {
                highlightHUDKey(event.code, true);
                if (event.code === state.customKeys.jump || event.code === 'Space') {
                    state.lastJumpInputAt = performance.now();
                }
            } else {
                highlightHUDKey(event.code, false);
            }
            dispatchUnityKey(type, mappedCode);
            if (type === 'keydown' && mappedCode === 'Space') {
                dispatchUnityKey('keypress', 'Space');
            }
        };

        window.addEventListener('keydown', forwardKey, true);
        window.addEventListener('keyup', forwardKey, true);
    }

    function getKeyCodeForCode(code) {
        return {
            'Space': 32,
            'Enter': 13,
            'Escape': 27,
            'ArrowUp': 38,
            'ArrowDown': 40,
            'ArrowLeft': 37,
            'ArrowRight': 39
        }[code] || 0;
    }

    // 6. Loader & Boot Sequence
    async function loadMap(mapSlug) {
        if (state.loading) return;
        state.loading = true;
        const loadToken = ++state.loadToken;
        state.activeMap = mapSlug;
        state.activeRepo = mapRepos[mapSlug];
        state.unityReady = false;
        
        if (!state.activeRepo) {
            alert('Mappa non supportata!');
            return;
        }

        // 1. Show boot splash overlay
        const bootSplash = document.getElementById('subwayBootSplash');
        bootSplash.classList.remove('hidden');
        resetBootProgress();
        logToConsole(`Inizializzazione portale per la mappa: ${mapSlug.toUpperCase()}...`);
        
        // Disable lobby UI & enable fullscreen mode overlay
        document.body.classList.add('subway-fullscreen-active');
        document.getElementById('subwayLobby').style.display = 'none';
        document.getElementById('subwayGameArea').style.display = 'block';
        document.getElementById('subwayGameArea').setAttribute('aria-busy', 'true');
        resetChallenge();
        setStartHintVisible(false);

        try {
            // 2. Fetch and patch the config JSON
            updateBootProgress('Etapa 01 · Manifest', 15, 'Lettura dei file di configurazione...');
            
            let configUrl = `https://raw.githubusercontent.com/tavvkkj/${state.activeRepo}/main/game-assets/builds/${mapSlug}/${mapSlug}.alt.json`;
            let response = await fetch(configUrl);
            if (!response.ok) {
                // Fallback to default json
                configUrl = `https://raw.githubusercontent.com/tavvkkj/${state.activeRepo}/main/game-assets/builds/${mapSlug}/${mapSlug}.json`;
                response = await fetch(configUrl);
                if (!response.ok) throw new Error('Configurazione della mappa non trovata.');
            }
            
            // Parse the JSON (handle BOM if present)
            let rawText = await response.text();
            rawText = rawText.replace(/^\uFEFF/, '');
            const config = JSON.parse(rawText);
            if (!config.dataUrl || !config.wasmCodeUrl || !config.wasmFrameworkUrl) {
                throw new Error('Il manifest Unity è incompleto.');
            }
            logToConsole('Configurazione ricevuta da GitHub. Risoluzione asset...');
            
            // 3. Resolve ALL relative URLs to absolute raw GitHub URLs
            const resolveUrl = (relUrl) => {
                if (!relUrl) return relUrl;
                // Strip query params for resolution, re-add after
                const qIdx = relUrl.indexOf('?');
                const cleanUrl = qIdx >= 0 ? relUrl.substring(0, qIdx) : relUrl;
                const query = qIdx >= 0 ? relUrl.substring(qIdx) : '';
                if (/^(http|https|ftp|file):\/\//.test(cleanUrl)) return relUrl;
                // Resolve relative paths like ../shared/wasm_framework.unityweb
                const resolved = new URL(cleanUrl, configUrl).href;
                return resolved + query;
            };
            
            config.dataUrl = resolveUrl(config.dataUrl);
            config.wasmCodeUrl = resolveUrl(config.wasmCodeUrl);
            config.wasmFrameworkUrl = resolveUrl(config.wasmFrameworkUrl);
            // UnityLoader 2019 only preloads WASM when the string literally
            // ends in ".unityweb". Version queries in Bangkok/Moscow manifests
            // make that check fail and leave Module.wasmBinary undefined.
            try {
                const wasmCodeAsset = new URL(config.wasmCodeUrl);
                wasmCodeAsset.search = '';
                wasmCodeAsset.hash = '';
                config.wasmCodeUrl = wasmCodeAsset.href;
            } catch (error) {}
            if (config.asmCodeUrl) config.asmCodeUrl = resolveUrl(config.asmCodeUrl);
            if (config.asmFrameworkUrl) config.asmFrameworkUrl = resolveUrl(config.asmFrameworkUrl);
            if (config.asmMemoryUrl) config.asmMemoryUrl = resolveUrl(config.asmMemoryUrl);
            config.cacheControl = config.cacheControl || { default: 'immutable' };

            // The public manifests point several maps at a generic shared
            // framework that is not ABI-compatible with their WASM code. Keep
            // the code/data pair untouched and select the matching Unity 2019
            // framework family used by the original builds.
            const frameworkByMap = {
                zurich: '4399.z.js',
                beijing: '4399.js',
                cairo: '4399.js',
                paris: '4399.js',
                tokyo: '4399.js',
                london: '4399.js',
                mexico: 'subwaySurf14.08.js',
                newyork: '4399.js',
                berlin: '4399.sf.js',
                buenosaires: '4399.js'
            };
            if (frameworkByMap[mapSlug]) {
                config.wasmFrameworkUrl = new URL(
                    `/assets/js/subway/runtime/${frameworkByMap[mapSlug]}`,
                    window.location.href
                ).href;
                logToConsole(`Framework Unity compatibile selezionato per ${mapSlug}.`);
            }
            
            updateBootProgress('Etapa 02 · Atmosfera', 45, 'Asset risolti in URL assoluti...');
            logToConsole(`Data: ${config.dataUrl}`);
            logToConsole(`WASM Code: ${config.wasmCodeUrl}`);
            logToConsole(`WASM Fw: ${config.wasmFrameworkUrl}`);

            await validateUnityAssets(config);
            if (loadToken !== state.loadToken) return;

            // Apply precise table size patch based on the WASM binary
            activeTablePatchSize = getExpectedTableSize(config.wasmCodeUrl);
            if (activeTablePatchSize) {
                logToConsole(`WASM table size patch configurata a ${activeTablePatchSize} slot.`);
            }

            // 4. Dynamically load dependencies
            updateBootProgress('Etapa 03 · Interface', 70, 'Caricamento script di avvio Unity...');
            await loadScript('/assets/js/subway/poki.js');
            installPokiHooks();
            await loadScript('/assets/js/subway/UnityLoader.js');
            logToConsole('Loader di Unity pronto in memoria.');
            installSharedSaveHashPatch(mapSlug);
            try {
                await installDefaultSave(mapSlug, config);
            } catch (saveError) {
                console.warn('Installazione del profilo moddato non riuscita:', saveError);
                logToConsole('Avviso: profilo moddato non installato; il gioco userà il save locale esistente.');
            }
            if (loadToken !== state.loadToken) return;
            
            // 5. Monkey-patch UnityLoader's progress update to handle cross-origin URLs
            // The original code does: r.target.responseURL.split("/Build/")[1].split("?")[0]
            // which crashes when responseURL doesn't contain "/Build/"
            if (window.UnityLoader && window.UnityLoader.Progress) {
                const origUpdate = window.UnityLoader.Progress.update;
                window.UnityLoader.Progress.update = function(e, t, r) {
                    if (r && !r.lengthComputable) {
                        const url = r.target && r.target.responseURL ? r.target.responseURL : '';
                        if (!url || url.indexOf('/Build/') === -1) {
                            // Fake a lengthComputable event so the original code
                            // skips the split("/Build/") branch entirely
                            const fakeEvent = {
                                lengthComputable: true,
                                loaded: r.loaded || 0,
                                total: r.total || 0,
                                target: r.target,
                                type: r.type
                            };
                            return origUpdate.call(this, e, t, fakeEvent);
                        }
                    }
                    return origUpdate.call(this, e, t, r);
                };
                logToConsole('Patch progress update applicata.');
            }

            // 6. Instantiate Unity WebGL
            updateBootProgress('Etapa 04 · Portal', 90, 'Caricamento del motore WebGL...');
            
            const gameContainer = document.getElementById('subwayGameContainer');
            gameContainer.innerHTML = '';
            
            window.commercialBreak = function() { return Promise.resolve(); };

            // Store resolved wasmCodeUrl for locateFile override
            const resolvedWasmCodeUrl = config.wasmCodeUrl;

            function onUnityInstanceReady() {
                if (loadToken !== state.loadToken || state.unityReady) return;
                state.unityReady = true;
                state.loading = false;
                document.getElementById('subwayGameArea').setAttribute('aria-busy', 'false');
                updateBootProgress('Portal Disponibile', 100, 'Fine sequenza di avvio.');
                setTimeout(() => {
                    bootSplash.classList.add('hidden');
                    const cv = document.querySelector('#subwayGameContainer canvas');
                    if (cv) {
                        cv.setAttribute('aria-label', `Subway Surfers - ${mapSlug}`);
                        cv.setAttribute('tabindex', '0');
                    }
                    setStartHintVisible(true);
                    logToConsole('Gioco avviato con successo. Buona fortuna!');
                }, 800);
            }

            // Create a config blob URL with absolute URLs already resolved.
            // This way resolveBuildUrl will see http:// and return the URL as-is.
            if (state.configBlobUrl) URL.revokeObjectURL(state.configBlobUrl);
            const configBlobUrl = URL.createObjectURL(
                new Blob([JSON.stringify(config)], { type: 'application/json' })
            );
            state.configBlobUrl = configBlobUrl;

            if (window.UnityLoader && window.UnityLoader.instantiate) {
                logToConsole("Avvio UnityLoader.instantiate con URL patchati...");
                
                const instance = window.UnityLoader.instantiate("subwayGameContainer", configBlobUrl, {
                    onProgress: function (gameInstance, progress) {
                        const percent = Math.round(90 + (progress * 10));
                        updateBootProgress('Etapa 04 · Portal', percent, `Caricamento memoria di gioco (${percent}%)`);
                        if (progress >= 1.0) state.unityInstance = gameInstance;
                    },
                    onsuccess: function (unityModule) {
                        if (unityModule && unityModule.unityInstance) {
                            state.unityInstance = unityModule.unityInstance;
                        }
                        onUnityInstanceReady();
                    },
                    onerror: function (message) {
                        if (loadToken !== state.loadToken) return;
                        state.loading = false;
                        state.unityReady = false;
                        document.getElementById('subwayGameArea').setAttribute('aria-busy', 'false');
                        const errorMessage = typeof message === 'string'
                            ? message
                            : 'Unity non è riuscito ad avviarsi.';
                        updateBootProgress('Errore di Avvio', 100, errorMessage);
                        logToConsole(`ERRORE UNITY: ${errorMessage}`);
                        console.error('Unity loader error:', message);
                    },
                    Module: {
                        locateFile: function (filename) {
                            // Override the hardcoded "Build/".concat(...) behavior
                            // Emscripten calls this to find the .wasm binary
                            if (filename === 'build.wasm') {
                                return resolvedWasmCodeUrl;
                            }
                            return filename;
                        }
                    }
                });
                state.unityInstance = instance;
                window.unityInstance = instance;
                window.gameInstance = instance;
                window.__unityInstance = instance;
                notifyPokiReady();
            } else if (window.createUnityInstance) {
                const canvas = document.createElement('canvas');
                canvas.id = 'subwayCanvas';
                gameContainer.appendChild(canvas);
                
                window.createUnityInstance(canvas, config, function (progress) {
                    const percent = Math.round(90 + (progress * 10));
                    updateBootProgress('Etapa 04 · Portal', percent, `Caricamento memoria di gioco (${percent}%)`);
                }).then(function (instance) {
                    state.unityInstance = instance;
                    window.unityInstance = instance;
                    window.gameInstance = instance;
                    window.__unityInstance = instance;
                    notifyPokiReady();
                    onUnityInstanceReady();
                }).catch(function (err) { throw err; });
            } else {
                throw new Error('Metodo di inizializzazione Unity non trovato.');
            }

        } catch (err) {
            if (loadToken !== state.loadToken) return;
            state.loading = false;
            console.error(err);
            updateBootProgress('Errore di Avvio', 100, 'Impossibile completare la sequenza.');
            logToConsole(`ERRORE DI CARICAMENTO: ${err.message}`);
            setTimeout(() => {
                alert(`Errore nel caricamento del gioco: ${err.message}`);
                exitGame();
            }, 3000);
        }
    }

    function exitGame() {
        state.loadToken += 1;
        state.unityReady = false;
        state.loading = false;
        document.body.classList.remove('subway-fullscreen-active');
        if (state.unityInstance) {
            try {
                const quitResult = state.unityInstance.Quit && state.unityInstance.Quit();
                if (quitResult && typeof quitResult.catch === 'function') quitResult.catch(() => {});
            } catch (e) {}
            state.unityInstance = null;
        }
        window.unityInstance = null;
        window.gameInstance = null;
        window.__unityInstance = null;
        activeTablePatchSize = 0;
        if (state.configBlobUrl) {
            URL.revokeObjectURL(state.configBlobUrl);
            state.configBlobUrl = null;
        }
        document.getElementById('subwayGameContainer').innerHTML = '';
        document.getElementById('subwayGameArea').style.display = 'none';
        document.getElementById('subwayLobby').style.display = 'block';
        resetBootProgress();
        resetChallenge();
    }

    async function validateUnityAssets(config) {
        const entries = [
            ['dati', config.dataUrl],
            ['codice WASM', config.wasmCodeUrl],
            ['framework', config.wasmFrameworkUrl]
        ];

        await Promise.all(entries.map(async ([label, url]) => {
            let response;
            try {
                response = await fetch(url, { method: 'HEAD', cache: 'force-cache', credentials: 'omit' });
            } catch (error) {
                throw new Error(`Asset ${label} non raggiungibile.`);
            }
            if (!response.ok) throw new Error(`Asset ${label} non disponibile (HTTP ${response.status}).`);
        }));
        logToConsole('Manifest verificato: tutti gli asset della mappa sono disponibili.');
    }

    // Helper functions
    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const r = document.querySelector(`script[src="${src}"]`);
            if (r) {
                resolve();
                return;
            }
            const i = document.createElement('script');
            i.src = src;
            i.async = false;
            i.onload = resolve;
            i.onerror = reject;
            document.body.appendChild(i);
        });
    }

    // 7. Draggable HUD Widget Implementation
    function initDraggableWidgets() {
        const widgets = document.querySelectorAll('.subway-hud-widget');
        widgets.forEach(widget => {
            const handle = widget.querySelector('.widget-handle') || widget;
            makeElementDraggable(widget, handle);
        });
    }

    function makeElementDraggable(elmnt, handle) {
        let pointerId = null;
        let offsetX = 0;
        let offsetY = 0;

        handle.addEventListener('pointerdown', function (event) {
            if (event.button !== undefined && event.button !== 0) return;
            event.preventDefault();
            event.stopPropagation();

            const rect = elmnt.getBoundingClientRect();
            const parentRect = elmnt.offsetParent.getBoundingClientRect();
            // Freeze both axes before removing right/bottom anchors. This keeps
            // the widget dimensions stable throughout and after the drag.
            elmnt.style.width = `${rect.width}px`;
            elmnt.style.height = `${rect.height}px`;
            elmnt.style.minWidth = `${rect.width}px`;
            elmnt.style.maxWidth = `${rect.width}px`;
            elmnt.style.minHeight = `${rect.height}px`;
            elmnt.style.maxHeight = `${rect.height}px`;
            elmnt.style.left = `${rect.left - parentRect.left}px`;
            elmnt.style.top = `${rect.top - parentRect.top}px`;
            elmnt.style.right = 'auto';
            elmnt.style.bottom = 'auto';

            pointerId = event.pointerId;
            offsetX = event.clientX - rect.left;
            offsetY = event.clientY - rect.top;
            handle.setPointerCapture(pointerId);
            elmnt.classList.add('is-dragging');
        });

        handle.addEventListener('pointermove', function (event) {
            if (pointerId !== event.pointerId) return;
            event.preventDefault();
            const parentRect = elmnt.offsetParent.getBoundingClientRect();
            updatePosition(event.clientY - parentRect.top - offsetY, event.clientX - parentRect.left - offsetX);
        });

        const stopDrag = function (event) {
            if (pointerId !== event.pointerId) return;
            try { handle.releasePointerCapture(pointerId); } catch (e) {}
            pointerId = null;
            elmnt.classList.remove('is-dragging');
        };
        handle.addEventListener('pointerup', stopDrag);
        handle.addEventListener('pointercancel', stopDrag);

        function updatePosition(top, left) {
            // Containment boundaries (stay inside parent container)
            const parent = elmnt.offsetParent;
            if (parent) {
                const maxTop = parent.clientHeight - elmnt.clientHeight;
                const maxLeft = parent.clientWidth - elmnt.clientWidth;
                
                top = Math.max(0, Math.min(top, maxTop));
                left = Math.max(0, Math.min(left, maxLeft));
            }
            
            elmnt.style.top = top + "px";
            elmnt.style.left = left + "px";
        }

    }

    // 8. Interface Update Helpers
    function updateBootProgress(stage, percent, statusText) {
        const bootStage = document.getElementById('subwayBootStage');
        const bootPercent = document.getElementById('subwayBootPercent');
        const bootTrackValue = document.getElementById('subwayBootTrackValue');
        const bootStatus = document.getElementById('subwayBootStatus');

        if (bootStage) bootStage.textContent = stage;
        if (bootPercent) bootPercent.textContent = percent;
        if (bootTrackValue) bootTrackValue.style.width = percent + '%';
        if (bootStatus) bootStatus.textContent = statusText;
    }

    function resetBootProgress() {
        updateBootProgress('Preparazione', 0, 'Caricamento risorse essenziali...');
        const consoleEl = document.getElementById('subwayBootConsole');
        if (consoleEl) consoleEl.innerHTML = '';
    }

    function logToConsole(text) {
        const consoleEl = document.getElementById('subwayBootConsole');
        if (consoleEl) {
            const span = document.createElement('span');
            span.textContent = `[${new Date().toLocaleTimeString()}] ${text}`;
            consoleEl.appendChild(span);
            consoleEl.scrollTop = consoleEl.scrollHeight;
        }
    }

    function updateTimerDisplay(ms) {
        const display = document.getElementById('subwayTimerDisplay');
        if (!display) return;
        
        let minutes = Math.floor(ms / 60000);
        let seconds = Math.floor((ms % 60000) / 1000);
        let milliseconds = Math.floor((ms % 1000));
        
        const pad = (num, size) => ('000' + num).slice(-size);
        display.textContent = `${pad(minutes, 2)}:${pad(seconds, 2)}.${pad(milliseconds, 3)}`;
    }

    function updateStatusHUD(status, color) {
        const badge = document.getElementById('subwayStatusBadge');
        if (badge) {
            badge.textContent = status;
            badge.className = `subway-status-badge ${status.toLowerCase()}`;
            badge.style.backgroundColor = color;
        }
    }

    function setStartHintVisible(visible) {
        const hint = document.getElementById('subwayStartHint');
        if (hint) hint.classList.toggle('is-visible', Boolean(visible && state.unityReady && !state.isRunning));
    }

    function highlightHUDKey(code, isPressed) {
        // Find which custom binding matches this code
        let boundKey = null;
        for (const [keyName, binding] of Object.entries(state.customKeys)) {
            if (binding === code) {
                boundKey = keyName;
                break;
            }
        }
        
        // If not found in custom, check default keys
        if (!boundKey) {
            for (const [keyName, binding] of Object.entries(state.defaultKeys)) {
                if (binding === code) {
                    boundKey = keyName;
                    break;
                }
            }
        }

        if (boundKey) {
            const keyIndicator = document.getElementById(`hudKey-${boundKey}`);
            if (keyIndicator) {
                keyIndicator.classList.toggle('pressed', isPressed);
            }
        }
    }

    // 9. Hotkey Mapping Customization Settings
    let activeRemappingKey = null;

    function openSettingsModal() {
        const modal = document.getElementById('subwaySettingsModal');
        modal.classList.add('show');
        
        // Render bind buttons
        for (const [key, code] of Object.entries(state.customKeys)) {
            document.querySelectorAll(`[data-keybind="${key}"]`).forEach(btn => {
                btn.textContent = cleanKeyCodeText(code);
                btn.className = 'subway-key-btn';
            });
        }
    }

    function closeSettingsModal() {
        const modal = document.getElementById('subwaySettingsModal');
        modal.classList.remove('show');
        activeRemappingKey = null;
    }

    function startRemap(keyName) {
        if (activeRemappingKey) return;
        
        activeRemappingKey = keyName;
        const btn = document.querySelector(`[data-keybind="${keyName}"]`);
        if (btn) {
            btn.textContent = 'Premere...';
            btn.classList.add('waiting');
        }

        const handleKey = function (e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Record new bind
            state.customKeys[keyName] = e.code;
            
            // Update key mapping redirect
            // Check default maps
            const targetMap = state.defaultKeys[keyName];
            
            // Clean old mapping for this key
            for (const [inputCode, outputCode] of Object.entries(state.keyMapping)) {
                if (outputCode === targetMap) {
                    delete state.keyMapping[inputCode];
                }
            }
            
            // Add new mapping
            state.keyMapping[e.code] = targetMap;
            
            // Render binds
            document.querySelectorAll(`[data-keybind="${keyName}"]`).forEach(keyButton => {
                keyButton.textContent = cleanKeyCodeText(e.code);
                keyButton.classList.remove('waiting');
            });
            
            // Remove listeners
            window.removeEventListener('keydown', handleKey, true);
            activeRemappingKey = null;
            
            // Sync key labels in HUD cheat-sheet
            updateHUDKeyCheatSheet();
            logToConsole(`Rimappato controllo [${keyName.toUpperCase()}] al tasto ${cleanKeyCodeText(e.code)}`);
        };
        
        window.addEventListener('keydown', handleKey, true);
    }

    function cleanKeyCodeText(code) {
        return code.replace('Key', '').replace('Arrow', '').replace('Digit', '');
    }

    function updateHUDKeyCheatSheet() {
        for (const keyName of ['jump', 'duck', 'left', 'right']) {
            const indicator = document.getElementById(`hudKey-${keyName}`);
            if (indicator) {
                const code = state.customKeys[keyName] || state.defaultKeys[keyName];
                indicator.textContent = cleanKeyCodeText(code);
            }
        }
    }

    // 10. Initialization on DOM Load
    document.addEventListener('DOMContentLoaded', function () {
        // Verify we are on the Subway Surfers page
        const container = document.getElementById('subwayPortal');
        if (!container) return;

        // Initialize Audio and Console Log Hooks
        initAudioHooks();
        initConsoleHooks();
        
        // Initialize Draggables and key bindings
        initDraggableWidgets();
        initKeyRemapper();
        updateHUDKeyCheatSheet();

        // Bind map card selection clicks
        const mapCards = document.querySelectorAll('.subway-map-card');
        mapCards.forEach(card => {
            card.setAttribute('role', 'button');
            card.setAttribute('tabindex', '0');
            card.addEventListener('click', function () {
                const slug = this.dataset.map;
                loadMap(slug);
            });
            card.addEventListener('keydown', function (event) {
                if (event.code !== 'Enter' && event.code !== 'Space') return;
                event.preventDefault();
                loadMap(this.dataset.map);
            });
        });

        // Toggle challenge settings
        const challengeToggle = document.getElementById('toggleNoCoinChallenge');
        if (challengeToggle) {
            challengeToggle.checked = state.noCoinChallenge;
            challengeToggle.addEventListener('change', function () {
                state.noCoinChallenge = this.checked;
                const timerWidget = document.getElementById('hudWidgetTimer');
                const statusWidget = document.getElementById('hudWidgetStatus');
                if (timerWidget) timerWidget.style.display = this.checked ? 'block' : 'none';
                if (statusWidget) statusWidget.style.display = this.checked ? 'block' : 'none';
                resetChallenge();
            });
        }

        const pauseToggle = document.getElementById('toggleAutoPause');
        if (pauseToggle) {
            pauseToggle.checked = state.autoPauseOnCoin;
            pauseToggle.addEventListener('change', function () {
                state.autoPauseOnCoin = this.checked;
            });
        }

        // Settings modal triggers
        const configBtn = document.getElementById('hudWidgetSettingsBtn');
        if (configBtn) {
            configBtn.addEventListener('click', openSettingsModal);
        }
        
        const closeBtn = document.getElementById('closeSettingsModal');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeSettingsModal);
        }

        const exitBtn = document.getElementById('exitGameBtn');
        if (exitBtn) {
            exitBtn.addEventListener('click', exitGame);
        }

        const cancelLoadBtn = document.getElementById('cancelSubwayLoad');
        if (cancelLoadBtn) cancelLoadBtn.addEventListener('click', exitGame);

        const startHint = document.getElementById('subwayStartHint');
        if (startHint) {
            startHint.setAttribute('role', 'button');
            startHint.setAttribute('tabindex', '0');
            const activateGame = function () {
                if (!isGameActive()) return;
                dispatchUnityKey('keydown', 'Space');
                dispatchUnityKey('keypress', 'Space');
                setTimeout(() => dispatchUnityKey('keyup', 'Space'), 30);
            };
            startHint.addEventListener('click', activateGame);
            startHint.addEventListener('keydown', function (event) {
                if (event.code !== 'Enter' && event.code !== 'Space') return;
                event.preventDefault();
                activateGame();
            });
        }

        // Bind customization mapping buttons
        for (const key of ['jump', 'duck', 'left', 'right']) {
            document.querySelectorAll(`[data-keybind="${key}"]`).forEach(btn => {
                btn.addEventListener('click', function () {
                    startRemap(key);
                });
            });
        }
    });

})();
