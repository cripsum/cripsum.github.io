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
            descriptor.initial = Math.max(descriptor.initial, activeTablePatchSize);
            descriptor.maximum = descriptor.maximum === undefined ? activeTablePatchSize : Math.max(descriptor.maximum, activeTablePatchSize);
        }
        return new _OrigWasmTable(descriptor);
    };
    WebAssembly.Table.prototype = _OrigWasmTable.prototype;
    try { Object.setPrototypeOf(WebAssembly.Table, _OrigWasmTable); } catch (e) {}

    // Framework Patching Engine (Neutralizes Poki site-lock and missing functions)
    function patchWasmFramework(text) {
        if (!text || typeof text !== 'string') return text;
        if (!text.includes("_JS_Eval_") && !text.includes("_JS_PokiSDK_") && !text.includes("_JS_SystemInfo_") && !text.includes("getInternalformatParameter")) {
            return text;
        }
        
        let t = text;
        
        // 1. Remove Poki Domain Lock timer
        t = t.replace(
            /setTimeout\(function\(\)\{var e,i,n,t=unityMapSource\("bG9jYXRpb24"\)[\s\S]*?window\[t\]=unityMapSource\(e\[3\]\)\}\},2e3\),ENVIRONMENT_IS_WEB=/g,
            "setTimeout(function(){},2e3),ENVIRONMENT_IS_WEB="
        );

        // 2. Wrap OpenURL & EvalJS with sitelock blockers
        t = t.replace(
            /function _JS_Eval_OpenURL\(([^)]*)\)\{var ([^=]+)=Pointer_stringify\(\1\);location\.href=\2\}/g,
            'function _JS_Eval_OpenURL($1){var $2=Pointer_stringify($1);if(typeof window!=="undefined"&&window.__blockUnityExternalOpenURL&&window.__blockUnityExternalOpenURL($2))return;location.href=$2}'
        );

        t = t.replace(
            /function _JS_Eval_EvalJS\(([^)]*)\)\{var ([^=]+)=Pointer_stringify\(\1\);try\{eval\(\2\)\}catch\(([^)]*)\)\{console\.error\(\3\)\}\}/g,
            'function _JS_Eval_EvalJS($1){var $2=Pointer_stringify($1);try{if(typeof window!=="undefined"&&window.__blockUnityExternalEval&&window.__blockUnityExternalEval($2))return;eval($2)}catch($3){console.error($3)}}'
        );

        // 3. Fix JS_PokiSDK_gameLoadingProgress abort (exact tavvkkj replacement)
        t = t.replace(
            /function _JS_PokiSDK_gameLoadingProgress\(\)\{err\("missing function: JS_PokiSDK_gameLoadingProgress"\);abort\(-1\)\}/g,
            'function _JS_PokiSDK_gameLoadingProgress(){if(typeof window!=="undefined"&&window.PokiSDK&&window.PokiSDK.gameLoadingProgress)window.PokiSDK.gameLoadingProgress.apply(window.PokiSDK,arguments)}'
        );

        // 4. Catch any other missing function aborts safely
        t = t.replace(
            /function (_JS_PokiSDK_[a-zA-Z0-9_]+)\(\)\{err\("missing function: [^"]+"\);abort\(-1\)\}/g,
            'function $1(){}'
        );

        return t;
    }

    function isWasmHeader(u8) {
        return u8[0] === 0 && u8[1] === 97 && u8[2] === 115 && u8[3] === 109; // \0asm
    }

    function patchPart(part, isScriptOrText, decoder, encoder) {
        if (typeof part === 'string') return patchWasmFramework(part);
        if (!isScriptOrText) return part;
        
        const u8 = part instanceof ArrayBuffer 
            ? new Uint8Array(part) 
            : ArrayBuffer.isView(part) 
                ? new Uint8Array(part.buffer, part.byteOffset, part.byteLength) 
                : null;
                
        if (!u8 || u8.length < 64 || isWasmHeader(u8)) return part;
        
        try {
            const decodedText = decoder.decode(u8);
            const patchedText = patchWasmFramework(decodedText);
            return patchedText === decodedText ? part : encoder.encode(patchedText);
        } catch (e) {
            return part;
        }
    }

    // Intercept window.Blob creation so decompressed framework blobs are patched automatically
    if (!window.__unityBlobPatchInstalled && window.Blob && window.TextDecoder && window.TextEncoder) {
        const OrigBlob = window.Blob;
        const decoder = new TextDecoder();
        const encoder = new TextEncoder();
        
        function PatchedBlob(blobParts = [], options = {}) {
            const mimeType = String((options && options.type) || "").toLowerCase();
            const isScriptOrText = mimeType.includes("javascript") || mimeType.includes("text");
            const patchedParts = Array.from(blobParts, part => patchPart(part, isScriptOrText, decoder, encoder));
            return new OrigBlob(patchedParts, options);
        }
        
        PatchedBlob.prototype = OrigBlob.prototype;
        Object.setPrototypeOf(PatchedBlob, OrigBlob);
        window.Blob = PatchedBlob;
        window.__unityBlobPatchInstalled = true;
    }

    // 1. Game State & Settings
    const state = {
        activeMap: null,
        activeRepo: null,
        unityInstance: null,
        timerInterval: null,
        startTime: 0,
        elapsedTime: 0,
        isRunning: false,
        isFailed: false,
        noCoinChallenge: true,
        autoPauseOnCoin: true,
        firstInputStarted: false,
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

    // 2. Audio Hooking (Web Audio API Interceptor)
    function initAudioHooks() {
        const origDecodeAudioData = window.AudioContext.prototype.decodeAudioData;
        window.AudioContext.prototype.decodeAudioData = function (arrayBuffer, successCallback, errorCallback) {
            const promise = origDecodeAudioData.call(this, arrayBuffer, function (buffer) {
                if (successCallback) successCallback(buffer);
            }, errorCallback);

            if (promise && typeof promise.then === 'function') {
                promise.then(function (buffer) {
                    analyzeAudioBuffer(buffer, 'decode');
                });
            }
            return promise;
        };

        const origStart = window.AudioBufferSourceNode.prototype.start;
        window.AudioBufferSourceNode.prototype.start = function (when, offset, duration) {
            if (this.buffer) {
                analyzeAudioBuffer(this.buffer, 'play');
            }
            return origStart.apply(this, arguments);
        };
        
        logToConsole("Iniettore AudioContext caricato. Intercettazione in ascolto...");
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
        // Check for coin pickup sound
        if (matchesAudioTarget(buffer, audioTargets.coin)) {
            triggerCoinPickup('audio-hook');
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
        
        updateStatusHUD('RUNNING', 'var(--game-blue)');
        
        // Run animation loop
        function tick() {
            if (!state.isRunning) return;
            state.elapsedTime = performance.now() - state.startTime;
            updateTimerDisplay(state.elapsedTime);
            requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
        
        logToConsole("Rilevato inizio corsa (Audio/Input). Timer avviato.");
    }

    function triggerCoinPickup(source) {
        if (!state.noCoinChallenge || state.isFailed || !state.isRunning) return;
        
        state.isRunning = false;
        state.isFailed = true;
        
        updateStatusHUD('FAILED', 'var(--game-red)');
        logToConsole(`SFIDA FALLITA: Rilevato ritiro moneta tramite [${source}]!`);
        
        if (state.autoPauseOnCoin && state.unityInstance) {
            // Emulate escape key or pause call to Unity if possible
            // Let's send a fake keydown Escape to the canvas to pause the game
            const canvas = document.querySelector('canvas');
            if (canvas) {
                const escDown = new KeyboardEvent('keydown', { code: 'Escape', key: 'Escape', keyCode: 27, which: 27, bubbles: true });
                canvas.dispatchEvent(escDown);
                logToConsole("Richiesto autopausa al gioco.");
            }
        }
    }

    function resetChallenge() {
        state.isRunning = false;
        state.isFailed = false;
        state.elapsedTime = 0;
        state.firstInputStarted = false;
        updateTimerDisplay(0);
        updateStatusHUD('ACTIVE', 'var(--game-green)');
        logToConsole("Sfida resettata. Pronti per la prossima corsa.");
    }

    // 5. Key Remapping Logic
    function initKeyRemapper() {
        const canvasContainer = document.getElementById('subwayGameArea');
        
        window.addEventListener('keydown', function (e) {
            // Highlight keys in the HUD cheatsheet
            highlightHUDKey(e.code, true);

            // Capture phase key mapping redirect
            const targetCanvas = document.querySelector('#subwayGameArea canvas');
            if (targetCanvas && document.activeElement === targetCanvas) {
                // If it's a first movement key, auto start the timer as backup
                if (!state.firstInputStarted && ['ArrowUp','ArrowDown','ArrowLeft','ArrowRight','KeyW','KeyS','KeyA','KeyD'].includes(e.code)) {
                    triggerRunStart('keyboard-start');
                }
                
                // Remap custom key bindings to default keys
                const remapped = state.keyMapping[e.code];
                if (remapped) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    
                    // Dispatch remapped key event to the canvas
                    const keyCode = getKeyCodeForCode(remapped);
                    const mappedEvent = new KeyboardEvent('keydown', {
                        code: remapped,
                        key: remapped,
                        keyCode: keyCode,
                        which: keyCode,
                        bubbles: true,
                        cancelable: true
                    });
                    targetCanvas.dispatchEvent(mappedEvent);
                }
            }
        }, true); // Use capture phase

        window.addEventListener('keyup', function (e) {
            highlightHUDKey(e.code, false);

            const targetCanvas = document.querySelector('#subwayGameArea canvas');
            if (targetCanvas && document.activeElement === targetCanvas) {
                const remapped = state.keyMapping[e.code];
                if (remapped) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    
                    const keyCode = getKeyCodeForCode(remapped);
                    const mappedEvent = new KeyboardEvent('keyup', {
                        code: remapped,
                        key: remapped,
                        keyCode: keyCode,
                        which: keyCode,
                        bubbles: true,
                        cancelable: true
                    });
                    targetCanvas.dispatchEvent(mappedEvent);
                }
            }
        }, true);
    }

    function getKeyCodeForCode(code) {
        return {
            'ArrowUp': 38,
            'ArrowDown': 40,
            'ArrowLeft': 37,
            'ArrowRight': 39
        }[code] || 0;
    }

    // 6. Loader & Boot Sequence
    async function loadMap(mapSlug) {
        state.activeMap = mapSlug;
        state.activeRepo = mapRepos[mapSlug];
        
        if (!state.activeRepo) {
            alert('Mappa non supportata!');
            return;
        }

        // 1. Show boot splash overlay
        const bootSplash = document.getElementById('subwayBootSplash');
        bootSplash.classList.remove('hidden');
        resetBootProgress();
        logToConsole(`Inizializzazione portale per la mappa: ${mapSlug.toUpperCase()}...`);
        
        // Disable lobby UI
        document.getElementById('subwayLobby').style.display = 'none';
        document.getElementById('subwayGameArea').style.display = 'flex';
        resetChallenge();

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
            logToConsole('Configurazione ricevuta da GitHub. Risoluzione asset...');
            
            // 3. Resolve ALL relative URLs to absolute raw GitHub URLs
            const baseUrl = configUrl.substring(0, configUrl.lastIndexOf('/') + 1);
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
            if (config.asmCodeUrl) config.asmCodeUrl = resolveUrl(config.asmCodeUrl);
            if (config.asmFrameworkUrl) config.asmFrameworkUrl = resolveUrl(config.asmFrameworkUrl);
            if (config.asmMemoryUrl) config.asmMemoryUrl = resolveUrl(config.asmMemoryUrl);
            
            updateBootProgress('Etapa 02 · Atmosfera', 45, 'Asset risolti in URL assoluti...');
            logToConsole(`Data: ${config.dataUrl}`);
            logToConsole(`WASM Code: ${config.wasmCodeUrl}`);
            logToConsole(`WASM Fw: ${config.wasmFrameworkUrl}`);

            // Apply precise table size patch based on the WASM binary
            activeTablePatchSize = getExpectedTableSize(config.wasmCodeUrl);
            if (activeTablePatchSize) {
                logToConsole(`WASM table size patch configurata a ${activeTablePatchSize} slot.`);
            }

            // 4. Dynamically load dependencies
            updateBootProgress('Etapa 03 · Interface', 70, 'Caricamento script di avvio Unity...');
            await loadScript('/assets/js/subway/poki.js');
            await loadScript('/assets/js/subway/UnityLoader.js');
            logToConsole('Loader di Unity pronto in memoria.');
            
            // Hook loadCode to neutralize Poki's domain lock check
            if (window.UnityLoader && window.UnityLoader.loadCode) {
                const origLoadCode = window.UnityLoader.loadCode;
                window.UnityLoader.loadCode = function (e, t, r, n) {
                    try {
                        if (typeof t === 'string') {
                            t = patchWasmFramework(t);
                        } else if (t instanceof Uint8Array || t instanceof ArrayBuffer) {
                            const textDecoder = new TextDecoder();
                            const decoded = textDecoder.decode(t);
                            const patched = patchWasmFramework(decoded);
                            t = new TextEncoder().encode(patched);
                        }
                    } catch (err) {
                        console.warn('WASM framework patch warning:', err);
                    }
                    return origLoadCode.call(this, e, t, r, n);
                };
                logToConsole('Patch anti-domain-lock attiva.');
            }
            
            // 5. Monkey-patch UnityLoader's progress update to handle cross-origin URLs
            // The original code does: r.target.responseURL.split("/Build/")[1].split("?")[0]
            // which crashes when responseURL doesn't contain "/Build/"
            if (window.UnityLoader && window.UnityLoader.Progress) {
                const origUpdate = window.UnityLoader.Progress.update;
                window.UnityLoader.Progress.update = function(e, t, r) {
                    if (r && !r.lengthComputable && r.target && r.target.responseURL) {
                        const url = r.target.responseURL;
                        if (url.indexOf('/Build/') === -1) {
                            // Fake a lengthComputable event so the original code
                            // skips the split("/Build/") branch entirely
                            const fakeEvent = {
                                lengthComputable: true,
                                loaded: r.loaded || 0,
                                total: r.total || 0,
                                target: r.target
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
                updateBootProgress('Portal Disponibile', 100, 'Fine sequenza di avvio.');
                setTimeout(() => {
                    bootSplash.classList.add('hidden');
                    const cv = document.querySelector('#subwayGameContainer canvas');
                    if (cv) {
                        cv.focus();
                        cv.addEventListener('click', () => cv.focus());
                    }
                    logToConsole('Gioco avviato con successo. Buona fortuna!');
                }, 800);
            }

            // Create a config blob URL with absolute URLs already resolved.
            // This way resolveBuildUrl will see http:// and return the URL as-is.
            const configBlobUrl = URL.createObjectURL(
                new Blob([JSON.stringify(config)], { type: 'application/json' })
            );

            if (window.UnityLoader && window.UnityLoader.instantiate) {
                logToConsole("Avvio UnityLoader.instantiate con URL patchati...");
                
                const instance = window.UnityLoader.instantiate("subwayGameContainer", configBlobUrl, {
                    onProgress: function (gameInstance, progress) {
                        const percent = Math.round(90 + (progress * 10));
                        updateBootProgress('Etapa 04 · Portal', percent, `Caricamento memoria di gioco (${percent}%)`);
                        if (progress >= 1.0) {
                            state.unityInstance = gameInstance;
                            onUnityInstanceReady();
                        }
                    },
                    Module: {
                        locateFile: function (filename) {
                            // Override the hardcoded "Build/".concat(...) behavior
                            // Emscripten calls this to find the .wasm binary
                            if (filename === 'build.wasm' || filename.endsWith('.unityweb')) {
                                return resolvedWasmCodeUrl;
                            }
                            return filename;
                        }
                    }
                });
                state.unityInstance = instance;
            } else if (window.createUnityInstance) {
                const canvas = document.createElement('canvas');
                canvas.id = 'subwayCanvas';
                gameContainer.appendChild(canvas);
                
                window.createUnityInstance(canvas, config, function (progress) {
                    const percent = Math.round(90 + (progress * 10));
                    updateBootProgress('Etapa 04 · Portal', percent, `Caricamento memoria di gioco (${percent}%)`);
                }).then(function (instance) {
                    state.unityInstance = instance;
                    onUnityInstanceReady();
                }).catch(function (err) { throw err; });
            } else {
                throw new Error('Metodo di inizializzazione Unity non trovato.');
            }

        } catch (err) {
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
        if (state.unityInstance) {
            state.unityInstance.Quit();
            state.unityInstance = null;
        }
        document.getElementById('subwayGameContainer').innerHTML = '';
        document.getElementById('subwayGameArea').style.display = 'none';
        document.getElementById('subwayLobby').style.display = 'block';
        resetBootProgress();
        resetChallenge();
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
        let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
        
        handle.onmousedown = dragMouseDown;
        handle.ontouchstart = dragTouchStart;

        function dragMouseDown(e) {
            e = e || window.event;
            e.preventDefault();
            // get the mouse cursor position at startup:
            pos3 = e.clientX;
            pos4 = e.clientY;
            document.onmouseup = closeDragElement;
            // call a function whenever the cursor moves:
            document.onmousemove = elementDrag;
        }

        function dragTouchStart(e) {
            if (e.touches.length === 1) {
                pos3 = e.touches[0].clientX;
                pos4 = e.touches[0].clientY;
                document.ontouchend = closeDragElement;
                document.ontouchmove = elementTouchDrag;
            }
        }

        function elementDrag(e) {
            e = e || window.event;
            e.preventDefault();
            // calculate the new cursor position:
            pos1 = pos3 - e.clientX;
            pos2 = pos4 - e.clientY;
            pos3 = e.clientX;
            pos4 = e.clientY;
            
            // set the element's new position:
            updatePosition(elmnt.offsetTop - pos2, elmnt.offsetLeft - pos1);
        }

        function elementTouchDrag(e) {
            if (e.touches.length === 1) {
                pos1 = pos3 - e.touches[0].clientX;
                pos2 = pos4 - e.touches[0].clientY;
                pos3 = e.touches[0].clientX;
                pos4 = e.touches[0].clientY;
                
                updatePosition(elmnt.offsetTop - pos2, elmnt.offsetLeft - pos1);
            }
        }

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

        function closeDragElement() {
            // stop moving when button is released:
            document.onmouseup = null;
            document.onmousemove = null;
            document.ontouchend = null;
            document.ontouchmove = null;
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
            const btn = document.getElementById(`keybindBtn-${key}`);
            if (btn) {
                btn.textContent = cleanKeyCodeText(code);
                btn.className = 'subway-key-btn';
            }
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
        const btn = document.getElementById(`keybindBtn-${keyName}`);
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
            btn.textContent = cleanKeyCodeText(e.code);
            btn.classList.remove('waiting');
            
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
            card.addEventListener('click', function () {
                const slug = this.dataset.map;
                loadMap(slug);
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

        // Bind customization mapping buttons
        for (const key of ['jump', 'duck', 'left', 'right']) {
            const btn = document.getElementById(`keybindBtn-${key}`);
            if (btn) {
                btn.addEventListener('click', function () {
                    startRemap(key);
                });
            }
        }
    });

})();
