/*
 * Negozio, Merch e Download: filtri, ordinamento, menu "Ordina", pagina
 * prodotto, checkout finto, download e conto alla rovescia dei drop.
 *
 * I dati arrivano dalla pagina in <script id="shop-data">: il PHP ha gia'
 * disegnato le card, qui si decide solo quali mostrare e in che ordine.
 * Lo stato dei filtri sta nell'indirizzo (?q=&cat=&sort=), cosi' una
 * ricerca si puo' condividere e il tasto Indietro fa quello che ci si
 * aspetta.
 */
(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const readData = () => {
        try {
            return JSON.parse($('#shop-data')?.textContent || '{}');
        } catch {
            return {};
        }
    };

    const data = readData();
    const strings = data.strings || {};
    const lang = document.body?.dataset.shopLang === 'en' ? 'en' : 'it';
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    }[char]));

    // "Felpa" trova anche "félpa": si confrontano le lettere senza accenti.
    const normalize = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    /* ── Toast e copia ────────────────────────────────────────────────── */

    let toastTimer = null;

    const toast = (message) => {
        const box = $('[data-shop-toast]');
        if (!box || !message) return;
        box.textContent = message;
        box.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => box.classList.remove('is-visible'), 2400);
    };

    const copyText = async (text) => {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                return true;
            }
            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();
            const ok = document.execCommand('copy');
            area.remove();
            return ok;
        } catch {
            return false;
        }
    };

    /* ── Indirizzo della pagina ──────────────────────────────────────── */

    // replaceState e' intercettato dalla Rich Presence di Discord, che a ogni
    // chiamata manda un aggiornamento: mentre si scrive nella ricerca si
    // aspetta che la persona si fermi.
    let urlTimer = null;

    const writeParams = (params, immediate = false) => {
        clearTimeout(urlTimer);
        const apply = () => {
            const url = new URL(window.location.href);
            Object.entries(params).forEach(([key, value]) => {
                if (value === '' || value === null || value === undefined) url.searchParams.delete(key);
                else url.searchParams.set(key, value);
            });
            if (url.href !== window.location.href) {
                window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash);
            }
        };
        if (immediate) apply();
        else urlTimer = setTimeout(apply, 450);
    };

    /* ── Lista: filtri e ordinamento ─────────────────────────────────── */

    const initList = () => {
        const grid = $('[data-shop-grid]');
        if (!grid) return;

        const items = new Map((data.products || []).map((item) => [item.slug, item]));
        const cards = $$('[data-shop-item]', grid);
        const search = $('[data-shop-search]');
        const clear = $('[data-shop-search-clear]');
        const sort = $('[data-shop-sort]');
        const filters = $$('[data-category]');
        const results = $('[data-shop-results]');
        const empty = $('[data-shop-empty]');
        const toolbar = $('[data-shop-toolbar]');
        const defaultSort = sort?.options[0]?.value || 'featured';

        const params = new URL(window.location.href).searchParams;
        const state = {
            q: params.get('q') || '',
            cat: params.get('cat') || '',
            sort: params.get('sort') || defaultSort,
        };

        if (!filters.some((button) => button.dataset.category === state.cat)) state.cat = '';
        if (sort && !Array.from(sort.options).some((option) => option.value === state.sort)) state.sort = defaultSort;

        const comparators = {
            featured: (a, b) => (b.featured - a.featured) || (a.position - b.position),
            popular: (a, b) => (b.orders - a.orders) || (a.position - b.position),
            'price-asc': (a, b) => (a.price - b.price) || (a.position - b.position),
            'price-desc': (a, b) => (b.price - a.price) || (a.position - b.position),
            name: (a, b) => a.name.localeCompare(b.name, lang, { sensitivity: 'base' }),
            recent: (a, b) => (b.created - a.created) || (a.position - b.position),
        };

        const render = () => {
            const query = normalize(state.q);
            const compare = comparators[state.sort] || comparators.featured;

            // Si riordina sempre, anche tornando all'ordine di partenza: prima
            // "Ordine originale" lasciava le card dove le aveva messe
            // l'ultimo ordinamento.
            const sorted = cards.slice().sort((a, b) => {
                const itemA = items.get(a.dataset.shopItem);
                const itemB = items.get(b.dataset.shopItem);
                return itemA && itemB ? compare(itemA, itemB) : 0;
            });

            let visible = 0;
            sorted.forEach((card) => {
                const item = items.get(card.dataset.shopItem);
                const haystack = normalize(`${item?.name || ''} ${item?.variant || ''} ${item?.description || ''}`);
                const show = (!query || haystack.includes(query)) && (!state.cat || item?.category === state.cat);
                card.hidden = !show;
                if (show) visible++;
                grid.appendChild(card);
            });

            if (empty) empty.hidden = visible > 0;
            if (results) {
                const filtered = query !== '' || state.cat !== '';
                results.textContent = filtered
                    ? (visible === 1 ? strings.resultsOne : String(strings.resultsMany || '%d').replace('%d', visible))
                    : '';
            }
            if (clear) clear.hidden = state.q === '';

            filters.forEach((button) => {
                const active = button.dataset.category === state.cat;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        };

        const sync = (immediate = false) => {
            render();
            writeParams({
                q: state.q.trim(),
                cat: state.cat,
                sort: state.sort === defaultSort ? '' : state.sort,
            }, immediate);
        };

        if (search) {
            search.value = state.q;
            search.addEventListener('input', () => {
                state.q = search.value;
                sync();
            });
            search.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && search.value) {
                    event.stopPropagation();
                    state.q = '';
                    search.value = '';
                    sync(true);
                }
            });
        }

        clear?.addEventListener('click', () => {
            state.q = '';
            if (search) {
                search.value = '';
                search.focus();
            }
            sync(true);
        });

        if (sort) {
            sort.value = state.sort;
            sort.addEventListener('change', () => {
                state.sort = sort.value;
                sync(true);
            });
        }

        filters.forEach((button) => button.addEventListener('click', () => {
            state.cat = button.dataset.category || '';
            sync(true);
        }));

        $$('[data-shop-reset]').forEach((button) => button.addEventListener('click', () => {
            state.q = '';
            state.cat = '';
            if (search) search.value = '';
            sync(true);
            search?.focus();
        }));

        // La barra si "stacca" dallo sfondo solo quando resta attaccata in
        // alto, altrimenti sarebbe un riquadro dentro un riquadro.
        if (toolbar) {
            let ticking = false;
            const check = () => {
                ticking = false;
                const top = parseFloat(getComputedStyle(toolbar).top) || 0;
                const rect = toolbar.getBoundingClientRect();
                const panel = toolbar.parentElement?.getBoundingClientRect();
                toolbar.classList.toggle('is-stuck', rect.top <= top + 1 && (!panel || panel.top < top - 4));
            };
            window.addEventListener('scroll', () => {
                if (!ticking) {
                    ticking = true;
                    requestAnimationFrame(check);
                }
            }, { passive: true });
            check();
        }

        render();
    };

    /* ── Menu a tendina su misura ─────────────────────────────────────── */

    /*
     * La <select> stampata dal PHP resta nel DOM (nascosta) e continua a
     * essere la fonte del valore: il menu la aggiorna e le manda "change",
     * quindi filtri e ordinamento non si accorgono di niente. Senza
     * JavaScript si vede la select normale.
     *
     * Tastiera come in una listbox vera: frecce, Home/Fine, Invio/Spazio per
     * scegliere, Esc per chiudere, e una lettera salta alla voce che inizia
     * cosi'.
     */
    let selectCounter = 0;

    const enhanceSelect = (wrap) => {
        const select = $('select', wrap);
        if (!select || wrap.dataset.enhanced === '1') return;
        wrap.dataset.enhanced = '1';

        const uid = `shop-select-${++selectCounter}`;
        const label = $('label', wrap)?.textContent.trim() || '';
        const options = Array.from(select.options);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'shop-select__button';
        button.id = `${uid}-button`;
        button.setAttribute('aria-haspopup', 'listbox');
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-controls', `${uid}-list`);
        button.innerHTML = `
            <i class="fa-solid fa-arrow-down-wide-short shop-select__icon" aria-hidden="true"></i>
            <span class="shop-select__label">${escapeHtml(label)}</span>
            <span class="shop-select__value"></span>
            <i class="fa-solid fa-chevron-down shop-select__chevron" aria-hidden="true"></i>`;

        const list = document.createElement('ul');
        list.className = 'shop-select__menu';
        list.id = `${uid}-list`;
        list.setAttribute('role', 'listbox');
        list.setAttribute('tabindex', '-1');
        list.setAttribute('aria-label', label);
        list.innerHTML = options.map((option, index) => `
            <li class="shop-select__option" role="option" id="${uid}-opt-${index}" data-value="${escapeHtml(option.value)}" style="--i:${index}" aria-selected="false">
                <span>${escapeHtml(option.textContent)}</span>
                <i class="fa-solid fa-check" aria-hidden="true"></i>
            </li>`).join('');

        // La select vera resta nel form (e tiene il valore), ma non si
        // raggiunge piu' con Tab: il bottone la sostituisce.
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

        wrap.classList.add('is-enhanced');
        wrap.append(button, list);

        const items = $$('.shop-select__option', list);
        const valueNode = $('.shop-select__value', button);
        let active = 0;
        let typed = '';
        let typedTimer = null;

        const sync = () => {
            const index = Math.max(0, options.findIndex((option) => option.value === select.value));
            valueNode.textContent = options[index]?.textContent || '';
            items.forEach((item, i) => item.setAttribute('aria-selected', i === index ? 'true' : 'false'));
            return index;
        };

        const setActive = (index, scroll = true) => {
            active = (index + items.length) % items.length;
            items.forEach((item, i) => item.classList.toggle('is-active', i === active));
            list.setAttribute('aria-activedescendant', items[active].id);
            if (scroll) items[active].scrollIntoView({ block: 'nearest' });
        };

        const isOpen = () => wrap.classList.contains('is-open');

        const open = () => {
            if (isOpen()) return;
            // Un menu alla volta.
            $$('[data-shop-select].is-open').forEach((other) => other !== wrap && other.dispatchEvent(new CustomEvent('shop-select:close')));
            wrap.classList.add('is-open');
            button.setAttribute('aria-expanded', 'true');
            setActive(sync(), false);
            requestAnimationFrame(() => list.focus({ preventScroll: true }));
        };

        const close = (focusButton = true) => {
            if (!isOpen()) return;
            wrap.classList.remove('is-open');
            button.setAttribute('aria-expanded', 'false');
            list.removeAttribute('aria-activedescendant');
            if (focusButton) button.focus({ preventScroll: true });
        };

        const choose = (index) => {
            const value = options[index]?.value;
            if (value === undefined) return;
            const changed = select.value !== value;
            select.value = value;
            sync();
            close();
            if (changed) select.dispatchEvent(new Event('change', { bubbles: true }));
        };

        button.addEventListener('click', () => (isOpen() ? close() : open()));

        button.addEventListener('keydown', (event) => {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
                event.preventDefault();
                open();
                if (event.key === 'ArrowUp') setActive(items.length - 1);
            }
        });

        list.addEventListener('keydown', (event) => {
            switch (event.key) {
                case 'ArrowDown': event.preventDefault(); setActive(active + 1); break;
                case 'ArrowUp': event.preventDefault(); setActive(active - 1); break;
                case 'Home': event.preventDefault(); setActive(0); break;
                case 'End': event.preventDefault(); setActive(items.length - 1); break;
                case 'Enter':
                case ' ': event.preventDefault(); choose(active); break;
                case 'Escape': event.preventDefault(); event.stopPropagation(); close(); break;
                case 'Tab': close(false); break;
                default:
                    if (event.key.length === 1 && /\S/.test(event.key)) {
                        typed += event.key.toLowerCase();
                        clearTimeout(typedTimer);
                        typedTimer = setTimeout(() => { typed = ''; }, 600);
                        const match = options.findIndex((option) => normalize(option.textContent).startsWith(normalize(typed)));
                        if (match >= 0) setActive(match);
                    }
            }
        });

        items.forEach((item, index) => {
            item.addEventListener('mousemove', () => { if (active !== index) setActive(index, false); });
            item.addEventListener('click', () => choose(index));
        });

        document.addEventListener('pointerdown', (event) => {
            if (isOpen() && !wrap.contains(event.target)) close(false);
        });
        wrap.addEventListener('shop-select:close', () => close(false));
        list.addEventListener('focusout', (event) => {
            if (isOpen() && !wrap.contains(event.relatedTarget)) close(false);
        });

        // Se qualcuno cambia la select da codice (filtri dall'indirizzo),
        // il menu si rimette in pari.
        select.addEventListener('change', sync);
        sync();
    };

    const initCustomSelects = () => $$('[data-shop-select]').forEach(enhanceSelect);

    /* ── Pagina prodotto ─────────────────────────────────────────────── */

    const initProductPage = () => {
        if (data.kind !== 'product') return;

        // Galleria: la miniatura scelta prende il posto della foto grande.
        const main = $('[data-gallery-main]');
        const thumbs = $$('[data-gallery-thumb]');
        thumbs.forEach((thumb) => thumb.addEventListener('click', () => {
            if (!main || main.getAttribute('src') === thumb.dataset.galleryThumb) return;
            main.classList.add('is-swapping');
            window.setTimeout(() => {
                main.src = thumb.dataset.galleryThumb;
                main.classList.remove('is-swapping');
            }, reduceMotion ? 0 : 140);
            thumbs.forEach((other) => {
                const on = other === thumb;
                other.classList.toggle('is-active', on);
                other.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }));

        // La taglia scelta qui arriva gia' selezionata nel checkout.
        const buy = $('[data-product-buy]');
        $$('[data-product-size]').forEach((input) => input.addEventListener('change', () => {
            if (!buy || !data.buyUrl) return;
            const url = new URL(data.buyUrl, window.location.origin);
            url.searchParams.set('taglia', input.value);
            buy.href = url.pathname + url.search;
        }));
    };

    /* ── Checkout finto ──────────────────────────────────────────────── */

    const initCheckout = () => {
        const form = $('[data-shop-checkout]');
        if (!form) return;

        const qty = $('[data-qty]', form);
        const subtotal = $('[data-subtotal]', form);
        const total = $('[data-total]', form);
        const error = $('[data-checkout-error]', form);
        const submit = $('[data-checkout-submit]', form);
        const unit = Number(qty?.dataset.unitPrice || 0);

        const money = (value) => value.toLocaleString(lang === 'en' ? 'en-IE' : 'it-IT', {
            style: 'currency',
            currency: 'EUR',
        });

        const clampQty = () => {
            const value = Math.min(99, Math.max(1, parseInt(qty.value, 10) || 1));
            qty.value = String(value);
            return value;
        };

        const updateTotals = () => {
            if (!qty) return;
            const amount = money(unit * clampQty());
            if (subtotal) subtotal.textContent = amount;
            if (total) total.textContent = amount;
        };

        $$('[data-qty-step]', form).forEach((button) => button.addEventListener('click', () => {
            qty.value = String((parseInt(qty.value, 10) || 1) + Number(button.dataset.qtyStep));
            updateTotals();
        }));
        qty?.addEventListener('change', updateTotals);
        qty?.addEventListener('input', () => {
            if (qty.value !== '') updateTotals();
        });

        const showError = (message) => {
            if (!error) return;
            error.textContent = message;
            error.hidden = !message;
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            showError('');
            form.classList.add('was-validated');

            const sizeInputs = $$('input[name="taglia"]', form);
            if (sizeInputs.length && !sizeInputs.some((input) => input.checked)) {
                showError(strings.chooseSize);
                sizeInputs[0].focus();
                return;
            }

            if (!form.checkValidity()) {
                const firstInvalid = $$('input', form).find((input) => !input.checkValidity());
                firstInvalid?.focus();
                firstInvalid?.reportValidity?.();
                return;
            }

            const label = submit?.querySelector('span');
            submit?.classList.add('is-loading');
            if (label) label.textContent = strings.processing;

            const csrf = $('input[name="csrf_token"]', form)?.value || '';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({
                        csrf_token: csrf,
                        p: $('input[name="p"]', form)?.value || '',
                        lang,
                        qty: clampQty(),
                        taglia: sizeInputs.find((input) => input.checked)?.value || '',
                    }),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || !payload.ok) throw new Error(payload.message || strings.orderError);
                window.location.assign(payload.redirect || `/${lang}/confirm`);
            } catch (err) {
                showError(err.message || strings.orderError);
                submit?.classList.remove('is-loading');
                if (label) label.textContent = strings.placeOrder;
            }
        });

        updateTotals();
    };

    /* ── Download ────────────────────────────────────────────────────── */

    const initDownloads = () => {
        $$('[data-download-go]').forEach((link) => link.addEventListener('click', () => {
            if (link.target !== '_blank') toast(strings.downloadStarted);
        }));

        $$('[data-copy-page]').forEach((button) => button.addEventListener('click', async () => {
            const url = new URL(window.location.href);
            url.search = '';
            url.hash = '';
            const ok = await copyText(url.toString());
            toast(ok ? strings.linkCopied : strings.copyFailed);
        }));
    };

    /* ── Conto alla rovescia dei drop ────────────────────────────────── */

    const initCountdowns = () => {
        const blocks = $$('[data-countdown]');
        if (!blocks.length) return;

        const units = lang === 'en' ? ['d', 'h', 'm', 's'] : ['g', 'h', 'm', 's'];

        const format = (ms) => {
            const total = Math.max(0, Math.floor(ms / 1000));
            const parts = [Math.floor(total / 86400), Math.floor(total / 3600) % 24, Math.floor(total / 60) % 60, total % 60];
            const start = parts.findIndex((value) => value > 0);
            return parts
                .map((value, index) => `${index > 0 ? String(value).padStart(2, '0') : value}${units[index]}`)
                .slice(start < 0 ? 3 : Math.min(start, 2))
                .join(' ');
        };

        let reloaded = false;
        const tick = () => {
            blocks.forEach((block) => {
                const target = Date.parse(block.dataset.countdown || '');
                const output = block.matches('[data-countdown-output]') ? block : $('[data-countdown-output]', block);
                if (!output || Number.isNaN(target)) return;

                const left = target - Date.now();
                output.textContent = format(left);

                // Il drop e' uscito: la pagina ricaricata mostra i prodotti.
                if (left <= 0 && !reloaded && block.classList.contains('shop-soon')) {
                    reloaded = true;
                    setTimeout(() => window.location.reload(), 1200);
                }
            });
        };

        tick();
        setInterval(tick, 1000);
    };

    const start = () => {
        initList();
        // Dopo initList: la select ha gia' il valore letto dall'indirizzo.
        initCustomSelects();
        initProductPage();
        initCheckout();
        initDownloads();
        initCountdowns();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
