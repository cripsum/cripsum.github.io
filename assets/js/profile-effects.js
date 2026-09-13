/**
 * Cripsum™ — Effetti della pagina e del cursore
 *
 * Un solo motore per il profilo e per le anteprime dell'editor: ogni effetto
 * si disegna dentro un contenitore qualsiasi, che sia lo strato fisso della
 * pagina (.profile-effects-layer) o un riquadro di 120 pixel. Quello che vedi
 * nell'editor e' lo stesso codice che gira sul profilo.
 *
 *   const fx = CripsumPageEffects.mount(container, 'aurora', { preview: false });
 *   fx.destroy();
 *
 *   const cursor = CripsumCursorEffects.mount(container, 'trail_stars', { preview: true });
 *
 * COME SONO FATTI
 *
 * - Gli effetti con molti elementi (particelle, stelle, petali, aurora, onde,
 *   griglia, rumore) disegnano su un canvas: niente centinaia di nodi DOM e
 *   niente filtri sfocati grandi quanto lo schermo, che erano la parte pesante
 *   della versione precedente. Aurora e onde lavorano a bassa risoluzione e il
 *   browser le ingrandisce: la morbidezza viene gratis.
 * - Gli effetti che sono solo luce o texture (bagliore del mouse, riflettore,
 *   luce ambientale, scanline, grana) restano CSS, in profile-effects.css.
 * - I colori sono --accent e --accent-2 del contenitore, riletti ogni tanto:
 *   se cambi colore nell'editor l'effetto si adegua da solo.
 * - Con "riduci animazioni" del sistema viene disegnato un fotogramma fermo.
 * - Il glass rain resta in profile.js (usa RaindropFX): qui c'e' solo la sua
 *   anteprima per l'editor.
 */
