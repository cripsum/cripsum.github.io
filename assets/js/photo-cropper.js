/**
 * Cripsum™ — Ritaglio della foto profilo
 *
 * Il pannello che si apre appena scegli un file: sposti, ingrandisci e ruoti,
 * e quello che viene caricato e' gia' l'immagine sistemata. Prima il file
 * partiva com'era e la foto veniva tagliata dal CSS in modo diverso su ogni
 * pagina, senza che si potesse decidere cosa restava dentro.
 *
 * Non usa librerie: l'immagine sta su un canvas e il ritaglio e' lo stesso
 * disegno rifatto alla dimensione di uscita, quindi anteprima e risultato non
 * possono discostarsi.
 *
 *   const file = await CripsumPhotoCropper.open(fileScelto, { shape: 'circle' });
 *   // file === null se l'utente annulla
 */
(() => {
    'use strict';

    if (window.CripsumPhotoCropper) return;

    /** Lato dell'immagine prodotta. Abbondante per gli schermi densi. */
    const OUTPUT = 512;

    /** Quanto si puo' ingrandire oltre il minimo che riempie il riquadro. */
    const MAX_ZOOM = 4;

    const isEnglish = () => document.documentElement.lang === 'en';

    const T = {
        title:   () => isEnglish() ? 'Adjust your photo' : 'Sistema la foto',
        hint:    () => isEnglish()
            ? 'Drag to move, scroll or use the slider to zoom.'
            : 'Trascina per spostare, rotella o cursore per ingrandire.',
        zoom:    () => isEnglish() ? 'Zoom' : 'Zoom',
        rotate:  () => isEnglish() ? 'Rotation' : 'Rotazione',
        left:    () => isEnglish() ? 'Rotate left' : 'Ruota a sinistra',
        right:   () => isEnglish() ? 'Rotate right' : 'Ruota a destra',
        reset:   () => isEnglish() ? 'Reset' : 'Reimposta',
        cancel:  () => isEnglish() ? 'Cancel' : 'Annulla',
        confirm: () => isEnglish() ? 'Use photo' : 'Usa la foto',
        loading: () => isEnglish() ? 'Loading…' : 'Caricamento…',
        broken:  () => isEnglish() ? "This file isn't a readable image." : 'Questo file non è un\'immagine leggibile.'
    };

    /**
     * Il file scelto diventa un'immagine gia' decodificata.
     *
     * `createImageBitmap` fa il lavoro fuori dal thread principale quando c'e';
     * l'alternativa con `<img>` serve a Safari piu' vecchi.
     */
    const decode = (file) => new Promise((resolve, reject) => {
        if (typeof createImageBitmap === 'function') {
            createImageBitmap(file).then(resolve).catch(() => fallback());
            return;
        }
        fallback();

        function fallback() {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('decode')); };
            img.src = url;
        }
    });

    /** Le dimensioni del rettangolo che contiene l'immagine ruotata. */
    const rotatedSize = (w, h, rad) => {
        const c = Math.abs(Math.cos(rad));
        const s = Math.abs(Math.sin(rad));
        return { w: w * c + h * s, h: w * s + h * c };
    };

    const open = (file, { shape = 'circle' } = {}) => new Promise((resolve) => {
        // Le GIF animate non passano di qui: ridisegnarle su un canvas
        // lascerebbe solo il primo fotogramma, e chi carica una GIF la vuole
        // animata.
        if (file.type === 'image/gif') {
            resolve(file);
            return;
        }

        const overlay = document.createElement('div');
        overlay.className = 'pc-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', T.title());

        overlay.innerHTML = `
            <div class="pc-backdrop"></div>
            <div class="pc-card">
                <header class="pc-header">
                    <h3>${T.title()}</h3>
                    <button type="button" class="pc-close" data-pc="cancel" aria-label="${T.cancel()}">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>

                <div class="pc-stage">
                    <canvas class="pc-canvas" width="320" height="320"></canvas>
                    <div class="pc-mask pc-mask--${shape}" aria-hidden="true"></div>
                    <p class="pc-loading">${T.loading()}</p>
                </div>

                <p class="pc-hint">${T.hint()}</p>

                <div class="pc-control">
                    <label for="pcZoom"><i class="fa-solid fa-magnifying-glass"></i> ${T.zoom()}</label>
                    <input type="range" id="pcZoom" min="1" max="${MAX_ZOOM}" step="0.01" value="1">
                </div>

                <div class="pc-control">
                    <label for="pcRotate"><i class="fa-solid fa-rotate"></i> ${T.rotate()}</label>
                    <input type="range" id="pcRotate" min="-180" max="180" step="1" value="0">
                    <button type="button" class="pc-icon-btn" data-pc="rot-left" title="${T.left()}" aria-label="${T.left()}">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                    <button type="button" class="pc-icon-btn" data-pc="rot-right" title="${T.right()}" aria-label="${T.right()}">
                        <i class="fa-solid fa-rotate-right"></i>
                    </button>
                </div>

                <footer class="pc-actions">
                    <button type="button" class="pc-btn pc-btn--ghost" data-pc="reset">${T.reset()}</button>
                    <span class="pc-spacer"></span>
                    <button type="button" class="pc-btn pc-btn--ghost" data-pc="cancel">${T.cancel()}</button>
                    <button type="button" class="pc-btn pc-btn--primary" data-pc="confirm">${T.confirm()}</button>
                </footer>
            </div>
        `;

        document.body.appendChild(overlay);
        document.body.classList.add('pc-open');

        // Leggere offsetWidth forza il calcolo degli stili: la transizione
        // parte lo stesso, ma senza dipendere da requestAnimationFrame, che in
        // una scheda che non sta disegnando non viene mai chiamato — e il
        // pannello resterebbe invisibile con il fondo gia' bloccato.
        void overlay.offsetWidth;
        overlay.classList.add('is-visible');

        const canvas = overlay.querySelector('.pc-canvas');
        const ctx = canvas.getContext('2d');
        const zoomEl = overlay.querySelector('#pcZoom');
        const rotEl = overlay.querySelector('#pcRotate');
        const loadingEl = overlay.querySelector('.pc-loading');

        const view = canvas.width;
        const state = { zoom: 1, rot: 0, x: 0, y: 0 };
        let image = null;

        const chiudi = (risultato) => {
            overlay.classList.remove('is-visible');
            document.body.classList.remove('pc-open');
            document.removeEventListener('keydown', onKey);
            setTimeout(() => overlay.remove(), 200);
            resolve(risultato);
        };

        /**
         * Tiene l'immagine sempre a coprire il riquadro: senza questo si poteva
         * trascinare fuori e restava una fetta vuota nel ritaglio.
         */
        const clamp = () => {
            const rad = state.rot * Math.PI / 180;
            const r = rotatedSize(image.width, image.height, rad);
            const cover = Math.max(view / r.w, view / r.h) * state.zoom;

            const limiteX = Math.max(0, (r.w * cover - view) / 2);
            const limiteY = Math.max(0, (r.h * cover - view) / 2);

            state.x = Math.min(limiteX, Math.max(-limiteX, state.x));
            state.y = Math.min(limiteY, Math.max(-limiteY, state.y));

            return cover;
        };

        /**
         * Lo stesso disegno per l'anteprima e per il file finale: cambia solo
         * il lato, tutto il resto e' in proporzione. Cosi' quello che si vede
         * e' esattamente quello che si ottiene.
         */
        const disegna = (contesto, lato) => {
            const k = lato / view;
            const cover = clamp();

            contesto.save();
            contesto.clearRect(0, 0, lato, lato);
            contesto.fillStyle = '#0b0f1a';
            contesto.fillRect(0, 0, lato, lato);

            contesto.translate(lato / 2 + state.x * k, lato / 2 + state.y * k);
            contesto.rotate(state.rot * Math.PI / 180);
            contesto.scale(cover * k, cover * k);
            contesto.drawImage(image, -image.width / 2, -image.height / 2);
            contesto.restore();
        };

        const render = () => disegna(ctx, view);

        // ── Trascinamento ────────────────────────────────────────────────
        let trascino = null;

        canvas.addEventListener('pointerdown', (e) => {
            if (!image) return;
            trascino = { x: e.clientX - state.x, y: e.clientY - state.y };
            canvas.setPointerCapture(e.pointerId);
            canvas.classList.add('is-dragging');
        });

        canvas.addEventListener('pointermove', (e) => {
            if (!trascino) return;
            state.x = e.clientX - trascino.x;
            state.y = e.clientY - trascino.y;
            render();
        });

        const fineTrascino = () => { trascino = null; canvas.classList.remove('is-dragging'); };
        canvas.addEventListener('pointerup', fineTrascino);
        canvas.addEventListener('pointercancel', fineTrascino);

        canvas.addEventListener('wheel', (e) => {
            if (!image) return;
            e.preventDefault();
            const passo = e.deltaY < 0 ? 1.08 : 1 / 1.08;
            state.zoom = Math.min(MAX_ZOOM, Math.max(1, state.zoom * passo));
            zoomEl.value = String(state.zoom);
            render();
        }, { passive: false });

        zoomEl.addEventListener('input', () => {
            state.zoom = Number(zoomEl.value);
            render();
        });

        rotEl.addEventListener('input', () => {
            state.rot = Number(rotEl.value);
            render();
        });

        // ── Pulsanti ─────────────────────────────────────────────────────
        overlay.addEventListener('click', async (e) => {
            const azione = e.target.closest('[data-pc]')?.dataset.pc;
            if (!azione) return;

            if (azione === 'cancel') return chiudi(null);

            if (azione === 'reset') {
                state.zoom = 1; state.rot = 0; state.x = 0; state.y = 0;
                zoomEl.value = '1';
                rotEl.value = '0';
                return render();
            }

            if (azione === 'rot-left' || azione === 'rot-right') {
                // A scatti di 90 gradi partendo dall'angolo attuale, cosi' il
                // cursore e i pulsanti non si contraddicono.
                const passo = azione === 'rot-left' ? -90 : 90;
                state.rot = Math.max(-180, Math.min(180, Math.round((state.rot + passo) / 90) * 90));
                rotEl.value = String(state.rot);
                return render();
            }

            if (azione === 'confirm') {
                if (!image) return;
                chiudi(await esporta());
            }
        });

        overlay.querySelector('.pc-backdrop').addEventListener('click', () => chiudi(null));

        const onKey = (e) => {
            if (e.key === 'Escape') chiudi(null);
        };
        document.addEventListener('keydown', onKey);

        /**
         * Produce il file ritagliato.
         *
         * PNG quando l'originale puo' avere trasparenza, altrimenti JPEG: un
         * PNG di una foto peserebbe diverse volte tanto senza guadagnarci
         * niente, e il limite di caricamento e' di 2 MB per chi non ha il
         * Premium.
         */
        const esporta = () => new Promise((risolvi) => {
            const trasparente = file.type === 'image/png' || file.type === 'image/webp';
            const tipo = trasparente ? 'image/png' : 'image/jpeg';

            const out = document.createElement('canvas');
            out.width = OUTPUT;
            out.height = OUTPUT;
            disegna(out.getContext('2d'), OUTPUT);

            out.toBlob((blob) => {
                if (!blob) return risolvi(file);

                const nome = file.name.replace(/\.[^.]+$/, '') + (trasparente ? '.png' : '.jpg');
                risolvi(new File([blob], nome, { type: tipo, lastModified: Date.now() }));
            }, tipo, trasparente ? undefined : 0.92);
        });

        decode(file)
            .then((bitmap) => {
                image = bitmap;
                loadingEl.remove();
                render();
            })
            .catch(() => {
                loadingEl.textContent = T.broken();
                setTimeout(() => chiudi(null), 1600);
            });
    });

    window.CripsumPhotoCropper = { open };
})();
