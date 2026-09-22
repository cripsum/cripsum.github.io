/**
 * Cripsum™ — cursore personalizzato dei profili.
 *
 * Un solo motore per il profilo pubblico, l'anteprima dell'editor e il
 * riquadro "Prova" dell'editor:
 *
 *   const cursor = CripsumCursor.mount(element, {
 *       size: 32,                                        // lato lungo, in px
 *       base: { url: '/uploads/…/cursor_x.png', x: 0, y: 0 },   // punta in % dell'immagine
 *       hover: { url: '…', x: 50, y: 50 },               // sopra cio' che si clicca
 *   });
 *   cursor.update(altraConfigurazione);
 *   cursor.destroy();
 *
 * Ogni immagine si disegna in uno di due modi, scelto da solo:
 * - di sistema: il cursore vero (CSS `cursor: url()`), con l'immagine
 *   ridisegnata alla misura giusta su un canvas. Non ha ritardo, ma i browser
 *   non animano i cursori e rimettono la freccia vicino ai bordi della
 *   finestra quando superano i 32 px;
 * - disegnato dalla pagina: un'immagine che segue il mouse. Serve alle GIF
 *   animate, ai cursori oltre i 32 px e alle immagini di altri siti che il
 *   canvas non puo' leggere.
 *
 * Le regole stanno in un cascade layer con !important: un !important in un
 * layer batte qualsiasi `cursor` del sito, anche con !important (pill, badge,
 * pulsanti disattivati), senza gare di specificita'.
 *
 * "Dove si clicca" non e' una lista di selettori: e' dove la pagina stessa
 * mostrerebbe la manina (`cursor: pointer`). Per saperlo si toglie per un
 * attimo l'elemento sotto il mouse dalle regole e si legge lo stile calcolato
 * (kindOf), una volta sola per elemento.
 */
