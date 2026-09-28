/*
 * Player degli edit caricati come file: window.CripsumPlayer.mount(host, opzioni).
 *
 *   const player = CripsumPlayer.mount(el, {
 *       src, poster, title, autoplay: true,
 *       next: { title, cover },    // facoltativo: la card "Prossimo" a fine video
 *       onNext: () => {...},
 *       strings: { play: 'Riproduci', ... },
 *   });
 *   player.handleKey(event)  // spazio/K, M, F, J/L, < >: true se l'ha usato
 *   player.destroy()
 *
 * Loop senza stacchi: il tag <video loop> torna all'inizio con un salto
 * (il decoder si svuota e riparte). Qui, con "Ripeti" acceso, c'e' un
 * secondo <video> uguale gia' fermo al primo fotogramma: parte un attimo
 * prima che il primo finisca e i due si scambiano sul fotogramma giusto.
 *
 * Volume, muto e "ripeti" si ricordano (localStorage, per chi guarda); la
 * velocita' vale finche' resti sulla pagina.
 */
(() => {
    'use strict';

    if (window.CripsumPlayer) return;

    const svg = (path, cls = '') => `<svg class="${cls}" viewBox="0 0 24 24" aria-hidden="true" focusable="false">${path}</svg>`;
    const I = {
        play: svg('<path fill="currentColor" d="M8 5.6v12.8c0 .9 1 1.4 1.7.9l9.6-6.4a1.1 1.1 0 0 0 0-1.8L9.7 4.7C9 4.2 8 4.7 8 5.6z"/>', 'ep-i-play'),
        pause: svg('<rect fill="currentColor" x="6" y="5" width="4.2" height="14" rx="1.4"/><rect fill="currentColor" x="13.8" y="5" width="4.2" height="14" rx="1.4"/>'),
        volHigh: svg('<path fill="currentColor" d="M4 9.5v5c0 .6.4 1 1 1h2.6l3.7 3.2c.6.5 1.7.1 1.7-.8V6.1c0-.9-1.1-1.3-1.7-.8L7.6 8.5H5c-.6 0-1 .4-1 1z"/><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M15.5 9a4 4 0 0 1 0 6M18 6.5a7.5 7.5 0 0 1 0 11"/>'),
        volLow: svg('<path fill="currentColor" d="M4 9.5v5c0 .6.4 1 1 1h2.6l3.7 3.2c.6.5 1.7.1 1.7-.8V6.1c0-.9-1.1-1.3-1.7-.8L7.6 8.5H5c-.6 0-1 .4-1 1z"/><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M15.5 9a4 4 0 0 1 0 6"/>'),
        volMute: svg('<path fill="currentColor" d="M4 9.5v5c0 .6.4 1 1 1h2.6l3.7 3.2c.6.5 1.7.1 1.7-.8V6.1c0-.9-1.1-1.3-1.7-.8L7.6 8.5H5c-.6 0-1 .4-1 1z"/><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M16 9.5l5 5M21 9.5l-5 5"/>'),
        loop: svg('<path fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" d="M17 3.5l3 3-3 3M20 6.5H8a4 4 0 0 0-4 4v1M7 20.5l-3-3 3-3M4 17.5h12a4 4 0 0 0 4-4v-1"/>'),
        expand: svg('<path fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" d="M4 9V5.5C4 4.7 4.7 4 5.5 4H9M15 4h3.5c.8 0 1.5.7 1.5 1.5V9M20 15v3.5c0 .8-.7 1.5-1.5 1.5H15M9 20H5.5c-.8 0-1.5-.7-1.5-1.5V15"/>'),
        compress: svg('<path fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" d="M9 4v3.5C9 8.3 8.3 9 7.5 9H4M20 9h-3.5C15.7 9 15 8.3 15 7.5V4M15 20v-3.5c0-.8.7-1.5 1.5-1.5H20M4 15h3.5c.8 0 1.5.7 1.5 1.5V20"/>'),
        replay: svg('<path fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" d="M4 12a8 8 0 1 0 2.3-5.6M4 4v4.5h4.5"/>'),
        back: svg('<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5 5-5M18 17l-5-5 5-5"/>'),
        fwd: svg('<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M13 17l5-5-5-5M6 17l5-5-5-5"/>'),
        check: svg('<path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.5 4.5L19 7.5"/>'),
    };

    const DEFAULT_STRINGS = {
        play: 'Riproduci',
        pause: 'Pausa',
        mute: 'Togli l\'audio',
        unmute: 'Attiva l\'audio',
        volume: 'Volume',
        loop: 'Ripeti',
        speed: 'Velocità',
        normal: 'Normale',
        fullscreen: 'Schermo intero',
        exitFullscreen: 'Esci dallo schermo intero',
        seek: 'Posizione nel video',
        of: 'di',
        replay: 'Rivedi',
        next: 'Prossimo',
        decimal: ',',
    };

    const SPEEDS = [0.5, 0.75, 1, 1.25, 1.5, 2];
    const STORE = { volume: 'cripsum.player.volume', muted: 'cripsum.player.muted', loop: 'cripsum.player.loop' };
    const HIDE_AFTER = 2200;
    const HAS_RVFC = typeof HTMLVideoElement !== 'undefined' && 'requestVideoFrameCallback' in HTMLVideoElement.prototype;

    // La velocita' scelta vale per tutti i player della pagina, finche' ci resti.
    let sharedRate = 1;
    let uid = 0;

    const store = {
        get(key, fallback) {
            try {
                const value = localStorage.getItem(key);
                return value === null ? fallback : value;
            } catch (_) {
                return fallback;
            }
        },
        set(key, value) {
            try {
                localStorage.setItem(key, String(value));
            } catch (_) {
                // niente: e' solo una comodita'
            }
        },
    };

    const fmt = (seconds) => {
        const s = Math.max(0, Math.floor(Number(seconds) || 0));
        return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
    };

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const clamp = (n, min, max) => Math.min(max, Math.max(min, n));

    const template = (o, S, id) => {
        const rateLabel = (r) => `${String(r).replace('.', S.decimal)}×`;
        return `
        <div class="ep" data-state="paused" data-controls="visible" data-started="false">
            <div class="ep__stage">
                <video class="ep__video is-active" playsinline preload="auto"></video>
            </div>
            ${o.poster ? `<img class="ep__poster" src="${esc(o.poster)}" alt="">` : ''}
            <div class="ep__shade" aria-hidden="true"></div>
            <div class="ep__spinner" aria-hidden="true"></div>
            <div class="ep__flash" aria-hidden="true"></div>
            <div class="ep__seek ep__seek--back" aria-hidden="true">${I.back} 5s</div>
            <div class="ep__seek ep__seek--fwd" aria-hidden="true">5s ${I.fwd}</div>
            <button type="button" class="ep__center" data-ep="toggle" aria-label="${esc(S.play)}">${I.play}</button>

            <div class="ep__end">
                <button type="button" class="ep__next" data-ep="next" hidden>
                    <span class="ep__next-cover"><img alt="" data-ep-next-cover></span>
                    <span><small>${esc(S.next)}</small><strong data-ep-next-title></strong></span>
                </button>
                <button type="button" class="ep__pill" data-ep="replay">${I.replay} ${esc(S.replay)}</button>
            </div>

            <div class="ep__bar">
                <div class="ep__progress" role="slider" tabindex="0" aria-label="${esc(S.seek)}" aria-valuemin="0" aria-valuemax="0" aria-valuenow="0" aria-valuetext="0:00">
                    <div class="ep__track"><span class="ep__buffered"></span><span class="ep__hoverfill"></span><span class="ep__played"></span></div>
                    <span class="ep__thumb" aria-hidden="true"></span>
                    <span class="ep__tip" aria-hidden="true">0:00</span>
                </div>
                <div class="ep__row">
                    <button type="button" class="ep__btn ep__swap" data-ep="toggle" aria-label="${esc(S.play)}">${I.play}${I.pause}</button>
                    <span class="ep__time" aria-hidden="true"><span data-ep-current>0:00</span><i>/</i><span data-ep-duration>0:00</span></span>
                    <span class="ep__spacer"></span>
                    <div class="ep__vol">
                        <button type="button" class="ep__btn ep__vol-btn" data-ep="mute" aria-label="${esc(S.mute)}">${I.volHigh}${I.volLow}${I.volMute}</button>
                        <div class="ep__vol-slider" role="slider" tabindex="0" aria-label="${esc(S.volume)}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100">
                            <div class="ep__vol-track"><span class="ep__vol-fill"></span><span class="ep__vol-knob"></span></div>
                        </div>
                    </div>
                    <div class="ep__speed">
                        <button type="button" class="ep__btn ep__speed-btn" data-ep="speed" id="ep-speed-${id}" aria-haspopup="listbox" aria-expanded="false" aria-controls="ep-speed-menu-${id}" aria-label="${esc(S.speed)}"><span data-ep-rate>1×</span></button>
                        <ul class="ep__speed-menu" id="ep-speed-menu-${id}" role="listbox" tabindex="-1" aria-label="${esc(S.speed)}">
                            ${SPEEDS.slice().reverse().map((r, i) => `
                                <li class="ep__speed-option" id="ep-speed-${id}-${String(r).replace('.', '-')}" role="option" data-rate="${r}" aria-selected="${r === 1}" style="--i: ${i}">
                                    <span>${rateLabel(r)}${r === 1 ? ` <em>${esc(S.normal)}</em>` : ''}</span>${I.check}
                                </li>`).join('')}
                        </ul>
                    </div>
                    <button type="button" class="ep__btn" data-ep="loop" aria-label="${esc(S.loop)}" aria-pressed="false">${I.loop}</button>
                    <button type="button" class="ep__btn ep__swap" data-ep="fullscreen" aria-label="${esc(S.fullscreen)}">${I.expand}${I.compress}</button>
                </div>
            </div>
            <div class="ep__mini" aria-hidden="true"><i></i></div>
        </div>`;
    };

    const mount = (host, options = {}) => {
        const S = { ...DEFAULT_STRINGS, ...(options.strings || {}) };
        const id = ++uid;
        host.innerHTML = template(options, S, id);

        const root = host.querySelector('.ep');
        const q = (sel) => root.querySelector(sel);
        const stage = q('.ep__stage');
        const progress = q('.ep__progress');
        const tip = q('.ep__tip');
        const volSlider = q('.ep__vol-slider');
        const volBox = q('.ep__vol');
        const volBtn = q('[data-ep="mute"]');
        const toggles = root.querySelectorAll('[data-ep="toggle"]');
        const rowToggle = q('.ep__row [data-ep="toggle"]');
        const loopBtn = q('[data-ep="loop"]');
        const fsBtn = q('[data-ep="fullscreen"]');
        const speedBox = q('.ep__speed');
        const speedBtn = q('[data-ep="speed"]');
        const speedMenu = q('.ep__speed-menu');
        const speedOptions = [...speedMenu.querySelectorAll('[role="option"]')];
        const flash = q('.ep__flash');
        const nextBtn = q('[data-ep="next"]');
        const current = q('[data-ep-current]');
        const duration = q('[data-ep-duration]');
        const coarse = window.matchMedia?.('(hover: none)').matches;

        // active: il video che si vede. spare: il gemello per il loop (solo con "Ripeti").
        let active = q('.ep__video');
        let spare = null;
        let swapping = false;
        let frameHandle = 0;
        let rafHandle = 0;
        let frameStep = 1 / 30;
        let lastFrame = null;

        const prefs = {
            volume: clamp(Number.parseFloat(store.get(STORE.volume, '1')) || 0, 0, 1),
            muted: store.get(STORE.muted, '0') === '1',
            loop: store.get(STORE.loop, '0') === '1',
            rate: sharedRate,
        };
        if (!Number.isFinite(prefs.volume)) prefs.volume = 1;

        const timers = { hide: 0 };
        let dragging = null;
        let hoverBar = false;
        let destroyed = false;

        /* ── Stato ── */

        const setState = (state) => {
            root.dataset.state = state;
            const playing = state === 'playing' || state === 'loading';
            toggles.forEach((button) => button.setAttribute('aria-label', playing ? S.pause : S.play));
            rowToggle.classList.toggle('is-alt', playing);
            q('.ep__center').innerHTML = playing ? I.pause : I.play;
            if (state !== 'playing') showControls();
            else scheduleHide();
        };

        const isSpeedOpen = () => speedBox.classList.contains('is-open');

        const showControls = () => {
            root.dataset.controls = 'visible';
            clearTimeout(timers.hide);
        };

        const scheduleHide = () => {
            clearTimeout(timers.hide);
            if (active.paused || dragging || hoverBar || isSpeedOpen()) return;
            timers.hide = setTimeout(() => {
                if (!active.paused && !dragging && !hoverBar && !isSpeedOpen()) root.dataset.controls = 'hidden';
            }, HIDE_AFTER);
        };

        const poke = () => {
            showControls();
            scheduleHide();
        };

        const pop = (icon) => {
            flash.innerHTML = icon;
            flash.classList.remove('is-on');
            void flash.offsetWidth;
            flash.classList.add('is-on');
        };

        const play = () => {
            const attempt = active.play();
            if (attempt && typeof attempt.catch === 'function') {
                // Bloccato dal browser (link aperto da fuori): resta il tasto grande.
                attempt.catch(() => setState('paused'));
            }
        };

        const toggle = (withFeedback = true) => {
            if (root.dataset.state === 'ended') {
                replay();
                return;
            }
            if (active.paused) {
                play();
                if (withFeedback) pop(I.play);
            } else {
                cancelSwap();
                active.pause();
                if (withFeedback) pop(I.pause);
            }
        };

        const seekTo = (time) => {
            if (!Number.isFinite(active.duration)) return;
            cancelSwap();
            active.currentTime = clamp(time, 0, active.duration);
            paintTime();
        };

        const seekBy = (delta) => {
            if (!Number.isFinite(active.duration)) return;
            seekTo(active.currentTime + delta);
            const bubble = q(delta < 0 ? '.ep__seek--back' : '.ep__seek--fwd');
            bubble.classList.remove('is-on');
            void bubble.offsetWidth;
            bubble.classList.add('is-on');
            poke();
        };

        /* ── Tempo e barra ── */

        const paintTime = () => {
            const total = Number.isFinite(active.duration) ? active.duration : 0;
            const pct = total ? (active.currentTime / total) * 100 : 0;
            root.style.setProperty('--ep-played', `${pct}%`);
            current.textContent = fmt(active.currentTime);
            progress.setAttribute('aria-valuenow', String(Math.floor(active.currentTime)));
            progress.setAttribute('aria-valuetext', `${fmt(active.currentTime)} ${S.of} ${fmt(total)}`);
        };

        const paintBuffered = () => {
            const total = active.duration;
            if (!Number.isFinite(total) || !active.buffered.length) return;
            let end = 0;
            for (let i = 0; i < active.buffered.length; i += 1) {
                if (active.buffered.start(i) <= active.currentTime + 0.5) end = Math.max(end, active.buffered.end(i));
            }
            root.style.setProperty('--ep-buffered', `${(end / total) * 100}%`);
        };

        const ratioAt = (event, el) => {
            const rect = el.getBoundingClientRect();
            return clamp((event.clientX - rect.left) / rect.width, 0, 1);
        };

        progress.addEventListener('pointermove', (event) => {
            const r = ratioAt(event, progress);
            root.style.setProperty('--ep-hover', `${r * 100}%`);
            tip.textContent = fmt(r * (active.duration || 0));
            if (dragging === 'seek') seekTo(r * active.duration);
        });

        progress.addEventListener('pointerdown', (event) => {
            if (event.button !== 0 || !Number.isFinite(active.duration)) return;
            dragging = 'seek';
            progress.setPointerCapture(event.pointerId);
            progress.classList.add('is-dragging');
            progress.dataset.resume = active.paused ? '0' : '1';
            cancelSwap();
            active.pause();
            const r = ratioAt(event, progress);
            root.style.setProperty('--ep-hover', `${r * 100}%`);
            seekTo(r * active.duration);
            showControls();
        });

        const endDrag = () => {
            if (dragging !== 'seek') return;
            dragging = null;
            progress.classList.remove('is-dragging');
            if (progress.dataset.resume === '1') play();
            scheduleHide();
        };
        progress.addEventListener('pointerup', endDrag);
        progress.addEventListener('pointercancel', endDrag);

        progress.addEventListener('keydown', (event) => {
            const step = { ArrowLeft: -5, ArrowRight: 5, ArrowDown: -5, ArrowUp: 5 }[event.key];
            if (step) {
                event.preventDefault();
                event.stopPropagation();
                seekBy(step);
            } else if (event.key === 'Home' || event.key === 'End') {
                event.preventDefault();
                event.stopPropagation();
                seekTo(event.key === 'Home' ? 0 : (active.duration || 0) - 0.1);
            }
        });

        /* ── Volume (vale per il video che si vede; il gemello resta muto) ── */

        const applyAudio = () => {
            active.volume = prefs.volume;
            active.muted = prefs.muted || prefs.volume === 0;
            if (spare) {
                spare.volume = prefs.volume;
                spare.muted = true;
            }
        };

        const paintVolume = () => {
            const level = prefs.muted ? 0 : prefs.volume;
            root.style.setProperty('--ep-vol', `${level * 100}%`);
            volSlider.setAttribute('aria-valuenow', String(Math.round(level * 100)));
            const icon = level === 0 ? 2 : (level < 0.5 ? 1 : 0);
            volBtn.querySelectorAll('svg').forEach((el, i) => el.classList.toggle('is-shown', i === icon));
            volBtn.setAttribute('aria-label', level === 0 ? S.unmute : S.mute);
        };

        const setVolume = (level) => {
            prefs.volume = clamp(level, 0, 1);
            prefs.muted = prefs.volume === 0;
            store.set(STORE.volume, prefs.volume.toFixed(2));
            store.set(STORE.muted, prefs.muted ? '1' : '0');
            applyAudio();
            paintVolume();
        };

        const toggleMute = () => {
            if (prefs.muted || prefs.volume === 0) {
                if (prefs.volume === 0) prefs.volume = 0.6;
                prefs.muted = false;
            } else {
                prefs.muted = true;
            }
            store.set(STORE.muted, prefs.muted ? '1' : '0');
            store.set(STORE.volume, prefs.volume.toFixed(2));
            applyAudio();
            paintVolume();
        };

        volSlider.addEventListener('pointerdown', (event) => {
            if (event.button !== 0) return;
            dragging = 'volume';
            volSlider.setPointerCapture(event.pointerId);
            volBox.classList.add('is-open');
            setVolume(ratioAt(event, q('.ep__vol-track')));
        });
        volSlider.addEventListener('pointermove', (event) => {
            if (dragging === 'volume') setVolume(ratioAt(event, q('.ep__vol-track')));
        });
        const endVolume = () => {
            if (dragging !== 'volume') return;
            dragging = null;
            volBox.classList.remove('is-open');
            scheduleHide();
        };
        volSlider.addEventListener('pointerup', endVolume);
        volSlider.addEventListener('pointercancel', endVolume);
        volSlider.addEventListener('keydown', (event) => {
            const step = { ArrowLeft: -0.1, ArrowDown: -0.1, ArrowRight: 0.1, ArrowUp: 0.1 }[event.key];
            if (!step) return;
            event.preventDefault();
            event.stopPropagation();
            setVolume((prefs.muted ? 0 : prefs.volume) + step);
        });

        /* ── Velocita' ── */

        const rateText = (r) => `${String(r).replace('.', S.decimal)}×`;

        const setRate = (r) => {
            prefs.rate = r;
            sharedRate = r;
            active.playbackRate = r;
            if (spare) spare.playbackRate = r;
            q('[data-ep-rate]').textContent = rateText(r);
            speedBtn.setAttribute('aria-label', `${S.speed}: ${rateText(r)}`);
            speedBtn.classList.toggle('is-on', r !== 1);
            speedOptions.forEach((o) => o.setAttribute('aria-selected', Number(o.dataset.rate) === r ? 'true' : 'false'));
        };

        let speedActive = 0;
        const highlightSpeed = (index) => {
            speedActive = (index + speedOptions.length) % speedOptions.length;
            speedOptions.forEach((o, i) => o.classList.toggle('is-active', i === speedActive));
            speedMenu.setAttribute('aria-activedescendant', speedOptions[speedActive].id);
        };

        const openSpeed = () => {
            speedBox.classList.add('is-open');
            speedBtn.setAttribute('aria-expanded', 'true');
            highlightSpeed(speedOptions.findIndex((o) => Number(o.dataset.rate) === prefs.rate));
            speedMenu.focus({ preventScroll: true });
            showControls();
        };

        const closeSpeed = (focusButton = true) => {
            if (!isSpeedOpen()) return;
            speedBox.classList.remove('is-open');
            speedBtn.setAttribute('aria-expanded', 'false');
            speedMenu.removeAttribute('aria-activedescendant');
            if (focusButton) speedBtn.focus({ preventScroll: true });
            scheduleHide();
        };

        speedMenu.addEventListener('keydown', (event) => {
            const actions = {
                ArrowDown: () => highlightSpeed(speedActive + 1),
                ArrowUp: () => highlightSpeed(speedActive - 1),
                Home: () => highlightSpeed(0),
                End: () => highlightSpeed(speedOptions.length - 1),
                Enter: () => { setRate(Number(speedOptions[speedActive].dataset.rate)); closeSpeed(); },
                ' ': () => { setRate(Number(speedOptions[speedActive].dataset.rate)); closeSpeed(); },
                Escape: () => closeSpeed(),
            };
            if (event.key === 'Tab') {
                closeSpeed(false);
                return;
            }
            if (!actions[event.key]) return;
            event.preventDefault();
            event.stopPropagation();
            actions[event.key]();
        });
        speedOptions.forEach((option, index) => {
            option.addEventListener('mousemove', () => { if (speedActive !== index) highlightSpeed(index); });
            option.addEventListener('click', (event) => {
                event.stopPropagation();
                setRate(Number(option.dataset.rate));
                closeSpeed();
            });
        });
        speedMenu.addEventListener('focusout', (event) => {
            if (isSpeedOpen() && !speedBox.contains(event.relatedTarget)) closeSpeed(false);
        });
        const outside = (event) => {
            if (isSpeedOpen() && !speedBox.contains(event.target)) closeSpeed(false);
        };
        document.addEventListener('pointerdown', outside);

        /* ── Loop senza stacchi ── */

        // Tempo (nel video) dell'ultimo fotogramma. La durata dichiarata e'
        // spesso quella dell'audio, che finisce qualche centesimo dopo.
        let tailTime = null;
        // Millisecondi tra play() di un video fermo e il momento in cui il
        // suo orologio parte davvero. Si misura nella prova e a ogni giro.
        let startLag = 50;
        // Millisecondi tra un giro di rendering e quando quel giro si vede.
        let paintLag = 16;
        let stallTimer = 0;
        let startTimer = 0;
        let swapJob = null;

        const videoEnd = () => (tailTime !== null ? tailTime + frameStep : active.duration);
        const frameMs = () => (frameStep / (prefs.rate || 1)) * 1000;

        const rewind = (el) => {
            try {
                el.currentTime = 0;
            } catch (_) {
                // niente
            }
        };

        // Prova generale, appena il gemello e' pronto: salta poco prima della
        // fine e va fino in fondo, muto e nascosto. Cosi' si sa dov'e' l'ultimo
        // fotogramma (un salto dritto alla fine non lo dice: oltre l'ultimo
        // fotogramma il browser ne mostra uno a caso) e quanto ci mette a
        // ripartire da fermo. Poi torna all'inizio e aspetta.
        const probeTail = (twin) => {
            if (!HAS_RVFC) return;
            const run = () => {
                if (tailTime !== null || twin !== spare || destroyed || swapping || !Number.isFinite(twin.duration)) return;
                const target = Math.max(0, twin.duration - 0.6);
                let seen = -1;
                let finished = false;
                let guard = 0;
                const end = () => {
                    if (finished) return;
                    finished = true;
                    clearTimeout(guard);
                    if (twin !== spare || destroyed) return;
                    if (seen > twin.duration / 2 || twin.duration < 1.2) tailTime = seen;
                    twin.pause();
                    twin.muted = true;
                    twin.volume = prefs.volume;
                    twin.playbackRate = prefs.rate;
                    if (!swapping) rewind(twin);
                };
                const onPlayFrame = (now, meta) => {
                    if (finished) return;
                    seen = Math.max(seen, meta.mediaTime);
                    twin.requestVideoFrameCallback(onPlayFrame);
                };
                const onSeekFrame = (now, meta) => {
                    if (finished || twin !== spare) return;
                    // Il primo fotogramma mostrato puo' essere ancora quello iniziale.
                    if (meta.mediaTime < target - 0.25) {
                        twin.requestVideoFrameCallback(onSeekFrame);
                        return;
                    }
                    const from = meta.mediaTime;
                    seen = from;
                    const calledAt = performance.now();
                    twin.requestVideoFrameCallback((n, m) => {
                        if (finished) return;
                        const lag = m.expectedDisplayTime - calledAt - (m.mediaTime - from) * 1000;
                        if (lag > -40 && lag < 500) startLag = Math.max(0, lag);
                        onPlayFrame(n, m);
                    });
                    const attempt = twin.play();
                    if (attempt && typeof attempt.catch === 'function') {
                        attempt.catch(() => {
                            if (finished || twin.muted) return end();
                            // Audio non permesso senza un clic: si riprova muto.
                            twin.muted = true;
                            twin.play().catch(end);
                        });
                    }
                };
                twin.addEventListener('ended', () => setTimeout(end, 80), { once: true });
                guard = setTimeout(end, 4000);
                twin.playbackRate = 1;
                // Audio acceso come nello scambio (fa partire piu' lento) ma a un
                // volume che non si sente: a zero il browser lo tratta da muto e
                // parte prima. Su iPhone il volume non si tocca: resta muto.
                twin.volume = 0.0001;
                if (twin.volume < 0.01) twin.muted = prefs.muted || prefs.volume === 0;
                twin.requestVideoFrameCallback(onSeekFrame);
                twin.currentTime = target;
            };
            // Un attimo dopo, per non pesare sull'avvio del video che si vede.
            const later = () => setTimeout(run, 700);
            if (twin.readyState >= 2) later();
            else twin.addEventListener('loadeddata', later, { once: true });
        };

        const makeTwin = () => {
            const twin = document.createElement('video');
            twin.className = 'ep__video';
            twin.playsInline = true;
            twin.preload = 'auto';
            twin.muted = true;
            twin.playbackRate = prefs.rate;
            twin.src = active.currentSrc || active.src;
            twin.setAttribute('aria-hidden', 'true');
            stage.appendChild(twin);
            bindVideo(twin);
            probeTail(twin);
            return twin;
        };

        const dropTwin = () => {
            if (!spare) return;
            try {
                spare.pause();
                spare.removeAttribute('src');
                spare.load();
            } catch (_) {
                // niente
            }
            spare.remove();
            spare = null;
        };

        const stopWatching = () => {
            if (frameHandle && typeof active.cancelVideoFrameCallback === 'function') active.cancelVideoFrameCallback(frameHandle);
            frameHandle = 0;
            cancelAnimationFrame(rafHandle);
            rafHandle = 0;
            clearTimeout(stallTimer);
            stallTimer = 0;
            clearTimeout(startTimer);
            startTimer = 0;
        };

        // Scambio in due tempi, preparato poco prima della fine. Il gemello,
        // fermo sul primo fotogramma (che ha gia' a schermo), riceve play() in
        // modo che il suo orologio scatti proprio quando finisce l'ultimo
        // fotogramma; in quell'istante diventa lui quello visibile. Nessun
        // fotogramma perso da una parte o dall'altra.
        const swap = (endsAt = performance.now()) => {
            const from = active;
            const to = spare;
            if (!to || swapping || to.readyState < 2 || to.seeking || to.currentTime > 0.001) return false;
            swapping = true;
            stopWatching();
            to.playbackRate = prefs.rate;
            to.volume = prefs.volume;
            to.muted = prefs.muted || prefs.volume === 0;

            const job = { to, flipped: false, raf: 0, fallback: 0, starter: 0, calledAt: 0 };
            swapJob = job;

            const flip = () => {
                if (job.flipped || swapJob !== job) return;
                job.flipped = true;
                cancelAnimationFrame(job.raf);
                to.classList.add('is-active');
                from.classList.remove('is-active');
                to.removeAttribute('aria-hidden');
                from.setAttribute('aria-hidden', 'true');
                from.muted = true;
                active = to;
                spare = from;
                lastFrame = null;
                paintTime();
            };

            const settle = () => {
                if (swapJob !== job) return;
                flip();
                clearTimeout(job.fallback);
                swapJob = null;
                swapping = false;
                from.pause();
                rewind(from);
                watch();
            };

            const tick = (ts) => {
                job.raf = 0;
                if (swapJob !== job || job.flipped) return;
                if (ts + paintLag + 4 >= endsAt) {
                    flip();
                    // Senza rVFC si chiude appena sono successe entrambe le cose.
                    if (!HAS_RVFC && job.calledAt) settle();
                    return;
                }
                job.raf = requestAnimationFrame(tick);
            };

            // Primo fotogramma nuovo del gemello: da qui si impara il ritardo.
            const onMoving = (now, meta) => {
                if (swapJob !== job) return;
                const lag = meta.expectedDisplayTime - job.calledAt - (meta.mediaTime / (prefs.rate || 1)) * 1000;
                // Arrivato prima del cambio: la stima era lunga, si prende la misura com'e'.
                if (lag > -40 && lag < 500) startLag = job.flipped ? startLag * 0.6 + Math.max(0, lag) * 0.4 : Math.max(0, lag);
                settle();
            };

            const start = () => {
                job.starter = 0;
                if (swapJob !== job) return;
                job.calledAt = performance.now();
                const attempt = to.play();
                if (HAS_RVFC) to.requestVideoFrameCallback(onMoving);
                // Se il gemello non si fa vedere, lo scambio si chiude lo stesso.
                job.fallback = setTimeout(settle, Math.max(0, endsAt - job.calledAt) + 500);
                if (attempt && typeof attempt.catch === 'function') attempt.catch(() => cancelSwap());
                if (!HAS_RVFC && job.flipped) settle();
            };

            job.raf = requestAnimationFrame(tick);
            const delay = endsAt - startLag - performance.now();
            if (delay > 2) job.starter = setTimeout(start, delay);
            else start();
            return true;
        };

        // Pausa o salto mentre il gemello sta partendo di nascosto: si ferma
        // e torna all'inizio. Se si vede gia' lui, lo scambio e' fatto.
        function cancelSwap() {
            clearTimeout(startTimer);
            startTimer = 0;
            const job = swapJob;
            if (!job || job.flipped) return;
            swapJob = null;
            cancelAnimationFrame(job.raf);
            clearTimeout(job.fallback);
            clearTimeout(job.starter);
            swapping = false;
            job.to.pause();
            job.to.muted = true;
            rewind(job.to);
        }

        const loopArmed = () => prefs.loop && spare && !swapping && !startTimer && !active.paused && !active.seeking
            && Number.isFinite(active.duration);

        // Lo scambio si prepara quando manca il ritardo di avvio del gemello
        // (almeno un paio di giri dello schermo, per cambiare video in tempo).
        // Se quel momento cade prima del prossimo fotogramma, lo si aspetta
        // col timer invece di arrivare tardi.
        const planSwap = (endsAt) => {
            const lead = Math.max(startLag, paintLag + 20);
            const wait = endsAt - lead - performance.now();
            if (wait > frameMs() * 1.5) return false;
            if (wait <= 2) return swap(endsAt);
            startTimer = setTimeout(() => {
                startTimer = 0;
                if (loopArmed()) swap(endsAt);
                else watch();
            }, wait);
            return true;
        };

        // Nessun fotogramma nuovo, il tempo corre e siamo in fondo: quello
        // appena visto era l'ultimo (serve solo se la prova non ha risposto).
        const onStall = () => {
            stallTimer = 0;
            if (!loopArmed() || !lastFrame || tailTime !== null) return;
            if (active.duration - active.currentTime > 0.25) return;
            tailTime = lastFrame.mediaTime;
            swap();
        };

        const onFrame = (now, meta) => {
            frameHandle = 0;
            if (destroyed) return;
            // Durata di un fotogramma, per sapere quando finisce l'ultimo.
            if (lastFrame && meta.presentedFrames > lastFrame.presentedFrames && meta.mediaTime > lastFrame.mediaTime) {
                const stepNow = (meta.mediaTime - lastFrame.mediaTime) / (meta.presentedFrames - lastFrame.presentedFrames);
                if (stepNow > 0.005 && stepNow < 0.2) frameStep = frameStep * 0.8 + stepNow * 0.2;
            }
            const shown = meta.expectedDisplayTime - now;
            if (shown > 0 && shown < 80) paintLag = paintLag * 0.9 + shown * 0.1;
            lastFrame = { mediaTime: meta.mediaTime, presentedFrames: meta.presentedFrames };
            if (loopArmed()) {
                const endsAt = meta.expectedDisplayTime + ((videoEnd() - meta.mediaTime) / (prefs.rate || 1)) * 1000;
                if (planSwap(endsAt)) return;
            }
            frameHandle = active.requestVideoFrameCallback(onFrame);
            clearTimeout(stallTimer);
            if (loopArmed() && tailTime === null) stallTimer = setTimeout(onStall, frameMs() * 1.6);
        };

        const onRaf = (ts) => {
            rafHandle = 0;
            if (destroyed) return;
            if (loopArmed()) {
                const endsAt = ts + paintLag + ((videoEnd() - active.currentTime) / (prefs.rate || 1)) * 1000;
                if (planSwap(endsAt)) return;
            }
            rafHandle = requestAnimationFrame(onRaf);
        };

        const watch = () => {
            stopWatching();
            if (!prefs.loop || destroyed) return;
            if (HAS_RVFC) frameHandle = active.requestVideoFrameCallback(onFrame);
            else rafHandle = requestAnimationFrame(onRaf);
        };

        const setLoop = (on) => {
            prefs.loop = on;
            store.set(STORE.loop, on ? '1' : '0');
            loopBtn.classList.toggle('is-on', on);
            loopBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
            if (on) {
                if (!spare) spare = makeTwin();
                watch();
                if (root.dataset.state === 'ended') replay();
            } else {
                cancelSwap();
                stopWatching();
                dropTwin();
            }
        };

        /* ── Schermo intero ── */

        const isFullscreen = () => document.fullscreenElement === root || document.webkitFullscreenElement === root;

        const toggleFullscreen = () => {
            if (isFullscreen()) {
                (document.exitFullscreen || document.webkitExitFullscreen)?.call(document);
                return;
            }
            if (root.requestFullscreen) root.requestFullscreen().catch(() => {});
            else if (root.webkitRequestFullscreen) root.webkitRequestFullscreen();
            // iPhone: solo il video puo' andare a schermo intero, coi controlli di iOS.
            else if (active.webkitEnterFullscreen) active.webkitEnterFullscreen();
        };

        const onFullscreen = () => {
            const on = isFullscreen();
            fsBtn.classList.toggle('is-alt', on);
            fsBtn.setAttribute('aria-label', on ? S.exitFullscreen : S.fullscreen);
        };
        document.addEventListener('fullscreenchange', onFullscreen);
        document.addEventListener('webkitfullscreenchange', onFullscreen);

        /* ── Fine: resta fermo, con "Rivedi" e "Prossimo" ── */

        const replay = () => {
            seekTo(0);
            play();
        };

        if (options.next && options.onNext) {
            nextBtn.hidden = false;
            q('[data-ep-next-title]').textContent = options.next.title || '';
            const cover = q('[data-ep-next-cover]');
            if (options.next.cover) cover.src = options.next.cover;
            else cover.closest('.ep__next-cover').remove();
        }

        /* ── Eventi dei video: conta solo quello che si vede ── */

        function bindVideo(el) {
            const mine = (handler) => (event) => {
                if (event.target === active && !destroyed) handler(event);
            };
            el.addEventListener('play', mine(() => {
                root.dataset.started = 'true';
                setState('playing');
                watch();
            }));
            el.addEventListener('playing', mine(() => setState('playing')));
            el.addEventListener('pause', mine(() => {
                if (root.dataset.state !== 'ended' && !swapping) setState('paused');
            }));
            el.addEventListener('waiting', mine(() => {
                if (!active.paused && !swapping) setState('loading');
            }));
            el.addEventListener('loadedmetadata', mine(() => {
                duration.textContent = fmt(active.duration);
                progress.setAttribute('aria-valuemax', String(Math.floor(active.duration)));
                paintTime();
            }));
            el.addEventListener('timeupdate', mine(paintTime));
            el.addEventListener('progress', mine(paintBuffered));
            el.addEventListener('ratechange', mine(() => {
                if (active.playbackRate !== prefs.rate) active.playbackRate = prefs.rate;
            }));
            el.addEventListener('ended', mine(() => {
                // Arrivato in fondo mentre il gemello sta gia' partendo: ci pensa lo scambio.
                if (swapping) return;
                if (prefs.loop) {
                    // Rete di sicurezza: lo scambio non e' partito in tempo.
                    if (swap()) return;
                    seekTo(0);
                    play();
                    return;
                }
                setState('ended');
            }));
        }

        bindVideo(active);

        /* ── Clic, tocchi, mouse ── */

        root.addEventListener('click', (event) => {
            const action = event.target.closest('[data-ep]')?.dataset.ep;
            if (action === 'toggle') return toggle(false);
            if (action === 'mute') return toggleMute();
            if (action === 'loop') return setLoop(!prefs.loop);
            if (action === 'fullscreen') return toggleFullscreen();
            if (action === 'replay') return replay();
            if (action === 'next') return options.onNext?.();
            if (action === 'speed') return isSpeedOpen() ? closeSpeed() : openSpeed();

            // Clic sul video: pausa/riprendi. Col dito il primo tocco mostra i controlli.
            if (event.target.closest('.ep__bar, .ep__end')) return;
            if (coarse && root.dataset.controls === 'hidden') {
                poke();
                return;
            }
            toggle(true);
        });

        root.addEventListener('dblclick', (event) => {
            if (event.target.closest('.ep__bar, .ep__end, button')) return;
            toggleFullscreen();
        });

        root.addEventListener('pointermove', (event) => {
            if (event.pointerType === 'mouse') poke();
        });
        root.addEventListener('pointerleave', () => {
            if (!active.paused && !dragging && !isSpeedOpen()) root.dataset.controls = 'hidden';
        });
        const bar = q('.ep__bar');
        bar.addEventListener('pointerenter', () => { hoverBar = true; showControls(); });
        bar.addEventListener('pointerleave', () => { hoverBar = false; scheduleHide(); });
        root.addEventListener('focusin', poke);

        /* ── Avvio ── */

        applyAudio();
        paintVolume();
        setRate(prefs.rate);
        if (options.poster) active.poster = options.poster;
        if (options.title) active.setAttribute('aria-label', options.title);
        active.src = options.src || '';
        loopBtn.classList.toggle('is-on', prefs.loop);
        loopBtn.setAttribute('aria-pressed', prefs.loop ? 'true' : 'false');
        if (prefs.loop && options.src) spare = makeTwin();
        if (options.autoplay) play();

        return {
            element: root,
            get video() {
                return active;
            },
            // Spazio/K pausa, M muto, F schermo intero, J/L 5 secondi, < > velocita'.
            handleKey(event) {
                if (destroyed || event.ctrlKey || event.metaKey || event.altKey) return false;
                const target = event.target;
                if (target instanceof HTMLElement && (target.isContentEditable || target.closest('input, textarea, select'))) return false;
                // Su un tasto o un link lo spazio lo preme, non mette in pausa.
                if (event.key === ' ' && target instanceof HTMLElement && target.closest('button, a, [role="slider"], [role="listbox"]')) return false;
                const key = event.key.length === 1 ? event.key.toLowerCase() : event.key;
                const stepRate = (dir) => {
                    const index = SPEEDS.indexOf(prefs.rate);
                    setRate(SPEEDS[clamp(index + dir, 0, SPEEDS.length - 1)]);
                    poke();
                };
                const actions = {
                    ' ': () => toggle(true),
                    k: () => toggle(true),
                    m: () => { toggleMute(); poke(); },
                    f: toggleFullscreen,
                    j: () => seekBy(-5),
                    l: () => seekBy(5),
                    '<': () => stepRate(-1),
                    '>': () => stepRate(1),
                };
                if (!actions[key]) return false;
                event.preventDefault();
                actions[key]();
                return true;
            },
            destroy() {
                destroyed = true;
                clearTimeout(timers.hide);
                cancelSwap();
                stopWatching();
                document.removeEventListener('pointerdown', outside);
                document.removeEventListener('fullscreenchange', onFullscreen);
                document.removeEventListener('webkitfullscreenchange', onFullscreen);
                if (isFullscreen()) (document.exitFullscreen || document.webkitExitFullscreen)?.call(document);
                [active, spare].forEach((el) => {
                    if (!el) return;
                    try {
                        el.pause();
                        el.removeAttribute('src');
                        el.load();
                    } catch (_) {
                        // niente
                    }
                });
                host.innerHTML = '';
            },
        };
    };

    window.CripsumPlayer = { mount, fmt };
})();
