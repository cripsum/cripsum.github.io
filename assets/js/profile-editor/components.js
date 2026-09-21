/**
 * Editor del profilo — componenti condivisi.
 *
 * Tutto quello che si ripete fra le aree: colori, icone, media, menu a
 * tendina, slider, condizioni di visibilita', caricamenti, avvisi e proposta
 * Premium. Ogni componente lavora su un campo vero del form (spesso nascosto)
 * e lancia input/change quando cambia, cosi' bozza, anteprima e annulla non
 * devono sapere niente dei componenti.
 *
 * Espone window.PE, usato da items.js ed editor.js.
 */
(function () {
    'use strict';

    const dataNode = document.getElementById('peData');
    const data = dataNode ? JSON.parse(dataNode.textContent || '{}') : {};
    const isIt = data.lang !== 'en';

    const PE = window.PE = window.PE || {};
    PE.data = data;
    PE.t = (it, en) => (isIt ? it : en);
    PE.$ = (selector, root = document) => root.querySelector(selector);
    PE.$$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    PE.uid = (() => { let n = 0; return (prefix = 'pe') => `${prefix}-${Date.now().toString(36)}-${(n++).toString(36)}`; })();

    PE.escape = (value) => String(value ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    /** Segnala un cambio fatto dal codice come se l'avesse fatto l'utente. */
    PE.emit = (el, { change = true } = {}) => {
        el.dispatchEvent(new Event('input', { bubbles: true }));
        if (change) el.dispatchEvent(new Event('change', { bubbles: true }));
    };

    PE.hex = (value) => {
        const v = String(value ?? '').trim();
        if (/^#[0-9a-fA-F]{6}$/.test(v)) return v.toLowerCase();
        if (/^#[0-9a-fA-F]{3}$/.test(v)) return ('#' + v.slice(1).split('').map((c) => c + c).join('')).toLowerCase();
        return null;
    };

    PE.formatValue = (value, format, zeroLabel) => {
        const n = Number(value);
        if (zeroLabel && n === 0) return zeroLabel;
        switch (format) {
            case 'px': return `${Math.round(n)}px`;
            case '%': return `${Math.round(n)}%`;
            case 'ms': return `${Math.round(n)}ms`;
            case 'deg': return `${Math.round(n)}°`;
            case 'x': return `${Number(n.toFixed(2))}×`;
            case 'ratio': return `${Math.round(n * 100)}%`;
            default: return String(value);
        }
    };

    PE.formatBytes = (bytes) => {
        const value = Number(bytes) || 0;
        if (value < 1024) return `${value} B`;
        if (value < 1024 * 1024) return `${Math.round(value / 1024)} KB`;
        return `${(value / (1024 * 1024)).toFixed(1).replace('.0', '')} MB`;
    };

    /** Il limite vero: il piu' basso fra quello del sito e quello del server. */
    PE.effectiveLimit = (siteLimit) => {
        const server = Number(data.serverUploadLimit) || 0;
        const usable = server > 0 ? Math.max(0, server - 256 * 1024) : 0;
        return usable > 0 ? Math.min(siteLimit, usable) : siteLimit;
    };

    // ── Avvisi ──────────────────────────────────────────────────────────────
    PE.toast = (message, { type = 'info', action = null, timeout = 4200 } = {}) => {
        const host = document.getElementById('peToasts');
        if (!host || !message) return;
        const toast = document.createElement('div');
        toast.className = `pe-toast is-${type}`;
        const icon = type === 'error' ? 'fa-circle-exclamation' : (type === 'success' ? 'fa-circle-check' : 'fa-circle-info');
        toast.innerHTML = `<i class="fa-solid ${icon}" aria-hidden="true"></i><span></span>`;
        toast.querySelector('span').textContent = message;
        if (action) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pe-toast-action';
            btn.textContent = action.label;
            btn.addEventListener('click', () => { action.run(); dismiss(); });
            toast.appendChild(btn);
        }
        const dismiss = () => {
            toast.classList.add('is-leaving');
            setTimeout(() => toast.remove(), 250);
        };
        host.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('is-visible'));
        setTimeout(dismiss, timeout);
    };

    // ── Conferme ────────────────────────────────────────────────────────────
    /**
     * Chiede conferma con il dialogo dell'editor e risponde con una Promise.
     * Prende il posto di window.confirm: quella finestra e' del sistema
     * operativo, non segue ne' i colori ne' la lingua dell'editor, e blocca
     * tutta la pagina mentre e' aperta.
     *
     * `danger: true` colora di rosso il pulsante di conferma.
     */
    PE.confirm = (text, { title = '', confirmLabel = '', cancelLabel = '', danger = false, icon = '' } = {}) => {
        const dialog = document.getElementById('peConfirm');
        if (!dialog) return Promise.resolve(window.confirm(text));

        const titleEl = document.getElementById('peConfirmTitle');
        const textEl = document.getElementById('peConfirmText');
        const iconEl = document.getElementById('peConfirmIcon');
        const okBtn = document.getElementById('peConfirmOk');
        const cancelBtn = document.getElementById('peConfirmCancel');

        titleEl.textContent = title || PE.t('Confermi?', 'Are you sure?');
        textEl.textContent = text || '';
        textEl.hidden = !text;
        iconEl.innerHTML = `<i class="fa-solid ${icon || (danger ? 'fa-triangle-exclamation' : 'fa-circle-question')}" aria-hidden="true"></i>`;
        okBtn.textContent = confirmLabel || PE.t('Conferma', 'Confirm');
        cancelBtn.textContent = cancelLabel || PE.t('Annulla', 'Cancel');
        dialog.classList.toggle('is-danger', !!danger);

        return new Promise((resolve) => {
            let settled = false;

            /*
             * La risposta si decide qui, non nell'evento `close` del dialogo:
             * quell'evento non arriva ovunque, e chi aspettava la conferma
             * restava fermo per sempre con il dialogo gia' chiuso.
             */
            const finish = (value) => {
                if (settled) return;
                settled = true;
                okBtn.removeEventListener('click', onOk);
                cancelBtn.removeEventListener('click', onCancel);
                dialog.removeEventListener('click', onBackdrop);
                dialog.removeEventListener('cancel', onDismiss);
                dialog.removeEventListener('close', onDismiss);
                if (dialog.open) dialog.close();
                resolve(value);
            };

            const onOk = () => finish(true);
            const onCancel = () => finish(false);
            const onDismiss = () => finish(false);
            // Clic fuori dal riquadro: il bersaglio e' il dialogo stesso.
            const onBackdrop = (event) => { if (event.target === dialog) finish(false); };

            okBtn.addEventListener('click', onOk);
            cancelBtn.addEventListener('click', onCancel);
            dialog.addEventListener('click', onBackdrop);
            dialog.addEventListener('cancel', onDismiss);
            dialog.addEventListener('close', onDismiss);

            dialog.showModal();
            // Il pulsante pericoloso non parte selezionato: si conferma apposta.
            (danger ? cancelBtn : okBtn).focus();
        });
    };

    // ── Ricerca su fonti esterne ────────────────────────────────────────────
    /*
     * Il dialogo che cerca giochi, anime/serie/film, canzoni e libri su
     * api/profile/media_search.php e restituisce quello scelto.
     *
     * Serve alle quattro sezioni dei preferiti e al brano del player: senza,
     * ogni copertina andava trovata a mano da qualche altra parte e incollata
     * come URL.
     */
    PE.mediaSearch = (() => {
        let dialog = null;
        let input = null;
        let resultsEl = null;
        let statusEl = null;
        let titleEl = null;
        let timer = null;
        let requestId = 0;
        let current = { kind: '', onPick: null };

        const build = () => {
            dialog = document.createElement('dialog');
            dialog.className = 'pe-dialog pe-mediasearch';
            dialog.innerHTML = `
                <div class="pe-mediasearch-box">
                    <header class="pe-mediasearch-head">
                        <h2></h2>
                        <button type="button" class="pe-icon-btn" data-close-dialog aria-label="${PE.escape(PE.t('Chiudi', 'Close'))}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </header>
                    <div class="pe-mediasearch-input">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" class="pe-input" autocomplete="off" spellcheck="false">
                    </div>
                    <p class="pe-mediasearch-status"></p>
                    <div class="pe-mediasearch-results"></div>
                </div>`;
            document.body.appendChild(dialog);
            input = dialog.querySelector('input');
            resultsEl = dialog.querySelector('.pe-mediasearch-results');
            statusEl = dialog.querySelector('.pe-mediasearch-status');
            titleEl = dialog.querySelector('h2');

            input.addEventListener('input', () => {
                clearTimeout(timer);
                // Mezzo secondo scarso: una richiesta per parola scritta, non
                // una per lettera battuta.
                timer = setTimeout(() => run(input.value), 350);
            });
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') { event.preventDefault(); clearTimeout(timer); run(input.value); }
            });
            dialog.addEventListener('close', () => { clearTimeout(timer); requestId += 1; });
        };

        const setStatus = (text, { busy = false } = {}) => {
            statusEl.textContent = text || '';
            statusEl.hidden = !text;
            dialog.classList.toggle('is-busy', busy);
        };

        const render = (items) => {
            resultsEl.innerHTML = '';
            items.forEach((item) => {
                const card = document.createElement('button');
                card.type = 'button';
                card.className = 'pe-mediasearch-card';
                card.innerHTML = `
                    <span class="pe-mediasearch-art${item.image ? '' : ' is-empty'}">
                        ${item.image ? `<img src="${PE.escape(item.image)}" alt="" loading="lazy">` : '<i class="fa-solid fa-image" aria-hidden="true"></i>'}
                    </span>
                    <span class="pe-mediasearch-info">
                        <strong></strong>
                        <small></small>
                    </span>`;
                card.querySelector('strong').textContent = item.title;
                card.querySelector('small').textContent = [item.subtitle, item.meta].filter(Boolean).join(' · ');
                // Una copertina che non carica e' peggio di nessuna copertina.
                card.querySelector('img')?.addEventListener('error', (event) => {
                    event.target.closest('.pe-mediasearch-art')?.classList.add('is-empty');
                    event.target.remove();
                    item.image = '';
                }, { once: true });
                card.addEventListener('click', () => {
                    current.onPick?.(item);
                    dialog.close();
                });
                resultsEl.appendChild(card);
            });
        };

        const run = async (query) => {
            const text = String(query || '').trim();
            resultsEl.innerHTML = '';
            if (text.length < 2) {
                setStatus(PE.t('Scrivi almeno due lettere.', 'Type at least two letters.'));
                return;
            }
            const mine = ++requestId;
            setStatus(PE.t('Sto cercando…', 'Searching…'), { busy: true });
            try {
                const url = `/api/profile/media_search.php?kind=${encodeURIComponent(current.kind)}&q=${encodeURIComponent(text)}&lang=${encodeURIComponent(data.lang || 'it')}`;
                const response = await fetch(url, { credentials: 'same-origin' });
                const json = await response.json().catch(() => ({}));
                // Una risposta vecchia non deve sostituire una ricerca nuova.
                if (mine !== requestId) return;
                if (!response.ok || !json.ok) throw new Error(json.message || PE.t('Ricerca non riuscita.', 'Search failed.'));

                const items = Array.isArray(json.results) ? json.results : [];
                if (!items.length) {
                    setStatus(PE.t('Nessun risultato. Puoi aggiungerlo a mano.', 'No results. You can add it by hand.'));
                    return;
                }
                render(items);
                setStatus(json.partial
                    ? PE.t('Una fonte non ha risposto: potrebbero mancare dei risultati.', 'One source did not answer: some results may be missing.')
                    : '');
            } catch (error) {
                if (mine !== requestId) return;
                setStatus(error.message);
            }
        };

        return {
            open(kind, { title = '', onPick = null, query = '' } = {}) {
                if (!dialog) build();
                current = { kind, onPick };
                titleEl.textContent = title || PE.t('Cerca', 'Search');
                input.placeholder = PE.t('Scrivi il titolo…', 'Type the title…');
                input.value = query;
                resultsEl.innerHTML = '';
                setStatus(PE.t('Scrivi almeno due lettere.', 'Type at least two letters.'));
                if (!dialog.open) dialog.showModal();
                input.focus();
                if (query) run(query);
            },
        };
    })();

    // ── Proposta Premium ────────────────────────────────────────────────────
    PE.upsell = (reason = '') => {
        const dialog = document.getElementById('peUpsell');
        if (!dialog) return;
        const reasonEl = document.getElementById('peUpsellReason');
        if (reasonEl) {
            reasonEl.textContent = reason
                ? PE.t(`«${reason}» è una funzione Premium.`, `“${reason}” is a Premium feature.`)
                : PE.t('Porta il tuo profilo al livello successivo.', 'Take your profile to the next level.');
        }
        if (!dialog.open) dialog.showModal();
    };

    document.addEventListener('click', (event) => {
        const closer = event.target.closest('[data-close-dialog]');
        if (closer) closer.closest('dialog')?.close();
        const opener = event.target.closest('[data-open-upsell]');
        if (opener) PE.upsell();
    });

    // Clic fuori dal contenuto di un dialog: chiude.
    document.addEventListener('click', (event) => {
        if (event.target instanceof HTMLDialogElement && event.target.classList.contains('pe-dialog')) {
            event.target.close();
        }
    });

    // Tutto cio' che e' bloccato per chi non e' Premium apre la proposta invece
    // di non fare nulla.
    document.addEventListener('click', (event) => {
        if (data.premium) return;
        const locked = event.target.closest('[data-premium-lock]');
        if (!locked) return;
        // Dentro un blocco si puo' comunque aprire/chiudere un <details>.
        if (event.target.closest('summary') && locked.tagName === 'DETAILS') {
            event.preventDefault();
        } else {
            event.preventDefault();
            event.stopPropagation();
        }
        // Il nome della funzione, dal primo di questi elementi che ha del testo.
        const label = ['.pe-theme-name', '.pe-choice-label', '.pe-toggle-title', '.pe-field-title', '.pe-field-label label', '.pe-group-head h3', 'summary']
            .map((selector) => locked.matches(selector) ? locked : locked.querySelector(selector))
            .map((el) => (el?.childNodes ? Array.from(el.childNodes).filter((n) => n.nodeType === Node.TEXT_NODE).map((n) => n.textContent).join('').trim() || el.textContent.trim() : ''))
            .find(Boolean) || '';
        PE.upsell(label);
    }, true);

    // ── Popover (colori, icone, menu) ───────────────────────────────────────
    let activePopover = null;

    PE.closePopover = () => {
        if (!activePopover) return;
        const { el, anchor, onClose } = activePopover;
        activePopover = null;
        el.remove();
        anchor?.setAttribute('aria-expanded', 'false');
        onClose?.();
    };

    const positionPopover = () => {
        if (!activePopover) return;
        const { el, anchor } = activePopover;
        const rect = anchor.getBoundingClientRect();
        const width = el.offsetWidth;
        const height = el.offsetHeight;
        const margin = 8;
        let left = Math.min(Math.max(margin, rect.left), window.innerWidth - width - margin);
        let top = rect.bottom + 6;
        if (top + height > window.innerHeight - margin && rect.top - height - 6 > margin) {
            top = rect.top - height - 6;
        }
        top = Math.max(margin, Math.min(top, window.innerHeight - height - margin));
        el.style.left = `${left}px`;
        el.style.top = `${top}px`;
    };

    PE.openPopover = (anchor, content, { className = '', onClose = null } = {}) => {
        const reopening = activePopover && activePopover.anchor === anchor;
        PE.closePopover();
        if (reopening) return null;
        const el = document.createElement('div');
        el.className = `pe-popover ${className}`;
        el.setAttribute('role', 'dialog');
        el.appendChild(content);
        document.body.appendChild(el);
        activePopover = { el, anchor, onClose };
        anchor.setAttribute('aria-expanded', 'true');
        positionPopover();
        requestAnimationFrame(() => el.classList.add('is-open'));
        return el;
    };

    document.addEventListener('pointerdown', (event) => {
        if (!activePopover) return;
        if (activePopover.el.contains(event.target) || activePopover.anchor.contains(event.target)) return;
        PE.closePopover();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && activePopover) {
            const anchor = activePopover.anchor;
            PE.closePopover();
            anchor?.focus();
        }
    });
    window.addEventListener('resize', positionPopover);
    document.addEventListener('scroll', positionPopover, true);

    // ── Colori ──────────────────────────────────────────────────────────────
    const hexToHsv = (hex) => {
        const h = PE.hex(hex) || '#000000';
        const r = parseInt(h.slice(1, 3), 16) / 255;
        const g = parseInt(h.slice(3, 5), 16) / 255;
        const b = parseInt(h.slice(5, 7), 16) / 255;
        const max = Math.max(r, g, b);
        const min = Math.min(r, g, b);
        const d = max - min;
        let hue = 0;
        if (d !== 0) {
            if (max === r) hue = ((g - b) / d + (g < b ? 6 : 0));
            else if (max === g) hue = (b - r) / d + 2;
            else hue = (r - g) / d + 4;
            hue *= 60;
        }
        return { h: hue, s: max === 0 ? 0 : d / max, v: max };
    };

    const hsvToHex = (h, s, v) => {
        const f = (n) => {
            const k = (n + h / 60) % 6;
            return v - v * s * Math.max(0, Math.min(k, 4 - k, 1));
        };
        const toHex = (x) => Math.round(x * 255).toString(16).padStart(2, '0');
        return `#${toHex(f(5))}${toHex(f(3))}${toHex(f(1))}`;
    };

    const SWATCHES = ['#ffffff', '#111827', '#9ca3af', '#ef4444', '#f97316', '#eab308', '#22c55e', '#14b8a6', '#06b6d4', '#3b82f6', '#6366f1', '#8b5cf6', '#d946ef', '#ec4899'];

    const autoPreviewColor = (wrap) => {
        const theme = document.querySelector('input[name="profile_theme"]:checked')?.value || 'dark';
        return wrap.dataset.autoPreview || (theme === 'light' ? '#111827' : '#ffffff');
    };

    PE.syncColor = (wrap) => {
        const input = wrap.querySelector('input');
        const trigger = wrap.querySelector('.pe-color-trigger');
        if (!input || !trigger) return;
        const value = PE.hex(input.value);
        const isAuto = !value && wrap.dataset.allowAuto === '1';
        trigger.querySelector('.pe-swatch').style.background = value || (isAuto ? autoPreviewColor(wrap) : '#000000');
        trigger.querySelector('.pe-swatch').classList.toggle('is-auto', isAuto);
        trigger.querySelector('.pe-color-text').textContent = isAuto ? (wrap.dataset.autoLabel || 'Auto') : (value || '#000000').toUpperCase();
    };

    const buildColorPicker = (wrap) => {
        const input = wrap.querySelector('input');
        const box = document.createElement('div');
        box.className = 'pe-colorpicker';
        box.innerHTML = `
            <div class="pe-cp-sv" tabindex="0" aria-label="${PE.escape(PE.t('Saturazione e luminosità', 'Saturation and brightness'))}"><span class="pe-cp-sv-handle"></span></div>
            <div class="pe-cp-hue" tabindex="0" aria-label="${PE.escape(PE.t('Tonalità', 'Hue'))}"><span class="pe-cp-hue-handle"></span></div>
            <div class="pe-cp-row">
                <span class="pe-cp-preview"></span>
                <input type="text" class="pe-input pe-cp-hex" maxlength="7" spellcheck="false" aria-label="HEX">
            </div>
            <div class="pe-cp-swatches"></div>
            ${wrap.dataset.allowAuto === '1' ? `<button type="button" class="pe-cp-auto"><i class="fa-solid fa-wand-magic" aria-hidden="true"></i>${PE.escape(wrap.dataset.autoLabel || 'Auto')}</button>` : ''}`;

        const sv = box.querySelector('.pe-cp-sv');
        const svHandle = box.querySelector('.pe-cp-sv-handle');
        const hue = box.querySelector('.pe-cp-hue');
        const hueHandle = box.querySelector('.pe-cp-hue-handle');
        const hexInput = box.querySelector('.pe-cp-hex');
        const preview = box.querySelector('.pe-cp-preview');
        const swatchesEl = box.querySelector('.pe-cp-swatches');

        let state = hexToHsv(PE.hex(input.value) || autoPreviewColor(wrap));

        const accent = PE.hex(document.querySelector('input[name="accent_color"]')?.value);
        const secondary = PE.hex(document.querySelector('input[name="profile_secondary_color"]')?.value);
        const swatches = [...new Set([accent, secondary, ...SWATCHES].filter(Boolean))];
        swatches.forEach((color, index) => {
            const s = document.createElement('button');
            s.type = 'button';
            s.className = 'pe-cp-swatch';
            s.style.background = color;
            s.title = index === 0 && accent ? PE.t('Colore principale', 'Main color') : (index === 1 && secondary ? PE.t('Colore secondario', 'Secondary color') : color);
            s.addEventListener('click', () => { state = hexToHsv(color); commit(true); });
            swatchesEl.appendChild(s);
        });

        const render = () => {
            const hex = hsvToHex(state.h, state.s, state.v);
            sv.style.background = `hsl(${state.h}, 100%, 50%)`;
            svHandle.style.left = `${state.s * 100}%`;
            svHandle.style.top = `${(1 - state.v) * 100}%`;
            hueHandle.style.left = `${(state.h / 360) * 100}%`;
            preview.style.background = hex;
            if (document.activeElement !== hexInput) hexInput.value = hex.toUpperCase();
            PE.$$('.pe-cp-swatch', box).forEach((s) => s.classList.toggle('is-active', PE.hex(s.style.backgroundColor) === hex || rgbToHex(s.style.backgroundColor) === hex));
        };

        const rgbToHex = (rgb) => {
            const m = String(rgb).match(/\d+/g);
            return m ? '#' + m.slice(0, 3).map((x) => Number(x).toString(16).padStart(2, '0')).join('') : null;
        };

        let pending = false;
        const commit = (final) => {
            input.value = hsvToHex(state.h, state.s, state.v);
            PE.syncColor(wrap);
            render();
            // Mentre si trascina basta "input" (anteprima), il "change" arriva
            // al rilascio: un solo passo nella cronologia.
            input.dispatchEvent(new Event('input', { bubbles: true }));
            if (final) input.dispatchEvent(new Event('change', { bubbles: true }));
            pending = !final;
        };

        const drag = (el, onMove) => {
            const move = (event) => {
                const rect = el.getBoundingClientRect();
                const x = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
                const y = Math.max(0, Math.min(1, (event.clientY - rect.top) / rect.height));
                onMove(x, y);
                commit(false);
            };
            el.addEventListener('pointerdown', (event) => {
                event.preventDefault();
                el.setPointerCapture(event.pointerId);
                move(event);
                const up = () => {
                    el.removeEventListener('pointermove', move);
                    el.removeEventListener('pointerup', up);
                    if (pending) commit(true);
                };
                el.addEventListener('pointermove', move);
                el.addEventListener('pointerup', up);
            });
        };
        drag(sv, (x, y) => { state.s = x; state.v = 1 - y; });
        drag(hue, (x) => { state.h = x * 360; });

        const nudge = (el, fn) => el.addEventListener('keydown', (event) => {
            const step = event.shiftKey ? 0.1 : 0.02;
            const map = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
            if (!map[event.key]) return;
            event.preventDefault();
            fn(...map[event.key]);
            commit(true);
        });
        nudge(sv, (dx, dy) => { state.s = Math.max(0, Math.min(1, state.s + dx)); state.v = Math.max(0, Math.min(1, state.v - dy)); });
        nudge(hue, (dx) => { state.h = (state.h + dx * 360 + 360) % 360; });

        hexInput.addEventListener('input', () => {
            const hex = PE.hex(hexInput.value.startsWith('#') ? hexInput.value : '#' + hexInput.value);
            if (hex) { state = hexToHsv(hex); commit(true); }
        });

        box.querySelector('.pe-cp-auto')?.addEventListener('click', () => {
            input.value = '';
            PE.syncColor(wrap);
            PE.emit(input);
            PE.closePopover();
        });

        render();
        return box;
    };

    PE.initColors = (root = document) => {
        PE.$$('[data-color-field]', root).forEach((wrap) => {
            if (wrap.dataset.ready) return;
            wrap.dataset.ready = '1';
            const input = wrap.querySelector('input');
            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'pe-color-trigger';
            trigger.setAttribute('aria-haspopup', 'dialog');
            trigger.setAttribute('aria-expanded', 'false');
            trigger.disabled = !!input?.disabled;
            trigger.innerHTML = '<span class="pe-swatch" aria-hidden="true"></span><span class="pe-color-text"></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i>';
            const label = wrap.closest('.pe-field, label')?.querySelector('.pe-field-title, label')?.textContent?.trim();
            if (label) trigger.setAttribute('aria-label', label);
            wrap.appendChild(trigger);
            trigger.addEventListener('click', () => PE.openPopover(trigger, buildColorPicker(wrap), { className: 'pe-popover-color' }));
            PE.syncColor(wrap);
        });
    };

    // ── Slider ──────────────────────────────────────────────────────────────
    PE.syncSlider = (input) => {
        const min = Number(input.min || 0);
        const max = Number(input.max || 100);
        const pct = max > min ? ((Number(input.value) - min) / (max - min)) * 100 : 0;
        input.style.setProperty('--pe-fill', `${pct}%`);
        const output = input.closest('.pe-field')?.querySelector('.pe-value');
        if (output) output.textContent = PE.formatValue(input.value, input.dataset.format, input.dataset.zeroLabel);
    };

    PE.initSliders = (root = document) => {
        PE.$$('input.pe-range', root).forEach((input) => {
            if (input.dataset.ready) return;
            input.dataset.ready = '1';
            input.addEventListener('input', () => PE.syncSlider(input));
            // Doppio clic sul valore: torna al predefinito.
            const output = input.closest('.pe-field')?.querySelector('.pe-value');
            if (output && input.dataset.default !== undefined) {
                output.title = PE.t('Doppio clic per il valore predefinito', 'Double-click to reset');
                output.addEventListener('dblclick', () => {
                    input.value = input.dataset.default;
                    PE.syncSlider(input);
                    PE.emit(input);
                });
            }
            PE.syncSlider(input);
        });
    };

    // ── Contatori di caratteri ──────────────────────────────────────────────
    PE.initCounters = (root = document) => {
        PE.$$('[data-counter]', root).forEach((input) => {
            if (input.dataset.counterReady) return;
            input.dataset.counterReady = '1';
            const counter = input.parentElement?.parentElement?.querySelector(`[data-counter-for="${input.id}"]`) || input.closest('.pe-field')?.querySelector('.pe-counter');
            if (!counter) return;
            const update = () => {
                const max = Number(input.maxLength) || 0;
                counter.textContent = max > 0 ? `${input.value.length}/${max}` : String(input.value.length);
                counter.classList.toggle('is-near', max > 0 && input.value.length >= max * 0.9);
            };
            input.addEventListener('input', update);
            input.peSyncCounter = update;
            update();
        });
    };

    // ── Tendine con opzioni Premium e anteprima del font ────────────────────
    /*
     * I font del catalogo, indicizzati per famiglia. Il link a Google Fonts
     * arriva da PHP con i pesi giusti; i due font che stanno nel repository
     * (Minecraft, Gang of Three) hanno `css` vuoto e non vanno chiesti a
     * nessuno. Prima il link si costruiva dal solo nome della famiglia.
     */
    const fontIndex = new Map((data.catalog?.fonts || []).map((f) => [f.value, f]));
    const loadedFonts = new Set();

    PE.loadFont = (family) => {
        if (!family || loadedFonts.has(family)) return;
        loadedFonts.add(family);
        const css = fontIndex.get(family)?.css;
        if (!css) return;
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = `https://fonts.googleapis.com/css2?family=${css}&display=swap`;
        document.head.appendChild(link);
    };

    /** La famiglia CSS di un font, riserva compresa. */
    PE.fontFamily = (family) => `'${String(family).replace(/'/g, '')}', ${fontIndex.get(family)?.stack || 'sans-serif'}`;

    PE.syncSelect = (select) => {
        const wrap = select.closest('.pe-selectmenu');
        if (!wrap) return;
        const option = select.options[select.selectedIndex];
        const current = wrap.querySelector('.pe-select-current');
        current.textContent = option ? option.textContent : '';
        if (select.hasAttribute('data-font-preview') && option) {
            PE.loadFont(option.value);
            current.style.fontFamily = PE.fontFamily(option.value);
        }
    };

    PE.initSelects = (root = document) => {
        PE.$$('select.pe-select', root).forEach((select) => {
            if (select.dataset.ready) return;
            select.dataset.ready = '1';
            // Tutte le tendine usano lo stesso menu: quella nativa si apriva con
            // l'aspetto del sistema operativo, diversa da tutto il resto.
            const fontPreview = select.hasAttribute('data-font-preview');

            const wrap = document.createElement('div');
            wrap.className = 'pe-selectmenu';
            select.parentNode.insertBefore(wrap, select);
            wrap.appendChild(select);
            select.classList.add('pe-select-native');
            select.tabIndex = -1;
            select.setAttribute('aria-hidden', 'true');

            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'pe-input pe-select-trigger';
            trigger.disabled = select.disabled;
            trigger.setAttribute('aria-haspopup', 'listbox');
            trigger.setAttribute('aria-expanded', 'false');
            const label = select.closest('.pe-field')?.querySelector('label')?.textContent?.trim();
            if (label) trigger.setAttribute('aria-label', label);
            trigger.innerHTML = '<span class="pe-select-current"></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i>';
            wrap.appendChild(trigger);

            const searchable = select.hasAttribute('data-searchable');

            /*
             * Il font di un'opzione si carica quando la sua riga si avvicina
             * al bordo della lista: con quaranta font, caricarli tutti
             * all'apertura voleva dire quaranta fogli di stile in un colpo.
             *
             * L'osservatore guarda dentro la lista (`root`), non la finestra,
             * e si accende solo dopo che il menu e' davvero nella pagina:
             * osservare righe ancora staccate dal documento non faceva
             * scattare niente, e si vedevano tutti i nomi nel font di riserva.
             */
            const makeFontLoader = (listEl) => {
                if (!fontPreview) return null;
                const options = PE.$$('.pe-listbox-option', listEl);

                // Le prime righe partono subito: sono quelle che si vedono
                // aprendo il menu, e non devono dipendere dall'osservatore.
                options.slice(0, 14).forEach((el) => PE.loadFont(el.dataset.value));

                if (!('IntersectionObserver' in window)) {
                    options.forEach((el) => PE.loadFont(el.dataset.value));
                    return null;
                }
                const obs = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;
                        PE.loadFont(entry.target.dataset.value);
                        obs.unobserve(entry.target);
                    });
                }, { root: listEl, rootMargin: '200px' });
                options.slice(14).forEach((el) => obs.observe(el));
                return obs;
            };

            const open = () => {
                const list = document.createElement('div');
                list.className = 'pe-listbox';
                list.setAttribute('role', 'listbox');

                // L'osservatore nasce dopo che il menu e' nella pagina, e si
                // rifa' a ogni ricerca perche' le righe sono nuove.
                let observer = null;
                let lastGroup = null;

                const build = (query = '') => {
                    list.innerHTML = '';
                    lastGroup = null;
                    const needle = query.trim().toLowerCase();
                    let shown = 0;

                    Array.from(select.options).forEach((option) => {
                        if (needle && !option.textContent.toLowerCase().includes(needle)) return;
                        shown += 1;

                        // Intestazione del gruppo, solo senza ricerca in corso.
                        const group = option.dataset.group || '';
                        if (!needle && group && group !== lastGroup) {
                            lastGroup = group;
                            const head = document.createElement('div');
                            head.className = 'pe-listbox-group';
                            head.textContent = group;
                            list.appendChild(head);
                        }

                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'pe-listbox-option';
                        item.setAttribute('role', 'option');
                        item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
                        item.dataset.value = option.value;
                        const locked = option.dataset.locked === '1';
                        item.innerHTML = `<span></span>${option.dataset.premium ? `<i class="fa-solid fa-crown ${locked ? 'is-locked' : ''}" aria-label="Premium"></i>` : ''}${option.selected ? '<i class="fa-solid fa-check pe-listbox-check" aria-hidden="true"></i>' : ''}`;
                        item.querySelector('span').textContent = option.textContent;
                        if (fontPreview) item.style.fontFamily = PE.fontFamily(option.value);
                        item.addEventListener('click', () => {
                            if (locked) {
                                PE.closePopover();
                                PE.upsell(option.textContent);
                                return;
                            }
                            select.value = option.value;
                            PE.syncSelect(select);
                            PE.emit(select);
                            PE.closePopover();
                            trigger.focus();
                        });
                        list.appendChild(item);
                    });

                    if (shown === 0) {
                        const empty = document.createElement('p');
                        empty.className = 'pe-listbox-empty';
                        empty.textContent = PE.t('Nessun risultato', 'No results');
                        list.appendChild(empty);
                    }
                };

                build();

                let holder = list;
                let search = null;
                if (searchable) {
                    holder = document.createElement('div');
                    holder.className = 'pe-listbox-wrap';
                    const searchWrap = document.createElement('div');
                    searchWrap.className = 'pe-listbox-search';
                    searchWrap.innerHTML = '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>';
                    search = document.createElement('input');
                    search.type = 'search';
                    search.className = 'pe-input';
                    search.placeholder = PE.t('Cerca…', 'Search…');
                    search.autocomplete = 'off';
                    searchWrap.appendChild(search);
                    holder.appendChild(searchWrap);
                    holder.appendChild(list);
                    search.addEventListener('input', () => {
                        build(search.value);
                        observer?.disconnect();
                        observer = makeFontLoader(list);
                    });
                }

                const pop = PE.openPopover(trigger, holder, {
                    className: searchable ? 'pe-popover-list pe-popover-search' : 'pe-popover-list',
                    onClose: () => observer?.disconnect(),
                });
                observer = makeFontLoader(list);
                if (pop) {
                    pop.style.minWidth = `${trigger.offsetWidth}px`;
                    if (search) search.focus();
                    else {
                        const selected = pop.querySelector('[aria-selected="true"]');
                        selected?.scrollIntoView({ block: 'nearest' });
                        selected?.focus();
                    }
                    holder.addEventListener('keydown', (event) => {
                        const items = PE.$$('.pe-listbox-option', list);
                        const index = items.indexOf(document.activeElement);
                        if (event.key === 'ArrowDown') { event.preventDefault(); items[Math.min(items.length - 1, index + 1)]?.focus(); }
                        if (event.key === 'ArrowUp') { event.preventDefault(); index <= 0 ? search?.focus() : items[index - 1]?.focus(); }
                        if (event.key === 'Enter' && event.target === search) { event.preventDefault(); items[0]?.click(); }
                    });
                }
            };
            trigger.addEventListener('click', open);
            trigger.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    open();
                }
            });
            select.addEventListener('change', () => PE.syncSelect(select));
            PE.syncSelect(select);
        });
    };

    // ── Caricamenti ─────────────────────────────────────────────────────────
    const pendingUploads = new Set();

    PE.uploads = {
        hasPending: () => pendingUploads.size > 0,
        waitForAll: () => Promise.all(Array.from(pendingUploads)),
    };

    const uploadCard = (name, size) => {
        const host = document.getElementById('peUploads');
        const card = document.createElement('div');
        card.className = 'pe-upload is-uploading';
        card.innerHTML = `
            <i class="fa-solid fa-cloud-arrow-up pe-upload-icon" aria-hidden="true"></i>
            <div class="pe-upload-main">
                <div class="pe-upload-row"><strong></strong><span class="pe-upload-pct">0%</span></div>
                <div class="pe-upload-track"><span></span></div>
                <small></small>
            </div>`;
        card.querySelector('strong').textContent = name;
        card.querySelector('small').textContent = size ? `${PE.formatBytes(size)} · ${PE.t('caricamento…', 'uploading…')}` : PE.t('caricamento…', 'uploading…');
        host?.appendChild(card);
        return {
            progress(pct) {
                card.querySelector('.pe-upload-track span').style.width = `${pct}%`;
                card.querySelector('.pe-upload-pct').textContent = `${Math.round(pct)}%`;
            },
            done(ok, message) {
                card.classList.remove('is-uploading');
                card.classList.add(ok ? 'is-done' : 'is-failed');
                card.querySelector('.pe-upload-icon').className = `fa-solid ${ok ? 'fa-circle-check' : 'fa-circle-exclamation'} pe-upload-icon`;
                card.querySelector('.pe-upload-track span').style.width = '100%';
                card.querySelector('.pe-upload-pct').textContent = ok ? '' : '!';
                card.querySelector('small').textContent = message;
                setTimeout(() => { card.classList.add('is-leaving'); setTimeout(() => card.remove(), 300); }, ok ? 2600 : 6000);
            },
        };
    };

    /**
     * Carica un file su api/upload_profile_media.php e restituisce la risposta.
     * `purpose`: icon (predefinito), block (immagini e video) o cursor.
     */
    PE.uploadFile = (file, { purpose = 'icon' } = {}) => {
        const isVideo = file.type.startsWith('video/');
        const siteLimit = purpose === 'cursor' ? 2 * 1024 * 1024 : (isVideo ? 50 * 1024 * 1024 : 25 * 1024 * 1024);
        const limit = PE.effectiveLimit(siteLimit);
        if (file.size > limit) {
            const message = PE.t(`File troppo pesante: il massimo è ${PE.formatBytes(limit)}.`, `File too large: the maximum is ${PE.formatBytes(limit)}.`);
            PE.toast(message, { type: 'error' });
            return Promise.reject(new Error(message));
        }

        const card = uploadCard(file.name, file.size);
        const form = document.getElementById('profileEditForm');
        const body = new FormData();
        body.append('file', file);
        body.append('csrf_token', form?.querySelector('input[name="csrf_token"]')?.value || '');
        body.append('target_user_id', String(data.targetUserId || ''));
        body.append('purpose', purpose);

        const request = new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/api/upload_profile_media.php', true);
            xhr.withCredentials = true;
            xhr.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) card.progress((event.loaded / event.total) * 100);
            });
            xhr.addEventListener('load', () => {
                let json = null;
                try { json = JSON.parse(xhr.responseText || '{}'); } catch (_) { /* gestito sotto */ }
                if (!json || !json.ok || !json.url) {
                    reject(new Error(json?.message || PE.t('Caricamento non riuscito.', 'Upload failed.')));
                    return;
                }
                resolve(json);
            });
            xhr.addEventListener('error', () => reject(new Error(PE.t('Errore di rete.', 'Network error.'))));
            xhr.send(body);
        });

        const tracked = request
            .then((json) => { card.done(true, PE.t('Caricato', 'Uploaded')); return json; })
            .catch((error) => { card.done(false, error.message); PE.toast(error.message, { type: 'error' }); throw error; })
            .finally(() => pendingUploads.delete(tracked));
        pendingUploads.add(tracked.catch(() => null));
        return tracked;
    };

    PE.pickFile = (accept) => new Promise((resolve) => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = accept;
        input.addEventListener('change', () => resolve(input.files?.[0] || null), { once: true });
        input.click();
    });

    // ── Icone ───────────────────────────────────────────────────────────────
    PE.ICONS = [
        'fa-solid fa-link', 'fa-solid fa-globe', 'fa-solid fa-house', 'fa-solid fa-user', 'fa-solid fa-star', 'fa-solid fa-heart', 'fa-solid fa-fire', 'fa-solid fa-bolt',
        'fa-solid fa-crown', 'fa-solid fa-gem', 'fa-solid fa-trophy', 'fa-solid fa-medal', 'fa-solid fa-rocket', 'fa-solid fa-code', 'fa-solid fa-terminal', 'fa-solid fa-laptop-code',
        'fa-solid fa-gamepad', 'fa-solid fa-dice', 'fa-solid fa-headphones', 'fa-solid fa-music', 'fa-solid fa-microphone', 'fa-solid fa-guitar', 'fa-solid fa-film', 'fa-solid fa-video',
        'fa-solid fa-camera', 'fa-solid fa-image', 'fa-solid fa-palette', 'fa-solid fa-paintbrush', 'fa-solid fa-pen-nib', 'fa-solid fa-wand-magic-sparkles', 'fa-solid fa-book', 'fa-solid fa-graduation-cap',
        'fa-solid fa-briefcase', 'fa-solid fa-cart-shopping', 'fa-solid fa-bag-shopping', 'fa-solid fa-shirt', 'fa-solid fa-mug-hot', 'fa-solid fa-pizza-slice', 'fa-solid fa-burger', 'fa-solid fa-plane',
        'fa-solid fa-car', 'fa-solid fa-bicycle', 'fa-solid fa-futbol', 'fa-solid fa-basketball', 'fa-solid fa-dumbbell', 'fa-solid fa-person-running', 'fa-solid fa-paw', 'fa-solid fa-cat',
        'fa-solid fa-dog', 'fa-solid fa-leaf', 'fa-solid fa-seedling', 'fa-solid fa-sun', 'fa-solid fa-moon', 'fa-solid fa-cloud', 'fa-solid fa-snowflake', 'fa-solid fa-droplet',
        'fa-solid fa-ghost', 'fa-solid fa-skull', 'fa-solid fa-dragon', 'fa-solid fa-robot', 'fa-solid fa-user-astronaut', 'fa-solid fa-hat-wizard', 'fa-solid fa-shield-halved', 'fa-solid fa-khanda',
        'fa-solid fa-envelope', 'fa-solid fa-comment', 'fa-solid fa-comments', 'fa-solid fa-phone', 'fa-solid fa-location-dot', 'fa-solid fa-calendar', 'fa-solid fa-clock', 'fa-solid fa-bell',
        'fa-solid fa-circle-info', 'fa-solid fa-circle-check', 'fa-solid fa-circle-play', 'fa-solid fa-play', 'fa-solid fa-download', 'fa-solid fa-upload', 'fa-solid fa-share-nodes', 'fa-solid fa-bookmark',
        'fa-solid fa-flag', 'fa-solid fa-tag', 'fa-solid fa-hashtag', 'fa-solid fa-at', 'fa-solid fa-lock', 'fa-solid fa-key', 'fa-solid fa-gift', 'fa-solid fa-hand-peace',
        'fa-solid fa-face-smile', 'fa-solid fa-face-grin-stars', 'fa-solid fa-thumbs-up', 'fa-solid fa-infinity', 'fa-solid fa-layer-group', 'fa-solid fa-cubes', 'fa-solid fa-chart-line', 'fa-solid fa-wand-sparkles',
        'fa-brands fa-instagram', 'fa-brands fa-tiktok', 'fa-brands fa-youtube', 'fa-brands fa-twitch', 'fa-brands fa-x-twitter', 'fa-brands fa-discord', 'fa-brands fa-github', 'fa-brands fa-spotify',
        'fa-brands fa-soundcloud', 'fa-brands fa-steam', 'fa-brands fa-playstation', 'fa-brands fa-xbox', 'fa-brands fa-reddit-alien', 'fa-brands fa-telegram', 'fa-brands fa-whatsapp', 'fa-brands fa-snapchat',
        'fa-brands fa-facebook', 'fa-brands fa-threads', 'fa-brands fa-linkedin', 'fa-brands fa-pinterest', 'fa-brands fa-patreon', 'fa-brands fa-paypal', 'fa-brands fa-kickstarter', 'fa-brands fa-behance',
        'fa-brands fa-dribbble', 'fa-brands fa-figma', 'fa-brands fa-js', 'fa-brands fa-python', 'fa-brands fa-php', 'fa-brands fa-html5', 'fa-brands fa-css3-alt', 'fa-brands fa-react',
        'fa-brands fa-node-js', 'fa-brands fa-java', 'fa-brands fa-android', 'fa-brands fa-apple', 'fa-brands fa-windows', 'fa-brands fa-linux', 'fa-brands fa-bitcoin', 'fa-brands fa-ethereum',
    ];

    PE.isImageIcon = (value) => /^(https?:\/\/|\/uploads\/profile_media\/)[^\s"'<>`\\]+$/i.test(String(value || '').trim());

    /** HTML di un'icona (classe Font Awesome o immagine). */
    PE.iconHtml = (value, fallback = 'fa-solid fa-icons') => {
        const v = String(value || '').trim();
        if (PE.isImageIcon(v)) return `<img src="${PE.escape(v)}" alt="" class="pe-icon-img">`;
        const cls = /^[a-z0-9 -]+$/i.test(v) && v ? v : fallback;
        return `<i class="${PE.escape(cls)}" aria-hidden="true"></i>`;
    };

    PE.syncIcon = (wrap) => {
        const input = wrap.querySelector('input');
        const trigger = wrap.querySelector('.pe-icon-trigger');
        if (!input || !trigger) return;
        const value = input.value.trim();
        trigger.querySelector('.pe-icon-preview').innerHTML = PE.iconHtml(value, wrap.dataset.placeholderIcon);
        trigger.querySelector('.pe-icon-preview').classList.toggle('is-empty', !value);
        trigger.querySelector('.pe-icon-name').textContent = !value
            ? PE.t('Nessuna icona', 'No icon')
            : (PE.isImageIcon(value) ? PE.t('Immagine', 'Image') : value.replace(/^fa-(solid|brands|regular) fa-/, '').replace(/-/g, ' '));
    };

    const buildIconPicker = (wrap) => {
        const input = wrap.querySelector('input');
        const box = document.createElement('div');
        box.className = 'pe-iconpicker';
        box.innerHTML = `
            <div class="pe-input-icon"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" class="pe-input" placeholder="${PE.escape(PE.t('Cerca un\'icona (es. musica, github)', 'Search icons (e.g. music, github)'))}" autocomplete="off"></div>
            <div class="pe-iconpicker-grid" role="listbox"></div>
            <div class="pe-iconpicker-foot">
                <button type="button" class="pe-btn pe-btn-secondary pe-btn-sm" data-act="upload">${data.premium ? '' : '<i class="fa-solid fa-crown" aria-hidden="true"></i>'}<i class="fa-solid fa-upload" aria-hidden="true"></i><span>${PE.escape(PE.t('Carica immagine', 'Upload image'))}</span></button>
                <button type="button" class="pe-btn pe-btn-ghost pe-btn-sm" data-act="url"><i class="fa-solid fa-link" aria-hidden="true"></i><span>URL</span></button>
                <button type="button" class="pe-btn pe-btn-ghost pe-btn-sm" data-act="clear"><i class="fa-solid fa-xmark" aria-hidden="true"></i><span>${PE.escape(PE.t('Nessuna', 'None'))}</span></button>
            </div>
            <div class="pe-iconpicker-url" hidden>
                <input type="url" class="pe-input" placeholder="https://… ${PE.escape(PE.t('o', 'or'))} fa-solid fa-star">
                <button type="button" class="pe-btn pe-btn-primary pe-btn-sm">${PE.escape(PE.t('Usa', 'Use'))}</button>
            </div>`;

        const grid = box.querySelector('.pe-iconpicker-grid');
        const search = box.querySelector('input[type="search"]');
        const set = (value) => {
            input.value = value;
            PE.syncIcon(wrap);
            PE.emit(input);
        };
        const render = (query = '') => {
            const q = query.trim().toLowerCase().replace(/\s+/g, '-');
            const synonyms = { musica: 'music', cuore: 'heart', stella: 'star', casa: 'house', gioco: 'gamepad', giochi: 'gamepad', libro: 'book', foto: 'camera', video: 'video', codice: 'code', posta: 'envelope', telefono: 'phone', fuoco: 'fire', gatto: 'cat', cane: 'dog', sole: 'sun', luna: 'moon', regalo: 'gift', lucchetto: 'lock', corona: 'crown' };
            const term = synonyms[q] || q;
            const list = PE.ICONS.filter((icon) => !term || icon.includes(term));
            grid.innerHTML = list.length ? '' : `<p class="pe-help">${PE.escape(PE.t('Nessuna icona trovata. Puoi incollare una classe Font Awesome con “URL”.', 'No icons found. You can paste a Font Awesome class with “URL”.'))}</p>`;
            list.forEach((icon) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'pe-iconpicker-item' + (icon === input.value ? ' is-active' : '');
                b.title = icon.replace(/^fa-(solid|brands) fa-/, '');
                b.innerHTML = `<i class="${icon}" aria-hidden="true"></i>`;
                b.addEventListener('click', () => { set(icon); PE.closePopover(); });
                grid.appendChild(b);
            });
        };
        search.addEventListener('input', () => render(search.value));

        box.querySelector('[data-act="upload"]').addEventListener('click', async () => {
            if (!data.premium) { PE.closePopover(); PE.upsell(PE.t('Icone caricate', 'Uploaded icons')); return; }
            const file = await PE.pickFile('image/jpeg,image/png,image/webp,image/gif');
            if (!file) return;
            PE.closePopover();
            try {
                const json = await PE.uploadFile(file, { purpose: 'icon' });
                set(json.url);
            } catch (_) { /* avviso gia' mostrato */ }
        });
        const urlRow = box.querySelector('.pe-iconpicker-url');
        box.querySelector('[data-act="url"]').addEventListener('click', () => {
            urlRow.hidden = !urlRow.hidden;
            urlRow.querySelector('input').value = input.value;
            urlRow.querySelector('input').focus();
        });
        const urlInput = urlRow.querySelector('input');
        const useUrl = () => {
            const value = urlInput.value.trim();
            const isClass = /^fa-[a-z0-9 -]+$/i.test(value);
            if (value && !isClass && !PE.isImageIcon(value)) {
                urlInput.setCustomValidity(PE.t('Usa un link https:// a un\'immagine oppure una classe Font Awesome.', 'Use an https:// image link or a Font Awesome class.'));
                urlInput.reportValidity();
                return;
            }
            if (PE.isImageIcon(value) && !data.premium) { PE.closePopover(); PE.upsell(PE.t('Icone da immagine', 'Image icons')); return; }
            set(value);
            PE.closePopover();
        };
        urlInput.addEventListener('input', () => urlInput.setCustomValidity(''));
        urlInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') { event.preventDefault(); useUrl(); }
        });
        urlRow.querySelector('button').addEventListener('click', useUrl);
        box.querySelector('[data-act="clear"]').addEventListener('click', () => { set(''); PE.closePopover(); });

        render();
        setTimeout(() => search.focus(), 30);
        return box;
    };

    PE.initIcons = (root = document) => {
        PE.$$('[data-icon-field]', root).forEach((wrap) => {
            if (wrap.dataset.ready) return;
            wrap.dataset.ready = '1';
            const input = wrap.querySelector('input');
            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'pe-input pe-icon-trigger';
            trigger.disabled = !!input?.disabled;
            trigger.setAttribute('aria-haspopup', 'dialog');
            trigger.innerHTML = '<span class="pe-icon-preview"></span><span class="pe-icon-name"></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i>';
            wrap.appendChild(trigger);
            trigger.addEventListener('click', () => PE.openPopover(trigger, buildIconPicker(wrap), { className: 'pe-popover-icons' }));
            PE.syncIcon(wrap);
        });
    };

    // ── Media da URL o da caricare (immagini, video, cursori) ───────────────
    PE.syncMediaUrl = (wrap) => {
        const input = wrap.querySelector('input[type="hidden"], input[data-field]');
        const preview = wrap.querySelector('.pe-media-thumb');
        if (!input || !preview) return;
        const value = input.value.trim();
        const isVideo = /\.(mp4|webm)(\?|$)/i.test(value) || wrap.dataset.mediaKind === 'video';
        preview.classList.toggle('is-empty', !value);
        preview.innerHTML = !value
            ? `<i class="fa-solid ${wrap.dataset.purpose === 'block' && wrap.dataset.mediaKind === 'video' ? 'fa-film' : 'fa-image'}" aria-hidden="true"></i>`
            : (isVideo ? `<video src="${PE.escape(value)}" muted playsinline preload="metadata"></video>` : `<img src="${PE.escape(value)}" alt="">`);
        const img = preview.querySelector('img');
        if (img) img.addEventListener('error', () => preview.classList.add('is-broken'), { once: true });
        wrap.querySelector('.pe-media-name').textContent = value
            ? decodeURIComponent(value.split('/').pop().split('?')[0]).slice(0, 42)
            : PE.t('Nessun file', 'No file');
        wrap.querySelector('[data-act="remove"]').hidden = !value;
    };

    PE.initMediaUrls = (root = document) => {
        PE.$$('[data-media-url]', root).forEach((wrap) => {
            if (wrap.dataset.ready) return;
            wrap.dataset.ready = '1';
            const input = wrap.querySelector('input');
            const disabled = !!input?.disabled;
            const ui = document.createElement('div');
            ui.className = 'pe-media';
            ui.innerHTML = `
                <span class="pe-media-thumb"></span>
                <span class="pe-media-main">
                    <span class="pe-media-name"></span>
                    <span class="pe-media-actions">
                        <button type="button" class="pe-btn pe-btn-secondary pe-btn-sm" data-act="upload" ${disabled ? 'disabled' : ''}>${data.premium ? '' : '<i class="fa-solid fa-crown" aria-hidden="true"></i>'}<i class="fa-solid fa-upload" aria-hidden="true"></i><span>${PE.escape(PE.t('Carica', 'Upload'))}</span></button>
                        <button type="button" class="pe-btn pe-btn-ghost pe-btn-sm" data-act="url" ${disabled ? 'disabled' : ''}><i class="fa-solid fa-link" aria-hidden="true"></i><span>URL</span></button>
                        <button type="button" class="pe-btn pe-btn-ghost pe-btn-sm pe-btn-danger-text" data-act="remove" ${disabled ? 'disabled' : ''}><i class="fa-solid fa-trash" aria-hidden="true"></i><span class="visually-hidden">${PE.escape(PE.t('Rimuovi', 'Remove'))}</span></button>
                    </span>
                </span>
                <span class="pe-media-urlrow" hidden>
                    <input type="url" class="pe-input" placeholder="https://…">
                    <button type="button" class="pe-btn pe-btn-primary pe-btn-sm">${PE.escape(PE.t('Usa', 'Use'))}</button>
                </span>`;
            wrap.appendChild(ui);

            const set = (value) => {
                input.value = value;
                PE.syncMediaUrl(wrap);
                PE.emit(input);
            };

            ui.querySelector('[data-act="upload"]').addEventListener('click', async () => {
                if (!data.premium) { PE.upsell(PE.t('Caricamento dei file', 'File uploads')); return; }
                const file = await PE.pickFile(wrap.dataset.accept || 'image/*');
                if (!file) return;
                try {
                    const json = await PE.uploadFile(file, { purpose: wrap.dataset.purpose || 'icon' });
                    wrap.dispatchEvent(new CustomEvent('pe:uploaded', { detail: json, bubbles: true }));
                    set(json.url);
                } catch (_) { /* avviso gia' mostrato */ }
            });
            const urlRow = ui.querySelector('.pe-media-urlrow');
            ui.querySelector('[data-act="url"]').addEventListener('click', () => {
                urlRow.hidden = !urlRow.hidden;
                const field = urlRow.querySelector('input');
                field.value = input.value;
                if (!urlRow.hidden) field.focus();
            });
            const applyUrl = () => { set(urlRow.querySelector('input').value.trim()); urlRow.hidden = true; };
            urlRow.querySelector('button').addEventListener('click', applyUrl);
            urlRow.querySelector('input').addEventListener('keydown', (event) => {
                if (event.key === 'Enter') { event.preventDefault(); applyUrl(); }
            });
            ui.querySelector('[data-act="remove"]').addEventListener('click', () => set(''));
            PE.syncMediaUrl(wrap);
        });
    };

    // ── Condizioni di visibilita' (data-show-if) ────────────────────────────
    const fieldValue = (form, name, scope) => {
        if (scope) {
            const scoped = PE.$$(`[data-field="${name}"], [data-local="${name}"]`, scope);
            if (scoped.length) {
                const checkedRadio = scoped.find((el) => el.type === 'radio' && el.checked);
                if (checkedRadio) return checkedRadio.value;
                const el = scoped[scoped.length - 1];
                if (el.type === 'checkbox') return el.checked ? '1' : '0';
                return el.value;
            }
        }
        const els = PE.$$(`[name="${CSS.escape(name)}"]`, form);
        if (!els.length) return '';
        const radio = els.find((el) => el.type === 'radio');
        if (radio) return els.find((el) => el.type === 'radio' && el.checked)?.value ?? '';
        const box = els.find((el) => el.type === 'checkbox');
        if (box) return box.checked ? '1' : '0';
        return els[els.length - 1].value;
    };

    const evaluate = (expr, form, scope) => {
        const m = expr.match(/^([\w-]+)\s*(!=|=)?\s*(.*)$/);
        if (!m) return true;
        const [, name, op, raw] = m;
        const value = String(fieldValue(form, name, scope));
        if (!op) return value !== '' && value !== '0';
        const options = raw.split('|');
        return op === '=' ? options.includes(value) : !options.includes(value);
    };

    PE.updateShowIf = (root = document) => {
        const form = document.getElementById('profileEditForm');
        PE.$$('[data-show-if]', root).forEach((el) => {
            const scope = el.closest('.pe-item');
            const visible = el.dataset.showIf.split('&&').every((expr) => evaluate(expr.trim(), form, scope));
            el.hidden = !visible;
        });
    };

    // ── Tutto insieme ───────────────────────────────────────────────────────
    PE.initComponents = (root = document) => {
        PE.initColors(root);
        PE.initSliders(root);
        PE.initCounters(root);
        PE.initSelects(root);
        PE.initIcons(root);
        PE.initMediaUrls(root);
        PE.updateShowIf(root);
    };

    /** Riallinea l'aspetto dei componenti ai valori dei campi (dopo annulla, temi, preset). */
    PE.syncAll = (root = document) => {
        PE.$$('[data-color-field]', root).forEach(PE.syncColor);
        PE.$$('input.pe-range', root).forEach(PE.syncSlider);
        PE.$$('select.pe-select', root).forEach(PE.syncSelect);
        PE.$$('[data-icon-field]', root).forEach(PE.syncIcon);
        PE.$$('[data-media-url]', root).forEach(PE.syncMediaUrl);
        PE.$$('[data-counter]', root).forEach((input) => input.peSyncCounter?.());
        PE.updateShowIf(root);
    };
})();