(function (global) {
    'use strict';

    const NATIVE_MAX = 32;
    const MIN_SIZE = 16;
    const MAX_SIZE = 128;
    const LAYER = 'cripsum-cursor';

    // Cosa si puo' cliccare prima di averlo chiesto alla pagina: evita un
    // attimo di cursore sbagliato sui link la prima volta che ci si passa.
    const CLICKABLE = [
        'a[href]', 'button:not(:disabled)', '[role="button"]', '[role="link"]', '[role="tab"]', '[role="menuitem"]',
        'select', 'summary', 'label[for]',
        'input:is([type="checkbox"], [type="radio"], [type="submit"], [type="button"], [type="reset"], [type="file"], [type="range"], [type="color"])',
    ].join(', ');

    // Dentro questi elementi il cursore lo decide un'altra pagina.
    const FOREIGN = /^(IFRAME|EMBED|OBJECT)$/;

    let counter = 0;

    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

    const sameOrigin = (url) => {
        try {
            return new URL(url, global.location.href).origin === global.location.origin;
        } catch (_) {
            return false;
        }
    };

    // I caricamenti statici diventano PNG: GIF e WebP caricati sono animati.
    const isAnimated = (url) => /\.(gif|webp)(?:[?#]|$)/i.test(url);
    const isCur = (url) => /\.cur(?:[?#]|$)/i.test(url);

    const cssUrl = (url) => `url("${String(url).replace(/["\\\n\r]/g, (ch) => encodeURIComponent(ch))}")`;

    const loadImage = (url, cors) => new Promise((resolve, reject) => {
        const img = new Image();
        if (cors) img.crossOrigin = 'anonymous';
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = url;
    });

    const cleanSize = (value) => clamp(Math.round(Number(value) || NATIVE_MAX), MIN_SIZE, MAX_SIZE);

    /**
     * Misure e punta di un'immagine alla dimensione scelta, e la sua versione
     * per il cursore di sistema quando si puo' usare. null se non si carica.
     */
    const prepare = async (slot, size) => {
        if (!slot || !slot.url) return null;
        const url = String(slot.url);
        const foreign = !sameOrigin(url);
        let img = null;
        let readable = true;
        try {
            img = await loadImage(url, foreign);
        } catch (_) {
            // Un vecchio .cur che il browser non sa mostrare come immagine:
            // resta il cursore di sistema, con la sua punta e la sua misura.
            if (isCur(url)) return { url, raw: true, native: url, w: 0, h: 0, x: 0, y: 0 };
            if (!foreign) return null;
            // Un altro sito senza CORS: l'immagine si vede, il canvas no.
            readable = false;
            try {
                img = await loadImage(url, false);
            } catch (__) {
                return null;
            }
        }

        const naturalW = img.naturalWidth || size;
        const naturalH = img.naturalHeight || size;
        const scale = size / Math.max(naturalW, naturalH);
        const w = Math.max(1, Math.round(naturalW * scale));
        const h = Math.max(1, Math.round(naturalH * scale));
        const shape = {
            url,
            w,
            h,
            x: clamp(Math.round(((Number(slot.x) || 0) / 100) * w), 0, w - 1),
            y: clamp(Math.round(((Number(slot.y) || 0) / 100) * h), 0, h - 1),
            // Ingrandendo si tengono i pixel netti: e' quasi sempre pixel art.
            pixelated: scale >= 2,
            native: '',
        };

        if (readable && !isAnimated(url) && size <= NATIVE_MAX) {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d');
                ctx.imageSmoothingEnabled = !shape.pixelated;
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(img, 0, 0, w, h);
                shape.native = canvas.toDataURL('image/png');
            } catch (_) {
                shape.native = '';
            }
        }
        return shape;
    };

    const cursorValue = (shape) => {
        if (!shape) return '';
        if (!shape.native) return 'none';
        if (shape.raw) return `${cssUrl(shape.native)}, auto`;
        return `${cssUrl(shape.native)} ${shape.x} ${shape.y}, auto`;
    };

    /*
     * Ogni regola salta l'elemento in prova (data-ccur-probe): per quell'attimo
     * vale solo il CSS del sito. Non si usa revert-layer, perche' dentro un
     * !important Chrome lo riporta ai valori del browser, saltando il sito.
     */
    const buildCss = (id, base, hover) => {
        const scope = `[data-ccur="${id}"]`;
        const free = ':not([data-ccur-probe])';
        const baseValue = cursorValue(base);
        // Senza un'immagine per i link resta quella normale.
        const hoverValue = cursorValue(hover) || baseValue;
        const rules = [];
        const rule = (selectors, value) => rules.push(`${selectors.join(', ')} { cursor: ${value} !important; }`);

        if (baseValue) {
            rule([`${scope}${free}`, `${scope} *${free}`], baseValue);
            rule([`${scope} [data-ccur-kind="base"]${free}`], baseValue);
        }
        if (hoverValue) {
            // Prima della prova vale la lista; dopo, quello che ha detto la prova.
            const unknown = `:not([data-ccur-kind])${free}`;
            rule([`${scope} :is(${CLICKABLE})${unknown}`, `${scope} :is(${CLICKABLE}) *${unknown}`], hoverValue);
            rule([`${scope}[data-ccur-kind="hover"]${free}`, `${scope} [data-ccur-kind="hover"]${free}`], hoverValue);
        }
        // Senza l'immagine normale, dove non si clicca resta il cursore del
        // sito: nessuna regola lo tocca.
        rule([`${scope} ::before`, `${scope} ::after`], 'inherit');

        return `@layer ${LAYER} {\n${rules.join('\n')}\n}`;
    };

    /** Il cursore che il sito mostrerebbe da solo su questo elemento. */
    const siteCursor = (el) => {
        el.setAttribute('data-ccur-probe', '');
        const value = global.getComputedStyle(el).cursor;
        el.removeAttribute('data-ccur-probe');
        return value;
    };

    /**
     * "hover" se il sito ci mostra la manina, altrimenti "base". Un elemento
     * senza un cursore suo eredita quello del genitore, che e' gia' il nostro
     * (un url() o none): allora si chiede al genitore.
     */
    const kindOf = (el, scope) => {
        const cached = el.getAttribute('data-ccur-kind');
        if (cached) return cached;
        const asked = [];
        let kind = 'base';
        for (let node = el; node && node.nodeType === 1 && scope.contains(node); node = node.parentElement) {
            const known = node.getAttribute('data-ccur-kind');
            if (known) {
                kind = known;
                break;
            }
            asked.push(node);
            const value = siteCursor(node);
            if (/url\(/.test(value) || value === 'none') continue;
            kind = value === 'pointer' ? 'hover' : 'base';
            break;
        }
        asked.forEach((node) => node.setAttribute('data-ccur-kind', kind));
        return kind;
    };

    const finePointer = () => !global.matchMedia || global.matchMedia('(any-pointer: fine)').matches;

    const mount = (scope, initialConfig) => {
        if (!scope) return { update() {}, destroy() {} };

        const id = `c${++counter}`;
        let version = 0;
        let alive = true;
        let style = null;
        let follower = null;
        let shown = null;
        let shapes = { base: null, hover: null };
        let kind = 'base';
        let inside = false;
        let x = 0;
        let y = 0;

        scope.setAttribute('data-ccur', id);

        const current = () => (inside ? (shapes[kind] || (kind === 'hover' ? shapes.base : null)) : null);

        const render = () => {
            if (!follower) return;
            const shape = current();
            if (!shape || shape.native) {
                if (follower.style.display !== 'none') follower.style.display = 'none';
                shown = null;
                return;
            }
            if (shown !== shape) {
                if (follower.getAttribute('src') !== shape.url) follower.src = shape.url;
                follower.style.width = `${shape.w}px`;
                follower.style.height = `${shape.h}px`;
                follower.style.imageRendering = shape.pixelated ? 'pixelated' : 'auto';
                follower.style.display = 'block';
                shown = shape;
            }
            follower.style.transform = `translate3d(${x - shape.x}px, ${y - shape.y}px, 0)`;
        };

        const isMouse = (event) => !event.pointerType || event.pointerType === 'mouse';

        const onOver = (event) => {
            if (!isMouse(event)) return;
            const target = event.target;
            x = event.clientX;
            y = event.clientY;
            if (!(target instanceof Element) || FOREIGN.test(target.tagName)) {
                inside = false;
            } else {
                inside = true;
                kind = kindOf(target, scope);
            }
            render();
        };
        const onMove = (event) => {
            if (!isMouse(event)) return;
            x = event.clientX;
            y = event.clientY;
            render();
        };
        const onOut = (event) => {
            if (!isMouse(event)) return;
            const to = event.relatedTarget;
            if (!to || !scope.contains(to) || FOREIGN.test(to.tagName)) {
                inside = false;
                render();
            }
        };
        const onBlur = () => {
            inside = false;
            render();
        };

        scope.addEventListener('pointerover', onOver, { passive: true });
        scope.addEventListener('pointermove', onMove, { passive: true });
        scope.addEventListener('pointerout', onOut, { passive: true });
        global.addEventListener('blur', onBlur);

        const ensureFollower = (needed) => {
            if (!needed) {
                follower?.remove();
                follower = null;
                shown = null;
                return;
            }
            if (follower) return;
            follower = document.createElement('img');
            follower.className = 'cripsum-cursor-follower';
            follower.alt = '';
            follower.draggable = false;
            follower.setAttribute('aria-hidden', 'true');
            follower.style.cssText = 'position: fixed; left: 0; top: 0; z-index: 2147483647; display: none; max-width: none; max-height: none; margin: 0; pointer-events: none; user-select: none; will-change: transform;';
            document.body.appendChild(follower);
        };

        const update = async (config) => {
            const mine = ++version;
            const size = cleanSize(config?.size);
            const [base, hover] = await Promise.all([prepare(config?.base, size), prepare(config?.hover, size)]);
            if (!alive || mine !== version) return;

            // Le immagini nuove prendono il posto delle vecchie tutte insieme:
            // niente freccia di sistema fra una misura e l'altra.
            shapes = { base, hover };
            if (base || hover) {
                if (!style) {
                    style = document.createElement('style');
                    style.setAttribute('data-ccur-style', id);
                    document.head.appendChild(style);
                }
                style.textContent = buildCss(id, base, hover);
            } else {
                style?.remove();
                style = null;
            }
            ensureFollower(finePointer() && [base, hover].some((shape) => shape && !shape.native));
            shown = null;
            render();
        };

        update(initialConfig);

        return {
            update,
            destroy() {
                alive = false;
                scope.removeEventListener('pointerover', onOver);
                scope.removeEventListener('pointermove', onMove);
                scope.removeEventListener('pointerout', onOut);
                global.removeEventListener('blur', onBlur);
                style?.remove();
                follower?.remove();
                if (scope.getAttribute('data-ccur') === id) scope.removeAttribute('data-ccur');
            },
        };
    };

    global.CripsumCursor = { mount, NATIVE_MAX, MIN_SIZE, MAX_SIZE };
})(window);