(function (global) {
    'use strict';

    const TAU = Math.PI * 2;
    const rand = (min, max) => min + Math.random() * (max - min);
    const pick = (list) => list[Math.floor(Math.random() * list.length)];
    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
    const reduceMotion = () => !!global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const coarsePointer = () => !!global.matchMedia && global.matchMedia('(hover: none), (pointer: coarse)').matches;

    // ── Colori ──────────────────────────────────────────────────────────────
    const probe = document.createElement('canvas').getContext('2d');

    /** Qualsiasi colore CSS in [r, g, b], oppure null. */
    const parseColor = (value) => {
        const text = String(value || '').trim();
        if (!text || !probe) return null;
        probe.fillStyle = '#010203';
        probe.fillStyle = text;
        const out = probe.fillStyle;
        if (out === '#010203' && text.toLowerCase() !== '#010203') return null;
        if (out[0] === '#') {
            return [1, 3, 5].map((i) => parseInt(out.slice(i, i + 2), 16));
        }
        const match = out.match(/rgba?\(([^)]+)\)/);
        return match ? match[1].split(',').slice(0, 3).map((n) => parseFloat(n)) : null;
    };

    const mix = (a, b, t) => a.map((v, i) => Math.round(v + (b[i] - v) * t));
    const rgba = (rgb, alpha) => `rgba(${rgb[0]}, ${rgb[1]}, ${rgb[2]}, ${alpha})`;
    const WHITE = [255, 255, 255];

    const readColors = (el) => {
        const cs = getComputedStyle(el);
        const a = parseColor(cs.getPropertyValue('--accent')) || [15, 91, 255];
        const b = parseColor(cs.getPropertyValue('--accent-2')) || mix(a, WHITE, 0.45);
        return { a, b, soft: mix(a, WHITE, 0.55), soft2: mix(b, WHITE, 0.55) };
    };

    // ── Canvas e ciclo ──────────────────────────────────────────────────────
    /**
     * Un canvas grande quanto il contenitore. `scale` < 1 disegna a bassa
     * risoluzione (il CSS lo allarga): per le cose sfumate e' piu' bello e costa
     * una frazione.
     */
    const createStage = (root, { scale = 1, dprMax = 1.5, className = '' } = {}) => {
        const canvas = document.createElement('canvas');
        canvas.className = `pfx-canvas ${className}`.trim();
        root.appendChild(canvas);
        const stage = { canvas, ctx: canvas.getContext('2d'), w: 1, h: 1, ratio: 1 };
        stage.resize = () => {
            const rect = root.getBoundingClientRect();
            stage.w = Math.max(1, rect.width);
            stage.h = Math.max(1, rect.height);
            stage.ratio = Math.min(global.devicePixelRatio || 1, dprMax) * scale;
            canvas.width = Math.max(1, Math.round(stage.w * stage.ratio));
            canvas.height = Math.max(1, Math.round(stage.h * stage.ratio));
            stage.ctx.setTransform(stage.ratio, 0, 0, stage.ratio, 0, 0);
        };
        stage.resize();
        return stage;
    };

    const runLoop = (tick) => {
        let raf = 0;
        let last = performance.now();
        let stopped = false;
        const frame = (now) => {
            if (stopped) return;
            const dt = Math.min(0.05, Math.max(0, (now - last) / 1000));
            last = now;
            if (!document.hidden) tick(dt, now / 1000);
            raf = requestAnimationFrame(frame);
        };
        raf = requestAnimationFrame(frame);
        return () => {
            stopped = true;
            cancelAnimationFrame(raf);
        };
    };

    /** Uno sprite rotondo e sfumato, da disegnare molte volte senza ricalcolarlo. */
    const glowSprite = (() => {
        const cache = new Map();
        return (rgb, core = 1) => {
            const key = rgb.join(',') + '|' + core;
            if (cache.has(key)) return cache.get(key);
            const c = document.createElement('canvas');
            c.width = c.height = 64;
            const g = c.getContext('2d');
            const grad = g.createRadialGradient(32, 32, 0, 32, 32, 32);
            grad.addColorStop(0, rgba(mix(rgb, WHITE, 0.7), core));
            grad.addColorStop(0.22, rgba(rgb, 0.8 * core));
            grad.addColorStop(0.55, rgba(rgb, 0.22));
            grad.addColorStop(1, rgba(rgb, 0));
            g.fillStyle = grad;
            g.fillRect(0, 0, 64, 64);
            cache.set(key, c);
            return c;
        };
    })();

    /** Puntatore vero (finestra) o finto (anteprime e schermi touch), in pixel del contenitore. */
    const createPointer = (root, { auto }) => {
        const state = { x: 0, y: 0, tx: 0, ty: 0, seen: false };
        let onMove = null;
        if (!auto) {
            onMove = (event) => {
                const rect = root.getBoundingClientRect();
                state.tx = event.clientX - rect.left;
                state.ty = event.clientY - rect.top;
                if (!state.seen) {
                    state.x = state.tx;
                    state.y = state.ty;
                    state.seen = true;
                }
            };
            global.addEventListener('pointermove', onMove, { passive: true });
        }
        return {
            state,
            /** Aggiorna il bersaglio (finto) e insegue con un po' di ritardo. */
            update(dt, t, w, h, ease = 10) {
                if (auto) {
                    state.tx = w * (0.5 + 0.34 * Math.sin(t * 0.9));
                    state.ty = h * (0.5 + 0.28 * Math.sin(t * 1.3 + 1.2));
                    if (!state.seen) {
                        state.x = state.tx;
                        state.y = state.ty;
                        state.seen = true;
                    }
                } else if (!state.seen) {
                    state.tx = w * 0.5;
                    state.ty = h * 0.35;
                    state.x = state.tx;
                    state.y = state.ty;
                }
                const k = 1 - Math.exp(-ease * dt);
                state.x += (state.tx - state.x) * k;
                state.y += (state.ty - state.y) * k;
                return state;
            },
            destroy() {
                if (onMove) global.removeEventListener('pointermove', onMove);
            },
        };
    };

    // ── Effetti della pagina ────────────────────────────────────────────────
    const PAGE = {};

    /** Particelle: bokeh luminoso che sale piano, su tre profondita'. */
    PAGE.soft_particles = (env) => {
        const stage = createStage(env.root);
        const u = env.preview ? 0.42 : 1;
        let parts = [];
        const spawn = (initial) => {
            const depth = rand(0.35, 1);
            return {
                x: rand(0, stage.w),
                y: initial ? rand(0, stage.h) : stage.h + rand(10, 80) * u,
                r: rand(12, 36) * depth * u,
                vy: rand(12, 34) * depth * u,
                swayA: rand(10, 30) * u,
                swayF: rand(0.2, 0.55),
                phase: rand(0, TAU),
                tone: pick(['a', 'a', 'b', 'soft']),
                // I lontani piccoli e nitidi, i vicini grandi e tenui.
                alpha: rand(0.35, 0.8) * (1.15 - depth * 0.5),
                core: depth > 0.7 ? 0.45 : 1,
            };
        };
        const fill = () => {
            const count = Math.round(clamp((stage.w * stage.h) / (env.preview ? 1100 : 30000), 8, 80));
            parts = Array.from({ length: count }, () => spawn(true));
        };
        fill();
        return {
            resize() { stage.resize(); fill(); },
            tick(dt, t) {
                const { ctx, w, h } = stage;
                const colors = env.colors();
                ctx.clearRect(0, 0, w, h);
                ctx.globalCompositeOperation = 'lighter';
                parts.forEach((p, i) => {
                    p.y -= p.vy * dt;
                    if (p.y < -p.r * 2) parts[i] = p = spawn(false);
                    const x = p.x + Math.sin(t * p.swayF + p.phase) * p.swayA;
                    const fade = clamp(p.y / (h * 0.3), 0, 1) * clamp((h - p.y + 60 * u) / (120 * u), 0, 1);
                    ctx.globalAlpha = p.alpha * fade;
                    const size = p.r * (1.9 + 0.25 * Math.sin(t * 1.4 + p.phase));
                    ctx.drawImage(glowSprite(colors[p.tone], p.core), x - size, p.y - size, size * 2, size * 2);
                });
                ctx.globalAlpha = 1;
                ctx.globalCompositeOperation = 'source-over';
            },
        };
    };

    /** Stelle: cielo su tre piani che scorre piano, scintillii e stelle cadenti. */
    PAGE.stars = (env) => {
        const stage = createStage(env.root);
        const u = env.preview ? 0.5 : 1;
        let stars = [];
        let meteor = null;
        let nextMeteor = rand(1.5, 4);
        const fill = () => {
            const count = Math.round(clamp((stage.w * stage.h) / (env.preview ? 160 : 5200), 40, 340));
            stars = Array.from({ length: count }, () => {
                const z = Math.pow(Math.random(), 1.8) * 0.85 + 0.15;
                return {
                    x: rand(0, stage.w),
                    y: rand(0, stage.h),
                    z,
                    r: (0.35 + z * 1.25) * (env.preview ? 0.8 : 1),
                    tw: rand(0.6, 2.4),
                    phase: rand(0, TAU),
                    tone: Math.random() < 0.7 ? 'white' : pick(['soft', 'soft2']),
                };
            });
        };
        fill();
        return {
            resize() { stage.resize(); fill(); },
            tick(dt, t) {
                const { ctx, w, h } = stage;
                const colors = { ...env.colors(), white: WHITE };
                ctx.clearRect(0, 0, w, h);
                stars.forEach((s) => {
                    s.x -= 3 * s.z * u * dt;
                    s.y += 1.5 * s.z * u * dt;
                    if (s.x < -2) s.x += w + 4;
                    if (s.y > h + 2) s.y -= h + 4;
                    const twinkle = 0.5 + 0.5 * Math.sin(t * s.tw + s.phase);
                    const alpha = (0.25 + 0.75 * twinkle) * (0.35 + 0.65 * s.z);
                    const rgb = colors[s.tone];
                    if (s.r > 1.25 && s.z > 0.8) {
                        const g = s.r * (5 + 3 * twinkle);
                        ctx.globalAlpha = alpha * 0.9;
                        ctx.drawImage(glowSprite(rgb), s.x - g, s.y - g, g * 2, g * 2);
                        ctx.strokeStyle = rgba(rgb, alpha * 0.5);
                        ctx.lineWidth = 0.6;
                        ctx.beginPath();
                        ctx.moveTo(s.x - g * 0.9, s.y);
                        ctx.lineTo(s.x + g * 0.9, s.y);
                        ctx.moveTo(s.x, s.y - g * 0.9);
                        ctx.lineTo(s.x, s.y + g * 0.9);
                        ctx.stroke();
                    }
                    ctx.globalAlpha = alpha;
                    ctx.fillStyle = rgba(rgb, 1);
                    ctx.beginPath();
                    ctx.arc(s.x, s.y, s.r, 0, TAU);
                    ctx.fill();
                });
                ctx.globalAlpha = 1;

                nextMeteor -= dt;
                if (!meteor && nextMeteor <= 0) {
                    meteor = { x: rand(w * 0.2, w * 1.05), y: rand(-h * 0.05, h * 0.35), life: 0, dur: rand(0.7, 1.1), speed: rand(520, 760) * u };
                    nextMeteor = env.preview ? rand(2, 4) : rand(4, 9);
                }
                if (meteor) {
                    meteor.life += dt;
                    const k = meteor.life / meteor.dur;
                    const hx = meteor.x - meteor.speed * meteor.life;
                    const hy = meteor.y + meteor.speed * 0.45 * meteor.life;
                    const len = 140 * u;
                    const grad = ctx.createLinearGradient(hx, hy, hx + len, hy - len * 0.45);
                    const a = Math.sin(Math.PI * clamp(k, 0, 1));
                    grad.addColorStop(0, rgba(WHITE, 0.95 * a));
                    grad.addColorStop(0.3, rgba(colors.soft, 0.5 * a));
                    grad.addColorStop(1, rgba(colors.soft, 0));
                    ctx.strokeStyle = grad;
                    ctx.lineWidth = 1.6 * (env.preview ? 0.7 : 1);
                    ctx.lineCap = 'round';
                    ctx.beginPath();
                    ctx.moveTo(hx, hy);
                    ctx.lineTo(hx + len, hy - len * 0.45);
                    ctx.stroke();
                    if (k >= 1) meteor = null;
                }
            },
        };
    };

    /** Petali di sakura: cadono girando su se stessi, con un po' di vento. */
    PAGE.sakura_falling = (env) => {
        const stage = createStage(env.root);
        const u = env.preview ? 0.5 : 1;
        const sprites = {};
        const petalSprite = (tone) => {
            if (sprites[tone]) return sprites[tone];
            const c = document.createElement('canvas');
            c.width = c.height = 48;
            const g = c.getContext('2d');
            g.translate(24, 24);
            const light = tone === 'tint' ? mix([255, 214, 226], env.colors().soft, 0.35) : [255, 236, 242];
            const mid = tone === 'tint' ? mix([255, 170, 196], env.colors().a, 0.25) : [255, 183, 205];
            const deep = tone === 'tint' ? mix([244, 114, 160], env.colors().b, 0.3) : [244, 128, 170];
            const grad = g.createLinearGradient(0, -18, 0, 18);
            grad.addColorStop(0, rgba(light, 1));
            grad.addColorStop(0.55, rgba(mid, 1));
            grad.addColorStop(1, rgba(deep, 1));
            g.fillStyle = grad;
            g.beginPath();
            g.moveTo(0, 18);
            g.bezierCurveTo(-16, 6, -14, -14, -3, -18);
            g.lineTo(0, -13);
            g.lineTo(3, -18);
            g.bezierCurveTo(14, -14, 16, 6, 0, 18);
            g.fill();
            g.globalCompositeOperation = 'source-atop';
            g.strokeStyle = rgba([255, 255, 255], 0.35);
            g.lineWidth = 1;
            g.beginPath();
            g.moveTo(0, 14);
            g.quadraticCurveTo(1, 0, 0, -12);
            g.stroke();
            sprites[tone] = c;
            return c;
        };
        let petals = [];
        const spawn = (initial) => {
            const depth = rand(0.45, 1);
            return {
                x: rand(-stage.w * 0.1, stage.w * 1.05),
                y: initial ? rand(-stage.h * 0.1, stage.h) : rand(-60, -20) * u,
                size: rand(18, 32) * depth * u,
                depth,
                rot: rand(0, TAU),
                vrot: rand(-1.4, 1.4),
                flip: rand(1.6, 3.2),
                phase: rand(0, TAU),
                vy: rand(34, 70) * depth * u,
                vx: rand(10, 34) * u,
                swayA: rand(14, 40) * u,
                swayF: rand(0.6, 1.3),
                tone: Math.random() < 0.35 ? 'tint' : 'pink',
            };
        };
        const fill = () => {
            const count = Math.round(clamp((stage.w * stage.h) / (env.preview ? 700 : 46000), 6, 44));
            petals = Array.from({ length: count }, () => spawn(true));
        };
        fill();
        return {
            resize() { stage.resize(); fill(); },
            colorsChanged() { delete sprites.tint; },
            tick(dt, t) {
                const { ctx, w, h } = stage;
                ctx.clearRect(0, 0, w, h);
                petals.forEach((p, i) => {
                    p.y += p.vy * dt;
                    p.x += (p.vx + Math.sin(t * p.swayF + p.phase) * p.swayA) * dt;
                    p.rot += p.vrot * dt;
                    if (p.y > h + 40 * u || p.x > w + 60 * u) {
                        petals[i] = p = spawn(false);
                    }
                    const flip = Math.cos(t * p.flip + p.phase);
                    ctx.save();
                    ctx.globalAlpha = 0.45 + p.depth * 0.5;
                    ctx.translate(p.x, p.y);
                    ctx.rotate(p.rot);
                    ctx.scale(Math.abs(flip) < 0.12 ? 0.12 * Math.sign(flip || 1) : flip, 1);
                    ctx.drawImage(petalSprite(p.tone), -p.size / 2, -p.size / 2, p.size, p.size);
                    ctx.restore();
                });
            },
        };
    };

    /**
     * Aurora: tende di luce che ondeggiano in alto.
     *
     * Ogni tenda e' fatta di colonne sottili: uno sprite verticale (buio in
     * basso, acceso poco sopra, che sfuma verso l'alto) disegnato lungo l'onda
     * con altezza e intensita' che cambiano. Niente bordi netti, e la trama a
     * raggi viene da sola.
     */
    PAGE.aurora = (env) => {
        const stage = createStage(env.root, { scale: env.preview ? 0.5 : 0.25, dprMax: 1, className: 'pfx-canvas--soft' });
        const sprites = {};
        const ray = (tone, rgb) => {
            const key = tone + rgb.join();
            if (sprites[key]) return sprites[key];
            const c = document.createElement('canvas');
            c.width = 4;
            c.height = 256;
            const g = c.getContext('2d');
            const grad = g.createLinearGradient(0, 256, 0, 0);
            grad.addColorStop(0, rgba(rgb, 0));
            grad.addColorStop(0.1, rgba(mix(rgb, WHITE, 0.35), 0.95));
            grad.addColorStop(0.22, rgba(rgb, 0.7));
            grad.addColorStop(0.6, rgba(rgb, 0.18));
            grad.addColorStop(1, rgba(rgb, 0));
            g.fillStyle = grad;
            g.fillRect(0, 0, 4, 256);
            sprites[key] = c;
            return c;
        };
        const curtains = [0, 1, 2].map((i) => ({
            base: 0.3 + i * 0.07,
            amp: rand(0.035, 0.06),
            height: rand(0.26, 0.38),
            f1: rand(0.8, 1.4),
            f2: rand(2.2, 3.6),
            s1: rand(0.07, 0.13) * (i % 2 ? -1 : 1),
            s2: rand(0.12, 0.22),
            phase: rand(0, TAU),
            tone: ['a', 'b', 'soft'][i],
            alpha: [0.32, 0.26, 0.18][i],
        }));
        return {
            resize() { stage.resize(); },
            colorsChanged() { Object.keys(sprites).forEach((key) => delete sprites[key]); },
            tick(dt, t) {
                const { ctx, w, h } = stage;
                const colors = env.colors();
                ctx.clearRect(0, 0, w, h);
                ctx.globalCompositeOperation = 'lighter';
                const step = Math.max(1, w / (env.preview ? 70 : 140));
                curtains.forEach((c) => {
                    const sprite = ray(c.tone, colors[c.tone]);
                    for (let x = -step; x <= w + step; x += step) {
                        const nx = x / w;
                        const y = h * c.base
                            + Math.sin(nx * TAU * c.f1 + t * c.s1 + c.phase) * h * c.amp
                            + Math.sin(nx * TAU * c.f2 - t * c.s2) * h * c.amp * 0.4;
                        // Intensita' e altezza che scorrono lungo la tenda: i raggi.
                        const shimmer = 0.55 + 0.45 * Math.sin(nx * 38 + t * 0.8 + c.phase) * Math.sin(nx * 11 - t * 0.35);
                        const lift = h * c.height * (0.65 + 0.35 * Math.sin(nx * TAU * 1.7 + t * 0.3 + c.phase));
                        // La tenda sfuma verso i lati dello schermo.
                        const edge = Math.min(1, Math.min(nx, 1 - nx) * 4 + 0.35);
                        ctx.globalAlpha = c.alpha * clamp(shimmer, 0.15, 1) * edge;
                        ctx.drawImage(sprite, x, y - lift, step + 1, lift * 1.12);
                    }
                });
                ctx.globalAlpha = 1;
                ctx.globalCompositeOperation = 'source-over';
            },
        };
    };

    /** Onde: strati sfumati in basso che scorrono a velocita' diverse. */
    PAGE.gradient_waves = (env) => {
        const stage = createStage(env.root, { scale: env.preview ? 0.8 : 0.5, dprMax: 1, className: 'pfx-canvas--soft' });
        const waves = [0, 1, 2, 3].map((i) => ({
            y: 0.64 + i * 0.075,
            amp: rand(0.025, 0.05),
            len: rand(0.9, 1.6),
            speed: rand(0.18, 0.42) * (i % 2 ? -1 : 1),
            phase: rand(0, TAU),
            tone: ['soft2', 'b', 'a', 'soft'][i],
            alpha: [0.1, 0.14, 0.18, 0.22][i],
        }));
        return {
            resize() { stage.resize(); },
            tick(dt, t) {
                const { ctx, w, h } = stage;
                const colors = env.colors();
                ctx.clearRect(0, 0, w, h);
                const step = Math.max(2, w / 80);
                waves.forEach((wv, index) => {
                    const yAt = (x) => h * wv.y
                        + Math.sin((x / w) * TAU * wv.len + t * wv.speed + wv.phase) * h * wv.amp
                        + Math.sin((x / w) * TAU * wv.len * 2.1 - t * wv.speed * 1.6) * h * wv.amp * 0.3;
                    const grad = ctx.createLinearGradient(0, h * (wv.y - wv.amp), 0, h);
                    grad.addColorStop(0, rgba(colors[wv.tone], wv.alpha));
                    grad.addColorStop(1, rgba(colors[wv.tone], wv.alpha * 0.25));
                    ctx.fillStyle = grad;
                    ctx.beginPath();
                    ctx.moveTo(0, h);
                    for (let x = 0; x <= w + step; x += step) ctx.lineTo(x, yAt(x));
                    ctx.lineTo(w, h);
                    ctx.closePath();
                    ctx.fill();
                    if (index === waves.length - 1 || index === 1) {
                        ctx.strokeStyle = rgba(mix(colors[wv.tone], WHITE, 0.4), wv.alpha * 1.4);
                        ctx.lineWidth = Math.max(1, h * 0.003);
                        ctx.beginPath();
                        for (let x = 0; x <= w + step; x += step) {
                            if (x === 0) ctx.moveTo(x, yAt(x));
                            else ctx.lineTo(x, yAt(x));
                        }
                        ctx.stroke();
                    }
                });
            },
        };
    };

    /** Griglia cyber: pavimento synthwave in prospettiva, orizzonte e sole. */
    PAGE.cyber_grid = (env) => {
        const stage = createStage(env.root, { dprMax: 1.25 });
        return {
            resize() { stage.resize(); },
            tick(dt, t) {
                const { ctx, w, h } = stage;
                const colors = env.colors();
                const hy = h * (env.preview ? 0.52 : 0.6);
                const cx = w / 2;
                ctx.clearRect(0, 0, w, h);

                // Pavimento
                const rows = 16;
                const offset = (t * 0.35) % 1;
                ctx.lineCap = 'butt';
                for (let pass = 0; pass < 2; pass++) {
                    ctx.strokeStyle = rgba(colors.a, pass ? 0.75 : 0.16);
                    ctx.lineWidth = pass ? 1 : 3.5;
                    ctx.beginPath();
                    for (let k = 0; k < rows; k++) {
                        const z = (k + offset) / rows;
                        const y = hy + (h - hy) * Math.pow(z, 2.4);
                        ctx.moveTo(0, y);
                        ctx.lineTo(w, y);
                    }
                    const cols = 14;
                    const spread = Math.max(w, h) * 0.16;
                    for (let i = -cols; i <= cols; i++) {
                        ctx.moveTo(cx + i * spread * 0.06, hy);
                        ctx.lineTo(cx + i * spread, h);
                    }
                    ctx.stroke();
                }
                // Il pavimento sfuma verso l'orizzonte.
                ctx.globalCompositeOperation = 'destination-out';
                const fade = ctx.createLinearGradient(0, hy, 0, hy + (h - hy) * 0.55);
                fade.addColorStop(0, 'rgba(0,0,0,1)');
                fade.addColorStop(1, 'rgba(0,0,0,0)');
                ctx.fillStyle = fade;
                ctx.fillRect(0, hy - 1, w, h - hy + 1);
                ctx.globalCompositeOperation = 'source-over';

                // Sole a righe, appena sopra l'orizzonte
                const r = Math.min(w, h) * 0.2;
                const sy = hy - r * 0.35;
                ctx.save();
                ctx.beginPath();
                ctx.rect(0, 0, w, hy);
                ctx.clip();
                const sun = ctx.createLinearGradient(0, sy - r, 0, sy + r);
                sun.addColorStop(0, rgba(colors.soft2, 0.5));
                sun.addColorStop(1, rgba(colors.a, 0.35));
                ctx.fillStyle = sun;
                ctx.beginPath();
                ctx.arc(cx, sy, r, 0, TAU);
                ctx.fill();
                ctx.globalCompositeOperation = 'destination-out';
                for (let i = 0; i < 6; i++) {
                    const bandY = sy + r * (0.05 + i * 0.16) + ((t * 6) % (r * 0.16));
                    ctx.fillRect(cx - r, bandY, r * 2, r * (0.02 + i * 0.012));
                }
                ctx.restore();

                // Linea d'orizzonte che brilla
                const glow = ctx.createLinearGradient(0, hy - h * 0.12, 0, hy + h * 0.05);
                glow.addColorStop(0, rgba(colors.a, 0));
                glow.addColorStop(0.72, rgba(colors.b, 0.22 + 0.06 * Math.sin(t * 1.5)));
                glow.addColorStop(1, rgba(colors.a, 0));
                ctx.fillStyle = glow;
                ctx.fillRect(0, hy - h * 0.12, w, h * 0.17);
                ctx.fillStyle = rgba(mix(colors.a, WHITE, 0.5), 0.85);
                ctx.fillRect(0, hy - 0.5, w, 1.2);
            },
        };
    };

    /** Rumore digitale: grana che vibra, righe di glitch e una banda di scansione. */
    PAGE.digital_noise = (env) => {
        const stage = createStage(env.root, { scale: env.preview ? 0.5 : 0.34, dprMax: 1, className: 'pfx-canvas--pixel' });
        const frames = [];
        const buildFrames = () => {
            frames.length = 0;
            const fw = 160;
            const fh = 100;
            for (let f = 0; f < 5; f++) {
                const c = document.createElement('canvas');
                c.width = fw;
                c.height = fh;
                const g = c.getContext('2d');
                const img = g.createImageData(fw, fh);
                for (let i = 0; i < img.data.length; i += 4) {
                    const v = Math.random() * 255;
                    img.data[i] = img.data[i + 1] = img.data[i + 2] = v;
                    img.data[i + 3] = Math.random() < 0.55 ? Math.random() * 46 : 0;
                }
                g.putImageData(img, 0, 0);
                frames.push(c);
            }
        };
        buildFrames();
        let clock = 0;
        let frame = 0;
        let glitch = 0;
        let nextGlitch = rand(1, 3);
        let bars = [];
        return {
            resize() { stage.resize(); },
            tick(dt, t) {
                const { ctx, w, h } = stage;
                const colors = env.colors();
                clock += dt;
                if (clock < 1 / 14) return;
                clock = 0;
                frame = (frame + 1) % frames.length;
                ctx.clearRect(0, 0, w, h);
                ctx.globalAlpha = 1;
                const pattern = ctx.createPattern(frames[frame], 'repeat');
                ctx.save();
                ctx.translate(-rand(0, 160), -rand(0, 100));
                ctx.fillStyle = pattern;
                ctx.fillRect(0, 0, w + 160, h + 100);
                ctx.restore();

                // Banda di scansione che scende
                const bandY = ((t * 0.12) % 1.3 - 0.15) * h;
                const band = ctx.createLinearGradient(0, bandY - h * 0.08, 0, bandY + h * 0.08);
                band.addColorStop(0, rgba(colors.a, 0));
                band.addColorStop(0.5, rgba(colors.soft, 0.1));
                band.addColorStop(1, rgba(colors.b, 0));
                ctx.fillStyle = band;
                ctx.fillRect(0, bandY - h * 0.08, w, h * 0.16);

                // Glitch: poche righe colorate spostate, per un attimo
                nextGlitch -= 1 / 14;
                if (nextGlitch <= 0 && glitch <= 0) {
                    glitch = rand(0.15, 0.35);
                    nextGlitch = env.preview ? rand(1.2, 2.5) : rand(2.5, 6);
                    bars = Array.from({ length: Math.round(rand(3, 7)) }, () => ({
                        y: rand(0, h), hgt: rand(1, 5) * (env.preview ? 0.6 : 1), x: rand(-w * 0.2, w * 0.6), len: rand(w * 0.2, w * 0.9),
                        tone: pick(['a', 'b', 'soft']),
                    }));
                }
                if (glitch > 0) {
                    glitch -= 1 / 14;
                    bars.forEach((b) => {
                        ctx.fillStyle = rgba(colors[b.tone], 0.55);
                        ctx.fillRect(b.x + rand(-6, 6), b.y, b.len, b.hgt);
                        ctx.fillStyle = rgba(WHITE, 0.25);
                        ctx.fillRect(b.x + rand(-10, 10), b.y + b.hgt, b.len * 0.4, 1);
                    });
                }
            },
        };
    };

    /** Bagliore del mouse: una luce morbida che insegue il puntatore. */
    PAGE.cursor_glow = (env) => {
        const light = document.createElement('i');
        light.className = 'pfx-light';
        env.root.appendChild(light);
        const pointer = createPointer(env.root, { auto: env.preview || coarsePointer() });
        return {
            tick(dt, t) {
                const rect = env.root.getBoundingClientRect();
                const p = pointer.update(dt, t, rect.width, rect.height, 7);
                light.style.transform = `translate3d(${p.x}px, ${p.y}px, 0) translate(-50%, -50%)`;
            },
            destroy() { pointer.destroy(); },
        };
    };

    /** Riflettore: la pagina resta in penombra, tranne un cerchio di luce sul mouse. */
    PAGE.spotlight = (env) => {
        const spot = document.createElement('i');
        spot.className = 'pfx-spot';
        env.root.appendChild(spot);
        const pointer = createPointer(env.root, { auto: env.preview || coarsePointer() });
        return {
            tick(dt, t) {
                const rect = env.root.getBoundingClientRect();
                const p = pointer.update(dt, t, rect.width, rect.height, 9);
                spot.style.setProperty('--px', `${p.x}px`);
                spot.style.setProperty('--py', `${p.y}px`);
            },
            destroy() { pointer.destroy(); },
        };
    };

    const cssOnly = (parts) => (env) => {
        parts.forEach((cls) => {
            const el = document.createElement('i');
            el.className = cls;
            env.root.appendChild(el);
        });
        return {};
    };

    PAGE.ambient = cssOnly(['pfx-blob pfx-blob--1', 'pfx-blob pfx-blob--2', 'pfx-blob pfx-blob--3']);
    PAGE.scanlines = cssOnly(['pfx-lines', 'pfx-band', 'pfx-vignette']);
    PAGE.bg_grain = cssOnly(['pfx-grain', 'pfx-vignette']);

    /** Solo per le anteprime: il glass rain vero e' RaindropFX in profile.js. */
    PAGE.glass_rain = (env) => {
        const count = env.preview ? 11 : 24;
        for (let i = 0; i < count; i++) {
            const drop = document.createElement('i');
            drop.className = 'pfx-drop';
            drop.style.setProperty('--x', `${rand(3, 95)}%`);
            drop.style.setProperty('--y', `${rand(-10, 80)}%`);
            drop.style.setProperty('--s', rand(0.55, 1.3).toFixed(2));
            drop.style.setProperty('--d', `${rand(-6, 0).toFixed(2)}s`);
            drop.style.setProperty('--t', `${rand(3.5, 7).toFixed(2)}s`);
            env.root.appendChild(drop);
        }
        return {};
    };

    const mountWith = (registry, baseClass) => (container, effect, options = {}) => {
        const noop = { destroy() {}, effect: 'none' };
        const factory = registry[effect];
        if (!container || !factory) return noop;

        const root = document.createElement('div');
        root.className = `${baseClass} ${baseClass}--${effect}${options.preview ? ` ${baseClass}--preview` : ''}`;
        root.setAttribute('aria-hidden', 'true');
        container.appendChild(root);

        let colors = readColors(container);
        const applyColorVars = () => {
            root.style.setProperty('--pfx-a', colors.a.join(', '));
            root.style.setProperty('--pfx-b', colors.b.join(', '));
        };
        applyColorVars();

        let instance = {};
        const env = { root, container, preview: !!options.preview, colors: () => colors, cursorAt: null };
        if (options.preview && baseClass === 'cfx') {
            // Nell'anteprima del cursore si vede anche il "mouse" che si muove.
            const arrow = document.createElement('i');
            arrow.className = 'cfx-arrow';
            root.appendChild(arrow);
            env.cursorAt = (x, y) => { arrow.style.transform = `translate3d(${x}px, ${y}px, 0)`; };
        }
        try {
            instance = factory(env) || {};
        } catch (error) {
            root.remove();
            return noop;
        }

        let stopLoop = null;
        if (instance.tick) {
            if (reduceMotion()) {
                instance.tick(1 / 60, 12);
            } else {
                stopLoop = runLoop((dt, t) => instance.tick(dt, t));
            }
        }

        // I colori cambiano dall'editor: una lettura ogni tanto basta.
        const colorTimer = setInterval(() => {
            const next = readColors(container);
            if (next.a.join() !== colors.a.join() || next.b.join() !== colors.b.join()) {
                colors = next;
                applyColorVars();
                instance.colorsChanged?.();
            }
        }, 700);

        let observer = null;
        if (instance.resize && global.ResizeObserver) {
            let pending = 0;
            observer = new ResizeObserver(() => {
                cancelAnimationFrame(pending);
                pending = requestAnimationFrame(() => instance.resize());
            });
            observer.observe(root);
        }

        return {
            effect,
            destroy() {
                stopLoop?.();
                clearInterval(colorTimer);
                observer?.disconnect();
                try { instance.destroy?.(); } catch (_) { /* niente da fare */ }
                root.remove();
            },
        };
    };

    // ── Effetti del cursore ─────────────────────────────────────────────────
    const CURSOR = {};

    /** Cerchio che segue il puntatore con un leggero ritardo, e il punto esatto al centro. */
    CURSOR.follower = (env) => {
        const ring = document.createElement('i');
        ring.className = 'cfx-ring';
        const dot = document.createElement('i');
        dot.className = 'cfx-dot';
        env.root.append(ring, dot);
        const pointer = createPointer(env.root, { auto: env.preview });
        const exact = { x: 0, y: 0 };
        return {
            tick(dt, t) {
                const rect = env.root.getBoundingClientRect();
                const p = pointer.update(dt, t, rect.width, rect.height, 9);
                env.root.classList.toggle('is-waiting', !pointer.state.seen);
                exact.x = pointer.state.tx;
                exact.y = pointer.state.ty;
                ring.style.transform = `translate3d(${p.x}px, ${p.y}px, 0) translate(-50%, -50%)`;
                dot.style.transform = `translate3d(${exact.x}px, ${exact.y}px, 0) translate(-50%, -50%)`;
                env.cursorAt?.(exact.x, exact.y);
            },
            destroy() { pointer.destroy(); },
        };
    };

    /** Scie: particelle, stelle o cuori che nascono dove passa il puntatore. */
    const trail = (shape) => (env) => {
        const stage = createStage(env.root, { dprMax: 2 });
        const pointer = createPointer(env.root, { auto: env.preview });
        const parts = [];
        const u = env.preview ? 0.55 : 1;
        let lastX = null;
        let lastY = null;
        let carry = 0;
        const heartColors = [[239, 68, 68], [244, 63, 94], [236, 72, 153]];

        const drawStar = (ctx, size) => {
            ctx.beginPath();
            for (let i = 0; i < 10; i++) {
                const r = i % 2 ? size * 0.45 : size;
                const a = (Math.PI / 5) * i - Math.PI / 2;
                ctx.lineTo(Math.cos(a) * r, Math.sin(a) * r);
            }
            ctx.closePath();
            ctx.fill();
        };
        const drawHeart = (ctx, size) => {
            const s = size;
            ctx.beginPath();
            ctx.moveTo(0, s * 0.35);
            ctx.bezierCurveTo(-s, -s * 0.35, -s * 0.45, -s * 1.05, 0, -s * 0.45);
            ctx.bezierCurveTo(s * 0.45, -s * 1.05, s, -s * 0.35, 0, s * 0.35);
            ctx.closePath();
            ctx.fill();
        };

        return {
            resize() { stage.resize(); },
            tick(dt, t) {
                const { ctx, w, h } = stage;
                const colors = env.colors();
                // In anteprima il puntatore finto si muove; sul profilo quello vero.
                if (env.preview) pointer.update(dt, t, w, h, 40);
                const x = env.preview ? pointer.state.x : pointer.state.tx;
                const y = env.preview ? pointer.state.y : pointer.state.ty;
                env.cursorAt?.(x, y);

                if (pointer.state.seen || env.preview) {
                    if (lastX === null) { lastX = x; lastY = y; }
                    const dist = Math.hypot(x - lastX, y - lastY);
                    carry += dist;
                    const spacing = (shape === 'dot' ? 6 : 16) * u;
                    const steps = Math.min(12, Math.floor(carry / spacing));
                    for (let i = 1; i <= steps; i++) {
                        const k = i / steps;
                        const px = lastX + (x - lastX) * k;
                        const py = lastY + (y - lastY) * k;
                        if (shape === 'dot') {
                            parts.push({ x: px, y: py, size: rand(2.5, 6) * u, color: Math.random() < 0.7 ? colors.a : colors.soft, alpha: 1, vx: rand(-0.4, 0.4) * 30 * u, vy: rand(-0.4, 0.4) * 30 * u });
                        } else if (shape === 'star') {
                            parts.push({ x: px, y: py, size: rand(5, 9) * u, color: Math.random() < 0.6 ? colors.a : [251, 191, 36], alpha: 1, vx: rand(-1, 1) * 40 * u, vy: rand(20, 60) * u, rot: rand(0, TAU), vrot: rand(-4, 4) });
                        } else {
                            parts.push({ x: px, y: py, size: rand(5, 9) * u, color: Math.random() < 0.75 ? pick(heartColors) : colors.a, alpha: 1, vx: rand(-0.8, 0.8) * 30 * u, vy: -rand(25, 60) * u, rot: rand(-0.3, 0.3), vrot: 0 });
                        }
                    }
                    if (steps > 0) carry = 0;
                    lastX = x;
                    lastY = y;
                }

                ctx.clearRect(0, 0, w, h);
                ctx.globalCompositeOperation = shape === 'dot' ? 'lighter' : 'source-over';
                for (let i = parts.length - 1; i >= 0; i--) {
                    const p = parts[i];
                    p.x += p.vx * dt;
                    p.y += p.vy * dt;
                    p.alpha -= dt * 1.4;
                    p.size *= Math.pow(0.35, dt);
                    if (p.rot !== undefined) p.rot += p.vrot * dt;
                    if (p.alpha <= 0 || p.size < 0.4) {
                        parts.splice(i, 1);
                        continue;
                    }
                    ctx.globalAlpha = p.alpha;
                    if (shape === 'dot') {
                        const g = p.size * 3;
                        ctx.drawImage(glowSprite(p.color), p.x - g, p.y - g, g * 2, g * 2);
                    } else {
                        ctx.save();
                        ctx.translate(p.x, p.y);
                        ctx.rotate(p.rot || 0);
                        ctx.fillStyle = rgba(p.color, 1);
                        if (shape === 'star') drawStar(ctx, p.size);
                        else drawHeart(ctx, p.size);
                        ctx.restore();
                    }
                }
                ctx.globalAlpha = 1;
                ctx.globalCompositeOperation = 'source-over';
            },
            destroy() { pointer.destroy(); },
        };
    };

    CURSOR.trail = trail('dot');
    CURSOR.trail_stars = trail('star');
    CURSOR.trail_hearts = trail('heart');

    /** Gattino: insegue il puntatore con calma e si gira dalla parte giusta. */
    CURSOR.cat_follower = (env) => {
        const cat = document.createElement('i');
        cat.className = 'cfx-cat';
        cat.textContent = '🐈';
        env.root.appendChild(cat);
        const pointer = createPointer(env.root, { auto: env.preview });
        let facing = 1;
        return {
            tick(dt, t) {
                const rect = env.root.getBoundingClientRect();
                const before = pointer.state.x;
                const p = pointer.update(dt, t, rect.width, rect.height, env.preview ? 2.2 : 3.5);
                env.root.classList.toggle('is-waiting', !pointer.state.seen);
                const dx = p.x - before;
                if (Math.abs(dx) > 0.05) facing = dx > 0 ? 1 : -1;
                const hop = Math.abs(Math.sin(t * 9)) * Math.min(1, Math.hypot(pointer.state.tx - p.x, pointer.state.ty - p.y) / 40) * (env.preview ? 3 : 5);
                cat.style.transform = `translate3d(${p.x}px, ${p.y - hop}px, 0) translate(-50%, -50%) scaleX(${-facing})`;
                env.cursorAt?.(pointer.state.tx, pointer.state.ty);
            },
            destroy() { pointer.destroy(); },
        };
    };

    const mountCursor = (container, effect, options = {}) => {
        // Sugli schermi touch il cursore non esiste: niente effetto (come prima).
        if (!options.preview && coarsePointer()) return { destroy() {}, effect: 'none' };
        return mountWith(CURSOR, 'cfx')(container, effect, options);
    };

    global.CripsumPageEffects = {
        EFFECTS: Object.keys(PAGE),
        mount: mountWith(PAGE, 'pfx'),
    };

    global.CripsumCursorEffects = {
        EFFECTS: Object.keys(CURSOR),
        mount: mountCursor,
    };
})(window);
