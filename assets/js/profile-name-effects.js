/**
 * Cripsum™ — Effetti del nome
 *
 * La parte degli effetti del nome che il CSS da solo non puo' fare:
 * - Rimbalzo divide il nome in lettere (ognuna salta un attimo dopo l'altra);
 * - Scintille, Fuoco e Acqua fanno nascere particelle sulle lettere
 *   (stelline, braci, bollicine).
 *
 * Lo usano il profilo e l'editor (anteprima e riquadri):
 *
 *   CripsumNameEffects.apply(el, 'fire', { text: 'Premium Tester' });
 *
 * `el` riceve la classe `cn-name`; gli stili sono in
 * assets/css/profile-name-effects.css. Le particelle si fermano quando il nome
 * non si vede o la scheda e' in secondo piano.
 */
(function (global) {
    'use strict';

    const EFFECTS = ['none', 'gradient', 'rainbow', 'glow', 'sparkles', 'fire', 'water', 'glitch', 'neon', 'bounce'];
    const PER_LETTER = ['bounce'];
    const rand = (min, max) => min + Math.random() * (max - min);
    const reduceMotion = () => !!global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const state = new WeakMap();

    /** Una particella: dove nasce, quanto e' grande e quanto dura. */
    const PARTICLES = {
        sparkles: {
            every: 170,
            spawn(el) {
                const colors = ['#ffffff', 'var(--cn-glow)', 'var(--accent, #8b5cf6)', '#fff4b8'];
                return {
                    className: 'cn-particle cn-particle--spark',
                    x: `${rand(-4, 104)}%`,
                    y: `${rand(-10, 95)}%`,
                    size: `${rand(0.16, 0.38).toFixed(2)}em`,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    life: rand(700, 1100),
                };
            },
        },
        fire: {
            every: 140,
            spawn() {
                return {
                    className: 'cn-particle cn-particle--ember',
                    x: `${rand(2, 98)}%`,
                    y: `${rand(5, 45)}%`,
                    size: `${rand(0.08, 0.2).toFixed(2)}em`,
                    dx: `${rand(-0.35, 0.35).toFixed(2)}em`,
                    life: rand(900, 1500),
                };
            },
        },
        water: {
            every: 260,
            spawn() {
                return {
                    className: 'cn-particle cn-particle--bubble',
                    x: `${rand(4, 96)}%`,
                    y: `${rand(55, 95)}%`,
                    size: `${rand(0.1, 0.24).toFixed(2)}em`,
                    dx: `${rand(-0.18, 0.18).toFixed(2)}em`,
                    life: rand(1300, 2200),
                };
            },
        },
    };

    const splitLetters = (el, text) => {
        el.textContent = '';
        Array.from(text).forEach((char, index) => {
            const span = document.createElement('span');
            span.className = char === ' ' ? 'name-char space-char' : 'name-char';
            span.style.setProperty('--char-index', String(index));
            span.textContent = char === ' ' ? ' ' : char;
            el.appendChild(span);
        });
    };

    const stopParticles = (el) => {
        const s = state.get(el);
        if (!s) return;
        clearInterval(s.timer);
        s.observer?.disconnect();
        el.querySelectorAll('.cn-particle').forEach((node) => node.remove());
        state.delete(el);
    };

    const startParticles = (el, effect) => {
        const def = PARTICLES[effect];
        if (!def || reduceMotion()) return;
        const s = { visible: true, timer: 0, observer: null };
        if ('IntersectionObserver' in global) {
            s.observer = new IntersectionObserver((entries) => {
                s.visible = entries.some((entry) => entry.isIntersecting);
            });
            s.observer.observe(el);
        }
        s.timer = setInterval(() => {
            if (!s.visible || document.hidden || !el.isConnected) return;
            // Mai piu' di una ventina di particelle vive per nome.
            if (el.querySelectorAll('.cn-particle').length > 22) return;
            const p = def.spawn(el);
            const node = document.createElement('span');
            node.className = p.className;
            node.setAttribute('aria-hidden', 'true');
            node.style.setProperty('--x', p.x);
            node.style.setProperty('--y', p.y);
            node.style.setProperty('--size', p.size);
            node.style.setProperty('--t', `${Math.round(p.life)}ms`);
            if (p.color) node.style.setProperty('--c', p.color);
            if (p.dx) node.style.setProperty('--dx', p.dx);
            el.appendChild(node);
            setTimeout(() => node.remove(), p.life + 50);
        }, def.every);
        state.set(el, s);
    };

    /**
     * Applica un effetto a un elemento.
     *
     * @param {HTMLElement} el
     * @param {string} effect
     * @param {{text?: string}} [options] testo del nome; senza, resta quello attuale
     */
    const apply = (el, effect, options = {}) => {
        if (!el) return;
        const name = EFFECTS.includes(effect) ? effect : 'none';
        const text = options.text !== undefined
            ? String(options.text)
            : (el.dataset.text || el.textContent.replace(/ /g, ' ').trim());

        // L'anteprima dell'editor richiama apply a ogni tasto: se nulla cambia,
        // le lettere e le particelle restano come sono e l'animazione non riparte.
        const applied = el.classList.contains('cn-name') && el.dataset.nameEffect === name && el.dataset.text === text;
        const lettersOk = !PER_LETTER.includes(name) || el.querySelector('.name-char');
        const particlesOk = !PARTICLES[name] || reduceMotion() || state.has(el);
        if (applied && lettersOk && particlesOk) return;

        stopParticles(el);
        el.classList.add('cn-name');
        el.dataset.nameEffect = name;
        el.dataset.text = text;

        if (PER_LETTER.includes(name)) {
            splitLetters(el, text);
        } else if (el.querySelector('.name-char') || el.textContent !== text) {
            el.textContent = text;
        }

        startParticles(el, name);
    };

    global.CripsumNameEffects = { EFFECTS, apply };
})(window);
